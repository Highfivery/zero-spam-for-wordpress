<?php
/**
 * Tests for the Trusted Proxy IPs setting on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\User;
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
}
