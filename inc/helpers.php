<?php
/**
 * Plugin helpers
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 * @link https://benmarshall.me/wordpress-zero-spam/
 */

/**
 * Sets a cookie
 */
if ( ! function_exists( 'wpzerospam_set_cookie' ) ) {
  function wpzerospam_set_cookie( $key, $value ) {
    $options    = wpzerospam_options();
    $expiration = current_time( 'timestamp' ) + ( $options['cookie_expiration'] * DAY_IN_SECONDS );

    setcookie( 'wpzerospam_' . $key, $value, $expiration, COOKIEPATH, COOKIE_DOMAIN );
  }
}

/**
 * Get a cookie
 */
if ( ! function_exists( 'wpzerospam_get_cookie' ) ) {
  function wpzerospam_get_cookie( $key ) {
    if ( ! empty( $_COOKIE[ 'wpzerospam_' . $key ] ) ) {
      return $_COOKIE[ 'wpzerospam_' . $key ];
    }

    return false;
  }
}

/**
 * Check access
 *
 * Determines if the current user IP should have access to the site.
 *
 * @return array Includes info about the access check.
 */
if ( ! function_exists( 'wpzerospam_check_access' ) ) {
  function wpzerospam_check_access() {
    $ip = wpzerospam_ip();
    //$ip = '46.229.168.150'; // StopForumSpam testing IP
    //$ip = '120.40.130.70'; // BotScout testing IP

    // Innocent until proven guilty...
    $access = [ 'access' => true, 'ip' => $ip ];

    // Always allow authenticated users & users trying to log in access
    if ( is_user_logged_in() || wpzerospam_is_login() ) {
      return $access;
    }

    /**
     * Only check access for actual page vists. Some resource requests like
     * favicons fire this function which causes duplicate entries in the DB.
     *
     * @TODO - Find a way to avoid these checks & ensure this function only gets
     * fired on page requests vs. resources.
     */
    if (
      ! is_singular() && ! is_page() && ! is_single() && ! is_archive() &&
      ! is_home() && ! is_front_page()
    ) {
      return $access;
    }

    $options = wpzerospam_options();

    // 1. Check whitelisted IP addresses
    $whitelist = wpzerospam_get_whitelist();
    if ( $whitelist && array_key_exists( $ip, $whitelist ) ) {
      return $access;
    }

    // 2. Check if the user's IP address has been blocked
    $blocked = wpzerospam_is_blocked( $ip );
    if ( $blocked ) {
      $access['access'] = false;
      $access['reason'] = $blocked['reason'];

      return $access;
    }

    // 3. Check the blacklist
    $blacklisted = wpzerospam_is_blacklisted( $ip );
    if ( $blacklisted ) {
      /**
       * IP found in the blacklist, check to see if the record needs to be
       * updated.
       */
      $api_blacklisted = wpzerospam_is_api_blacklisted( $ip, $blacklisted );
      if ( $api_blacklisted ) {
        // IP address blacklisted record found & updated
        $access['access'] = false;
        $access['reason'] = $api_blacklisted['blacklist_service'];

        return $access;
      }
    }

    // 4. Check the API blacklists
    $api_blacklisted = wpzerospam_is_api_blacklisted( $ip );
    if ( $api_blacklisted ) {
      $access['access'] = false;
      $access['reason'] = $api_blacklisted['blacklist_service'];

      return $access;
    }

    return $access;
  }
}

/**
 * Checks & updates blacklist records from APIs
 *
 * @param string $ip IP address to check.
 * @param array $blacklisted_record IP blacklist record from the DB
 * @return boolean/array False is not blacklisted, otherwise an array with the
 *                        blacklisted IP information.
 */
