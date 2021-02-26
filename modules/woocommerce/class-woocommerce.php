<?php
/**
 * WooCommerce class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\WooCommerce;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WooCommerce
 */
class WooCommerce {
	/**
	 * WooCommerce constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'woocommerce_protection' ) && ZeroSpam\Core\Access::process() ) {
			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'verify_registrations' ) ) {
				add_action( 'woocommerce_register_form', array( $this, 'honeypot' ) );
				add_action( 'woocommerce_register_post', array( $this, 'preprocess_registration' ), 10, 3 );
			}
		}
	}

	/**
	 * Add to the types array
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['woocommerce_registration'] = __( 'Registration (WooCommerce)', 'zerospam' );

		return $types;
	}

	/**
	 * WooCommerce sections
	 *
	 * @param array $sections Array of available setting sections.
	 */
	public function sections( $sections ) {
		$sections['woocommerce'] = array(
			'title' => __( 'WooCommerce Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * WooCommerce settings
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'wpzerospam' );

		$settings['woocommerce_protection'] = array(
			'title'   => __( 'WooCommerce Protection', 'zerospam' ),
			'section' => 'woocommerce',
			'type'    => 'checkbox',
			'desc'    => __( 'Enables integration with the WooCommerce.', 'zerospam' ),
			'options' => array(
				'enabled' => __( 'Enabled', 'zerospam' ),
			),
			'value'   => ! empty( $options['woocommerce_protection'] ) ? $options['woocommerce_protection'] : false,
		);

		return $settings;
	}

	/**
	 * Add a 'honeypot' field to the WooCommerce registration form
	 */
	public function honeypot() {
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		woocommerce_form_field(
			$honeypot,
			array( 'type' => 'hidden' )
		);
	}

	/**
	 * Preprocess registrations
	 *
	 * @param string $username The username.
	 * @param string $email The email.
	 * @param object $errors Errors object.
	 */
	public function preprocess_registration( $username, $email, $errors ) {
		$settings = ZeroSpam\Core\Settings::get_settings();

		// Check honeypot.
		// @codingStandardsIgnoreLine
		if ( ! empty( $_REQUEST[ ZeroSpam\Core\Utilities::get_honeypot() ] ) ) {
			$message = ZeroSpam\Core\Utilities::detection_message( 'registration_spam_message' );
			$errors->add( 'zerospam_error', $message );

			if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_registrations' ) ) {
				$details = array(
					'user_login' => $username,
					'user_email' => $email,
					'failed'     => 'honeypot',
				);
				ZeroSpam\Includes\DB::log( 'woocommerce_registration', $details );
			}
		}

		$errors = apply_filters( 'zerospam_registration_errors', $errors, $username, $email );

		return $errors;
	}
}
