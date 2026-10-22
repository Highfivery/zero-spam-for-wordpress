<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Site_Admin_Overrides to this file name.
/**
 * Site Admin Overrides UI
 *
 * Modifies the site admin settings page to show network settings,
 * lock indicators, and prevent editing of locked settings.
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
	 * Network Settings instance
	 *
	 * @var \ZeroSpam\Includes\Network_Settings
	 */
	private $network_settings;

	/**
	 * Keys filter_site_save() removed, per option, while the option did not exist yet.
	 *
	 * WordPress sanitizes the value twice when update_option() adds a missing
	 * option (once itself, once in add_option()). On the second pass
	 * Settings::settings_validation() puts `false` back for every checkbox
	 * the first pass removed, so filter_site_save() removes those keys again.
	 *
	 * @var array<string, string[]>
	 */
	private array $removed_before_add = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Only for multisite site admin (not network admin).
		if ( ! is_multisite() || is_network_admin() ) {
			return;
		}

		$this->network_settings = new \ZeroSpam\Includes\Network_Settings();

		// Add lock indicators to settings fields.
		add_action( 'zerospam_setting_field_before', array( $this, 'render_lock_indicator' ) );
		add_action( 'zerospam_setting_field_after', array( $this, 'render_override_notice' ) );

		// Enqueue site admin styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add info notice at top of settings page.
		add_action( 'zerospam_settings_page_top', array( $this, 'render_network_notice' ) );

		// Disable locked fields.
		add_filter( 'zerospam_setting_field_disabled', array( $this, 'disable_locked_field' ), 10, 2 );

		// Keep locked and unchanged settings out of what a site save stores.
		add_action( 'admin_init', array( $this, 'register_save_filters' ) );
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
		$all_settings   = $this->network_settings->get_network_settings();
		$locked_count   = 0;
		$override_count = 0;

		if ( ! empty( $all_settings['settings'] ) ) {
			foreach ( $all_settings['settings'] as $key => $config ) {
				if ( ! empty( $config['locked'] ) ) {
					++$locked_count;
				} elseif ( ! $this->network_settings->is_using_default( $key ) ) {
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
						/* translators: %d: number of locked settings */
						esc_html__( '%d settings are locked by your Network Administrator and cannot be changed.', 'zero-spam' ),
						esc_html( (string) $locked_count )
					);
				}

				if ( $override_count > 0 ) {
					echo ' ';
					printf(
						/* translators: %d: number of overridden settings */
						esc_html__( 'You have %d customized settings that override network defaults.', 'zero-spam' ),
						esc_html( (string) $override_count )
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
		if ( ! $this->network_settings->is_locked( $field_key ) ) {
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
		if ( $this->network_settings->is_locked( $field_key ) ) {
			return;
		}

		$network_default = $this->network_settings->get_network_default( $field_key );

		if ( null === $network_default ) {
			return;
		}

		$network_display = is_string( $network_default ) ? $network_default : (string) wp_json_encode( $network_default );

		// Check if using network default or has override.
		if ( $this->network_settings->is_using_default( $field_key ) ) {
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
	 * Disable locked fields
	 *
	 * @param bool   $disabled  Whether field is disabled.
	 * @param string $field_key Setting key.
	 * @return bool True if should be disabled.
	 */
	public function disable_locked_field( $disabled, $field_key ) {
		if ( $this->network_settings->is_locked( $field_key ) ) {
			return true;
		}

		return $disabled;
	}

	/**
	 * Hooks filter_site_save() into the sanitizing of every module option.
	 *
	 * Uses the same option names as Settings::register_settings()
	 * (`zero-spam-{section}`), at priority 20 so it runs after
	 * Settings::settings_validation() (priority 10).
	 */
	public function register_save_filters() {
		foreach ( array_keys( \ZeroSpam\Core\Settings::get_sections() ) as $section ) {
			add_filter( 'sanitize_option_zero-spam-' . $section, array( $this, 'filter_site_save' ), 20, 2 );
		}

		add_action( 'added_option', array( $this, 'forget_removed_keys' ) );
	}

	/**
	 * Forgets the keys removed while adding an option, once it has been added.
	 *
	 * @param string $option Option name.
	 */
	public function forget_removed_keys( $option ) {
		unset( $this->removed_before_add[ (string) $option ] );
	}

	/**
	 * Keeps a site save from storing locked settings or accidental overrides.
	 *
	 * Network_Settings::resolve_all_settings() treats any key stored in a
	 * site's `zero-spam-{module}` option as a site override, whatever its
	 * value. The settings form submits every field, and
	 * Settings::settings_validation() stores `false` for every unticked
	 * checkbox, so without this filter one save would turn every setting
	 * into an override and network defaults would stop reaching the site.
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

		$network = $this->network_settings->get_network_settings();

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

			if ( ! array_key_exists( $key, $value ) || ! array_key_exists( 'value', $config ) ) {
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
