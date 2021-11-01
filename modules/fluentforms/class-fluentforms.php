<?php
/**
 * Fluent Forms class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\FluentForms;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Fluent Forms
 */
class FluentForms {
	/**
	 * Fluent Forms constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'verify_fluentforms' ) && ZeroSpam\Core\Access::process() ) {
			// @TODO
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['fluentforms'] = __( 'Fluent Forms', 'zerospam' );

		return $types;
	}

	/**
	 * Fluent Forms sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['fluentforms'] = array(
			'title' => __( 'Fluent Forms Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Fluent Forms settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_fluentforms'] = array(
			'title'       => __( 'Protect Fluent Form Submissions', 'zerospam' ),
			'section'     => 'fluentforms',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor Fluent Form submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_fluentforms'] ) ? $options['verify_fluentforms'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );

		$settings['fluentforms_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When Fluent Form protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'fluentforms',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['fluentforms_spam_message'] ) ? $options['fluentforms_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_fluentforms'] = array(
			'title'       => __( 'Log Blocked Fluent Form Submissions', 'zerospam' ),
			'section'     => 'fluentforms',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked Fluent Form submissions. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_fluentforms'] ) ? $options['log_blocked_fluentforms'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
