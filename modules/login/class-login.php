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

use ZeroSpam\Modules\DavidWalsh\DavidWalsh;

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
	 * Renewed when the login page loads and after a successful password
	 * step; change it with the zerospam_login_intent_ttl filter.
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
	 * How many times one intent token can stand in for missing login form
	 * fields (missing-field fallback and later steps together).
	 *
	 * @var int
	 */
	const INTENT_MAX_USES = 5;

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

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_login' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Adds Zero Spam's honeypot field.
			add_action( 'login_form', array( $this, 'add_honeypot' ), 10 );

			// Processes the form.
			add_filter( 'wp_authenticate_user', array( $this, 'process_form' ), 10, 1 );

			// Verifies the intent token once the password was accepted.
			add_filter( 'authenticate', array( $this, 'verify_intent_after_password' ), 25, 3 );

			// Load scripts.
			add_action( 'login_enqueue_scripts', array( $this, 'add_scripts' ), 10 );

			// Set intent token on login page load.
			add_action( 'login_init', array( $this, 'set_intent_token' ) );
		}
	}

	/**
	 * Sets a login intent token cookie and transient.
	 *
	 * The token proves the client loaded the login page. It lets a login
	 * through when another plugin strips the honeypot and key from the form
	 * (subject to validate_login_intent()), and, once a password step with
	 * it succeeded, lets later steps of that login through (see
	 * is_later_auth_step()). It is bound to the client's network and user
	 * agent. When the client already holds a valid token it is kept and
	 * renewed instead of replaced, so every step of one login sees the same
	 * token.
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
				'expires'  => 0,
				'verified' => false,
				'user_id'  => 0,
				'uses'     => 0,
			);
		}

		self::store_intent( $token, $intent, true );
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
	 * Runs on `wp_authenticate_user`, inside core's password authentication
	 * and before core checks the password, so nothing here may treat the
	 * attempt as successful.
	 *
	 * - A filled honeypot or a forged/malformed David Walsh key blocks.
	 * - A missing honeypot or key, or a key this site signed that has expired
	 *   or been used up, is accepted with a valid login intent token (see
	 *   validate_login_intent()); otherwise "Verification missing".
	 * - A request with none of the login form's fields is skipped only when
	 *   it is a later step of a login whose password step succeeded (see
	 *   is_later_auth_step()), or when zerospam_login_skip_check says so.
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
		 * a later step. IP block checks are not affected. Skips fire the
		 * zerospam_login_checks_waived action.
		 *
		 * @since 5.7.14
		 *
		 * @param bool               $skip True when the request was detected as a later
		 *                                 step of a login whose password step succeeded.
		 * @param array              $post Sanitized POST data.
		 * @param \WP_User|\WP_Error $user The user being authenticated, or the error
		 *                                 from an earlier callback.
		 */
		if ( apply_filters( 'zerospam_login_skip_check', $later_step, $post, $user ) ) {
			if ( $later_step ) {
				self::use_intent();
			}

			$this->checks_waived( $later_step ? 'second_step' : 'skipped_by_filter', $user, $post );

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
		// a forged key), not just a missing or lapsed one. These are never waived.
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
				// A missing key, or one this site signed that expired or was used
				// up (cached page, back/forward cache, resubmission), is treated
				// like a missing field. Anything else is a spam signal.
				if ( DavidWalsh::is_lapsed_error( $key ) ) {
					$missing_keys = true;
				} else {
					$spam_signal = true;
				}
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		if ( empty( $validation_errors ) ) {
			return $user;
		}

		// Failed only because a field is missing or lapsed: accept a valid login
		// intent token instead.
		if ( $missing_keys && ! $spam_signal && $this->validate_login_intent( $user ) ) {
			$this->checks_waived( 'bypassed_by_intent', $user, $post );

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
	 * Marks the client's intent token as verified once a password step
	 * succeeded, and renews it.
	 *
	 * Runs on `authenticate` at priority 25: after core's password checks
	 * (priority 20, which include `wp_authenticate_user`) and before
	 * two-factor plugins take over the response (Defender's
	 * maybe_show_otp_form() at 30 renders its code form and exits, so
	 * `wp_login` and later priorities never run on its first step). A
	 * `WP_User` here with a non-empty password means the password was
	 * accepted; a failed attempt never verifies or renews the token. A plugin
	 * that returns a `WP_User` on `authenticate` below priority 25 (an OTP or
	 * magic-link login, say) also verifies it, by design: that plugin has
	 * authenticated the user. The token is verified for that user only.
	 *
	 * @since 5.7.14
	 *
	 * @param \WP_User|\WP_Error|null $user     Result so far.
	 * @param string                  $username Username or email.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null Unchanged.
	 */
	public function verify_intent_after_password( $user, $username = '', $password = '' ) {
		if ( ! $user instanceof \WP_User || ! is_string( $password ) || '' === $password ) {
			return $user;
		}

		$token  = self::get_intent_cookie();
		$intent = '' !== $token ? self::get_intent( $token ) : null;

		if ( null !== $intent ) {
			$intent['verified'] = true;
			$intent['user_id']  = (int) $user->ID;
			self::store_intent( $token, $intent, true );
		}

		return $user;
	}

	/**
	 * Whether an intent was verified by a successful password step of the
	 * user now being authenticated.
	 *
	 * @param array{verified: bool, user_id: int} $intent Intent data.
	 * @param \WP_User|\WP_Error|null             $user   The user being authenticated, or an error.
	 * @return bool
	 */
	private static function is_verified_for( $intent, $user ) {
		return $intent['verified'] && $user instanceof \WP_User && $intent['user_id'] > 0 && (int) $user->ID === $intent['user_id'];
	}

	/**
	 * Checks whether an authentication is a later step of a login whose
	 * password step already succeeded, so the login form checks don't apply.
	 *
	 * Both must hold, judged from the request only (never from which plugins
	 * are active):
	 *
	 * 1. The request carries none of the login form's fields: no `pwd`, no
	 *    Zero Spam honeypot field and no David Walsh key.
	 * 2. It carries a valid intent token for this client, with uses left,
	 *    that a successful password step of the same user verified (see
	 *    verify_intent_after_password()).
	 *
	 * Anything else, including custom login endpoints and XML-RPC, gets the
	 * normal checks; integrators can exempt their flows with the
	 * zerospam_login_skip_check filter. Two-factor plugins that finish the
	 * login with wp_set_auth_cookie() don't reach this check on their code
	 * step at all; this covers flows that authenticate again. For reference,
	 * the code steps of Defender (`login_form_defender-verify-otp`:
	 * `login_token`, `requested_user`, `auth_method`, `password`, `_wpnonce`;
	 * defender-security trunk, src/controller/class-two-factor.php) and Two
	 * Factor (`login_form_validate_2fa`: `provider`, `wp-auth-id`,
	 * `wp-auth-nonce`; two-factor trunk, class-two-factor-core.php) post no
	 * `pwd`, so they would match rule 1.
	 *
	 * @since 5.7.14
	 *
	 * @param array                   $post Sanitized POST data.
	 * @param \WP_User|\WP_Error|null $user The user being authenticated, or an error.
	 * @return bool
	 */
	public function is_later_auth_step( $post, $user = null ) {
		$form_fields = array( 'pwd', \ZeroSpam\Core\Utilities::get_honeypot(), 'zerospam_david_walsh_key' );

		if ( array() !== array_intersect( $form_fields, array_keys( (array) $post ) ) ) {
			return false;
		}

		$token = self::get_intent_cookie();
		if ( '' === $token ) {
			return false;
		}

		$intent = self::get_intent( $token );

		return null !== $intent && self::is_verified_for( $intent, $user );
	}

	/**
	 * Reports a login whose checks were waived.
	 *
	 * Fires zerospam_login_checks_waived, and writes the reason (nothing
	 * else) to the plugin's debug log (Utilities::log()) when WP_DEBUG is on.
	 * Never written to the detection log, which records blocked attempts.
	 *
	 * @param string             $reason second_step, skipped_by_filter or bypassed_by_intent.
	 * @param \WP_User|\WP_Error $user   The user being authenticated, or an error.
	 * @param array              $post   Sanitized POST data.
	 */
	private function checks_waived( $reason, $user, $post ) {
		/**
		 * Fires when the login honeypot and David Walsh checks were waived.
		 *
		 * @since 5.7.14
		 *
		 * @param string             $reason second_step (later step of a login whose
		 *                                   password step succeeded), skipped_by_filter
		 *                                   (zerospam_login_skip_check) or
		 *                                   bypassed_by_intent (missing or lapsed fields,
		 *                                   valid intent token).
		 * @param \WP_User|\WP_Error $user   The user being authenticated, or an error.
		 * @param array              $post   Sanitized POST data.
		 */
		do_action( 'zerospam_login_checks_waived', $reason, $user, $post );

		// The reason only: the log file sits in uploads and may be readable from
		// the web, so no username or IP address goes in it.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			\ZeroSpam\Core\Utilities::log( 'Login checks waived: ' . $reason );
		}
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
	 * Validates the login intent token in place of missing or lapsed login
	 * form fields, and counts one use of it.
	 *
	 * Requires a valid token for this client (see get_intent()) with uses
	 * left. When the David Walsh technique is enabled, the token must also
	 * have been verified by a successful password step of the same user, or
	 * the request must carry a valid key cookie set by the login page's
	 * script (as since 5.7.12). The key cookie never verifies the token: any
	 * client can set it.
	 *
	 * The token is not one-time, so a login that runs the check more than
	 * once (a plugin that resubmits the login, a retry after a lapsed key)
	 * still works. What bounds it: it is random and HttpOnly; bound to the
	 * client's network (/24 or /64) and user agent, and once verified to
	 * that user; usable INTENT_MAX_USES times; renewed only when the login
	 * page loads or a password step succeeds, never by a use; and never
	 * older than INTENT_MAX_LIFETIME. It never waives a filled honeypot or a
	 * forged key.
	 *
	 * @param \WP_User|\WP_Error|null $user The user being authenticated, or an error.
	 * @return bool True if intent is valid, false otherwise.
	 */
	public function validate_login_intent( $user = null ) {
		$token = self::get_intent_cookie();
		if ( '' === $token ) {
			return false;
		}

		$intent = self::get_intent( $token );
		if ( null === $intent ) {
			return false;
		}

		if ( ! self::is_verified_for( $intent, $user ) && 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			$dw_key = isset( $_COOKIE['zerospam_david_walsh_key'] ) && is_string( $_COOKIE['zerospam_david_walsh_key'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['zerospam_david_walsh_key'] ) ) : '';

			if ( ! DavidWalsh::validate_token( $dw_key ) ) {
				return false;
			}
		}

		self::use_intent();

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
		 * Filters how long a login intent token stays valid after the login
		 * page was loaded or a password step succeeded, in seconds.
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
	 * Counts one use of the client's intent token, without renewing it.
	 */
	private static function use_intent() {
		$token = self::get_intent_cookie();
		if ( '' === $token ) {
			return;
		}

		$intent = self::get_intent( $token );
		if ( null === $intent ) {
			return;
		}

		++$intent['uses'];
		self::store_intent( $token, $intent, false );
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
	 * Gets the stored intent for a token, if it is valid for this client:
	 * bound to this client, unexpired, not older than INTENT_MAX_LIFETIME and
	 * with uses left.
	 *
	 * Tokens stored before 5.7.14 hold a bare timestamp, are not bound to a
	 * client and are not accepted.
	 *
	 * @param string $token Intent token.
	 * @return array{client: string, created: int, expires: int, verified: bool, user_id: int, uses: int}|null
	 */
	private static function get_intent( $token ) {
		$intent = get_transient( self::INTENT_TRANSIENT_PREFIX . $token );

		if ( ! is_array( $intent ) || empty( $intent['client'] ) || ! is_string( $intent['client'] ) || empty( $intent['created'] ) ) {
			return null;
		}

		$intent = array(
			'client'   => $intent['client'],
			'created'  => (int) $intent['created'],
			'expires'  => isset( $intent['expires'] ) ? (int) $intent['expires'] : 0,
			'verified' => ! empty( $intent['verified'] ),
			'user_id'  => isset( $intent['user_id'] ) ? (int) $intent['user_id'] : 0,
			'uses'     => isset( $intent['uses'] ) ? (int) $intent['uses'] : 0,
		);

		$now = time();

		if (
			! hash_equals( $intent['client'], self::get_client_fingerprint() ) ||
			$now >= $intent['expires'] ||
			$now - $intent['created'] >= self::INTENT_MAX_LIFETIME ||
			$intent['uses'] >= self::INTENT_MAX_USES
		) {
			return null;
		}

		return $intent;
	}

	/**
	 * Stores an intent, renewing it (and sending its cookie) when asked.
	 *
	 * A renewal moves the expiry to one intent TTL from now, but never later
	 * than INTENT_MAX_LIFETIME after the token was created. Without renewal
	 * the expiry is kept.
	 *
	 * @param string                                                                                     $token  Intent token.
	 * @param array{client: string, created: int, expires: int, verified: bool, user_id: int, uses: int} $intent Intent data.
	 * @param bool                                                                                       $renew  Whether to renew it.
	 */
	private static function store_intent( $token, $intent, $renew ) {
		$now = time();

		if ( $renew ) {
			$intent['expires'] = min( $now + self::get_intent_ttl(), $intent['created'] + self::INTENT_MAX_LIFETIME );
		}

		if ( $intent['expires'] <= $now ) {
			return;
		}

		set_transient( self::INTENT_TRANSIENT_PREFIX . $token, $intent, $intent['expires'] - $now );

		if ( ! $renew || headers_sent() ) {
			return;
		}

		setcookie(
			self::INTENT_COOKIE,
			$token,
			array(
				'expires'  => $intent['expires'],
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Salted hash identifying the client a token was issued to: its network
	 * (the /24 of an IPv4 address or the /64 of an IPv6 address, so a change
	 * of address within the provider's range mid-login is tolerated) and its
	 * user agent.
	 *
	 * @return string
	 */
	private static function get_client_fingerprint() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return wp_hash( 'zerospam_login_intent|' . self::get_client_network( (string) \ZeroSpam\Core\User::get_ip() ) . '|' . $user_agent );
	}

	/**
	 * Network prefix of an IP address: /24 for IPv4, /64 for IPv6.
	 *
	 * @param string $ip IP address.
	 * @return string The prefix, or the input when it isn't an IP address.
	 */
	private static function get_client_network( $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return implode( '.', array_slice( explode( '.', $ip ), 0, 3 ) ) . '.0/24';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );

			if ( false !== $packed ) {
				return bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
			}
		}

		return $ip;
	}
}
