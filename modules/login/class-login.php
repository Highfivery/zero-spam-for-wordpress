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
			add_filter( 'wp_authenticate_user', array( $this, 'process_form' ), 10, 2 );

			// Load scripts.
			add_action( 'login_enqueue_scripts', array( $this, 'add_scripts' ), 10 );

			// Set intent token on login page load.
			add_action( 'login_init', array( $this, 'set_intent_token' ) );
		}
	}

	/**
	 * Sets a login intent token cookie and transient.
	 *
	 * This token proves the user visited the login page and is the same client,
	 * even if the POST payload is scrubbed by an intermediate plugin (e.g. math captcha).
	 */
	public function set_intent_token() {
		// Only run on the login page.
		if ( ! did_action( 'login_init' ) ) {
			return;
		}

		// Generate a secure random token.
		$token = wp_generate_password( 32, false, false );

		// Set a transient with a short TTL (10 minutes).
		// We use the token as part of the key to keep it unique per session.
		set_transient( 'zerospam_login_intent_' . $token, time(), 10 * MINUTE_IN_SECONDS );

		// Set a secure cookie.
		$secure = is_ssl();
		$samesite = PHP_VERSION_ID < 70300 ? null : 'Lax'; // PHP 7.3+ supports SameSite in setcookie options.
		
		// WordPress doesn't support SameSite in setcookie() natively pre-5.5 fully? 
		// Actually, let's use standard PHP setcookie for maximum compatibility or WP's if possible.
		// WP 6.9 environment so use setcookie with array options for modern PHP.
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				'zerospam_login_intent',
				$token,
				array(
					'expires'  => time() + ( 10 * MINUTE_IN_SECONDS ),
					'path'     => COOKIEPATH,
					'domain'   => COOKIE_DOMAIN,
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} else {
			// Fallback for older PHP.
			setcookie(
				'zerospam_login_intent',
				$token,
				time() + ( 10 * MINUTE_IN_SECONDS ),
				COOKIEPATH,
				COOKIE_DOMAIN,
				$secure,
				true
			);
		}
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
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Processes a login attempt.
	 *
	 * @param WP_User|WP_Error $user     WP_User or WP_Error object if a previous callback failed authentication.
	 * @param string           $password Password to check against the user.
	 */
	public function process_form( $user, $password ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		/**
		 * Fix for https://github.com/Highfivery/wordpress-zero-spam/issues/310
		 *
		 * Don't process WooCommerce login forms, this module is only for core login
		 * forms. Would be nice if there was a hook specific to core logins that
		 * wasn't fired for other 3rd-party login forms. A bit of a hacky solution,
		 * but checking if the woocommerce nonce was submitted, if so, ignore
		 * processing. WooCommerce login forms will eventually be processed by a
		 * WooCommerce login hook in the WooCommerce Zero Spam module.
		 */
		if ( ! empty( $post['woocommerce-login-nonce'] ) ) {
			// Submitted via a WooCommerce login form, ignore processing.
			return $user;
		}

		/**
		 * Fix for https://github.com/Highfivery/zero-spam-for-wordpress/issues/357
		 *
		 * Don't process ProfilePress login forms, this module is only for core login
		 * forms... same as above.
		 */
		if ( ! empty( $post['pp_current_url'] ) ) {
			// Submitted via a ProfilePress login form, ignore processing.
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

		// Begin validation checks.
		$validation_errors = array();
		$missing_keys      = false;

		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check (Bot filled it out).
			$validation_errors[] = 'honeypot';
		} elseif ( ! isset( $post[ $honeypot_field_name ] ) ) {
			// Honey pot missing entirely. Potentially interrupted flow.
			$missing_keys = true;
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$errors = apply_filters( 'zerospam_preprocess_login_attempt', array(), $post, 'login_spam_message' );

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $key => $message ) {
				// Check if this error is due to a missing key (David Walsh).
				if ( 'zerospam_david_walsh' === $key ) {
					// Check if the key was actually posted but invalid, or completely missing.
					if ( empty( $post['zerospam_david_walsh_key'] ) ) {
						$missing_keys = true;
					}
				}
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		// If validation failed solely due to missing keys/fields, check for Intent Token.
		if ( ! empty( $validation_errors ) && $missing_keys ) {
			if ( $this->validate_login_intent() ) {
				// Intent valid! Bypassing checks for this request.
				// Log the bypass if debugging/logging enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_logins' ) ) {
					$details['failed'] = 'bypassed_by_intent';
					$details['type']   = 'login_bypass';
					// Optional: Log it as a "Notice" rather than "Block" if your DB logger supports it, 
					// for now just skip logging the block or log successful bypass.
					// We will skip logging a BLOCK here.
				}
				return $user;
			}
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
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

		return $user;
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
	 * Validates the login intent token.
	 *
	 * Checks if a valid intent cookie exists and matches a server-side transient.
	 *
	 * @return bool True if intent is valid, false otherwise.
	 */
	public function validate_login_intent() {
		// Check for the intent cookie.
		if ( empty( $_COOKIE['zerospam_login_intent'] ) ) {
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE['zerospam_login_intent'] ) );

		// Check for the corresponding transient.
		$transient_key = 'zerospam_login_intent_' . $token;
		if ( get_transient( $transient_key ) ) {
			// Intent is valid!
			// Invalidate the token (one-time use) to prevent replay attacks.
			delete_transient( $transient_key );
			return true;
		}

		return false;
	}
}
