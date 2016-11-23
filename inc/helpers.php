<?php
/**
 * Zero Spam Helpers
 *
 * Contains all of Zero Spam helper functions. Can be used within other themes
 * & plugins.
 *
 * @package WordPress Zero Spam
 * @since 1.0.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function zerospam_settings() {
  if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
    // Network plugin settings.
    return (array) get_site_option( 'zerospam_general_settings' );
  }

  // Site plugin settings.
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
  }
  if ( false === WP_Http::is_ip_address( $ipaddress ) ) {
    $ipaddress = 'UNKNOWN';
  }

  return $ipaddress;
}

function zerospam_log_spam( $key, $url = false ) {
  global $wpdb;

  $settings   = zerospam_settings();
  $ip         = zerospam_get_ip();
  $url        = ( $url ) ? $url : zerospam_get_url();
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
    case 'nf':
      $key = 6;
      break;
    case 'wpf':
      $key = 7;
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
  $check      = zerospam_get_blocked_ip( $ip );
  $current    = current_time( 'timestamp' );

  if ( empty( $check ) ) {
    return false;
  }

  // Check block type
  if (
    'temporary' == $check->type &&
    $current >= strtotime( $check->start_date ) &&
    $current <= strtotime( $check->end_date )
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
  $query      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE ip = %s", $ip ) );

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
    if ( zerospam_is_blocked( $ip ) ) {

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

function zerospam_get_url() {
  $pageURL = 'http';

  if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) {
    $pageURL .= 's';
  }

  $pageURL .= '://';

  if ( '80' != $_SERVER['SERVER_PORT'] ) {
    $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
  } else {
    $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
  }

  return $pageURL;
}

function zerospam_get_spam( $args = array() ) {
  global $wpdb;

  $table_name = $wpdb->prefix . 'zerospam_log';

  $order_by = isset( $args['order_by'] ) ? ' ORDER BY ' . $args['order_by'] : ' ORDER BY date DESC';

  $offset = isset( $args['offset'] ) ? $args['offset'] : false;
  $limit = isset( $args['limit'] ) ? $args['limit'] : false;
  if ( $offset && $limit ) {
    $limit = ' LIMIT ' . $offset . ', ' . $limit;
  } elseif( $limit ) {
    $limit = ' LIMIT ' . $limit;
  }

  $query = 'SELECT * FROM ' . $table_name . $order_by . $limit;
  $results = $wpdb->get_results( $query );

  return $results;
}

function zerospam_parse_spam_ary( $ary ) {
  $return = array(
    'by_date'              => array(),
    'by_spam_count'        => array(),
    'raw'                  => $ary,
    'comment_spam'         => 0,
    'registration_spam'    => 0,
    'cf7_spam'             => 0,
    'gf_spam'              => 0,
    'bp_registration_spam' => 0,
    'nf_spam'              => 0,
    'wpf_spam'             => 0,
    'unique_spammers'      => array(),
    'by_day'               => array(
      'Sun' => 0,
      'Mon' => 0,
      'Tue' => 0,
      'Wed' => 0,
      'Thu' => 0,
      'Fri' => 0,
      'Sat' => 0
    ),
  );

  foreach ( $ary as $key => $obj ) {
    // By day
    $return['by_day'][ date( 'D', strtotime( $obj->date ) ) ]++;

    // By date
    if ( ! isset( $return['by_date'][ substr( $obj->date, 0, 10 ) ] ) ) {
      $return['by_date'][ substr( $obj->date, 0, 10 ) ] = array(
        'data'                 => array(),
        'comment_spam'         => 0,
        'registration_spam'    => 0,
        'cf7_spam'             => 0,
        'gf_spam'              => 0,
        'bp_registration_spam' => 0,
        'nf_spam'              => 0,
        'wpf_spam'             => 0,
      );
    }

    // By date
    $return['by_date'][ substr( $obj->date, 0, 10 ) ]['data'][] = array(
      'zerospam_id' => $obj->zerospam_id,
      'type'        => $obj->type,
      'ip'          => $obj->ip,
      'date'        => $obj->date,
    );

    // By spam count
    if ( ! isset( $return['by_spam_count'][ $obj->ip ] ) ) {
      $return['by_spam_count'][ $obj->ip ] = 0;
    }
    $return['by_spam_count'][ $obj->ip ]++;

    // Spam type
    if ( 1 == $obj->type) {

      // Registration spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['registration_spam']++;
      $return['registration_spam']++;
    } elseif ( 2 == $obj->type ) {

      // Comment spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['comment_spam']++;
      $return['comment_spam']++;
    } elseif ( 3 == $obj->type ) {

      // Contact Form 7 spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['cf7_spam']++;
      $return['cf7_spam']++;
    } elseif ( 4 == $obj->type ) {

      // Gravity Form spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['gf_spam']++;
      $return['gf_spam']++;
    } elseif ( 5 == $obj->type ) {

      // BuddyPress spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['bp_registration_spam']++;
      $return['bp_registration_spam']++;
    } elseif ( 6 == $obj->type ) {

      // Ninja Form spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['nf_spam']++;
      $return['nf_spam']++;
    } elseif ( 7 == $obj->type ) {

      // WPForms spam.
      $return['by_date'][ substr( $obj->date, 0, 10 ) ]['wpf_spam']++;
      $return['wpf_spam']++;
    } else {
      if ( empty(  $return['by_date'][ substr( $obj->date, 0, 10 ) ][$obj->type] ) )  $return['by_date'][ substr( $obj->date, 0, 10 ) ][$obj->type] = 0;
      $return['by_date'][ substr( $obj->date, 0, 10 ) ][$obj->type]++;

      if ( empty( $return[$obj->type] ) ) $return[$obj->type] = 0;
      $return[$obj->type]++;
    }

    // Unique spammers
    if ( ! in_array( $obj->ip, $return['unique_spammers'] ) ) {
      $return['unique_spammers'][] = $obj->ip;
    }

  }

  return $return;
}

function zerospam_all_spam_ary() {
  global $wpdb;
  $return = array(
    'by_date'              => array(),
    'by_spam_count'        => array(),
    'raw'                  => 0,
    'comment_spam'         => 0,
    'registration_spam'    => 0,
    'cf7_spam'             => 0,
    'gf_spam'              => 0,
    'bp_registration_spam' => 0,
    'nf_spam'              => 0,
    'unique_spammers'      => array(),
    'by_day'               => array(
      'Sun' => 0,
      'Mon' => 0,
      'Tue' => 0,
      'Wed' => 0,
      'Thu' => 0,
      'Fri' => 0,
      'Sat' => 0
    ),
  );

  $table_name = $wpdb->prefix . 'zerospam_log';

  // Count all
  if ( $r = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS zerospam_id FROM $table_name WHERE 1=1 LIMIT 10") ) {
    // SELECT COUNT(*) counts, MIN(date) date_start FROM $table_name WHERE 1=1
    $return['raw'] = $wpdb->get_var("SELECT FOUND_ROWS()");// array_fill(0, $count_all, 0);// JUST USE $count_all next time...
    $return['date_start'] = $wpdb->get_var("SELECT date FROM $table_name WHERE zerospam_id = (SELECT MIN(zerospam_id) FROM $table_name)");
  } else {
    // there's no spammer logs...
    return $return;
  }

  $type_map = array(
    1                => 'registration_spam',
    2                => 'comment_spam',
    3                => 'cf7_spam',
    4                => 'gf_spam',
    5                => 'bp_registration_spam',
    'nf'             => 'nf_spam',
    'Undefined Form' =>  'undefined_form' );

  // Get spammers by weekday.
  $by_weekday_ary = $wpdb->get_results( "SELECT DATE_FORMAT(date, '%a') as day, COUNT(*) num FROM $table_name GROUP BY day", ARRAY_A );
  if ( $by_weekday_ary )
  {
    foreach ( $by_weekday_ary as $key => $ary )
    {
      $return['by_day'][ $ary['day'] ] = $ary['num'];
    }
  }

  // By IP.
  // @TODO - Need to make the limit dynamic.
  $by_ip_ary = $wpdb->get_results( "SELECT ip, COUNT(*) num FROM $table_name GROUP BY ip ORDER BY num DESC LIMIT 100", ARRAY_A );
  if ( $by_ip_ary )
  {
    foreach ( $by_ip_ary as $key => $ary ) {
      $return['by_spam_count'][ $ary['ip'] ] = $ary['num'];
    }
  }

  // By type.
  $by_type = $wpdb->get_results( "SELECT type, COUNT(*) num FROM $table_name GROUP BY type", ARRAY_A );
  if ( $by_type )
  {
    foreach( $by_type as $key => $ary )
    {
      $type = ! empty( $type_map[ $ary['type'] ] ) ? $type_map[ $ary['type'] ] : $ary['type'];
      $return[ $type ] = $ary['num'];
    }
  }

  // Unique Spammers
  if ( $unique_spammers = $wpdb->get_var( "SELECT COUNT(DISTINCT ip) FROM $table_name" ) )
  {
    $return['unique_spammers'] = $unique_spammers;
  }

  // By date: LIMIT 100 days for graph
  // @TODO - Need to make the limit dynamic.
  $by_date = $wpdb->get_results( "SELECT type, LEFT(date, 10) day, COUNT(*) num FROM $table_name GROUP BY day, type ORDER BY date DESC LIMIT 100", ARRAY_A );
  if ( $by_date )
  {
    foreach( $by_date as $key => $ary )
    {
      if ( ! empty( $return['by_date'][ $ary['day'] ] ) )
      {
        $return['by_date'][ $ary['day'] ] = array(
            'data'                 => array(),
            'comment_spam'         => 0,
            'registration_spam'    => 0,
            'cf7_spam'             => 0,
            'gf_spam'              => 0,
            'bp_registration_spam' => 0,
            'nf_spam'              => 0
        );
      }
      $return['by_date'][ $ary['day'] ][ $type_map[ $ary['type'] ] ] = $ary['num'];
    }
  }

  return $return;
}

function zerospam_num_days( $date ) {
  $datediff = time() - strtotime( $date );

  return floor( $datediff / ( DAY_IN_SECONDS ) );
}

function zerospam_get_blocked_ips( $args = array() ) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'zerospam_blocked_ips';

  $order_by   = isset( $args['order_by'] ) ? ' ORDER BY ' . $args['order_by'] : ' ORDER BY zerospam_ip_id DESC';

  $offset     = isset( $args['offset'] ) ? $args['offset'] : false;
  $limit      = isset( $args['limit'] ) ? $args['limit'] : false;
  if ( $offset && $limit ) {
    $limit = ' LIMIT ' . $offset . ', ' . $limit;
  } elseif ( $limit ) {
    $limit = ' LIMIT ' . $limit;
  }

  $query = 'SELECT * FROM ' . $table_name . $order_by . $limit;
  $results = $wpdb->get_results( $query );

  if ( null == $results ) {
    return false;
  }

  return $results;
}

function zerospam_get_percent( $num1, $num2 ) {
  return number_format( ( $num1 / $num2 ) * 100, 2 );
}

function zerospam_pager( $limit = 10, $total_num, $page, $tab ) {
  $max_pages = 11;
  $num_pages = ceil( $total_num / $limit );
  $cnt       = 0;

  $start = 1;
  if ( $page > 5 ) {
    $start = ( $page - 4 );
  }

  $pre_html = '';
  if ( 1 != $page ) {
    if ( 2 != $page ) {
      $pre_html = '<li><a href="' . zerospam_admin_url() . '?page=zerospam&tab=' . $tab . '&p=1"><i class="fa fa-angle-double-left"></i></a>';
    }
    $pre_html .= '<li><a href="' . zerospam_admin_url() . '?page=zerospam&tab=' . $tab . '&p=' . ( $page - 1 ) . '"><i class="fa fa-angle-left"></i></a>';
  }

  echo '<ul class="zero-spam__pager">';
  if ( isset( $pre_html ) ) {
    echo $pre_html;
  }
  for ( $i = $start; $i <= $num_pages; $i ++ ) {
    $cnt ++;
    if ( $cnt >= $max_pages ) {
      break;
    }

    if ( $num_pages != $page ) {
      $post_html = '<li><a href="' . zerospam_admin_url() . '?page=zerospam&tab=' . $tab . '&p=' . ( $page + 1 ) . '"><i class="fa fa-angle-right"></i></a>';
      if ( ( $page + 1 ) != $num_pages ) {
        $post_html .= '<li><a href="' . zerospam_admin_url() . '?page=zerospam&tab=' . $tab . '&p=1"><i class="fa fa-angle-double-right"></i></a>';
      }
    }

    $class = '';
    if ( $page == $i ) {
      $class = ' class="zero-spam__page-selected"';
    }
    echo '<li><a href="' . zerospam_admin_url() . '?page=zerospam&tab=' . $tab . '&p=' . $i . '"' . $class . '>' . $i . '</a>';
  }

  if( isset( $post_html ) ) {
    echo $post_html;
  }
  echo '</ul>';

      ?>
  <div class="zero-spam__page-info">
    <?php echo __( 'Page ', 'zerospam' ) . number_format( $page, 0 ) . ' of ' . number_format( $num_pages, 0 ); ?>
    (<?php echo number_format( $total_num, 0 ) . __( ' total records found', 'zerospam' ); ?>)
  </div>
  <?php
}

function zerospam_get_spam_count() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'zerospam_log';
  $query = $wpdb->get_row( 'SELECT COUNT(*) AS count FROM ' . $table_name );
  return $query->count;
}

function zerospam_plugin_check( $plugin ) {
  $result = false;
  switch ( $plugin ) {
    case 'cf7':
      if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
        $result = true;
      }
      break;
    case 'gf':
      if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
        $result = true;
      }
      break;
    case 'bp':
      if ( function_exists( 'bp_is_active' ) ) {
        $result = true;
      }
      break;
    case 'nf':
      if ( is_plugin_active( 'ninja-forms/ninja-forms.php' ) ) {
        $result = true;
      }
      break;
    case 'wpf':
      if ( is_plugin_active( 'wpforms/wpforms.php' ) || is_plugin_active( 'wpforms-lite/wpforms.php' ) ) {
        $result = true;
      }
      break;
  }

  return $result;
}

function zerospam_delete_blocked_ip( $ip ) {
  global $wpdb;
  $table_name = $wpdb->prefix . 'zerospam_blocked_ips';
  $query      = $wpdb->delete( $table_name, array( 'ip' => $ip ), array( '%s' ) );

  return $query;
}

function zerospam_reset_log() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'zerospam_log';
  $query      = $wpdb->query( 'TRUNCATE ' . $table_name );

  return $query;
}

function zerospam_get_ip_info( $ip ) {
  global $wpdb;

  // Ignore local hosts.
  if ( $ip == '127.0.0.1' || $ip == '::1' ) {
    return false;
  }

  // Check DB
  $table_name = $wpdb->prefix . 'zerospam_ip_data';
  $data       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE ip = %s", $ip ) );

  // Retrieve from API
  if ( ! $data ) {
    // @ used to suppress API usage block warning.
    $json = @file_get_contents( 'http://freegeoip.net/json/' . $ip );

    $data = json_decode( $json );

    if ( $data ) {
      $wpdb->insert( $table_name, array(
          'ip'            => $ip,
          'country_code'  => $data->country_code,
          'country_name'  => $data->country_name,
          'region_code'   => $data->region_code,
          'region_name'   => $data->region_name,
          'city'          => $data->city,
          'zipcode'       => $data->zipcode,
          'latitude'      => $data->latitude,
          'longitude'     => $data->longitude,
          'metro_code'    => $data->metro_code,
          'area_code'     => $data->area_code
        ),
        array(
          '%s',
          '%s',
          '%s',
          '%s',
          '%s',
          '%s',
          '%s',
          '%d',
          '%d',
          '%d',
          '%d'
        )
      );
    }
  }

  if ( FALSE != $data ) {
    return $data;
  }

  return false;
}

/**
 * WordPress admin URL.
 *
 * Returns the admin URL based off of whether it's a network enabled.
 *
 * @since 1.0.0
 *
 * @see is_plugin_active_for_network
 * @see network_admin_url
 * @see home_url
 * @see admin_url
 * @global string ZEROSPAM_PLUGIN The plugin root directory.
 *
 * @return string The plugin settings page URL.
 */
