<?php
/**
 * Handles checking submitted Contact Form 7 forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Adds admin settings for CF7 protection.
 *
 * @since 4.9.12
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_cf7_admin_fields' ) ) {
  function wpzerospam_cf7_admin_fields() {
    if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
      add_settings_field( 'verify_cf7', __( 'Detect Spam/Malicious CF7 Submissions', 'zero-spam' ), 'wpzerospam_field_cb', 'wpzerospam', 'wpzerospam_spam_checks', [
        'label_for' => 'verify_cf7',
        'type'      => 'checkbox',
        'multi'     => false,
        'desc'      => __( 'Monitors CF7 for malicious or automated spambot submissions.', 'zero-spam' ),
        'options'   => [
          'enabled' => __( 'Enabled', 'zero-spam' )
        ]
      ]);
    }
  }
}
add_action( 'wpzerospam_admin_options', 'wpzerospam_cf7_admin_fields' );

/**
 * Add validation to the CF7 form protection admin fields.
 *
 * @since 4.9.12
 *
 * @param array $fields Array on available admin fields.
 */
if ( ! function_exists( 'wpzerospam_cf7_admin_validation' ) ) {
  function wpzerospam_cf7_admin_validation( $fields ) {
    if ( empty( $fields['verify_cf7'] ) ) { $fields['verify_cf7'] = 'disabled'; }

    return $fields;
  }
}
add_filter( 'wpzerospam_admin_validation', 'wpzerospam_cf7_admin_validation' );

if ( ! function_exists( 'wpzerospam_cf7_admin_fields_default' ) ) {
  function wpzerospam_cf7_admin_fields_default( $defaults ) {
    if ( empty( $defaults['verify_cf7'] ) ) { $defaults['verify_cf7'] = 'enabled'; }

    return $defaults;
  }
}
add_filter( 'wpzerospam_admin_option_defaults', 'wpzerospam_cf7_admin_fields_default' );

if ( ! function_exists( 'zero_spam_wpcf7_admin_submission_data_item' ) ) {
  function zero_spam_wpcf7_admin_submission_data_item( $key, $value ) {
    switch( $key ) {
      case '_wpcf7':
        echo wpzerospam_admin_details_item( __( 'CF7 ID', 'zero-spam' ), $value );
      break;
      case '_wpcf7_version':
        echo wpzerospam_admin_details_item( __( 'CF7 Version', 'zero-spam' ), $value );
      break;
      case '_wpcf7_locale':
        echo wpzerospam_admin_details_item( __( 'CF7 Language', 'zero-spam' ), json_encode( $value ) );
      break;
      case '_wpcf7_container_post':
        echo wpzerospam_admin_details_item( __( 'CF7 Referrer Post ID', 'zero-spam' ), json_encode( $value ) );
      break;
      case '_wpcf7_unit_tag':
        echo wpzerospam_admin_details_item( __( 'CF7 Unit Tag', 'zero-spam' ), json_encode( $value ) );
      break;
    }
  }
}
add_action( 'wpzerospam_admin_submission_data_items', 'zero_spam_wpcf7_admin_submission_data_item', 10, 2 );

if ( ! function_exists( 'wpzerospam_wpcf7_defined_submission_data' ) ) {
  function wpzerospam_wpcf7_defined_submission_data( $submission_data_keys ) {
    $submission_data_keys[] = '_wpcf7';
    $submission_data_keys[] = '_wpcf7_version';
    $submission_data_keys[] = '_wpcf7_locale';
    $submission_data_keys[] = '_wpcf7_container_post';
    $submission_data_keys[] = '_wpcf7_unit_tag';

    return $submission_data_keys;
  }
}
add_filter( 'wpzerospam_defined_submission_data', 'wpzerospam_wpcf7_defined_submission_data', 10, 1 );

/*
 * Runs the CF7 form spam detections.
 *
 * Runs all action & filter hooks needed for monitoring CF7 for
 * spam (when enabled via the 'Detect Spam/Malicious CF7 Submissions' option).
 *
 * @since 4.9.12
 *
 * @return void
 */
if ( ! function_exists( 'wpzerospam_cf7_after_setup_theme' ) ) {
  function wpzerospam_cf7_after_setup_theme() {
    // Check if site registrations are enabled
    $options = wpzerospam_options();

    // Add the 'cf7' spam type.
    add_filter( 'wpzerospam_types', 'wpzerospam_wpcf7_type' );

    // Check if detecting registration spam is enabled & user is unauthenticated.
    if ( 'enabled' != $options['verify_cf7'] || is_user_logged_in() ) { return false; }

    // Add the 'honeypot' field to the CF7 form.
    add_filter( 'wpcf7_form_elements', 'zero_spam_wpcf7_form_elements', 10, 1 );

    // Preprocess CF7 form submissions.
    add_filter( 'wpcf7_validate', 'wpzerospam_wpcf7_preprocess_submission', 10, 2 );
  }
}
add_action( 'after_setup_theme', 'wpzerospam_cf7_after_setup_theme' );

/**
 * Adds the 'cf7' spam type.
 *
 * @param array An array of the current spam types.
 * @return array The resulting current spam types.
 */
if ( ! function_exists( 'wpzerospam_wpcf7_type' ) ) {
  function wpzerospam_wpcf7_type( $types ) {
    $types = array_merge( $types, [ 'cf7' => __( 'Contact Form 7', 'zero-spam' ) ] );

    return $types;
  }
}

/**
 * Adds the honeypot field to CF7 forms.
 *
 * @since 4.9.12
 *
 * @param sting HTML for the form.
 * @return string The modified form HTML.
 */
function zero_spam_wpcf7_form_elements( $this_form_do_shortcode ) {
  $this_form_do_shortcode .= wpzerospam_honeypot_field();

  return $this_form_do_shortcode;
};

/**
 * Preprocess CF7 submissions.
 *
 * @since 4.9.12
 *
 * @param object $result CF7 result object.
 * @param object $tags CF7 tags object.
 * @return object A CF7 object.
 */
if ( ! function_exists( 'wpzerospam_wpcf7_preprocess_submission' ) ) {
  function wpzerospam_wpcf7_preprocess_submission( $result, $tags ) {
    $options  = wpzerospam_options();
    $honeypot = wpzerospam_get_honeypot();

    if (
      // First, check the 'honeypot' field.
      ( ! isset( $_REQUEST[ $honeypot ] ) || $_REQUEST[ $honeypot ] ) ||
      // Next, check the 'wpzerospam_key' field.
      ( empty( $_REQUEST['wpzerospam_key'] ) || wpzerospam_get_key() != $_REQUEST['wpzerospam_key'] )
    ) {
      // Spam registration selected.
      do_action( 'wpzerospam_cf7_spam', $_REQUEST );
      wpzerospam_spam_detected( 'cf7', $_REQUEST, false );

      $result->invalidate( $tags[0], $options['spam_message'] );
    }

    return $result;
  }
}

/**
 * Enqueue the CF7 form JS
 */
if ( ! function_exists( 'wpzerospam_cf7' ) ) {
  function wpzerospam_cf7() {
    wp_enqueue_script(
      'wpzerospam-integration-cf7',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/contact-form-7/js/cf7.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'wpcf7_enqueue_scripts', 'wpzerospam_cf7' );
