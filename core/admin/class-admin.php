<?php
/**
 * Admin class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\Utilities;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * The plugin's links on the Plugins screen and the license error notice.
 *
 * Since 6.0.0 the 5.x welcome, settings review, API monitoring and Enhanced
 * Protection promo notices are gone (first-run setup and "What's new"
 * replace them), and the 5.x screens redirect to the 6.0 admin
 * (Legacy_Redirects).
 */
class Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'plugin_action_links_' . ZEROSPAM_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );
		add_filter( 'network_admin_plugin_action_links_' . ZEROSPAM_PLUGIN_BASE, array( $this, 'network_plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Display admin notices
	 */
	public function admin_notices() {
		// The notice links to settings only administrators can change (#405).
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Enhanced Protection is on without a license key (no API request).
		if ( \ZeroSpam\Core\License_Status::is_enabled() && ! \ZeroSpam\Core\License_Status::has_key() ) {
			$this->display_license_error_notice();
		}
	}

	/**
	 * Display license error notice
	 */
	private function display_license_error_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %1$s: Replaced with the Zero Spam license settings URL */
						__( 'Zero Spam Enhanced Protection is currently enabled, but <strong>missing a valid license key</strong>. <a href="%1$s">Add your license key</a> to enable enhanced site protection.', 'zero-spam' ),
						array(
							'strong' => array(),
							'a'      => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
					esc_url( Utilities::admin_link( 'license' ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin action links
	 *
	 * Adds a Settings link to the plugin list table.
	 *
	 * Fired by `plugin_action_links` filter.
	 *
	 * @param array $links An array of plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf( '<a href="%1$s">%2$s</a>', esc_url( Utilities::admin_link( 'settings' ) ), esc_html__( 'Settings', 'zero-spam' ) );

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Network plugin action links
	 *
	 * Adds a Settings link to the network admin's Plugins screen, to the
	 * network page, for users who can manage network options.
	 *
	 * Fired by `network_admin_plugin_action_links_{plugin}` filter.
	 *
	 * @since 6.0.0
	 *
	 * @param array $links An array of plugin action links.
	 * @return array
	 */
	public function network_plugin_action_links( $links ) {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return $links;
		}

		array_unshift( $links, sprintf( '<a href="%1$s">%2$s</a>', esc_url( network_admin_url( 'admin.php?page=zero-spam-network' ) ), esc_html__( 'Settings', 'zero-spam' ) ) );

		return $links;
	}

	/**
	 * Plugin row meta.
	 *
	 * Adds row meta links to the plugin list table
	 *
	 * Fired by `plugin_row_meta` filter.
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata, including
	 *                            the version, author, author URI, and plugin URI.
	 * @param string $plugin_file Path to the plugin file, relative to the plugins
	 *                            directory.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( ZEROSPAM_PLUGIN_BASE === $plugin_file ) {
			$row_meta = array(
				'docs' => '<a href="https://github.com/Highfivery/zero-spam-for-wordpress/wiki" aria-label="' . esc_attr( __( 'View Documentation', 'zero-spam' ) ) . '" target="_blank">' . esc_html__( 'Docs & FAQs', 'zero-spam' ) . '</a>',
			);

			$plugin_meta = array_merge( $plugin_meta, $row_meta );
		}

		return $plugin_meta;
	}
}
