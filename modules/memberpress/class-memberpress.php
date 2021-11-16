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
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_memberpress_registration' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Add Zero Spam's honeypot field to the registration form.
			add_action( 'mepr-checkout-before-submit', array( $this, 'add_honeypot' ) );

			// Preprocess registration form submissions.
			add_filter( 'mepr-validate-signup', array( $this, 'process_form' ) );

			// Add scripts.
			add_filter( 'mepr-signup-scripts', array( $this, 'scripts' ), 10, 1 );
		}
	}

	/**
	 * Load the add-on scripts
	 *
	 * @param array $prereqs Script keys.
	 */
	public function scripts( $prereqs ) {
		$scripts = apply_filters( 'zerospam_memberpress_scripts', $prereqs );

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
	public function process_form( $errors ) {
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
		$blocked_email_domains = \ZeroSpam\Core\Settings::get_settings( 'blocked_email_domains' );
		if ( $blocked_email_domains && ! empty( $post['user_email'] ) ) {
			$blocked_email_domains_array = explode( "\n", $blocked_email_domains );
			$blocked_email_domains_array = array_map( 'trim', $blocked_email_domains_array );
			$tmp_domain                  = explode( '@', $post['user_email'] );
			$domain                      = trim( array_pop( $tmp_domain ) );

			if ( in_array( $domain, $blocked_email_domains_array, true ) ) {
				// Email domain has been blocked.
				$validation_errors[] = 'blocked_email_domain';
			}
		}

		// Fire hook for additional validation (ex. David Walsh script).
		// @codingStandardsIgnoreLine
		$errors = apply_filters( 'zerospam_preprocess_memberpress_registration', array(), $post );

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
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['memberpress_registration'] = __( 'MemberPress Registration', 'zerospam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['memberpress'] = array(
			'title' => __( 'MemberPress Integration', 'zerospam' ),
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

		$settings['verify_memberpress_registration'] = array(
			'title'       => __( 'Protect Registration Forms', 'zerospam' ),
			'section'     => 'memberpress',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor MemberPress registrations for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_memberpress_registration'] ) ? $options['verify_memberpress_registration'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zerospam' );

		$settings['memberpress_regsitration_spam_message'] = array(
			'title'       => __( 'Registration Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When registration protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'memberpress',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['memberpress_regsitration_spam_message'] ) ? $options['memberpress_regsitration_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_memberpress_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zerospam' ),
			'section'     => 'memberpress',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked registration attempts. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_memberpress_registrations'] ) ? $options['log_blocked_memberpress_registrations'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
