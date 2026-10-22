<?php
/**
 * Tests for the Trusted Proxy IPs setting on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Shared_IP_Warning;
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
 */
class Test_Network_Trusted_Proxies extends TestCase {

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
