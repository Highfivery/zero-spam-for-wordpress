<?php
/**
 * Login class
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
	 * Add-on constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_login' ) && \ZeroSpam\Core\Access::process() ) {
			// Adds Zero Spam's honeypot field.
			add_action( 'login_form', array( $this, 'add_honeypot' ), 10 );

			// Processes the form.
			add_filter( 'wp_authenticate_user', array( $this, 'process_form' ), 10, 2 );

			// Load scripts.
			add_action( 'login_enqueue_scripts', array( $this, 'scripts' ), 10 );

			// Add script to WooCommerce login.
			add_action( 'woocommerce_login_form_start', array( $this, 'scripts' ), 10 );
		}
	}

	/**
	 * Load the add-on scripts.
	 */
	public function scripts() {
		do_action( 'zerospam_login_scripts' );
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
		if ( isset( $_POST[ $honeypot_field_name ] ) && ! empty( $_POST[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		// @codingStandardsIgnoreLine
		$errors = apply_filters( 'zerospam_preprocess_login_attempt', array(), $user, $password, $_POST );

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
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['login'] = __( 'Login Attempt', 'zerospam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['login'] = array(
			'title' => __( 'User Login Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_login'] = array(
			'title'       => __( 'Protect Login Attempts', 'zerospam' ),
			'section'     => 'login',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor login attempts for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_login'] ) ? $options['verify_login'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zerospam' );

		$settings['login_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When login protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'login',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['login_spam_message'] ) ? $options['login_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_logins'] = array(
			'title'       => __( 'Log Blocked Login Attempts', 'zerospam' ),
			'section'     => 'login',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked login attempts. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_logins'] ) ? $options['log_blocked_logins'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
