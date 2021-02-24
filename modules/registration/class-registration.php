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
			add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

			$settings = ZeroSpam\Core\Settings::get_settings();
			if ( ! empty( $settings['verify_registrations']['value'] ) && 'enabled' === $settings['verify_registrations']['value'] && ZeroSpam\Core\Access::process() ) {
				add_action( 'register_form', array( $this, 'honeypot' ) );
				add_filter( 'registration_errors', array( $this, 'preprocess_registration' ), 10, 3 );
			}
		}
	}

	/**
	 * Add to the types array.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function types( $types ) {
		$types['registration'] = __( 'Registration', 'zerospam' );

		return $types;
	}

	/**
	 * Preprocess registrations.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
		$settings = ZeroSpam\Core\Settings::get_settings();
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		// Check honeypot.
		if (
			! empty( $_REQUEST[ $honeypot ] )
		) {
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			if ( ! empty( $settings['registration_spam_message']['value'] ) ) {
				$message = $settings['registration_spam_message']['value'];
			}
			$errors->add( 'zerospam_error', __( $message, 'zerospam' ) );

			if ( ! empty( $settings['log_blocked_registrations']['value'] ) && 'enabled' === $settings['log_blocked_registrations']['value'] ) {
				$details = array(
					'user_login' => $sanitized_user_login,
					'user_email' => $user_email,
					'failed'     => 'honeypot',
				);
				ZeroSpam\Includes\DB::log( 'registration', $details );
			}
		}

		$errors = apply_filters( 'zerospam_registration_errors', $errors, $sanitized_user_login, $user_email );

		return $errors;
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
	 * Registration settings.
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
			$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );
			$settings['registration_spam_message'] = array(
				'title'       => __( 'Registration Spam/Malicious Message', 'zerospam' ),
				'desc'        => __( 'Displayed to the user when a registration is detected as spam/malicious.', 'zerospam' ),
				'section'     => 'registration',
				'type'        => 'text',
				'field_class' => 'large-text',
				'placeholder' => $message,
				'value'       => ! empty( $options['registration_spam_message'] ) ? $options['registration_spam_message'] : $message,
			);
		}

		$settings['log_blocked_registrations'] = array(
			'title'   => __( 'Log Blocked Registrations', 'zerospam' ),
			'section' => 'registration',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables logging blocked registrations. High traffic sites should leave this disabled.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['log_blocked_registrations'] ) ? $options['log_blocked_registrations'] : false,
		);

		return $settings;
	}
}
