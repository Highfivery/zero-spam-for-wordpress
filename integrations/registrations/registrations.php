<?php
/**
 * Handles checking registration submissions for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Adds admin settings for registration protection.
 *
 * @since 4.9.12
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_registrations_admin_fields' ) ) {
  function wpzerospam_registrations_admin_fields() {
    if ( get_option( 'users_can_register' ) ) {
      // Registration spam check
      add_settings_field( 'verify_registrations', __( 'Detect Spam/Malicious Registrations', 'zero-spam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
        'label_for' => 'verify_registrations',
        'type'      => 'checkbox',
        'multi'     => false,
        'desc'      => __( 'Monitors registrations for malicious or automated spambot submissions.', 'zero-spam' ),
        'options'   => [
          'enabled' => __( 'Enabled', 'zero-spam' )
        ]
      ]);
    }
  }
}
add_action( 'wpzerospam_admin_options', 'wpzerospam_registrations_admin_fields' );

/**
 * Add validation to the registration form protection admin fields.
 *
 * @since 4.9.12
 *
 * @param array $fields Array on available admin fields.
 */
if ( ! function_exists( 'wpzerospam_registrations_admin_validation' ) ) {
  function wpzerospam_registrations_admin_validation( $fields ) {
    if ( empty( $fields['verify_registrations'] ) ) { $fields['verify_registrations'] = 'disabled'; }

    return $fields;
  }
}
add_filter( 'wpzerospam_admin_validation', 'wpzerospam_registrations_admin_validation' );

/**
 * Sets the default admin option fields for registrations.
 *
 * @since 4.9.12
 *
 * @param array $defaults Array of WPZS admin option fields.
 * @return array The modified array of the WPZS admin option fields.
 */
if ( ! function_exists( 'wpzerospam_registrations_admin_fields_default' ) ) {
  function wpzerospam_registrations_admin_fields_default( $defaults ) {
    if ( empty( $defaults['verify_registrations'] ) ) { $defaults['verify_registrations'] = 'enabled'; }

    return $defaults;
  }
}
add_filter( 'wpzerospam_admin_option_defaults', 'wpzerospam_registrations_admin_fields_default' );

if ( ! function_exists( 'wpzerospam_registrations_admin_submission_data_item' ) ) {
  function wpzerospam_registrations_admin_submission_data_item( $key, $value ) {
    switch( $key ) {
      case 'sanitized_user_login':
        echo wpzerospam_admin_details_item( __( 'Sanitized User Login', 'zero-spam' ), $value );
      break;
      case 'user_email':
        echo wpzerospam_admin_details_item( __( 'User Email', 'zero-spam' ), $value );
      break;
      case 'errors':
        echo wpzerospam_admin_details_item( __( 'Errors', 'zero-spam' ), json_encode( $value ) );
      break;
    }
  }
}
add_action( 'wpzerospam_admin_submission_data_items', 'wpzerospam_registrations_admin_submission_data_item', 10, 2 );

if ( ! function_exists( 'wpzerospamregistrations_defined_submission_data' ) ) {
  function wpzerospamregistrations_defined_submission_data( $submission_data_keys ) {
    $submission_data_keys[] = 'sanitized_user_login';
    $submission_data_keys[] = 'user_email';
    $submission_data_keys[] = 'errors';

    return $submission_data_keys;
  }
}
add_filter( 'wpzerospam_defined_submission_data', 'wpzerospamregistrations_defined_submission_data', 10, 1 );

/*
 * Runs the registration form spam detections.
 *
 * Runs all action & filter hooks needed for monitoring registrations for
 * spam (when enabled via the 'Detect Registration Spam' option).
 *
 * @since 4.9.12
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_registrations_after_setup_theme' ) ) {
  function wpzerospam_registrations_after_setup_theme() {
    // Add the 'registration' spam type.
    add_filter( 'wpzerospam_types', 'wpzerospam_registrations_types' );

    // Check if site registrations are enabled
    if ( ! get_option( 'users_can_register' ) ) { return false; }

    $options = wpzerospam_options();

    // Check if detecting registration spam is enabled & user is unauthenticated.
    if ( 'enabled' != $options['verify_registrations'] || is_user_logged_in() ) { return false; }

    // Add the 'honeypot' field to the registration form.
    add_action( 'register_form', 'wpzerospam_registrations_form' );

    // Preprocess registration submissions.
    add_action( 'register_post', 'wpzerospam_registrations_preprocess', 10, 3 );
  }
}
add_action( 'after_setup_theme', 'wpzerospam_registrations_after_setup_theme' );

/**
 * Adds the 'registration' spam type.
 *
 * @param array An array of the current spam types.
 * @return array The resulting current spam types.
 */
if ( ! function_exists( 'wpzerospam_registrations_types' ) ) {
  function wpzerospam_registrations_types( $types ) {
    $types = array_merge( $types, [ 'registration' => __( 'Registration', 'zero-spam' ) ] );

    return $types;
  }
}

/**
 * Add a 'honeypot' field to the registration form.
 *
 * @since 4.9.12
 *
 * @link https://codex.wordpress.org/Plugin_API/Action_Reference/register_form
 *
 * @return string HTML to append to the registration form.
 */
if ( ! function_exists( 'wpzerospam_registrations_form' ) ) {
  function wpzerospam_registrations_form( $defaults ) {
    echo wpzerospam_honeypot_field();
  }
}

/**
 * Preprocess registration form submissions.
 */
if ( ! function_exists( 'wpzerospam_registrations_preprocess' ) ) {
  function wpzerospam_registrations_preprocess( $sanitized_user_login, $user_email, $errors ) {
    $options  = wpzerospam_options();
    $honeypot = wpzerospam_get_honeypot();

    if (
      // First, check the 'honeypot' field.
      ( ! isset( $_REQUEST[ $honeypot ] ) || $_REQUEST[ $honeypot ] ) ||
      // Next, check the 'wpzerospam_key' field.
      ( empty( $_REQUEST['wpzerospam_key'] ) || wpzerospam_get_key() != $_REQUEST['wpzerospam_key'] )
    ) {
      // Spam registration selected.
      do_action( 'wpzerospam_registration_spam', $errors, $sanitized_user_login, $user_email );
      wpzerospam_spam_detected( 'registration', [
        'sanitized_user_login' => $sanitized_user_login,
        'user_email'           => $user_email,
        'errors'               => $errors
      ]);
    }
  }
}

/**
 * Enqueue the registration form JS
 */
if ( ! function_exists( 'wpzerospam_registration_form' ) ) {
  function wpzerospam_registration_form() {
    $options = wpzerospam_options();
    if ( 'enabled' != $options['verify_registrations'] ) { return; }

    // WordPress Zero Spam registration integration
    wp_enqueue_script(
      'wpzerospam-integration-registrations',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/registrations/js/registrations.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'login_enqueue_scripts', 'wpzerospam_registration_form' );
