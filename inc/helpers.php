<?php
/**
 * Plugin helpers
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Locations helper
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/inc/locations.php';

/**
 * Handles what happens when spam is detected
 */
if ( ! function_exists( 'wpzerospam_get_ip_info' ) ) {
  function wpzerospam_get_ip_info( $ip ) {
    $options  = wpzerospam_options();

    if ( empty( $options['ipstack_api'] ) ) { return false; }

    $base_url   = 'http://api.ipstack.com/';
    $remote_url = $base_url . $ip . '?access_key=' . $options['ipstack_api'];
    $response   = wp_remote_get( $remote_url );

    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
      $info = json_decode( $response['body'], true );

      return [
        'type'           => ! empty( $info['type'] ) ? sanitize_text_field( $info['type'] ) : false,
        'continent_code' => ! empty( $info['continent_code'] ) ? sanitize_text_field( $info['continent_code'] ) : false,
        'continent_name' => ! empty( $info['continent_name'] ) ? sanitize_text_field( $info['continent_name'] ) : false,
        'country_code'   => ! empty( $info['country_code'] ) ? sanitize_text_field( $info['country_code'] ) : false,
        'country_name'   => ! empty( $info['country_name'] ) ? sanitize_text_field( $info['country_name'] ) : false,
        'region_code'    => ! empty( $info['region_code'] ) ? sanitize_text_field( $info['region_code'] ) : false,
        'region_name'    => ! empty( $info['region_name'] ) ? sanitize_text_field( $info['region_name'] ) : false,
        'city'           => ! empty( $info['city'] ) ? sanitize_text_field( $info['city'] ) : false,
        'zip'            => ! empty( $info['zip'] ) ? sanitize_text_field( $info['zip'] ) : false,
        'latitude'       => ! empty( $info['latitude'] ) ? sanitize_text_field( $info['latitude'] ) : false,
        'longitude'      => ! empty( $info['longitude'] ) ? sanitize_text_field( $info['longitude'] ) : false,
        'flag'           => ! empty( $info['location']['country_flag'] ) ? sanitize_text_field( $info['location']['country_flag'] ) : false,
      ];
    }

    return false;
  }
}

/**
 * Handles what happens when spam is detected
 */
if ( ! function_exists( 'wpzerospam_get_log' ) ) {
  function wpzerospam_get_log( $args = [] ) {
    global $wpdb;

    return $wpdb->get_results( 'SELECT * FROM ' . wpzerospam_tables( 'log' ) );
  }
}

/**
 * Handles what happens when spam is detected
 */
if ( ! function_exists( 'wpzerospam_spam_detected' ) ) {
  function wpzerospam_spam_detected( $type, $data = [], $handle_error = true ) {
    $options = wpzerospam_options();

    wpzerospam_log_spam( $type, $data );

    // Check if the IP should be auto-blocked
    if ( 'enabled' == $options['auto_block_ips'] ) {

      $start_block = current_time( 'mysql' );
      $end_block   = new DateTime( $start_block );
      $end_block->add( new DateInterval( 'PT' . $options['auto_block_period'] . 'M' ) );

      wpzerospam_update_blocked_ip( wpzerospam_ip(), [
        'blocked_type' => 'temporary',
        'start_block'  => $start_block,
        'end_block'    => $end_block->format('Y-m-d G:i:s'),
        'reason'       => $type . ' (auto-blocked)'
      ]);
    }

    // Check if WordPress Zero Spam should handle the error. False for forms
    // that process via AJAX & expect a json response.
    if ( $handle_error ) {
      if ( 'redirect' == $options['spam_handler'] ) {
        wp_redirect( esc_url( $options['spam_redirect_url'] ) );
        exit();
      } else {
        status_header( 403 );
        die( $options['spam_message'] );
      }
    }
  }
}

/**
 * Checks the post submission for a valid key
 */
if ( ! function_exists( 'wpzerospam_key_check' ) ) {
  function wpzerospam_key_check() {
    if ( ! empty( $_POST['wpzerospam_key'] ) && $_POST['wpzerospam_key'] == wpzerospam_get_key() ) {
      return true;
    }

    return false;
  }
}

/**
 * Add a IP address to the blocked table
 */
