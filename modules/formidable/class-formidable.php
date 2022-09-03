<?php
/**
 * Formidable integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Formidable;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Formidable
 */
class Formidable {
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
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_formidable' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'frm_entry_form', array( $this, 'honeypot' ), 10, 1 );
			add_filter( 'frm_validate_entry', array( $this, 'preprocess_submission' ), 10, 2 );
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['formidable'] = __( 'Formidable', 'zero-spam' );

		return $types;
	}

	/**
	 * Formidable sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['formidable'] = array(
			'title'    => __( 'Formidable', 'zero-spam' ),
			'icon'     => 'modules/formidable/icon-formidable.png',
			'supports' => array( 'honeypot' ),
		);

		return $sections;
	}

	/**
	 * Formidable settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-formidable' );

		$settings['verify_formidable'] = array(
			'title'       => __( 'Protect Formidable Submissions', 'zero-spam' ),
			'desc'        => __( 'Protects & monitors Formidable submissions.', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_formidable'] ) ? $options['verify_formidable'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['formidable_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['formidable_spam_message'] ) ? $options['formidable_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_formidable'] = array(
			'title'       => __( 'Log Blocked Formidable Submissions', 'zero-spam' ),
			'section'     => 'formidable',
			'module'      => 'formidable',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked Formidable submissions in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_formidable'] ) ? $options['log_blocked_formidable'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}

	/**
	 * Add a 'honeypot' field to the form
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function honeypot( $form_data ) {
		// @codingStandardsIgnoreLine
		echo \ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Preprocess submission
	 *
	 * @param array $errors Array of errors.
	 * @param array $values Array of values.
	 */
	public function preprocess_submission( $errors, $values ) {
		// @codingStandardsIgnoreLine
		$post = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the error message.
		$error_message = \ZeroSpam\Core\Utilities::detection_message( 'formidable_spam_message' );

		// Create the details array for logging & sharing data.
		$details = $values;

		$details['type'] = 'formidable';

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

		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_formidable' ) ) {
					\ZeroSpam\Includes\DB::log( 'formidable', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			$errors['zerospam_honeypot'] = $error_message;
		}

		return $errors;
	}
}
