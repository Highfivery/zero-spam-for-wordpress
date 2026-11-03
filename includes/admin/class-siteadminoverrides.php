<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Site_Admin_Overrides to this file name.
/**
 * Site Admin Overrides
 *
 * On a network site: keeps locked or unchanged settings out of what the
 * site stores, whichever path writes them (REST, WP-CLI, imports). The 6.0
 * admin shows locks and network values from the settings schema; the 5.x
 * settings page's lock badges and notices were removed in 6.0.0.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Site Admin Overrides class
 */
class Site_Admin_Overrides {

	/**
	 * Network Settings instance, created on first use.
	 *
	 * @var \ZeroSpam\Includes\Network_Settings|null
	 */
	private ?\ZeroSpam\Includes\Network_Settings $network_settings = null;

	/**
	 * Keys filter_site_save() removed, per option, while the option did not exist yet.
	 *
	 * WordPress sanitizes the value twice when update_option() adds a missing
	 * option (once itself, once in add_option()). A writer that puts `false`
	 * back for a key the first pass removed (the 5.x settings form did, for
	 * every unticked checkbox) has it removed again.
	 *
	 * @var array<string, string[]>
	 */
	private array $removed_before_add = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Only for network sites (not the network admin, which saves through switch_to_blog()).
		if ( ! is_multisite() || is_network_admin() ) {
			return;
		}

