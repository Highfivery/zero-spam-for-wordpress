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
	 * Lock option of the canonicalize_blocked_ips() migration.
	 *
	 * @var string
	 */
	const BLOCKED_IP_LOCK_OPTION = 'zerospam_migration_lock_canonical_blocked_ips';

	/**
	 * Option holding the last blocked_id canonicalize_blocked_ips() processed.
	 *
	 * @var string
	 */
	const BLOCKED_IP_CURSOR_OPTION = 'zerospam_migration_cursor_canonical_blocked_ips';

	/**
	 * Seconds after which a migration lock is considered stale.
	 *
	 * @var int
	 */
	const MIGRATION_LOCK_TTL = 300;

	/**
	 * Rows read per query by canonicalize_blocked_ips().
	 *
	 * @var int
	 */
	public int $blocked_ip_batch_size = 500;

	/**
	 * Rows canonicalize_blocked_ips() reads per request before resuming later.
	 *
	 * @var int
	 */
	public int $blocked_ip_row_budget = 2000;

	/**
	 * Seconds canonicalize_blocked_ips() spends per request before resuming later.
	 *
	 * @var float
	 */
	public float $blocked_ip_time_budget = 2.0;

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
			// Stays pending until an administrator loads the site, see Admin_Safe_IPs::migrate().
			'admin_safe_ip'                => array( \ZeroSpam\Core\Admin_Safe_IPs::class, 'migrate' ),
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
	 * matching that visitor.
	 *
	 * Only rows whose address contains a colon can be non-canonical. They are
	 * read in primary-key batches; each batch's colliding rows are found with
	 * one query and resolved in PHP (the longer-lasting block is kept), and
	 * rows are then updated or deleted by ID. The work is spread over requests:
	 * after a row or time budget the cursor is saved and the migration
	 * returns false, so it resumes on the next request and is only marked
	 * complete once the table has been fully processed. A lock keeps
	 * concurrent requests from doing the same work.
	 *
	 * @return bool True when done, false to resume on a later request.
	 */
	public function canonicalize_blocked_ips() {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['blocked'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			delete_option( self::BLOCKED_IP_CURSOR_OPTION );

			return true;
		}

		if ( ! self::acquire_lock( self::BLOCKED_IP_LOCK_OPTION ) ) {
			return false;
		}

		try {
			return $this->canonicalize_blocked_ip_batches( $table );
		} finally {
			delete_option( self::BLOCKED_IP_LOCK_OPTION );
		}
	}

	/**
	 * Processes batches of blocked rows until the table is done or the budget
	 * is spent.
	 *
	 * @param string $table Blocked table name.
	 * @return bool True when the table is done.
	 */
	private function canonicalize_blocked_ip_batches( string $table ): bool {
		global $wpdb;

		$cursor    = (int) get_option( self::BLOCKED_IP_CURSOR_OPTION, 0 );
		$started   = microtime( true );
		$processed = 0;

		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration, primary-key bounded batch.
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					'SELECT blocked_id, user_ip, blocked_type, end_block FROM %i WHERE blocked_id > %d AND user_ip LIKE %s ORDER BY blocked_id ASC LIMIT %d',
					$table,
					$cursor,
					'%' . $wpdb->esc_like( ':' ) . '%',
					$this->blocked_ip_batch_size
				),
				ARRAY_A
			);

			if ( $rows ) {
				$this->canonicalize_blocked_ip_rows( $table, $rows );

				$last       = end( $rows );
				$cursor     = (int) $last['blocked_id'];
				$processed += count( $rows );
			}

			if ( count( $rows ) < $this->blocked_ip_batch_size ) {
				delete_option( self::BLOCKED_IP_CURSOR_OPTION );

				return true;
			}

			if ( $processed >= $this->blocked_ip_row_budget || microtime( true ) - $started >= $this->blocked_ip_time_budget ) {
				update_option( self::BLOCKED_IP_CURSOR_OPTION, $cursor, false );

				return false;
			}
		}
	}

	/**
	 * Rewrites one batch of rows, merging rows that share an address.
	 *
	 * @param string                           $table Blocked table name.
	 * @param array<int, array<string, mixed>> $rows  Rows (blocked_id, user_ip, blocked_type, end_block).
	 */
	private function canonicalize_blocked_ip_rows( string $table, array $rows ): void {
		global $wpdb;

		// Non-canonical rows of this batch, by canonical address. PHP compares
		// case-sensitively, so a case-only difference counts as non-canonical.
		$groups = array();
		foreach ( $rows as $row ) {
			$canonical = \ZeroSpam\Core\User::normalize_ip( (string) $row['user_ip'] );
			if ( '' !== $canonical && $canonical !== $row['user_ip'] ) {
				$groups[ $canonical ][ (int) $row['blocked_id'] ] = $row;
			}
		}

		if ( ! $groups ) {
			return;
		}

		// Every other row of those addresses, in one query (MySQL's
		// case-insensitive collation also returns case-only variants).
		$addresses    = array_map( 'strval', array_keys( $groups ) );
		$placeholders = implode( ', ', array_fill( 0, count( $addresses ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-time migration; $placeholders only holds "%s" placeholders, one per bound address.
		$others = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT blocked_id, user_ip, blocked_type, end_block FROM %i WHERE user_ip IN ( {$placeholders} )",
				array_merge( array( $table ), $addresses )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		foreach ( $others as $row ) {
			$canonical = \ZeroSpam\Core\User::normalize_ip( (string) $row['user_ip'] );
			if ( isset( $groups[ $canonical ] ) ) {
				$groups[ $canonical ][ (int) $row['blocked_id'] ] = $row;
			}
		}

		foreach ( $groups as $canonical => $group ) {
			$canonical = (string) $canonical;

			// Keep the longer-lasting block; on a tie, a row already in
			// canonical form, then the oldest.
			uasort(
				$group,
				static function ( array $a, array $b ) use ( $canonical ): int {
					return array( self::block_ends_at( $b ), $b['user_ip'] === $canonical, - (int) $b['blocked_id'] )
						<=> array( self::block_ends_at( $a ), $a['user_ip'] === $canonical, - (int) $a['blocked_id'] );
				}
			);

			$keeper = reset( $group );
			$drop   = array_map( 'intval', array_slice( array_keys( $group ), 1 ) );

			if ( $drop ) {
				$ids = implode( ', ', array_fill( 0, count( $drop ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-time migration; $ids only holds "%d" placeholders, one per bound ID.
				$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE blocked_id IN ( {$ids} )", array_merge( array( $table ), $drop ) ) );
			}

			if ( $keeper['user_ip'] !== $canonical ) {
				$wpdb->update( $table, array( 'user_ip' => $canonical ), array( 'blocked_id' => (int) $keeper['blocked_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration, by primary key.
			}
		}
	}

	/**
	 * Takes a lock held in an option, so only one request runs a migration.
	 *
	 * The lock row is inserted with INSERT IGNORE rather than add_option(),
	 * which is not atomic (it checks, then inserts with ON DUPLICATE KEY
	 * UPDATE): with INSERT IGNORE exactly one
	 * request inserts it. A lock older than MIGRATION_LOCK_TTL is stale (its
	 * request died) and is replaced.
	 *
	 * @param string $name Lock option name.
	 * @return bool Whether this request holds the lock.
	 */
	private static function acquire_lock( string $name ): bool {
		global $wpdb;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; see the docblock.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
					$name,
					(string) time()
				)
			);

			if ( 1 === $inserted ) {
				wp_cache_delete( $name, 'options' );
				wp_cache_delete( 'notoptions', 'options' );

				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the lock without the options cache.
			$since = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

			if ( time() - (int) $since < self::MIGRATION_LOCK_TTL ) {
				return false;
			}

			// Stale: remove that lock only (another request may have replaced it), then try again.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-delete of a stale lock.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $since ) );
		}

		return false;
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
