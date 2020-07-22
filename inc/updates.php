<?php
/**
 * Contains plugin updates
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */


/**
 * Transfers the blocked IPs that used to exist in options to the dedicated
 * table.
 *
 * @since 4.2.0
 */
add_action( 'admin_init', function() {
  $options = wpzerospam_options();

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

  if ( ! empty( $options['blocked_ips'] ) ) {
    // IPs found, transfer them to the database
    foreach( $options['blocked_ips'] as $key => $ip ) {
      if ( ! empty( $ips ) ) {
        wpzerospam_update_blocked_ip( $ip['ip_address'], [
          'reason' => $ip['reason']
        ]);
      }
    }

    unset( $options['blocked_ips'] );
    update_option( 'wpzerospam', $options );
  }
});
