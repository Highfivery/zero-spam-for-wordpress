<?php
class ZeroSpam_Install extends ZeroSpam_Plugin {
  public $db_version = '2.0';

  public function run() {
    // Called when the plugin is activated.
    register_activation_hook( ZEROSPAM_PLUGIN, array( $this, 'activate' ) );

    add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
  }

  public function activate() {
    $this->install();

    if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
      update_site_option( 'zerospam_general_settings', $this->default_settings );
    } else {
      update_option( 'zerospam_general_settings', $this->default_settings );
    }
  }

  /**
   * Installs the plugins DB tables.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Creating_Tables_with_Plugins
   */
  public function install() {
    global $wpdb;

    $log_table_name     = $wpdb->prefix . 'zerospam_log';
    $ip_table_name      = $wpdb->prefix . 'zerospam_blocked_ips';
    $ip_data_table_name = $wpdb->prefix . 'zerospam_ip_data';

    $current_version = get_option( 'zerospam_db_version' );

    if ( empty( $current_version ) ) {
      /*
       * We'll set the default character set and collation for this table.
       * If we don't do this, some characters could end up being converted
       * to just ?'s when saved in our table.
       */
      $charset_collate = '';

      if ( ! empty( $wpdb->charset ) ) {
        $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
      }

      if ( ! empty( $wpdb->collate ) ) {
        $charset_collate .= " COLLATE {$wpdb->collate}";
      }

      $sql = false;

      // Check for the log table.
      if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $log_table_name . '\'') != $log_table_name ) {
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

      // Check for the IP table.
      if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $ip_table_name . '\'' ) != $ip_table_name ) {
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

      if ( $sql ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
      }

      $options = (array) $this->settings;
      $options['registration_support'] = 1;
      $options['comment_support']      = 1;
      $options['log_spammers']         = 1;
      $options['wp_generator']         = 1;
      $options['cf7_support']          = 1;
      $options['gf_support']           = 1;
      $options['bp_support']           = 1;
      $options['nf_support']           = 1;
      $options['ip_location_support']  = 1;

      if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
        update_site_option( 'zerospam_general_settings', $options );
      } else {
        update_option( 'zerospam_general_settings', $options );
      }

      update_option( 'zerospam_db_version', $this->db_version );
    } elseif ( $current_version != $this->db_version ) {
      if ( version_compare( '2.0', $current_version ) ) {
        $wpdb->query( "ALTER TABLE `$log_table_name` CHANGE `type` `type` VARCHAR(255) NOT NULL DEFAULT 'Undefined Form';" );
      }

      update_option( 'zerospam_db_version', $this->db_version );
    }
  }

  /**
   * Uses plugins_loaded.
   *
   * This hook is called once any activated plugins have been loaded. Is
   * generally used for immediate filter setup, or plugin overrides.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded
   */
  public function plugins_loaded() {
    if ( get_option( 'zerospam_db_version' ) != $this->db_version ) {
      $this->install();
    }
  }
}