<?php
/**
 * Tests for the administrator addresses that are never locked out, on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin_Safe_IPs;
use ZeroSpam\Tests\TestCase;

/**
 * Site activation saves to that site, network activation to the main site;
 * a network-locked IP Whitelist does not matter, because the list is separate.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Admin_Safe_IPs
 */
class Test_Admin_Safe_IPs_Network extends TestCase {

	/**
	 * A public address (no network request is made for it).
	 */
	const PUBLIC_IP = '8.8.8.8';

	/**
	 * A site besides the main site.
	 *
	 * @var int
	 */
	private static int $site = 0;

	/**
	 * Creates the site.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site = self::create_site_with_tables( $factory );
	}

	/**
	 * Logs in a super admin requesting from a public IP.
	 */
	public function set_up(): void {
		parent::set_up();

		grant_super_admin( $this->as_role( 'administrator' ) );
		$this->set_request( '/wp-admin/network/plugins.php', self::PUBLIC_IP );
	}

	/**
	 * The saved addresses of a site.
	 *
	 * @param int $blog_id Site ID.
	 * @return string[]
	 */
	private function saved_on( int $blog_id ): array {
		switch_to_blog( $blog_id );
		$ips = Admin_Safe_IPs::get_ips();
		restore_current_blog();

		return $ips;
	}

	/**
	 * Network activation saves to the main site, wherever it runs.
	 */
	public function test_network_activation_saves_to_the_main_site(): void {
		switch_to_blog( self::$site );
		zerospam_plugin_activation( true );
		$current = get_current_blog_id();
		restore_current_blog();

		$this->assertSame( self::$site, $current, 'The original site was not restored.' );
		$this->assertSame( array( self::PUBLIC_IP ), $this->saved_on( get_main_site_id() ) );
		$this->assertSame( array(), $this->saved_on( self::$site ) );
	}

	/**
	 * Activating on one site saves to that site only.
	 */
	public function test_site_activation_saves_to_that_site(): void {
		switch_to_blog( self::$site );
		zerospam_plugin_activation( false );
		restore_current_blog();

		$this->assertSame( array( self::PUBLIC_IP ), $this->saved_on( self::$site ) );
		$this->assertSame( array(), $this->saved_on( get_main_site_id() ) );
	}

	/**
	 * A locked network IP Whitelist neither blocks the save nor is changed by it.
	 */
	public function test_locked_network_whitelist_does_not_matter(): void {
		$this->set_network_setting( 'ip_whitelist', '192.0.2.50', true );

		zerospam_plugin_activation( false );

		$this->assertSame( array( self::PUBLIC_IP ), $this->saved_on( get_current_blog_id() ) );
		$options = get_option( 'zero-spam-settings', array() );
		$this->assertArrayNotHasKey( 'ip_whitelist', is_array( $options ) ? $options : array() );
	}
}
