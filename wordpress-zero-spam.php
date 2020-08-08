<?php
/**
 * WordPress Zero Spam Plugin
 *
 * @package    WordPressZeroSpam
 * @subpackage WordPress
 * @since      4.0.0
 * @author     Ben Marshall
 * @copyright  2020 Ben Marshall
 * @license    GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WordPress Zero Spam
 * Plugin URI:        https://benmarshall.me/wordpress-zero-spam
 * Description:       Tired of all the useless and bloated WordPress spam plugins? The WordPress Zero Spam plugin makes blocking spam a cinch. <strong>Just install, activate and say goodbye to spam.</strong> Based on work by <a href="http://davidwalsh.name/wordpress-comment-spam" target="_blank">David Walsh</a>.
 * Version:           4.9.12
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Ben Marshall
 * Author URI:        https://benmarshall.me
 * Text Domain:       zero-spam
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Define plugin constants
define( 'WORDPRESS_ZERO_SPAM', __FILE__ );
define( 'WORDPRESS_ZERO_SPAM_DB_VERSION', '0.5' );
define( 'WORDPRESS_ZERO_SPAM_VERSION', '4.9.12' );

/**
 * Utility helper functions.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/utilities.php';

/**
 * Helpers.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/helpers.php';

/**
 * Plugin updates.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/updates.php';

/**
 * Install & upgrade functionality.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/install.php';

/**
 * Uninstall functionality.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/uninstall.php';

/**
 * Plugin CSS & JS scripts.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/scripts.php';

/**
 * Admin interface & functionality.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/admin.php';

/**
 * Action & filter hooks for enhanced site security.
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/security.php';

/**
 * Initializes the plugin.
 *
 * @since 4.9.12
 *
 * @return void
 */
if ( ! function_exists( 'zero_spam_setup' ) ) {
  function zero_spam_setup() {
    /**
     * Include the WPZS core comments integration.
     */
    require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/comments/comments.php';

    /**
     * Include the WPZS core registration integration.
     */
    require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/registrations/registrations.php';

    /**
     * Include the WPZS Contact Form 7 integration if it's active.
     */
    if ( class_exists( 'WPCF7' ) ) {
      require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/contact-form-7/contact-form-7.php';
    }
  }
}
add_action( 'plugins_loaded', 'zero_spam_setup' );





if ( wpzerospam_plugin_integration_enabled( 'bp_registrations' ) ) {
  require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/buddypress/buddypress.php';
}

if ( wpzerospam_plugin_integration_enabled( 'wpforms' ) ) {
  require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/wpforms/wpforms.php';
}

if ( wpzerospam_plugin_integration_enabled( 'fluentform' ) ) {
  require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/fluentform/fluentform.php';
}

if ( wpzerospam_plugin_integration_enabled( 'formidable' ) ) {
  require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/formidable/formidable.php';
}

/**
 * Plugin redirect functionality
 */
if ( ! function_exists( 'wpzerospam_template_redirect' ) ) {
  function wpzerospam_template_redirect() {
    // No need to check everytime a user visits a page
    if ( wpzerospam_get_cookie( 'last_check' ) ) { return false; }

    $options = wpzerospam_options();

    // Check if the current user has access to the site
    $access = wpzerospam_check_access();

    if ( ! $access['access'] ) {
      wpzerospam_attempt_blocked( $access['ip'], $access['reason'] );
    } else {
      wpzerospam_set_cookie( 'last_check', current_time( 'timestamp' ) );
    }
  }
}
add_action( 'template_redirect', 'wpzerospam_template_redirect' );
