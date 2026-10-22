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
 * limited per site and per IP before it does anything else.
 *
 * The emailed link carries a random single-use token that expires after
 * 15 minutes; only its SHA-256 hash is stored. Opening the link (Access::init())
 * sets the same one-hour rescue cookie as the ZEROSPAM_RESCUE_KEY constant
 * and sends the visitor to the login page. The link may be opened from
 * another IP than the one that asked for it (people often open email on
 * another device); both IPs are written to the detection log.
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
	const SITE_LIMIT = 3;

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
	 * Registers the form handler and the log type.
	 */
	public function __construct() {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_request' ) );
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
	 * Handles a rescue form submission and ends the request.
	 *
	 * Every valid submission gets the same answer whether or not an email is
	 * sent, so the form cannot be used to find out who the administrators are.
	 */
	public static function handle_request(): void {
		$ip = self::visitor_ip();

		// Rate limits first: a flood costs one transient read and write per request.
		if ( ! self::take( self::IP_LIMIT_TRANSIENT_PREFIX . md5( '' === $ip ? 'unknown' : $ip ), self::IP_LIMIT ) ) {
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
		if ( null !== $recipient && self::take( self::SITE_LIMIT_TRANSIENT, self::SITE_LIMIT ) ) {
			self::send( $recipient, $ip );
		}

		self::respond_sent();
	}

	/**
	 * Uses a token from an emailed link, once.
	 *
	 * @param string $token Token from the link.
	 * @return array{ip: string, time: int}|null The IP that asked for the link and when, or null when the token is not valid.
	 */
	public static function redeem_token( string $token ): ?array {
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

		// Single use: only the request that deletes the token may use it.
		if ( ! delete_transient( $key ) || (int) $stored['expires'] < time() ) {
			return null;
		}

		return array(
			'ip'   => isset( $stored['ip'] ) ? (string) $stored['ip'] : '',
			'time' => isset( $stored['time'] ) ? (int) $stored['time'] : 0,
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

		$html  = '<details class="zerospam-rescue"' . ( $open ? ' open' : '' ) . ' style="margin-top: 24px; border-top: 1px solid #dcdcde; padding-top: 16px;">';
		$html .= '<summary style="font-size: 14px; font-weight: 600;">' . esc_html__( 'Help for site owners', 'zero-spam' ) . '</summary>';
		$html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<p id="' . esc_attr( $help_id ) . '">' . esc_html__( 'Site owner? If you administer this site, enter the site\'s administrator email address and we\'ll email you a link that lets you in for one hour.', 'zero-spam' ) . '</p>';
		$html .= '<p>';
		$html .= '<label for="' . esc_attr( self::FIELD_ID ) . '" style="display: block; font-weight: 600; margin-bottom: 4px;">' . esc_html__( 'Administrator email address', 'zero-spam' ) . '</label>';
		$html .= '<input type="email" id="' . esc_attr( self::FIELD_ID ) . '" name="' . esc_attr( self::EMAIL_FIELD ) . '" value="' . esc_attr( $value ) . '" required autocomplete="email" spellcheck="false" aria-describedby="' . esc_attr( $described ) . '"' . ( '' !== $error ? ' aria-invalid="true"' : '' ) . ' style="width: 100%; max-width: 320px; padding: 4px 8px; font-size: 14px; line-height: 2; border: 1px solid ' . ( '' !== $error ? '#d63638' : '#8c8f94' ) . '; border-radius: 4px;">';
		if ( '' !== $error ) {
			$html .= '<span id="' . esc_attr( $error_id ) . '" style="display: block; margin-top: 4px; color: #d63638; font-weight: 600;">' . esc_html( $error ) . '</span>';
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
	 * Tags and attributes allowed on pages that include the rescue form.
	 *
	 * The post allowlist (for the configured blocked message) plus the form
	 * controls form_html() uses.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		$allowed = wp_kses_allowed_html( 'post' );
		$global  = array(
			'id'    => true,
			'class' => true,
			'style' => true,
		);

		$allowed['details'] = array_merge( $allowed['details'] ?? array(), $global, array( 'open' => true ) );
		$allowed['summary'] = array_merge( $allowed['summary'] ?? array(), $global );
		$allowed['form']    = array_merge(
			$allowed['form'] ?? array(),
			$global,
			array(
				'method' => true,
				'action' => true,
			)
		);
		$allowed['label']   = array_merge( $allowed['label'] ?? array(), $global, array( 'for' => true ) );
		$allowed['button']  = array_merge( $allowed['button'] ?? array(), $global, array( 'type' => true ) );
		$allowed['input']   = array_merge(
			$global,
			array(
				'type'             => true,
				'name'             => true,
				'value'            => true,
				'required'         => true,
				'autocomplete'     => true,
				'spellcheck'       => true,
				'aria-describedby' => true,
				'aria-invalid'     => true,
			)
		);

		return $allowed;
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
			__( 'After you log in, add your IP address to the IP Whitelist under Settings → Zero Spam so you are not blocked again.', 'zero-spam' ),
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
		$now    = time();
		$window = get_transient( $key );

		if ( ! is_array( $window ) || ! isset( $window['count'], $window['start'] ) || (int) $window['start'] + HOUR_IN_SECONDS <= $now ) {
			$window = array(
				'count' => 0,
				'start' => $now,
			);
		}

		if ( (int) $window['count'] >= $limit ) {
			return false;
		}

		$window['count'] = (int) $window['count'] + 1;
		set_transient( $key, $window, max( 1, (int) $window['start'] + HOUR_IN_SECONDS - $now ) );

		return true;
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
			'<p>' . esc_html__( 'The link works once and expires in 15 minutes. If it does not arrive, check the spam folder.', 'zero-spam' ) . '</p>',
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
	 * @param string $title Page title.
	 * @param string $html  Escaped markup.
	 * @param int    $code  HTTP status.
	 */
	private static function render( string $title, string $html, int $code ): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		wp_die(
			wp_kses( $html, self::allowed_html() ),
			esc_html( $title ),
			array( 'response' => absint( $code ) )
		);
	}
}
