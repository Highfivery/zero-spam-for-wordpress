<?php
/**
 * WPForms class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\WPForms;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WPForms.
 */
class WPForms {
	/**
	 * WooCommerce constructor.
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === ZeroSpam\Core\Settings::get_settings('verify_wpforms') ) {
			add_action( 'wpforms_frontend_output', array( $this, 'honeypot' ), 10, 1 );
			add_action( 'wpforms_process', array( $this, 'preprocess_submission' ), 10, 3 );
		}
	}

	/**
	 * Add to the types array.
	 */
	public function types( $types ) {
		$types['wpforms'] = __( 'WPForms', 'zerospam' );

		return $types;
	}

	/**
	 * Registration sections.
	 */
	public function sections( $sections ) {
		$sections['wpforms'] = array(
			'title' => __( 'WPForms Settings', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * WPForms settings.
	 *
	 * Registers WPForms setting fields.
	 *
	 * @param array $settings Array of WordPress Zero Spam settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_wpforms'] = array(
			'title'   => __( 'Protect WPForms Submissions', 'zerospam' ),
			'section' => 'wpforms',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => __( 'Monitor WPForms form submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'   => ! empty( $options['verify_wpforms'] ) ? $options['verify_wpforms'] : false,
		);

		if ( ! empty( $options['verify_wpforms'] ) && 'enabled' === $options['verify_wpforms'] ) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			$settings['wpforms_spam_message'] = array(
				'title'       => __( 'WPForms Spam/Malicious Message', 'zerospam' ),
				'desc'        => __( 'Displayed to the user when a submission is detected as spam/malicious.', 'zerospam' ),
				'section'     => 'wpforms',
				'type'        => 'text',
				'field_class' => 'large-text',
				'placeholder' => $message,
				'value'       => ! empty( $options['wpforms_spam_message'] ) ? $options['wpforms_spam_message'] : $message,
			);
		}

		$settings['log_blocked_wpforms'] = array(
			'title'   => __( 'Log Blocked WPForms Submissions', 'zerospam' ),
			'section' => 'wpforms',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables logging blocked WPForms submissions. High traffic sites should leave this disabled.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['log_blocked_wpforms'] ) ? $options['log_blocked_wpforms'] : false,
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
	public function preprocess_submission( $entry, $form_data ) {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		// Check honeypot.
		if (
			! empty( $_REQUEST[ $honeypot ] )
		) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			if ( ! empty( $settings['wpforms_spam_message']['value'] ) ) {
				$message = $settings['wpforms_spam_message']['value'];
			}

			wpforms()->process->errors[ $form_data['id'] ][0] = $message;

			if ( ! empty( $settings['log_blocked_wpforms']['value'] ) && 'enabled' === $settings['log_blocked_wpforms']['value'] ) {
				$details = $entry;
				$details = array_merge( $details, $form_data );

				$details['failed'] = 'honeypot';
				ZeroSpam\Includes\DB::log( 'wpforms', $details );
			}
		}
	}
}
