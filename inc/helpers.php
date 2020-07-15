<?php
/**
 * Plugin helpers
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Handles what happens when spam is detected
 */
if ( ! function_exists( 'wpzerospam_spam_detected' ) ) {
  function wpzerospam_spam_detected( $type, $data = [] ) {
    $options = wpzerospam_options();

    wp_redirect( esc_url( $options['spam_redirect_url'] ) );
    exit();
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
 * Create a log entry if logging is enabled
 */
if ( ! function_exists( 'wpzerospam_log_spam' ) ) {
  function wpzerospam_log_spam( $type, $data = [] ) {
    $options = wpzerospam_options();

    if ( 'enabled' != $options['log_spam'] ) {
      // Logging disabled
      return false;
    }

    // Logging enabled
  }
}

 /**
 * Sets a plugin cookie
 */
if ( ! function_exists( 'wpzerospam_set_cookie' ) ) {
  function wpzerospam_set_cookie( $name, $value ) {
    setcookie( 'wpzerospam_' . $name, $value, 0, COOKIEPATH,COOKIE_DOMAIN );
  }
}

/**
 * Gets a plugin cookie
 */
if ( ! function_exists( 'wpzerospam_get_cookie' ) ) {
  function wpzerospam_get_cookie( $name ) {
    if ( empty( $_COOKIE['wpzerospam_' . $name ] ) ) {
      return false;
    }

    return $_COOKIE['wpzerospam_' . $name ];
  }
}

/**
 * Returns the generated key for checking submissions
 */
if ( ! function_exists( 'wpzerospam_get_key' ) ) {
  function wpzerospam_get_key() {
    $key = wpzerospam_get_cookie( 'key' );
    if ( ! $key ) {
      $key = wp_generate_password( 64 );
      wpzerospam_set_cookie( 'key', $key );
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
 * Returns the plugin settings.
 */
if ( ! function_exists( 'wpzerospam_options' ) ) {
  function wpzerospam_options() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    $options = get_option( 'wpzerospam' );

    if ( empty( $options['blocked_redirect_url'] ) ) { $options['blocked_redirect_url'] = 'https://www.google.com'; }
    if ( empty( $options['spam_redirect_url'] ) ) { $options['spam_redirect_url'] = 'https://www.google.com'; }
    if ( empty( $options['log_spam'] ) ) { $options['log_spam'] = 'disabled'; }
    if ( empty( $options['verify_comments'] ) ) { $options['verify_comments'] = 'enabled'; }
    if ( empty( $options['verify_registrations'] ) ) { $options['verify_registrations'] = 'enabled'; }

    if ( empty( $options['verify_cf7'] ) && is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
      $options['verify_cf7'] = 'enabled';
    }

    if ( empty( $options['verify_gforms'] ) && is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
      $options['verify_gforms'] = 'enabled';
    }

    if ( empty( $options['verify_ninja_forms'] ) && is_plugin_active( 'ninja-forms/ninja-forms.php' ) ) {
      $options['verify_ninja_forms'] = 'enabled';
    }

    if ( empty( $options['verify_bp_registrations'] ) && function_exists( 'bp_is_active' ) ) {
      $options['verify_bp_registrations'] = 'enabled';
    }

    if ( empty( $options['verify_wpforms'] ) && ( is_plugin_active( 'wpforms/wpforms.php' ) || is_plugin_active( 'wpforms-lite/wpforms.php' ) ) ) {
      $options['verify_wpforms'] = 'enabled';
    }

    if ( empty( $options['blocked_ips'] ) ) { $options['blocked_ips'] = []; }

    return $options;
  }
}

/**
 * Returns the current user's IP address
 */
if ( ! function_exists( 'wpzerospam_ip' ) ) {
  function wpzerospam_ip() {
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
      return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      return $_SERVER['REMOTE_ADDR'];
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
      ( ! is_singular() && ! is_page() && ! is_single() && ! is_archive() && ! is_home() && ! is_front_page() )
    ) {
      return $access;
    }

    $options = wpzerospam_options();

    // Check if the current user's IP address has been blocked
    $ip           = wpzerospam_ip();
    $block_ip_key = array_search( $ip, array_column( $options['blocked_ips'], 'ip_address' ) );
    if ( $block_ip_key ) {
      $access['access'] = false;
      $access['type']   = 'blocked_ips';
      $access['ip']     = $ip;
      $access['reason'] = ! empty( $options['blocked_ips'][ $block_ip_key ]['reason'] ) ? $options['blocked_ips'][ $block_ip_key ]['reason'] : false;
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
 * Returns the location of a log file
 */
if ( ! function_exists( 'wpzerospam_log_file' ) ) {
  function wpzerospam_log_file( $type ) {
    $wp_upload_dir = wp_upload_dir();
    $wp_upload_dir = $wp_upload_dir['basedir'];
    $file          = $wp_upload_dir . '/wpzerospam-' . $type . '.log';

    return $file;
  }
}

/**
 * Creates a log entry & reads a log file
 */
if ( ! function_exists( 'wpzerospam_log' ) ) {
  function wpzerospam_log( $type, $data = false, $mode = 'a' ) {
    $options  = wpzerospam_options();
    $location = wpzerospam_log_file( $type );

    if ( $data ) {
      // Only log if the type is enabled
      if ( empty( $options['log_' . $type ] ) || 'enabled' != $options['log_' . $type ] ) { return false; }

      $data = [ 'date' => current_time( 'mysql' ) ] + $data;

      // Write a log entry
      $file = fopen( $location, $mode );
      fwrite( $file, json_encode( $data ) . "\n" );
      fclose( $file );

      return true;
    }

    // Return a log
    if ( file_exists( $location ) ) {
      $log = [];
      $entries = file_get_entries( $location );
      $entries  = explode( "\n", $entries );

      foreach( $entries as $key => $entry ) {
        if ( ! $entry ) { continue; }

        $log[] = json_decode( $entry, true );
      }

      return $log;
    }

    return false;
  }
}
