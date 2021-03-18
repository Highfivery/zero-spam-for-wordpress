<?php
/**
 * WordPress Zero Spam Plugin
 *
 * @package    WordPressZeroSpam
 * @subpackage WordPress
 * @since      5.0.0
 * @author     Ben Marshall
 * @copyright  2021 Ben Marshall
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WordPress Zero Spam
 * Plugin URI:        https://benmarshall.me/wordpress-zero-spam
 * Description:       Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch. <strong>Just install, activate and say goodbye to spam.</strong>.
 * Version:           5.0.11
 * Requires at least: 5.2
 * Requires PHP:      7.3
 * Author:            Ben Marshall
 * Author URI:        https://benmarshall.me
 * Text Domain:       zerospam
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

// Define plugin constants.
define( 'ZEROSPAM', __FILE__ );
define( 'ZEROSPAM_PATH', plugin_dir_path( ZEROSPAM ) );
define( 'ZEROSPAM_PLUGIN_BASE', plugin_basename( ZEROSPAM ) );
define( 'ZEROSPAM_VERSION', '5.0.11' );

add_action( 'plugins_loaded', 'zerospam_load_plugin_textdomain' );

if ( ! version_compare( PHP_VERSION, '7.3', '>=' ) ) {
	add_action( 'admin_notices', 'zerospam_fail_php_version' );
} elseif ( ! version_compare( get_bloginfo( 'version' ), '5', '>=' ) ) {
	add_action( 'admin_notices', 'zerospam_fail_wp_version' );
} else {
	require ZEROSPAM_PATH . 'includes/class-plugin.php';
}

/**
 * Load plugin textdomain
 */
function zerospam_load_plugin_textdomain() {
	load_plugin_textdomain( 'zerospam' );
}

/**
 * WordPress Zero Spam admin notice for minimum PHP version
 */
function zerospam_fail_php_version() {
	/* translators: %s: PHP version */
	$message      = sprintf( esc_html__( 'WordPress Zero Spam requires PHP version %s+, plugin is currently NOT RUNNING.', 'zerospam' ), '7.3' );
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );
	echo wp_kses_post( $html_message );
}

/**
 * WordPress Zero Spam admin notice for minimum WordPress version
 */
function zerospam_fail_wp_version() {
	/* translators: %s: WordPress version */
	$message      = sprintf( esc_html__( 'WordPress Zero Spam requires WordPress version %s+. Because you are using an earlier version, the plugin is currently NOT RUNNING.', 'zerospam' ), '5' );
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );
	echo wp_kses_post( $html_message );
}
