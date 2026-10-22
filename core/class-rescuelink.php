<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Rescue_Link to this file name.
/**
 * Rescue_Link class
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

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
 * detection log.
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
	 * Ends the request with the page an emailed link opens: one "Let me back in" button.
	 *
	 * @param string $token Token from the link (valid, see peek_token()).
	 */
	public static function show_confirm_page( string $token ): void {
		$html  = self::focus_style();
		$html .= '<h1>' . esc_html__( 'Let me back in', 'zero-spam' ) . '</h1>';
		$html .= '<div class="zerospam-rescue">';
		$html .= '<p>' . sprintf(
			/* translators: %s: site name */
			esc_html__( 'This Zero Spam rescue link lets this browser into %s for one hour, so you can log in.', 'zero-spam' ),
			'<strong>' . esc_html( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ) . '</strong>'
		) . '</p>';
		$html .= '<form method="post" action="' . esc_url( home_url( '/' ) ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( self::TOKEN_PARAM ) . '" value="' . esc_attr( $token ) . '">';
		$html .= '<p><button type="submit" class="button button-large">' . esc_html__( 'Let me back in', 'zero-spam' ) . '</button></p>';
		$html .= '</form>';
		$html .= '<p>' . esc_html__( 'The link works once and expires 15 minutes after it was sent.', 'zero-spam' ) . '</p>';
		$html .= '</div>';

		self::render( __( 'Let me back in', 'zero-spam' ), $html, 200, false );
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
	 * Markup of the "Help for site owners" section of the blocked page.
	 *
	 * Plain HTML that works without JavaScript; inline styles because the
	 * wp_die() page loads no stylesheet (its built-in styles cover `.button`).
	 *
	 * @param string $error Error to show for the email field.
	 * @param string $value Value of the email field.
	 * @param bool   $open  Whether the section starts expanded.
	 * @return string Escaped markup.
	 */
	public static function form_html( string $error = '', string $value = '', bool $open = false ): string {
		$help_id   = self::FIELD_ID . '-help';
		$error_id  = self::FIELD_ID . '-error';
		$described = $help_id . ( '' !== $error ? ' ' . $error_id : '' );

		$html  = self::focus_style();
		$html .= '<details class="zerospam-rescue"' . ( $open ? ' open' : '' ) . ' style="margin-top: 24px; border-top: 1px solid #dcdcde; padding-top: 16px;">';
		$html .= '<summary style="font-size: 14px; font-weight: 600;">' . esc_html__( 'Help for site owners', 'zero-spam' ) . '</summary>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<p id="' . esc_attr( $help_id ) . '">' . esc_html__( 'Site owner? If you administer this site, enter the email address of an administrator account on this site and we\'ll email you a link that lets you in for one hour.', 'zero-spam' ) . '</p>';
		$html .= '<p>';
		$html .= '<label for="' . esc_attr( self::FIELD_ID ) . '" style="display: block; font-weight: 600; margin-bottom: 4px;">' . esc_html__( 'Administrator email address', 'zero-spam' ) . '</label>';
		$html .= '<input type="email" id="' . esc_attr( self::FIELD_ID ) . '" name="' . esc_attr( self::EMAIL_FIELD ) . '" value="' . esc_attr( $value ) . '" required autocomplete="email" spellcheck="false" aria-describedby="' . esc_attr( $described ) . '"' . ( '' !== $error ? ' aria-invalid="true"' : '' ) . ( '' !== $error ? ' autofocus' : '' ) . ' style="width: 100%; max-width: 320px; padding: 4px 8px; font-size: 14px; line-height: 2; border: 1px solid ' . ( '' !== $error ? '#d63638' : '#8c8f94' ) . '; border-radius: 4px;">';
		if ( '' !== $error ) {
			$html .= '<span id="' . esc_attr( $error_id ) . '" role="alert" style="display: block; margin-top: 4px; color: #d63638; font-weight: 600;">' . esc_html( $error ) . '</span>';
		}
		$html .= '</p>';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		$html .= '<input type="hidden" name="' . esc_attr( self::NONCE_FIELD ) . '" value="' . esc_attr( wp_create_nonce( self::NONCE_ACTION ) ) . '">';
		$html .= '<p><button type="submit" class="button button-large">' . esc_html__( 'Email me a rescue link', 'zero-spam' ) . '</button></p>';
		$html .= '</form>';
		$html .= '<p>' . sprintf(
			/* translators: %s: link to the "I blocked myself!" question of the plugin FAQ */
			esc_html__( 'No access to that inbox? See %s in the Zero Spam FAQ.', 'zero-spam' ),
			'<a href="https://wordpress.org/plugins/zero-spam/#faq">' . esc_html__( 'I blocked myself!', 'zero-spam' ) . '</a>'
		) . '</p>';
		$html .= '</details>';

		return $html;
	}

	/**
	 * A visible focus indicator for the rescue controls.
	 *
	 * The wp_die() page only styles `.button:focus` (a 1px ring); this adds a 2px
	 * outline, offset from the control, in WordPress blue (#2271b1, 5.3:1
	 * against the white page).
	 */
	private static function focus_style(): string {
		return '<style>.zerospam-rescue summary:focus-visible, .zerospam-rescue input:focus-visible, .zerospam-rescue button:focus-visible, .zerospam-rescue a:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; box-shadow: none; }</style>';
	}

	/**
	 * Tags and attributes of the rescue markup (form, confirm and answer pages).
	 *
	 * Only for markup this class builds. The administrator's blocked message
	 * is filtered with wp_kses_post() on its own (Access::terminate_execution()).
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		$global = array(
			'id'    => true,
			'class' => true,
			'style' => true,
		);

		return array(
			'style'   => array(),
			'h1'      => $global,
			'p'       => $global,
			'div'     => $global,
			'strong'  => array(),
			'a'       => array( 'href' => true ),
			'span'    => array_merge( $global, array( 'role' => true ) ),
			'details' => array_merge( $global, array( 'open' => true ) ),
			'summary' => $global,
			'form'    => array_merge(
				$global,
				array(
					'method' => true,
					'action' => true,
				)
			),
			'label'   => array_merge( $global, array( 'for' => true ) ),
			'button'  => array_merge( $global, array( 'type' => true ) ),
			'input'   => array_merge(
				$global,
				array(
					'type'             => true,
					'name'             => true,
					'value'            => true,
					'required'         => true,
					'autocomplete'     => true,
					'spellcheck'       => true,
					'autofocus'        => true,
					'aria-describedby' => true,
					'aria-invalid'     => true,
				)
			),
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
	 * Creates a token and emails the link.
	 *
	 * @param string $recipient Administrator address.
	 * @param string $ip        IP that asked for the link.
	 */
	private static function send( string $recipient, string $ip ): void {
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

		$site_name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$from_ip   = '' === $ip ? __( 'an unknown IP address', 'zero-spam' ) : $ip;

		$paragraphs = array(
			sprintf(
				/* translators: 1: site name, 2: site address, 3: IP address the request came from */
				__( 'Someone asked for a rescue link for %1$s (%2$s) from IP address %3$s.', 'zero-spam' ),
				$site_name,
				home_url( '/' ),
				$from_ip
			),
			__( 'Open this link to let your browser in for one hour, then log in:', 'zero-spam' ),
			home_url( '/?' . self::TOKEN_PARAM . '=' . $token ),
			__( 'The link works once and expires in 15 minutes. It works on any device, not only the one that asked for it.', 'zero-spam' ),
			__( 'After you log in, add your IP address to the IP Whitelist under Settings → Zero Spam → Settings so you are not blocked again.', 'zero-spam' ),
			__( 'If you did not ask for this, you can ignore this email. Nobody can use the link without access to this email.', 'zero-spam' ),
		);

		wp_mail(
			$recipient,
			sprintf(
				/* translators: %s: site name */
				__( '[%s] Your Zero Spam rescue link', 'zero-spam' ),
				$site_name
			),
			implode( "\n\n", $paragraphs )
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
		self::render(
			__( 'Check your email', 'zero-spam' ),
			'<p>' . esc_html__( 'If that address belongs to a site administrator, a rescue link has been sent.', 'zero-spam' ) . '</p>' .
			'<p>' . esc_html__( 'The link works once and expires in 15 minutes. It can take a few minutes to arrive. If it does not arrive, check the spam folder.', 'zero-spam' ) . '</p>',
			200
		);
	}

	/**
	 * Ends the request with the form again and an error for the email field.
	 *
	 * @param string $error Error message.
	 * @param string $value Submitted value.
	 * @param int    $code  HTTP status.
	 */
	private static function respond_form( string $error, string $value, int $code ): void {
		self::render( __( 'Help for site owners', 'zero-spam' ), self::form_html( $error, $value, true ), $code );
	}

	/**
	 * Ends the request with an uncached page.
	 *
	 * @param string $title   Page title.
	 * @param string $html    Escaped markup.
	 * @param int    $code    HTTP status.
	 * @param bool   $heading Whether to start the page with the title as its heading.
	 */
	private static function render( string $title, string $html, int $code, bool $heading = true ): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		if ( $heading ) {
			$html = '<h1>' . esc_html( $title ) . '</h1>' . $html;
		}

		wp_die(
			wp_kses( $html, self::allowed_html() ),
			esc_html( $title ),
			array( 'response' => absint( $code ) )
		);
	}
}
