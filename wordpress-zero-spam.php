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
 * Version:           4.9.6
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
define( 'WORDPRESS_ZERO_SPAM_DB_VERSION', '0.5' );
define( 'WORDPRESS_ZERO_SPAM_VERSION', '4.9.6' );

/**
 * Utility helper functions
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/utilities.php';

/**
 * Helpers
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/helpers.php';

/**
 * Plugin updates
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/updates.php';

/**
 * Install plugin tables
 */
function wpzerospam_install() {
  global $wpdb;

  $charset_collate      = $wpdb->get_charset_collate();
  $installed_db_version = get_option( 'wpzerospam_db_version' );

  if ( $installed_db_version != WORDPRESS_ZERO_SPAM_DB_VERSION ) {
    $log_table       = wpzerospam_tables( 'log' );
    $blocked_table   = wpzerospam_tables( 'blocked' );
    $blacklist_table = wpzerospam_tables( 'blacklist' );

    $sql = "CREATE TABLE $log_table (
      log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      log_type VARCHAR(255) NOT NULL,
      user_ip VARCHAR(39) NOT NULL,
      date_recorded DATETIME NOT NULL,
      page_url VARCHAR(255) NULL DEFAULT NULL,
      submission_data LONGTEXT NULL DEFAULT NULL,
      country VARCHAR(2) NULL DEFAULT NULL,
      region VARCHAR(255) NULL DEFAULT NULL,
      city VARCHAR(255) NULL DEFAULT NULL,
      latitude VARCHAR(255) NULL DEFAULT NULL,
      longitude VARCHAR(255) NULL DEFAULT NULL,
      PRIMARY KEY (`log_id`)) $charset_collate;";

    $sql .= "CREATE TABLE $blocked_table (
      blocked_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      blocked_type ENUM('permanent','temporary') NOT NULL DEFAULT 'temporary',
      user_ip VARCHAR(39) NOT NULL,
      date_added DATETIME NOT NULL,
      start_block DATETIME NULL DEFAULT NULL,
      end_block DATETIME NULL DEFAULT NULL,
      reason VARCHAR(255) NULL DEFAULT NULL,
      attempts BIGINT UNSIGNED NOT NULL,
      PRIMARY KEY (`blocked_id`)) $charset_collate;";

    $sql .= "CREATE TABLE $blacklist_table (
      blacklist_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_ip VARCHAR(39) NOT NULL,
      last_updated DATETIME NOT NULL,
      blacklist_service VARCHAR(255) NULL DEFAULT NULL,
      attempts BIGINT UNSIGNED NOT NULL,
      blacklist_data LONGTEXT NULL DEFAULT NULL,
      PRIMARY KEY (`blacklist_id`)) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $blocked_table ) ) === $blocked_table ) {
      $wpdb->query( "DELETE t1 FROM $blocked_table AS t1 JOIN $blocked_table AS t2 ON t2.blocked_id = t1.blocked_id WHERE t1.blocked_id < t2.blocked_id AND t1.user_ip = t2.user_ip" );
    }

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $blacklist_table ) ) === $blacklist_table ) {
      $wpdb->query( "DELETE t1 FROM $blacklist_table AS t1 JOIN $blacklist_table AS t2 ON t2.blacklist_id = t1.blacklist_id WHERE t1.blacklist_id < t2.blacklist_id AND t1.user_ip = t2.user_ip" );
    }

    update_option( 'wpzerospam_db_version', WORDPRESS_ZERO_SPAM_DB_VERSION );
  }
}
register_activation_hook( WORDPRESS_ZERO_SPAM, 'wpzerospam_install' );

/**
 * Check to ensure the database tables have been installed
 */
function wpzerospam_db_check() {
  if ( get_site_option( 'wpzerospam_db_version' ) != WORDPRESS_ZERO_SPAM_DB_VERSION ) {
    wpzerospam_install();
  }
}
add_action( 'plugins_loaded', 'wpzerospam_db_check' );

/**
 * Plugin scripts
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/scripts.php';

/**
 * Admin interface & functionality
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/admin.php';

/**
 * WP filters
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/filters.php';

/**
 * Below are the includes for individual spam check integrations
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/comments/comments.php';
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/registrations/registrations.php';

if ( wpzerospam_plugin_integration_enabled( 'cf7' ) ) {
  require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/contact-form-7/contact-form-7.php';
}

if ( wpzerospam_plugin_integration_enabled( 'gform' ) ) {
  require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'integrations/gravity-forms/gravity-forms.php';
}

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

/**
 * Handles uninstalling the plugin
 */
if ( ! function_exists( 'wpzerospam_uninstall' ) ) {
  function wpzerospam_uninstall() {
    global $wpdb;

    if ( is_multisite() ) {
      $blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );

      if ( $blogs ) {
        foreach ( $blogs as $blog ) {
          switch_to_blog( $blog['blog_id'] );

          delete_option( 'wpzerospam' );
          delete_option( 'wpzerospam_key' );
          delete_option( 'wpzerospam_db_version' );
          delete_option( 'wpzerospam_update_version' );

          $tables = wpzerospam_tables();
          foreach( $tables as $key => $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
          }
        }
        restore_current_blog();
      }
    } else {
      delete_option( 'wpzerospam' );
      delete_option( 'wpzerospam_key' );
      delete_option( 'wpzerospam_db_version' );
      delete_option( 'wpzerospam_update_version' );

      $tables = wpzerospam_tables();
      foreach( $tables as $key => $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS $table" );
      }
    }
  }
}
register_uninstall_hook( WORDPRESS_ZERO_SPAM, 'wpzerospam_uninstall' );