		// Keep locked and unchanged settings out of what this site stores, on every request.
		add_action( 'init', array( $this, 'register_save_filters' ), 20 );
	}

	/**
	 * Network Settings instance.
	 *
	 * Created on first use: each instance adds its own resolver to
	 * `zerospam_settings`, which front-end requests should not pay for.
	 */
	private function network_settings(): \ZeroSpam\Includes\Network_Settings {
		if ( null === $this->network_settings ) {
			$this->network_settings = new \ZeroSpam\Includes\Network_Settings();
		}

		return $this->network_settings;
	}

	/**
	 * Hooks the save filters into every module option and the blocked email domains option.
	 *
	 * Uses the option names `zero-spam-{section}`, at priority 20 so
	 * filter_site_save() runs after Settings_Schema::sanitize_settings_option()
	 * (priority 5).
	 */
	public function register_save_filters(): void {
		foreach ( array_keys( \ZeroSpam\Core\Settings::get_sections() ) as $section ) {
			add_filter( 'sanitize_option_zero-spam-' . $section, array( $this, 'filter_site_save' ), 20, 2 );
			add_action( 'add_option_zero-spam-' . $section, array( $this, 'forget_removed_keys' ) );
		}

		add_filter( 'pre_update_option_zerospam_blocked_email_domains', array( $this, 'keep_locked_blocked_email_domains' ), 10, 2 );
	}

	/**
	 * Forgets the keys removed while adding an option, once it has been added.
	 *
	 * @param string $option Option name.
	 */
	public function forget_removed_keys( string $option ): void {
		unset( $this->removed_before_add[ $option ] );
	}

	/**
	 * Keeps a locked `blocked_email_domains` from being changed on the site.
	 *
	 * The setting is stored in its own option, written by the REST settings
	 * routes and actions, by Settings_Schema::sanitize_settings_option(), by
	 * Utilities::update_setting() and by WP-CLI. Writes inside
	 * switch_to_blog() are left alone.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Stored value (false when the option does not exist).
	 * @return mixed Value to store; the stored one while the setting is locked, so nothing changes.
	 */
	public function keep_locked_blocked_email_domains( $value, $old_value ) {
		if ( ms_is_switched() || ! $this->network_settings()->is_locked( 'blocked_email_domains' ) ) {
			return $value;
		}

		return $old_value;
	}

	/**
	 * Keeps a site save from storing locked settings or accidental overrides.
	 *
	 * Network_Settings::resolve_all_settings() treats any key stored in a
	 * site's `zero-spam-{module}` option as a site override, whatever its
	 * value. A writer that saves every field (the 5.x settings form did, with
	 * `false` for every unticked checkbox) would otherwise turn every setting
	 * into an override, and network defaults would stop reaching the site.
	 *
	 * For each setting the network administrator has configured:
	 *
	 * - Locked: the submitted value is never stored. The value the site had
	 *   stored before (if any) is kept unchanged, so a save cannot change it
	 *   and an override made before the lock applies again if the setting is
	 *   unlocked. resolve_all_settings() ignores it while the lock is on.
	 * - Not locked, and the submitted value equals the network value: the key
	 *   is removed, so the site keeps following the network default (and
	 *   later changes to it). Values are compared loosely, see
	 *   comparable_value(): an unticked checkbox matches `disabled`, empty
	 *   values match each other, numbers match their string form and the
	 *   order of a multi-select does not matter.
	 * - Not locked, and the value differs: stored as submitted, a real site
	 *   override.
	 *
	 * Settings the network has not configured are stored exactly as on a
	 * single site. Writes made inside switch_to_blog() (the network
	 * administrator applying settings to sites) are left alone. When the
	 * option does not exist yet, WordPress sanitizes the value twice; see
	 * $removed_before_add.
	 *
	 * @param mixed  $value  Sanitized option value.
	 * @param string $option Option name.
	 * @return mixed Value to store.
	 */
	public function filter_site_save( $value, $option = '' ) {
		if ( ! is_array( $value ) || ms_is_switched() ) {
			return $value;
		}

		$network = $this->network_settings()->get_network_settings();

		if ( empty( $network['settings'] ) || ! is_array( $network['settings'] ) ) {
			return $value;
		}

		$option  = (string) $option;
		$current = get_option( $option );
		$adding  = false === $current;
		$stored  = is_array( $current ) ? $current : array();

		// Second sanitize pass of add_option(): keys the first pass removed come back as false.
		$readded  = $adding && isset( $this->removed_before_add[ $option ] ) ? $this->removed_before_add[ $option ] : array();
		$removed  = array();
		$registry = null;

		foreach ( $network['settings'] as $key => $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			if ( ! empty( $config['locked'] ) ) {
				if ( array_key_exists( $key, $stored ) ) {
					$value[ $key ] = $stored[ $key ];
				} else {
					unset( $value[ $key ] );
				}
				continue;
			}

			// Same test as the resolver's network-default level.
			if ( ! array_key_exists( $key, $value ) || ! isset( $config['value'] ) ) {
				continue;
			}

			if ( null === $registry ) {
				$registry = \ZeroSpam\Core\Settings::get_settings();
			}

			$type      = isset( $registry[ $key ]['type'] ) ? (string) $registry[ $key ]['type'] : '';
			$unchanged = self::comparable_value( $value[ $key ], $type ) === self::comparable_value( $config['value'], $type );
			$refilled  = in_array( $key, $readded, true ) && in_array( $value[ $key ], array( false, array() ), true );

			if ( $unchanged || $refilled ) {
				unset( $value[ $key ] );
				$removed[] = (string) $key;
			}
		}

		if ( $adding ) {
			$this->removed_before_add[ $option ] = $removed;
		}

		return $value;
	}

	/**
	 * Normalizes a setting value for comparison with the network value.
	 *
	 * The settings form submits strings, `false` for an unticked checkbox and
	 * an empty array for an empty multi-select, while the network settings page
	 * stores `disabled` for an unticked checkbox and an empty string for an
	 * empty multi-select.
	 *
	 * @param mixed  $value Setting value.
	 * @param string $type  Setting type from the registry.
	 * @return string|string[]
	 */
	private static function comparable_value( $value, string $type ) {
		if ( 'checkbox' === $type && 'disabled' === $value ) {
			$value = '';
		}

		if ( null === $value || false === $value || array() === $value ) {
			return '';
		}

		if ( is_array( $value ) ) {
			$items = array();
			foreach ( $value as $item ) {
				$items[] = is_scalar( $item ) ? (string) $item : (string) wp_json_encode( $item );
			}
			sort( $items, SORT_STRING );

			return $items;
		}

		if ( true === $value ) {
			return '1';
		}

		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
	}
}
