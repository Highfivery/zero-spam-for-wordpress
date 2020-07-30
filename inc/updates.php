<?php
/**
 * Contains plugin updates
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Check if any updates should be preformed
 */
add_action( 'init', function() {
  global $wpdb;
  $update_version = get_option( 'wpzerospam_update_version' );

  if ( ! $update_version ) {
    // Clear the blacklist.
    $wpdb->query( "TRUNCATE TABLE " . wpzerospam_tables( 'blacklist' ) );
    update_option( 'wpzerospam_update_version', '1' );
  }
});


/**
 * Fixes issue with upgrade from 3 to 4
 *
 * @since 4.2.0
 */
add_action( 'admin_init', function() {
  if(  ! function_exists( 'is_plugin_active' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  }

  // Deactive deprecated versions of the plugin & activate the new one
  if (
    is_plugin_active( 'zero-spam/zero-spam.php' ) &&
    function_exists( 'deactivate_plugins' )
  ) {
    deactivate_plugins( 'zero-spam/zero-spam.php', false, true );
  }

  if (
    ! is_plugin_active( 'zero-spam/wordpress-zero-spam.php' ) &&
    function_exists( 'activate_plugin' )
  ) {
    activate_plugin( 'zero-spam/wordpress-zero-spam.php', '', true );
  }
});
