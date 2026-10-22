<?php
/**
 * Migrations class
 *
 * Handles one-time data migrations that run exactly once per site.
 * Each migration is tracked by a unique key in the `zerospam_completed_migrations` option.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Migrations
 *
 * Lightweight migration runner that replaces the legacy Updates class.
 * Migrations are registered in order and each runs exactly once,
 * tracked by unique string keys.
 */
class Migrations {

	/**
	 * Option name for tracking completed migrations.
	 *
	 * @var string
	 */
	const COMPLETED_OPTION = 'zerospam_completed_migrations';

	/**
	 * Option name for the settings review admin notice.
	 *
	 * @var string
	 */
	const SETTINGS_REVIEW_NOTICE_OPTION = 'zerospam_show_settings_review_notice';

	/**
	 * Option holding when proxy-aware IP detection started on this site (site
	 * time, Y-m-d H:i:s, the format of the log's `date_recorded`).
	 *
	 * @var string
	 */
	const PROXY_DETECTION_SINCE_OPTION = 'zerospam_proxy_detection_since';

	/**
	 * Rows read per query by canonicalize_blocked_ips().
	 *
	 * @var int
	 */
	const BLOCKED_IP_BATCH_SIZE = 200;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'run' ) );
	}

	/**
	 * Run any pending migrations.
	 *
	 * Iterates through registered migrations in order, executing any
	 * that have not yet been marked as completed. Always updates the
	 * plugin version tracker after processing.
	 */
	public function run() {
		$completed  = $this->get_completed();
		$migrations = $this->get_migrations();
		$ran_any    = false;

		foreach ( $migrations as $key => $callable ) {
			if ( in_array( $key, $completed, true ) ) {
				continue;
			}

			$result = call_user_func( $callable );

			if ( false !== $result ) {
				$this->mark_completed( $key );
				$ran_any = true;
			}
		}

		// Always track the current plugin version, regardless of whether
		// migrations ran. This fixes the original bug where version tracking
		// was gated inside a conditional block.
		update_option( 'zero-spam-last-update', ZEROSPAM_VERSION );

		/**
		 * Fires after the plugin has been updated to a new version.
		 *
		 * Only fires when migrations were executed during this request.
		 *
		 * @param string $version The current plugin version.
		 */
		if ( $ran_any ) {
			do_action( 'zerospam_plugin_updated', ZEROSPAM_VERSION );
		}
	}

	/**
	 * Get the ordered list of registered migrations.
	 *
	 * Each migration is keyed by a unique string identifier and maps
	 * to a callable. Migrations run in the order they appear here.
	 * New migrations should be appended to the end of the array.
	 *
	 * @return array<string, callable> Associative array of migration key => callable.
	 */
	private function get_migrations() {
		return array(
			'legacy_options_to_modules_v5' => array( $this, 'migrate_legacy_options' ),
			'share_detection_cron_cleanup' => array( $this, 'remove_share_detection_cron_events' ),
			'davidwalsh_signed_tokens'     => array( '\\ZeroSpam\\Modules\\DavidWalsh\\DavidWalsh', 'start_legacy_grace_period' ),
			'geolocation_header_cache'     => array( $this, 'clear_geolocation_cache' ),
			'proxy_detection_since'        => array( $this, 'record_proxy_detection_since' ),
			'canonical_blocked_ips'        => array( $this, 'canonicalize_blocked_ips' ),
		);
	}

	/**
	 * Get the list of completed migration keys.
	 *
	 * @return array List of completed migration key strings.
	 */
	private function get_completed() {
		$completed = get_option( self::COMPLETED_OPTION, array() );

		return is_array( $completed ) ? $completed : array();
	}

	/**
	 * Mark a migration as completed.
	 *
	 * @param string $key The unique migration key.
	 */
	private function mark_completed( $key ) {
		$completed   = $this->get_completed();
		$completed[] = $key;

		update_option( self::COMPLETED_OPTION, array_unique( $completed ), true );
	}

	/**
	 * Remove the per-detection cron events scheduled before 5.7.10.
	 *
	 * Earlier versions scheduled a `zerospam_async_share_detection` event for
	 * every detection, with the raw detection data as its argument. On busy
	 * sites thousands of them built up in the autoloaded `cron` option.
	 * Detections are now queued and sent by a single event.
	 *
	 * @return true
	 */
	public function remove_share_detection_cron_events() {
		wp_unschedule_hook( 'zerospam_async_share_detection' );

		return true;
	}

	/**
	 * Clear cached geolocation data.
	 *
	 * Before 5.7.12 the country from `CF-IPCountry`/`X-Forwarded-Country` was
	 * trusted from any visitor and cached by IP for a week, so a visitor could
	 * pick the country cached for their IP.
	 *
	 * @return true
	 */
	public function clear_geolocation_cache() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_zerospam_geo_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_zerospam_geo_' ) . '%'
			)
		);

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'transient' );
		}

		return true;
	}

	/**
	 * Record when proxy-aware IP detection started.
	 *
	 * Before this version every visitor behind a CDN or proxy was logged with
	 * the proxy's address. The shared-IP warning (Admin\Shared_IP_Warning)
	 * only looks at detections logged after this moment, so those older rows
	 * (for example Cloudflare edge addresses) never trigger it. An existing
	 * value is kept.
	 *
	 * @return true
	 */
	public function record_proxy_detection_since() {
		add_option( self::PROXY_DETECTION_SINCE_OPTION, current_time( 'mysql' ), '', false );

		return true;
	}

	/**
	 * Rewrite blocked IPv6 addresses in canonical form.
	 *
	 * Visitor addresses are now reported in canonical form
	 * (User::normalize_ip(): lowercase, compressed IPv6, IPv4 for IPv4-mapped
	 * addresses) and blocks are looked up and stored that way. A block saved
	 * from a server that reported `2001:DB8:0:0:0:0:0:1` would otherwise stop
	 * matching that visitor. Rows are read in batches; when two rows end up
	 * with the same address, the longer-lasting block is kept.
	 *
	 * @return true
	 */
	public function canonicalize_blocked_ips() {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['blocked'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return true;
		}

		$last_id = 0;
		$deleted = array();

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration, bounded batch.
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE blocked_id > %d AND user_ip LIKE %s ORDER BY blocked_id ASC LIMIT %d',
					$table,
					$last_id,
					'%' . $wpdb->esc_like( ':' ) . '%',
					self::BLOCKED_IP_BATCH_SIZE
				),
				ARRAY_A
			);

			foreach ( $rows as $row ) {
				$id      = (int) $row['blocked_id'];
				$last_id = $id;

				if ( isset( $deleted[ $id ] ) ) {
					continue;
				}

				$canonical = \ZeroSpam\Core\User::normalize_ip( (string) $row['user_ip'] );
				if ( '' === $canonical || $canonical === $row['user_ip'] ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
				$existing = $wpdb->get_row(
					$wpdb->prepare( 'SELECT * FROM %i WHERE user_ip = %s AND blocked_id <> %d LIMIT 1', $table, $canonical, $id ),
					ARRAY_A
				);

				if ( $existing && self::block_ends_at( $existing ) >= self::block_ends_at( $row ) ) {
					// The other block lasts at least as long: drop this one.
					$wpdb->delete( $table, array( 'blocked_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
					$deleted[ $id ] = true;
					continue;
				}

				if ( $existing ) {
					$wpdb->delete( $table, array( 'blocked_id' => (int) $existing['blocked_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
					$deleted[ (int) $existing['blocked_id'] ] = true;
				}

				$wpdb->update( $table, array( 'user_ip' => $canonical ), array( 'blocked_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
			}

			$full_batch = self::BLOCKED_IP_BATCH_SIZE === count( $rows );
		} while ( $full_batch );

		return true;
	}

	/**
	 * When a block ends, for comparing two blocks of the same address.
	 *
	 * @param array<string, mixed> $block Blocked table row.
	 * @return int Unix time; PHP_INT_MAX for a permanent block, 0 for a
	 *             temporary block without an end date (it never applies).
	 */
	private static function block_ends_at( array $block ): int {
		if ( 'temporary' !== ( $block['blocked_type'] ?? '' ) ) {
			return PHP_INT_MAX;
		}

		return empty( $block['end_block'] ) ? 0 : (int) strtotime( (string) $block['end_block'] );
	}

	/**
	 * Migrate legacy `wpzerospam` option to per-module options.
	 *
	 * This is the one-time migration that replaces the broken logic in the
	 * old Updates class. The old code ran on every plugin update and overwrote
	 * current user settings with stale values from the legacy option.
	 *
	 * This migration:
	 * 1. Reads the legacy `wpzerospam` option.
	 * 2. For each module, merges legacy values into the current per-module
	 *    option — but only for keys that are MISSING from the current option.
	 *    Existing user settings are never overwritten.
	 * 3. Deletes the legacy option and flushes relevant caches.
	 * 4. Flags a one-time admin notice so users can verify their settings.
	 *
	 * @return bool True if migration ran (whether or not legacy data existed).
	 */
	public function migrate_legacy_options() {
		$old_settings = get_option( 'wpzerospam' );

		// No legacy option — nothing to migrate, but migration is complete.
		if ( ! $old_settings || ! is_array( $old_settings ) ) {
			return true;
		}

		$modules = \ZeroSpam\Core\Settings::get_settings_by_module();

		foreach ( $modules as $module => $settings ) {
			// Read the CURRENT per-module option — these are the user's live settings.
			$current_module_settings = get_option( "zero-spam-$module", array() );

			if ( ! is_array( $current_module_settings ) ) {
				$current_module_settings = array();
			}

			$updated = false;

			foreach ( $settings as $key => $attr ) {
				// Skip settings that already exist in the current module option.
				// This is the critical fix: never overwrite what the user has configured.
				if ( array_key_exists( $key, $current_module_settings ) ) {
					continue;
				}

				// Only migrate keys that exist and are non-empty in the legacy option.
				if ( ! empty( $old_settings[ $key ] ) ) {
					$current_module_settings[ $key ] = $old_settings[ $key ];
					$updated                         = true;
				}
			}

			if ( $updated ) {
				update_option( "zero-spam-$module", $current_module_settings, true );
			}
		}

		// Delete the legacy option — this closes the door permanently.
		delete_option( 'wpzerospam' );

		// Flush object caches to handle Redis/Memcached environments where
		// the deleted option could persist in memory.
		wp_cache_delete( 'wpzerospam', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// Flag a one-time admin notice so users can verify their settings.
		update_option( self::SETTINGS_REVIEW_NOTICE_OPTION, true, true );

		return true;
	}
}
