<?php
/**
 * Tests for uninstall.php on a multisite network.
 *
 * @package ZeroSpam\Tests
 */

declare( strict_types=1 );

use ZeroSpam\Tests\TestCase;

/**
 * Uninstalling on a network cleans every site and the network, and leaves
 * WordPress on the site it started on.
 *
 * @group ms-required
 * @group known-defect
 * @coversNothing
 */
class Test_Uninstall_Network extends TestCase {

	/**
	 * Sites besides the main site.
	 *
	 * @var int[]
	 */
	private static array $sites = array();

	/**
	 * Network (site) options the plugin writes.
	 */
	const NETWORK_OPTIONS = array(
		'zerospam_network_settings',
		'zero-spam-network-zerospam',
		'zerospam_network_notifications_enabled',
		'zerospam_network_settings_notifications_enabled',
		'zero_spam_last_api_request',
	);

	/**
	 * Creates two sites with real plugin tables.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Name required by the WordPress test framework.
		self::$sites = array(
			self::create_site_with_tables( $factory ),
			self::create_site_with_tables( $factory ),
		);
	}

	/**
	 * Runs uninstall.php as WordPress does.
	 */
	private function uninstall(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'zero-spam/wordpress-zero-spam.php' );
		}

		require dirname( __DIR__, 3 ) . '/uninstall.php';
	}

	/**
	 * Seeds every module option on every site.
	 */
	private function seed_sites(): void {
		foreach ( array_merge( array( get_main_site_id() ), self::$sites ) as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( self::PLUGIN_MODULES as $module ) {
				update_option( "zero-spam-{$module}", array( 'seeded' => 'yes' ) );
			}
			update_option( 'zerospam_db_version', 'seeded' );
			restore_current_blog();
		}
	}

	/**
	 * Module options are removed from every site.
	 */
	public function test_removes_options_on_every_site(): void {
		$this->seed_sites();

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();

		$left = array();
		foreach ( array_merge( array( get_main_site_id() ), self::$sites ) as $blog_id ) {
			foreach ( array_merge( self::PLUGIN_MODULES, array( 'db_version' ) ) as $module ) {
				$option = 'db_version' === $module ? 'zerospam_db_version' : "zero-spam-{$module}";
				if ( false !== get_blog_option( $blog_id, $option ) ) {
					$left[] = "site {$blog_id}: {$option}";
				}
			}
		}

		$this->assertSame( array(), $left, 'uninstall.php left these site options behind.' );
	}

	/**
	 * Network options are removed.
	 */
	public function test_removes_network_options(): void {
		foreach ( self::NETWORK_OPTIONS as $option ) {
			update_site_option( $option, array( 'seeded' => 'yes' ) );
		}

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();

		$left = array();
		foreach ( self::NETWORK_OPTIONS as $option ) {
			if ( false !== get_site_option( $option ) ) {
				$left[] = $option;
			}
		}

		$this->assertSame( array(), $left, 'uninstall.php left these network options behind.' );
	}

	/**
	 * Cron events are cleared on every site.
	 */
	public function test_removes_cron_events_on_every_site(): void {
		$sites = array_merge( array( get_main_site_id() ), self::$sites );
		foreach ( $sites as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( self::PLUGIN_CRON_HOOKS as $hook ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
			}
			restore_current_blog();
		}

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		$left = array();
		foreach ( $sites as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( self::PLUGIN_CRON_HOOKS as $hook ) {
				if ( false !== wp_next_scheduled( $hook ) ) {
					$left[] = "site {$blog_id}: {$hook}";
				}
			}
			restore_current_blog();
		}

		$this->assertSame( array(), $left, 'uninstall.php left these cron events behind.' );
	}

	/**
	 * After uninstalling, WordPress is back on the site it started on.
	 */
	public function test_restores_the_original_site(): void {
		$this->assertSame( 1, get_current_blog_id() );

		$this->uninstall();

		$this->assertFalse( ms_is_switched(), 'uninstall.php left WordPress switched to another site.' );
		$this->assertSame( 1, get_current_blog_id() );
	}
}
