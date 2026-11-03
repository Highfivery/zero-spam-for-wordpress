<?php
/**
 * Tests for Settings::auto_configure() on a network site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Core\Settings;
use ZeroSpam\Core\Settings_Schema;
use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Applying the recommended settings on a network site writes only the
 * settings that have a recommended value (and keeps what the site already
 * stores), so the site keeps following the network's other defaults,
 * the IP Allowlist among them (`v6_allowlist_follows_network`).
 *
 * @group ms-required
 * @covers \ZeroSpam\Core\Settings::auto_configure
 */
class Test_Auto_Configure_Network extends TestCase {

	/**
	 * A site of the network.
	 *
	 * @var int
	 */
	private static int $site = 0;

	/**
	 * Creates the site with its plugin tables.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$site = self::create_site_with_tables( $factory );
	}

	/**
	 * No network values, no stored settings on the site.
	 */
	public function set_up(): void {
		parent::set_up();

		delete_site_option( Network_Settings::META_KEY );
		delete_site_transient( Network_Settings::cache_key() );

		switch_to_blog( self::$site );
		delete_option( 'zero-spam-settings' );
		restore_current_blog();
	}

	/**
	 * The recommended settings, then a network IP Allowlist: the site follows it.
	 */
	public function test_site_follows_a_later_network_allowlist(): void {
		switch_to_blog( self::$site );
		self::reset_static_caches();
		Settings::auto_configure();
		$stored = get_option( 'zero-spam-settings' );
		restore_current_blog();

		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'ip_whitelist', $stored, 'A setting with no recommended value is not stored.' );
		$this->assertSame( 'enabled', $stored['log_blocked_ips'] ?? null, 'Recommended values are still stored.' );

		$this->set_network_setting( 'ip_whitelist', "198.51.100.7\n203.0.113.8" );

		switch_to_blog( self::$site );
		self::reset_static_caches();
		$resolved = Settings_Schema::resolve( 'ip_whitelist' );
		restore_current_blog();

		$this->assertSame( "198.51.100.7\n203.0.113.8", $resolved['value'] );
		$this->assertSame( Settings_Schema::SOURCE_NETWORK, $resolved['source'] );
	}

	/**
	 * A site's own value of a setting without a recommendation stays.
	 */
	public function test_site_keeps_its_own_values(): void {
		switch_to_blog( self::$site );
		update_option( 'zero-spam-settings', array( 'ip_whitelist' => '192.0.2.44' ) );
		self::reset_static_caches();
		Settings::auto_configure();
		$stored = get_option( 'zero-spam-settings' );
		restore_current_blog();

		$this->assertSame( '192.0.2.44', $stored['ip_whitelist'] ?? null );
	}
}
