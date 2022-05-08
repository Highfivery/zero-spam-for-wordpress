<?php
/**
 * Zero Spam for WordPress Plugin
 *
 * @package    ZeroSpam
 * @subpackage WordPress
 * @since      5.0.0
 * @author     Highfivery LLC
 * @copyright  2022 Highfivery LLC
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Zero Spam for WordPress
 * Plugin URI:        https://www.highfivery.com/projects/zero-spam/
 * Description:       Tired of all the ineffective WordPress anti-spam & security plugins? Zero Spam for WordPress makes blocking spam &amp; malicious activity a cinch. <strong>Just activate, configure, and say goodbye to spam.</strong>
 * Version:           5.3.9
 * Requires at least: 5.2
 * Requires PHP:      7.3
 * Author:            Highfivery LLC
 * Author URI:        https://www.highfivery.com/
 * Text Domain:       zero-spam
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
define( 'ZEROSPAM_VERSION', '5.3.9' );

if ( defined( 'ZEROSPAM_DEVELOPMENT_URL' ) ) {
	define( 'ZEROSPAM_URL', ZEROSPAM_DEVELOPMENT_URL );
} else {
	define( 'ZEROSPAM_URL', 'https://www.zerospam.org/' );
}

add_action( 'plugins_loaded', 'zerospam_load_plugin_textdomain' );

if ( ! version_compare( PHP_VERSION, '7.3', '>=' ) ) {
	add_action( 'admin_notices', 'zerospam_fail_php_version' );
} elseif ( ! version_compare( get_bloginfo( 'version' ), '5', '>=' ) ) {
	add_action( 'admin_notices', 'zerospam_fail_wp_version' );
} else {
	require_once ZEROSPAM_PATH . 'includes/class-plugin.php';
}

/**
 * Load plugin textdomain
 */
function zerospam_load_plugin_textdomain() {
	load_plugin_textdomain( 'zero-spam' );
}

/**
 * Admin notice for minimum PHP version
 */
function zerospam_fail_php_version() {
	$message = sprintf(
		/* translators: %s: replaced with the PHP version number */
		esc_html__(
			'Zero Spam for WordPress requires PHP version %s+, plugin is currently NOT RUNNING.',
			'zero-spam'
		),
		'7.3'
	);
	$html_message = sprintf(
		/* translators: %s: replaced with the error message */
		'<div class="error">%s</div>',
		wpautop( $message )
	);
	echo wp_kses_post( $html_message );
}

/**
 * Admin notice for minimum WordPress version
 */
function zerospam_fail_wp_version() {
	$message = sprintf(
		/* translators: %s: replaced with the WordPress version number */
		esc_html__(
			'Zero Spam for WordPress requires WordPress version %s+. Because you are using an earlier version, the plugin is currently NOT RUNNING.',
			'zerospam'
		),
		'5'
	);
	$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );
	echo wp_kses_post( $html_message );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ZEROSPAM_PATH . 'includes/class-cli.php';
}
