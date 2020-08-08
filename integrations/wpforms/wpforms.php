<?php
/**
 * Handles checking submitted WPForms submissions
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Add the 'wpform' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'wpform' => __( 'WPForms', 'zero-spam' ) ] );
  return $types;
});

/**
 * Validation for WPForms submissions
 *
 * @link https://wpforms.com/developers/wpforms_process_before/
 */
if ( ! function_exists( 'wpzerospam_wpforms_process_before' ) ) {
  function wpzerospam_wpforms_process_before( $entry, $form_data ) {
    if ( is_user_logged_in() || wpzerospam_key_check() ) {
      return;
    }

    do_action( 'wpzerospam_wpform_spam' );

    $data = [
      'entry'     => $entry,
      'form_data' => $form_data
    ];
    wpzerospam_spam_detected( 'wpform', $data );
  }
}
add_action( 'wpforms_process_before', 'wpzerospam_wpforms_process_before', 10, 2 );

/**
 * Enqueue the WPForms form JS
 */
if ( ! function_exists( 'wpzerospam_wpforms' ) ) {
  function wpzerospam_wpforms() {
    wp_enqueue_script(
      'wpzerospam-integration-wpforms',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/wpforms/js/wpforms.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'wpforms_frontend_output_before', 'wpzerospam_wpforms' );
