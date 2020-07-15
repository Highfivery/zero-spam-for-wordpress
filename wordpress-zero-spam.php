<?php
/**
 * WordPress Zero Spam Plugin
 *
 * @package    WordPressZeroSpam
 * @subpackage WordPress
 * @since      4.1.1
 * @author     Ben Marshall
 * @copyright  2020 Ben Marshall
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WordPress Zero Spam
 * Plugin URI:        https://benmarshall.me/wordpress-zero-spam
 * Description:       Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch. <strong>Just install, activate and say goodbye to spam.</strong> Based on work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.
 * Version:           4.1.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Ben Marshall
 * Author URI:        https://benmarshall.me
 * Text Domain:       wpzerospam
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Define plugin constants
define( 'WORDPRESS_ZERO_SPAM', __FILE__ );

/**
 * Plugin scripts
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/inc/scripts.php';

/**
 * Helpers
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/inc/helpers.php';

/**
 * Admin interface
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/inc/admin.php';

/**
 * Below are the includes for individual spam check addons
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/comments.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/registration.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/contact-form-7.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/gravity-forms.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/ninja-forms.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/buddypress.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/addons/wpforms.php';

/**
 * Plugin redirect functionality
 */
if ( ! function_exists( 'wpzerospam_template_redirect' ) ) {
  function wpzerospam_template_redirect() {
    $options = wpzerospam_options();

    // Check if the current user has access to the site
    $access = wpzerospam_check_access();
    if ( ! $access['access'] ) {

      // Log the blocked entry
      wpzerospam_log( $access['type'], [
        'ip'     => $access['ip'],
        'reason' => $access['reason']
      ] );

      wp_redirect( $options['blocked_redirect_url'] );
      exit();
    }
  }
}
add_action( 'template_redirect', 'wpzerospam_template_redirect' );
