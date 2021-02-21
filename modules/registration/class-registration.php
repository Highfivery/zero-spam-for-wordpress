<?php
/**
 * Registration class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Registration;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Registration.
 *
 * @since 5.0.0
 */
class Registration {
	/**
	 * Registration constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		if ( get_option( 'users_can_register' ) ) {
			add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
			add_filter( 'zerospam_settings', array( $this, 'settings' ) );

			add_action( 'register_form', array( $this, 'honeypot' ) );
			add_filter( 'registration_errors', array( $this, 'preprocess_registrations' ), 10, 3 );
		}
	}

	/**
	 * Preprocess registrations.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function preprocess_registrations( $errors, $sanitized_user_login, $user_email ) {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		if (
			! empty( $_REQUEST[ $honeypot ] )
		) {
			$message = __( 'Your IP address has been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			if ( ! empty( $settings['registration_spam_message']['value'] ) ) {
				$message = $settings['registration_spam_message']['value'];
			}
			$errors->add( 'zerospam_error', __( $message, 'zerospam' ) );

			return $errors;
		}
	}

	/**
	 * Add a 'honeypot' field to the registration form.
	 *
	 * @since 5.0.0
	 *
	 * @return string HTML to append to the registration form.
	 */
	public function honeypot() {
		echo ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Registration sections.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function sections( $sections ) {
		$sections['registration'] = array(
			'title' => __( 'Registration Settings', 'zerospam' ),
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

		$settings['verify_registrations'] = array(
			'title'   => __( 'Detect Spam/Malicious Registrations', 'zerospam' ),
			'section' => 'registration',
			'type'    => 'checkbox',
			'options' => array(
				'enabled' => __( 'Monitor registrations for malicious or automated spambots.', 'zerospam' ),
			),
			'value'   => ! empty( $options['verify_registrations'] ) ? $options['verify_registrations'] : false,
		);

		if ( ! empty( $options['verify_registrations'] ) && 'enabled' === $options['verify_registrations'] ) {
			$message = __( 'Your IP address has been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			$settings['registration_spam_message'] = array(
				'title'       => __( 'Registration Spam/Malicious Message', 'zerospam' ),
				'desc'        => __( 'Displayed to the user when a registration is detected as spam/malicious.', 'zerospam' ),
				'section'     => 'registration',
				'type'        => 'text',
				'class'       => 'large-text',
				'placeholder' => $message,
				'value'       => ! empty( $options['registration_spam_message'] ) ? $options['registration_spam_message'] : $message,
			);
		}

		return $settings;
	}
}