if ( ! function_exists( 'wpzerospam_is_api_blacklisted' ) ) {
  function wpzerospam_is_api_blacklisted( $ip, $blacklisted_record = false ) {
    global $wpdb;

    // No need to check everytime a user visits a page
    if ( wpzerospam_get_cookie( 'api_blacklist' ) ) { return false; }
    wpzerospam_set_cookie( 'api_blacklist', current_time( 'timestamp' ) );

    $options = wpzerospam_options();

    if ( $blacklisted_record ) {
      // Check/update existing blacklist record

      $last_updated = strtotime( $blacklisted_record['last_updated'] );
      $current_time = current_time( 'timestamp' );
      $expiration   = $last_updated + MONTH_IN_SECONDS;

      if ( $current_time > $expiration ) {
        // Expired, update the blacklist record in the DB
        $query = wpzerospam_query_blacklist_api(
          $ip,
          $blacklisted_record['blacklist_service']
        );

        if ( $query ) {
          if (
            // Check for Stop Forum Spam confidence level
            ( ! empty( $query['confidence'] ) && $query['confidence'] < $options['stopforumspam_confidence_min'] ) ||
            // Check for BotScout counts
            ( ! empty( $query['count'] ) && $query['count'] < $options['botscout_count_min'] )
          ) {
            // Does not meet the stopforumspam confidence minimum, delete record
            $wpdb->delete( wpzerospam_tables( 'blacklist' ), [
              'blacklist_id' => $blacklisted_record['blacklist_id']
            ]);

            return false;
          } else {
            // Blacklist API found a matching record, update the DB one
            $blacklisted_record['last_updated']   = current_time( 'mysql' );
            $blacklisted_record['blacklist_data'] = json_encode( $query );

            $wpdb->update( wpzerospam_tables( 'blacklist' ), $blacklisted_record, [
              'blacklist_id' => $blacklisted_record['blacklist_id']
            ]);

            return $blacklisted_record;
          }
        } else {
          // Blacklist API didn't find a matching record, delete the DB one
          $wpdb->delete( wpzerospam_tables( 'blacklist' ), [
            'blacklist_id' => $blacklisted_record['blacklist_id']
          ]);

          return false;
        }
      } else {
        // Not expired
        return $blacklisted_record;
      }
    } else {
      // Check all available blacklist APIs
      $stopforumspam = wpzerospam_query_blacklist_api( $ip, 'stopforumspam' );
      if ( $stopforumspam ) {
        if ( ! empty( $stopforumspam['confidence'] ) && $stopforumspam['confidence'] < $options['stopforumspam_confidence_min'] ) {
          // Does not meet the stopforumspam confidence minimum, delete record
          $wpdb->delete( wpzerospam_tables( 'blacklist' ), [
            'user_ip' => $ip
          ]);

          return false;
        }

        $blacklisted_record = [
          'blacklist_service' => 'stopforumspam',
          'user_ip'           => $ip,
          'last_updated'      => current_time( 'mysql' ),
          'blacklist_data'    => json_encode( $stopforumspam )
        ];

        $wpdb->replace( wpzerospam_tables( 'blacklist' ), $blacklisted_record );

        return $blacklisted_record;
      }

      $botscout = wpzerospam_query_blacklist_api( $ip, 'botscout' );
      if ( $botscout ) {
        if ( ! empty( $botscout['count'] ) && $botscout['count'] < $options['botscout_count_min'] ) {
          // Does not meet the botscout count minimum, delete record
          $wpdb->delete( wpzerospam_tables( 'blacklist' ), [
            'user_ip' => $ip
          ]);

          return false;
        }

        $blacklisted_record = [
          'blacklist_service' => 'botscout',
          'user_ip'           => $ip,
          'last_updated'      => current_time( 'mysql' ),
          'blacklist_data'    => json_encode( $botscout )
        ];

        $wpdb->replace( wpzerospam_tables( 'blacklist' ), $blacklisted_record );

        return $blacklisted_record;
      }
    }

    return false;
  }
}

/**
 * Adds a access attempt from a blocked user
 *
 * @param string $reason The reason for the block
 */
