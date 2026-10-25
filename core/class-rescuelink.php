<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Rescue_Link to this file name.
/**
 * Rescue_Link class
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

use ZeroSpam\Core\Front\Block_Page;
use ZeroSpam\Includes\Email;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Emailed rescue link for administrators who blocked themselves.
 *
 * The blocked page (Access::run_block_check()) has a form where a visitor
 * can ask for a rescue link. It posts to admin-post.php, which Access never
 * checks for this action (see is_rescue_post()). That is safe because the
 * handler can only ever email an address that already belongs to a site
 * administrator, it answers every request the same way, and it is rate
 * limited per IP before it does anything else, then per address and per
 * site.
 *
 * The email is never sent during the request. Every valid request (for an
 * administrator's address or not) does the same work: it updates the same
 * limit counters and schedules one WP-Cron event, whose payload names the
 * recipient (empty when nothing is to be sent). The event creates the token
 * and sends the email. When WP-Cron is disabled (DISABLE_WP_CRON, unless
 * the `zerospam_rescue_email_via_cron` filter says otherwise), the payload is
 * handled on `shutdown` instead, after the response is finished where the
 * server allows it (PHP-FPM, LiteSpeed); on other servers that fallback
 * makes a request that sends an email take longer.
 *
 * The emailed link carries a random single-use token that expires after
 * 15 minutes; only its SHA-256 hash is stored. Opening the link (a GET,
 * handled in Access::init()) only shows a "Let me back in" button: email
 * security scanners open links in emails on their own, and must not use up
 * the token. The button POSTs the token (no nonce: the token is the secret),
 * which uses it, sets the same one-hour rescue cookie as the
 * ZEROSPAM_RESCUE_KEY constant and sends the visitor to the login page. The
 * link may be opened from another IP than the one that asked for it (people
 * often open email on another device); both IPs are written to the
 * detection log. A used or expired link shows the "This link has expired"
 * page, with the form to ask for a new one.
 *
 * The pages are rendered by Front\Block_Page; the email is HTML with a
 * plain-text alternative (Includes\Email).
 *
 * The `zerospam_rescue_form_enabled` filter (default true) hides the form
 * from the blocked page and makes the request handler send nothing. Links
 * already sent keep working until they expire.
 */
final class Rescue_Link {

	/**
	 * The admin-post.php action of the rescue form.
	 *
	 * @var string
	 */
	const ACTION = 'zerospam_rescue_request';

	/**
	 * Nonce action of the rescue form.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'zerospam_rescue_request';

	/**
	 * Nonce field name of the rescue form.
	 *
	 * @var string
	 */
	const NONCE_FIELD = 'zerospam_rescue_nonce';

	/**
	 * Email field name of the rescue form.
	 *
	 * @var string
	 */
	const EMAIL_FIELD = 'zerospam_rescue_email';

	/**
	 * Query parameter that carries the token in the emailed link.
	 *
	 * @var string
	 */
	const TOKEN_PARAM = 'zerospam_rescue_token';

	/**
	 * Transient name prefix for a token, followed by the token's SHA-256 hash.
	 *
	 * @var string
	 */
	const TOKEN_TRANSIENT_PREFIX = 'zerospam_rescue_token_';

	/**
	 * How long a link works, in seconds.
	 *
	 * @var int
	 */
	const TOKEN_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Emails sent per site per hour.
	 *
	 * @var int
	 */
	const SITE_LIMIT = 10;

	/**
	 * Emails sent to one address per hour, so one inbox cannot use up the site's quota.
	 *
	 * @var int
	 */
	const RECIPIENT_LIMIT = 3;

	/**
	 * Transient name prefix that counts the emails to one address this hour, followed by the address's SHA-256 hash.
	 *
	 * @var string
	 */
	const RECIPIENT_LIMIT_TRANSIENT_PREFIX = 'zerospam_rescue_to_';

	/**
	 * WP-Cron hook that sends a rescue email.
	 *
	 * @var string
	 */
	const SEND_HOOK = 'zerospam_send_rescue_link';

	/**
	 * Transient name prefix for a scheduled email's payload, followed by its random job key.
	 *
	 * @var string
	 */
	const JOB_TRANSIENT_PREFIX = 'zerospam_rescue_job_';

	/**
	 * Transient that counts the emails sent this hour.
	 *
	 * @var string
	 */
	const SITE_LIMIT_TRANSIENT = 'zerospam_rescue_site_sends';

	/**
	 * Requests accepted per IP per hour.
	 *
	 * @var int
	 */
	const IP_LIMIT = 5;

