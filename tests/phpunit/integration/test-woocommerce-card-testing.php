<?php
/**
 * Tests the WooCommerce card-testing defence.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use ZeroSpam\Modules\WooCommerce\Card_Testing;
use ZeroSpam\Modules\WooCommerce\Checkout_Guard;
use ZeroSpam\Modules\WooCommerce\Checkout_Token;
use ZeroSpam\Modules\WooCommerce\WooCommerce;
use ZeroSpam\Tests\FakeOrder;
use ZeroSpam\Tests\TestCase;

require_once dirname( __DIR__ ) . '/includes/stubs/woocommerce.php';
require_once dirname( __DIR__ ) . '/includes/stubs/woocommerce-functions.php';

/**
 * Failed payments pause a visitor's checkout, a surge refuses orders that did
 * not come from the checkout page, and both checkouts (classic and Store API)
 * run the same checks and log no card data.
 *
 * @covers \ZeroSpam\Modules\WooCommerce\Card_Testing
 * @covers \ZeroSpam\Modules\WooCommerce\Checkout_Guard
 * @covers \ZeroSpam\Modules\WooCommerce\Checkout_Token
 * @covers \ZeroSpam\Modules\WooCommerce\WooCommerce
 */
class Test_WooCommerce_Card_Testing extends TestCase {

	/**
	 * A TEST-NET visitor address.
	 */
	private const IP = '203.0.113.50';

	/**
	 * The module.
	 *
	 * @var WooCommerce
	 */
	private WooCommerce $module;

	/**
	 * Mail sent through wp_mail().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $mail = array();

	/**
	 * The module with checkout protection on, a visitor, a fixed clock.
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		$this->module = new WooCommerce();
		$this->set_setting( 'verify_woocommerce_checkout', 'enabled' );
		$this->set_setting( 'log_blocked_woocommerce_checkouts', 'enabled' );
		$this->set_request( '/wp-json/wc/store/v1/checkout', self::IP );

		Card_Testing::$now = 1_800_000_000;
		Card_Testing::clear();

		$GLOBALS['zerospam_test_wc_notices'] = array();

		$this->mail = array();
		add_filter(
			'pre_wp_mail',
			function ( $result, array $atts ) {
				$this->mail[] = $atts;
				return true;
			},
			10,
			2
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Test set-up in the plugin's own table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'wpzerospam_log' ) );
	}

	/**
	 * Real time again.
	 */
	public function tear_down(): void {
		Card_Testing::$now = null;
		unset( $GLOBALS['zerospam_test_wc_notices'] );

		parent::tear_down();
	}

	/**
	 * A Store API request.
	 *
	 * @param string      $method HTTP method.
	 * @param string|null $token  Checkout token, or none.
	 */
	private function store_request( string $method = 'POST', ?string $token = null ): WP_REST_Request {
		$request = new WP_REST_Request( $method, '/wc/store/v1/checkout' );

		if ( null !== $token ) {
			$request->set_param( 'extensions', array( 'zero-spam' => array( 'token' => $token ) ) );
		}

		return $request;
	}