if ( ! function_exists( 'wpzerospam_update_blocked_ip' ) ) {
  function wpzerospam_update_blocked_ip( $ip, $args = [] ) {
    global $wpdb;

    $options = wpzerospam_options();

    $record = wp_parse_args( $args, [
      'blocked_type' => 'permanent',
      'date_added'   => current_time( 'mysql' ),
      'start_block'  => false,
      'end_block'    => false,
      'reason'       => false,
      'attempts'     => 1
    ]);

    $record['user_ip'] = $ip;

    // First, check if the IP is already in the DB
    $check = wpzerospam_get_blocked_ips( $record['user_ip'] );
    if ( $check ) {
      $attempts = $check->attempts;
      $attempts++;

      // IP exists, update accordingly
      $update = [ 'attempts' => $attempts ];

      if ( $record['blocked_type'] && $record['blocked_type'] != $check->blocked_type ) {
        $update['blocked_type'] = $record['blocked_type'];
      }

      if ( $record['start_block'] && $record['start_block'] != $check->start_block ) {
        $update['start_block'] = $record['start_block'];
      }

      if ( $record['end_block'] && $record['end_block'] != $check->end_block ) {
        $update['end_block'] = $record['end_block'];
      }

      if ( $record['reason'] && $record['reason'] != $check->reason ) {
        $update['reason'] = $record['reason'];
      }

      if ( $update ) {
        $wpdb->update( wpzerospam_tables( 'blocked' ), $update, [
          'blocked_id' => $check->blocked_id
        ]);
      }
    } else {
      // IP doesn't exist, add it
      $wpdb->insert( wpzerospam_tables( 'blocked' ), $record );
    }
  }
}

/**
 * Create a log entry if logging is enabled
 */
if ( ! function_exists( 'wpzerospam_log_spam' ) ) {
  function wpzerospam_log_spam( $type, $data = [] ) {
    global $wpdb;

    $options = wpzerospam_options();

    // Check if spam logging is enabled, also check if type is 'denied'
    // (blocked IP address) & logging of blocked IPs is enabled.
    if (
      'enabled' != $options['log_spam'] ||
      ( 'denied' == $type && 'enabled' != $options['log_blocked_ips'] )
    ) {
      // Logging disabled
      return false;
    }

    if ( ! empty( $data['ip'] ) ) {
      $ip_address = $data['ip'];
      unset( $data['ip'] );
    } else {
      $ip_address = wpzerospam_ip();
    }

    $current_url   = wpzerospam_current_url();
    $location_info = wpzerospam_get_ip_info( $ip_address );

    // Add record to the database
    $record = [
      'log_type'        => $type,
      'user_ip'         => wpzerospam_ip(),
      'date_recorded'   => current_time( 'mysql' ),
      'page_url'        => $current_url['full'],
      'submission_data' => json_encode( $data )
    ];

    if ( $location_info ) {
      $record['country']   = $location_info['country_code'];
      $record['region']    = $location_info['region_code'];
      $record['city']      = $location_info['city'];
      $record['latitude']  = $location_info['latitude'];
      $record['longitude'] = $location_info['longitude'];
    }

    $wpdb->insert( wpzerospam_tables( 'log' ), $record );
  }
}

/**
 * Returns an array of tables the plugin uses
 */
if ( ! function_exists( 'wpzerospam_tables' ) ) {
  function wpzerospam_tables( $key = false ) {
    global $wpdb;

    $tables = [
      'log'     => $wpdb->prefix . 'wpzerospam_log',
      'blocked' => $wpdb->prefix . 'wpzerospam_blocked'
    ];

    if ( ! $key ) {
      return $tables;
    } elseif( ! empty( $tables[ $key ] ) ) {
      return $tables[ $key ];
    }

    return false;
  }
}

/**
 * Returns the generated key for checking submissions
 */
if ( ! function_exists( 'wpzerospam_get_key' ) ) {
  function wpzerospam_get_key() {
    $key = get_option( 'wpzerospam_key' );
    if ( ! $key ) {
      $key = wp_generate_password( 64 );
      update_option( 'wpzerospam_key', $key );
    }

    return $key;
  }
}

/**
 * Validates a post submission
 */
if ( ! function_exists( 'wpzerospam_validate_submission' ) ) {
  function wpzerospam_validate_submission() {
    if ( ! empty( $_POST['wpzerospam'] ) && wpzerospam_get_key() == $_POST['wpzerospam'] ) {
      return true;
    }

    return false;
  }
}

/**
 * Returns an array of blocked IPs or an individual IP's details
 */
if ( ! function_exists( 'wpzerospam_get_blocked_ips' ) ) {
  function wpzerospam_get_blocked_ips( $ip = false ) {
    global $wpdb;

    if ( ! $ip ) {
      return $wpdb->get_results( 'SELECT * FROM ' . wpzerospam_tables( 'blocked' ) );
    }

    return $wpdb->get_row($wpdb->prepare(
      'SELECT * FROM ' . wpzerospam_tables( 'blocked' ) . ' WHERE user_ip = %s',
      $ip
    ));
  }
}

