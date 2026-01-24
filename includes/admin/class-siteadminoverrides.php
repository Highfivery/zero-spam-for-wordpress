<?php
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
	 * Constructor
	 */
	public function __construct() {
		// Only for multisite site admin (not network admin).
		if ( ! is_multisite() || is_network_admin() ) {
			return;
		}

		$this->network_settings = new \ZeroSpam\Includes\Network_Settings();

		// Add lock indicators to settings fields.
		add_action( 'zerospam_setting_field_before', array( $this, 'render_lock_indicator' ), 10, 2 );
		add_action( 'zerospam_setting_field_after', array( $this, 'render_override_notice' ), 10, 2 );

		// Enqueue site admin styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add info notice at top of settings page.
		add_action( 'zerospam_settings_page_top', array( $this, 'render_network_notice' ) );

		// Disable locked fields.
		add_filter( 'zerospam_setting_field_disabled', array( $this, 'disable_locked_field' ), 10, 2 );
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
					$locked_count++;
				} elseif ( ! $this->network_settings->is_using_default( $key ) ) {
					$override_count++;
				}
			}
		}

		if ( $locked_count === 0 && $override_count === 0 ) {
			return; // No network settings, no need to show notice.
		}

		?>
		<div class="notice notice-info zerospam-network-notice">
			<p>
				<strong><?php esc_html_e( 'Network Settings Active', 'zero-spam' ); ?></strong>
			</p>
			<p>
				<?php
				if ( $locked_count > 0 ) {
					printf(
						/* translators: %d: number of locked settings */
						esc_html__( '%d settings are locked by your Network Administrator and cannot be changed.', 'zero-spam' ),
						esc_html( $locked_count )
					);
				}

				if ( $override_count > 0 ) {
					echo ' ';
					printf(
						/* translators: %d: number of overridden settings */
						esc_html__( 'You have %d customized settings that override network defaults.', 'zero-spam' ),
						esc_html( $override_count )
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
	 * @param array  $field     Field config.
	 */
	public function render_lock_indicator( $field_key, $field ) {
		if ( ! $this->network_settings->is_locked( $field_key ) ) {
			return;
		}

		?>
		<div class="zerospam-locked-badge">
			🔒 <?php esc_html_e( 'Locked by Network Admin', 'zero-spam' ); ?>
		</div>
		<?php
	}

	/**
	 * Render override notice after field
	 *
	 * @param string $field_key Setting key.
	 * @param array  $field     Field config.
	 */
	public function render_override_notice( $field_key, $field ) {
		// Skip if locked (locked settings show their own indicator).
		if ( $this->network_settings->is_locked( $field_key ) ) {
			return;
		}

		// Check if using network default or has override.
		if ( $this->network_settings->is_using_default( $field_key ) ) {
			$network_default = $this->network_settings->get_network_default( $field_key );

			if ( null !== $network_default ) {
				?>
				<p class="description zerospam-network-default-notice">
					ℹ️ <?php esc_html_e( 'Using network default', 'zero-spam' ); ?>:
					<code><?php echo esc_html( is_string( $network_default ) ? $network_default : wp_json_encode( $network_default ) ); ?></code>
				</p>
				<?php
			}
		} else {
			$network_default = $this->network_settings->get_network_default( $field_key );
			$site_override   = $this->network_settings->get_site_override( $field_key );

			if ( null !== $network_default ) {
				?>
				<p class="description zerospam-override-notice">
					⚠️ <?php esc_html_e( 'You have customized this setting', 'zero-spam' ); ?>.
					<?php esc_html_e( 'Network default', 'zero-spam' ); ?>:
					<code><?php echo esc_html( is_string( $network_default ) ? $network_default : wp_json_encode( $network_default ) ); ?></code>
				</p>
				<?php
			}
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
}
