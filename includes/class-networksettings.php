<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Network_Settings to this file name.
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
	 * Name of the network settings cache (cache_key() is its key)
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
	 * Marks a list's entry in the cached network settings whose value was left
	 * in the store (see without_lists()).
	 *
	 * @since 6.0.1
	 */
	const VALUE_IN_STORE = 'value_in_store';

	/**
	 * A network cache's key, by network (since 6.0.0). Under a persistent
	 * object cache core keys site transients by name alone, so one network's
	 * values would answer for another (without one, they live in that
	 * network's sitemeta). On a single site, the name as it is.
	 *
	 * @param string $name Cache name.
	 */
	public static function network_key( string $name ): string {
		return is_multisite() ? $name . '_' . get_current_network_id() : $name;
	}

	/**
	 * The key of the network settings cache.
	 */
	public static function cache_key(): string {
		return self::network_key( self::CACHE_KEY );
	}

	/**
	 * The instance whose hooks are added (they are the same for every
	 * instance: the plugin, the manager, REST and CLI each create one).
	 *
	 * @since 6.0.1
	 *
	 * @var Network_Settings|null
	 */
	private static ?Network_Settings $hooked = null;

	/**
	 * Constructor. The hooks are added once per request, by the first
	 * instance, and again by a later one if they were removed since.
	 */
	public function __construct() {
		if ( null !== self::$hooked && false !== has_filter( 'zerospam_settings', array( self::$hooked, 'resolve_all_settings' ) ) ) {
			return;
		}
		self::$hooked = $this;

		add_filter( 'zerospam_settings', array( $this, 'resolve_all_settings' ), 100, 1 );
		add_action( 'update_site_option_' . self::META_KEY, array( $this, 'clear_cache' ) );
		add_action( 'add_site_option_' . self::META_KEY, array( $this, 'clear_cache' ) );
		add_action( 'delete_site_option_' . self::META_KEY, array( $this, 'clear_cache' ) );
		add_action( 'updated_option', array( $this, 'clear_site_cache_on_update' ), 10, 3 );
	}

	/**
	 * A setting's module, from the current site's registry without lists
	 * (built once per request): a setting belongs to the same module on
	 * every site, so per-site checks need not build the registry there.
	 *
	 * @since 6.0.1
	 *
	 * @param string $setting_key Setting key.
	 */
	private static function module_of( string $setting_key ): ?string {
		$module = \ZeroSpam\Core\Settings::get_settings_without_lists()[ $setting_key ]['module'] ?? null;

		return is_string( $module ) && '' !== $module ? $module : null;
	}

	/**
	 * Clear site cache when any zero-spam option is updated
	 *
	 * @param string $option    Name of the updated option.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	public function clear_site_cache_on_update( $option, $old_value, $value ) {
		unset( $old_value, $value ); // The updated_option action passes them.

		if ( 0 === strpos( $option, 'zero-spam-' ) || in_array( $option, \ZeroSpam\Core\Settings_Schema::OWN_OPTIONS, true ) ) {
			$this->clear_site_cache( get_current_blog_id() );
		}
	}

	/**
	 * Whether a setting is a list kept in an option of its own on each site
	 * (Settings_Schema::OWN_OPTIONS): the blocked email domains can be about
	 * 1.1 MB, and only a submitted form needs them (#404).
	 *
	 * @since 6.0.1
	 *
	 * @param string $setting_key Setting key.
	 */
	public static function is_list( string $setting_key ): bool {
		return isset( \ZeroSpam\Core\Settings_Schema::OWN_OPTIONS[ $setting_key ] );
	}

	/**
	 * The network store with each list's value left out, marked
	 * VALUE_IN_STORE instead: what the network settings cache holds, so that
	 * reading it on every request does not load the lists.
	 *
	 * @since 6.0.1
	 *
	 * @param array $store Network settings store (`settings` => key => config).
	 * @return array
	 */
	public static function without_lists( array $store ): array {
		if ( empty( $store['settings'] ) || ! is_array( $store['settings'] ) ) {
			return $store;
		}

		foreach ( $store['settings'] as $key => $config ) {
			if ( self::is_list( (string) $key ) && is_array( $config ) && array_key_exists( 'value', $config ) ) {
				unset( $store['settings'][ $key ]['value'] );
				$store['settings'][ $key ][ self::VALUE_IN_STORE ] = true;
			}
		}

		return $store;
	}

	/**
	 * A list's network value, read from the store (not the cache).
	 *
	 * @since 6.0.1
	 *
	 * @param string $setting_key Setting key.
	 * @return mixed The value, or null when the network does not set it.
	 */
	public static function list_value( string $setting_key ): mixed {
		$store = get_site_option( self::META_KEY, array() );
		if ( is_array( $store ) && ! isset( $store['settings'] ) ) {
			$store = array( 'settings' => $store );
		}

		return is_array( $store ) && isset( $store['settings'][ $setting_key ] ) && is_array( $store['settings'][ $setting_key ] )
			? ( $store['settings'][ $setting_key ]['value'] ?? null )
			: null;
	}

	/**
	 * Fills in the values the cache left in the store. A list the store no
	 * longer holds (a cache older than a change) reads as null, as a missing
	 * network value does elsewhere.
	 *
	 * @since 6.0.1
	 *
	 * @param array $store Network settings, possibly from the cache.
	 * @return array
	 */
	public static function with_lists( array $store ): array {
		if ( empty( $store['settings'] ) || ! is_array( $store['settings'] ) ) {
			return $store;
		}

		$raw = null;
		foreach ( $store['settings'] as $key => $config ) {
			if ( is_array( $config ) && ! empty( $config[ self::VALUE_IN_STORE ] ) ) {
				if ( null === $raw ) {
					$raw = \ZeroSpam\Core\Settings_Schema::normalize_network_store( get_site_option( self::META_KEY, array() ) );
				}
				unset( $store['settings'][ $key ][ self::VALUE_IN_STORE ] );
				$store['settings'][ $key ]['value'] = $raw['settings'][ $key ]['value'] ?? null;
			}
		}

		return $store;
	}

	/**
	 * The current site's own list (is_list()), or null when it stores none.
	 *
	 * Lists live in an option of their own (Settings_Schema::OWN_OPTIONS),
	 * not in the module option. An empty stored list (left by old saves)
	 * reads as none, so the site keeps following the network's list.
	 * The 5.x copy in `zero-spam-settings` is ignored here: an old copy
	 * follows the network too.
	 *
	 * @param string $setting_key List setting key.
	 * @return string|null
	 */
	public static function own_list( string $setting_key ): ?string {
		if ( ! self::is_list( $setting_key ) ) {
			return null;
		}

		$own = get_option( \ZeroSpam\Core\Settings_Schema::OWN_OPTIONS[ $setting_key ], false );

		return is_string( $own ) && '' !== trim( $own ) ? $own : null;
	}

	/**
	 * The MD5 of the current site's own list, read in the database so the
	 * list (up to about 1.1 MB) is neither loaded into PHP nor kept in the
	 * object cache: the network admin compares every site's list with the
	 * network's. Null when the site stores none (or only whitespace).
	 *
	 * @param string $setting_key List setting key.
	 * @return string|null
	 */
	private static function own_list_md5( string $setting_key ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deliberate: get_option() would load and cache a list of up to 1.1 MB for every site of the network; only its digest is needed, once.
		$digest = $wpdb->get_var(
			$wpdb->prepare(
				// Whitespace only (spaces, tabs, line breaks) reads as no list, as in own_list().
				"SELECT MD5(option_value) FROM %i WHERE option_name = %s AND REPLACE(REPLACE(REPLACE(REPLACE(option_value, ' ', ''), CHAR(9), ''), CHAR(10), ''), CHAR(13), '') <> ''",
				$wpdb->options,
				\ZeroSpam\Core\Settings_Schema::OWN_OPTIONS[ $setting_key ]
			)
		);

		return is_string( $digest ) && '' !== $digest ? $digest : null;
	}

	/**
	 * Whether the current site stores its own value for a setting (a site
	 * override), and that value.
	 *
	 * A list (is_list()) is stored in its own option, not in the module
	 * option; since 6.0.1 a site's own list overrides an unlocked network
	 * list, as every other setting does. An empty stored list (left by old
	 * saves) is not counted, so the site keeps the network's list.
	 *
	 * @param string      $setting_key Setting key.
	 * @param string|null $module      The setting's module.
	 * @return array{0: bool, 1: mixed}
	 */
	private static function site_value( string $setting_key, ?string $module ): array {
		if ( self::is_list( $setting_key ) ) {
			$own = self::own_list( $setting_key );

			return null === $own ? array( false, null ) : array( true, $own );
		}

		if ( ! $module ) {
			return array( false, null );
		}

		$module_settings = get_option( "zero-spam-{$module}", array() );

		return is_array( $module_settings ) && isset( $module_settings[ $setting_key ] )
			? array( true, $module_settings[ $setting_key ] )
			: array( false, null );
	}

	/**
	 * Resolve ALL settings using network hierarchy
	 *
	 * Lists (is_list()) are resolved only when the registry carries their
	 * value: the registry without lists (Settings::get_settings_without_lists(),
	 * every page view) leaves them out, so the network's list is not loaded,
	 * and the per-setting hooks do not fire for them on those builds. Since
	 * 6.0.1 `zerospam_network_settings_applied` receives the cached network
	 * settings, whose lists carry VALUE_IN_STORE instead of their value.
	 *
	 * @param array $settings All plugin settings.
	 * @return array Modified settings with network values applied.
	 */
	public function resolve_all_settings( $settings ) {
		if ( ! is_multisite() ) {
			return $settings;
		}

		$network_settings = $this->get_network_settings_without_lists();

		if ( empty( $network_settings['settings'] ) ) {
			return $settings;
		}

		foreach ( $settings as $setting_key => $setting_config ) {
			if ( empty( $setting_config['module'] ) ) {
				continue;
			}

			if ( ! isset( $network_settings['settings'][ $setting_key ] ) ) {
				continue;
			}

			$network_config = $network_settings['settings'][ $setting_key ];
			$original_value = $setting_config['value'] ?? null;

			if ( self::is_list( (string) $setting_key ) ) {
				if ( ! array_key_exists( 'value', (array) $setting_config ) ) {
					continue; // A registry without lists: leave the list alone.
				}
				if ( ! empty( $network_config[ self::VALUE_IN_STORE ] ) ) {
					// Null when the store no longer holds it, as a missing value reads elsewhere.
					$network_config['value'] = self::list_value( (string) $setting_key );
				}
			}

			// Level 1: Network Enforced (locked).
			if ( ! empty( $network_config['locked'] ) ) {
				$settings[ $setting_key ]['value'] = $network_config['value'];
				do_action( 'zerospam_network_setting_enforced', $setting_key, $network_config['value'], $original_value, $network_config );
				continue;
			}

			// Level 2: Site Override (if exists and not locked).
			if ( ! is_network_admin() ) {
				list( $has_own, $own ) = self::site_value( (string) $setting_key, $setting_config['module'] ?? null );
				// A site's own list identical to the network's follows the network.
				if ( $has_own && self::is_list( (string) $setting_key ) && ( $network_config['value'] ?? null ) === $own ) {
					$has_own = false;
				}
				if ( $has_own ) {
					do_action( 'zerospam_network_setting_overridden', $setting_key, $original_value, $network_config['value'] ?? null, get_current_blog_id() );
					continue;
				}
			}

			// Level 3: Network Default (if no site override).
			if ( isset( $network_config['value'] ) ) {
				$settings[ $setting_key ]['value'] = $network_config['value'];
				do_action( 'zerospam_network_setting_default', $setting_key, $network_config['value'], $original_value );
			}
		}

		do_action( 'zerospam_network_settings_applied', $settings, $network_settings );

		return $settings;
	}

	/**
	 * Get all network settings, lists included.
	 *
	 * @return array Network settings.
	 */
	public function get_network_settings() {
		return self::with_lists( $this->get_network_settings_without_lists() );
	}

	/**
	 * The network settings without the lists' values (see without_lists()):
	 * the cached copy, for code that runs on every request. A list's entry
	 * keeps `locked` and is marked VALUE_IN_STORE.
	 *
	 * @since 6.0.1
	 *
	 * @return array Network settings.
	 */
	public function get_network_settings_without_lists() {
		return self::cached_without_lists();
	}

	/**
	 * The cached network settings without the lists' values, for code that
	 * does not hold a Network_Settings instance (see
	 * get_network_settings_without_lists()).
	 *
	 * @since 6.0.1
	 *
	 * @return array Network settings.
	 */
	public static function cached_without_lists(): array {
		if ( ! is_multisite() ) {
			return array(
				'settings' => array(),
			);
		}

		// Try cache first. A cache written before 6.0.1 still holds the lists'
		// values (or is not in the `settings` shape): build it again from the
		// store rather than write an old copy back.
		$cached = get_site_transient( self::cache_key() );
		if ( is_array( $cached ) ) {
			if ( isset( $cached['settings'] ) && is_array( $cached['settings'] ) && self::without_lists( $cached ) === $cached ) {
				return $cached;
			}
			delete_site_transient( self::cache_key() );
		}

		// Get from database.
		$settings = get_site_option( self::META_KEY, array() );

		// Ensure proper structure.
		if ( ! isset( $settings['settings'] ) ) {
			$settings = array(
				'settings' => isset( $settings ) && is_array( $settings ) ? $settings : array(),
			);
		}

		$settings = self::without_lists( $settings );

		// Cache it.
		set_site_transient( self::cache_key(), $settings, self::CACHE_DURATION );

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

		$network_settings = $this->get_network_settings_without_lists();

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

		// Check if network has a default for this setting.
		$network_default = $this->get_network_default( $setting_key );
		if ( null === $network_default ) {
			// No network setting configured, so not using a "default".
			return false;
		}

		// Check if site has override in module-specific option.
		$module = self::module_of( (string) $setting_key );
		switch_to_blog( $site_id );

		if ( self::is_list( (string) $setting_key ) ) {
			// Compared in the database: this runs for every site of the network.
			$digest       = self::own_list_md5( (string) $setting_key );
			$has_override = null !== $digest && md5( (string) $network_default ) !== $digest;
		} else {
			// Site has an override if it stores a value AND the value differs from network default.
			list( $has_own, $site_value ) = self::site_value( (string) $setting_key, $module );
			$has_override                 = $has_own && $site_value !== $network_default;
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

		$network_settings = $this->get_network_settings_without_lists();

		if ( ! isset( $network_settings['settings'][ $setting_key ] ) ) {
			return null;
		}

		if ( ! empty( $network_settings['settings'][ $setting_key ][ self::VALUE_IN_STORE ] ) ) {
			return self::list_value( (string) $setting_key );
		}

		return $network_settings['settings'][ $setting_key ]['value'] ?? null;
	}

	/**
	 * Get site override value for a setting
	 *
	 * @param string $setting_key Setting key.
	 * @param int    $site_id     Site ID (default current site).
	 * @return mixed Site override value or null if no override or matches network.
	 */
	public function get_site_override( $setting_key, $site_id = 0 ) {
		if ( ! is_multisite() ) {
			return null;
		}

		if ( 0 === $site_id ) {
			$site_id = get_current_blog_id();
		}

		// Get network default.
		$network_default = $this->get_network_default( $setting_key );

		$module = self::module_of( (string) $setting_key );
		switch_to_blog( $site_id );

		$override_value = null;

		list( $has_own, $site_value ) = self::site_value( (string) $setting_key, $module );
		if ( $has_own ) {
			// Only return as override if it DIFFERS from network default.
			if ( $site_value !== $network_default ) {
				$override_value = $site_value;
			}
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

		$sites         = get_sites(
			array(
				'number'     => 0,
				'network_id' => get_current_network_id(),
			)
		);
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

		$sites     = get_sites(
			array(
				'number'     => 0,
				'network_id' => get_current_network_id(),
			)
		);
		$overrides = array();

		foreach ( $sites as $site ) {
			if ( ! $this->is_using_default( $setting_key, $site->blog_id ) ) {
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

		$sites         = get_sites(
			array(
				'number'     => 0,
				'network_id' => get_current_network_id(),
			)
		);
		$total_sites   = count( $sites );
		$using_default = 0;
		foreach ( $sites as $site ) {
			if ( $this->is_using_default( $setting_key, (int) $site->blog_id ) ) {
				++$using_default;
			}
		}
		$overridden = $total_sites - $using_default;

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
		delete_site_transient( self::cache_key() );

		// Clear all site-level caches too.
		if ( is_multisite() ) {
			$sites = get_sites(
				array(
					'number'     => 100,
					'network_id' => get_current_network_id(),
				)
			);
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

			$all_settings = \ZeroSpam\Core\Settings::get_settings_without_lists();
			$module       = $all_settings[ $setting_key ]['module'] ?? null;
			$value        = null;

			if ( $module ) {
				$module_settings = get_option( "zero-spam-{$module}", array() );
				$value           = $module_settings[ $setting_key ] ?? null;
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