	/**
	 * Fails a payment of an order, as a gateway would.
	 *
	 * @param FakeOrder $order Order.
	 */
	private function fail_payment( FakeOrder $order ): void {
		// The address the checkout recorded (remember_checkout()) when the order was placed.
		if ( '' === $order->get_meta( WooCommerce::META_IP ) ) {
			$order->update_meta_data( WooCommerce::META_IP, (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		}

		$this->module->payment_failed( 1, $order );
	}

	/**
	 * Places a Store API order and returns the refusal, or null.
	 *
	 * @param FakeOrder   $order Order.
	 * @param string|null $token Checkout token.
	 */
	private function place( FakeOrder $order, ?string $token = null ): ?RouteException {
		$request = $this->store_request( 'POST', $token );
		$request->set_route( '/wc/store/v1/checkout' );

		$this->module->note_store_api_request( null, array(), $request );
		$this->module->remember_store_api_checkout( $order, $request );

		try {
			$this->module->process_store_api_checkout( $order );
		} catch ( RouteException $refused ) {
			return $refused;
		}

		return null;
	}

	/**
	 * The log's submission data, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function logged(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the plugin's own table.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT submission_data FROM %i ORDER BY log_id DESC', $wpdb->prefix . 'wpzerospam_log' ) );

		return array_map( static fn( $row ) => (array) json_decode( (string) $row, true ), $rows );
	}

	/**
	 * The switch is on unless turned off, and only with checkout protection.
	 */
	public function test_on_by_default_with_checkout_protection(): void {
		$this->assertTrue( Card_Testing::enabled() );

		$this->set_setting( 'woocommerce_card_testing', 'disabled' );
		$this->assertFalse( Card_Testing::enabled() );

		$this->set_setting( 'woocommerce_card_testing', false );
		$this->assertFalse( Card_Testing::enabled(), 'An unticked box stored as false is off.' );

		$this->set_setting( 'woocommerce_card_testing', 'enabled' );
		$this->set_setting( 'verify_woocommerce_checkout', false );
		$this->assertFalse( Card_Testing::enabled() );
	}

	/**
	 * Three failed payments in an hour pause that visitor; the fourth order is refused.
	 */
	public function test_three_failures_pause_the_visitor(): void {
		$order = new FakeOrder();

		$this->fail_payment( $order );
		$this->fail_payment( $order );
		$this->assertNull( $this->place( $order, Checkout_Token::current() ) );

		$this->fail_payment( $order );
		$refused = $this->place( $order, Checkout_Token::current() );

		$this->assertInstanceOf( RouteException::class, $refused );
		$this->assertSame( 'zerospam_checkout_refused', $refused->error_code );
		$this->assertSame( 403, $refused->http_status_code );
		$this->assertSame( 'card_testing', $this->logged()[0]['failed'] );
	}

	/**
	 * The same billing email from another address is paused too; another visitor is not.
	 */
	public function test_email_pause_only_refuses_unverified_orders(): void {
		$order = new FakeOrder();

		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( $order );
		}

		// The same email from another address: refused without a token, served from the checkout page.
		$this->set_request( '/wp-json/wc/store/v1/checkout', '198.51.100.20' );
		$this->assertInstanceOf( RouteException::class, $this->place( new FakeOrder() ) );
		$this->assertNull( $this->place( new FakeOrder(), Checkout_Token::current() ) );
		$this->assertNull( $this->place( new FakeOrder( array( 'billing_email' => 'someone@example.com' ) ) ) );
	}

	/**
	 * The paused address is refused whatever email it uses, also from the checkout page.
	 */
	public function test_ip_pause_refuses_every_order_from_that_address(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( new FakeOrder( array( 'billing_email' => "card{$i}@example.com" ) ) );
		}

		$this->assertInstanceOf( RouteException::class, $this->place( new FakeOrder( array( 'billing_email' => 'new@example.com' ) ), Checkout_Token::current() ) );
	}

	/**
	 * A logged-in customer is checked too, and paused by account.
	 */
	public function test_logged_in_customers_are_checked(): void {
		$customer = $this->as_role( 'subscriber' );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->set_request( '/', '198.51.100.' . ( 30 + $i ) );
			$this->fail_payment(
				new FakeOrder(
					array(
						'billing_email' => "acct{$i}@example.com",
						'customer_id'   => $customer,
					)
				)
			);
		}