if ( ! function_exists( 'wpzerospam_attempt_blocked' ) ) {
  function wpzerospam_attempt_blocked( $ip, $reason ) {
    global $wpdb;

    $options = wpzerospam_options();

    // Check blocked tables
    $blocked = wpzerospam_is_blocked( $ip );
    if ( $blocked ) {
      // IP already exists in the blocked IP table, increment attempt
      $attempts = $blocked['attempts'];
      $attempts++;

      $wpdb->update( wpzerospam_tables( 'blocked' ), [
        'attempts' => $attempts
      ], [
        'blocked_id' => $blocked['blocked_id']
      ]);
    }

    // Check $blacklisted table
    $blacklisted = wpzerospam_is_blacklisted( $ip );
    if ( $blacklisted ) {
      // IP already exists in the blacklisted IP table, increment attempt
      $attempts = $blacklisted['attempts'];
      $attempts++;

      $wpdb->update( wpzerospam_tables( 'blacklist' ), [
        'attempts' => $attempts
      ], [
        'blacklist_id' => $blacklisted['blacklist_id']
      ]);
    }

    wpzerospam_log_detection( 'blocked', [ 'reason' => $reason ] );

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
 * Logs a spam detection.
 *
 * This functions logs (if enabled) detections & handles sharing those
 * detections with Zero Spam (if enabled).
 *
 * @since 4.9.7
 *
 * @param string $type Machine-readable name of the detection type. Pass an 'ip'
 *                     key to define a specific IP address vs. inferring it
 *                     from the current users IP address.
 * @param array  $data Optional. Array of additional information to log.
 */
if ( ! function_exists( 'wpzerospam_log_detection' ) ) {
  function wpzerospam_log_detection( $type, $data = [] ) {
    global $wpdb;
    $options = wpzerospam_options();

    // Setup the detection record.
    $record = [
      'user_ip'       => wpzerospam_ip(),
      'log_type'      => $type,
      'date_recorded' => current_time( 'mysql' )
    ];

    // Check if an IP address is present, if not, get it from the current user.
    if ( ! empty( $data['ip'] ) && rest_is_ip_address( $data['ip'] ) ) {
      $record['user_ip'] = $data['ip'];
    }

    // Make sure an IP address was found.
    if (
      empty( $record['user_ip'] ) ||
      ! rest_is_ip_address( $record['user_ip'] )
    ) {
      return false;
    }

    // If sharing detections is enabled, send the detection to Zero Spam.
    if ( 'enabled' == $options['share_detections'] ) {
      wpzerospam_share_detection([
        'ip'   => $record['user_ip'],
        'type' => $record['log_type']
      ]);
    }

    // Check if logging detections & 'blocks' are enabled.
    if (
      'enabled' != $options['log_spam'] ||
      ('blocked' == $record['log_type'] && 'enabled' != $options['log_blocked_ips'])
    ) {
      // Logging disabled.
      return false;
    }

    // Logging enabled, get the current URL & IP location information.
    $location    = wpzerospam_get_ip_info( $record['user_ip'] );
    $current_url = wpzerospam_current_url();

    // Add additional information to the detection record.
    $record['page_url']        = ! empty( $current_url['full'] ) ? $current_url['full'] : false;
    $record['submission_data'] = json_encode( $data );

    if ( $location ) {
      $record['country']   = ! empty( $location['country_code'] ) ? $location['country_code'] : false;
      $record['region']    = ! empty( $location['region_code'] ) ? $location['region_code'] : false;
      $record['city']      = ! empty( $location['city'] ) ? $location['city'] : false;
      $record['latitude']  = ! empty( $location['latitude'] ) ? $location['latitude'] : false;
      $record['longitude'] = ! empty( $location['longitude'] ) ? $location['longitude'] : false;
    }

    return $wpdb->insert( wpzerospam_tables( 'log' ), $record );
  }
}

/**
 * Shares a detection with the Zero Spam database.
 */
function wpzerospam_share_detection( $data ) {
  // The Zero Spam API endpoint for sharing detections.
  $api_url = 'https://zerospam.org/wp-json/wpzerospamapi/v1/detection/';

  // Make sure a type & valid IP address are provided.
  if (
    empty( $data['ip'] ) ||
    ! rest_is_ip_address( $data['ip'] ) ||
    empty( $data['type'] )
  ) {
    return false;
  }

  // Setup the request parameters.
  $request_args = [
    'method' => 'POST',
    'body'   => [
      'ip'        => $data['ip'],
      'type'      => $data['type'],
      'site'      => site_url(),
      'email'     => get_bloginfo( 'admin_email' ),
      'wpversion' => get_bloginfo( 'version' ),
      'name'      => get_bloginfo( 'name' ),
      'desc'      => get_bloginfo( 'description' ),
      'language'  => get_bloginfo( 'language' ),
      'version'   => WORDPRESS_ZERO_SPAM_VERSION
    ],
    'sslverify' => true
  ];

  // For debugging purposes only.
  if ( WP_DEBUG ) {
    $request_args['sslverify'] = false;
  }

  // Send the request.
  $request = wp_remote_post( $api_url, $request_args );
  if ( is_wp_error( $request ) ) {
    // Request failed.
    return false;
  }

  // Request succeeded, return the result.
  return wp_remote_retrieve_body( $request );
}

/**
 * Returns the generated key for checking submissions.
 *
 * @since 4.0.0
 *
 * @return string A unique key used for detections.
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
 * Returns the generated key for checking submissions.
 *
 * @since 4.9.9
 *
 * @return string A unique key used for the 'honeypot' field.
 */
if ( ! function_exists( 'wpzerospam_get_honeypot' ) ) {
  function wpzerospam_get_honeypot() {
    $key = get_option( 'wpzerospam_honeypot' );
    if ( ! $key ) {
      $key = wp_generate_password( 5, false, false );
      update_option( 'wpzerospam_honeypot', $key );
    }

    return $key;
  }
}










/**
 * Handles what happens when spam is detected.
 *
 * @since 4.0.0
 *
 * @param string $type Machine-readable name for the type of spam.
 * @param array $data Additional information submitted when the spam was detected.
 * @param boolean $handle_detection Determines if this function should handle the function or is handled in the submission hook.
 * @return void
 */
if ( ! function_exists( 'wpzerospam_spam_detected' ) ) {
  function wpzerospam_spam_detected( $type, $data = [], $handle_detection = true ) {
    $options = wpzerospam_options();
    $ip      = wpzerospam_ip();

    // Log the spam sttempt
    wpzerospam_log_detection( $type, $data );

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
    if ( $handle_detection ) {
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
 * Checks if a specific plugin integration is turned on & plugin active.
 */
if ( ! function_exists( 'wpzerospam_plugin_integration_enabled' ) ) {
  function wpzerospam_plugin_integration_enabled( $plugin ) {
    if(  ! function_exists( 'is_plugin_active' ) ) {
      require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $options = wpzerospam_options();

    $integrations = [
      'fluentform' => 'fluentform/fluentform.php',
      'wpforms'    => [ 'wpforms/wpforms.php', 'wpforms-lite/wpforms.php' ],
      'formidable' => 'formidable/formidable.php',
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
