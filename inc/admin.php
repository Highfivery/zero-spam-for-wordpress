<?php
/**
 * Admin interface & functionality
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

function wpzerospam_admin_menu() {
  add_menu_page(
    __( 'WordPress Zero Spam Dashboard', 'wpzerospam' ),
    __( 'WP Zero Spam', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam',
    'wpzerospam_dashboard',
    'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iNTEycHgiIGhlaWdodD0iNDc4cHgiIHZpZXdCb3g9IjAgMCA1MTIgNDc4IiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCA1OCAoODQ2NjMpIC0gaHR0cHM6Ly9za2V0Y2guY29tIC0tPgogICAgPHRpdGxlPmljb248L3RpdGxlPgogICAgPGRlc2M+Q3JlYXRlZCB3aXRoIFNrZXRjaC48L2Rlc2M+CiAgICA8ZyBpZD0iUGFnZS0xIiBzdHJva2U9Im5vbmUiIHN0cm9rZS13aWR0aD0iMSIgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj4KICAgICAgICA8ZyBpZD0iaWNvbiIgZmlsbD0iI0ExQTVBOSIgZmlsbC1ydWxlPSJub256ZXJvIj4KICAgICAgICAgICAgPHBhdGggZD0iTTE1LDExMiBMMjU2LjIyMDA1MiwxMTIgTDI1Ni4yMjAwNTIsMTEyIEwyNTYuMjIwMDUyLDE1IEMyNTYuMjIwMDUyLDYuNzE1NzI4NzUgMjYyLjkzNTc4MSwwIDI3MS4yMjAwNTIsMCBMNDE2LDAgQzQyNC4yODQyNzEsMCA0MzEsNi43MTU3Mjg3NSA0MzEsMTUgTDQzMSwxMTIgTDQzMSwxMTIgTDQ5NywxMTIgQzUwNS4yODQyNzEsMTEyIDUxMiwxMTguNzE1NzI5IDUxMiwxMjcgTDUxMiw0NjMgQzUxMiw0NzEuMjg0MjcxIDUwNS4yODQyNzEsNDc4IDQ5Nyw0NzggTDE1LDQ3OCBDNi43MTU3Mjg3NSw0NzggMCw0NzEuMjg0MjcxIDAsNDYzIEwwLDEyNyBDMCwxMTguNzE1NzI5IDYuNzE1NzI4NzUsMTEyIDE1LDExMiBaIiBpZD0iUmVjdGFuZ2xlIj48L3BhdGg+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4='
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'WordPress Zero Spam Dashboard', 'wpzerospam' ),
    __( 'Dashboard', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam',
    'wpzerospam_dashboard'
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'Spam Detections', 'wpzerospam' ),
    __( 'Spam Detections', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam-detections',
    'wpzerospam_spam_detections_page'
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'Blocked IP Addresses', 'wpzerospam' ),
    __( 'Blocked IPs', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam-blocked-ips',
    'wpzerospam_blocked_ips_page'
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'Blacklisted IPs', 'wpzerospam' ),
    __( 'Blacklisted IPs', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam-blacklisted',
    'wpzerospam_blacklist_page'
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'WordPress Zero Spam Settings', 'wpzerospam' ),
    __( 'Settings', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam-settings',
    'wpzerospam_options_page'
  );
}
add_action( 'admin_menu', 'wpzerospam_admin_menu' );

function wpzerospam_spam_detections_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/callout.php'; ?>

    <?php
    /**
     * Log table
     */
    require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'classes/class-wpzerospam-log-table.php';

    $table_data = new WPZeroSpam_Log_Table();

    // Fetch, prepare, sort, and filter our data...
    $table_data->prepare_items();
    ?>
    <form id="log-table" method="post">
      <?php wp_nonce_field( 'wpzerospam_nonce', 'wpzerospam_nonce' ); ?>
      <input type="hidden" name="paged" value="1" />
      <?php $table_data->search_box( __( 'Search IPs', 'wpzerospam' ), 'search-ip' ); ?>
      <?php $table_data->display(); ?>
    </form>
  </div>
  <?php
}

