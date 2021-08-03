<?php
/**
 * Cron
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Shortcodes
 */
class Cron {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cron' ) );
		add_action( 'zerospam_update_blacklist_terms', array( $this, 'update_blacklist' ) );
		register_deactivation_hook( ZEROSPAM, array( $this, 'deactivate_cron' ) );
	}

	/**
	 * Register cron jobs.
	 */
	public function register_cron() {
		if ( ! wp_next_scheduled( 'zerospam_update_blacklist_terms' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'weekly', 'zerospam_update_blacklist_terms' );
		}
	}

	/**
	 * Updates the WP core blacklist.
	 */
	public function update_blacklist() {
		if ( 'enabled' === \ZeroSpam\Core\Settings::get_settings( 'sync_disallowed_keys' ) ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$text = $wp_filesystem->get_contents( ZEROSPAM_PATH . 'assets/blacklist.txt' );

			update_option( 'disallowed_keys', $text );
		}
	}

	/**
	 * Deactivates cron.
	 */
	public function deactivate_cron() {
		wp_clear_scheduled_hook( 'zerospam_update_blacklist_terms' );
	}
}
