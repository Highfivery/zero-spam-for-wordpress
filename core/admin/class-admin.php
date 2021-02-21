<?php
/**
 * Admin class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\Admin\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Admin.
 *
 * Handles access checks.
 *
 * @since 5.0.0
 */
class Admin {

	/**
	 * Settings.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Admin constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		$this->settings = new Settings();

		add_filter( 'plugin_action_links_' . ZEROSPAM_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
	}

	/**
	 * Plugin action links.
	 *
	 * Adds action links to the plugin list table
	 *
	 * Fired by `plugin_action_links` filter.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @param array $links An array of plugin action links.
	 *
	 * @return array An array of plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ), __( 'Settings', 'zerospam' ) );

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Plugin row meta.
	 *
	 * Adds row meta links to the plugin list table
	 *
	 * Fired by `plugin_row_meta` filter.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata, including
	 *                            the version, author, author URI, and plugin URI.
	 * @param string $plugin_file Path to the plugin file, relative to the plugins
	 *                            directory.
	 *
	 * @return array An array of plugin row meta links.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( ZEROSPAM_PLUGIN_BASE === $plugin_file ) {
			$row_meta = [
				'docs' => '<a href="https://github.com/bmarshall511/wordpress-zero-spam/wiki" aria-label="' . esc_attr( __( 'View WordPress Zero Spam Documentation', 'zerospam' ) ) . '" target="_blank">' . __( 'Docs & FAQs', 'zerospam' ) . '</a>',
			];

			$plugin_meta = array_merge( $plugin_meta, $row_meta );
		}

		return $plugin_meta;
	}

	/**
	 * Admin footer text.
	 *
	 * Modifies the "Thank you" text displayed in the admin footer.
	 *
	 * Fired by `admin_footer_text` filter.
	 *
	 * @since 5.0.0
	 * @access public
	 *
	 * @param string $footer_text The content that will be printed.
	 *
	 * @return string The content that will be printed.
	 */
	public function admin_footer_text( $footer_text ) {
		$current_screen     = get_current_screen();
		$is_zerospam_screen = ( $current_screen && false !== strpos( $current_screen->id, 'wordpress-zero-spam' ) );

		if ( $is_zerospam_screen ) {
			$footer_text = sprintf(
				/* translators: 1: Elementor, 2: Link to plugin review */
				__( 'Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'zerospam' ),
				'<strong>' . __( 'WordPress Zero Spam', 'zerospam' ) . '</strong>',
				'<a href="https://wordpress.org/plugins/zero-spam/#reviews" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}
}
