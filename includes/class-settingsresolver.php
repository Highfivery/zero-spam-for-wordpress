<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Settings_Resolver to this file name.
/**
 * Settings Resolver class
 *
 * Handles multisite-aware settings resolution with per-request caching.
 * Implements the precedence Network_Settings applies: locked network value >
 * site override > network default > plugin defaults.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

use ZeroSpam\Core\Settings_Schema;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Settings Resolver
 *
 * Centralized settings resolution for both admin UI and REST API.
 * Provides per-request caching to minimize database queries.
 *
 * Since 6.0.0 network values are read from and written to the network
 * settings store (`zerospam_network_settings`, see Network_Settings) only.
 * The `zero-spam-network-{module}` options used before are merged into it
 * once (Migrations::merge_network_store()) and no longer read or written.
 */
class Settings_Resolver {

	/**
	 * Per-request cache of resolved settings.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Get resolved settings for a module with multisite support.
	 *
	 * Precedence: locked network value > site override > network default >
	 * plugin defaults.
	 *
	 * @param string $module Module name (e.g., 'zerospam', 'settings').
	 * @param array  $defaults Optional. Default values for settings.
	 * @return array Resolved settings array.
	 */
	public static function get_resolved_settings( $module, $defaults = array() ) {
		$cache_key = self::get_cache_key( $module );

		// Return cached if available.
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$resolved = array();

		// Start with defaults.
		if ( ! empty( $defaults ) ) {
			$resolved = $defaults;
		}

		$network = self::get_network_values( $module );

		// Layer 2: Network defaults.
		$resolved = array_merge( $resolved, $network['values'] );

		// Layer 3: Site overrides.
		$site_settings = get_option( "zero-spam-$module", array() );
		if ( is_array( $site_settings ) ) {
			$resolved = array_merge( $resolved, $site_settings );
		}

		// Layer 4: Locked network values (highest priority).
		$resolved = array_merge( $resolved, array_intersect_key( $network['values'], array_flip( $network['locked'] ) ) );

		// Cache for this request.
		self::$cache[ $cache_key ] = $resolved;

		return $resolved;
	}

	/**
	 * Get resolved settings with source information.
	 *
	 * Returns settings along with metadata about where each value came from:
	 * `locked`, `site`, `network` or `default`.
	 *
	 * @param string $module Module name.
	 * @param array  $defaults Default values.
	 * @return array Array with 'values' and 'sources' keys.
	 */
	public static function get_resolved_with_sources( $module, $defaults = array() ) {
		$sources = array();
		$values  = array();

		// Get all layers.
		$network          = self::get_network_values( $module );
		$network_settings = $network['values'];
		$locked           = array_flip( $network['locked'] );

		$site_settings = get_option( "zero-spam-$module", array() );
		$site_settings = is_array( $site_settings ) ? $site_settings : array();

		// Determine source for each key.
		$all_keys = array_unique(
			array_merge(
				array_keys( $defaults ),
				array_keys( $network_settings ),
				array_keys( $site_settings )
			)
		);

		foreach ( $all_keys as $key ) {
			if ( isset( $locked[ $key ] ) ) {
				$values[ $key ]  = $network_settings[ $key ];
				$sources[ $key ] = 'locked';
			} elseif ( isset( $site_settings[ $key ] ) ) {
				$values[ $key ]  = $site_settings[ $key ];
				$sources[ $key ] = 'site';
			} elseif ( isset( $network_settings[ $key ] ) ) {
				$values[ $key ]  = $network_settings[ $key ];
				$sources[ $key ] = 'network';
			} elseif ( isset( $defaults[ $key ] ) ) {
				$values[ $key ]  = $defaults[ $key ];
				$sources[ $key ] = 'default';
			}
		}

		return array(
			'values'  => $values,
			'sources' => $sources,
		);
	}

