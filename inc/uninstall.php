<?php
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
          delete_option( 'wpzerospam_honeypot' );
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
      delete_option( 'wpzerospam_honeypot' );
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
