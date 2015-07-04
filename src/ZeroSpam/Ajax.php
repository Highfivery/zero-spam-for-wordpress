<?php
class ZeroSpam_Ajax extends ZeroSpam_Plugin {
  public function run() {
    // Block an IP.
    add_action( 'wp_ajax_block_ip', array( $this, 'wp_ajax_block_ip' ) );

    // Get the Block IP form.
    add_action( 'wp_ajax_block_ip_form', array( $this, 'wp_ajax_block_ip_form' ) );

    // Get a blocked IP's record.
    add_action( 'wp_ajax_get_blocked_ip', array( $this, 'wp_ajax_get_blocked_ip' ) );

    // Delete a blocked IP.
    add_action( 'wp_ajax_trash_ip_block', array( $this, 'wp_ajax_trash_ip_block' ) );

    // Reset the spammer log.
    add_action( 'wp_ajax_reset_log', array( $this, 'wp_ajax_reset_log' ) );

    // Get the location of an IP.
    add_action( 'wp_ajax_get_location', array( $this, 'wp_ajax_get_location' ) );

    // Get spam by IP.
    add_action( 'wp_ajax_get_ip_spam', array( $this, 'wp_ajax_get_ip_spam' ) );
  }

  /**
   * Uses wp_ajax_(action).
   *
   * AJAX function to block a user's IP address.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_block_ip() {
    check_ajax_referer( 'zero-spam', 'security' );

    if ( ! $_POST['zerospam-type'] == 'temporary' ) {
      $start_date = false;
      $end_date = false;
    } else {
      $start_date = date( 'Y-m-d G:i:s', strtotime(
        $_POST['zerospam-startdate-year'] . '-' .
        $_POST['zerospam-startdate-month'] . '-' .
        $_POST['zerospam-startdate-day']
      ));

      $end_date = date( 'Y-m-d G:i:s', strtotime(
        $_POST['zerospam-enddate-year'] . '-' .
        $_POST['zerospam-enddate-month'] . '-' .
        $_POST['zerospam-enddate-day']
      ));
    }

    $reason = isset( $_POST['zerospam-reason'] ) ? $_POST['zerospam-reason'] : NULL;

    // Add/update the blocked IP.
    zerospam_block_ip( array(
      'ip' => $_POST['zerospam-ip'],
      'type' => $_POST['zerospam-type'],
      'start_date' => $start_date,
      'end_date' => $end_date,
      'reason' => $reason,
    ));

    die();
  }

  /**
   * Uses wp_ajax_(action).
   *
   * Renders the block IP form.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_block_ip_form() {
    global $wpdb;
    check_ajax_referer( 'zero-spam', 'security' );

    $ajax_nonce       = wp_create_nonce( 'zero-spam' );

    $date             = new DateTime();
    $end_date         = $date->modify('+1 day');

    $start_date_year  = date( 'Y' );
    $start_date_month = date( 'n' );
    $start_date_day   = date( 'd' );

    $end_date_year    = $end_date->format( 'Y' );
    $end_date_month   = $end_date->format( 'n' );
    $end_date_day     = $end_date->format( 'd' );

    if ( isset( $_REQUEST['ip'] ) ) {
      $ip   = $_REQUEST['ip'];
      $data = zerospam_get_blocked_ip( $_REQUEST['ip'] );

      if ( $data ) {
        if ( $data->start_date ) {
          list( $start_date_year, $start_date_month, $start_date_day ) = explode( '-', $data->start_date );
        }
        if ( $data->end_date ) {
          list( $end_date_year, $end_date_month, $end_date_day ) = explode( '-', $data->end_date );
        }
      }
    }

    require_once( ZEROSPAM_ROOT . 'inc/block-ip-form.tpl.php' );

    die();
  }

  /**
   * Uses wp_ajax_(action).
   *
   * Get the blocked IP data.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_get_blocked_ip() {
    global $wpdb;
    check_ajax_referer( 'zero-spam', 'security' );

    $ajax_nonce = wp_create_nonce( 'zero-spam' );
    $ip         = $_REQUEST['ip'];
    $data       = zerospam_get_blocked_ip( $ip );

    if ( $data ) {
      $data->is_blocked     = zerospam_is_blocked( $ip );
      $data->start_date_txt = date( 'l, F j, Y', strtotime( $data->start_date ) );
      $data->end_date_txt   = date( 'l, F j, Y', strtotime( $data->end_date ) );

      echo json_encode( (array) $data );
    }

    die();
  }

  /**
   * Uses wp_ajax_(action).
   *
   * Deletes a IP block.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_trash_ip_block() {
    global $wpdb;
    check_ajax_referer( 'zero-spam', 'security' );

    $ip = $_REQUEST['ip'];
    zerospam_delete_blocked_ip( $ip );

    die();
  }

  /**
   * Uses wp_ajax_(action).
   *
   * Resets the spammer log.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_reset_log() {
    global $wpdb;
    check_ajax_referer( 'zero-spam', 'security' );

    zerospam_reset_log();
    die();
  }

  /**
   * Uses wp_ajax_(action).
   *
   * Get location data from IP.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_get_location() {
    global $wpdb;
    check_ajax_referer( 'zero-spam', 'security' );

    $ip = $_REQUEST['ip'];
    echo json_encode( zerospam_get_ip_info( $ip ) );

    die();
  }

  /**
   * Uses wp_ajax_(action).
   *
   * Get's spam by IP.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
   */
  public function wp_ajax_get_ip_spam() {
    global $wpdb;
    check_ajax_referer( 'zero-spam', 'security' );

    $spam = zerospam_get_spam();
    $return = array(
      'by_country' => array(),
      'by_lat_long' => array()
    );

    // API usage limit protection.
    $limit = 10;
    $cnt   = 0;
    foreach ( $spam as $key => $obj ) {
      $cnt++;
      if ( $cnt > 10 ) {
        break;
      }
      $loc = zerospam_get_ip_info( $obj->ip );

      if ( $loc ) {
        if ( ! isset( $return['by_country'][ $loc->country_code ] ) ) {
          $return['by_country'][ $loc->country_code ] = array(
            'count' => 0,
            'name' => $loc->country_name
          );
        }
        $return['by_country'][ $loc->country_code ]['count']++;

        if ( ! isset( $return['by_lat_long'][ $obj->ip ] ) ) {
          $return['by_lat_long'][ $obj->ip ] = array(
            'latLng' => array( $loc->latitude, $loc->longitude ),
            'name' => $loc->country_name,
            'count' => 1
          );
        }
      }

      sleep(1);
    }

    arsort( $return['by_country'] );

    echo json_encode( $return );

    die();
  }
}