function wpzerospam_blacklist_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/callout.php'; ?>

    <?php
    /**
     * Blocked IP table
     */
    require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'classes/class-wpzerospam-blacklisted-table.php';

    $table_data = new WPZeroSpam_Blacklisted_Table();

    // Fetch, prepare, sort, and filter our data...
    $table_data->prepare_items();
    ?>
    <form id="blacklist-table" method="post">
      <?php wp_nonce_field( 'wpzerospam_nonce', 'wpzerospam_nonce' ); ?>
      <input type="hidden" name="paged" value="1" />
      <?php $table_data->search_box( __( 'Search IPs', 'wpzerospam' ), 'search-ip' ); ?>
      <?php $table_data->display(); ?>
    </form>
  </div>
  <?php
}

function wpzerospam_add_blocked_ip_action() {
  if ( ! empty( $_POST ) ) {
    $ip                 = sanitize_text_field( $_POST['blocked_ip'] );
    $type               = in_array( sanitize_text_field( $_POST['blocked_type'] ), [ 'permanent', 'temporary' ] ) ? sanitize_text_field( $_POST['blocked_type'] ) : false;
    $reason             = sanitize_text_field( $_POST['blocked_reason'] );
    $blocked_start_date = sanitize_text_field( $_POST['blocked_start_date'] );
    $blocked_end_date   = sanitize_text_field( $_POST['blocked_end_date'] );

    if ( ! $ip || false === WP_Http::is_ip_address( $ip ) ) {
      wp_redirect( $_SERVER['HTTP_REFERER'] . '&error=1' );
      exit;
    }

    if ( ! $type ) {
      wp_redirect( $_SERVER['HTTP_REFERER'] . '&error=2' );
      exit;
    }

    $data = [ 'blocked_type' => $type ];

    if ( $reason ) {
      $data['reason'] = $reason;
    } else {
      $data['reason'] = NULL;
    }

    if ( $blocked_start_date ) {
      $data['start_block'] = date( 'Y-m-d G:i:s', strtotime( $blocked_start_date ));
    } else {
      $data['start_block'] = NULL;
    }

    if ( $blocked_end_date ) {
      $data['end_block'] = date( 'Y-m-d G:i:s', strtotime( $blocked_end_date ));
    } else {
      $data['end_block'] = NULL;
    }

    if ( 'temporary' == $type && ! $data['end_block']  ) {
      wp_redirect( $_SERVER['HTTP_REFERER'] . '&error=3' );
      exit;
    }

    $data['attempts'] = 0;

    wpzerospam_update_blocked_ip( $ip, $data );
  }

  wp_redirect( $_SERVER['HTTP_REFERER'] . '&success=1' );
  exit();
}
add_action( 'admin_action_add_blocked_ip', 'wpzerospam_add_blocked_ip_action' );

