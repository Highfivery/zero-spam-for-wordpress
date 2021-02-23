<?php
/**
 * Contact Form 7 class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\ContactForm7;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Contact Form 7.
 *
 * @since 5.0.0
 */
class ContactForm7 {
	/**
	 * Registration constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		$settings = ZeroSpam\Core\Settings::get_settings();
		if ( ! empty( $settings['verify_contactform7']['value'] ) && 'enabled' === $settings['verify_contactform7']['value'] ) {
			add_filter( 'wpcf7_form_elements', array( $this, 'honeypot' ), 10, 1 );
			add_filter( 'wpcf7_validate', array( $this, 'preprocess_submission' ), 10, 2 );
		}
	}

	/**
	 * Add a 'honeypot' field to the form.
	 *
	 * @since 5.0.0
	 *
	 * @return string HTML to append to the form.
	 */
	public function honeypot( $this_form_do_shortcode ) {
		$this_form_do_shortcode .= ZeroSpam\Core\Utilities::honeypot_field();

		return $this_form_do_shortcode;
	}

	/**
	 * Preprocess submission.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function preprocess_submission( $result, $tags ) {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		// Check honeypot.
		if (
			! empty( $_REQUEST[ $honeypot ] )
		) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			if ( ! empty( $settings['contactform7_spam_message']['value'] ) ) {
				$message = $settings['contactform7_spam_message']['value'];
			}
			$result->invalidate( $tags[0], $message );

			if ( ! empty( $settings['log_blocked_contactform7']['value'] ) && 'enabled' === $settings['log_blocked_contactform7']['value'] ) {
				$_REQUEST['failed'] = 'honeypot';
				ZeroSpam\Includes\DB::log( 'contactform7', $_REQUEST );
			}
		}

		return $result;
	}

	/**
	 * Add to the types array.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function types( $types ) {
		$types['contactform7'] = __( 'Contact Form 7', 'zerospam' );

		return $types;
	}

	/**
	 * Registration sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['contactform7'] = array(
			'title' => __( 'Contact Form 7', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Botscout settings.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_contactform7'] = array(
			'title'   => __( 'Detect Contact Form 7 Submissions', 'zerospam' ),
			'section' => 'contactform7',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => __( 'Monitor Contact Form 7 form submissions for malicious or automated spambots.', 'zerospam' ),
			),
			'value'   => ! empty( $options['verify_contactform7'] ) ? $options['verify_contactform7'] : false,
		);

		if ( ! empty( $options['verify_contactform7'] ) && 'enabled' === $options['verify_contactform7'] ) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			$settings['contactform7_spam_message'] = array(
				'title'       => __( 'Contact Form 7 Spam/Malicious Message', 'zerospam' ),
				'desc'        => __( 'Displayed to the user when a submission is detected as spam/malicious.', 'zerospam' ),
				'section'     => 'contactform7',
				'type'        => 'text',
				'field_class' => 'large-text',
				'placeholder' => $message,
				'value'       => ! empty( $options['contactform7_spam_message'] ) ? $options['contactform7_spam_message'] : $message,
			);
		}

		$settings['log_blocked_contactform7'] = array(
			'title'   => __( 'Log Blocked Contact Form 7 Submissions', 'zerospam' ),
			'section' => 'contactform7',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables logging blocked Contact Form 7 submissions. High traffic sites should leave this disabled.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['log_blocked_contactform7'] ) ? $options['log_blocked_contactform7'] : false,
		);

		return $settings;
	}
}
