<?php
/**
 * Fluent Forms class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\FluentForms;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Fluent Forms
 */
class FluentForms {
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
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 2 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_fluentforms' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			// Load scripts.
			add_action( 'fluentform_load_form_assets', array( $this, 'scripts' ), 10 );

			// Adds Zero Spam's honeypot field.
			add_filter( 'fluentform_rendering_form', array( $this, 'render_form' ), 10, 1 );

			// Processes the form.
			add_action( 'fluentform_before_insert_submission', array( $this, 'process_form' ), 10, 3 );

			// Validates email addresses.
			add_filter( 'fluentform_validate_input_item_input_email', array( $this, 'validate_email' ), 10, 5 );
		}
	}

	/**
	 * Fires before a form is rendered.
	 */
	public function scripts() {
		do_action( 'zerospam_fluentforms_scripts' );
	}

	/**
	 * Adds Zero Spam's custom form fields.
	 *
	 * @see https://fluentforms.com/docs/fluentform_rendering_form/
	 *
	 * @param $form The $form Object.
	 */
	public function render_form( $form ) {
		// Add Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		$form->fields['fields'][] = array(
			'element'    => 'input_hidden',
			'attributes' => array(
				'type'  => 'hidden',
				'name'  => $honeypot_field_name,
				'value' => '',
			),
		);

		return $form;
	}

	/**
	 * Processes a Fluent Form submission after it's validation is completed.
	 *
	 * @see https://fluentforms.com/docs/fluentform_before_insert_submission/
	 *
	 * @param array  $insert_data submission_data Array.
	 * @param array  $data        $_POST[‘data’] from submission.
	 * @param object $form        The $form Object.
	 */
	public function process_form( $insert_data, $data, $form ) {
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'fluentforms_spam_message' );

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();

		// Create the details array for logging & sharing data.
		$details = array(
			'insert_data' => $insert_data,
			'data'        => $data,
			'form'        => array(
				'id'    => $form->id,
				'title' => $form->title,
			),
		);

		if ( ! isset( $data[ $honeypot_field_name ] ) || ! empty( $data[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$details['failed'] = 'honeypot';

			// Log the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_fluentforms' ) ) {
				\ZeroSpam\Includes\DB::log( 'fluent_form', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'fluent_form';
				do_action( 'zerospam_share_detection', $details );
			}

			wp_send_json(
				array(
					'errors' => array(
						'zerospam_honeypot' => $error_message,
					),
				),
				422
			);
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$errors = apply_filters( 'zerospam_preprocess_fluentform_submission', array(), $data, 'fluentforms_spam_message' );

		if ( ! empty( $errors ) ) {
			$errors_array = array();
			foreach ( $errors as $key => $message ) {
				$errors_array[ $key ] = $message;

				$details['failed'] = str_replace( 'zerospam_', '', $key );

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_fluentforms' ) ) {
					\ZeroSpam\Includes\DB::log( 'fluent_form', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					$details['type'] = 'fluent_form';
					do_action( 'zerospam_share_detection', $details );
				}
			}

			wp_send_json(
				array(
					'errors' => $errors_array,
				),
				422
			);
		}
	}

	/**
	 * Validates email inputs.
	 *
	 * @see https://fluentforms.com/docs/fluentform_validate_input_item_input_text/
	 *
	 * @param string $error     Error message.
	 * @param array  $field     Contains the fill field settings.
	 * @param array  $form_data Contains all the user input values as key pair.
	 * @param array  $fields    All fields of the form.
	 * @param object $form      The $form Object.
	 */
	public function validate_email( $error, $field, $form_data, $fields, $form ) {
		$field_name = $field['name'];
		if ( empty( $form_data[ $field_name ] ) ) {
			return $error;
		}

		// Check blocked email domains.
		if (
			! empty( $form_data[ $field_name ] ) &&
			\ZeroSpam\Core\Utilities::is_email_domain_blocked( $form_data[ $field_name ] )
		) {
			$error_message = \ZeroSpam\Core\Utilities::detection_message( 'fluentforms_spam_message' );

			return array( $error_message );
		}

		return $error;
	}


	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['fluent_form'] = __( 'Fluent Form', 'zero-spam' );

		return $types;
	}

	/**
	 * Fluent Forms sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['fluentforms'] = array(
			'title' => __( 'Fluent Forms Integration', 'zero-spam' ),
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
		$settings['verify_fluentforms'] = array(
			'title'       => __( 'Protect Fluent Form Submissions', 'zero-spam' ),
			'section'     => 'fluentforms',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor Fluent Form submissions for malicious or automated spambots.', 'zero-spam' ),
			),
			'value'       => ! empty( $options['verify_fluentforms'] ) ? $options['verify_fluentforms'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['fluentforms_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zero-spam' ),
			'desc'        => __( 'When Fluent Form protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zero-spam' ),
			'section'     => 'fluentforms',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['fluentforms_spam_message'] ) ? $options['fluentforms_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_fluentforms'] = array(
			'title'       => __( 'Log Blocked Fluent Form Submissions', 'zero-spam' ),
			'section'     => 'fluentforms',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked Fluent Form submissions. <strong>Recommended for enhanced protection.</strong>', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zero-spam' ),
			),
			'value'       => ! empty( $options['log_blocked_fluentforms'] ) ? $options['log_blocked_fluentforms'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