function wpzerospam_blocked_ips_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/callout.php'; ?>

    <?php if ( ! empty( $_GET['error'] ) ): ?>
      <div class="notice notice-error is-dismissible">
        <p><strong>
          <?php
          switch( $_GET['error'] ):
            case 1:
              _e( 'Please enter a valid IP address.', 'wpzerospam' );
            break;
            case 2:
              _e( 'Please select a valid type.', 'wpzerospam' );
            break;
            case 3:
              _e( 'Please select a date & time when the temporary block should end.', 'wpzerospam' );
            break;
          endswitch;
          ?>
        </strong></p>
        <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
      </div>
    <?php elseif ( ! empty( $_GET['success'] ) ): ?>
      <div class="notice notice-success is-dismissible">
        <p><strong><?php _e( 'The blocked IP has been successfully added.', 'wpzerospam' ); ?></strong></p>
        <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
      </div>
    <?php endif; ?>
    <form method="post" action="<?php echo admin_url( 'admin.php' ); ?>">
      <input type="hidden" name="action" value="add_blocked_ip" />
      <div class="wpzerospam-callout wpzerospam-add-ip-container<?php if( ! empty( $_REQUEST['ip'] ) ): ?> wpzerospam-add-ip-container-highlight<?php endif; ?>">
        <h2><?php _e( 'Add Blocked IP', 'wpzerospam' ); ?></h2>
        <div class="wpzerospam-add-ip-field">
          <label for="blocked-ip"><?php _e( 'IP Address', 'wpzerospam' ); ?></label>
          <input
            type="text"
            id="blocked-ip"
            name="blocked_ip"
            value="<?php if( ! empty( $_REQUEST['ip'] ) ): echo esc_attr( $_REQUEST['ip'] ); endif; ?>"
            placeholder="e.g. xxx.xxx.x.x"
          />
        </div>
        <div class="wpzerospam-add-ip-field">
          <label for="blocked-type"><?php _e( 'Type', 'wpzerospam' ); ?></label>
          <select id="blocked-type" name="blocked_type">
            <option value="temporary"><?php _e( 'Temporary', 'wpzerospam' ); ?></option>
            <option value="permanent"><?php _e( 'Permanent', 'wpzerospam' ); ?></option>
          </select>
        </div>
        <div class="wpzerospam-add-ip-field" id="wpzerospam-add-ip-field-reason">
          <label for="blocked-reason"><?php _e( 'Reason', 'wpzerospam' ); ?></label>
          <input type="text" id="blocked-reason" name="blocked_reason" value="" placeholder="<?php _e( 'e.g. Spammed form', 'wpzerospam' ); ?>" />
        </div>
        <div class="wpzerospam-add-ip-field" id="wpzerospam-add-ip-field-start-date">
          <label for="blocked-start-date"><?php _e( 'Start Date', 'wpzerospam' ); ?></label>
          <input type="datetime-local" id="blocked-start-date" name="blocked_start_date" value="" placeholder="<?php _e( 'Optional', 'wpzerospam' ); ?>" />
        </div>
        <div class="wpzerospam-add-ip-field" id="wpzerospam-add-ip-field-end-date">
          <label for="blocked-end-date"><?php _e( 'End Date', 'wpzerospam' ); ?></label>
          <input type="datetime-local" id="blocked-end-date" name="blocked_end_date" value="" placeholder="<?php _e( 'Optional', 'wpzerospam' ); ?>" />
        </div>
        <div class="wpzerospam-add-ip-field" id="wpzerospam-add-ip-field-submit">
          <input type="submit" class="button button-primary" value="<?php _e( 'Add Blocked IP', 'wpzerospam' ); ?>" />
        </div>
      </div>
    </form>

    <?php
    /**
     * Blocked IP table
     */
    require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'classes/class-wpzerospam-blocked-ip-table.php';

    $table_data = new WPZeroSpam_Blocked_IP_Table();

    // Fetch, prepare, sort, and filter our data...
    $table_data->prepare_items();
    ?>
    <form id="blocked-table" method="post">
      <?php wp_nonce_field( 'wpzerospam_nonce', 'wpzerospam_nonce' ); ?>
      <input type="hidden" name="paged" value="1" />
      <?php $table_data->search_box( __( 'Search IPs', 'wpzerospam' ), 'search-ip' ); ?>
      <?php $table_data->display(); ?>
    </form>
  </div>
  <?php
}

function wpzerospam_dashboard() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }

  $log = wpzerospam_query( 'log' );

  $predefined_colors = [
    '#1a0003', '#4d000a', '#800011', '#b30017', '#e6001e', '#ff1a38', '#ff4d64', '#ff8090', '#ffb3bd', '#ffe5e9'
  ];
  ?>
    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

      <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/callout.php'; ?>

      <h2><?php _e( 'Statistics', 'wpzerospam' ); ?></h2>
      <div class="wpzerospam-boxes">
        <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/map.php'; ?>
        <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/ip-list.php'; ?>
        <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/countries-pie-chart.php'; ?>
        <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/regions-pie-chart.php'; ?>
        <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/spam-line-chart.php'; ?>
      </div>
    </div>
  <?php
}

