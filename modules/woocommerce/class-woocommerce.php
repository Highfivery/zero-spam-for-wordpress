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

		// Run one-time option key migration.
		self::maybe_migrate_options();
	}

	/**
	 * Migrates legacy option keys to their correct names.
	 *
	 * Prior to 5.7.9:
	 * - The registration spam message was stored under the incorrect key
	 *   'registration_spam_message' instead of 'woocommerce_registration_spam_message'.
	 * - Checkout protection was implicitly tied to the registration toggle. This
	 *   migration enables the new 'verify_woocommerce_checkout' setting for sites
	 *   that already had registration protection enabled, and copies the registration
	 *   logging preference to the new checkout logging setting.
	 *
	 * Runs once per site.
	 */
	public static function maybe_migrate_options() {
		if ( get_transient( 'zerospam_woo_options_migrated' ) ) {
			return;
		}

		$options = get_option( 'zero-spam-woocommerce' );
		$updated = false;

		if ( is_array( $options ) ) {
			// Migrate the mismatched spam message key.
			if ( isset( $options['registration_spam_message'] ) ) {
				if ( empty( $options['woocommerce_registration_spam_message'] ) ) {
					$options['woocommerce_registration_spam_message'] = $options['registration_spam_message'];
				}

				unset( $options['registration_spam_message'] );
				$updated = true;
			}

			// Carry over checkout protection from the registration toggle so existing
			// sites don't silently lose checkout protection after the update.
			if (
				! empty( $options['verify_woocommerce_registrations'] ) &&
				! isset( $options['verify_woocommerce_checkout'] )
			) {
				$options['verify_woocommerce_checkout'] = $options['verify_woocommerce_registrations'];
				$updated = true;
			}

			// Carry over logging preference to the new checkout logging setting.
			if (
				! empty( $options['log_blocked_woocommerce_registrations'] ) &&
				! isset( $options['log_blocked_woocommerce_checkouts'] )
			) {
				$options['log_blocked_woocommerce_checkouts'] = $options['log_blocked_woocommerce_registrations'];
				$updated = true;
			}

			if ( $updated ) {
				update_option( 'zero-spam-woocommerce', $options );
			}
		}

		// Use a long-lived transient as a migration flag — cleaned up on uninstall.
		set_transient( 'zerospam_woo_options_migrated', 1, YEAR_IN_SECONDS );
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

		$types['woocommerce_checkout'] = array(
			'label' => __( 'Woo Checkout', 'zero-spam' ),
			'color' => '#96588a',
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
			'desc'        => __( 'Stop spam accounts from being created through WooCommerce.', 'zero-spam' ),
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
			'title'       => __( 'Registration Flagged Message', 'zero-spam' ),
			'desc'        => __( 'The message shown when WooCommerce detects a spam registration.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $message,
			'value'       => ! empty( $options['woocommerce_registration_spam_message'] ) ? $options['woocommerce_registration_spam_message'] : $message,
			'recommended' => $message,
		);

		$settings['log_blocked_woocommerce_registrations'] = array(
			'title'       => __( 'Log Blocked Registrations', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of blocked WooCommerce registration attempts.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_woocommerce_registrations'] ) ? 'enabled' : false,
			'recommended' => 'enabled',
		);

		$settings['verify_woocommerce_checkout'] = array(
			'title'       => __( 'Protect Checkout', 'zero-spam' ),
			'desc'        => __( 'Stop spam orders from being placed through WooCommerce checkout.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['verify_woocommerce_checkout'] ) ? 'enabled' : false,
			'recommended' => 'enabled',
		);

		$checkout_message = __( 'Your order could not be processed. Please contact us for assistance.', 'zero-spam' );

		$settings['woocommerce_checkout_spam_message'] = array(
			'title'       => __( 'Checkout Flagged Message', 'zero-spam' ),
			'desc'        => __( 'The message shown when WooCommerce detects a spam checkout attempt.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $checkout_message,
			'value'       => ! empty( $options['woocommerce_checkout_spam_message'] ) ? $options['woocommerce_checkout_spam_message'] : $checkout_message,
			'recommended' => $checkout_message,
		);

		$settings['log_blocked_woocommerce_checkouts'] = array(
			'title'       => __( 'Log Blocked Checkouts', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'checkbox',
			'desc'        => __( 'Keep a record of blocked WooCommerce checkout attempts.', 'zero-spam' ),
			'options'     => array(
				'enabled' => false,
			),
			'value'       => ! empty( $options['log_blocked_woocommerce_checkouts'] ) ? 'enabled' : false,
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

		$should_process = \ZeroSpam\Core\Access::process();

		// Registration protection.
		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_woocommerce_registrations' ) &&
			$should_process
		) {
			add_action( 'woocommerce_register_form', array( $this, 'add_honeypot_field' ) );
			add_action( 'woocommerce_register_form', array( $this, 'add_scripts' ) );
			add_action( 'woocommerce_register_post', array( $this, 'process_registration' ), 10, 3 );
		}

		// Checkout protection.
		if (
			'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_woocommerce_checkout' ) &&
			$should_process
		) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'add_scripts' ) );
			add_action( 'woocommerce_after_order_notes', array( $this, 'add_honeypot_field' ), 10 );
			add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) );
		}
	}

	/**
	 * Adds the 'honeypot' field to WooCommerce forms.
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
	 * Load the scripts.
	 *
	 * Uses centralized David Walsh script - selectors are managed in class-davidwalsh.php.
	 */
	public function add_scripts() {
		// Only add scripts if David Walsh is enabled.
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'davidwalsh' ) ) {
			wp_enqueue_script( 'zerospam-davidwalsh' );
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
		$filtered_errors = apply_filters( 'zerospam_preprocess_woocommerce_registration', array(), $data, 'woocommerce_registration_spam_message' );

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

	/**
	 * Process checkout for spam.
	 *
	 * Validates checkout submissions against Zero Spam protections.
	 */
	public function process_checkout() {
		// Get all posted form fields.
		// @codingStandardsIgnoreLine
		$data = \ZeroSpam\Core\Utilities::sanitize_array( $_POST );

		// Get the WooCommerce checkout spam message.
		$spam_message = \ZeroSpam\Core\Utilities::detection_message( 'woocommerce_checkout_spam_message' );

		// Get the billing email for validation.
		$billing_email = isset( $data['billing_email'] ) ? $data['billing_email'] : '';

		// Create the details array for logging & sharing data.
		$details = array(
			'email' => $billing_email,
			'data'  => $data,
			'type'  => 'woocommerce_checkout',
		);

		// Create the validation errors array.
		$validation_errors = array();

		// Check Zero Spam's honeypot field.
		$honeypot_field_name = \ZeroSpam\Core\Utilities::get_honeypot();
		if ( isset( $data[ $honeypot_field_name ] ) && ! empty( $data[ $honeypot_field_name ] ) ) {
			// Failed the honeypot check.
			$validation_errors[] = 'honeypot';
		}

		// Check blocked email domains against billing email.
		if ( ! empty( $billing_email ) && \ZeroSpam\Core\Utilities::is_email_domain_blocked( $billing_email ) ) {
			$validation_errors[] = 'blocked_email_domain';
		}

		// Fire hook for additional validation (ex. David Walsh script).
		$filtered_errors = apply_filters( 'zerospam_preprocess_woocommerce_checkout', array(), $data, 'woocommerce_checkout_spam_message' );

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
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'log_blocked_woocommerce_checkouts' ) ) {
					\ZeroSpam\Includes\DB::log( 'woocommerce_checkout', $details );
				}

				// Share the detection if enabled.
				if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'share_data' ) ) {
					do_action( 'zerospam_share_detection', $details );
				}
			}

			// Add WooCommerce notice for checkout.
			wc_add_notice( $spam_message, 'error' );
		}
	}
}
