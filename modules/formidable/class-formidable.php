<?php
/**
 * Formidable class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Formidable;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Formidable
 */
class Formidable {
	/**
	 * Formidable constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'verify_formidable' ) && ZeroSpam\Core\Access::process() ) {
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
		$types['formidable'] = __( 'Formidable', 'zerospam' );

		return $types;
	}

	/**
	 * Formidable sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['formidable'] = array(
			'title' => __( 'Formidable Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Formidable settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_formidable'] = array(
			'title'       => __( 'Protect Formidable Submissions', 'zerospam' ),
			'section'     => 'formidable',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor Formidable submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_formidable'] ) ? $options['verify_formidable'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );

		$settings['formidable_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When Formidable protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'formidable',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['formidable_spam_message'] ) ? $options['formidable_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_formidable'] = array(
			'title'       => __( 'Log Blocked Formidable Submissions', 'zerospam' ),
			'section'     => 'formidable',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked Formidable submissions. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'    => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
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
		echo ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Preprocess submission
	 *
	 * @param array $errors Array of errors.
	 * @param array $values Array of values.
	 */
	public function preprocess_submission( $errors, $values ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		// Check honeypot.
		// @codingStandardsIgnoreLine
		if ( ! empty( $_REQUEST[ ZeroSpam\Core\Utilities::get_honeypot() ] ) ) {
			$message = ZeroSpam\Core\Utilities::detection_message( 'formidable_spam_message' );

			$errors['zerospam_honeypot'] = $message;

			$details           = $values;
			$details['failed'] = 'honeypot';

			// Log if enabled.
			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_formidable' ) ) {
				ZeroSpam\Includes\DB::log( 'formidable', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'formidable';
				do_action( 'zerospam_share_detection', $details );
			}
		}

		return $errors;
	}
}
