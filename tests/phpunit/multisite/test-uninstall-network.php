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
	 * Path of the current site's error log written by Utilities::log().
	 */
	private static function error_log_file(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'zerospam.log';
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
		foreach ( self::PLUGIN_NETWORK_TRANSIENTS as $transient ) {
			set_site_transient( $transient, 'seeded', HOUR_IN_SECONDS );
		}

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
		foreach ( self::PLUGIN_NETWORK_TRANSIENTS as $transient ) {
			if ( false !== get_site_transient( $transient ) ) {
				$left[] = "site transient {$transient}";
			}
		}

		$this->assertSame( array(), $left, 'uninstall.php left these network options behind.' );
		$this->assertSame( 'seeded', get_site_option( 'zerospamXunlisted' ), 'The sitemeta prefix sweep deleted a key that is not the plugin\'s.' );
	}

	/**
	 * Every site's error log (uploads/ or uploads/sites/{id}/) is deleted.
	 */
	public function test_deletes_the_error_log_on_every_site(): void {
		$files = array();
		foreach ( $this->all_sites() as $blog_id ) {
			switch_to_blog( $blog_id );
			$files[ $blog_id ] = self::error_log_file();
			restore_current_blog();
		}
		$this->assertCount( count( $files ), array_unique( $files ), 'Each site needs its own error log path.' );
		foreach ( $files as $file ) {
			wp_mkdir_p( dirname( $file ) );
			file_put_contents( $file, "203.0.113.10 seeded\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Seeds a fixture file.
		}

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		$left = array();
		foreach ( $files as $blog_id => $file ) {
			if ( file_exists( $file ) ) {
				$left[] = "site {$blog_id}: {$file}";
				wp_delete_file( $file );
			}
		}

		$this->assertSame( array(), $left, 'uninstall.php left these error logs behind.' );
	}

	/**
	 * Legacy user meta is removed.
	 */
	public function test_removes_user_meta(): void {
		$user_id = self::factory()->user->create();
		foreach ( self::PLUGIN_USER_META as $meta_key ) {
			update_user_meta( $user_id, $meta_key, time() );
		}

		$this->uninstall();
		while ( ms_is_switched() ) {
			restore_current_blog();
		}
		wp_cache_flush();

		$left = array();
		foreach ( self::PLUGIN_USER_META as $meta_key ) {
			if ( '' !== get_user_meta( $user_id, $meta_key, true ) ) {
				$left[] = $meta_key;
			}
		}

		$this->assertSame( array(), $left, 'uninstall.php left this user meta behind.' );
	}

	/**
	 * Cron events, including those older versions scheduled, are cleared on every site.
	 */
	public function test_removes_cron_events_on_every_site(): void {
		$cron_hooks = array_merge( self::PLUGIN_CRON_HOOKS, self::PLUGIN_LEGACY_CRON_HOOKS );
		foreach ( $this->all_sites() as $blog_id ) {
			switch_to_blog( $blog_id );
			foreach ( $cron_hooks as $hook ) {
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
			foreach ( $cron_hooks as $hook ) {
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
	 * Tables a run of uninstall.php dropped.
	 *
	 * @return string[] Table names.
	 */
	private function dropped_tables(): array {
		$dropped = array();
		foreach ( $this->capture_queries( fn () => $this->uninstall() ) as $query ) {
			if ( preg_match( '/^\s*DROP TABLE IF EXISTS\s+`?([A-Za-z0-9_]+)`?/i', $query, $match ) ) {
				$dropped[] = $match[1];
			}
		}
		while ( ms_is_switched() ) {
			restore_current_blog();
		}

		return $dropped;
	}

	/**
	 * Stores the network's "Delete Data on Uninstall" value.
	 *
	 * @param mixed $value  Value.
	 * @param bool  $locked Locked.
	 */
	private function network_flag( $value, bool $locked ): void {
		update_site_option(
			'zerospam_network_settings',
			array(
				'settings' => array(
					'delete_data_on_uninstall' => array(
						'value'  => $value,
						'locked' => $locked,
					),
				),
			)
		);
	}

	/**
	 * Records the v6_uninstall_flag migration as run for the network and the
	 * given sites (default: every site).
	 *
	 * @param int[]|null $sites Sites.
	 */
	private function migrated( ?array $sites = null ): void {
		update_site_option( 'zerospam_network_completed_migrations', array( 'v6_uninstall_flag' ) );
		foreach ( $sites ?? $this->all_sites() as $blog_id ) {
			update_blog_option( $blog_id, 'zerospam_completed_migrations', array( 'v6_uninstall_flag' ) );
		}
	}

	/**
	 * A dormant subsite (no request since the update, so v6_uninstall_flag
	 * never ran there) that stored a 5.x false still has its data deleted,
	 * as 5.x always did; a migrated subsite that turned it off keeps it.
	 */
	public function test_dormant_subsite_still_deletes(): void {
		global $wpdb;

		update_site_option( 'zerospam_network_completed_migrations', array( 'v6_uninstall_flag' ) );
		update_blog_option( self::$sites[0], 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => false ) );
		update_blog_option( self::$sites[0], 'zerospam_completed_migrations', array( 'v6_backup_5x' ) );
		update_blog_option( self::$sites[1], 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => false ) );
		update_blog_option( self::$sites[1], 'zerospam_completed_migrations', array( 'v6_uninstall_flag' ) );

		$dropped = $this->dropped_tables();

		$this->assertContains( $wpdb->get_blog_prefix( self::$sites[0] ) . 'wpzerospam_log', $dropped, 'The dormant subsite\'s 5.x false still deletes.' );
		$this->assertNotContains( $wpdb->get_blog_prefix( self::$sites[1] ) . 'wpzerospam_log', $dropped, 'A migrated subsite that turned it off keeps its data.' );
	}

	/**
	 * A network value the migration never saw (a locked 5.x false) still deletes.
	 */
	public function test_unmigrated_network_false_still_deletes(): void {
		global $wpdb;

		$this->network_flag( false, true );
		delete_site_option( 'zerospam_network_completed_migrations' );

		$dropped = $this->dropped_tables();

		foreach ( self::NETWORK_TABLES as $table ) {
			$this->assertContains( $wpdb->base_prefix . $table, $dropped );
		}
	}

	/**
	 * Each site follows its own value, then the network's; the network-wide
	 * data follows the network's value.
	 */
	public function test_each_site_follows_its_own_value_then_the_network(): void {
		global $wpdb;

		$this->network_flag( false, false );
		$this->migrated();
		update_blog_option( get_main_site_id(), 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => 'enabled' ) );
		update_blog_option( self::$sites[0], 'zero-spam-settings', array( 'max_logs' => 500 ) );
		update_blog_option( self::$sites[1], 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => false ) );
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'zerospam_ui', array( 'theme' => 'dark' ) );

		$dropped = $this->dropped_tables();
		wp_cache_flush();

		foreach ( self::SITE_TABLES as $table ) {
			$this->assertContains( $wpdb->get_blog_prefix( get_main_site_id() ) . $table, $dropped, 'The main site turned it on.' );
			$this->assertNotContains( $wpdb->get_blog_prefix( self::$sites[0] ) . $table, $dropped, 'A site without a value follows the network (off).' );
			$this->assertNotContains( $wpdb->get_blog_prefix( self::$sites[1] ) . $table, $dropped, 'A site that turned it off keeps its data.' );
		}
		foreach ( self::NETWORK_TABLES as $table ) {
			$this->assertNotContains( $wpdb->base_prefix . $table, $dropped, 'The network turned it off.' );
		}

		$this->assertFalse( get_blog_option( get_main_site_id(), 'zero-spam-api-monitoring' ) );
		$this->assertSame( array( 'max_logs' => 500 ), get_blog_option( self::$sites[0], 'zero-spam-settings' ) );
		$this->assertNotFalse( get_site_option( 'zerospam_network_settings' ) );
		$this->assertSame( array( 'theme' => 'dark' ), get_user_meta( $user_id, 'zerospam_ui', true ) );
	}

	/**
	 * A locked network value wins over a site's own.
	 */
	public function test_a_locked_network_value_wins(): void {
		global $wpdb;

		$this->network_flag( 'enabled', true );
		update_blog_option( self::$sites[1], 'zero-spam-api-monitoring', array( 'delete_data_on_uninstall' => false ) );

		$dropped = $this->dropped_tables();

		foreach ( self::SITE_TABLES as $table ) {
			$this->assertContains( $wpdb->get_blog_prefix( self::$sites[1] ) . $table, $dropped );
		}
		foreach ( self::NETWORK_TABLES as $table ) {
			$this->assertContains( $wpdb->base_prefix . $table, $dropped );
		}
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
