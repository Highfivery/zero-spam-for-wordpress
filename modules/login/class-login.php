<?php
/**
 * Login integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Uses the David Walsh technique
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Login;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Login
 */
class Login {
	/**
	 * Name of the login intent cookie.
	 *
	 * @var string
	 */
	const INTENT_COOKIE = 'zerospam_login_intent';

	/**
	 * Prefix of the transient that backs each login intent token.
	 *
	 * @var string
	 */
	const INTENT_TRANSIENT_PREFIX = 'zerospam_login_intent_';

	/**
	 * Default lifetime of a login intent token, in seconds (10 minutes).
	 *
	 * Renewed on each successful use, see validate_login_intent(); change it
	 * with the zerospam_login_intent_ttl filter.
	 *
	 * @var int
	 */
	const INTENT_TTL = 600;

	/**
	 * Longest a login intent token can live in total, renewals included, and
	 * the upper bound of the zerospam_login_intent_ttl filter (1 hour).
	 *
	 * @var int
	 */
	const INTENT_MAX_LIFETIME = 3600;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 1 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );
		add_filter( 'zerospam_failed_types', array( $this, 'failed_types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_login' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Adds Zero Spam's honeypot field.
			add_action( 'login_form', array( $this, 'add_honeypot' ), 10 );

			// Processes the form.
			add_filter( 'wp_authenticate_user', array( $this, 'process_form' ), 10, 1 );

			// Load scripts.
			add_action( 'login_enqueue_scripts', array( $this, 'add_scripts' ), 10 );

			// Set intent token on login page load.
			add_action( 'login_init', array( $this, 'set_intent_token' ) );
		}
	}

	/**
	 * Sets a login intent token cookie and transient.
	 *
	 * The token proves the client loaded the login page, even if the POST
	 * payload is scrubbed by an intermediate plugin (e.g. math captcha) or a
	 * later step of the login posts without the login form's fields. It is
	 * bound to the client (IP address and user agent). When the client
	 * already holds a valid token it is kept and renewed instead of replaced,
	 * so every step of one login sees the same token.
	 *
	 * Nothing is set once headers have been sent (WP-CLI, or output already
	 * started), since the cookie could not reach the browser.
	 */
	public function set_intent_token() {
		// Only run on the login page.
		if ( ! did_action( 'login_init' ) || headers_sent() ) {
			return;
		}

		$token  = self::get_intent_cookie();
		$intent = '' !== $token ? self::get_intent( $token ) : null;

		if ( null === $intent ) {
			// A secure random token, keyed per visitor.
			$token  = wp_generate_password( 32, false, false );
			$intent = array(
				'client'   => self::get_client_fingerprint(),
				'created'  => time(),
				'verified' => false,
			);
		}

		self::store_intent( $token, $intent );
	}

	/**
	 * Load the scripts.
	 *
	 * Uses centralized David Walsh script - selectors are managed in class-davidwalsh.php.
	 */
	public function add_scripts() {
		// Only add scripts if David Walsh is enabled.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
		}
	}

	/**
	 * Adds Zero Spam's honeypot field.
	 */
	public function add_honeypot() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- honeypot_field() escapes the field name and builds the rest from literals.
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Processes a login attempt.
	 *
	 * Runs on `wp_authenticate_user`, i.e. for every password authentication,
	 * including ones that never came from a login form (XML-RPC, custom login
	 * endpoints) and later steps of multi-step logins. Those carry none of the
	 * login form's fields and are not checked here, see is_later_auth_step();
	 * the IP block checks in Access still apply to them.
	 *
	 * A filled honeypot or an invalid David Walsh key always blocks. A missing
	 * honeypot or key (stripped by another plugin) is accepted with a valid
	 * login intent token, see validate_login_intent().
	 *
	 * @param \WP_User|\WP_Error $user WP_User or WP_Error object if a previous callback failed authentication.
	 * @return \WP_User|\WP_Error
	 */
	public function process_form( $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core's login form has no nonce; the fields are only read to check them.
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		if ( $this->is_third_party_login( $post ) ) {
			return $user;
		}

		$later_step = $this->is_later_auth_step( $post, $user );

		/**
		 * Filters whether the login honeypot and David Walsh checks are skipped
		 * for this authentication.
		 *
		 * Lets integrators exempt their own login flows (SSO, custom login
		 * endpoints, multi-step logins) without turning login protection off.
		 * Return false to force the checks on a request Zero Spam detected as
		 * a later step. IP block checks are not affected. Skips are logged as
		 * `login_bypass` when "Log Blocked Login Attempts" is on.
		 *
		 * @since 5.7.14
		 *
		 * @param bool               $skip True when the request was detected as a later
		 *                                 authentication step (no login form fields, or
		 *                                 the user is already logged in).
		 * @param array              $post Sanitized POST data.
		 * @param \WP_User|\WP_Error $user The user being authenticated, or the error
		 *                                 from an earlier callback.
		 */
		if ( apply_filters( 'zerospam_login_skip_check', $later_step, $post, $user ) ) {
			$this->log_bypass( $user, $later_step ? 'second_step' : 'skipped_by_filter' );

			return $user;
		}

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'login_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'user' => $user,
			'type' => 'login',
		);

		// Begin validation checks.
		$validation_errors = array();
		$missing_keys      = false;

		// Set when a check failed on a submitted value (e.g. a filled honeypot or
		// an invalid key), not just a missing field. These are never waived.
		$spam_signal = false;

		if ( ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check (Bot filled it out).
			$validation_errors[] = 'honeypot';
			$spam_signal         = true;
		} elseif ( ! isset( $post[ $honeypot_field_name ] ) ) {
			// Honey pot missing entirely. Potentially interrupted flow.
			$missing_keys = true;
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$errors = apply_filters( 'zerospam_preprocess_login_attempt', array(), $post, 'login_spam_message' );

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $key => $message ) {
				// Check if this error is due to a missing key (David Walsh).
				if ( 'zerospam_david_walsh' === $key && empty( $post['zerospam_david_walsh_key'] ) ) {
					$missing_keys = true;
				} else {
					$spam_signal = true;
				}
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		if ( empty( $validation_errors ) ) {
			// Passed the full checks: later steps of this login can rely on the
			// intent token alone.
			$this->mark_intent_verified();

			return $user;
		}

		// Failed solely because the honeypot or key is missing (stripped by
		// another plugin): accept a valid login intent token instead.
		if ( $missing_keys && ! $spam_signal && $this->validate_login_intent() ) {
			$this->log_bypass( $user, 'bypassed_by_intent' );

			return $user;
		}

		// Failed validations, log & send details if enabled.
		foreach ( $validation_errors as $fail ) {
			$details['failed'] = $fail;

			// Log the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_logins' ) ) {
				\ZeroSpam\Includes\DB::log( 'login', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				do_action( 'zerospam_share_detection', $details );
			}
		}

		// If missing keys, return a specific "Verification Missing" error instead of "Malicious".
		if ( $missing_keys ) {
			return new \WP_Error(
				'failed_zerospam',
				__( 'Verification missing. Please try again.', 'zero-spam' )
			);
		}

		return new \WP_Error( 'failed_zerospam', $error_message );
	}

	/**
	 * Checks whether an authentication is a later step of a login, or not a
	 * login form submission at all, so the login form checks don't apply.
	 *
	 * Generic rule, based only on the request (never on which plugins are
	 * active): `wp_authenticate_user` ran for a request that carries none of
	 * the login form's fields, i.e. no `pwd`, no Zero Spam honeypot field and
	 * no David Walsh key. On wp-login.php core only authenticates with a
	 * non-empty `pwd` from the POST, so removing fields from a core login POST
	 * can't reach this. It matches XML-RPC and other API password logins,
	 * custom login endpoints that call wp_signon() with their own field
	 * names, and the code step of two-factor plugins, for example:
	 *
	 * - Defender / Defender Pro (WPMU DEV): `login_form_defender-verify-otp`
	 *   posts `login_token`, `requested_user`, `auth_method`, `password` (the
	 *   code) and `_wpnonce`, then logs in with wp_set_auth_cookie().
	 *   Source: defender-security trunk, src/controller/class-two-factor.php,
	 *   verify_otp_login_time().
	 * - Two Factor (WordPress.org): `login_form_validate_2fa` posts
	 *   `provider`, `wp-auth-id`, `wp-auth-nonce` and the provider's code
	 *   field, then logs in with wp_set_auth_cookie(). Source: two-factor
	 *   trunk, class-two-factor-core.php, login_form_validate_2fa().
	 *
	 * Neither posts `pwd`, so the generic rule covers them and there is no
	 * plugin-specific detection. It also applies when the request carries a
	 * valid logged-in cookie for the same user (step one already passed).
	 *
	 * @since 5.7.14
	 *
	 * @param array              $post Sanitized POST data.
	 * @param \WP_User|\WP_Error $user The user being authenticated, or an error.
	 * @return bool
	 */
	public function is_later_auth_step( $post, $user ) {
		$form_fields = array( 'pwd', \ZeroSpam\Core\Utilities::get_honeypot(), 'zerospam_david_walsh_key' );

		if ( array() === array_intersect( $form_fields, array_keys( (array) $post ) ) ) {
			return true;
		}

		// Only parse the cookie when there is one (a missing cookie fires the
		// auth_cookie_malformed action, which other security plugins watch).
		if ( $user instanceof \WP_User && defined( 'LOGGED_IN_COOKIE' ) && ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			$cookie_user = wp_validate_auth_cookie( '', 'logged_in' );

			if ( $cookie_user && (int) $cookie_user === (int) $user->ID ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Logs a login whose checks were waived, when "Log Blocked Login
	 * Attempts" is on, so admins can see what was let through and why.
	 *
	 * Not shared with Zero Spam: a waived check is not a detection.
	 *
	 * @param \WP_User|\WP_Error $user   The user being authenticated, or an error.
	 * @param string             $reason second_step, skipped_by_filter or bypassed_by_intent.
	 */
	private function log_bypass( $user, $reason ) {
		if ( 'enabled' !== \ZeroSpam\Core\Settings::get_settings( 'log_blocked_logins' ) ) {
			return;
		}

		\ZeroSpam\Includes\DB::log(
			'login_bypass',
			array(
				'type'   => 'login_bypass',
				'failed' => $reason,
				'user'   => $user instanceof \WP_User ? $user->user_login : '',
			)
		);
	}

	/**
	 * Checks if a login attempt came from a supported third-party login form
	 * (WooCommerce, ProfilePress), which this module doesn't process.
	 *
	 * Only applies outside wp-login.php, and only when that plugin is active —
	 * otherwise anyone could skip the login checks by adding the plugin's
	 * field to a wp-login.php request.
	 *
	 * @see https://github.com/Highfivery/wordpress-zero-spam/issues/310
	 * @see https://github.com/Highfivery/zero-spam-for-wordpress/issues/357
	 *
	 * @param array $post Sanitized POST data.
	 * @return bool
	 */
	public function is_third_party_login( $post ) {
		// Core login form (wp-login.php, including custom login URLs).
		if ( did_action( 'login_init' ) ) {
			return false;
		}

		// WooCommerce login form, with a valid WooCommerce login nonce.
		if (
			! empty( $post['woocommerce-login-nonce'] ) &&
			is_string( $post['woocommerce-login-nonce'] ) &&
			class_exists( 'WooCommerce' ) &&
			wp_verify_nonce( $post['woocommerce-login-nonce'], 'woocommerce-login' )
		) {
			return true;
		}

		// ProfilePress login form.
		if ( ! empty( $post['pp_current_url'] ) && defined( 'PPRESS_VERSION_NUMBER' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['login'] = array(
			'label' => __( 'Login', 'zero-spam' ),
			'color' => '#7b90ff',
		);

		$types['login_bypass'] = array(
			'label' => __( 'Login (checks waived)', 'zero-spam' ),
			'color' => '#b4c0ff',
		);

		return $types;
	}

	/**
	 * Add to the failed types array
	 *
	 * @param array $types Array of failed types.
	 * @return array
	 */
	public function failed_types( $types ) {
		$types['second_step']        = __( 'Later login step (no login form fields)', 'zero-spam' );
		$types['skipped_by_filter']  = __( 'Skipped by the zerospam_login_skip_check filter', 'zero-spam' );
		$types['bypassed_by_intent'] = __( 'Missing fields, valid login intent', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['login'] = array(
			'title'    => __( 'User Login', 'zero-spam' ),
			'icon'     => 'assets/img/icon-wordpress.svg',
			'supports' => array( 'honeypot', 'davidwalsh' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-login' );

		$settings['verify_login'] = array(
			'title'       => __( 'Protect Login Attempts', 'zero-spam' ),
			'desc'        => __( 'Stop spam bots from trying to login to your website.', 'zero-spam' ),
			'section'     => 'login',
			'module'      => 'login',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_login'] ) ? $options['verify_login'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['login_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'The message shown to spam bots trying to login.', 'zero-spam' ),
			'section'     => 'login',
			'module'      => 'login',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['login_spam_message'] ) ? $options['login_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_logins'] = array(
			'title'       => __( 'Log Blocked Login Attempts', 'zero-spam' ),
			'section'     => 'login',
			'module'      => 'login',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of all blocked login attempts in the database.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_logins'] ) ? $options['log_blocked_logins'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}

	/**
	 * Validates the login intent token.
	 *
	 * Accepts a token that matches a stored, unexpired intent bound to this
	 * client. When the David Walsh technique is enabled, the token must also
	 * have been proven: by an earlier step of this login that passed the full
	 * checks (honeypot and key), or by a valid key cookie set by the login
	 * page's script. An unproven token only shows that the login page was
	 * requested, which any client can do.
	 *
	 * The token is valid for its whole lifetime, not once: two-step logins
	 * and plugins that resubmit the login run this check more than once, and
	 * a one-time token turned their second request into a block. Replay stays
	 * bounded: the token is random and HttpOnly, tied to the client's IP
	 * address and user agent (a salted hash stored server-side), expires one
	 * intent TTL after it was issued or last used (renewed on each use so an
	 * active login doesn't expire mid-flow), and never lives longer than
	 * INTENT_MAX_LIFETIME in total. It never waives a filled honeypot or an
	 * invalid key.
	 *
	 * @return bool True if intent is valid, false otherwise.
	 */
	public function validate_login_intent() {
		$token = self::get_intent_cookie();
		if ( '' === $token ) {
			return false;
		}

		$intent = self::get_intent( $token );
		if ( null === $intent ) {
			return false;
		}

		if ( ! $intent['verified'] && 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			$dw_key = isset( $_COOKIE['zerospam_david_walsh_key'] ) && is_string( $_COOKIE['zerospam_david_walsh_key'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['zerospam_david_walsh_key'] ) ) : '';

			if ( ! \ZeroSpam\Modules\DavidWalsh\DavidWalsh::validate_token( $dw_key ) ) {
				return false;
			}

			$intent['verified'] = true;
		}

		// Renew the token for the next step of this login.
		self::store_intent( $token, $intent );

		return true;
	}

	/**
	 * Gets the login intent window in seconds.
	 *
	 * @since 5.7.14
	 *
	 * @return int Between one minute and INTENT_MAX_LIFETIME.
	 */
	public static function get_intent_ttl() {
		/**
		 * Filters how long a login intent token stays valid after it was
		 * issued or last used, in seconds.
		 *
		 * Clamped to between one minute and one hour; a token never lives
		 * longer than one hour in total, renewals included.
		 *
		 * @since 5.7.14
		 *
		 * @param int $ttl Lifetime in seconds. Default 600 (10 minutes).
		 */
		$ttl = (int) apply_filters( 'zerospam_login_intent_ttl', self::INTENT_TTL );

		return max( MINUTE_IN_SECONDS, min( self::INTENT_MAX_LIFETIME, $ttl ) );
	}

	/**
	 * Marks the client's intent token as proven by a step that passed the
	 * full checks, so later steps of this login can rely on it.
	 */
	private function mark_intent_verified() {
		$token = self::get_intent_cookie();
		if ( '' === $token ) {
			return;
		}

		$intent = self::get_intent( $token );
		if ( null === $intent ) {
			return;
		}

		$intent['verified'] = true;
		self::store_intent( $token, $intent );
	}

	/**
	 * Gets the intent token from the request cookie.
	 *
	 * @return string The token, or an empty string when missing or malformed.
	 */
	private static function get_intent_cookie() {
		if ( ! isset( $_COOKIE[ self::INTENT_COOKIE ] ) || ! is_string( $_COOKIE[ self::INTENT_COOKIE ] ) ) {
			return '';
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ self::INTENT_COOKIE ] ) );

		return 1 === preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ? $token : '';
	}

	/**
	 * Gets the stored intent for a token, if it is valid for this client.
	 *
	 * Tokens stored before 5.7.14 hold a bare timestamp, are not bound to a
	 * client and are not accepted.
	 *
	 * @param string $token Intent token.
	 * @return array{client: string, created: int, verified: bool}|null
	 */
	private static function get_intent( $token ) {
		$intent = get_transient( self::INTENT_TRANSIENT_PREFIX . $token );

		if ( ! is_array( $intent ) || empty( $intent['client'] ) || ! is_string( $intent['client'] ) || empty( $intent['created'] ) ) {
			return null;
		}

		if ( ! hash_equals( $intent['client'], self::get_client_fingerprint() ) ) {
			return null;
		}

		if ( time() - (int) $intent['created'] >= self::INTENT_MAX_LIFETIME ) {
			return null;
		}

		return array(
			'client'   => $intent['client'],
			'created'  => (int) $intent['created'],
			'verified' => ! empty( $intent['verified'] ),
		);
	}

	/**
	 * Stores (or renews) an intent and sends its cookie.
	 *
	 * The new expiry is one intent TTL from now, but never later than
	 * INTENT_MAX_LIFETIME after the token was created.
	 *
	 * @param string                                              $token  Intent token.
	 * @param array{client: string, created: int, verified: bool} $intent Intent data.
	 */
	private static function store_intent( $token, $intent ) {
		$ttl = min( self::get_intent_ttl(), self::INTENT_MAX_LIFETIME - ( time() - $intent['created'] ) );
		if ( $ttl < 1 ) {
			return;
		}

		set_transient( self::INTENT_TRANSIENT_PREFIX . $token, $intent, $ttl );

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::INTENT_COOKIE,
			$token,
			array(
				'expires'  => time() + $ttl,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Salted hash identifying the client a token was issued to (IP address
	 * and user agent).
	 *
	 * @return string
	 */
	private static function get_client_fingerprint() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return wp_hash( 'zerospam_login_intent|' . \ZeroSpam\Core\User::get_ip() . '|' . $user_agent );
	}
}