/**
 * Adds a access attempt from a blocked user
 */
if ( ! function_exists( 'wpzerospam_attempt_blocked' ) ) {
  function wpzerospam_attempt_blocked( $reason ) {
    global $wpdb;

    $options    = wpzerospam_options();
    $ip_address = wpzerospam_ip();

    $is_blocked = wpzerospam_get_blocked_ips( $ip_address );
    if ( $is_blocked ) {
      // IP already exists in the database
      $attempts = $is_blocked->attempts;
      $attempts++;

      $wpdb->update( wpzerospam_tables( 'blocked' ), [
        'attempts' => $attempts
      ], [
        'blocked_id' => $is_blocked->blocked_id
      ]);
    }

    wpzerospam_log_spam( 'blocked' );

    if ( 'redirect' == $options['block_handler'] ) {
      wp_redirect( esc_url( $options['blocked_redirect_url'] ) );
      exit();
    } else {
      status_header( 403 );
      die( $options['blocked_message'] );
    }
  }
}

/**
 * Checks if a specific plugin integration is turned on & plugin active.
 */
if ( ! function_exists( 'wpzerospam_plugin_integration_enabled' ) ) {
  function wpzerospam_plugin_integration_enabled( $plugin ) {
    if(  ! function_exists( 'is_plugin_active' ) ) {
      require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $options = get_option( 'wpzerospam' );

    $integrations = [
      'ninja_forms' => 'ninja-forms/ninja-forms.php',
      'cf7'         => 'contact-form-7/wp-contact-form-7.php',
      'gforms'      => 'gravityforms/gravityforms.php',
      'wpforms'     => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ]
    ];

    // Handle BuddyPress check a little differently for presence of a function
    if ( 'bp_registrations' == $plugin ) {
      if (
        ! empty( $options['verify_bp_registrations'] ) &&
        'enabled' == $options['verify_bp_registrations']
      ) {
        return true;
      } else {
        return false;
      }
    }

    // Handling all other plugin checks
    if (
      ! empty( $options['verify_' . $plugin] ) &&
      'enabled' == $options['verify_' . $plugin ] &&
      ! empty( $integrations[ $plugin ] )
    ) {
      if ( is_array( $integrations[ $plugin ] ) ) {
        // Check at least one of the defined plugins are active
        foreach( $integrations[ $plugin ] as $key => $value ) {
          if ( is_plugin_active( $value ) ) {
            return true;
          }
        }
      } else {
        // Check if one plugin is active
        if ( is_plugin_active( $integrations[ $plugin ] ) ) {
          return true;
        }
      }
    }

    return false;
  }
}

/**
 * Returns the plugin settings.
 */
if ( ! function_exists( 'wpzerospam_options' ) ) {
  function wpzerospam_options() {
    $options = get_option( 'wpzerospam' );

    if ( empty( $options['share_data'] ) ) { $options['share_data'] = 'enabled'; }
    if ( empty( $options['auto_block_ips'] ) ) { $options['auto_block_ips'] = 'disabled'; }
    if ( empty( $options['auto_block_period'] ) ) { $options['auto_block_period'] = 30; }
    if ( empty( $options['blocked_redirect_url'] ) ) { $options['blocked_redirect_url'] = 'https://www.google.com'; }
    if ( empty( $options['spam_handler'] ) ) { $options['spam_handler'] = '403'; }
    if ( empty( $options['block_handler'] ) ) { $options['block_handler'] = '403'; }
    if ( empty( $options['spam_redirect_url'] ) ) { $options['spam_redirect_url'] = 'https://www.google.com'; }
    if ( empty( $options['spam_message'] ) ) { $options['spam_message'] = __( 'There was a problem with your submission. Please go back and try again.', 'wpzerospam' ); }
    if ( empty( $options['blocked_message'] ) ) { $options['blocked_message'] = __( 'You have been blocked from visiting this site.', 'wpzerospam' ); }
    if ( empty( $options['log_spam'] ) ) { $options['log_spam'] = 'disabled'; }
    if ( empty( $options['verify_comments'] ) ) { $options['verify_comments'] = 'enabled'; }
    if ( empty( $options['verify_registrations'] ) ) { $options['verify_registrations'] = 'enabled'; }
    if ( empty( $options['log_blocked_ips'] ) ) { $options['log_blocked_ips'] = 'disabled'; }

    if ( empty( $options['verify_cf7'] )  ) {
      $options['verify_cf7'] = 'enabled';
    }

    if ( empty( $options['verify_gforms'] )  ) {
      $options['verify_gforms'] = 'enabled';
    }

    if ( empty( $options['verify_ninja_forms'] )  ) {
      $options['verify_ninja_forms'] = 'enabled';
    }

    if ( empty( $options['verify_bp_registrations'] ) ) {
      $options['verify_bp_registrations'] = 'enabled';
    }

    if ( empty( $options['verify_wpforms'] ) ) {
      $options['verify_wpforms'] = 'enabled';
    }

    return $options;
  }
}

