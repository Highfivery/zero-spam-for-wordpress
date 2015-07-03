<?php
/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function zerospam_settings() {
  return (array) get_option( 'zerospam_general_settings' );
}

function zerospam_get_key() {
  if ( ! $key = get_option( 'zerospam_key' ) ) {
    $key = wp_generate_password( 64 );
    update_option( 'zerospam_key', $key );
  }

  return $key;
}

function zerospam_is_valid() {
  if (  ! empty( $_POST['zerospam_key'] ) && $_POST['zerospam_key'] == zerospam_get_key() ) {
    return true;
  }

  return false;
}

function zerospam_get_ip() {
  $ipaddress = '';

  if ( getenv('HTTP_CLIENT_IP') ) {
    $ipaddress = getenv('HTTP_CLIENT_IP');
  } else if ( getenv('HTTP_X_FORWARDED_FOR') ) {
    $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
  } else if ( getenv('HTTP_X_FORWARDED') ) {
    $ipaddress = getenv('HTTP_X_FORWARDED');
  } else if ( getenv('HTTP_FORWARDED_FOR') ) {
    $ipaddress = getenv('HTTP_FORWARDED_FOR');
  } else if ( getenv('HTTP_FORWARDED') ) {
    $ipaddress = getenv('HTTP_FORWARDED');
  } else if ( getenv('REMOTE_ADDR') ) {
    $ipaddress = getenv('REMOTE_ADDR');
  } else {
    $ipaddress = 'UNKNOWN';
  }

  return $ipaddress;
}

function zerospam_log_spam( $key, $url ) {
  global $wpdb;

  $settings   = zerospam_settings();
  $ip         = zerospam_get_ip();
  $table_name = $wpdb->prefix . 'zerospam_log';

  switch( $key ) {
    case 'registration':
      $key = 1;
      break;
    case 'comment':
      $key = 2;
      break;
    case 'cf7':
      $key = 3;
      break;
    case 'gf':
      $key = 4;
      break;
    case 'buddypress-registration':
      $key = 5;
      break;
  }

  $wpdb->insert( $table_name, array(
      'type' => $key,
      'ip'   => $ip,
      'page' => $url,
    ),
    array(
      '%s',
      '%s',
      '%s',
    )
  );

  if ( ! empty( $settings['auto_block'] )  && $settings['auto_block'] ) {
    zerospam_block_ip( array(
      'ip'     => $ip,
      'type'   => 'permanent',
      'reason' => __( 'Auto block triggered on ', 'zerospam' ) . date( 'r' ) . '.'
    ));
  }
}

function zerospam_is_blocked( $ip ) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'zerospam_blocked_ips';
  $check      = $this->_get_blocked_ip( $ip );

  if ( ! $check ) {
    return false;
  }

  // Check block type
  if (
    'temporary' == $check->type &&
    time() >= strtotime( $check->start_date ) &&
    time() <= strtotime( $check->end_date )
    ) {
    return true;
  }

  if ( 'permanent' == $check->type ) {
    return true;
  }

  return false;
}

function zerospam_get_blocked_ip( $ip ) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'zerospam_blocked_ips';
  $query      = $wpdb->get_row( "SELECT * FROM $table_name WHERE ip = '" . $ip . "'" );

  if ( null == $query ) {
    return false;
  }

  return $query;
}

function zerospam_block_ip( $args ) {
  global $wpdb;

  $table_name = $wpdb->prefix . 'zerospam_blocked_ips';
  $ip         = isset( $args['ip'] ) ? $args['ip'] : false;
  $type       = isset( $args['type'] ) ? $args['type'] : 'temporary';

  if ( $ip ) {
    // Check is IP has already been blocked.
    if ( $this->_is_blocked( $ip, false ) ) {

      // Update existing record.
      $wpdb->update(
        $table_name,
        array(
          'type'       => $type,
          'start_date' => isset( $args['start_date'] ) ? $args['start_date'] : null,
          'end_date'   => isset( $args['end_date'] ) ? $args['end_date'] : null,
          'reason'     => $args['reason'],
        ),
        array( 'ip' => $ip ),
        array(
          '%s',
          '%s',
          '%s',
          '%s',
        ),
        array( '%s' )
      );
    } else {

      // Insert new record.
      $insert = array(
        'ip'   => $ip,
        'type' => $type,
      );

      if ( 'temporary' == $type ) {
        $insert['start_date'] = $args['start_date'];
        $insert['end_date'] = $args['end_date'];
      }

      if ( isset( $args['reason'] ) && $args['reason'] ) {
        $insert['reason'] = $args['reason'];
      }

      $wpdb->insert(
        $table_name,
        $insert,
        array(
          '%s',
          '%s',
          '%s',
          '%s',
          '%s',
        )
      );
    }
  }
}