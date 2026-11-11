<?php
/**
 * Tests for how much work Network_Settings does per call and per site.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Includes\Network_Settings;
use ZeroSpam\Tests\TestCase;

/**
 * Network_Settings is created in several places (the plugin, the manager,
 * REST and CLI); each instance used to add its resolver to the settings
 * filter again, and checking which sites follow a network value rebuilt the
 * settings registry on every site (AGENTS.md section 8).
 *
 * @group ms-required
 *
 * @covers \ZeroSpam\Includes\Network_Settings
 */
class Test_Network_Settings_Reads extends TestCase {

	/**
	 * Sites of the network besides the main one.
	 *
	 * @var int[]
	 */
	private static array $sites = array();

	/**
	 * Three more sites.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		for ( $i = 1; $i <= 3; $i++ ) {
			self::$sites[] = self::create_site_with_tables( $factory );
		}
	}

	/**
	 * The resolver is added to the settings filter once, however many instances exist.
	 */
	public function test_resolver_is_added_once(): void {
		new Network_Settings();
		new Network_Settings();
		new \ZeroSpam\Includes\Network_Settings_Manager();

		$callbacks = 0;
		foreach ( $GLOBALS['wp_filter']['zerospam_settings']->callbacks[100] ?? array() as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Network_Settings ) {
				++$callbacks;
			}
		}

		$this->assertSame( 1, $callbacks );
	}

	/**
	 * Hooks removed after the first instance are added again by the next one.
	 */
	public function test_removed_hooks_are_added_again(): void {
		new Network_Settings();
		remove_all_filters( 'zerospam_settings' );

		$this->assertFalse( has_filter( 'zerospam_settings' ) );
		new Network_Settings();

		$found = false;
		foreach ( $GLOBALS['wp_filter']['zerospam_settings']->callbacks[100] ?? array() as $callback ) {
			$found = $found || ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Network_Settings );
		}
		$this->assertTrue( $found );
	}

	/**
	 * A locked setting has no overridden sites, whatever the sites store.
	 */
	public function test_locked_setting_has_no_overrides(): void {
		$this->set_network_setting( 'blocked_message', 'Network says no.', true );
		switch_to_blog( self::$sites[0] );
		update_option( 'zero-spam-settings', array( 'blocked_message' => 'Mine.' ) );
		restore_current_blog();

		$status = ( new Network_Settings() )->get_application_status( 'blocked_message' );

		$this->assertSame( 0, $status['overridden'] );
		$this->assertSame( $status['total_sites'], $status['using_default'] );
	}

	/**
	 * Counting the sites that follow a network value builds the registry at most once.
	 */
	public function test_application_status_does_not_build_the_registry_per_site(): void {
		$this->set_network_setting( 'blocked_message', 'Network says no.' );
		switch_to_blog( self::$sites[0] );
		update_option( 'zero-spam-settings', array( 'blocked_message' => 'Mine.' ) );
		restore_current_blog();

		$network = new Network_Settings();
		$status  = null;
		$builds  = $this->count_registry_builds(
			static function () use ( $network, &$status ): void {
				$status = $network->get_application_status( 'blocked_message' );
			}
		);

		$this->assertLessThanOrEqual( 1, $builds );
		$this->assertSame( 1, $status['overridden'] );
		$this->assertSame( $status['total_sites'] - 1, $status['using_default'] );
	}

	/**
	 * Each site's own value is still read by is_using_default() and get_site_override().
	 */
	public function test_per_site_answers_are_unchanged(): void {
		$this->set_network_setting( 'blocked_message', 'Network says no.' );
		switch_to_blog( self::$sites[1] );
		update_option( 'zero-spam-settings', array( 'blocked_message' => 'Mine.' ) );
		restore_current_blog();

		$network = new Network_Settings();

		$this->assertFalse( $network->is_using_default( 'blocked_message', self::$sites[1] ) );
		$this->assertSame( 'Mine.', $network->get_site_override( 'blocked_message', self::$sites[1] ) );
		$this->assertTrue( $network->is_using_default( 'blocked_message', self::$sites[2] ) );
		$this->assertNull( $network->get_site_override( 'blocked_message', self::$sites[2] ) );
	}

	/**
	 * Clearing the network statistics cache deletes its own transients only:
	 * `_` in a LIKE pattern matches any character, so it must be escaped.
	 *
	 * @covers \ZeroSpam\Includes\Network_Stats_Tracker::clear_cache
	 */
	public function test_stats_cache_clear_deletes_only_its_transients(): void {
		set_transient( 'zerospam_network_stats_1_30', array( 'x' ), HOUR_IN_SECONDS );
		set_transient( 'zerospam_site_breakdown_1_30_10_spam_count', array( 'x' ), HOUR_IN_SECONDS );
		update_option( '_transient_zerospam_networkXstats_keep', 'unrelated', false );
		update_option( '_transientXzerospam_site_breakdown_keep', 'unrelated', false );

		\ZeroSpam\Includes\Network_Stats_Tracker::clear_cache();
		wp_cache_flush();

		$this->assertFalse( get_option( '_transient_zerospam_network_stats_1_30' ) );
		$this->assertFalse( get_option( '_transient_zerospam_site_breakdown_1_30_10_spam_count' ) );
		$this->assertSame( 'unrelated', get_option( '_transient_zerospam_networkXstats_keep' ) );
		$this->assertSame( 'unrelated', get_option( '_transientXzerospam_site_breakdown_keep' ) );
	}
}
