<?php
/**
 * Plugin helpers
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 * @link https://benmarshall.me/wordpress-zero-spam/
 */

/**
 * Locations helper
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/inc/locations.php';

/**
 * Returns the geolocation information for a specified IP address.
 *
 * @param string $ip IP address.
 * @return array/false An array with the IP address location information or
 * false if not found.
 */
if ( ! function_exists( 'wpzerospam_get_ip_info' ) ) {
  function wpzerospam_get_ip_info( $ip ) {
    $options = wpzerospam_options();

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
 * Returns the human-readable spam type or an array of available spam types.
 *
 * @param string $type_key The key of the type that should be returned.
 * @return string/array The human-readable type name or an array of all the
 * available types.
 */
if ( ! function_exists( 'wpzerospam_types' ) ) {
  function wpzerospam_types( $type_key = false ) {
    $types = apply_filters( 'wpzerospam_types', [ 'blocked' => __( 'Access Blocked', 'wpzerospam' ) ] );

    if ( $type_key ) {
      if ( ! empty( $types[ $type_key ] ) ) {
        return $types[ $type_key ];
      }

      return $type_key;
    }

    return $types;
  }
}

/**
 * Checks if either the submission data or $_POST contain the wpzerospam_key and
 * if it matches whats in the database.
 *
 * @param array $submission_data An array of submission data that contains the
 * wpzerospam_key field
 * @return boolean true if the submission key matches the one in the database,
 * false if it doesn’t.
 */
if ( ! function_exists( 'wpzerospam_key_check' ) ) {
  function wpzerospam_key_check( $submission_data = false ) {
    if (
      $submission_data &&
      ! empty( $submission_data['wpzerospam_key'] ) &&
      $submission_data['wpzerospam_key'] == wpzerospam_get_key()
    ) {
      return true;
    }

    if ( ! empty( $_POST['wpzerospam_key'] ) && $_POST['wpzerospam_key'] == wpzerospam_get_key() ) {
      return true;
    }

    return false;
  }
}

/**
 * Sets the $_SERVER['REQUEST_URI'] for pages that extend WP_List_Table
 *
 * Fix for passing filters to WP_List_Table paging. See @link below.
 *
 * @since 4.8.2
 * @link https://wordpress.stackexchange.com/questions/67669/how-to-stop-wpnonce-and-wp-http-referer-from-appearing-in-url/185006#185006
 * @param array $query_args Array of the current query arguments for a table
 * query.
 * @return void
 */
if ( ! function_exists( 'wpzerospam_set_list_table_request_uri' ) ) {
  function wpzerospam_set_list_table_request_uri( $query_args ) {
    $paging_options = $query_args;
    unset( $paging_options['offset'] );
    unset( $paging_options['where'] );

    if ( ! empty( $query_args['where'] ) ) {
      foreach( $query_args['where'] as $key => $value ) {
        switch( $key ) {
          case 'blacklist_service':
            $paging_options['service'] = $value;
          break;
          case 'user_ip':
            $paging_options['s'] = $value;
          break;
          case 'blocked_type':
          case 'log_type':
            $paging_options['type'] = $value;
          break;
        }
      }
    }

    $_SERVER['REQUEST_URI'] = add_query_arg( $paging_options, $_SERVER['REQUEST_URI'] );
  }
}








/**
 * Query the database
 */
if ( ! function_exists( 'wpzerospam_query' ) ) {
  function wpzerospam_query( $table, $args = [], $return_total = false ) {
    global $wpdb;

    $sql = 'SELECT';

    if ( ! $return_total ) {
      if ( ! empty( $args['select'] ) ) {
        $sql .= ' ' . implode( ',', $args['select'] );
      } else {
        $sql .= ' *';
      }
    } else {
      $sql .= ' COUNT(*)';
    }

    $sql .= ' FROM ' . wpzerospam_tables( $table );

    if ( ! empty( $args['where'] ) ) {
      $sql .= ' WHERE';
      $cnt = 0;
      foreach( $args['where'] as $k => $v ) {
        if ( $cnt ) {
          $sql .= ' AND ';
        } else {
          $sql .= ' ';
        }

        if ( is_int( $v ) ) {
          $sql .= $k . ' = ' . $v;
        } else {
          $sql .= $k . ' = "' . $v . '"';
        }

        $cnt++;
      }
    }

    if ( ! empty( $args['orderby'] ) ) {
      $sql .= ' ORDER BY ' . $args['orderby'];
    }

    if ( ! empty( $args['order'] ) ) {
      $sql .= ' ' . $args['order'];
    }

    if ( ! $return_total ) {
      if ( ! empty( $args['limit'] ) ) {
        $sql .= ' LIMIT ' . $args['limit'];
      }

      if ( ! empty( $args['offset'] ) ) {
        $sql .= ', ' . $args['offset'];
      }
    }

    if ( ! $return_total ) {
      return $wpdb->get_results( $sql );
    } else {
      return $wpdb->get_var( $sql );
    }
  }
}











/**
 * Handles what happens when spam is detected
 */
if ( ! function_exists( 'wpzerospam_spam_detected' ) ) {
  function wpzerospam_spam_detected( $type, $data = [], $handle_error = true ) {
    $options = wpzerospam_options();
    $ip      = wpzerospam_ip();

    // Log the spam sttempt
    wpzerospam_log_spam( $type, $data );

    // Check if number attempts should result in a permanent block
    $blocked_ip = wpzerospam_get_blocked_ips( $ip );
    if ( $blocked_ip && $blocked_ip->attempts >= $options['auto_block_permanently'] ) {
      // Permanently block the IP
      wpzerospam_update_blocked_ip( $ip , [
        'blocked_type' => 'permanent',
        'reason'       => $type . ' (permanently auto-blocked)'
      ]);

    // Check if the IP should be temporarily auto-blocked
    } elseif ( 'enabled' == $options['auto_block_ips'] ) {

      $start_block = current_time( 'mysql' );
      $end_block   = new DateTime( $start_block );
      $end_block->add( new DateInterval( 'PT' . $options['auto_block_period'] . 'M' ) );

      wpzerospam_update_blocked_ip( $ip , [
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

    if ( ! empty( $data['ip'] ) ) {
      $ip_address = $data['ip'];
      unset( $data['ip'] );
    } else {
      $ip_address = wpzerospam_ip();
    }

    // Check is the spam detection should be shared
    if ( 'enabled' == $options['share_detections'] ) {
      wpzerospam_send_detection([
        'ip'   => $ip_address,
        'type' => $type
      ]);
    }

    // Check if spam logging is enabled, also check if type is 'denied'
    // (blocked IP address) & logging of blocked IPs is enabled.
    if ( 'enabled' != $options['log_spam'] ||
      ( 'blocked' == $type && 'enabled' != $options['log_blocked_ips'] )
    ) {
      // Logging disabled
      return false;
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
      'log'       => $wpdb->prefix . 'wpzerospam_log',
      'blocked'   => $wpdb->prefix . 'wpzerospam_blocked',
      'blacklist' => $wpdb->prefix . 'wpzerospam_blacklist'
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

    $options = wpzerospam_options();

    $integrations = [
      'ninja_forms' => 'ninja-forms/ninja-forms.php',
      'cf7'         => 'contact-form-7/wp-contact-form-7.php',
      'gforms'      => 'gravityforms/gravityforms.php',
      'fluentform'  => 'fluentform/fluentform.php',
      'wpforms'     => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
      'formidable'  => 'formidable/formidable.php',
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
    if ( empty( $options['blocked_message'] ) ) { $options['blocked_message'] = __( 'You have been blocked from visiting this site by WordPress Zero Spam due to detected spam activity.', 'wpzerospam' ); }
    if ( empty( $options['log_spam'] ) ) { $options['log_spam'] = 'disabled'; }
    if ( empty( $options['verify_comments'] ) ) { $options['verify_comments'] = 'enabled'; }
    if ( empty( $options['verify_registrations'] ) ) { $options['verify_registrations'] = 'enabled'; }
    if ( empty( $options['log_blocked_ips'] ) ) { $options['log_blocked_ips'] = 'disabled'; }
    if ( empty( $options['auto_block_permanently'] ) ) { $options['auto_block_permanently'] = 3; }
    if ( empty( $options['botscout_api'] ) ) { $options['botscout_api'] = false; }
    if ( empty( $options['ip_whitelist'] ) ) { $options['ip_whitelist'] = false; }

    if ( empty( $options['verify_cf7'] )  ) {
      $options['verify_cf7'] = 'enabled';
    }

    if ( empty( $options['share_detections'] )  ) {
      $options['share_detections'] = 'enabled';
    }

    if ( empty( $options['verify_gform'] )  ) {
      $options['verify_gform'] = 'enabled';
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

    if ( empty( $options['verify_fluentform'] ) ) {
      $options['verify_fluentform'] = 'enabled';
    }

    if ( empty( $options['verify_formidable'] ) ) {
      $options['verify_formidable'] = 'enabled';
    }

    if ( empty( $options['stop_forum_spam'] ) ) {
      $options['stop_forum_spam'] = 'enabled';
    }

    if ( empty( $options['strip_comment_links'] ) ) {
      $options['strip_comment_links'] = 'disabled';
    }

    if ( empty( $options['strip_comment_author_links'] ) ) {
      $options['strip_comment_author_links'] = 'disabled';
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

    if ( ! rest_is_ip_address( $ip ) ) { return false; }

    return $ip;
  }
}

/**
 * Return true/false if the IP is currently blocked
 */
if ( ! function_exists( 'wpzerospam_is_blocked' ) ) {
  function wpzerospam_is_blocked( $blocked_ip_entry ) {
    if ( 'permanent' == $blocked_ip_entry->blocked_type ) {
      return true;
    }

    $todays_date = new DateTime( current_time( 'mysql' ) );

    if ( ! empty( $blocked_ip_entry->start_block ) || ! empty( $blocked_ip_entry->end_block ) ) {
      $start_block = ! empty( $blocked_ip_entry->start_block ) ? new DateTime( $blocked_ip_entry->start_block ): false;
      $end_block   = ! empty( $is_blocked->end_block ) ? new DateTime( $is_blocked->end_block ): false;

      // @TODO - I'm sure there's a better way to handle this
      if (
        (
          $start_block && $end_block &&
          $todays_date->getTimestamp() >= $start_block->getTimestamp() &&
          $todays_date->getTimestamp() <= $end_block->getTimestamp()
        ) || (
          $start_block && ! $end_block &&
          $todays_date->getTimestamp() >= $start_block->getTimestamp()
        ) || (
          ! $start_block && $end_block &&
          $todays_date->getTimestamp() <= $end_block->getTimestamp()
        )
      ) {
        return true;
      }
    } else {
      return true;
    }

    return false;
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
      (
        ! is_singular() &&
        ! is_page() &&
        ! is_single() &&
        ! is_archive() &&
        ! is_home() &&
        ! is_front_page()
      )
    ) {
      return $access;
    }

    $options      = wpzerospam_options();
    $ip           = wpzerospam_ip();
    $access['ip'] = $ip;

    // Check whitelist
    $whitelist = $options['ip_whitelist'];
    if ( $whitelist ) {
      $whitelist = explode( PHP_EOL, $whitelist );
      foreach( $whitelist as $k => $whitelisted_ip ) {
        if ( $ip ==  $whitelisted_ip ) {
          return $access;
        }
      }
    }

    // Check if the current user's IP address has been blocked
    $is_blocked = wpzerospam_get_blocked_ips( $ip );
    if ( ! $is_blocked ) {
      // IP hasen't been blocked
      // If enabled, check the Stop Forum Spam blacklist
      if ( 'enabled' == $options['stop_forum_spam'] ) {
        $stop_forum_spam_is_spam = wpzerospam_stopforumspam_is_spam( $ip );
        if ( ! $stop_forum_spam_is_spam ) {
          // IP wasn't found in the Stop Forum Spam blacklist
          return $access;
        } else {
          // IP was found in the Stop Forum Spam blacklist
          $access['access'] = false;
          $access['reason'] = 'Stop Forum Spam';

          return $access;
        }
      }

      // If enabled, check the BotScout blacklist
      if ( 'enabled' == $options['botscout'] ) {
        $botscout_request = wpzerospam_botscout_is_spam( $ip );
        if ( ! $botscout_request ) {
          // IP wasn't found in the Stop Forum Spam blacklist
          return $access;
        } else {
          // IP was found in the Stop Forum Spam blacklist
          $access['access'] = false;
          $access['reason'] = 'BotScout';

          return $access;
        }
      }

      // Passed all tests
      return $access;
    }

    // Check if in the blacklist
    $in_blacklist = wpzerospam_in_blacklist( $ip );
    if ( $in_blacklist ) {
      // IP found in the blacklist
      $access['access'] = false;
      $access['reason'] = $in_blacklist->blacklist_service;

      return $access;
    }

    if ( wpzerospam_is_blocked( $is_blocked ) ) {
      $access['access'] = false;
      $access['reason'] = $is_blocked->reason;
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

/**
 * Queries the BotScout API
 *
 * @link http://botscout.com/api.htm
 *
 * @since 4.6.0
 */
if ( ! function_exists( 'wpzerospam_botscout_request' ) ) {
  function wpzerospam_botscout_request( $ip ) {
    $options = wpzerospam_options();

    if ( empty( $options['botscout_api'] ) ) { return false; }

    $api_url  = 'https://botscout.com/test/?';
    $params   = [ 'ip' => $ip, 'key' => $options['botscout_api'] ];
    $endpoint = $api_url . http_build_query( $params );
    $response = wp_remote_get( $endpoint );

    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
      $data = wp_remote_retrieve_body( $response );

      // Check if there's an error
      if ( strpos( $data, '!' ) === false ) {
        // Valid request
        list( $matched, $type, $count ) = explode( "|", $data );
        return [
          'matched' => ( $matched == 'Y' ) ? true : false,
          'count' => $count
        ];
      }
    }

    return false;
  }
}

/**
 * Checks the post submission for a valid key
 *
 * @since 4.5.0
 */
if ( ! function_exists( 'wpzerospam_botscout_is_spam' ) ) {
  function wpzerospam_botscout_is_spam( $ip ) {
    // First check if the IP is already in the blacklist table
    $in_blacklist = wpzerospam_in_blacklist( $ip );
    if ( $in_blacklist ) {
      // Check if the record should be updated
      $last_updated = strtotime( $in_blacklist->last_updated );
      $current_time = current_time( 'timestamp' );
      $expiration   = $last_updated + MONTH_IN_SECONDS;

      if ( $current_time > $expiration ) {
        // Expired, update the record
        $botscout_request = wpzerospam_botscout_request( $ip );
        if ( $botscout_request && ! empty( $botscout['matched'] ) ) {
          $botscout_request['blacklist_service'] = 'botscout';
          $botscout_request['blacklist_id'] = $in_blacklist->blacklist_id;
          wpzerospam_update_blacklist( $botscout_request );

          return $botscout_request;
        }
      }

      return $in_blacklist;
    }

    // Not in the blacklist, query the BotScout API now
    $botscout_request = wpzerospam_botscout_request( $ip );
    if (
      $botscout_request &&
      ! empty( $botscout_request['matched'] )
    ) {
      $new_record                      = $botscout_request;
      $new_record['ip']                = $ip;
      $new_record['blacklist_service'] = 'botscout';

      wpzerospam_update_blacklist( $new_record );

      return $new_record;
    }

    return false;
  }
}

/**
 * Queries the Stop Forum Spam API
 *
 * @since 4.5.0
 */
if ( ! function_exists( 'wpzerospam_stopforumspam_request' ) ) {
  function wpzerospam_stopforumspam_request( $ip ) {
    $api_url  = 'https://api.stopforumspam.org/api?';
    $params   = [ 'ip' => $ip, 'json' => '' ];
    $endpoint = $api_url . http_build_query( $params );
    $response = wp_remote_get( $endpoint );

    if ( is_array( $response ) && ! is_wp_error( $response ) ) {
      $data = wp_remote_retrieve_body( $response );
      $data = json_decode( $data, true );
      if ( ! empty( $data['success'] ) && ! empty( $data['ip'] ) ) {
        return $data['ip'];
      }
    }

    return false;
  }
}

/**
 * Add/update blacklisted IP
 *
 * @since 4.5.0
 */
if ( ! function_exists( 'wpzerospam_update_blacklist' ) ) {
  function wpzerospam_update_blacklist( $data ) {
    global $wpdb;

    $update = [
      'last_updated'   => current_time( 'mysql' ),
      'blacklist_data' => []
    ];

    if ( ! empty( $data['ip'] ) ) {
      $update['user_ip'] = $data['ip'];
    }

    if ( ! empty( $data['blacklist_service'] ) ) {
      $update['blacklist_service'] = $data['blacklist_service'];
    }

    if ( ! empty( $data['count'] ) ) {
      $update['blacklist_data']['count'] = intval( $data['count'] );
    }

    if ( ! empty( $data['appears'] ) ) {
      $update['blacklist_data']['appears'] = intval( $data['appears'] );
    }

    if ( ! empty( $data['confidence'] ) ) {
      $update['blacklist_data']['confidence'] = floatval( $data['confidence'] );
    }

    if ( ! empty( $data['frequency'] ) ) {
      $update['blacklist_data']['frequency'] = floatval( $data['frequency'] );
    }

    if ( ! empty( $data['lastseen'] ) ) {
      $update['blacklist_data']['lastseen'] = floatval( $data['lastseen'] );
    }

    if ( ! empty( $data['delegated'] ) ) {
      $update['blacklist_data']['delegated'] = floatval( $data['delegated'] );
    }

    if ( ! empty( $data['asn'] ) ) {
      $update['blacklist_data']['asn'] = floatval( $data['asn'] );
    }

    if ( ! empty( $data['country'] ) ) {
      $update['blacklist_data']['country'] = floatval( $data['country'] );
    }

    if ( ! empty( $update['blacklist_data'] ) ) {
      $update['blacklist_data'] = json_encode( $update['blacklist_data'] );
    }

    if ( ! empty( $data['blacklist_id'] ) ) {
      // Update
      $wpdb->update( wpzerospam_tables( 'blacklist' ), $update, [
        'blacklist_id' => $data['blacklist_id']
      ]);
      return true;
    }

    // Insert
    $wpdb->insert( wpzerospam_tables( 'blacklist' ), $update );
    return true;
  }
}

/**
 * Checks the post submission for a valid key
 *
 * @since 4.5.0
 */
if ( ! function_exists( 'wpzerospam_stopforumspam_is_spam' ) ) {
  function wpzerospam_stopforumspam_is_spam( $ip ) {
    // First check if the IP is already in the blacklist table
    $in_blacklist = wpzerospam_in_blacklist( $ip );
    if ( $in_blacklist ) {
      // Check if the record should be updated
      $last_updated = strtotime( $in_blacklist->last_updated );
      $current_time = current_time( 'timestamp' );
      $expiration   = $last_updated + MONTH_IN_SECONDS;

      if ( $current_time > $expiration ) {
        // Expired, update the record
        $stopforumspam_request = wpzerospam_stopforumspam_request( $ip );
        if ( $stopforumspam_request ) {
          $stopforumspam_request['blacklist_id'] = $in_blacklist->blacklist_id;
          wpzerospam_update_blacklist( $stopforumspam_request );

          return $stopforumspam_request;
        }
      }

      return $in_blacklist;
    }

    // Not in the blacklist, query the Stop Forum Spam API now
    $stopforumspam_request = wpzerospam_stopforumspam_request( $ip );
    if (
      $stopforumspam_request &&
      ! empty( $stopforumspam_request['appears'] ) &&
      'no' != $stopforumspam_request['appears']
    ) {
      $new_record                      = $stopforumspam_request;
      $new_record['ip']                = $ip;
      $new_record['blacklist_service'] = 'stopforumspam';

      wpzerospam_update_blacklist( $new_record );

      return $new_record;
    }

    return false;
  }
}

/**
 * Returns a record from the blacklist table if one exists
 *
 * @since 4.5.0
 */
if ( ! function_exists( 'wpzerospam_in_blacklist' ) ) {
  function wpzerospam_in_blacklist( $ip ) {
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare(
      'SELECT * FROM ' . wpzerospam_tables( 'blacklist' ) . ' WHERE user_ip = %s',
      $ip
    ));
  }
}

/**
 * Return all blacklisted IPs in the DB
 */
if ( ! function_exists( 'wpzerospam_get_blacklist' ) ) {
  function wpzerospam_get_blacklist( $args = [] ) {
    global $wpdb;

    return $wpdb->get_results( 'SELECT * FROM ' . wpzerospam_tables( 'blacklist' ) );
  }
}

/**
 * Sends a spam detection to the WordPress Zero Spam database
 */
if ( ! function_exists( 'wpzerospam_send_detection' ) ) {
  function wpzerospam_send_detection( $data ) {
    $api_url = 'https://zerospam.org/wp-json/wpzerospamapi/v1/detection/';

    if (
      empty( $data['ip'] ) ||
      empty( $data['type'] )
    ) {
      return false;
    }

    $request_args = [
      'method' => 'POST',
      'body'   => [
        'ip'   => $data['ip'],
        'type' => $data['type'],
        'site' => site_url()
      ],
      'sslverify' => true
    ];

    if ( WP_DEBUG ) {
      $request_args['sslverify'] = false;
    }

    $request = wp_remote_post( $api_url, $request_args );
    if ( is_wp_error( $request ) ) {
      return false;
    }

    return wp_remote_retrieve_body( $request );
  }
}
