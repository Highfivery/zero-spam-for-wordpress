<?php
/**
 * MemberPress class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\MemberPress;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * MemberPress
 */
class MemberPress {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		global $wp_query;

		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 2 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( \ZeroSpam\Core\Access::process() ) {
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_memberpress_registration' ) ) {
				// Add Zero Spam's honeypot field to the registration form.
				add_action( 'mepr-checkout-before-submit', array( $this, 'add_honeypot' ) );

				// Preprocess registration form submissions.
				add_filter( 'mepr-validate-signup', array( $this, 'process_registration_form' ), 10, 1 );

				// Add registration scripts.
				add_filter( 'mepr-signup-scripts', array( $this, 'scripts' ), 10, 1 );
			}

			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_memberpress_login' ) ) {
				// Add Zero Spam's honeypot field to the login form.
				add_action( 'mepr-login-form-before-submit', array( $this, 'add_honeypot' ) );

				// Preprocess login form submissions.
				add_filter( 'mepr-validate-login', array( $this, 'process_login_form' ), 10, 1 );

				// Add login scripts.
				add_action(
					'the_post',
					function() {
						$mepr_options  = \MeprOptions::fetch();
						$login_page_id = ( ! empty( $mepr_options->login_page_id ) && $mepr_options->login_page_id > 0 ) ? $mepr_options->login_page_id : 0;
						$is_login_page = is_page( $login_page_id );

						if ( $is_login_page ) {
							do_action( 'zerospam_memberpress_login_scripts' );
						}
					}
				);
			}
		}
	}

	/**
	 * Load the add-on scripts
	 *
	 * @param array $prereqs Script keys.
	 */
	public function scripts( $prereqs ) {
		$scripts = apply_filters( 'zerospam_memberpress_registration_scripts', $prereqs );

		return $scripts;
	}

	/**
	 * Adds Zero Spam's honeypot field.
	 */
	public function add_honeypot() {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Processes a registration submission.
	 *
	 * @param array $errors Array of errors.
	 */
	public function process_registration_form( $errors ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'memberpress_regsitration_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'post' => $post,
			'type' => 'memberpress_registration',
		);

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Begin validation checks.
		$validation_errors = array();

		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Check blocked email domains.
		if (
			! empty( $post['user_email'] ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $post['user_email'] )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		// @codingStandardsIgnoreLine
		$filtered_errors = apply_filters( 'zerospam_preprocess_memberpress_registration', array(), $post, 'memberpress_regsitration_spam_message' );

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
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_memberpress_registrations' ) ) {
					\ZeroSpam\Includes\DB::log( 'memberpress_registration', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$errors[] = $error_message;
		}

		return $errors;
	}

	/**
	 * Processes a login submission.
	 *
	 * @param array $errors Array of errors.
	 */
	public function process_login_form( $errors ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'memberpress_login_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'post' => $post,
			'type' => 'memberpress_login',
		);

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Begin validation checks.
		$validation_errors = array();

		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		// @codingStandardsIgnoreLine
		$filtered_errors = apply_filters( 'zerospam_preprocess_memberpress_login', array(), $post, 'memberpress_login_spam_message' );
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
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_memberpress_logins' ) ) {
					\ZeroSpam\Includes\DB::log( 'memberpress_login', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$errors[] = $error_message;
		}

		return $errors;
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['memberpress_registration'] = __( 'MemberPress Registration', 'zero-spam' );
		$types['memberpress_login']        = __( 'MemberPress Login', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['memberpress'] = array(
			'title' => __( 'MemberPress Integration', 'zero-spam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 * @param array $options  Array of saved database options.
	 */
	public function settings( $settings, $options ) {
		$default_spam_message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		// Registration protection.
		$settings['memberpress_registration_info'] = array(
			'section' => 'memberpress',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					__( '<h3 style="margin: 0">Registration Protection</h3>', 'zero-spam' ),
					array(
						'h3' => array(
							'style' => array(),
						),
					)
				)
			),
		);

		$settings['verify_memberpress_registration'] = array(
			'title'       => __( 'Protect Registration Forms', 'zero-spam' ),
			'section'     => 'memberpress',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor MemberPress registrations for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_memberpress_registration'] ) ? $options['verify_memberpress_registration'] : false,
			'recommended' => 'enabled',
		);

		$settings['memberpress_regsitration_spam_message'] = array(
			'title'       => __( 'Registration Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When registration protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'memberpress',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $default_spam_message,
			'value'       => ! empty( $options['memberpress_regsitration_spam_message'] ) ? $options['memberpress_regsitration_spam_message'] : $default_spam_message,
			'recommended' => $default_spam_message,
		);

		$settings['log_blocked_memberpress_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'memberpress',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked registration attempts. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_memberpress_registrations'] ) ? $options['log_blocked_memberpress_registrations'] : false,
			'recommended' => 'enabled',
		);

		// User login protection.
		$settings['memberpress_login_info'] = array(
			'section' => 'memberpress',
			'type'    => 'html',
			'html'    => sprintf(
				wp_kses(
					__( '<h3 style="margin-bottom: 0">User Login Protection</h3>', 'zero-spam' ),
					array(
						'h3' => array(
							'style' => array(),
						),
					)
				)
			),
		);

		$settings['verify_memberpress_login'] = array(
			'title'       => __( 'Protect Login Forms', 'zero-spam' ),
			'section'     => 'memberpress',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor MemberPress logins for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_memberpress_login'] ) ? $options['verify_memberpress_login'] : false,
			'recommended' => 'enabled',
		);

		$settings['memberpress_login_spam_message'] = array(
			'title'       => __( 'Login Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When login protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'memberpress',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $default_spam_message,
			'value'       => ! empty( $options['memberpress_login_spam_message'] ) ? $options['memberpress_login_spam_message'] : $default_spam_message,
			'recommended' => $default_spam_message,
		);

		$settings['log_blocked_memberpress_logins'] = array(
			'title'       => __( 'Log Blocked Logins', 'zero-spam' ),
			'section'     => 'memberpress',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked login attempts. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_memberpress_logins'] ) ? $options['log_blocked_memberpress_logins'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