		$this->set_request( '/wp-json/wc/store/v1/checkout', '198.51.100.40' );
		$this->assertInstanceOf(
			RouteException::class,
			$this->place(
				new FakeOrder(
					array(
						'billing_email' => 'acct9@example.com',
						'customer_id'   => $customer,
					)
				),
				Checkout_Token::current()
			)
		);
		$this->assertSame( 'card_testing', $this->logged()[0]['failed'] );
	}

	/**
	 * Shop staff and IP Allowlist addresses are never refused.
	 */
	public function test_staff_are_never_refused(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( new FakeOrder() );
		}

		// WooCommerce gives shop managers and administrators this capability.
		$staff = $this->as_role( 'subscriber' );
		add_filter(
			'user_has_cap',
			static function ( array $caps, array $requested, array $args ) use ( $staff ): array {
				if ( (int) ( $args[1] ?? 0 ) === $staff ) {
					$caps['manage_woocommerce'] = true;
				}
				return $caps;
			},
			10,
			3
		);
		$this->assertNull( $this->place( new FakeOrder() ) );
	}

	/**
	 * Private, reserved and shared addresses never count: one proxy in front of the
	 * site must not pause every guest.
	 */
	public function test_shared_and_private_addresses_do_not_count(): void {
		foreach ( array( '10.0.0.5', '172.18.0.1' ) as $private ) {
			$this->set_request( '/', $private );
			for ( $i = 0; $i < 3; $i++ ) {
				$this->fail_payment( new FakeOrder( array( 'billing_email' => "p{$i}{$private}@example.com" ) ) );
			}
			$this->assertFalse( Card_Testing::is_paused( $private, '' ) );
		}

		set_transient(
			\ZeroSpam\Core\Admin\Shared_IP_Warning::TRANSIENT,
			array(
				'ip'     => self::IP,
				'reason' => 'shared_ip',
			),
			HOUR_IN_SECONDS
		);
		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( new FakeOrder( array( 'billing_email' => "s{$i}@example.com" ) ) );
		}
		$this->assertFalse( Card_Testing::is_paused( self::IP, '' ) );
	}

	/**
	 * An email that already paid an order is never counted.
	 */
	public function test_paid_email_never_counts(): void {
		add_filter( 'zerospam_test_paid_emails', fn() => array( 'loyal@example.com' ) );

		$order = new FakeOrder( array( 'billing_email' => 'loyal@example.com' ) );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->set_request( '/', '198.51.100.' . ( 50 + $i ) );
			$this->fail_payment( $order );
			$order->update_meta_data( WooCommerce::META_IP, '' );
		}

		$this->assertFalse( Card_Testing::is_paused( '', 'loyal@example.com' ) );
	}

	/**
	 * A retry of an order that already failed counts as one more failure.
	 */
	public function test_retries_of_a_failed_order_count(): void {
		$order = new FakeOrder( array( 'status' => 'failed' ) );
		$this->fail_payment( $order );

		$this->place( $order, Checkout_Token::current() );
		$this->assertFalse( Card_Testing::is_paused( self::IP, '' ) );

		$refused = $this->place( $order, Checkout_Token::current() );
		$this->assertInstanceOf( RouteException::class, $refused );
	}

	/**
	 * The pay-for-order page is checked: a paused visitor's payment gets an error notice.
	 */
	public function test_pay_for_order_page_is_checked(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( new FakeOrder( array( 'billing_email' => "pay{$i}@example.com" ) ) );
		}

		$this->module->process_pay_for_order( new FakeOrder( array( 'status' => 'pending' ) ) );

		$this->assertSame( 'error', $GLOBALS['zerospam_test_wc_notices'][0][0] ?? '' );
	}

	/**
	 * An order without the address Zero Spam recorded counts on its account and
	 * email only, never on WooCommerce's own (spoofable) customer IP.
	 */
	public function test_no_fallback_to_woocommerce_ip(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->module->payment_failed(
				1,
				new FakeOrder(
					array(
						'billing_email' => "x{$i}@example.com",
						'customer_ip'   => '198.51.100.77',
					)
				)
			);
		}

		$this->assertFalse( Card_Testing::is_paused( '198.51.100.77', '' ) );
	}

	/**
	 * Clearing ends every pause.
	 */
	public function test_clear_ends_pauses(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( new FakeOrder() );
		}
		$this->assertTrue( Card_Testing::is_paused( self::IP, '' ) );

		Card_Testing::clear();
		$this->assertFalse( Card_Testing::is_paused( self::IP, '' ) );
	}

	/**
	 * Failures older than an hour no longer count, and a pause ends by itself.
	 */
	public function test_window_and_pause_expire(): void {
		$order = new FakeOrder();

		$this->fail_payment( $order );
		$this->fail_payment( $order );
		Card_Testing::$now += Card_Testing::WINDOW + 1;
		$this->fail_payment( $order );
		$this->assertFalse( Card_Testing::is_paused( self::IP, '' ) );

		$this->fail_payment( $order );
		$this->fail_payment( $order );
		$this->assertTrue( Card_Testing::is_paused( self::IP, '' ) );

		Card_Testing::$now += Card_Testing::DEFAULT_MINUTES * 60 + 1;
		$this->assertFalse( Card_Testing::is_paused( self::IP, '' ) );
	}

	/**
	 * Unverified orders (no checkout token) are paused one failure sooner, never below two.
	 */
	public function test_unverified_attempts_count_sooner(): void {
		$order = new FakeOrder();
		$order->update_meta_data( WooCommerce::META_UNVERIFIED, '1' );

		$this->fail_payment( $order );
		$this->assertFalse( Card_Testing::is_paused( self::IP, '' ) );
		$this->fail_payment( $order );
		$this->assertTrue( Card_Testing::is_paused( self::IP, '' ) );
	}

	/**
	 * Ten failures across the store in 10 minutes start a surge: unverified orders are
	 * refused for 30 minutes and the administrator gets one email; orders from the
	 * checkout page still go through.
	 */
	public function test_surge_refuses_unverified_orders(): void {
		for ( $i = 1; $i <= Card_Testing::DEFAULT_SURGE; $i++ ) {
			$this->set_request( '/', '198.51.100.' . $i );
			$this->fail_payment( new FakeOrder( array( 'billing_email' => "card{$i}@example.com" ) ) );
		}

		$this->assertTrue( Card_Testing::surge_active() );
		$this->assertNotFalse( wp_next_scheduled( Card_Testing::ALERT_EVENT ) );

		do_action( Card_Testing::ALERT_EVENT );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( get_option( 'admin_email' ), $this->mail[0]['to'] );

		$this->set_request( '/wp-json/wc/store/v1/checkout', self::IP );
		$buyer = new FakeOrder( array( 'billing_email' => 'buyer@example.com' ) );

		$this->assertNull( $this->place( $buyer, Checkout_Token::current() ) );
		$this->assertInstanceOf( RouteException::class, $this->place( $buyer ) );
		$this->assertSame( 'card_testing_surge', $this->logged()[0]['failed'] );

		Card_Testing::$now += Card_Testing::SURGE_LENGTH + 1;
		$this->assertNull( $this->place( $buyer ) );
	}

	/**
	 * Without a surge, an order without a token goes through (express payment buttons).
	 */
	public function test_missing_token_alone_does_not_refuse(): void {
		$order = new FakeOrder();

		$this->assertNull( $this->place( $order ) );
		$this->assertSame( '1', $order->get_meta( WooCommerce::META_UNVERIFIED ) );
		$this->assertSame( self::IP, $order->get_meta( WooCommerce::META_IP ) );
	}

	/**
	 * The token is valid for three hours and refuses anything else.
	 */
	public function test_token_lifetime(): void {
		$token = Checkout_Token::current();

		$this->assertTrue( Checkout_Token::is_valid( $token ) );
		Card_Testing::$now += 2 * 3600;
		$this->assertTrue( Checkout_Token::is_valid( $token ) );
		Card_Testing::$now += 3600;
		$this->assertFalse( Checkout_Token::is_valid( $token ) );
		$this->assertFalse( Checkout_Token::is_valid( 'forged' ) );
		$this->assertFalse( Checkout_Token::is_valid( array( $token ) ) );
	}

	/**
	 * Field updates the Checkout block saves while the visitor types (PUT) are never checked.
	 */
	public function test_field_updates_are_not_checked(): void {
		$order = new FakeOrder();
		for ( $i = 0; $i < 3; $i++ ) {
			$this->fail_payment( $order );
		}

		$fresh = new FakeOrder();
		$this->module->remember_store_api_checkout( $fresh, $this->store_request( 'PUT' ) );
		$this->assertSame( '', $fresh->get_meta( WooCommerce::META_IP ) );
		$this->assertSame( array(), $this->logged() );
	}

	/**
	 * A blocked IP address is refused at the Store API checkout, which page-view checks never reach.
	 */
	public function test_blocked_ip_is_refused_at_store_api(): void {
		// Access::init() adds its block check on a visitor's request (none while the suite loads).
		add_filter( 'zerospam_access_checks', array( new \ZeroSpam\Core\Access(), 'check_blocked' ), 0, 3 );
		$this->block( self::IP );

		$refused = $this->place( new FakeOrder(), Checkout_Token::current() );

		$this->assertInstanceOf( RouteException::class, $refused );
		$this->assertContains( $this->logged()[0]['failed'], array( 'blocked', 'blocked_ip' ) );
	}

	/**
	 * Failures an administrator sets, orders not placed through a checkout, and
	 * customers on the IP Allowlist never count.
	 */
	public function test_what_never_counts(): void {
		$this->fail_payment( new FakeOrder( array( 'created_via' => 'admin' ) ) );

		set_current_screen( 'edit-shop_order' );
		$this->fail_payment( new FakeOrder() );
		set_current_screen( 'front' );

		$this->set_setting( 'ip_whitelist', self::IP );
		$allowed = new FakeOrder();
		$allowed->update_meta_data( WooCommerce::META_IP, self::IP );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->fail_payment( $allowed );
		}

		$this->assertFalse( Card_Testing::is_paused( self::IP, 'tester@example.com' ) );
	}

	/**
	 * The stored counters hold keyed hashes, never an address or an email.
	 */
	public function test_state_holds_no_address_or_email(): void {
		$this->fail_payment( new FakeOrder() );

		$stored = (string) wp_json_encode( get_option( Card_Testing::OPTION ) );

		$this->assertStringNotContainsString( self::IP, $stored );
		$this->assertStringNotContainsString( 'tester@example.com', $stored );
		$this->assertFalse( wp_load_alloptions()[ Card_Testing::OPTION ] ?? false, 'The option is not autoloaded.' );
	}

	/**
	 * A refused classic checkout logs the order fields only, never posted card fields.
	 */
	public function test_classic_checkout_logs_no_card_data(): void {
		$honeypot = \ZeroSpam\Core\Utilities::get_honeypot();
		$_POST    = array(
			'billing_email'                        => 'tester@example.com',
			'billing_first_name'                   => 'Card',
			'payment_method'                       => 'authorize_net_cim_credit_card',
			'wc-authorize-net-cim-credit-card-account-number' => '4111111111111111',
			'wc-authorize-net-cim-credit-card-csc' => '123',
			$honeypot                              => 'bot',
		);

		$this->module->process_checkout();

		$this->assertSame( 'error', $GLOBALS['zerospam_test_wc_notices'][0][0] );

		$logged = $this->logged();
		$this->assertSame( 'honeypot', $logged[0]['failed'] );
		$this->assertStringNotContainsString( '4111111111111111', (string) wp_json_encode( $logged ) );
		$this->assertStringNotContainsString( 'csc', (string) wp_json_encode( $logged ) );
		$this->assertSame( 'classic', $logged[0]['data']['checkout'] );

		$_POST = array();
	}

	/**
	 * A clean checkout passes both paths.
	 */
	public function test_clean_checkouts_pass(): void {
		$this->assertSame( array(), Checkout_Guard::check( Checkout_Guard::order_fields( new FakeOrder() ), Checkout_Guard::STORE_API, true ) );

		$_POST = array( 'billing_email' => 'tester@example.com' );
		$this->module->process_checkout();
		$this->assertSame( array(), $GLOBALS['zerospam_test_wc_notices'] );
		$_POST = array();
	}
}