	/**
	 * Transient name prefix that counts one IP's requests this hour.
	 *
	 * @var string
	 */
	const IP_LIMIT_TRANSIENT_PREFIX = 'zerospam_rescue_ip_';

	/**
	 * Detection log type of a used link.
	 *
	 * @var string
	 */
	const LOG_TYPE = 'rescue';

	/**
	 * ID of the email field.
	 *
	 * @var string
	 */
	const FIELD_ID = 'zerospam-rescue-email';

	/**
	 * Job keys waiting for `shutdown` (only when WP-Cron is disabled).
	 *
	 * @var string[]
	 */
	private static array $queue = array();

	/**
	 * Registers the form handler and the log type.
	 */
	public function __construct() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_request' ) );
		add_action( self::SEND_HOOK, array( self::class, 'run_job' ) );
		add_filter( 'zerospam_types', array( self::class, 'types' ) );
	}

	/**
	 * Adds the rescue log type.
	 *
	 * @param array $types Detection types.
	 * @return array
	 */
	public static function types( $types ) {
		$types[ self::LOG_TYPE ] = array(
			'label' => __( 'Rescue link used', 'zero-spam' ),
			'color' => '#00a32a',
		);

		return $types;
	}

	/**
	 * Whether this is the rescue form's admin-post.php request.
	 *
	 * Checks the loaded admin screen, not the URL, so adding the action to
	 * any other URL exempts nothing.
	 */
	public static function is_rescue_post(): bool {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; handle_request() verifies the nonce.
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return is_admin() && 'admin-post.php' === $pagenow && self::ACTION === $action;
	}

	/**
	 * Whether the blocked page offers the rescue form.
	 */
	public static function form_enabled(): bool {
		/**
		 * Filters whether the blocked page offers the "Help for site owners" rescue form.
		 *
		 * When false the form is not shown and form submissions send nothing.
		 *
		 * @since 5.7.14
		 *
		 * @param bool $enabled Whether the form is offered. Default true.
		 */
		return (bool) apply_filters( 'zerospam_rescue_form_enabled', true );
	}

	/**
	 * Handles a rescue form submission and ends the request.
	 *
	 * Every valid submission gets the same answer whether or not an email is
	 * sent, so the form cannot be used to find out who the administrators are.
	 */
	public static function handle_request(): void {
		if ( ! self::form_enabled() ) {
			self::respond_sent();
			return;
		}

		$ip = self::visitor_ip();

		// No usable visitor IP, no shared bucket: refuse. Then the per-IP limit,
		// before any other work: a flood costs one transient read and write per request.
		if ( '' === $ip || ! self::take( self::IP_LIMIT_TRANSIENT_PREFIX . md5( $ip ), self::IP_LIMIT ) ) {
			self::respond_sent();
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) && is_string( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::respond_form( __( 'This form has expired. Please enter the address again.', 'zero-spam' ), '', 403 );
			return;
		}

		$submitted = isset( $_POST[ self::EMAIL_FIELD ] ) && is_string( $_POST[ self::EMAIL_FIELD ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ self::EMAIL_FIELD ] ) ) ) : '';
		$email     = sanitize_email( $submitted );
		if ( '' === $email || ! is_email( $email ) ) {
			self::respond_form( __( 'Enter an email address, for example name@example.com.', 'zero-spam' ), $submitted, 400 );
			return;
		}

		$recipient = self::administrator_email( $email );

		// The same reads and writes for every address; only an email that will be sent is counted.
		$address_key    = self::RECIPIENT_LIMIT_TRANSIENT_PREFIX . hash( 'sha256', strtolower( $email ) );
		$address_window = self::window( $address_key );
		$site_window    = self::window( self::SITE_LIMIT_TRANSIENT );
		$send           = null !== $recipient && $address_window['count'] < self::RECIPIENT_LIMIT && $site_window['count'] < self::SITE_LIMIT;

		self::save_window( $address_key, $address_window, $send );
		self::save_window( self::SITE_LIMIT_TRANSIENT, $site_window, $send );

		self::schedule( $send ? (string) $recipient : '', $ip );

		self::respond_sent();
	}

	/**
	 * Schedules the email (or, for addresses that get none, the same empty job).
	 *
	 * The payload never holds the token: run_job() creates it.
	 *
	 * @param string $recipient Address to send to, or '' to send nothing.
	 * @param string $ip        IP that asked for the link.
	 */
	private static function schedule( string $recipient, string $ip ): void {
		$job = bin2hex( random_bytes( 16 ) );

		set_transient(
			self::JOB_TRANSIENT_PREFIX . $job,
			array(
				'to' => $recipient,
				'ip' => $ip,
			),
			HOUR_IN_SECONDS
		);

		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && true === constant( 'DISABLE_WP_CRON' );

		/**
		 * Filters whether rescue emails are sent by a WP-Cron event (true) or on `shutdown` (false).
		 *
		 * Default: WP-Cron unless DISABLE_WP_CRON is true. A site that runs
		 * wp-cron.php from a system cron can return true; the link is created
		 * when the event runs, so its 15 minutes start then.
		 *
		 * @since 5.7.14
		 *
		 * @param bool $use_cron Whether to use WP-Cron.
		 */
		if ( ! apply_filters( 'zerospam_rescue_email_via_cron', ! $cron_disabled ) ) {
			self::$queue[] = $job;
			if ( false === has_action( 'shutdown', array( self::class, 'send_queued' ) ) ) {
				add_action( 'shutdown', array( self::class, 'send_queued' ) );
			}

			return;
		}

		wp_schedule_single_event( time(), self::SEND_HOOK, array( $job ) );
		spawn_cron();
	}

	/**
	 * Runs a scheduled rescue email job once (the WP-Cron event).
	 *
	 * @param string $job Job key.
	 */
	public static function run_job( $job ): void {
		if ( ! is_string( $job ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/', $job ) ) {
			return;
		}

		$payload = get_transient( self::JOB_TRANSIENT_PREFIX . $job );
		delete_transient( self::JOB_TRANSIENT_PREFIX . $job );

		if ( ! is_array( $payload ) || empty( $payload['to'] ) || ! is_string( $payload['to'] ) ) {
			return;
		}

		self::send( $payload['to'], isset( $payload['ip'] ) ? (string) $payload['ip'] : '' );
	}

	/**
	 * Runs the jobs queued for `shutdown` (only when WP-Cron is disabled).
	 *
	 * Finishes the response first where the server allows it (PHP-FPM,
	 * LiteSpeed), as wp-cron.php does. Elsewhere the visitor waits for the
	 * mail server, which is why WP-Cron is preferred.
	 */
	public static function send_queued(): void {
		$queue       = self::$queue;
		self::$queue = array();

		if ( ! $queue ) {
			return;
		}

		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		} elseif ( function_exists( 'litespeed_finish_request' ) ) {
			litespeed_finish_request();
		}

		foreach ( $queue as $job ) {
			self::run_job( $job );
		}
	}

	/**
	 * Whether a token from an emailed link is valid, without using it.
	 *
	 * @param string $token Token from the link.
	 */
	public static function peek_token( string $token ): bool {
		$stored = self::stored_token( $token );

		return null !== $stored && (int) $stored['data']['expires'] >= time();
	}

	/**
	 * Uses a token from an emailed link, once.
	 *
	 * @param string $token Token from the link.
	 * @return array{ip: string, time: int}|null The IP that asked for the link and when, or null when the token is not valid.
	 */
	public static function redeem_token( string $token ): ?array {
		$stored = self::stored_token( $token );
		if ( null === $stored ) {
			return null;
		}

		// Single use: only the request that deletes the token may use it.
		if ( ! delete_transient( $stored['key'] ) || (int) $stored['data']['expires'] < time() ) {
			return null;
		}

		return array(
			'ip'   => isset( $stored['data']['ip'] ) ? (string) $stored['data']['ip'] : '',
			'time' => isset( $stored['data']['time'] ) ? (int) $stored['data']['time'] : 0,
		);
	}

	/**
	 * Looks up a token's stored record.
	 *
	 * @param string $token Token from the link.
	 * @return array{key: string, data: array<string, mixed>}|null Transient name and record, or null.
	 */
	private static function stored_token( string $token ): ?array {
		if ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $token ) ) {
			return null;
		}

		$hash   = hash( 'sha256', $token );
		$key    = self::TOKEN_TRANSIENT_PREFIX . $hash;
		$stored = get_transient( $key );

		if (
			! is_array( $stored ) ||
			! isset( $stored['hash'], $stored['expires'] ) ||
			! is_string( $stored['hash'] ) ||
			! hash_equals( $stored['hash'], $hash )
		) {
			return null;
		}

		return array(
			'key'  => $key,
			'data' => $stored,
		);
	}

	/**
	 * Whether a value has the shape of a token (64 hexadecimal characters), valid or not.
	 *
	 * @param string $token Value from the link or the "Let me back in" form.
	 */
	public static function is_token_format( string $token ): bool {
		return 1 === preg_match( '/\A[a-f0-9]{64}\z/', $token );
	}

	/**
	 * Ends the request with the page an emailed link opens: one "Let me back in" button.
	 *
	 * @param string $token Token from the link (valid, see peek_token()).
	 */
	public static function show_confirm_page( string $token ): void {
		Block_Page::render(
			'rescue-confirm',
			array(
				'site_name' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
				'action'    => home_url( '/' ),
				'field'     => self::TOKEN_PARAM,
				'token'     => $token,
			),
			__( 'Let me back in', 'zero-spam' ),
			200
		);
	}

	/**
	 * Ends the request with the "This link has expired" page, which offers the form to ask for a new link.
	 */
	public static function show_expired_page(): void {
		Block_Page::render(
			'rescue-expired',
			array(
				'rescue' => self::form_enabled() ? self::form_args( '', '', true ) : null,
			),
			__( 'This link has expired', 'zero-spam' ),
			403
		);
	}

	/**
	 * Logs the use of a link in the detection log, without the token.
	 *
	 * @param string $requested_ip IP that asked for the link.
	 */
	public static function log_use( string $requested_ip ): void {
		$strip_token = static function ( $record ) {
			if ( is_array( $record ) && isset( $record['page_url'] ) ) {
				$record['page_url'] = remove_query_arg( self::TOKEN_PARAM, (string) $record['page_url'] );
			}

			return $record;
		};

		add_filter( 'zerospam_log_record', $strip_token, PHP_INT_MAX );
		\ZeroSpam\Includes\DB::log(
			self::LOG_TYPE,
			array(
				'requested_ip' => $requested_ip,
				'used_ip'      => self::visitor_ip(),
			)
		);
		remove_filter( 'zerospam_log_record', $strip_token, PHP_INT_MAX );
	}

	/**
	 * Arguments of the "Help for site owners" rescue form (includes/templates/front/parts/rescue-form.php).
	 *
	 * Plain HTML that works without JavaScript.
	 *
	 * @param string $error Error to show for the email field.
	 * @param string $value Value of the email field.
	 * @param bool   $open  Whether the section starts expanded.
	 * @return array<string, mixed>
	 */
	public static function form_args( string $error = '', string $value = '', bool $open = false ): array {
		return array(
			'open'        => $open || '' !== $error,
			'error'       => $error,
			'value'       => $value,
			'action'      => admin_url( 'admin-post.php' ),
			'field_id'    => self::FIELD_ID,
			'field_name'  => self::EMAIL_FIELD,
			'action_name' => self::ACTION,
			'nonce_field' => self::NONCE_FIELD,
			'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
			'faq_url'     => 'https://wordpress.org/plugins/zero-spam/#faq',
			'placeholder' => 'name@example.com',
			'help_id'     => self::FIELD_ID . '-help',
			'error_id'    => self::FIELD_ID . '-error',
		);
	}

	/**
	 * Address to send the link to, when the submitted one is an administrator's.
	 *
	 * Matches the site's administration email address or the address of a
	 * user who can manage options (on a network, also any super admin),
	 * ignoring letter case.
	 *
	 * @param string $email Submitted address.
	 * @return string|null The stored address, or null.
	 */
	private static function administrator_email( string $email ): ?string {
		$wanted      = strtolower( $email );
		$admin_email = (string) get_option( 'admin_email' );

		if ( '' !== $admin_email && hash_equals( strtolower( $admin_email ), $wanted ) ) {
			return $admin_email;
		}

		$user = get_user_by( 'email', $email );
		if (
			$user instanceof \WP_User &&
			hash_equals( strtolower( (string) $user->user_email ), $wanted ) &&
			( user_can( $user, 'manage_options' ) || ( is_multisite() && is_super_admin( $user->ID ) ) )
		) {
			return (string) $user->user_email;
		}

		return null;
	}

	/**
	 * Creates a single-use token that works for 15 minutes and stores only its hash.
	 *
	 * @param string $ip IP that asked for the link.
	 * @return string The token, for the emailed link only.
	 */
	public static function issue_token( string $ip ): string {
		$token = bin2hex( random_bytes( 32 ) );
		$hash  = hash( 'sha256', $token );
		$now   = time();

		set_transient(
			self::TOKEN_TRANSIENT_PREFIX . $hash,
			array(
				'hash'    => $hash,
				'ip'      => $ip,
				'time'    => $now,
				'expires' => $now + self::TOKEN_TTL,
			),
			self::TOKEN_TTL
		);

		return $token;
	}

	/**
	 * Creates a token and emails the link (HTML with a plain-text alternative).
	 *
	 * @param string $recipient Administrator address.
	 * @param string $ip        IP that asked for the link.
	 */
	private static function send( string $recipient, string $ip ): void {
		$token     = self::issue_token( $ip );
		$link      = home_url( '/?' . self::TOKEN_PARAM . '=' . $token );
		$site_name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$from_ip   = '' === $ip ? __( 'an unknown IP address', 'zero-spam' ) : $ip;

		Email::send(
			$recipient,
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Your Zero Spam rescue link', 'zero-spam' ),
				$site_name
			),
			array(
				'heading' => __( 'Your rescue link', 'zero-spam' ),
				'blocks'  => array(
					array(
						'type' => 'text',
						/* translators: 1: site name, 2: site address, 3: IP address the request came from */
						'text' => __( 'Someone asked for a rescue link for %1$s (%2$s) from IP address %3$s.', 'zero-spam' ),
						'args' => array(
							array(
								'text'  => $site_name,
								'style' => 'strong',
							),
							home_url( '/' ),
							array(
								'text'  => $from_ip,
								'style' => '' === $ip ? '' : 'mono',
							),
						),
					),
					array(
						'type' => 'text',
						'text' => __( 'Open this link to let your browser in for one hour, then log in:', 'zero-spam' ),
					),
					array(
						'type'  => 'button',
						'label' => __( 'Let me back in', 'zero-spam' ),
						'url'   => $link,
						'paste' => true,
					),
					array(
						'type' => 'text',
						'size' => 'small',
						'text' => __( 'The link works once and expires in 15 minutes. It works on any device, not only the one that asked for it.', 'zero-spam' ),
					),
					array(
						'type' => 'text',
						'size' => 'small',
						'text' => __( 'After you log in, add your IP address to the IP Allowlist under Zero Spam → Rules so you aren\'t blocked again.', 'zero-spam' ),
					),
					array(
						'type' => 'text',
						'size' => 'small',
						'text' => __( 'If you didn\'t ask for this, you can ignore this email. Nobody can use the link without access to this inbox.', 'zero-spam' ),
					),
				),
			)
		);
	}

	/**
	 * Counts one use against an hourly limit.
	 *
	 * @param string $key   Transient name.
	 * @param int    $limit Uses allowed per hour.
	 * @return bool False when the limit is already reached.
	 */
	private static function take( string $key, int $limit ): bool {
		$window = self::window( $key );

		if ( $window['count'] >= $limit ) {
			return false;
		}

		self::save_window( $key, $window, true );

		return true;
	}

	/**
	 * Reads an hourly counter.
	 *
	 * @param string $key Transient name.
	 * @return array{count: int, start: int}
	 */
	private static function window( string $key ): array {
		$now    = time();
		$window = get_transient( $key );

		if ( ! is_array( $window ) || ! isset( $window['count'], $window['start'] ) || (int) $window['start'] + HOUR_IN_SECONDS <= $now ) {
			return array(
				'count' => 0,
				'start' => $now,
			);
		}

		return array(
			'count' => (int) $window['count'],
			'start' => (int) $window['start'],
		);
	}

	/**
	 * Writes an hourly counter back, counting one more use or not.
	 *
	 * @param string                        $key       Transient name.
	 * @param array{count: int, start: int} $window    Counter from window().
	 * @param bool                          $increment Whether to count one more use.
	 */
	private static function save_window( string $key, array $window, bool $increment ): void {
		if ( $increment ) {
			++$window['count'];
		}

		set_transient( $key, $window, max( 1, $window['start'] + HOUR_IN_SECONDS - time() ) );
	}

	/**
	 * Visitor IP, or an empty string.
	 */
	private static function visitor_ip(): string {
		$ip = User::get_ip();

		return is_string( $ip ) ? $ip : '';
	}

	/**
	 * Ends the request with the answer every valid submission gets.
	 */
	private static function respond_sent(): void {
		Block_Page::render( 'rescue-sent', array(), __( 'Check your email', 'zero-spam' ), 200 );
	}

	/**
	 * Ends the request with the form again, open, and an error for the email field.
	 *
	 * @param string $error Error message.
	 * @param string $value Submitted value.
	 * @param int    $code  HTTP status.
	 */
	private static function respond_form( string $error, string $value, int $code ): void {
		$message = Settings::get_settings( 'blocked_message' );

		Block_Page::render(
			'form-error',
			array(
				'message' => is_string( $message ) && '' !== $message ? $message : __( 'Your IP address has been blocked due to detected spam/malicious activity.', 'zero-spam' ),
				'rescue'  => self::form_args( $error, $value, true ),
			),
			__( 'Help for site owners', 'zero-spam' ),
			$code
		);
	}
}
