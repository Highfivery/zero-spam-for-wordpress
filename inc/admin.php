<?php
/**
 * Admin functionality
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

function wpzerospam_admin_menu() {
  $main_page = add_menu_page(
    __( 'WordPress Zero Spam Dashboard', 'wpzerospam' ),
    __( 'WP Zero Spam', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam',
    'wpzerospam_dashboard',
    'dashicons-shield'
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'Blocked IP Addresses', 'wpzerospam' ),
    __( 'Blocked IPs', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam-blocked-ips',
    'wpzerospam_blocked_ips_page',
  );

  add_submenu_page(
    'wordpress-zero-spam',
    __( 'WordPress Zero Spam Settings', 'wpzerospam' ),
    __( 'Settings', 'wpzerospam' ),
    'manage_options',
    'wordpress-zero-spam-settings',
    'wpzerospam_options_page',
  );
}
add_action( 'admin_menu', 'wpzerospam_admin_menu' );

function wpzerospam_blocked_ips_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  ?>
  <div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <?php
    /**
     * Blocked IP table
     */
    require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/classes/class-wpzerospam-blocked-ip-table.php';

    $table_data = new WPZeroSpam_Blocked_IP_Table();

    // Setup page parameters
    $current_page = $table_data->get_pagenum();
    $current_page = ( isset( $current_page ) ) ? $current_page : 1;
    $paged        = ( isset( $_GET['page'] ) ) ? absint( $_GET['page'] ) : $current_page;
    $paged        = ( isset( $_GET['paged'] ) ) ? absint(  $_GET['paged'] ) : $current_page;
    $paged        = ( isset( $args['paged'] ) ) ? $args['paged'] : $paged;

    // Fetch, prepare, sort, and filter our data...
    $table_data->prepare_items();
    ?>
    <form id="log-table" method="post">
      <?php wp_nonce_field( 'wpzerospam_nonce', 'wpzerospam_nonce' ); ?>

      <?php # Current page ?>
      <input type="hidden" name="paged" value="<?php echo $paged; ?>" />

      <?php $table_data->display(); ?>
    </form>
  </div>
  <?php
}

function wpzerospam_dashboard() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  ?>
    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

      <h2><?php _e( 'Spam Detections Log', 'wpzerospam' ); ?></h2>
      <p><?php _e( 'Charts, graphs, etc. coming soon!', 'wpzerospam' ); ?></p>
      <?php
      /**
       * Log table
       */
      require plugin_dir_path( WORDPRESS_ZERO_SPAM ) . '/classes/class-wpzerospam-log-table.php';

      $table_data = new WPZeroSpam_Log_Table();

      // Setup page parameters
      $current_page = $table_data->get_pagenum();
      $current_page = (isset($current_page)) ? $current_page : 1;
      $paged        = ( isset( $_GET['page'] ) ) ? absint( $_GET['page'] ) : $current_page;
      $paged        = ( isset( $_GET['paged'] ) ) ? absint(  $_GET['paged'] ) : $current_page;
      $paged        = ( isset( $args['paged'] ) ) ? $args['paged'] : $paged;

      // Fetch, prepare, sort, and filter our data...
      $table_data->prepare_items();
      ?>
      <form id="log-table" method="post">
        <?php wp_nonce_field( 'wpzerospam_nonce', 'wpzerospam_nonce' ); ?>

        <?php # Current page ?>
        <input type="hidden" name="paged" value="<?php echo $paged; ?>" />

        <?php $table_data->display(); ?>
      </form>
    </div>
  <?php
}

function wpzerospam_options_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
    ?>
    <div class="wrap">
      <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
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
  if ( empty( $input['verify_cf7'] ) ) { $input['verify_cf7'] = 'disabled'; }
  if ( empty( $input['verify_gform'] ) ) { $input['verify_gform'] = 'disabled'; }
  if ( empty( $input['verify_ninja_forms'] ) ) { $input['verify_ninja_forms'] = 'disabled'; }
  if ( empty( $input['verify_bp_registrations'] ) ) { $input['verify_bp_registrations'] = 'disabled'; }
  if ( empty( $input['verify_wpforms'] ) ) { $input['verify_wpforms'] = 'disabled'; }
  if ( empty( $input['log_blocked_ips'] ) ) { $input['log_blocked_ips'] = 'disabled'; }

  return $input;
 }

function wpzerospam_admin_init() {
  $options = wpzerospam_options();

  register_setting( 'wpzerospam', 'wpzerospam', 'wpzerospam_validate_options' );

  add_settings_section( 'wpzerospam_general_settings', __( 'General Settings', 'wpzerospam' ), 'wpzerospam_general_settings_cb', 'wpzerospam' );
  add_settings_section( 'wpzerospam_spam_checks', __( 'Spam Checks', 'wpzerospam' ), 'wpzerospam_spam_checks_cb', 'wpzerospam' );

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
      'placeholder' => __( 'You have been blocked from visiting this site.', 'wpzerospam' )
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

  if ( 'enabled' == $options['log_spam'] ) {
    // Redirect URL for spam detections
    add_settings_field( 'ipstack_api', __( 'ipstack API Key', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
      'label_for'   => 'ipstack_api',
      'type'        => 'text',
      'class'       => 'regular-text',
      'desc'        => 'Enter your <a href="https://ipstack.com/" target="_blank">ipstack API key</a> to enable location-based statistics.',
    ]);
  }

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

  // Ninja Forms spam check
  if ( is_plugin_active( 'ninja-forms/ninja-forms.php' ) ) {
    add_settings_field( 'verify_ninja_forms', __( 'Verify Ninja Forms Submissions', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
      'label_for' => 'verify_ninja_forms',
      'type'      => 'checkbox',
      'multi'     => false,
      'desc'      => 'Enables spam detection for Ninja Forms submissions.',
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
}
add_action( 'admin_init', 'wpzerospam_admin_init' );

function wpzerospam_general_settings_cb() {
}

function wpzerospam_spam_checks_cb() {
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
