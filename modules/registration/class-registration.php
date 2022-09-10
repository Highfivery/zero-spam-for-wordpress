<?php
/**
 * Registration integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Checks blocked email domains
 * 3. Uses the David Walsh technique
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Registration;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Registration
 */
class Registration {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_registrations' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'register_form', array( $this, 'add_scripts' ) );
			add_action( 'register_form', array( $this, 'add_honeypot_field' ) );
			add_filter( 'registration_errors', array( $this, 'process_form' ), 10, 3 );
		}
	}

	/**
	 * Add to the detection types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['registration'] = array(
			'label' => __( 'Registration', 'zero-spam' ),
			'color' => '#3858e9',
		);

		return $types;
	}

	/**
	 * Load the scripts
	 */
	public function add_scripts() {
		// Only add scripts to the appropriate pages.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
			wp_add_inline_script( 'zerospam-davidwalsh', 'jQuery("#registerform").ZeroSpamDavidWalsh();' );
		}
	}

	/**
	 * Preprocess registrations
	 *
	 * @param WP_Error $errors A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email User's email.
	 */
	public function process_form( $errors, $sanitized_user_login, $user_email ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'user_login' => $sanitized_user_login,
			'user_email' => $user_email,
			'type'       => 'registration',
		);

		// Begin validation checks.
		$validation_errors = array();

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();
		// @codingStandardsIgnoreLine
		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$details['failed'] = 'honeypot';

			$validation_errors[] = 'honeypot';
		}

		// Check email.
		if ( ! empty( $user_email ) && ! \ZeroSpam\Core\Utilities::is_email( $user_email ) ) {
			$validation_errors[] = 'invalid_email';
		}

		// Check blocked email domains.
		if (
			! empty( $user_email ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $user_email )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$filtered_errors = apply_filters( 'zerospam_preprocess_registration_submission', array(), $post, 'registration_spam_message' );

		if ( ! empty( $filtered_errors ) ) {
			foreach ( $filtered_errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_registrations' ) ) {
					\ZeroSpam\Includes\DB::log( 'registration', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$errors->add( 'zerospam_error', $error_message );
		}

		return $errors;
	}

	/**
	 * Add a 'honeypot' field to the registration form
	 */
	public function add_honeypot_field() {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['registration'] = array(
			'title'    => __( 'Registration', 'zero-spam' ),
			'icon'     => 'assets/img/icon-wordpress.svg',
			'supports' => array( 'honeypot', 'email', 'davidwalsh' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-registration' );

		$settings['verify_registrations'] = array(
			'title'       => __( 'Protect Registrations', 'zero-spam' ),
			'desc'        => __( 'Protects & monitors registration submissions.', 'zero-spam' ),
			'section'     => 'registration',
			'module'      => 'registration',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_registrations'] ) ? $options['verify_registrations'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['registration_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'registration',
			'module'      => 'registration',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['registration_spam_message'] ) ? $options['registration_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'registration',
			'module'      => 'registration',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked registration submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_registrations'] ) ? $options['log_blocked_registrations'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
