<?php
/**
 * Formidable class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Formidable;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Formidable.
 */
class Formidable {
	/**
	 * Formidable constructor.
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
	 * Add to the types array.
	 */
	public function types( $types ) {
		$types['formidable'] = __( 'Formidable', 'zerospam' );

		return $types;
	}

	/**
	 * Formidable sections.
	 */
	public function sections( $sections ) {
		$sections['formidable'] = array(
			'title' => __( 'Formidable Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Formidable settings.
	 *
	 * Registers Formidable setting fields.
	 *
	 * @param array $settings Array of WordPress Zero Spam settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_formidable'] = array(
			'title'   => __( 'Protect Formidable Submissions', 'zerospam' ),
			'section' => 'formidable',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => __( 'Monitor Formidable submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'   => ! empty( $options['verify_formidable'] ) ? $options['verify_formidable'] : false,
		);

		if ( ! empty( $options['verify_formidable'] ) && 'enabled' === $options['verify_formidable'] ) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			$settings['formidable_spam_message'] = array(
				'title'       => __( 'Formidable Spam/Malicious Message', 'zerospam' ),
				'desc'        => __( 'Displayed to the user when a submission is detected as spam/malicious.', 'zerospam' ),
				'section'     => 'formidable',
				'type'        => 'text',
				'field_class' => 'large-text',
				'placeholder' => $message,
				'value'       => ! empty( $options['formidable_spam_message'] ) ? $options['formidable_spam_message'] : $message,
			);
		}

		$settings['log_blocked_formidable'] = array(
			'title'   => __( 'Log Blocked Formidable Submissions', 'zerospam' ),
			'section' => 'formidable',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables logging blocked Formidable submissions. High traffic sites should leave this disabled.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['log_blocked_formidable'] ) ? $options['log_blocked_formidable'] : false,
		);

		return $settings;
	}

	/**
	 * Add a 'honeypot' field to the form.
	 *
	 * @param array $form_data Form data and settings.
	 */
	public function honeypot( $form_data ) {
		echo ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Preprocess submission.
	 */
	public function preprocess_submission( $errors, $values ) {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		// Check honeypot.
		if (
			! empty( $_REQUEST[ $honeypot ] )
		) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			if ( ! empty( $settings['formidable_spam_message']['value'] ) ) {
				$message = $settings['formidable_spam_message']['value'];
			}

			$errors['zerospam_honeypot'] = $message;

			if ( ! empty( $settings['log_blocked_formidable']['value'] ) && 'enabled' === $settings['log_blocked_formidable']['value'] ) {
				$details           = $values;
				$details['failed'] = 'honeypot';
				ZeroSpam\Includes\DB::log( 'formidable', $details );
			}
		}

		return $errors;
	}
}
