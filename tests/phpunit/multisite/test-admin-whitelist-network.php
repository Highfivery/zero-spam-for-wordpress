<?php
/**
 * Tests for whitelisting the administrator's IP on a network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Admin_Whitelist;
use ZeroSpam\Tests\TestCase;

/**
 * Site activation writes to that site, network activation to the main site,
 * and a whitelist the network manages is left alone.
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Admin_Whitelist
 */
class Test_Admin_Whitelist_Network extends TestCase {

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
	 * Logs in a super admin requesting from the default IP.
	 */
	public function set_up(): void {
		parent::set_up();

		grant_super_admin( $this->as_role( 'administrator' ) );
		$this->set_request( '/wp-admin/network/plugins.php', self::DEFAULT_IP );
	}

	/**
	 * The stored IP Whitelist of a site.
	 *
	 * @param int $blog_id Site ID.
	 */
	private function whitelist_on( int $blog_id ): string {
		switch_to_blog( $blog_id );
		$options = get_option( 'zero-spam-settings', array() );
		restore_current_blog();

		return is_array( $options ) && isset( $options['ip_whitelist'] ) ? (string) $options['ip_whitelist'] : '';
	}

	/**
	 * Network activation writes to the main site, wherever it runs.
	 */
	public function test_network_activation_writes_to_the_main_site(): void {
		switch_to_blog( self::$site );
		zerospam_plugin_activation( true );
		$current = get_current_blog_id();
		restore_current_blog();

		$this->assertSame( self::$site, $current, 'The original site was not restored.' );
		$this->assertSame( self::DEFAULT_IP, $this->whitelist_on( get_main_site_id() ) );
		$this->assertSame( '', $this->whitelist_on( self::$site ) );
	}

	/**
	 * Activating on one site writes to that site only.
	 */
	public function test_site_activation_writes_to_that_site(): void {
		switch_to_blog( self::$site );
		zerospam_plugin_activation( false );
		restore_current_blog();

		$this->assertSame( self::DEFAULT_IP, $this->whitelist_on( self::$site ) );
		$this->assertSame( '', $this->whitelist_on( get_main_site_id() ) );
	}

	/**
	 * A whitelist the network locks or sets as the default is left to the network.
	 *
	 * @dataProvider data_network_managed
	 *
	 * @param bool $locked Whether the network value is locked.
	 */
	public function test_network_managed_whitelist_is_left_alone( bool $locked ): void {
		$this->set_network_setting( 'ip_whitelist', '192.0.2.50', $locked );

		zerospam_plugin_activation( false );

		$this->assertSame( '', $this->whitelist_on( get_current_blog_id() ) );
		$this->assertFalse( get_option( Admin_Whitelist::NOTICE_OPTION ) );
	}

	/**
	 * Locked and unlocked network values.
	 *
	 * @return array<string, array{0: bool}>
	 */
	public static function data_network_managed(): array {
		return array(
			'locked'          => array( true ),
			'network default' => array( false ),
		);
	}

	/**
	 * A site that already overrides an unlocked network value gets the IP added to its own list.
	 */
	public function test_site_override_is_extended(): void {
		$this->set_network_setting( 'ip_whitelist', '192.0.2.50', false );
		$this->set_setting( 'ip_whitelist', '192.0.2.60', 'settings' );

		zerospam_plugin_activation( false );

		$this->assertSame( "192.0.2.60\n" . self::DEFAULT_IP, $this->whitelist_on( get_current_blog_id() ) );
	}
}
