<?php
class ZeroSpam_Admin extends ZeroSpam_Plugin {
  public $tabs = array();

  public function run() {
    // Merge and update new changes
    if ( isset( $_POST['zerospam_general_settings'] ) ) {
      $saved_settings = array();
      foreach ( $this->default_settings as $key => $val ) {
        if ( isset( $_POST['zerospam_general_settings'][$key] ) ) {
          $saved_settings[$key] = $_POST['zerospam_general_settings'][$key];
        } else {
          $saved_settings[$key] = 0;
        }
      }

      if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
        update_site_option( 'zerospam_general_settings', $saved_settings );
      } else {
        update_option( 'zerospam_general_settings', $saved_settings );
      }
      $this->load_settings();
    }

    $this->tabs['zerospam_general_settings'] = 'General Settings';
    $this->tabs['zerospam_ip_block'] = 'Blocked IPs';

    if ( ! empty( $this->settings['log_spammers'] ) && $this->settings['log_spammers'] ) {
      $this->tabs['zerospam_spammer_logs'] = 'Spammer Log';
    }

    if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
      add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
      add_action( 'network_admin_edit_zerospam', array( $this, 'update_network_setting' ) );
    }

    add_action( 'admin_init', array( $this, 'admin_init' ) );
    add_action( 'admin_menu', array( $this, 'admin_menu' ) );

    add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
  }

  /**
   * Add admin scripts.
   *
   * Adds the CSS & JS for the WordPress Zero Spam settings page.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
   *
   * @param string $hook Used to target a specific admin page.
   * @return void
   */
  public function admin_enqueue_scripts( $hook ) {
    if ( 'settings_page_zerospam' != $hook ) {
          return;
    }

    // Create nonce for AJAX requests.
    $ajax_nonce = wp_create_nonce( 'zero-spam' );

    // Register the WordPress Zero Spam admin script.
    wp_register_script(
      'zero-spam-admin', plugin_dir_url( ZEROSPAM_PLUGIN ) .
      'js/zero-spam-admin.js'
    );

    // Localize the script with the plugin data.
    $zero_spam_array = array( 'nonce' => $ajax_nonce );
    wp_localize_script( 'zero-spam-admin', 'zero_spam_admin', $zero_spam_array );

    // Enqueue the script.
    wp_enqueue_script( 'zero-spam-admin' );
  }

  /**
   * Uses admin_init.
   *
   * Triggered before any other hook when a user accesses the admin area.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_init
   */
  public function admin_init() {
    register_setting( 'zerospam_general_settings', 'zerospam_general_settings' );

    add_settings_section( 'section_general', __( 'General Settings', 'zerospam' ), false, 'zerospam_general_settings' );
    add_settings_section( 'section_messages', __( 'Messages', 'zerospam' ), false, 'zerospam_general_settings' );

    add_settings_field( 'wp_generator', __( 'WP Generator Meta Tag', 'zerospam' ), array( $this, 'field_wp_generator' ), 'zerospam_general_settings', 'section_general' );
    add_settings_field( 'log_spammers', __( 'Log Spammers', 'zerospam' ), array( $this, 'field_log_spammers' ), 'zerospam_general_settings', 'section_general' );

    if ( ! empty( $this->settings['log_spammers'] ) && $this->settings['log_spammers'] ) {
      // IP location API support.
      add_settings_field( 'ip_location_support', __( 'IP Location Support', 'zerospam' ), array( $this, 'field_ip_location_support' ), 'zerospam_general_settings', 'section_general' );

      // Auto IP block support.
      add_settings_field( 'auto_block', __( 'Auto IP Block', 'zerospam' ), array( $this, 'field_auto_block' ), 'zerospam_general_settings', 'section_general' );
    }

    add_settings_field( 'blocked_ip_msg', __( 'Blocked IP Message', 'zerospam' ), array( $this, 'field_blocked_ip_msg' ), 'zerospam_general_settings', 'section_messages' );

    add_settings_field( 'comment_support', __( 'Comment Support', 'zerospam' ), array( $this, 'field_comment_support' ), 'zerospam_general_settings', 'section_general' );

    // Comment support.
    if ( ! empty( $this->settings['comment_support'] ) && $this->settings['comment_support'] ) {
      add_settings_field( 'spammer_msg_comment', __( 'Spam Comment Message', 'zerospam' ), array( $this, 'field_spammer_msg_comment' ), 'zerospam_general_settings', 'section_messages' );
    }

    // Registration support.
    add_settings_field( 'registration_support', __( 'Registration Support', 'zerospam' ), array( $this, 'field_registration_support' ), 'zerospam_general_settings', 'section_general' );
    if ( ! empty( $this->settings['registration_support'] ) && $this->settings['registration_support'] ) {
      add_settings_field( 'spammer_msg_registration', __( 'Spam Registration Message', 'zerospam' ), array( $this, 'field_spammer_msg_registration' ), 'zerospam_general_settings', 'section_messages' );
    }

    // Contact Form 7 support.
    if ( zerospam_plugin_check( 'cf7' ) ) {
      add_settings_field( 'cf7_support', __( 'Contact Form 7 Support', 'zerospam' ), array( $this, 'field_cf7_support' ), 'zerospam_general_settings', 'section_general' );

      if ( ! empty( $this->settings['cf7_support'] ) && $this->settings['cf7_support'] ) {
        add_settings_field( 'spammer_msg_contact_form_7', __( 'Contact Form 7 Spam Message', 'zerospam' ), array( $this, 'field_spammer_msg_contact_form_7' ), 'zerospam_general_settings', 'section_messages' );
      }
    }

    // Gravity Forms support.
    if ( zerospam_plugin_check( 'gf' ) ) {
      add_settings_field( 'gf_support', __( 'Gravity Forms Support', 'zerospam' ), array( $this, 'field_gf_support' ), 'zerospam_general_settings', 'section_general' );
    }

    // BuddyPress support.
    if ( zerospam_plugin_check( 'bp' ) ) {
      add_settings_field( 'bp_support', __( 'BuddyPress Support', 'zerospam' ), array( $this, 'field_bp_support' ), 'zerospam_general_settings', 'section_general' );

      if ( ! empty( $this->settings['bp_support'] ) && $this->settings['bp_support'] ) {
        add_settings_field( 'spammer_msg_bp', __( 'BuddyPress Spam Message', 'zerospam' ), array( $this, 'field_spammer_msg_bp' ), 'zerospam_general_settings', 'section_messages' );
      }
    }

    // Ninja Forms support.
    if ( zerospam_plugin_check( 'nf' ) ) {
      add_settings_field( 'nf_support', __( 'Ninja Forms Support', 'zerospam' ), array( $this, 'field_nf_support' ), 'zerospam_general_settings', 'section_general' );

      if ( ! empty( $this->settings['nf_support'] ) && $this->settings['nf_support'] ) {
        add_settings_field( 'spammer_msg_nf', __( 'Ninja Forms Spam Message', 'zerospam' ), array( $this, 'field_spammer_msg_nf' ), 'zerospam_general_settings', 'section_messages' );
      }
    }
  }

  /**
   * Spam Ninja Forms message option.
   *
   * Field callback, renders a text input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_spammer_msg_nf() {
    ?>
    <label for="spammer_msg_nf">
      <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_nf]" value="<?php echo esc_attr( $this->settings['spammer_msg_nf'] ); ?>">
    <p class="description"><?php echo __( 'Enter a short message to display when a spam Ninja Form has been submitted.', 'zerospam' ); ?></p>
    </label>
    <?php
  }

  /**
   * Ninja Forms support option.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_nf_support() {
    ?>
    <label for="nf_support">
      <input type="checkbox" id="nf_support" name="zerospam_general_settings[nf_support]" value="1" <?php if( isset( $this->settings['nf_support'] ) ) : checked( $this->settings['nf_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>
    <?php
  }

  /**
   * BuddyPress spam message option.
   *
   * Field callback, renders a text input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_spammer_msg_bp() {
    ?>
    <label for="spammer_msg_bp">
      <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_bp]" value="<?php echo esc_attr( $this->settings['spammer_msg_bp'] ); ?>">
      <p class="description"><?php echo __( 'Enter a short message to display when a spam BuddyPress registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
    </label>
    <?php
  }

  /**
   * BuddyPress support option.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_bp_support() {
    ?>
    <label for="bp_support">
      <input type="checkbox" id="bp_support" name="zerospam_general_settings[bp_support]" value="1" <?php if( isset( $this->settings['bp_support'] ) ) : checked( $this->settings['bp_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>
    <?php
  }

  /**
   * Gravity Forms support option.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_gf_support() {
    ?>
    <label for="gf_support">
      <input type="checkbox" id="gf_support" name="zerospam_general_settings[gf_support]" value="1" <?php if( isset( $this->settings['gf_support'] ) ) : checked( $this->settings['gf_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>
    <?php
  }

  /**
   * Contact Form 7 spam message option.
   *
   * Field callback, renders a text input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_spammer_msg_contact_form_7() {
    ?>
    <label for="spammer_msg_contact_form_7">
      <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_contact_form_7]" value="<?php echo esc_attr( $this->settings['spammer_msg_contact_form_7'] ); ?>">
      <p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
    </label>
    <?php
  }

  /**
   * Contact Form 7 support option.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_cf7_support() {
    ?>
    <label for="cf7_support">
      <input type="checkbox" id="cf7_support" name="zerospam_general_settings[cf7_support]" value="1" <?php if( isset( $this->settings['cf7_support'] ) ) : checked( $this->settings['cf7_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>
    <?php
  }

  /**
   * Spam registration message option.
   *
   * Field callback, renders a text input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_spammer_msg_registration() {
    ?>
    <label for="spammer_msg_registration">
      <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_registration]" value="<?php echo esc_attr( $this->settings['spammer_msg_registration'] ); ?>">
    <p class="description"><?php echo __( 'Enter a short message to display when a spam registration has been detected (HTML allowed).', 'zerospam' ); ?></p>
    </label>
    <?php
  }

  /**
   * Registration support option.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_registration_support() {
    ?>
    <label for="registration_support">
      <input type="checkbox" id="registration_support" name="zerospam_general_settings[registration_support]" value="1" <?php if( isset( $this->settings['registration_support'] ) ) : checked( $this->settings['registration_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>
    <?php
  }

  /**
   * Spam comment message option.
   *
   * Field callback, renders a text input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_spammer_msg_comment() {
    ?>
    <label for="spammer_msg_comment">
      <input type="text" class="regular-text" name="zerospam_general_settings[spammer_msg_comment]" value="<?php echo esc_attr( $this->settings['spammer_msg_comment'] ); ?>">
    <p class="description"><?php echo __( 'Enter a short message to display when a spam comment has been detected.', 'zerospam' ); ?></p>
    </label>
    <?php
  }

  /**
   * Comment support option.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_comment_support() {
    ?>
    <label for="comment_support">
      <input type="checkbox" id="comment_support" name="zerospam_general_settings[comment_support]" value="1" <?php if( isset( $this->settings['comment_support'] ) ) : checked( $this->settings['comment_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>
    <?php
  }

  /**
   * Blocked IP message option.
   *
   * Field callback, renders a text input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_blocked_ip_msg() {
    ?>
    <label for="blocked_ip_msg">
      <input type="text" class="regular-text" name="zerospam_general_settings[blocked_ip_msg]" value="<?php echo esc_attr( $this->settings['blocked_ip_msg'] ); ?>">
    <p class="description"><?php echo __( 'Enter a short message to display when a blocked IP visits the site.', 'zerospam' ); ?></p>
    </label>
    <?php
  }

  /**
   * Auto block option.
   *
   * Field callback, renders checkbox input, note the name and value.
   *
   * @since 2.0.0
   *
   * @return string HTML output for auto block tag.
   */
  public function field_auto_block() {
    ?>
    <label for="auto_block">
      <input type="checkbox" id="auto_block" name="zerospam_general_settings[auto_block]" value="1" <?php if ( isset( $this->settings['auto_block']) ): checked( $this->settings['auto_block'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
     </label>

    <p class="description"><?php echo __( 'With auto IP block enabled, users who are identified as spam will automatically be blocked from the site.', 'zerospam' ); ?></p>
    <?php
  }

  /**
   * IP location API field.
   *
   * Field callback, renders a checkbox input, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_ip_location_support() {
    ?>
    <label for="ip_location_support">
      <input type="checkbox" id="gf_support" name="zerospam_general_settings[ip_location_support]" value="1" <?php if( isset( $this->settings['ip_location_support'] ) ) : checked( $this->settings['ip_location_support'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
      <p class="description">
        <?php echo __( 'IP location data provided by', 'zerospam' ); ?> <a href="http://freegeoip.net/" target="_blank">freegeoip.net</a>. <?php echo __( 'API usage is limited to 10,000 queries per hour.', 'zerospam' ); ?><br>
        <?php echo __( 'Disable this option if you experience slow load times on the', 'zerospam' ); ?> <a href="<?php echo zerospam_admin_url() . '?page=zerospam&tab=zerospam_spammer_logs'; ?>"><?php echo __( 'Spammer Log', 'zerospam' ); ?></a> <?php echo __( 'page', 'zerospam' ); ?>.
      </p>
    </label>
    <?php
  }

  /**
   * WP generator meta tag option.
   *
   * Field callback, renders radio inputs, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_wp_generator() {
    ?>
    <label for="wp_generator_remove">
      <input type="checkbox" id="wp_generator_remove" name="zerospam_general_settings[wp_generator]" value="1" <?php if ( isset( $this->settings['wp_generator']) ): checked( $this->settings['wp_generator'] ); endif; ?> /> <?php echo __( 'Hide', 'zerospam' ); ?>
     </label>

    <p class="description"><?php echo __( 'It can be considered a security risk to make your WordPress version visible and public you should hide it.', 'zerospam' ); ?></p>
    <?php
  }

  /**
   * Log spammers option.
   *
   * Field callback, renders radio inputs, note the name and value.
   *
   * @since 2.0.0
   */
  public function field_log_spammers() {
    ?>
    <label for="log_spammers">
      <input type="checkbox" id="log_spammers" name="zerospam_general_settings[log_spammers]" value="1" <?php if( isset( $this->settings['log_spammers'] ) ) : checked( $this->settings['log_spammers'] ); endif; ?> /> <?php echo __( 'Enabled', 'zerospam' ); ?>
    </label>

    <p class="description"><?php echo __( 'If you are using CloudFlare, disable this option. Follow <a href="https://wphuman.com/blocking-spam-zero-spam/" target="_blank">this guide</a> to let CloudFlare blacklist spammers.', 'zerospam' ); ?>
    <?php
  }

  /**
   * Update network settings.
   *
   * Used when plugin is network activated to save settings.
   *
   * @since 2.0.0
   *
   * @link http://wordpress.stackexchange.com/questions/64968/settings-api-in-multisite-missing-update-message
   * @link http://benohead.com/wordpress-network-wide-plugin-settings/
   */
  public function update_network_setting() {
    update_site_option( 'zerospam_general_settings', $_POST['zerospam_general_settings'] );
    wp_redirect( add_query_arg(
      array(
        'page'    => 'zerospam',
        'updated' => 'true',
        ),
      network_admin_url( 'settings.php' )
    ) );
    exit;
  }

  /**
   * Uses admin_menu.
   *
   * Used to add extra submenus and menu options to the admin panel's menu
   * structure.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu
   *
   * @return void
   */
  public function admin_menu() {

    if ( is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ) {
      $hook_suffix = add_submenu_page(
        'settings.php',
        __( 'Zero Spam Settings', 'zerospam' ),
        __( 'Zero Spam', 'zerospam' ),
        'manage_network',
        'zerospam',
        array( $this, 'settings_page' )
      );
    } else {
      // Register plugin settings page.
      $hook_suffix = add_options_page(
        __( 'Zero Spam Settings', 'zerospam' ),
        __( 'Zero Spam', 'zerospam' ),
        'manage_options',
        'zerospam',
        array( $this, 'settings_page' )
      );
    }

    // Load WordPress Zero Spam settings from the database.
    add_action( "load-{$hook_suffix}", array( $this, 'load_zerospam_settings' ) );
  }

  /**
   * Admin Scripts
   *
   * Adds CSS and JS files to the admin pages.
   *
   * @since 2.0.0
   *
   * @return void | boolean
   */
  public function load_zerospam_settings() {
    if ( 'options-general.php' !== $GLOBALS['pagenow'] ) {
      return false;
    }

    wp_enqueue_style( 'zerospam-admin', plugins_url( 'css/style.css', ZEROSPAM_PLUGIN ) );
    wp_enqueue_script( 'zerospam-charts', plugins_url( 'js/charts.js', ZEROSPAM_PLUGIN ), array( 'jquery' ) );
  }

  /**
   * Plugin options page.
   *
   * Rendering goes here, checks for active tab and replaces key with the related
   * settings key. Uses the _options_tabs method to render the tabs.
   *
   * @since 2.0.0
   */
  public function settings_page() {
    $plugin = get_plugin_data( ZEROSPAM_PLUGIN );
    $tab    = isset( $_GET['tab'] ) ? $_GET['tab'] : 'zerospam_general_settings';
    $page   = isset( $_GET['p'] ) ? $_GET['p'] : 1;
    $action = is_plugin_active_for_network( plugin_basename( ZEROSPAM_PLUGIN ) ) ? 'edit.php?action=zerospam' : 'options.php';
    ?>
    <div class="wrap">
      <h2><?php echo __( 'WordPress Zero Spam', 'zerospam' ); ?></h2>
      <?php $this->option_tabs(); ?>
      <div class="zerospam__row">
        <div class="zerospam__right">
        <?php require_once ZEROSPAM_ROOT . 'inc/admin-sidebar.tpl.php'; ?>
        </div>
        <div class="zerospam__left">
        <?php
          if (
            'zerospam_spammer_logs' == $tab &&
            '1' == $this->settings['log_spammers']
          ) {
            $ajax_nonce = wp_create_nonce( 'zero-spam' );

            $limit = 10;
            $args = array(
              'limit' => $limit,
              'offset' => ($page - 1) * $limit
            );
            $spam            = zerospam_get_spam( $args );
            $spam            = zerospam_parse_spam_ary( $spam );
            $all_spam        = zerospam_get_spam();
            $all_spam        = zerospam_parse_spam_ary( $all_spam );

            if ( count( $all_spam['raw'] ) ) {
              $starting_date =  end( $all_spam['raw'] )->date;
              $num_days      = zerospam_num_days( $starting_date );
              $per_day       = $num_days ? number_format( ( count( $all_spam['raw'] ) / $num_days ), 2 ) : 0;
            }

            if (
              isset( $this->settings['ip_location_support'] ) &&
              '1' == $this->settings['ip_location_support']
            ) {
              $ip_location_support = true;
            } else {
              $ip_location_support = false;
            }

            require_once ZEROSPAM_ROOT . 'inc/spammer-logs.tpl.php';
          } elseif ( $tab == 'zerospam_ip_block' ) {
            $limit = 10;
            $args = array(
              'limit' => $limit,
              'offset' => ($page - 1) * $limit
            );
            $ips = zerospam_get_blocked_ips( $args );

            require_once ZEROSPAM_ROOT . 'inc/ip-block.tpl.php';
          } else {
            require_once ZEROSPAM_ROOT . 'inc/general-settings.tpl.php';
          } ?>
        </div>

      </div>
    </div>
    <?php
  }

  /**
   * Renders setting tabs.
   *
   * Walks through the object's tabs array and prints them one by one.
   * Provides the heading for the settings_page method.
   *
   * @since 2.0.0
   */
  public function option_tabs() {
    $current_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'zerospam_general_settings';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ( $this->tabs as $key => $name ) {
      $active = $current_tab == $key ? 'nav-tab-active' : '';
      echo '<a class="nav-tab ' . $active . '" href="?page=zerospam&tab=' . $key . '">' . $name . '</a>';
    }
    echo '</h2>';
  }
}