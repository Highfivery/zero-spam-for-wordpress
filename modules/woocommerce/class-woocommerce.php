<?php
/**
 * WooCommerce integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Blocked email domains
 * 3. David Walsh technique
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Modules\WooCommerce;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Zero Spam WooCommerce Module Class
 */
class WooCommerce {
	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Register the Zero Spam detection types
	 *
	 * @param array $types Array of available detection types.
	 */
	public function types( $types ) {
		$types['woocommerce_registration'] = array(
			'label' => __( 'Woo Registration', 'zero-spam' ),
			'color' => '#7f54b3',
		);

		return $types;
	}

	/**
	 * Register the Zero Spam admin setting sections
	 *
	 * @param array $sections Array of admin setting sections.
	 */
	public function sections( $sections ) {
		$sections['woocommerce'] = array(
			'title'    => __( 'WooCommerce', 'zero-spam' ),
			'icon'     => 'modules/woocommerce/icon-woocommerce.svg',
			'supports' => array( 'honeypot', 'email', 'davidwalsh' ),
		);

		return $sections;
	}

	/**
	 * Register the Zero Spam admin settings for this module
	 *
	 * @param array $settings Array of available settings.
	 */
	public function settings( $settings ) {
		$options = get_option( 'zero-spam-woocommerce' );

		$settings['verify_woocommerce_registrations'] = array(
			'title'       => __( 'Protect Registrations', 'zero-spam' ),
			'desc'        => __( 'Protects & monitors WooCommerce registrations.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_woocommerce_registrations'] ) ? 'enabled' : false,
			'recommended' => 'enabled',
		);

		$message = __( 'Your IP has been flagged as spam/malicious.', 'zero-spam' );

		$settings['woocommerce_registration_spam_message'] = array(
			'title'       => __( 'Flagged Message', 'zero-spam' ),
			'desc'        => __( 'Message displayed when a submission has been flagged.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['registration_spam_message'] ) ? $options['registration_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_woocommerce_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'checkbox',
			'desc'        => wp_kses(
				__( 'When enabled, stores blocked WooCommerce registrations in the database.', 'zero-spam' ),
				array( 'strong' => array() )
			),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_woocommerce_registrations'] ) ? 'enabled' : false,
			'recommended' => 'enabled',
		);

		return $settings;
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'zerospam_setting_sections', array( $this, 'sections' ) );
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 2 );
		add_filter( 'zerospam_types', array( $this, 'types' ), 10, 1 );

		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_woocommerce_registrations' ) &&
			\ZeroSpam\Core\Access::process()
		) {
			add_action( 'woocommerce_register_form', array( $this, 'add_honeypot_field' ) );
			add_action( 'woocommerce_register_form', array( $this, 'add_scripts' ) );
			add_action( 'woocommerce_register_post', array( $this, 'process_registration' ), 10, 3 );

			add_action( 'woocommerce_before_checkout_form', array( $this, 'add_scripts' ) );
			add_action( 'woocommerce_after_order_notes', array( $this, 'add_honeypot_field' ), 10 );
		}
	}

	/**
	 * Adds the 'honeypot' field to the WooCommerce registration form
	 */
	public function add_honeypot_field() {
		woocommerce_form_field(
			\ZeroSpam\Core\Utilities::get_honeypot(),
			array(
				'type'  => 'hidden',
				'class' => array( 'zero-spam-hidden' ),
			)
		);
	}

	/**
	 * Load the scripts
	 */
	public function add_scripts() {
		// Only add scripts to the appropriate pages.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
			add_action( 'wp_footer', function() {
				echo '<script type="text/javascript">jQuery(".woocommerce-form-register, .woocommerce-checkout").ZeroSpamDavidWalsh();</script>';
			}, 999 );
		}
	}

	/**
	 * Preprocess registrations
	 *
	 * @param string $username Registration username.
	 * @param string $email    Registration email address.
	 * @param string $errors   WooCommerce error object.
	 */
	public function process_registration( $username, $email, $errors ) {
		// Get all posted form fields.
		// @codingStandardsIgnoreLine
		$data = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the WooCommerce registration spam message.
		$spam_message = \ZeroSpam\Core\Utilities::detection_message( 'woocommerce_registration_spam_message' );

		// Create the details array for logging & sharing data.
		$details = array(
			'username' => $username,
			'email'    => $email,
			'data'     => $data,
			'type'     => 'woocommerce_registration',
		);

		// Create the validation errors array.
		$validation_errors = array();

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();
		if ( isset( $data[ $honeypot_field_name ] ) && ! empty( $data[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Check email.
		if ( ! empty( $email ) && ! \ZeroSpam\Core\Utilities::is_email( $email ) ) {
			$validation_errors[] = 'invalid_email';
		}

		// Check blocked email domains.
		if ( \ZeroSpam\Core\Utilities::is_email_domain_blocked( $email ) ) {
			// Email domain has been blocked.
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$filtered_errors = apply_filters( 'zerospam_process_woocommerce_registration', array(), $data, 'woocommerce_registration_spam_message' );

		if ( ! empty( $filtered_errors ) ) {
			foreach ( $filtered_errors as $key => $message ) {
				$validation_errors[] = str_replace( 'zerospam_', '', $key );
			}
		}

		// Check for validation errors, then log & share if enabled.
		if ( ! empty( $validation_errors ) ) {
			// Failed validations, log & send details if enabled.
			foreach ( $validation_errors as $key => $fail ) {
				$details['failed'] = $fail;

				// Log the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_woocommerce_registrations' ) ) {
					\ZeroSpam\Includes\DB::log( 'woocommerce_registration', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			// Add the spam message to the WooCommerce errors object.
			$errors->add( 'zerospam_error', $spam_message );
		}
	}
}
