<?php
/**
 * Tests for the network admin Plugins screen's Settings link.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin\Admin;
use ZeroSpam\Tests\TestCase;

/**
 * On the network admin's Plugins screen, Zero Spam's row links to the
 * network page, for users who can manage network options only.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Admin\Admin::network_plugin_action_links
 * @covers \ZeroSpam\Core\Admin\Admin::init
 */
class Test_Admin_Network_Links extends TestCase {

	/**
	 * The filter is registered for the plugin's row.
	 */
	public function test_filter_is_registered(): void {
		$admin = new Admin();
		$admin->init();

		$this->assertNotFalse( has_filter( 'network_admin_plugin_action_links_' . ZEROSPAM_PLUGIN_BASE, array( $admin, 'network_plugin_action_links' ) ) );
	}

	/**
	 * A super admin gets a Settings link to the network page, first.
	 */
	public function test_super_admin_gets_the_network_page_link(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user );
		wp_set_current_user( $user );

		$links = ( new Admin() )->network_plugin_action_links( array( 'deactivate' => 'x' ) );

		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'href="' . esc_url( network_admin_url( 'admin.php?page=zero-spam-network' ) ) . '"', (string) $links[0] );
		$this->assertStringContainsString( '>Settings<', (string) $links[0] );
	}

	/**
	 * A user who cannot manage network options gets no link.
	 */
	public function test_site_administrator_gets_no_link(): void {
		$this->as_role( 'administrator' );

		$this->assertSame( array( 'deactivate' => 'x' ), ( new Admin() )->network_plugin_action_links( array( 'deactivate' => 'x' ) ) );
	}
}
