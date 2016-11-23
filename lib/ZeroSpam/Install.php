<?php
/**
 * ZeroSpam_Install library
 *
 * Runs when the plugin is activated.
 *
 * @package WordPress Zero Spam
 * @subpackage ZeroSpam_Install
 * @since 1.0.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Installs the Zero Spam plugin.
 *
 * This library creates all of the required tables and sets the initial
 * settings.
 *
 * @since 1.0.0
 *
 * @see ZeroSpam_Plugin
 */
class ZeroSpam_Install extends ZeroSpam_Plugin
{
  const DB_VERSION = '2.0';

  /**
   * Runs the library.
   *
   * Initializes & runs the ZeroSpam_Install library.
   *
   * @since 1.0.0
   *
   * @see register_activation_hook
   * @see add_action
   * @global string ZEROSPAM_PLUGIN The plugin root directory.
   */
  public function run()
  {

    // Called when the plugin is activated.
    register_activation_hook( ZEROSPAM_PLUGIN, array( $this, 'activate' ) );

    // This hook is called once any activated plugins have been loaded.
    add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
  }

  /**
   * Sets the plugin's default options.
   *
   * When the plugin is activated, this is ran to set/update the plugin default
   * options.
   *
   * @since 1.0.0
   *
   * @see is_plugin_active_for_network
   * @see plugin_basename
   * @see update_site_option
   * @see update_option
   * @global string ZEROSPAM_PLUGIN The plugin root directory.
   */
  private function activate()
  {
    // Install the DB tables.
    $this->install();

    if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) )
    {

      // Set the network's site default settings.
      update_site_option( 'zerospam_general_settings', $this->default_settings );
    }
    else
    {

      // Set the site default settings.
      update_option( 'zerospam_general_settings', $this->default_settings );
    }
  }

  /**
   * Installs plugin tables.
   *
   * Installs all of the required plugin tables.
   *
   * @since 2.0.0
   * @access private
   *
   * @see wpdb::get_row
   * @see dbDelta
   * @see update_option
   * @global string ZEROSPAM_PLUGIN The plugin root directory.
   */
  private function install()
  {
    global $wpdb;

    // Define the plugin table names.
    $log_table_name     = $wpdb->prefix . 'zerospam_log';
    $ip_table_name      = $wpdb->prefix . 'zerospam_blocked_ips';
    $ip_data_table_name = $wpdb->prefix . 'zerospam_ip_data';

    // Get the plugin database version.
    $current_version = get_option( 'zerospam_db_version' );

    // If no version available, first time install.
    if ( empty( $current_version ) )
    {
      /*
       * We'll set the default character set and collation for this table.
       * If we don't do this, some characters could end up being converted
       * to just ?'s when saved in our table.
       */
      $charset_collate = $wpdb->get_charset_collate();

      // Prepare the DB queries.
      $sql = false;

      // Check for the log table. If not available, create it.
      if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $log_table_name . '\'') != $log_table_name )
      {
        $sql = "CREATE TABLE $log_table_name (
               zerospam_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
               type varchar(255) NOT NULL DEFAULT 'Undefined Form',
               ip varchar(15) NOT NULL,
               date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
               page varchar(255) DEFAULT NULL,
               PRIMARY KEY  (zerospam_id),
               KEY type (type)
               ) $charset_collate;";
      }

      // Check for the IP table. . If not available, create it.
      if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $ip_table_name . '\'' ) != $ip_table_name )
      {
        $sql .= "CREATE TABLE $ip_table_name (
             zerospam_ip_id mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
             ip varchar(15) NOT NULL,
             type enum('permanent','temporary') NOT NULL DEFAULT 'temporary',
             start_date datetime DEFAULT NULL,
             end_date datetime DEFAULT NULL,
             reason varchar(255) DEFAULT NULL,
             PRIMARY KEY  (zerospam_ip_id),
             UNIQUE KEY ip (ip)
             ) $charset_collate;";
      }

      // Run the query is set.
      if ( $sql )
      {
        /**
         * Rather than executing an SQL query directly, we'll use the dbDelta
         * function in wp-admin/includes/upgrade.php.
         */
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
      }

      // Set the plugin DB version.
      update_option( 'zerospam_db_version', self::DB_VERSION );
    }

    // Check for available DB updates.
    elseif ( $current_version != self::DB_VERSION )
    {
      // 2.0 Updates.
      if ( version_compare( '2.0', $current_version ) ) {
        $wpdb->query( "ALTER TABLE `$log_table_name` CHANGE `type` `type` VARCHAR(255) NOT NULL DEFAULT 'Undefined Form';" );
      }

      // Set the updated plugin DB version.
      update_option( 'zerospam_db_version', self::DB_VERSION );
    }
  }

  /**
   * Checks for outdated DB tables.
   *
   * This hook is called once any activated plugins have been loaded. Is
   * generally used for immediate filter setup, or plugin overrides.
   *
   * @since 2.0.0
   * @access private
   *
   * @see get_option
   * @see dbDelta
   * @see update_option
   * @see ZeroSpam_Install::install
   * @global string ZEROSPAM_PLUGIN The plugin root directory.
   */
  public function plugins_loaded()
  {
    if ( get_option( 'zerospam_db_version' ) != self::DB_VERSION )
    {

      // If version outdated, upgrade.
      $this->install();
    }
  }
}