/**
 * Returns the current user's IP address
 */
if ( ! function_exists( 'wpzerospam_ip' ) ) {
  function wpzerospam_ip() {
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif ( ! empty($_SERVER['HTTP_X_FORWARDED'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif ( ! empty($_SERVER['HTTP_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif ( ! empty($_SERVER['HTTP_FORWARDED'])) {
      $ip = $_SERVER['HTTP_FORWARDED'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    $ip = explode( ',', $ip );
    $ip = trim( $ip[0] );

    if ( false === WP_Http::is_ip_address( $ip ) ) { return false; }

    return $ip;
  }
}

/**
 * Checks to see if the current user has access to the site
 */
if ( ! function_exists( 'wpzerospam_check_access' ) ) {
  function wpzerospam_check_access() {
    $access = [
      'access' => true
    ];

    // Ignore admin dashboard & login page checks
    if (
      is_admin() || wpzerospam_is_login() ||
      ( ! is_singular() && ! is_page() && ! is_single() && ! is_archive() && ! is_home() && ! is_front_page() )
    ) {
      return $access;
    }

    $options = wpzerospam_options();

    // Check if the current user's IP address has been blocked
    $ip          = wpzerospam_ip();
    $is_blocked  = wpzerospam_get_blocked_ips( $ip );

    if ( ! $is_blocked ) {
      return $access;
    }

    if ( 'permanent' == $is_blocked->blocked_type ) {
      $access['access'] = false;
      $access['ip']     = $ip;
      $access['reason'] = $is_blocked->reason;
    } else {
      $todays_date = new DateTime( current_time( 'mysql' ) );

      if ( ! empty( $is_blocked->start_block ) || ! empty( $is_blocked->end_block ) ) {
        $start_block = ! empty( $is_blocked->start_block ) ? new DateTime( $is_blocked->start_block ): false;
        $end_block   = ! empty( $is_blocked->end_block ) ? new DateTime( $is_blocked->end_block ): false;

        // @TODO - I'm sure there's a better way to handle this
        if (
          $start_block && $end_block &&
          $todays_date->getTimestamp() >= $start_block->getTimestamp() &&
          $todays_date->getTimestamp() <= $end_block->getTimestamp()
        ) {
          $access['access'] = false;
          $access['ip']     = $ip;
          $access['reason'] = $is_blocked->reason;
        } elseif (
          $start_block && ! $end_block &&
          $todays_date->getTimestamp() >= $start_block->getTimestamp()
        ) {
          $access['access'] = false;
          $access['ip']     = $ip;
          $access['reason'] = $is_blocked->reason;
        } elseif (
          ! $start_block && $end_block &&
          $todays_date->getTimestamp() <= $end_block->getTimestamp()
        ) {
          $access['access'] = false;
          $access['ip']     = $ip;
          $access['reason'] = $is_blocked->reason;
        }
      } else {
        $access['access'] = false;
        $access['ip']     = $ip;
        $access['reason'] = $is_blocked->reason;
      }
    }

    return $access;
  }
}

/**
 * Determines if the current page is the login page
 */
if ( ! function_exists( 'wpzerospam_is_login' ) ) {
  function wpzerospam_is_login() {
    $login_url   = wp_login_url();
    $current_url = wpzerospam_current_url();

    if ( $login_url == $current_url['full'] ) {
      return true;
    }

    return false;
  }
}

/**
 * Get the user's current URL
 */
if ( ! function_exists( 'wpzerospam_current_url' ) ) {
  function wpzerospam_current_url() {
    $url = [];

    $url['full'] = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url = array_merge( $url, parse_url( $url['full'] ) );

    // Parse the URL query string
    if ( ! empty( $url['query'] ) ) {
      parse_str( $url['query'], $url['query'] );
    }

    return $url;
  }
}