	/**
	 * Update settings for a specific scope (site or network).
	 *
	 * Note: WordPress update_option/update_site_option returns false if the value
	 * hasn't changed. This is not an error - we consider it a success.
	 *
	 * A network update keeps each setting's lock, records who changed it and
	 * adds one network audit log entry per changed setting (secrets masked).
	 *
	 * @param string $module Module name.
	 * @param string $scope  Scope: 'site' or 'network'.
	 * @param array  $values Settings values to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update_settings( $module, $scope, $values ) {
		if ( 'network' === $scope ) {
			if ( ! is_multisite() ) {
				return false;
			}

			$stored  = get_site_option( Network_Settings::META_KEY, array() );
			$store   = Settings_Schema::normalize_network_store( $stored );
			$changed = array();

			foreach ( $values as $key => $value ) {
				$entry = isset( $store['settings'][ $key ] ) && is_array( $store['settings'][ $key ] ) ? $store['settings'][ $key ] : array();

				if ( array_key_exists( 'value', $entry ) && $entry['value'] === $value ) {
					continue;
				}

				$changed[ $key ] = array_key_exists( 'value', $entry ) ? $entry['value'] : null;

				$store['settings'][ $key ] = array_merge(
					$entry,
					array(
						'value'      => $value,
						'locked'     => ! empty( $entry['locked'] ),
						'updated_by' => get_current_user_id(),
						'updated_at' => current_time( 'mysql' ),
					)
				);
			}

			// No change, but this is success (idempotent).
			if ( ! $changed ) {
				return true;
			}

			$result = update_site_option( Network_Settings::META_KEY, $store );

			if ( $result ) {
				delete_site_transient( Network_Settings::CACHE_KEY );

				// Every network change is audited with the acting user, as the network settings screen does.
				$manager = new Network_Settings_Manager();
				foreach ( $changed as $key => $old_value ) {
					$manager->log_setting_change( (string) $key, self::audit_value( (string) $key, $old_value ), self::audit_value( (string) $key, $values[ $key ] ) );
				}
			}
		} else {
			// Site scope.
			$existing = get_option( "zero-spam-$module", array() );
			$updated  = array_merge( is_array( $existing ) ? $existing : array(), $values );

			// Check if values actually changed.
			if ( $existing === $updated ) {
				// No change, but this is success (idempotent).
				return true;
			}

			$result = update_option( "zero-spam-$module", $updated, true );

			// update_option() also returns false when the sanitized value equals the stored one, for
			// example when a network site's save filter drops a value equal to the network default.
			if ( ! $result ) {
				$stored = get_option( "zero-spam-$module", array() );
				$result = maybe_serialize( $stored ) === maybe_serialize( sanitize_option( "zero-spam-$module", $updated ) );
			}
		}

		// Invalidate cache on successful update.
		if ( $result ) {
			self::invalidate_cache( $module );
		}

		return $result;
	}

	/**
	 * Network values of a module's settings, and which of them are locked.
	 *
	 * Read from the network settings store; empty on a single site. Keys the
	 * settings registry does not know are left out.
	 *
	 * @param string $module Module name.
	 * @return array{values: array<string, mixed>, locked: string[]}
	 */
	public static function get_network_values( $module ) {
		$network = array(
			'values' => array(),
			'locked' => array(),
		);

		if ( ! is_multisite() ) {
			return $network;
		}

		$stored = get_site_transient( Network_Settings::CACHE_KEY );
		if ( false === $stored ) {
			$stored = get_site_option( Network_Settings::META_KEY, array() );
		}
		$store    = Settings_Schema::normalize_network_store( $stored );
		$registry = Settings_Schema::registry();

		foreach ( $store['settings'] as $key => $config ) {
			if ( ! is_array( $config ) || ! isset( $registry[ $key ]['module'] ) || $module !== $registry[ $key ]['module'] ) {
				continue;
			}

			// Locked whatever its value, as Network_Settings enforces it: a locked
			// null still overrides the site's value.
			if ( ! empty( $config['locked'] ) ) {
				$network['values'][ $key ] = $config['value'] ?? null;
				$network['locked'][]       = (string) $key;
				continue;
			}

			// An unlocked null value is no network value (the isset() test Network_Settings uses).
			if ( isset( $config['value'] ) ) {
				$network['values'][ $key ] = $config['value'];
			}
		}

		return $network;
	}

	/**
	 * A value as the network audit log records it: secrets masked.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	private static function audit_value( string $key, $value ) {
		if ( null === $value || ! Settings_Schema::is_secret( $key ) ) {
			return $value;
		}

		$value = is_scalar( $value ) && ! is_bool( $value ) ? trim( (string) $value ) : '';

		return '' === $value ? array( 'set' => false ) : array(
			'set'  => true,
			'hint' => Settings_Schema::hint( $value ),
		);
	}

	/**
	 * Invalidate cached settings for a module.
	 *
	 * @param string $module Module name.
	 */
	public static function invalidate_cache( $module ) {
		$cache_key = self::get_cache_key( $module );
		unset( self::$cache[ $cache_key ] );
	}

	/**
	 * Invalidate all cached settings.
	 */
	public static function invalidate_all() {
		self::$cache = array();
	}

	/**
	 * Get cache key for a module.
	 *
	 * Includes blog ID for multisite support.
	 *
	 * @param string $module Module name.
	 * @return string Cache key.
	 */
	private static function get_cache_key( $module ) {
		$blog_id = is_multisite() ? get_current_blog_id() : 0;
		return "module_{$module}_blog_{$blog_id}";
	}
}
