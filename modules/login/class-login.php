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
		}
	}

	/**
	 * Load the scripts
	 */
	public function add_scripts() {
		// Only add scripts to the appropriate pages.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
			wp_add_inline_script( 'zerospam-davidwalsh', 'jQuery("#loginform").ZeroSpamDavidWalsh();' );
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

		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$errors = apply_filters( 'zerospam_preprocess_login_attempt', array(), $post, 'login_spam_message' );

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
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
		$types['login'] = __( 'Login Attempt', 'zero-spam' );

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
			'desc'        => __( 'Protects & monitors login attempts.', 'zero-spam' ),
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
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
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
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked login attempts in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_logins'] ) ? $options['log_blocked_logins'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
