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

  if ( ! empty( $options['blocked_ips'] ) ) {
    // IPs found, transfer them to the database
    foreach( $options['blocked_ips'] as $key => $ip ) {
      if ( ! empty( $ips ) ) {
        wpzerospam_add_blocked_ip( $ip['ip_address'], [
          'reason' => $ip['reason']
        ]);
      }
    }

    unset( $options['blocked_ips'] );
    update_option( 'wpzerospam', $options );
  }
});
