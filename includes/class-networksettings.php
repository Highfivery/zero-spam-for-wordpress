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
		// Hook into settings retrieval - use priority 100 to run after modules (priority 10)
		// but allow other plugins/themes to override with priority > 100.
		add_filter( 'zerospam_settings', array( $this, 'resolve_all_settings' ), 100, 1 );
		
		// Clear cache when network settings are updated.
		add_action( 'update_site_option_' . self::META_KEY, array( $this, 'clear_cache' ) );
		
		// Clear cache when site settings are updated (any module).
		add_action( 'updated_option', array( $this, 'clear_site_cache_on_update' ), 10, 3 );
	}

	/**
	 * Clear site cache when any zero-spam option is updated
	 *
	 * @param string $option    Name of the updated option.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	public function clear_site_cache_on_update( $option, $old_value, $value ) {
		if ( 0 === strpos( $option, 'zero-spam-' ) ) {
			$this->clear_site_cache( get_current_blog_id() );
		}
	}

	/**
	 * Resolve ALL settings using network hierarchy
	 *
	 * This filter runs AFTER all modules have built their settings arrays (priority 100),
	 * allowing us to override the 'value' key based on network settings hierarchy.
	 * Other plugins/themes can still override with priority > 100.
	 *
	 * Priority architecture:
	 * - 10: Modules register their settings (default WordPress filter priority)
	 * - 100: Network settings applies management layer (this method)
	 * - 200+: Other plugins/themes can override (extensibility)
	 *
	 * @param array $settings All plugin settings.
	 * @return array Modified settings with network values applied.
	 */
	public function resolve_all_settings( $settings ) {
		// Only apply to multisite.
		if ( ! is_multisite() ) {
			return $settings;
		}

		// Get network settings.
		$network_settings = $this->get_network_settings();

		if ( empty( $network_settings['settings'] ) ) {
			return $settings;
		}

		// Loop through each setting and apply network hierarchy.
		foreach ( $settings as $setting_key => $setting_config ) {
			// Skip if not a real setting (e.g., HTML blocks).
			if ( empty( $setting_config['module'] ) ) {
				continue;
			}

			// Check if this setting has a network configuration.
			if ( ! isset( $network_settings['settings'][ $setting_key ] ) ) {
				continue;
			}

			$network_config = $network_settings['settings'][ $setting_key ];
			$original_value = $setting_config['value'] ?? null;

			// Level 1: Network Enforced (locked).
			if ( ! empty( $network_config['locked'] ) ) {
				$settings[ $setting_key ]['value'] = $network_config['value'];
				
				/**
				 * Fires when a network setting is enforced (locked).
				 * 
				 * This allows other plugins to react to locked settings.
				 * Note: Do NOT modify the value here if respecting the lock.
				 * Use priority > 100 on 'zerospam_settings' filter to override.
				 *
				 * @param string $setting_key   The setting key being locked.
				 * @param mixed  $network_value The enforced network value.
				 * @param mixed  $original_value The original value before override.
				 * @param array  $network_config Full network configuration for this setting.
				 */
				do_action( 'zerospam_network_setting_enforced', $setting_key, $network_config['value'], $original_value, $network_config );
				continue;
			}

			// Level 2: Site Override (if exists and not locked).
			// The module already loaded the site option into 'value' at priority 10.
			// We need to check if that value is a site override or just the plugin default.
			if ( ! is_network_admin() ) {
				$module = $setting_config['module'] ?? null;
				
				if ( $module ) {
					// Check module-specific option for site override.
					$module_settings = get_option( "zero-spam-{$module}", array() );
					if ( isset( $module_settings[ $setting_key ] ) ) {
						// Site HAS an override - the module already loaded it into 'value'.
						// Don't overwrite it with the network default!
						
						/**
						 * Fires when a site override is being used.
						 *
						 * @param string $setting_key    The setting key with an override.
						 * @param mixed  $site_value     The site's override value.
						 * @param mixed  $network_value  The network default value.
						 * @param int    $site_id        The current site ID.
						 */
						do_action( 'zerospam_network_setting_overridden', $setting_key, $original_value, $network_config['value'], get_current_blog_id() );
						continue; // Keep the site override, don't apply network default
					}
				}
			}

			// Level 3: Network Default (if no site override).
			// Site does NOT have an override, so apply network default.
			if ( isset( $network_config['value'] ) ) {
				$settings[ $setting_key ]['value'] = $network_config['value'];
				
				/**
				 * Fires when a network default is being applied.
				 *
				 * @param string $setting_key   The setting key using network default.
				 * @param mixed  $network_value The network default value.
				 * @param mixed  $plugin_default The plugin's default value.
				 */
				do_action( 'zerospam_network_setting_default', $setting_key, $network_config['value'], $original_value );
			}

			// Level 4: Plugin Default (already set in the $settings array).
		}

		/**
		 * Filter the settings after network hierarchy has been applied.
		 * 
		 * This runs at priority 100, allowing other plugins/themes to:
		 * 1. Override network settings (use priority > 100 on 'zerospam_settings')
		 * 2. React to network settings (use this action hook)
		 * 3. Log/monitor network setting enforcement
		 *
		 * @param array $settings         Settings after network hierarchy applied.
		 * @param array $network_settings Complete network settings configuration.
		 */
		do_action( 'zerospam_network_settings_applied', $settings, $network_settings );

		return $settings;
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

		// Check if site has override in module-specific option.
		switch_to_blog( $site_id );
		
		$all_settings = \ZeroSpam\Core\Settings::get_settings();
		$module = $all_settings[ $setting_key ]['module'] ?? null;
		$has_override = false;
		
		if ( $module ) {
			$module_settings = get_option( "zero-spam-{$module}", array() );
			$has_override = isset( $module_settings[ $setting_key ] );
		}
		
		restore_current_blog();

		return ! $has_override;
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
		
		$all_settings = \ZeroSpam\Core\Settings::get_settings();
		$module = $all_settings[ $setting_key ]['module'] ?? null;
		$override_value = null;
		
		if ( $module ) {
			$module_settings = get_option( "zero-spam-{$module}", array() );
			$override_value = $module_settings[ $setting_key ] ?? null;
		}
		
		restore_current_blog();

		return $override_value;
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
			
			$all_settings = \ZeroSpam\Core\Settings::get_settings();
			$module = $all_settings[ $setting_key ]['module'] ?? null;
			$value = null;
			
			if ( $module ) {
				$module_settings = get_option( "zero-spam-{$module}", array() );
				$value = $module_settings[ $setting_key ] ?? null;
			}
			
			restore_current_blog();

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
