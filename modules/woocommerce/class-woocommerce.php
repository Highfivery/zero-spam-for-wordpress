<?php
/**
 * WooCommerce integration module
 *
 * Malicious user detection techniques available:
 *
 * 1. Zero Spam honeypot field
 * 2. Blocked email domains
 * 3. David Walsh technique
 * 4. Card-testing limits (since 6.0.2): Card_Testing, Checkout_Guard and
 *    Checkout_Token, on the classic checkout and the Checkout block / Store API
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
	 * Order meta: the visitor's IP address as Zero Spam sees it.
	 */
	const META_IP = '_zerospam_ip';

	/**
	 * Order meta: '1' when the order did not come from the store's checkout page.
	 */
	const META_UNVERIFIED = '_zerospam_unverified';

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'zerospam_settings', array( $this, 'settings' ), 10, 2 );
		add_filter( 'zerospam_failed_types', array( $this, 'failed_types' ), 10, 1 );
		add_action( 'init', array( $this, 'init' ) );

		// The surge alert, sent from cron so a slow mail server never holds up the gateway's webhook.
		add_action( Card_Testing::ALERT_EVENT, array( self::class, 'send_surge_alert' ) );

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
				$updated                                = true;
			}

			// Carry over logging preference to the new checkout logging setting.
			if (
				! empty( $options['log_blocked_woocommerce_registrations'] ) &&
				! isset( $options['log_blocked_woocommerce_checkouts'] )
			) {
				$options['log_blocked_woocommerce_checkouts'] = $options['log_blocked_woocommerce_registrations'];
				$updated                                      = true;
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
	 * Labels of the card-testing checks.
	 *
	 * @param array $types Failed check keys and their labels.
	 */
	public function failed_types( $types ) {
		$types['card_testing']       = __( 'Card testing: repeated failed payments', 'zero-spam' );
		$types['card_testing_surge'] = __( 'Card testing: surge of failed payments', 'zero-spam' );

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
			'title'       => __( 'Protect registrations', 'zero-spam' ),
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

		$message = \ZeroSpam\Core\Utilities::spam_message();

		$settings['woocommerce_registration_spam_message'] = array(
			'title'       => __( 'Registration flagged message', 'zero-spam' ),
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
			'title'       => __( 'Log blocked registrations', 'zero-spam' ),
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
			'title'       => __( 'Protect checkout', 'zero-spam' ),
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
			'title'       => __( 'Checkout flagged message', 'zero-spam' ),
			'desc'        => __( 'The message shown when WooCommerce detects a spam checkout attempt.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'text',
			'field_class' => 'large-text',
			'placeholder' => $checkout_message,
			'value'       => ! empty( $options['woocommerce_checkout_spam_message'] ) ? $options['woocommerce_checkout_spam_message'] : $checkout_message,
			'recommended' => $checkout_message,
		);

		$settings['woocommerce_card_testing'] = array(
			'title'       => __( 'Stop card testing', 'zero-spam' ),
			'desc'        => sprintf(
				/* translators: %s: link to the Enhanced Protection card */
				__( 'Pauses checkout for a visitor whose payments keep failing, the sign of someone testing stolen cards, and refuses orders that did not come from your checkout page while failed payments pile up across the store. Real customers whose card is declined once or twice are not affected. With <a href="%s">Enhanced Protection</a>, addresses the Zero Spam network has already seen testing cards on other stores are stopped before their first attempt.', 'zero-spam' ),
				esc_url( \ZeroSpam\Core\Utilities::admin_link( 'license' ) )
			),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'checkbox',
			'options'     => array(
				'enabled' => false,
			),
			'value'       => is_array( $options ) && array_key_exists( 'woocommerce_card_testing', $options ) ? $options['woocommerce_card_testing'] : 'enabled',
			'default'     => 'enabled',
			'recommended' => 'enabled',
		);

		$settings['woocommerce_card_testing_failures'] = array(
			'title'       => __( 'Failed payments before checkout is paused', 'zero-spam' ),
			'desc'        => __( 'How many failed payments from one IP address or one billing email, within an hour, pause checkout for that visitor. Recommended: 3.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'number',
			'field_class' => 'small-text',
			'min'         => 2,
			'max'         => 20,
			'suffix'      => __( 'failed payments', 'zero-spam' ),
			'value'       => ! empty( $options['woocommerce_card_testing_failures'] ) ? (int) $options['woocommerce_card_testing_failures'] : Card_Testing::DEFAULT_FAILURES,
			'default'     => Card_Testing::DEFAULT_FAILURES,
			'recommended' => Card_Testing::DEFAULT_FAILURES,
		);

		$settings['woocommerce_card_testing_minutes'] = array(
			'title'       => __( 'Pause checkout for', 'zero-spam' ),
			'desc'        => __( 'How long a paused visitor cannot check out. The rest of the site stays open to them. Recommended: 60 minutes.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'number',
			'field_class' => 'small-text',
			'min'         => 5,
			'max'         => 1440,
			'suffix'      => __( 'minutes', 'zero-spam' ),
			'value'       => ! empty( $options['woocommerce_card_testing_minutes'] ) ? (int) $options['woocommerce_card_testing_minutes'] : Card_Testing::DEFAULT_MINUTES,
			'default'     => Card_Testing::DEFAULT_MINUTES,
			'recommended' => Card_Testing::DEFAULT_MINUTES,
		);

		$settings['woocommerce_card_testing_surge'] = array(
			'title'       => __( 'Failed payments that start a surge', 'zero-spam' ),
			'desc'        => __( 'How many failed payments across the whole store, within 10 minutes, count as an attack from many addresses at once. For the next 30 minutes, orders that did not come from your checkout page are refused, and you get an email. Recommended: 10.', 'zero-spam' ),
			'section'     => 'woocommerce',
			'module'      => 'woocommerce',
			'type'        => 'number',
			'field_class' => 'small-text',
			'min'         => 3,
			'max'         => 500,
			'suffix'      => __( 'in 10 minutes', 'zero-spam' ),
			'value'       => ! empty( $options['woocommerce_card_testing_surge'] ) ? (int) $options['woocommerce_card_testing_surge'] : Card_Testing::DEFAULT_SURGE,
			'default'     => Card_Testing::DEFAULT_SURGE,
			'recommended' => Card_Testing::DEFAULT_SURGE,
		);

		$settings['log_blocked_woocommerce_checkouts'] = array(
			'title'       => __( 'Log blocked checkouts', 'zero-spam' ),
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

		$protect_checkout = 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'verify_woocommerce_checkout' );

		if ( ! $protect_checkout ) {
			return;
		}

		// The honeypot field and David Walsh script are for guests (the David Walsh check only runs for them).
		if ( $should_process ) {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'add_scripts' ) );
			add_action( 'woocommerce_after_order_notes', array( $this, 'add_honeypot_field' ), 10 );
		}

		// Every checkout is checked, also a logged-in customer's: card testers register accounts first.
		add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) );
		add_action( 'woocommerce_before_pay_action', array( $this, 'process_pay_for_order' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'remember_store_api_checkout' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_store_api_checkout' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'note_store_api_request' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( Checkout_Token::class, 'enqueue' ) );

		// Who placed a classic order, as Zero Spam sees it (WooCommerce's own IP trusts any forwarding header).
		add_action( 'woocommerce_checkout_create_order', array( $this, 'remember_classic_checkout' ) );

		// Counted whoever's request marks the payment failed (often the gateway's webhook).
		add_action( 'woocommerce_order_status_failed', array( $this, 'payment_failed' ), 10, 2 );

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			Checkout_Token::register_schema();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( Checkout_Token::class, 'register_schema' ) );
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
	 * The Store API checkout request being answered (see note_store_api_request()).
	 *
	 * @var \WP_REST_Request|null
	 */
	private ?\WP_REST_Request $store_request = null;

	/**
	 * Checks a classic checkout (on `woocommerce_checkout_process`).
	 *
	 * A post without the honeypot field did not come from the checkout page
	 * (bots posting to `?wc-ajax=checkout`, express payment buttons); a
	 * logged-in customer's checkout never carries the field.
	 */
	public function process_checkout() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the checkout nonce before this action.
		$posted = \ZeroSpam\Core\Utilities::sanitize_array( wp_unslash( $_POST ) );
		$posted = is_array( $posted ) ? $posted : array();
		$order  = Checkout_Guard::posted_fields( $posted );

		$verified = is_user_logged_in() || array_key_exists( \ZeroSpam\Core\Utilities::get_honeypot(), $posted );

		// A retry of an order that already failed (WooCommerce reuses it) is one more card tried.
		if ( function_exists( 'WC' ) && function_exists( 'wc_get_order' ) && WC()->session ) {
			$this->count_retry( wc_get_order( (int) WC()->session->get( 'order_awaiting_payment' ) ), $verified );
		}

		$this->refuse_classic( Checkout_Guard::check( $order, Checkout_Guard::CLASSIC, $verified, $posted ), $order );
	}

	/**
	 * Checks a payment on the classic pay-for-order page (on `woocommerce_before_pay_action`).
	 *
	 * Card testers retry a failed order there, away from the checkout. A
	 * notice of type error makes WooCommerce skip the payment.
	 *
	 * @param object $order WC_Order.
	 */
	public function process_pay_for_order( $order ) {
		$this->count_retry( $order, true );

		$fields = Checkout_Guard::order_fields( $order );

		$this->refuse_classic( Checkout_Guard::check( $fields, Checkout_Guard::CLASSIC, true ), $fields );
	}

	/**
	 * Refuses a classic checkout or payment, if there are reasons.
	 *
	 * @param string[] $reasons Failed check keys.
	 * @param array    $fields  Order fields.
	 */
	private function refuse_classic( array $reasons, array $fields ): void {
		if ( ! $reasons ) {
			return;
		}

		Checkout_Guard::record( $reasons, $fields, Checkout_Guard::CLASSIC );
		wc_add_notice( \ZeroSpam\Core\Utilities::detection_message( 'woocommerce_checkout_spam_message' ), 'error' );
	}

	/**
	 * Keeps the Store API checkout request (on `rest_request_before_callbacks`), so
	 * process_store_api_checkout() can read its token: WooCommerce passes that
	 * action the order alone.
	 *
	 * @param mixed            $response The response so far (unchanged).
	 * @param mixed            $handler  The route handler.
	 * @param \WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public function note_store_api_request( $response, $handler, $request ) {
		unset( $handler );

		if ( $request instanceof \WP_REST_Request && 'POST' === $request->get_method() && preg_match( '#^/wc/store(?:/v\d+)?/checkout(?:/\d+)?/?$#', $request->get_route() ) ) {
			$this->store_request = $request;
		}

		return $response;
	}

	/**
	 * Notes on a Store API order who placed it and whether it came from the
	 * checkout page, before WooCommerce saves it (on
	 * `woocommerce_store_api_checkout_update_order_from_request`, placing orders only).
	 *
	 * @param object $order   WC_Order.
	 * @param mixed  $request WP_REST_Request.
	 */
	public function remember_store_api_checkout( $order, $request ) {
		if ( ! $request instanceof \WP_REST_Request || 'POST' !== $request->get_method() ) {
			return;
		}

		$this->remember_checkout( $order, Checkout_Token::is_valid( Checkout_Token::from_request( $request ) ) );
	}

	/**
	 * Checks a Checkout block / Store API order before payment (on
	 * `woocommerce_store_api_checkout_order_processed`), which both the
	 * checkout and the pay-for-order routes fire.
	 *
	 * @param object $order WC_Order.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When the order is refused.
	 */
	public function process_store_api_checkout( $order ) {
		$verified = $this->store_request instanceof \WP_REST_Request && Checkout_Token::is_valid( Checkout_Token::from_request( $this->store_request ) );

		$this->count_retry( $order, $verified );

		$fields  = Checkout_Guard::order_fields( $order );
		$reasons = Checkout_Guard::check( $fields, Checkout_Guard::STORE_API, $verified );

		if ( ! $reasons ) {
			return;
		}

		Checkout_Guard::record( $reasons, $fields, Checkout_Guard::STORE_API );

		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
			'zerospam_checkout_refused',
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain text: the Store API answers it as JSON, and the Checkout block shows it as text.
			wp_strip_all_tags( \ZeroSpam\Core\Utilities::detection_message( 'woocommerce_checkout_spam_message' ) ),
			403
		);
	}

	/**
	 * Notes on a classic order who placed it (on `woocommerce_checkout_create_order`).
	 *
	 * @param object $order WC_Order.
	 */
	public function remember_classic_checkout( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only reads whether the honeypot field was posted; WooCommerce checked the nonce.
		$this->remember_checkout( $order, is_user_logged_in() || isset( $_POST[ \ZeroSpam\Core\Utilities::get_honeypot() ] ) );
	}

	/**
	 * Notes on the order who placed it and whether it came from the checkout page.
	 *
	 * @param object $order    WC_Order.
	 * @param bool   $verified Whether it came from the checkout page.
	 */
	public function remember_checkout( $order, $verified = true ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( self::META_IP, (string) \ZeroSpam\Core\User::get_ip() );
		$order->update_meta_data( self::META_UNVERIFIED, $verified ? '' : '1' );
	}

	/**
	 * Counts a payment attempt on an order that already failed as one more
	 * failure: a retry with another card. WooCommerce reuses a failed order
	 * for the next attempt, and a gateway that fails it again changes no
	 * status, so woocommerce_order_status_failed never fires for it.
	 *
	 * @param mixed $order    WC_Order or false.
	 * @param bool  $verified Whether the attempt came from the checkout page.
	 */
	private function count_retry( $order, bool $verified ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'has_status' ) || ! $order->has_status( 'failed' ) ) {
			return;
		}

		if ( ! Card_Testing::enabled() || Checkout_Guard::is_trusted( (string) \ZeroSpam\Core\User::get_ip() ) ) {
			return;
		}

		$this->record( (string) \ZeroSpam\Core\User::get_ip(), $order, ! $verified );
	}

	/**
	 * Counts a failed payment (on `woocommerce_order_status_failed`).
	 *
	 * Only orders placed through a checkout count, never one shop staff mark
	 * failed. Visitors on the IP Allowlist or the no-lockout list never count.
	 * The visitor's IP is the one Zero Spam recorded at checkout; an order
	 * without it counts on its account and email only.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order    WC_Order.
	 */
	public function payment_failed( $order_id, $order = null ) {
		if ( ! Card_Testing::enabled() ) {
			return;
		}

		if ( ! is_object( $order ) && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		// Shop staff changing the status (the admin, REST, the mobile app). Gateway webhooks run without a user.
		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce's shop management capability.
		if ( ( is_admin() && ! wp_doing_ajax() ) || current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! in_array( (string) $order->get_created_via(), array( 'checkout', 'store-api' ), true ) ) {
			return;
		}

		$ip = (string) $order->get_meta( self::META_IP );

		if ( '' !== $ip && ( \ZeroSpam\Core\Utilities::is_whitelisted( $ip ) || \ZeroSpam\Core\Admin_Safe_IPs::contains( $ip ) ) ) {
			return;
		}

		$this->record( $ip, $order, '1' === (string) $order->get_meta( self::META_UNVERIFIED ) );
	}

	/**
	 * Records one failure for an order's visitor.
	 *
	 * @param string $ip         The visitor's IP address, or ''.
	 * @param object $order      WC_Order.
	 * @param bool   $unverified Whether the attempt did not come from the checkout page.
	 */
	private function record( string $ip, $order, bool $unverified ): void {
		$email = method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '';
		$user  = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;

		Card_Testing::record_failure( $ip, $email, $user, $unverified, ! self::email_has_paid( $email ) );
	}

	/**
	 * Whether an email already paid an order on the store (then it never counts,
	 * so no one can pause a real customer by typing their email).
	 *
	 * @param string $email Billing email.
	 */
	private static function email_has_paid( string $email ): bool {
		if ( '' === $email || ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		$paid = wc_get_orders(
			array(
				'billing_email' => $email,
				'status'        => array( 'wc-processing', 'wc-completed' ),
				'limit'         => 1,
				'return'        => 'ids',
			)
		);

		return ! empty( $paid );
	}

	/**
	 * Emails the site's administrator that a surge started (on the cron event Card_Testing::ALERT_EVENT).
	 */
	public static function send_surge_alert(): void {
		$until = Card_Testing::surge_until();

		if ( ! $until ) {
			return;
		}

		$sent = \ZeroSpam\Includes\Email::send(
			(string) get_option( 'admin_email' ),
			__( 'Zero Spam: card testing on your store', 'zero-spam' ),
			array(
				'heading' => __( 'Card testing on your store', 'zero-spam' ),
				'blocks'  => array(
					array(
						'type' => 'text',
						/* translators: %1$s: number of failed payments */
						'text' => __( '%1$s payments failed on your store in the last 10 minutes, from different visitors. That is how card testing with stolen cards looks.', 'zero-spam' ),
						'args' => array( number_format_i18n( Card_Testing::surge_threshold() ) ),
					),
					array(
						'type' => 'text',
						/* translators: %1$s: time the surge ends */
						'text' => __( 'Until %1$s, Zero Spam refuses orders that did not come from your checkout page. Customers on your checkout page can still pay. Visitors whose payments keep failing stay paused as usual.', 'zero-spam' ),
						'args' => array( wp_date( (string) get_option( 'time_format' ), $until ) ),
					),
					array(
						'type'  => 'button',
						'label' => __( 'See what was stopped', 'zero-spam' ),
						'url'   => \ZeroSpam\Core\Utilities::admin_link( 'activity' ),
					),
				),
			)
		);

		if ( ! $sent ) {
			\ZeroSpam\Core\Utilities::log( 'Card testing: the surge alert email could not be sent.' );
		}
	}
}
