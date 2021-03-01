<?php
/**
 * Contact Form 7 class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\ContactForm7;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Contact Form 7
 */
class ContactForm7 {
	/**
	 * Contact Form 7 constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'verify_contactform7' ) && ZeroSpam\Core\Access::process() ) {
			add_filter( 'wpcf7_form_elements', array( $this, 'honeypot' ), 10, 1 );
			add_filter( 'wpcf7_validate', array( $this, 'preprocess_submission' ), 10, 2 );
		}
	}

	/**
	 * Add a 'honeypot' field to the form
	 *
	 * @param string $this_replace_all_form_tags Form tags.
	 */
	public function honeypot( $this_replace_all_form_tags ) {
		$this_replace_all_form_tags .= ZeroSpam\Core\Utilities::honeypot_field();

		return $this_replace_all_form_tags;
	}

	/**
	 * Preprocess submission
	 *
	 * @param WPCF7_Validation $result Validation.
	 * @param WPCF7_FormTag    $tag Form tag.
	 */
	public function preprocess_submission( $result, $tag ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		// Check honeypot.
		// @codingStandardsIgnoreLine
		if ( ! empty( $_REQUEST[ ZeroSpam\Core\Utilities::get_honeypot() ] ) ) {
			$message = ZeroSpam\Core\Utilities::detection_message( 'contactform7_spam_message' );
			$result->invalidate( $tag[0], $message );

			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_contactform7' ) ) {
				$_REQUEST['failed'] = 'honeypot';
				// @codingStandardsIgnoreLine
				ZeroSpam\Includes\DB::log( 'contactform7', $_REQUEST );
			}
		}

		return $result;
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['contactform7'] = __( 'Contact Form 7', 'zerospam' );

		return $types;
	}

	/**
	 * CF7 sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['contactform7'] = array(
			'title' => __( 'Contact Form 7 Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * CF7 settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_contactform7'] = array(
			'title'       => __( 'Protect CF7 Submissions', 'zerospam' ),
			'section'     => 'contactform7',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor CF7 submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_contactform7'] ) ? $options['verify_contactform7'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );

		$settings['contactform7_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When CF7 protection is enabled, the message displayed to the user when a submission has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'contactform7',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['contactform7_spam_message'] ) ? $options['contactform7_spam_message'] : $message,
		);

		$settings['log_blocked_contactform7'] = array(
			'title'       => __( 'Log Blocked CF7 Submissions', 'zerospam' ),
			'section'     => 'contactform7',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked CF7 submissions. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_contactform7'] ) ? $options['log_blocked_contactform7'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
