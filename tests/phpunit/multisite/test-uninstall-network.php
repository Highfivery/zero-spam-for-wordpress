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
	 * Per-site tables (`{prefix}`).
	 */
	const SITE_TABLES = array( 'wpzerospam_log', 'wpzerospam_blocked' );

	/**
	 * Network-wide tables (`{base_prefix}`).
	 */
	const NETWORK_TABLES = array(
		'wpzerospam_api_usage',
		'wpzerospam_stats_daily',
		'wpzerospam_stats_monthly',
		'wpzerospam_network_templates',
		'wpzerospam_network_audit',
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
	 * Every site of the network, main site first.
	 *
	 * @return int[]
	 */
	private function all_sites(): array {
		return array_merge( array( get_main_site_id() ), self::$sites );
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
	 * Every network option the plugin writes.
	 *
	 * @return string[]
	 */
	private function network_options(): array {
		return array_merge(
			self::PLUGIN_NETWORK_OPTIONS,
			array_map(
				static function ( string $module ): string {
					return "zero-spam-network-{$module}";
				},
				self::PLUGIN_MODULES
			)
		);
	}

	/**
	 * Seeds every module option, site option and transient on every site.
	 */
	private function seed_sites(): void {
		foreach ( $this->all_sites() as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( self::PLUGIN_MODULES as $module ) {
				update_option( "zero-spam-{$module}", array( 'seeded' => 'yes' ) );
			}
			foreach ( self::PLUGIN_SITE_OPTIONS as $option ) {
				update_option( $option, 'seeded' );
			}
			foreach ( self::PLUGIN_TRANSIENTS as $transient ) {
				set_transient( $transient, 'seeded', HOUR_IN_SECONDS );
			}
			restore_current_blog();
		}
	}

	/**
	 * Module options, site options and transients are removed from every site.
	 */
	public function test_removes_options_on_every_site(): void {
		$this->seed_sites();

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();

		$options = array_merge(
			array_map(
				static function ( string $module ): string {
					return "zero-spam-{$module}";
				},
				self::PLUGIN_MODULES
			),
			self::PLUGIN_SITE_OPTIONS
		);

		$left = array();
		foreach ( $this->all_sites() as $blog_id ) {
			foreach ( $options as $option ) {
				if ( false !== get_blog_option( $blog_id, $option ) ) {
					$left[] = "site {$blog_id}: {$option}";
				}
			}
			switch_to_blog( $blog_id );
			foreach ( self::PLUGIN_TRANSIENTS as $transient ) {
				if ( false !== get_transient( $transient ) ) {
					$left[] = "site {$blog_id}: transient {$transient}";
				}
			}
			restore_current_blog();
		}

		$this->assertSame( array(), $left, 'uninstall.php left these site options behind.' );
	}

	/**
	 * Network options and network transients are removed.
	 */
	public function test_removes_network_options(): void {
		foreach ( $this->network_options() as $option ) {
			update_site_option( $option, array( 'seeded' => 'yes' ) );
		}
		update_site_option( 'zerospam_unlisted', 'seeded' );
		update_site_option( 'zerospamXunlisted', 'seeded' );
		set_site_transient( 'zerospam_network_settings', 'seeded', HOUR_IN_SECONDS );

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();

		$left = array();
		foreach ( array_merge( $this->network_options(), array( 'zerospam_unlisted' ) ) as $option ) {
			if ( false !== get_site_option( $option ) ) {
				$left[] = $option;
			}
		}
		if ( false !== get_site_transient( 'zerospam_network_settings' ) ) {
			$left[] = 'site transient zerospam_network_settings';
		}

		$this->assertSame( array(), $left, 'uninstall.php left these network options behind.' );
		$this->assertSame( 'seeded', get_site_option( 'zerospamXunlisted' ), 'The sitemeta prefix sweep deleted a key that is not the plugin\'s.' );
	}

	/**
	 * Cron events are cleared on every site.
	 */
	public function test_removes_cron_events_on_every_site(): void {
		foreach ( $this->all_sites() as $blog_id ) {
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
		foreach ( $this->all_sites() as $blog_id ) {
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
	 * Every site's tables and the network tables are dropped, each once.
	 */
	public function test_drops_site_and_network_tables(): void {
		global $wpdb;

		$queries = array_map(
			'trim',
			$this->capture_queries(
				function () {
					$this->uninstall();
				}
			)
		);
		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		$expected = array();
		foreach ( $this->all_sites() as $blog_id ) {
			foreach ( self::SITE_TABLES as $table ) {
				$expected[] = $wpdb->get_blog_prefix( $blog_id ) . $table;
			}
		}
		foreach ( self::NETWORK_TABLES as $table ) {
			$expected[] = $wpdb->base_prefix . $table;
		}

		$problems = array();
		foreach ( $expected as $name ) {
			$pattern = '/^DROP TABLE IF EXISTS\s+`?' . preg_quote( $name, '/' ) . '`?\s*;?$/i';
			$drops   = count( preg_grep( $pattern, $queries ) );
			if ( 1 !== $drops ) {
				$problems[] = "{$name} dropped {$drops} times";
			}
		}

		$this->assertSame( array(), $problems, 'uninstall.php does not drop each table exactly once.' );
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