function wpzerospam_options_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . 'templates/callout.php'; ?>

    <form action="options.php" method="post">
    <?php
    // Output security fields for the registered setting "wpzerospam"
    settings_fields( 'wpzerospam' );

    // Output setting sections and their fields
    do_settings_sections( 'wpzerospam' );

    // Output save settings button
    submit_button( 'Save Settings' );
    ?>
    </form>
  </div>
<?php
}

function wpzerospam_validate_options( $input ) {
  if ( empty( $input['log_spam'] ) ) { $input['log_spam'] = 'disabled'; }
  if ( empty( $input['verify_comments'] ) ) { $input['verify_comments'] = 'disabled'; }
  if ( empty( $input['verify_registrations'] ) ) { $input['verify_registrations'] = 'disabled'; }
  if ( empty( $input['log_blocked_ips'] ) ) { $input['log_blocked_ips'] = 'disabled'; }
  if ( empty( $input['auto_block_ips'] ) ) { $input['auto_block_ips'] = 'disabled'; }
  if ( empty( $input['auto_block_period'] ) ) { $input['auto_block_period'] = 0; }
  if ( empty( $input['botscout_api'] ) ) { $input['botscout'] = false; }
  if ( empty( $input['auto_block_permanently'] ) ) { $input['auto_block_permanently'] = 3; }
  if ( empty( $input['api_timeout'] ) ) { $input['api_timeout'] = 5; }
  if ( empty( $input['stopforumspam_confidence_min'] ) ) { $input['stopforumspam_confidence_min'] = 20; }

  if ( empty( $input['ip_whitelist'] ) ) {
    $input['ip_whitelist'] = '';
  } else {
    $whitelist         = explode( PHP_EOL, $input['ip_whitelist'] );
    $cleaned_whitelist = '';
    foreach( $whitelist as $k => $whitelisted_ip ) {
      $whitelisted_ip = trim( $whitelisted_ip );

      if ( rest_is_ip_address( $whitelisted_ip ) ) {
        if ( $cleaned_whitelist ) { $cleaned_whitelist .= "\n"; }
        $cleaned_whitelist .= $whitelisted_ip;
      }
    }

    $input['ip_whitelist'] = $cleaned_whitelist;
  }

  if ( empty( $input['verify_cf7'] ) ) {
    $input['verify_cf7'] = 'disabled';
  }

  if ( empty( $input['verify_gform'] ) ) {
    $input['verify_gform'] = 'disabled';
  }

  if ( empty( $input['verify_bp_registrations'] ) ) {
    $input['verify_bp_registrations'] = 'disabled';
  }

  if ( empty( $input['verify_wpforms'] ) ) {
    $input['verify_wpforms'] = 'disabled';
  }

  if ( empty( $input['verify_fluentform'] ) ) {
    $input['verify_fluentform'] = 'disabled';
  }

  if ( empty( $input['verify_formidable'] ) ) {
    $input['verify_formidable'] = 'disabled';
  }

  if ( empty( $input['stop_forum_spam'] ) ) {
    $input['stop_forum_spam'] = 'disabled';
  }

  if ( empty( $input['strip_comment_links'] ) ) {
    $input['strip_comment_links'] = 'disabled';
  }

  if ( empty( $input['share_detections'] ) ) {
    $input['share_detections'] = 'disabled';
  }

  if ( empty( $input['strip_comment_author_links'] ) ) {
    $input['strip_comment_author_links'] = 'disabled';
  }

  if ( empty( $input['blocked_message'] ) ) {
    $input['blocked_message'] = 'You have been blocked from visiting this site by WordPress Zero Spam due to detected spam activity.';
  }

  return $input;
}

/**
 * Add settings link to plugin description
 */
function wpzerospam_admin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
  $links = [
    'settings' => '<a href="' . admin_url( 'admin.php?page=wordpress-zero-spam-settings' ) . '">' . __( 'Settings' ) . '</a>'
  ];

  return array_merge( $links, $actions );
}


