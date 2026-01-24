<?php
/**
 * Network Settings - Core hierarchy resolution
 *
 * Resolves settings hierarchy:
 * 1. Network Enforced (locked by network admin)
 * 2. Site Override (set by site admin if not locked)
 * 3. Network Default (set by network admin as default)
 * 4. Plugin Default (hardcoded fallback)
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Settings class
 */
class Network_Settings {

	/**
	 * Cache key for network settings
	 */
	const CACHE_KEY = 'zerospam_network_settings';

	/**
	 * Cache duration (1 hour)
	 */
	const CACHE_DURATION = HOUR_IN_SECONDS;

	/**
	 * Network settings meta key
	 */
	const META_KEY = 'zerospam_network_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into settings retrieval.
		add_filter( 'zerospam_setting', array( $this, 'resolve_setting' ), 10, 3 );
		
		// Clear cache when network settings are updated.
		add_action( 'update_site_option_' . self::META_KEY, array( $this, 'clear_cache' ) );
		
		// Clear cache when site settings are updated.
		add_action( 'update_option_zero-spam-settings', array( $this, 'clear_site_cache' ) );
	}

	/**
	 * Resolve a single setting using hierarchy
	 *
	 * @param mixed  $value       Current value.
	 * @param string $key         Setting key.
	 * @param string $setting_key Full setting key with prefix.
	 * @return mixed Resolved value.
	 */
	public function resolve_setting( $value, $key, $setting_key ) {
		// Only apply to multisite.
		if ( ! is_multisite() ) {
			return $value;
		}

		// Get network settings.
		$network_settings = $this->get_network_settings();

		// Check if this setting exists in network config.
		if ( ! isset( $network_settings['settings'][ $setting_key ] ) ) {
			return $value;
		}

		$network_config = $network_settings['settings'][ $setting_key ];

		// Level 1: Network Enforced (locked).
		if ( ! empty( $network_config['locked'] ) ) {
			return $network_config['value'];
		}

		// Level 2: Site Override (if exists and not locked).
		if ( ! is_network_admin() ) {
			$site_settings = get_option( 'zero-spam-settings', array() );
			if ( isset( $site_settings[ $setting_key ] ) ) {
				return $site_settings[ $setting_key ];
			}
		}

		// Level 3: Network Default.
		if ( isset( $network_config['value'] ) ) {
			return $network_config['value'];
		}

		// Level 4: Plugin Default (passed in $value).
		return $value;
	}

	/**
	 * Get all network settings
	 *
	 * @return array Network settings.
	 */
	public function get_network_settings() {
		if ( ! is_multisite() ) {
			return array(
				'settings' => array(),
			);
		}

		// Try cache first.
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		// Get from database.
		$settings = get_site_option( self::META_KEY, array() );

		// Ensure proper structure.
		if ( ! isset( $settings['settings'] ) ) {
			$settings = array(
				'settings' => isset( $settings ) && is_array( $settings ) ? $settings : array(),
			);
		}

		// Cache it.
		set_site_transient( self::CACHE_KEY, $settings, self::CACHE_DURATION );

		return $settings;
	}

	/**
	 * Check if a setting is locked by network admin
	 *
	 * @param string $setting_key Setting key.
	 * @return bool True if locked.
	 */
	public function is_locked( $setting_key ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$network_settings = $this->get_network_settings();

		if ( ! isset( $network_settings['settings'][ $setting_key ] ) ) {
			return false;
		}

		return ! empty( $network_settings['settings'][ $setting_key ]['locked'] );
	}

	/**
	 * Check if a setting is using network default (not overridden)
	 *
	 * @param string $setting_key Setting key.
	 * @param int    $site_id     Site ID (default current site).
	 * @return bool True if using default.
	 */
	public function is_using_default( $setting_key, $site_id = 0 ) {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( 0 === $site_id ) {
			$site_id = get_current_blog_id();
		}

		// If locked, always using network value.
		if ( $this->is_locked( $setting_key ) ) {
			return true;
		}

		// Check if site has override.
		switch_to_blog( $site_id );
		$site_settings = get_option( 'zero-spam-settings', array() );
		restore_current_blog();

		return ! isset( $site_settings[ $setting_key ] );
	}

	/**
	 * Get network default value for a setting
	 *
	 * @param string $setting_key Setting key.
	 * @return mixed Network default value or null.
	 */
	public function get_network_default( $setting_key ) {
		if ( ! is_multisite() ) {
			return null;
		}

		$network_settings = $this->get_network_settings();

		if ( ! isset( $network_settings['settings'][ $setting_key ] ) ) {
			return null;
		}

		return $network_settings['settings'][ $setting_key ]['value'] ?? null;
	}

	/**
	 * Get site override value for a setting
	 *
	 * @param string $setting_key Setting key.
	 * @param int    $site_id     Site ID (default current site).
	 * @return mixed Site override value or null.
	 */
	public function get_site_override( $setting_key, $site_id = 0 ) {
		if ( ! is_multisite() ) {
			return null;
		}

		if ( 0 === $site_id ) {
			$site_id = get_current_blog_id();
		}

		switch_to_blog( $site_id );
		$site_settings = get_option( 'zero-spam-settings', array() );
		restore_current_blog();

		return $site_settings[ $setting_key ] ?? null;
	}

	/**
	 * Get sites using default for a setting
	 *
	 * @param string $setting_key Setting key.
	 * @return array Array of site IDs using default.
	 */
	public function get_sites_using_default( $setting_key ) {
		if ( ! is_multisite() ) {
			return array();
		}

		$sites         = get_sites( array( 'number' => 0 ) );
		$using_default = array();

		foreach ( $sites as $site ) {
			if ( $this->is_using_default( $setting_key, $site->blog_id ) ) {
				$using_default[] = $site->blog_id;
			}
		}

		return $using_default;
	}

	/**
	 * Get sites with overrides for a setting
	 *
	 * @param string $setting_key Setting key.
	 * @return array Array of site IDs with overrides.
	 */
	public function get_sites_with_overrides( $setting_key ) {
		if ( ! is_multisite() ) {
			return array();
		}

		$sites     = get_sites( array( 'number' => 0 ) );
		$overrides = array();

		foreach ( $sites as $site ) {
			if ( ! $this->is_using_default( $setting_key, $site->blog_id ) && ! $this->is_locked( $setting_key ) ) {
				$overrides[] = $site->blog_id;
			}
		}

		return $overrides;
	}

	/**
	 * Get application status for a setting
	 *
	 * @param string $setting_key Setting key.
	 * @return array Status array with counts.
	 */
	public function get_application_status( $setting_key ) {
		if ( ! is_multisite() ) {
			return array(
				'total_sites'   => 0,
				'using_default' => 0,
				'overridden'    => 0,
				'locked'        => false,
			);
		}

		$sites         = get_sites( array( 'number' => 0 ) );
		$total_sites   = count( $sites );
		$using_default = count( $this->get_sites_using_default( $setting_key ) );
		$overridden    = $total_sites - $using_default;

		return array(
			'total_sites'   => $total_sites,
			'using_default' => $using_default,
			'overridden'    => $overridden,
			'locked'        => $this->is_locked( $setting_key ),
		);
	}

	/**
	 * Clear network settings cache
	 */
	public function clear_cache() {
		delete_site_transient( self::CACHE_KEY );
		
		// Clear all site-level caches too.
		if ( is_multisite() ) {
			$sites = get_sites( array( 'number' => 100 ) );
			foreach ( $sites as $site ) {
				$cache_key = 'zerospam_site_settings_' . $site->blog_id;
				delete_transient( $cache_key );
			}
		}
	}

	/**
	 * Clear site-specific cache
	 *
	 * @param int $site_id Site ID.
	 */
	public function clear_site_cache( $site_id = 0 ) {
		if ( 0 === $site_id ) {
			$site_id = get_current_blog_id();
		}

		$cache_key = 'zerospam_site_settings_' . $site_id;
		delete_transient( $cache_key );
	}

	/**
	 * Get effective value for a setting (what's actually being used)
	 *
	 * @param string $setting_key Setting key.
	 * @param int    $site_id     Site ID (default current site).
	 * @return array Array with 'value', 'source' (locked/override/default/plugin).
	 */
	public function get_effective_value( $setting_key, $site_id = 0 ) {
		if ( 0 === $site_id ) {
			$site_id = get_current_blog_id();
		}

		if ( ! is_multisite() ) {
			switch_to_blog( $site_id );
			$site_settings = get_option( 'zero-spam-settings', array() );
			restore_current_blog();

			$value = $site_settings[ $setting_key ] ?? null;

			return array(
				'value'  => $value,
				'source' => $value ? 'site' : 'plugin',
			);
		}

		// Check locked.
		if ( $this->is_locked( $setting_key ) ) {
			return array(
				'value'  => $this->get_network_default( $setting_key ),
				'source' => 'locked',
			);
		}

		// Check site override.
		$override = $this->get_site_override( $setting_key, $site_id );
		if ( null !== $override ) {
			return array(
				'value'  => $override,
				'source' => 'override',
			);
		}

		// Check network default.
		$network_default = $this->get_network_default( $setting_key );
		if ( null !== $network_default ) {
			return array(
				'value'  => $network_default,
				'source' => 'default',
			);
		}

		// Plugin default.
		return array(
			'value'  => null,
			'source' => 'plugin',
		);
	}
}
