<?php
/**
 * Registration class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\Registration;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Registration
 */
class Registration {
	/**
	 * Registration constructor
	 */
	public function __construct() {

		if ( get_option( 'users_can_register' ) ) {
			add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
			add_filter( 'zerospam_settings', array( $this, 'settings' ) );
			add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'verify_registrations' ) && ZeroSpam\Core\Access::process() ) {
				add_action( 'register_form', array( $this, 'register_form' ) );
				add_action( 'register_form', array( $this, 'honeypot' ) );
				add_filter( 'registration_errors', array( $this, 'preprocess_registration' ), 10, 3 );
			}
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['registration'] = __( 'Registration', 'zerospam' );

		return $types;
	}

	/**
	 * Fires following the ‘Email’ field in the user registration form.
	 */
	public function register_form() {
		do_action( 'zerospam_register_form' );
	}

	/**
	 * Preprocess registrations
	 *
	 * @param WP_Error $errors A WP_Error object containing any errors encountered during registration.
	 * @param string   $sanitized_user_login User's username after it has been sanitized.
	 * @param string   $user_email User's email.
	 */
	public function preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
		// Check honeypot.
		// @codingStandardsIgnoreLine
		if ( ! empty( $_REQUEST[ ZeroSpam\Core\Utilities::get_honeypot() ] ) ) {
			$message = ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' );
			$errors->add( 'zerospam_error', $message );

			$details = array(
				'user_login' => $sanitized_user_login,
				'user_email' => $user_email,
				'failed'     => 'honeypot',
			);
			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_registrations' ) ) {
				ZeroSpam\Includes\DB::log( 'registration', $details );
			}

			// Share the detection if enabled.
			if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
				$details['type'] = 'registration';
				do_action( 'zerospam_share_detection', $details );
			}
		}

		return apply_filters( 'zerospam_registration_errors', $errors, $sanitized_user_login, $user_email );
	}

	/**
	 * Add a 'honeypot' field to the registration form
	 */
	public function honeypot() {
		// @codingStandardsIgnoreLine
		echo ZeroSpam\Core\Utilities::honeypot_field();
	}

	/**
	 * Registration sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['registration'] = array(
			'title' => __( 'Registration Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * Registration settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['verify_registrations'] = array(
			'title'       => __( 'Protect Registrations', 'zerospam' ),
			'section'     => 'registration',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => __( 'Monitor registrations for malicious or automated spambots.', 'zerospam' ),
			),
			'value'       => ! empty( $options['verify_registrations'] ) ? $options['verify_registrations'] : false,
			'recommended' => 'enabled',
		);

		$message = __( 'You have been flagged as spam/malicious by WordPress Zero Spam.', 'zerospam' );

		$settings['registration_spam_message'] = array(
			'title'       => __( 'Spam/Malicious Message', 'zerospam' ),
			'desc'        => __( 'When registration protection is enabled, the message displayed to the user when a registration has been detected as spam/malicious.', 'zerospam' ),
			'section'     => 'registration',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['registration_spam_message'] ) ? $options['registration_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zerospam' ),
			'section'     => 'registration',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'Enables logging blocked registrations. <strong>Recommended for enhanced protection.</strong>', 'zerospam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'       => ! empty( $options['log_blocked_registrations'] ) ? $options['log_blocked_registrations'] : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}
}