function wpzerospam_admin_init() {
  if(  ! function_exists( 'is_plugin_active' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  }

  $options = wpzerospam_options();

  // Add settings link to plugin description
  add_filter( 'plugin_action_links_' . plugin_basename( WORDPRESS_ZERO_SPAM ), 'wpzerospam_admin_action_links', 10, 4 );

  register_setting( 'wpzerospam', 'wpzerospam', 'wpzerospam_validate_options' );

  add_settings_section( 'wpzerospam_general_settings', __( 'General Settings', 'wpzerospam' ), 'wpzerospam_general_settings_cb', 'wpzerospam' );
  add_settings_section( 'wpzerospam_autoblocks', __( 'Auto-block Settings', 'wpzerospam' ), 'wpzerospam_autoblock_settings_cb', 'wpzerospam' );
  add_settings_section( 'wpzerospam_onsite', __( 'On-site Spam Prevention', 'wpzerospam' ), 'wpzerospam_onsite_cb', 'wpzerospam' );
  add_settings_section( 'wpzerospam_spam_checks', __( 'Integrations & Third-party APIs', 'wpzerospam' ), 'wpzerospam_spam_checks_cb', 'wpzerospam' );

  // Determines is spam detections should be shared with WordPress Zero Spam
  add_settings_field( 'share_detections', __( 'Share Spam Detections', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for' => 'share_detections',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Help support WordPress Zero Spam and strenghten its ability to detect spammers by sharing spam detections. The only data that\'s shared is the IP address, type & site where the spam was detected. <strong>Absolutely no personal data is shared.</strong>',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Determines is spam detected IPs should automatically be blocked
  add_settings_field( 'auto_block_ips', __( 'Auto-block IPs', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_autoblocks', [
    'label_for' => 'auto_block_ips',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Auto-blocks IPs addresses that trigger a spam detection.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  if ( 'enabled' == $options['auto_block_ips'] ) {
    // Number of minutes a IP should be blocked after a auto-block
    add_settings_field( 'auto_block_period', __( 'Auto-block Period', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_autoblocks', [
      'label_for'   => 'auto_block_period',
      'type'        => 'number',
      'desc'        => 'Number of minutes a user will be blocked from viewing the site after being auto-blocked.',
      'class'       => 'small-text',
      'placeholder' => '30',
      'suffix'      => __( 'minutes', 'wpzerospam' )
    ]);
  }

  // Number of spam attempts before the IP is permanently blocked
  add_settings_field( 'auto_block_permanently', __( 'Permanently Auto-block', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_autoblocks', [
    'label_for'   => 'auto_block_permanently',
    'type'        => 'number',
    'desc'        => 'Number of spam detections before an IP is permanently blocked.',
    'class'       => 'small-text',
    'placeholder' => 3
  ]);

  // Option to strips links in comments
  add_settings_field( 'strip_comment_links', __( 'Strip Comment Links', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_onsite', [
    'label_for' => 'strip_comment_links',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Spambots commonly post spam links in comments. Enable this option to strip links from comments.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Option to remove author links
  add_settings_field( 'strip_comment_author_links', __( 'Strip Comment Author Links', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_onsite', [
    'label_for' => 'strip_comment_author_links',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Spammers are well-known at injecting malicious links in the comment author website field, this option disables it.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

   // API timeout
   add_settings_field( 'api_timeout', __( 'API Timeout', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
    'label_for'   => 'api_timeout',
    'type'        => 'number',
    'desc'        => 'Number of seconds to allow an API to return a response.<br /><strong>WARNING:</strong> Setting this too high could cause your site to load slowly. Setting too low may not allow an API enough time to respond with a result. <strong>Recommended is 5 seconds.</strong>',
    'class'       => 'small-text',
    'placeholder' => '30',
    'suffix'      => __( 'seconds', 'wpzerospam' )
  ]);

  if ( 'enabled' == $options['log_spam'] ) {
    // Redirect URL for spam detections
    add_settings_field( 'ipstack_api', __( 'ipstack API Key', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for'   => 'ipstack_api',
      'type'        => 'text',
      'placeholder' => __( 'Enter your ipstack API key.', 'wpzerospam' ),
      'class'       => 'regular-text',
      'desc'        => 'Enter your <a href="https://ipstack.com/" target="_blank">ipstack API key</a> to enable location-based statistics. Don\'t have an API key? <a href="https://ipstack.com/signup/free" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>',
    ]);
  }

  // Enables the ability to check IPs against BotScout blacklists.
  add_settings_field( 'botscout_api', __( 'BotScout API Key', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
    'label_for'   => 'botscout_api',
    'type'        => 'text',
    'class'       => 'regular-text',
    'placeholder' => __( 'Enter your free BotScout API key.', 'wpzerospam' ),
    'desc'        => 'Enter your BotScout API key to check user IPs against <a href="https://botscout.com/" target="_blank" rel="noopener noreferrer">BotScout</a>\'s blacklist. Don\'t have an API key? <a href="https://botscout.com/getkey.htm" target="_blank" rel="noopener noreferrer"><strong>Get one for free!</strong></a>'
  ]);

  // Enables the ability to check IPs against Stop Forum Spam blacklists.
  add_settings_field( 'stop_forum_spam', __( 'Stop Forum Spam', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
    'label_for' => 'stop_forum_spam',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Checks user IPs against <a href="https://www.stopforumspam.com/" target="_blank" rel="noopener noreferrer">Stop Forum Spam</a>\'s blacklist.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // StopForumSpam confidence minimum
  add_settings_field( 'stopforumspam_confidence_min', __( 'Stop Forum Spam Confidence Minimum', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
    'label_for'   => 'stopforumspam_confidence_min',
    'type'        => 'number',
    'desc'        => 'Minimum <a href="https://www.stopforumspam.com/usage" target="_blank" rel="noopener noreferrer">confidence score</a> an IP must meet before being marked as spam/malicious.<br /><strong>WARNING:</strong> Setting this too low could cause users to be blocked that shouldn\'t be, <strong>recommended is 20%</strong>.',
    'class'       => 'small-text',
    'placeholder' => '20',
    'suffix'      => __( '%', 'wpzerospam' )
  ]);

  // How to handle blocks
  add_settings_field( 'block_handler', __( 'Blocked IPs', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for' => 'block_handler',
    'type'      => 'radio',
    'desc'      => 'Determines how blocked IPs are handled when they attempt to visit the site.',
    'options'   => [
      'redirect' => __( 'Redirect user', 'wpzerospam' ),
      '403'      => __( 'Display a <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/403" target="_blank"><code>403 Forbidden</code></a> error', 'wpzerospam' )
    ]
  ]);

  if ( 'redirect' == $options['block_handler'] ) {
    // Redirect URL for blocked users
    add_settings_field( 'blocked_redirect_url', __( 'Redirect for Blocked Users', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
      'label_for'   => 'blocked_redirect_url',
      'type'        => 'url',
      'class'       => 'regular-text',
      'desc'        => 'URL blocked users will be taken to.',
      'placeholder' => 'e.g. https://google.com'
    ]);
  } else {
    // Blocked message
    add_settings_field( 'blocked_message', __( 'Blocked Message', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
      'label_for'   => 'blocked_message',
      'type'        => 'text',
      'class'       => 'large-text',
      'desc'        => 'The message that will be displayed to a blocked user.',
      'placeholder' => __( 'You have been blocked from visiting this site by WordPress Zero Spam due to detected spam activity.', 'wpzerospam' )
    ]);
  }

  // How to handle spam detections
  add_settings_field( 'spam_handler', __( 'Spam Detections', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for' => 'spam_handler',
    'type'      => 'radio',
    'desc'      => 'Determines how users are handled when spam is detected.',
    'options'   => [
      'redirect' => __( 'Redirect user', 'wpzerospam' ),
      '403'      => __( 'Display a <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/403" target="_blank"><code>403 Forbidden</code></a> error', 'wpzerospam' )
    ]
  ]);

  if ( 'redirect' == $options['spam_handler'] ) {
    // Redirect URL for spam detections
    add_settings_field( 'spam_redirect_url', __( 'Redirect for Spam', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
      'label_for'   => 'spam_redirect_url',
      'type'        => 'url',
      'class'       => 'regular-text',
      'desc'        => 'URL users will be taken to when a spam submission is detected.',
      'placeholder' => 'e.g. https://google.com'
    ]);
  } else {
    // Spam message
    add_settings_field( 'spam_message', __( 'Spam Detection Message', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
      'label_for'   => 'spam_message',
      'type'        => 'text',
      'class'       => 'large-text',
      'desc'        => 'The message that will be displayed when spam is detected.',
      'placeholder' => __( 'There was a problem with your submission. Please go back and try again.', 'wpzerospam' )
    ]);
  }

  // Toggle logging of blocked IPs
  add_settings_field( 'log_blocked_ips', __( 'Log Blocked IPs', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for' => 'log_blocked_ips',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Enables logging of when IPs are blocked from accessing the site.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Log spam detections
  add_settings_field( 'log_spam', __( 'Log Spam Detections', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for' => 'log_spam',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Enables logging of spam detections and provides an admin interface to view statistics.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Comment spam check
  add_settings_field( 'verify_comments', __( 'Verify Comments', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
    'label_for' => 'verify_comments',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Enables spam detection of submitted comments.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Registration spam check
  add_settings_field( 'verify_registrations', __( 'Verify Registrations', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
    'label_for' => 'verify_registrations',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Enables spam detection for site registrations.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Contact Form 7 spam check
  if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
    add_settings_field( 'verify_cf7', __( 'Verify CF7 Submissions', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_cf7',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for Contact Form 7 submissions.',
      'options'   => [
        'enabled' => __( 'Enabled', 'wpzerospam' )
      ]
    ]);
  }

  // Gravity Forms spam check
  if ( is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
    add_settings_field( 'verify_gform', __( 'Verify Gravity Forms Submissions', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_gform',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for Gravity Forms submissions.',
      'options'   => [
        'enabled' => __( 'Enabled', 'wpzerospam' )
      ]
    ]);
  }

  // BuddyPress registrations spam check
  if ( function_exists( 'bp_is_active' ) ) {
    add_settings_field( 'verify_bp_registrations', __( 'Verify BuddyPress Registrations', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_bp_registrations',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for BuddyPress registrations.',
      'options'   => [
        'enabled' => __( 'Enabled', 'wpzerospam' )
      ]
    ]);
  }

  // WPForms spam check
  if ( is_plugin_active( 'wpforms/wpforms.php' ) || is_plugin_active( 'wpforms-lite/wpforms.php' ) ) {
    add_settings_field( 'verify_wpforms', __( 'Verify WPForms Submissions', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_wpforms',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for WPForms submissions.',
      'options'   => [
        'enabled' => __( 'Enabled', 'wpzerospam' )
      ]
    ]);
  }

  // Fluent Form spam check
  if ( is_plugin_active( 'fluentform/fluentform.php' ) ) {
    add_settings_field( 'verify_fluentform', __( 'Verify Fluent Form Submissions', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_fluentform',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for Fluent Form submissions.',
      'options'   => [
        'enabled' => __( 'Enabled', 'wpzerospam' )
      ]
    ]);
  }

  // Formidable forms spam check
  if ( is_plugin_active( 'formidable/formidable.php' ) ) {
    add_settings_field( 'verify_formidable', __( 'Verify Formidable Form Submissions', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_formidable',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for Formidable form submissions.',
      'options'   => [
        'enabled' => __( 'Enabled', 'wpzerospam' )
      ]
    ]);
  }

  // IP whitelist
  add_settings_field( 'ip_whitelist', __( 'IP Whitelist', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for'   => 'ip_whitelist',
    'type'        => 'textarea',
    'class'       => 'large-text',
    'desc'        => 'Enter IPs that should be whitelisted (IPs that should never be blocked), one per line.',
    'placeholder' => __( 'e.g. xxx.xxx.x.x', 'wpzerospam' )
  ]);
}
add_action( 'admin_init', 'wpzerospam_admin_init' );

function wpzerospam_general_settings_cb() {
}

function wpzerospam_autoblock_settings_cb() {
}

function wpzerospam_spam_checks_cb() {
}

function wpzerospam_onsite_cb() {
}

function wpzerospam_whitelist_cb() {
}

function wpzerospam_field_cb( $args ) {
  $options = wpzerospam_options();

  switch( $args['type'] ) {
    case 'url':
    case 'text':
    case 'password':
    case 'number':
    case 'email':
      ?>
      <input class="<?php echo $args['class']; ?>" type="<?php echo $args['type']; ?>" value="<?php if ( ! empty( $options[ $args['label_for'] ] ) ): echo esc_attr( $options[ $args['label_for'] ] ); endif; ?>" placeholder="<?php if ( ! empty( $args['placeholder'] ) ): echo $args['placeholder']; endif; ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]"><?php if ( ! empty( $args['suffix'] ) ): echo ' ' . $args['suffix']; endif; ?>
      <p class="description"><?php echo $args['desc'] ?></p>
      <?php
    break;
    case 'textarea':
      ?>
      <textarea rows="10" class="<?php echo $args['class']; ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]"><?php if ( ! empty( $options[ $args['label_for'] ] ) ): echo esc_attr( $options[ $args['label_for'] ] ); endif; ?></textarea>
      <p class="description"><?php echo $args['desc'] ?></p>
      <?php
    break;
    case 'select':
      ?>
      <select name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]" id="<?php echo esc_attr( $args['label_for'] ); ?>">
        <?php foreach( $args['options'] as $key => $label ): ?>
          <option value="<?php echo $key; ?>"<?php if ( $key === $options[ $args['label_for'] ] ): ?> selected="selected"<?php endif; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
      </select>
      <p class="description"><?php echo $args['desc'] ?></p>
      <?php
    break;
    case 'checkbox':
      ?>
      <?php foreach( $args['options'] as $key => $label ): ?>
        <label for="<?php echo esc_attr( $args['label_for'] . $key ); ?>">
          <input
            type="checkbox"
            <?php if ( ! empty( $args['class'] ) ): ?>class="<?php echo $args['class']; ?>"<?php endif; ?>
            id="<?php echo esc_attr( $args['label_for'] . $key ); ?>"
            name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]<?php if( $args['multi'] ): ?>[<?php echo $key; ?>]<?php endif; ?>" value="<?php echo $key; ?>"
            <?php if( $args['multi'] && $key === $options[ $args['label_for'] ][ $key ] || ! $args['multi'] && $key === $options[ $args['label_for'] ] ): ?> checked="checked"<?php endif; ?> /> <?php echo $label; ?>
        </label>
      <?php endforeach; ?>
      <p class="description"><?php echo $args['desc'] ?></p>
      <?php
    break;
    case 'radio':
      ?>
      <?php foreach( $args['options'] as $key => $label ): ?>
        <label for="<?php echo esc_attr( $args['label_for'] . $key ); ?>">
          <input
            type="radio"
            <?php if ( ! empty( $args['class'] ) ): ?>class="<?php echo $args['class']; ?>"<?php endif; ?>
            id="<?php echo esc_attr( $args['label_for'] . $key ); ?>"
            name="wpzerospam[<?php echo esc_attr( $args['label_for'] ); ?>]" value="<?php echo $key; ?>"
            <?php if( $key == $options[ $args['label_for'] ] ): ?> checked="checked"<?php endif; ?> /> <?php echo $label; ?>
        </label><br />
      <?php endforeach; ?>
      <p class="description"><?php echo $args['desc'] ?></p>
      <?php
    break;
  }
  ?>
  <?php
}
