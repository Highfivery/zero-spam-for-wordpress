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
