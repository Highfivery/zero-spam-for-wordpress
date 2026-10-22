<?php
/**
 * Tests for the Trusted Proxy IPs setting on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Shared_IP_Warning;
use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\Admin\Network_Settings_Page;
use ZeroSpam\Core\Settings;
use ZeroSpam\Core\User;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Includes\Network_Settings_Manager;
use ZeroSpam\Tests\TestCase;

/**
 * The network administrator can set and lock `trusted_proxies` like any other
 * registry setting, and a locked value wins over a site's own list.
 *
 * @group ms-required
 * @covers \ZeroSpam\Includes\Network_Settings_Manager::set_setting
 * @covers \ZeroSpam\Core\User::get_ip
 * @covers \ZeroSpam\Core\Admin\Shared_IP_Warning::render
 * @covers \ZeroSpam\Includes\Admin\Network_Settings_Page::ajax_set_setting
 */
class Test_Network_Trusted_Proxies extends TestCase {

	/**
	 * Forgets the simulated AJAX post.
	 */
	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Saves one setting through the network settings screen's AJAX handler.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Submitted value.
	 * @return array<string, mixed> Decoded JSON response.
	 */
	private function ajax_set_setting( string $key, string $value ): array {
		$this->as_role( 'administrator' );
		grant_super_admin( get_current_user_id() );

		$_POST    = array(
			'nonce'  => wp_create_nonce( 'zerospam_network_settings' ),
			'key'    => $key,
			'value'  => $value,
			'locked' => '0',
		);
		$_REQUEST = $_POST;

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new WPDieException( 'ajax' );
				};
			}
		);

		ob_start();
		try {
			( new Network_Settings_Page() )->ajax_set_setting();
		} catch ( WPDieException $e ) {
			unset( $e );
		}
		$response = json_decode( (string) ob_get_clean(), true );

		$this->assertIsArray( $response, 'The handler answered with JSON.' );

		return $response;
	}

	/**
	 * The stored network value of a setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	private function network_value( string $key ) {
		$settings = get_site_option( Network_Settings::META_KEY, array() );

		return $settings['settings'][ $key ]['value'] ?? null;
	}

	/**
	 * Saving Trusted Proxy IPs on the network screen keeps one entry per line.
	 */
	public function test_network_save_keeps_trusted_proxies_one_per_line(): void {
		$response = $this->ajax_set_setting( 'trusted_proxies', "192.0.2.0/24\n10.0.0.0/8\n::ffff:198.51.100.0/120" );

		$this->assertTrue( $response['success'] );
		$this->assertSame( "192.0.2.0/24\n10.0.0.0/8\n198.51.100.0/24", $this->network_value( 'trusted_proxies' ) );
	}

	/**
	 * Invalid or too-broad entries are refused on the network screen, with
	 * the entries listed (escaped) in the error, and nothing is stored.
	 */
	public function test_network_save_rejects_invalid_and_too_broad_trusted_proxies(): void {
		$response = $this->ajax_set_setting( 'trusted_proxies', "192.0.2.0/24\n0.0.0.0/0\nnot-an-ip" );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'not-an-ip', $response['data']['message'] );
		$this->assertStringContainsString( '0.0.0.0/0 (too broad', $response['data']['message'] );
		$this->assertSame( array( 'not-an-ip', '0.0.0.0/0 (too broad: would trust every visitor\'s X-Forwarded-For)' ), $response['data']['invalid'] );
		$this->assertNull( $this->network_value( 'trusted_proxies' ) );

		$response = $this->ajax_set_setting( 'trusted_proxies', '<img src=x onerror=alert(1)>' );
		$this->assertStringNotContainsString( '<img', $response['data']['message'], 'The rejected entries are escaped for the inline message.' );
	}

	/**
	 * A multi-line IP Whitelist saved on the network screen keeps its lines,
	 * so every address on it is whitelisted (it used to be collapsed to one line).
	 */
	public function test_network_save_keeps_ip_whitelist_lines(): void {
		$response = $this->ajax_set_setting( 'ip_whitelist', "192.0.2.1\n198.51.100.2" );

		$this->assertTrue( $response['success'] );
		$this->assertSame( "192.0.2.1\n198.51.100.2", $this->network_value( 'ip_whitelist' ) );

		self::reset_static_caches();
		delete_site_transient( Network_Settings::CACHE_KEY );
		$this->assertTrue( Utilities::is_whitelisted( '198.51.100.2' ) );
		$this->assertTrue( Utilities::is_whitelisted( '192.0.2.1' ) );
	}

	/**
	 * A locked network list is what every site uses, including for IP detection.
	 */
	public function test_network_admin_can_set_and_lock_trusted_proxies(): void {
		$this->as_role( 'administrator' );
		grant_super_admin( get_current_user_id() );

		// The site has its own list, which the lock must override.
		update_option( 'zero-spam-settings', array( 'trusted_proxies' => '198.51.100.0/24' ) );

		$this->assertTrue( ( new Network_Settings_Manager() )->set_setting( 'trusted_proxies', '192.0.2.0/24', true ) );
		self::reset_static_caches();

		$this->assertTrue( ( new Network_Settings() )->is_locked( 'trusted_proxies' ) );
		$this->assertSame( '192.0.2.0/24', Settings::get_settings( 'trusted_proxies' ) );

		$this->set_request( '/', '192.0.2.10' );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
		$this->assertSame( '203.0.113.9', User::get_ip(), 'The locked network proxy is trusted.' );

		$this->set_request( '/', '198.51.100.10' );
		$this->assertSame( '198.51.100.10', User::get_ip(), 'The site\'s own list does not apply while the setting is locked.' );
	}

	/**
	 * On a site where Trusted Proxy IPs is locked, the shared-IP warning asks
	 * for the network administrator instead of linking to a disabled field.
	 */
	public function test_warning_points_to_the_network_admin_when_locked(): void {
		$this->admin_screen( 'settings_page_wordpress-zero-spam-settings' );
		$this->as_role( 'administrator' );
		$this->set_network_setting( 'trusted_proxies', '192.0.2.0/24', true );
		delete_transient( Shared_IP_Warning::TRANSIENT );
		update_option( Migrations::PROXY_DETECTION_SINCE_OPTION, wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		$this->insert_log_rows( Shared_IP_Warning::SAMPLE_SIZE, array( 'user_ip' => '198.51.100.50' ) );
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.77';

		ob_start();
		( new Shared_IP_Warning() )->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'ask your network administrator', $html );
		$this->assertStringNotContainsString( 'subview=settings#trusted_proxies', $html, 'No link to the locked field.' );
		$this->assertStringContainsString( 'action=' . Shared_IP_Warning::ACTION, $html, 'It can still be dismissed.' );
	}
}