if ( ! function_exists( 'zerospam_admin_url' ) )
{
  function zerospam_admin_url()
  {
    if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) )
    {
      // Network enabled.
      $settings_url = network_admin_url( 'settings.php' );
    }
    else
    {
      // No network.
      $settings_url = admin_url( 'options-general.php' );
    }

    return $settings_url;
  }
}

/**
 * Blocked IP count.
 *
 * Returns the number of blocked IPs.
 *
 * @since 1.0.0
 *
 * @see wpdb::get_row
 * @global object $wpdb Contains a set of functions used to interact with a database.
 *
 * @return int The number of blocked IPs.
 */
if ( ! function_exists( 'zerospam_get_blocked_ip_count' ) )
  {
  function zerospam_get_blocked_ip_count()
  {
    global $wpdb;

    $table_name = $wpdb->prefix . 'zerospam_blocked_ips';
    $query = $wpdb->get_row( 'SELECT COUNT(*) AS count FROM ' . $table_name );
    return $query->count;
  }
}

/**
 * ZeroSpam library autoloader.
 *
 * The autoloads all available Zero Spam libraries.
 *
 * @since 1.0.0
 *
 * @param string $class_name The name of the ZeroSpam library.
 * @return boolean TRUE if library successfully included, FALSE if not.
 */
if ( ! function_exists( 'zerospam_autoloader' ) )
{
  function zerospam_autoloader( $class_name )
  {
    if ( false !== strpos( $class_name, 'ZeroSpam' ) )
    {
      $classes_dir = ZEROSPAM_ROOT . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR;
      $class_file = str_replace( '_', DIRECTORY_SEPARATOR, $class_name ) . '.php';

      /**
       * Include the plugin library.
       */
      require_once $classes_dir . $class_file;

      return true;
    }

    return false;
  }
}
