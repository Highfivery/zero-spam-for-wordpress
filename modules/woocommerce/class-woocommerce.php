<?php
/**
 * WooCommerce class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\WooCommerce;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * WooCommerce.
 */
class WooCommerce {
	/**
	 * WooCommerce constructor.
	 */
	public function __construct() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ) );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		$settings = ZeroSpam\Core\Settings::get_settings();
		if ( ! empty( $settings['woocommerce_protection']['value'] ) && 'enabled' === $settings['woocommerce_protection']['value'] && ZeroSpam\Core\Access::process() ) {
			$settings = ZeroSpam\Core\Settings::get_settings();
			if ( ! empty( $settings['verify_registrations']['value'] ) && 'enabled' === $settings['verify_registrations']['value'] ) {
				add_action( 'woocommerce_register_form', array( $this, 'honeypot' ) );
				add_action( 'woocommerce_register_post', array( $this, 'preprocess_registration' ), 10, 3 );
			}
		}
	}

	/**
	 * Add to the types array.
	 */
	public function types( $types ) {
		$types['woocommerce_registration'] = __( 'Registration (WooCommerce)', 'zerospam' );

		return $types;
	}

	/**
	 * Registration sections.
	 */
	public function sections( $sections ) {
		$sections['woocommerce'] = array(
			'title' => __( 'WooCommerce Integration', 'zerospam' ),
		);

		return $sections;
	}

	/**
	 * WooCommerce settings.
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
	 * Add a 'honeypot' field to the WooCommerce registration form.
	 */
	public function honeypot() {
		$honeypot = ZeroSpam\Core\Utilities::get_honeypot();

		woocommerce_form_field(
			$honeypot,
			array( 'type' => 'hidden' )
		);
	}

	/**
	 * Preprocess registrations.
	 */
	public function preprocess_registration( $username, $email, $errors ) {
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
