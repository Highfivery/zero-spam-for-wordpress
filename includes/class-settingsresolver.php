<?php
/**
 * Settings Resolver class
 *
 * Handles multisite-aware settings resolution with per-request caching.
 * Implements precedence: site override > network default > plugin defaults.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Settings Resolver
 *
 * Centralized settings resolution for both admin UI and REST API.
 * Provides per-request caching to minimize database queries.
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
	 * Precedence: site override > network default > plugin defaults.
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

		// Layer 2: Network defaults (if multisite and network-active).
		if ( is_multisite() && self::is_network_active() ) {
			$network_settings = get_site_option( "zero-spam-network-$module", array() );
			if ( is_array( $network_settings ) ) {
				$resolved = array_merge( $resolved, $network_settings );
			}
		}

		// Layer 3: Site overrides (highest priority).
		$site_settings = get_option( "zero-spam-$module", array() );
		if ( is_array( $site_settings ) ) {
			$resolved = array_merge( $resolved, $site_settings );
		}

		// Cache for this request.
		self::$cache[ $cache_key ] = $resolved;

		return $resolved;
	}

	/**
	 * Get resolved settings with source information.
	 *
	 * Returns settings along with metadata about where each value came from.
	 *
	 * @param string $module Module name.
	 * @param array  $defaults Default values.
	 * @return array Array with 'values' and 'sources' keys.
	 */
	public static function get_resolved_with_sources( $module, $defaults = array() ) {
		$sources = array();
		$values  = array();

		// Get all layers.
		$network_settings = array();
		if ( is_multisite() && self::is_network_active() ) {
			$network_settings = get_site_option( "zero-spam-network-$module", array() );
		}

		$site_settings = get_option( "zero-spam-$module", array() );

		// Determine source for each key.
		$all_keys = array_unique(
			array_merge(
				array_keys( $defaults ),
				array_keys( $network_settings ),
				array_keys( $site_settings )
			)
		);

		foreach ( $all_keys as $key ) {
			if ( isset( $site_settings[ $key ] ) ) {
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

			// Get existing network settings.
			$existing = get_site_option( "zero-spam-network-$module", array() );
			$updated  = array_merge( is_array( $existing ) ? $existing : array(), $values );

			// Check if values actually changed.
			if ( $existing === $updated ) {
				// No change, but this is success (idempotent).
				return true;
			}

			$result = update_site_option( "zero-spam-network-$module", $updated );
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
		}

		// Invalidate cache on successful update.
		if ( $result ) {
			self::invalidate_cache( $module );
		}

		return $result;
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

	/**
	 * Check if plugin is network active.
	 *
	 * @return bool True if network active.
	 */
	private static function is_network_active() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( ZEROSPAM_PLUGIN_BASE );
	}
}
