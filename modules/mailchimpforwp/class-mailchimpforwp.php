<?php
/**
 * Mailchimp for WordPress class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\MailchimpForWP;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Mailchimp for WordPress class
 */
class MailchimpForWP {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_mailchimp4wp' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Add Zero Spam's honeypot field to the registration form.
			add_filter( 'mc4wp_form_content', array( $this, 'add_honeypot' ), 10, 3 );

			// Preprocess Mailchimp form submissions.
			add_filter( 'mc4wp_form_errors', array( $this, 'process_form' ), 10, 1 );

			// Add the error key and message.
			add_filter( 'mc4wp_form_messages', array( $this, 'error_keys' ), 10, 1 );

			// Add scripts.
			add_action( 'mc4wp_load_form_scripts', array( $this, 'scripts' ), 10 );
		}
	}

	/**
	 * Load the add-on scripts
	 */
	public function scripts() {
		do_action( 'zerospam_mailchimp4wp_scripts' );
	}

	/**
	 * Adds Zero Spam's honeypot field
	 *
	 * @param string             $content Form content.
	 * @param MC4WP_Form         $form    Form object.
	 * @param MC4WP_Form_Element $element Form element.
	 */
	public function add_honeypot( $content, $form, $element ) {
		$content .= \ZeroSpam\Core\Utilities::honeypot_field();

		return $content;
	}

	/**
	 * Registers an additional Mailchimp for WP error message to match our error
	 * code from above.
	 *
	 * @param array $messages Array of error codes.
	 */
	public function error_keys( $messages ) {
		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'mailchimp4wp_spam_message' );

		$messages['zerospam'] = $error_message;

		return $messages;
	}

	/**
	 * Processes a Mailchimp form submission.
	 *
	 * @param array $errors An array of error codes.
	 */
	public function process_form( $errors ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Create the details array for logging & sharing data.
		$details = array(
			'post' => $post,
			'type' => 'mailchimp4wp',
		);

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Begin validation checks.
		$validation_errors = array();

		if ( isset( $post[ $honeypot_field_name ] ) && ! empty( $post[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Check email.
		if ( ! empty( $post['EMAIL'] ) && ! \ZeroSpam\Core\Utilities::is_email( $post['EMAIL'] ) ) {
			$validation_errors[] = 'invalid_email';
		}

		// Check blocked email domains.
		if (
			! empty( $post['EMAIL'] ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $post['EMAIL'] )
		) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		// @codingStandardsIgnoreLine
		$filtered_errors = apply_filters( 'zerospam_preprocess_mailchimp4wp', array(), $post, 'mailchimp4wp_spam_message' );

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
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_mailchimp4wp' ) ) {
					\ZeroSpam\Includes\DB::log( 'mailchimp4wp', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$errors[] = 'zerospam';
		}

		return $errors;
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['mailchimp4wp'] = __( 'Mailchimp for WordPress', 'zero-spam' );

		return $types;
	}

	/**
	 * Admin section
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['mailchimp4wp'] = array(
			'title' => __( 'Mailchimp for WordPress', 'zero-spam' ),
		);

		return $sections;
	}

	/**
	 * Admin settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-mailchimp4wp' );

		$settings['verify_mailchimp4wp'] = array(
			'title'       => __( 'Protect Forms', 'zero-spam' ),
			'section'     => 'mailchimp4wp',
			'module'      => 'mailchimp4wp',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor Mailchimp form submissions for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_mailchimp4wp'] ) ? $options['verify_mailchimp4wp'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['mailchimp4wp_spam_message'] = array(
			'title'       => __( 'Mailchimp Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When Mailchimp form protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'mailchimp4wp',
			'module'      => 'mailchimp4wp',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['mailchimp4wp_spam_message'] ) ? $options['mailchimp4wp_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_mailchimp4wp'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'mailchimp4wp',
			'module'      => 'mailchimp4wp',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked Mailchimp form submissions. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_mailchimp4wp'] ) ? $options['log_blocked_mailchimp4wp'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
