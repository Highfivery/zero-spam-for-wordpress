<?php
/**
 * Admin functionality
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

function wpzerospam_admin_menu() {
  add_submenu_page( 'options-general.php', __( 'WordPress Zero Spam Settings', 'wpzerospam' ), __( 'WP Zero Spam', 'wpzerospam' ), 'manage_options', 'wordpress-zero-spam', 'wpzerospam_options_page' );
}
add_action( 'admin_menu', 'wpzerospam_admin_menu' );

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

function wpzerospam_admin_init() {
  register_setting( 'wpzerospam', 'wpzerospam' );

  add_settings_section( 'wpzerospam_general_settings', __( 'General Settings', 'wpzerospam' ), 'wpzerospam_general_settings_cb', 'wpzerospam' );
  add_settings_section( 'wpzerospam_spam_checks', __( 'Spam Checks', 'wpzerospam' ), 'wpzerospam_spam_checks_cb', 'wpzerospam' );
  add_settings_section( 'wpzerospam_ip_blocks', __( 'Blocked IP Address', 'wpzerospam' ), 'wpzerospam_ip_blocks_cb', 'wpzerospam' );

  // Redirect URL for blocked users
  add_settings_field( 'blocked_redirect_url', __( 'Redirect for Blocked Users', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for'   => 'blocked_redirect_url',
    'type'        => 'url',
    'class'       => 'regular-text',
    'desc'        => 'URL blocked users will be taken to.',
    'placeholder' => 'e.g. https://google.com'
  ]);

  // Redirect URL for spam detections
  add_settings_field( 'spam_redirect_url', __( 'Redirect for Spam', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_general_settings', [
    'label_for'   => 'spam_redirect_url',
    'type'        => 'url',
    'class'       => 'regular-text',
    'desc'        => 'URL users will be taken to when a spam submission is detected.',
    'placeholder' => 'e.g. https://google.com'
  ]);

  // Store Cookies
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

  // Toggle logging of blocked IPs
  add_settings_field( 'log_blocked_ips', __( 'Log Blocked IPs', 'wpzerospam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_ip_blocks', [
    'label_for' => 'log_blocked_ips',
    'type'      => 'checkbox',
    'multi'     => false,
    'desc'      => 'Enables logging of when IPs are blocked from accessing the site.',
    'options'   => [
      'enabled' => __( 'Enabled', 'wpzerospam' )
    ]
  ]);

  // Blocked IP address
  add_settings_field( 'blocked_ips', __( 'Blocked IP Address', 'wpzerospam' ), 'wpzerospam_blocked_ip_cb', 'wpzerospam', 'wpzerospam_ip_blocks' );
}
add_action( 'admin_init', 'wpzerospam_admin_init' );

function wpzerospam_ip_blocks_cb() {
}

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
  }
  ?>
  <?php
}

function wpzerospam_blocked_ip_cb( $args ) {
  $options = wpzerospam_options();
  $key     = 0;
  ?>
  <div class="wpzerospam-setting-header">
    <div>
      <label><?php _e( 'IP Address', 'wpzerospam' ); ?></label>
      <small><?php _e( 'The IP address to block.', 'wpzerospam' ); ?></small>
    </div>
    <div>
      <label><?php _e( 'Reason', 'wpzerospam' ); ?></label>
      <small><?php _e( 'Reason the IP address is being blocked.', 'wpzerospam' ); ?></small>
    </div>
  </div>
  <?php
  $cnt = 0;
  if ( $options['blocked_ips'] ):
    foreach( $options['blocked_ips'] as $key => $ip ):
      if ( empty( $ip['ip_address'] )) { continue; }
      ?>
      <div class="wpzerospam-blocked-ip-option">
        <input
          type="text"
          name="wpzerospam[blocked_ips][<?php echo $cnt; ?>][ip_address]"
          value="<?php echo trim( $ip['ip_address'] ); ?>"
          placeholder="<?php _e( 'Blocked IP Address (i.e. XXX.XXX.X.X)', 'wpzerospam' ); ?>"
          class="wpzerospam-input"
        />

        <input
          type="text"
          name="wpzerospam[blocked_ips][<?php echo $cnt; ?>][reason]"
          value="<?php echo trim( $ip['reason'] ); ?>"
          placeholder="<?php _e( 'Reason (i.e. spam)', 'wpzerospam' ); ?>"
          class="wpzerospam-input"
        />
      </div>
      <?php
      $cnt++;
    endforeach;
  endif;
  ?>
  <div class="wpzerospam-blocked-ip-option">
    <input
      type="text"
      name="wpzerospam[blocked_ips][<?php echo $cnt; ?>][ip_address]"
      value=""
      placeholder="<?php _e( 'Blocked IP Address (i.e. XXX.XXX.X.X)', 'wpzerospam' ); ?>"
      class="wpzerospam-input"
    />

    <input
      type="text"
      name="wpzerospam[blocked_ips][<?php echo $cnt; ?>][reason]"
      value=""
      placeholder="<?php _e( 'Reason (i.e. spam)', 'wpzerospam' ); ?>"
      class="wpzerospam-input"
    />
  </div>
  <?php
}
