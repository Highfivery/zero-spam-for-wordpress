<?php
/**
 * Handles checking submitted Fluent Forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.4.0
 */

/**
 * Add the 'fluentform' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'fluentform' => __( 'Fluent Forms', 'zero-spam' ) ] );
  return $types;
});

/**
 * Validation for Fluent Form submissions
 */
if ( ! function_exists( 'wpzerospam_fluentform_validate' ) ) {
  function wpzerospam_fluentform_validate( $insertData, $data, $form ) {
    if ( is_user_logged_in() || wpzerospam_key_check( $data ) ) {
      return;
    }

    do_action( 'wpzerospam_fluentform_spam' );

    $data = [
      'insertData' => $insertData,
      'data'       => $data,
      'form'       => $form
    ];

    wpzerospam_spam_detected( 'fluentform', $result, false );

    $options = wpzerospam_options();
    echo $options['spam_message'];
    die();
  }
}
add_action( 'fluentform_before_insert_submission', 'wpzerospam_fluentform_validate', 10, 3 );

/**
 * Enqueue the Fluent Form JS
 */
if ( ! function_exists( 'wpzerospam_fluentform' ) ) {
  function wpzerospam_fluentform() {
    wp_enqueue_script(
      'wpzerospam-integration-fluentform',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/fluentform/js/fluentform.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'fluentform_before_form_render', 'wpzerospam_fluentform' );
