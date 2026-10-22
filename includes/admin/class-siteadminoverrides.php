<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Site_Admin_Overrides to this file name.
/**
 * Site Admin Overrides
 *
 * On a network site: shows network settings and lock indicators on the
 * settings page, and keeps locked or unchanged settings out of what the
 * site stores, whichever path writes them (settings page, WP-CLI, REST).
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
	 * Module of each setting key, read once per request.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $module_by_key = null;

	/**
	 * Keys filter_site_save() removed, per option, while the option did not exist yet.
	 *
	 * WordPress sanitizes the value twice when update_option() adds a missing
	 * option (once itself, once in add_option()). On the second pass of a
	 * settings form submission Settings::settings_validation() puts `false`
	 * back for every checkbox the first pass removed, so filter_site_save()
	 * removes those keys again.
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

		if ( ! is_admin() ) {
			return;
		}

		// Add lock indicators to settings fields.
		add_action( 'zerospam_setting_field_before', array( $this, 'render_lock_indicator' ) );
		add_action( 'zerospam_setting_field_after', array( $this, 'render_override_notice' ) );

		// Enqueue site admin styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add info notice at top of settings page.
		add_action( 'zerospam_settings_page_top', array( $this, 'render_network_notice' ) );

		// Disable locked fields.
		add_filter( 'zerospam_setting_field_disabled', array( $this, 'disable_locked_field' ), 10, 2 );
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
	 * Enqueue assets
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Only on Zero Spam settings page.
		if ( 'settings_page_wordpress-zero-spam-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'zerospam-site-overrides',
			plugin_dir_url( ZEROSPAM ) . 'assets/css/site-overrides.css',
			array(),
			ZEROSPAM_VERSION
		);
	}

	/**
	 * Render network notice
	 */
	public function render_network_notice() {
		$all_settings   = $this->network_settings()->get_network_settings();
		$locked_count   = 0;
		$override_count = 0;

		if ( ! empty( $all_settings['settings'] ) ) {
			foreach ( $all_settings['settings'] as $key => $config ) {
				if ( ! empty( $config['locked'] ) ) {
					++$locked_count;
				} elseif ( ! $this->uses_network_default( (string) $key ) ) {
					++$override_count;
				}
			}
		}

		if ( 0 === $locked_count && 0 === $override_count ) {
			return; // No network settings, no need to show notice.
		}

		// The inline class keeps WordPress from moving the notice below the first heading.
		?>
		<div class="notice notice-info inline zerospam-network-notice">
			<p>
				<strong><?php esc_html_e( 'Network Settings Active', 'zero-spam' ); ?></strong>
			</p>
			<p>
				<?php
				if ( $locked_count > 0 ) {
					printf(
						esc_html(
							/* translators: %d: number of locked settings */
							_n(
								'%d setting is locked by your Network Administrator and cannot be changed.',
								'%d settings are locked by your Network Administrator and cannot be changed.',
								$locked_count,
								'zero-spam'
							)
						),
						(int) $locked_count
					);
				}

				if ( $override_count > 0 ) {
					echo ' ';
					printf(
						esc_html(
							/* translators: %d: number of overridden settings */
							_n(
								'You have %d customized setting that overrides a network default.',
								'You have %d customized settings that override network defaults.',
								$override_count,
								'zero-spam'
							)
						),
						(int) $override_count
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render lock indicator before field
	 *
	 * @param string $field_key Setting key.
	 */
	public function render_lock_indicator( $field_key ) {
		if ( ! $this->network_settings()->is_locked( self::lock_key( (string) $field_key ) ) ) {
			return;
		}

		?>
		<p class="zerospam-locked-badge">
			<span class="dashicons dashicons-lock" aria-hidden="true"></span>
			<?php esc_html_e( 'Locked by Network Admin', 'zero-spam' ); ?>
		</p>
		<?php
	}

	/**
	 * Render override notice after field
	 *
	 * @param string $field_key Setting key.
	 */
	public function render_override_notice( $field_key ) {
		// Skip if locked (locked settings show their own indicator).
		if ( $this->network_settings()->is_locked( $field_key ) ) {
			return;
		}

		$network_default = $this->network_settings()->get_network_default( $field_key );

		if ( null === $network_default ) {
			return;
		}

		$network_display = is_string( $network_default ) ? $network_default : (string) wp_json_encode( $network_default );

		// Check if using network default or has override.
		if ( $this->uses_network_default( (string) $field_key ) ) {
			?>
			<p class="description zerospam-network-default-notice">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<?php esc_html_e( 'Using network default', 'zero-spam' ); ?>:
				<code><?php echo esc_html( $network_display ); ?></code>
			</p>
			<?php
		} else {
			?>
			<p class="description zerospam-override-notice">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php esc_html_e( 'You have customized this setting', 'zero-spam' ); ?>.
				<?php esc_html_e( 'Network default', 'zero-spam' ); ?>:
				<code><?php echo esc_html( $network_display ); ?></code>
			</p>
			<?php
		}
	}

	/**
	 * Whether the current site follows the network value for a setting.
	 *
	 * Same answer as Network_Settings::is_using_default() for the current
	 * site, without its switch_to_blog() and settings registry rebuild per
	 * call: the page asks once per network-configured field.
	 *
	 * @param string $setting_key Setting key.
	 */
	private function uses_network_default( string $setting_key ): bool {
		if ( $this->network_settings()->is_locked( $setting_key ) ) {
			return true;
		}

		$network_default = $this->network_settings()->get_network_default( $setting_key );

		if ( null === $network_default ) {
			return false;
		}

		if ( null === $this->module_by_key ) {
			$this->module_by_key = array();
			foreach ( \ZeroSpam\Core\Settings::get_settings() as $key => $setting ) {
				if ( isset( $setting['module'] ) ) {
					$this->module_by_key[ (string) $key ] = (string) $setting['module'];
				}
			}
		}

		if ( ! isset( $this->module_by_key[ $setting_key ] ) ) {
			return true;
		}

		$stored = get_option( 'zero-spam-' . $this->module_by_key[ $setting_key ], array() );

		return ! ( is_array( $stored ) && isset( $stored[ $setting_key ] ) && $stored[ $setting_key ] !== $network_default );
	}

	/**
	 * Disable locked fields
	 *
	 * @param bool   $disabled  Whether field is disabled.
	 * @param string $field_key Setting key.
	 * @return bool True if should be disabled.
	 */
	public function disable_locked_field( $disabled, $field_key ) {
		if ( $this->network_settings()->is_locked( self::lock_key( (string) $field_key ) ) ) {
			return true;
		}

		return $disabled;
	}

	/**
	 * Setting whose lock applies to a settings page field.
	 *
	 * The "Override & Update Blocked Email Domains" button rewrites
	 * blocked_email_domains; every other field is its own setting.
	 *
	 * @param string $field_key Field (setting) key.
	 */
	private static function lock_key( string $field_key ): string {
		return 'update_blocked_email_domains' === $field_key ? 'blocked_email_domains' : $field_key;
	}

	/**
	 * Hooks the save filters into every module option and the blocked email domains option.
	 *
	 * Uses the same option names as Settings::register_settings()
	 * (`zero-spam-{section}`), at priority 20 so filter_site_save() runs after
	 * Settings::settings_validation() (priority 10) where that is registered.
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
	 * The setting is stored in its own option, written by
	 * Settings::settings_validation() before filter_site_save() runs, by the
	 * "Override & Update Blocked Email Domains" action and by
	 * Utilities::update_setting(). Writes inside switch_to_blog() are left alone.
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
	 * value. The settings form submits every field, and on a form submission
	 * Settings::settings_validation() stores `false` for every unticked
	 * checkbox, so without this filter one save would turn every setting into
	 * an override and network defaults would stop reaching the site.
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
