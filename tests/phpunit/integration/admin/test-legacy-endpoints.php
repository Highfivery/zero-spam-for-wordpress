<?php
/**
 * Tests for the deprecated form handlers of the 5.x admin screens.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Includes\Legacy_Endpoints;
use ZeroSpam\Tests\RedirectException;
use ZeroSpam\Tests\TestCase;

/**
 * The 5.x form handlers stay one release (build plan C10): each checks the
 * capability and nonce the 5.x handler checked, raises a deprecation notice
 * and runs the REST route that replaced it, then redirects to the 6.0 page.
 *
 * @covers \ZeroSpam\Includes\Legacy_Endpoints
 */
class Test_Legacy_Endpoints extends TestCase {

	/**
	 * An administrator in the admin.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_screen();
		$this->as_role( 'administrator' );
	}

	/**
	 * Forgets the simulated request.
	 */
	public function tear_down(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Runs a handler with a request and returns how it ended.
	 *
	 * @param string               $method Handler method.
	 * @param array<string, mixed> $post   POST fields.
	 * @param array<string, mixed> $get    Query arguments.
	 * @return Throwable|null RedirectException, WPDieException or null.
	 */
	private function run_handler( string $method, array $post = array(), array $get = array() ): ?Throwable {
		$_POST    = $post;
		$_GET     = $get;
		$_REQUEST = array_merge( $get, $post );

		add_filter(
			'wp_redirect',
			static function ( $location, $status ) {
				throw new RedirectException( (string) $location, (int) $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught below, never rendered.
			},
			1,
			2
		);

		try {
			( new Legacy_Endpoints() )->{$method}();
		} catch ( RedirectException | WPDieException $e ) {
			return $e;
		} finally {
			remove_all_filters( 'wp_redirect' );
		}

		return null;
	}

	/**
	 * The handlers are registered.
	 */
	public function test_handlers_are_registered(): void {
		$endpoints = new Legacy_Endpoints();

		$this->assertNotFalse( has_action( 'admin_action_add_blocked_ip', array( $endpoints, 'add_blocked_ip' ) ) );
		$this->assertNotFalse( has_action( 'admin_action_import_settings', array( $endpoints, 'import_settings' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_zerospam_refresh_htaccess', array( $endpoints, 'refresh_htaccess' ) ) );
		$this->assertNotFalse( has_action( 'admin_post_zerospam_remove_admin_safe_ip', array( $endpoints, 'remove_admin_safe_ip' ) ) );
		$this->assertFalse( has_action( 'wp_ajax_zerospam_dismiss_promo_notice' ), 'The promo was removed with its AJAX actions.' );
		$this->assertFalse( has_action( 'wp_ajax_zerospam_track_promo_click' ) );
	}

	/**
	 * The 5.x block form blocks through rules/blocks (which refuses loopback) and goes to Rules.
	 */
	public function test_add_blocked_ip(): void {
		$this->setExpectedDeprecated( 'admin_action_add_blocked_ip' );

		$end = $this->run_handler(
			'add_blocked_ip',
			array(
				'zerospam'       => wp_create_nonce( 'zerospam' ),
				'blocked_ip'     => '198.51.100.40',
				'blocked_type'   => 'permanent',
				'blocked_reason' => 'From the old form',
			)
		);

		$this->assertInstanceOf( RedirectException::class, $end );
		$this->assertSame( admin_url( 'admin.php?page=zero-spam-rules&tab=ips' ), $end->location );
		$this->assertSame( 1, $this->count_blocks( '198.51.100.40' ) );

		$this->run_handler(
			'add_blocked_ip',
			array(
				'zerospam'     => wp_create_nonce( 'zerospam' ),
				'blocked_ip'   => '127.0.0.1',
				'blocked_type' => 'permanent',
			)
		);
		$this->assertSame( 0, $this->count_blocks( '127.0.0.1' ), 'The route\'s checks apply.' );
	}

	/**
	 * A form with an expired nonce blocks nothing and goes back to the blocks list, as 5.x did.
	 */
	public function test_add_blocked_ip_with_a_bad_nonce(): void {
		$end = $this->run_handler(
			'add_blocked_ip',
			array(
				'zerospam'     => 'expired',
				'blocked_ip'   => '198.51.100.41',
				'blocked_type' => 'permanent',
			)
		);

		$this->assertInstanceOf( RedirectException::class, $end );
		$this->assertSame( admin_url( 'admin.php?page=zero-spam-rules&tab=ips' ), $end->location );
		$this->assertSame( 0, $this->count_blocks( '198.51.100.41' ) );
	}

	/**
	 * A location goes to the Locations tab.
	 */
	public function test_add_blocked_location(): void {
		$this->setExpectedDeprecated( 'admin_action_add_blocked_ip' );

		$end = $this->run_handler(
			'add_blocked_ip',
			array(
				'zerospam'     => wp_create_nonce( 'zerospam' ),
				'key_type'     => 'country_code',
				'blocked_key'  => 'FR',
				'blocked_type' => 'permanent',
			)
		);

		$this->assertSame( admin_url( 'admin.php?page=zero-spam-rules&tab=locations' ), $end->location );
	}

	/**
	 * Without the nonce nothing is blocked; without the capability the request is refused.
	 */
	public function test_add_blocked_ip_needs_nonce_and_capability(): void {
		$end = $this->run_handler(
			'add_blocked_ip',
			array(
				'zerospam'     => 'bad',
				'blocked_ip'   => '198.51.100.41',
				'blocked_type' => 'permanent',
			)
		);
		$this->assertInstanceOf( RedirectException::class, $end );
		$this->assertSame( 0, $this->count_blocks( '198.51.100.41' ) );

		$this->as_role( 'editor' );
		$end = $this->run_handler(
			'add_blocked_ip',
			array(
				'zerospam'     => wp_create_nonce( 'zerospam' ),
				'blocked_ip'   => '198.51.100.41',
				'blocked_type' => 'permanent',
			)
		);
		$this->assertInstanceOf( WPDieException::class, $end );
		$this->assertSame( 0, $this->count_blocks( '198.51.100.41' ) );
	}

	/**
	 * The 5.x import form imports through settings/import.
	 */
	public function test_import_settings(): void {
		$this->setExpectedDeprecated( 'admin_action_import_settings' );

		$end = $this->run_handler(
			'import_settings',
			array(
				'zerospam' => wp_create_nonce( 'import_settings' ),
				'settings' => (string) wp_json_encode( array( 'settings' => array( 'max_logs' => 4242 ) ) ),
			)
		);

		$this->assertSame( admin_url( 'admin.php?page=zero-spam-settings&tab=io' ), $end->location );
		$this->assertSame( 4242, (int) get_option( 'zero-spam-settings' )['max_logs'] );
	}

	/**
	 * Without the nonce nothing is imported.
	 */
	public function test_import_settings_needs_nonce(): void {
		delete_option( 'zero-spam-settings' );

		$end = $this->run_handler(
			'import_settings',
			array(
				'zerospam' => 'bad',
				'settings' => (string) wp_json_encode( array( 'settings' => array( 'max_logs' => 4242 ) ) ),
			)
		);

		$this->assertInstanceOf( RedirectException::class, $end );
		$this->assertFalse( get_option( 'zero-spam-settings' ) );

		$this->as_role( 'editor' );
		$this->assertInstanceOf( WPDieException::class, $this->run_handler( 'import_settings' ) );
	}

	/**
	 * The 5.x Refresh .htaccess button runs the settings action and goes to Rules.
	 */
	public function test_refresh_htaccess(): void {
		$this->setExpectedDeprecated( 'admin_post_zerospam_refresh_htaccess' );

		$end = $this->run_handler( 'refresh_htaccess', array(), array( '_wpnonce' => wp_create_nonce( 'zerospam-refresh-htaccess' ) ) );

		$this->assertSame( admin_url( 'admin.php?page=zero-spam-rules&tab=ips' ), $end->location );
	}

	/**
	 * Refreshing needs the nonce and the capability.
	 */
	public function test_refresh_htaccess_needs_nonce_and_capability(): void {
		$this->assertInstanceOf( WPDieException::class, $this->run_handler( 'refresh_htaccess' ) );

		$this->as_role( 'editor' );
		$this->assertInstanceOf( WPDieException::class, $this->run_handler( 'refresh_htaccess', array(), array( '_wpnonce' => wp_create_nonce( 'zerospam-refresh-htaccess' ) ) ) );
	}

	/**
	 * The 5.x Remove link of a no-lockout address removes it through the route, with the address in the nonce.
	 */
	public function test_remove_admin_safe_ip(): void {
		update_option( Admin_Safe_IPs::OPTION, array( '8.8.8.8', '1.1.1.1' ), false );

		$end = $this->run_handler(
			'remove_admin_safe_ip',
			array(),
			array(
				'ip'       => '8.8.8.8',
				'_wpnonce' => wp_create_nonce( 'zerospam_remove_admin_safe_ip|1.1.1.1' ),
			)
		);
		$this->assertInstanceOf( WPDieException::class, $end, 'A nonce for another address is refused.' );
		$this->assertCount( 2, Admin_Safe_IPs::get_ips() );

		$this->setExpectedDeprecated( 'admin_post_zerospam_remove_admin_safe_ip' );
		$end = $this->run_handler(
			'remove_admin_safe_ip',
			array(),
			array(
				'ip'       => '8.8.8.8',
				'_wpnonce' => wp_create_nonce( 'zerospam_remove_admin_safe_ip|8.8.8.8' ),
			)
		);

		$this->assertSame( admin_url( 'admin.php?page=zero-spam-rules&tab=no-lockout' ), $end->location );
		$this->assertSame( array( '1.1.1.1' ), Admin_Safe_IPs::get_ips() );

		$this->as_role( 'editor' );
		$this->assertInstanceOf( WPDieException::class, $this->run_handler( 'remove_admin_safe_ip', array(), array( 'ip' => '1.1.1.1' ) ) );
	}

	/**
	 * Blocks of an address.
	 *
	 * @param string $ip Address.
	 */
	private function count_blocks( string $ip ): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_ip = %s', $wpdb->prefix . \ZeroSpam\Includes\DB::$tables['blocked'], $ip ) );
	}
}
