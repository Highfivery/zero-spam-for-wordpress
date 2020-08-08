<?php
/**
 * Utility helpers
 *
 * Helpers functions to return data vs. altering it or doing anything with it.
 *
 * @package WordPressZeroSpam
 * @since 4.9.3
 * @link https://benmarshall.me/wordpress-zero-spam/
 */

/**
 * Locations helper
 */
require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'inc/locations.php';

/**
 * Outputs a honeypot field
 *
 * @since 4.9.9
 *
 * @return string Returns a HTML honeypot field.
 */
if ( ! function_exists( 'wpzerospam_honeypot_field' ) ) {
  function wpzerospam_honeypot_field() {
    return '<input type="text" name="' . wpzerospam_get_honeypot() . '" value="" style="display: none !important;" />';
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
    if ( empty( $options['spam_message'] ) ) { $options['spam_message'] = __( 'There was a problem with your submission. Please go back and try again.', 'zero-spam' ); }
    if ( empty( $options['blocked_message'] ) ) { $options['blocked_message'] = __( 'You have been blocked from visiting this site by WordPress Zero Spam due to detected spam activity.', 'zero-spam' ); }
    if ( empty( $options['log_spam'] ) ) { $options['log_spam'] = 'disabled'; }
    if ( empty( $options['log_blocked_ips'] ) ) { $options['log_blocked_ips'] = 'disabled'; }
    if ( empty( $options['auto_block_permanently'] ) ) { $options['auto_block_permanently'] = 3; }
    if ( empty( $options['botscout_api'] ) ) { $options['botscout_api'] = false; }
    if ( empty( $options['ip_whitelist'] ) ) { $options['ip_whitelist'] = false; }
    if ( empty( $options['api_timeout'] ) ) { $options['api_timeout'] = 5; }
    if ( empty( $options['stopforumspam_confidence_min'] ) ) { $options['stopforumspam_confidence_min'] = 20; }
    if ( empty( $options['botscout_count_min'] ) ) { $options['botscout_count_min'] = 5; }
    if ( empty( $options['cookie_expiration'] ) ) { $options['cookie_expiration'] = 7; }

    if ( empty( $options['share_detections'] )  ) {
      $options['share_detections'] = 'enabled';
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

    $options = apply_filters( 'wpzerospam_admin_options_defaults', $options );

    return $options;
  }
}

/**
 * Queries a blacklist API
 *
 * @param string $ip The IP address to query
 * @param string $service The API service to query
 * @return false/array False if not found, otherwise the IP info.
 */
if ( ! function_exists( 'wpzerospam_query_blacklist_api' ) ) {
  function wpzerospam_query_blacklist_api( $ip, $service ) {
    $options = wpzerospam_options();

    switch( $service ) {
      case 'stopforumspam':
        if ( 'enabled' != $options['stop_forum_spam'] ) { return false; }

        $api_url  = 'https://api.stopforumspam.org/api?';
        $params   = [ 'ip' => $ip, 'json' => '' ];
        $endpoint = $api_url . http_build_query( $params );
      break;
      case 'botscout':
        if ( empty( $options['botscout_api'] ) ) { return false; }

        $api_url  = 'https://botscout.com/test/?';
        $params   = [ 'ip' => $ip, 'key' => $options['botscout_api'] ];
        $endpoint = $api_url . http_build_query( $params );
      break;
    }

    if ( ! empty( $endpoint ) ) {
      $response = wp_remote_get( $endpoint, [ 'timeout' => $options['api_timeout'] ] );
      if ( is_array( $response ) && ! is_wp_error( $response ) ) {
        $data = wp_remote_retrieve_body( $response );

        switch( $service ) {
          case 'stopforumspam':
            $data = json_decode( $data, true );
            if (
              ! empty( $data['success'] ) &&
              $data['success'] &&
              ! empty( $data['ip'] ) &&
              ! empty( $data['ip']['appears'] )
            ) {
              return $data['ip'];
            }
          break;
          case 'botscout':
            if ( strpos( $data, '!' ) === false ) {
              list( $matched, $type, $count ) = explode( "|", $data );
              if ( 'Y' == $matched ) {
                return [
                  'type'  => $type,
                  'count' => $count
                ];
              }
            }
          break;
        }
      }
    }

    return false;
  }
}

/**
 * Query the database tables
 *
 * @return false/array False if not found, otherwise the blocked IP info.
 */
if ( ! function_exists( 'wpzerospam_query_table' ) ) {
  function wpzerospam_query_table( $table, $args = [] ) {
    global $wpdb;

    // Select
    $sql = 'SELECT ';
    if ( ! empty( $args['select'] ) ) {
      $sql .= implode( ',', $args['select'] );
    } else {
      $sql .= '*';
    }

    // From
    $sql .= " from " . wpzerospam_tables( $table );

    // Where
    if ( ! empty( $args['where'] ) ) {
      $sql .= ' WHERE ';
      foreach( $args['where'] as $key => $value ) {
        if ( is_int( $value ) ) {
          $sql .= $key . ' = ' . $value . ' ';
        } else {
          $sql .= $key . ' = "' . $value . '" ';
        }
      }
    }

    // Limit
    if ( ! empty( $args['limit'] ) ) {
      $sql .= 'LIMIT ' . $args['limit'];

      // Offset
      if ( ! empty( $args['offset'] ) ) {
        $sql .= ', ' . $args['offset'];
      }
    }

    if ( ! empty( $args['limit'] ) && 1 == $args['limit'] ) {
      return $wpdb->get_row( $sql, ARRAY_A );
    } else {
      return $wpdb->get_results( $sql, ARRAY_A );
    }
  }
}

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
    $response   = wp_remote_get( $remote_url, [ 'timeout' => $options['api_timeout'] ] );

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
 * Whitelisted IPs
 *
 * @return array An array of whitelisted IP addresses.
 */
if ( ! function_exists( 'wpzerospam_get_whitelist' ) ) {
  function wpzerospam_get_whitelist() {
    $options = wpzerospam_options();
    if ( $options['ip_whitelist'] ) {
      $whitelist = explode( PHP_EOL, $options['ip_whitelist'] );
      if ( $whitelist ) {
        $whitelisted = [];
        foreach( $whitelist as $k => $whitelisted_ip ) {
          $whitelisted[ $whitelisted_ip ] = $whitelisted_ip;
        }

        return $whitelisted;
      }
    }

    return false;
  }
}

/**
 * Checks if an IP is blocked
 *
 *  @param string IP address to check.
 *  @return boolean/array False is not blocked, otherwise an array with the
 *                        blocked IP information.
 */
if ( ! function_exists( 'wpzerospam_is_blocked' ) ) {
  function wpzerospam_is_blocked( $ip ) {
    $blocked_ip = wpzerospam_query_table( 'blocked', [
      'select' => [
        'blocked_type',
        'start_block',
        'end_block',
        'reason',
        'attempts'
      ],
      'where'  => [ 'user_ip' => $ip ],
      'limit'  => 1
    ]);

    if ( $blocked_ip ) {
      if ( 'permanent' == $blocked_ip['blocked_type'] ) {
        // Permanently blocked
        return $blocked_ip;
      } else {
        // Temporarily blocked
        $current_datetime = current_time( 'timestamp' );
        $start_block      = strtotime( $blocked_ip['start_block'] );
        $end_block        = strtotime( $blocked_ip['end_block'] );
        if (
          $current_datetime >= $start_block &&
          $current_datetime < $end_block
        ) {
          return $blocked_ip;
        }
      }
    }

    return false;
  }
}

/**
 * Checks if an IP is blacklisted
 *
 *  @param string IP address to check.
 *  @return boolean/array False is not blacklisted, otherwise an array with the
 *                        blacklisted IP information.
 */
if ( ! function_exists( 'wpzerospam_is_blacklisted' ) ) {
  function wpzerospam_is_blacklisted( $ip ) {
    $blacklist_ip = wpzerospam_query_table( 'blacklist', [
      'select' => [
        'blacklist_service', 'blacklist_id', 'last_updated', 'attempts'
      ],
      'where'  => [ 'user_ip' => $ip ],
      'limit'  => 1
    ]);

    return $blacklist_ip;
  }
}
