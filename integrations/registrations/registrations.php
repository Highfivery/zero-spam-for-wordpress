<?php
/**
 * Handles checking registration submissions for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Add the 'registration' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'registration' => 'Registration' ] );
  return $types;
});

/**
 * Preprocess registration fields
 */
if ( ! function_exists( 'wpzerospam_preprocess_registration' ) ) {
  function wpzerospam_preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
    $options = wpzerospam_options();
    if ( 'enabled' != $options['verify_registrations'] ) { return $errors; }

    if ( ! wpzerospam_key_check() ) {
      // Spam registration detected
      do_action( 'wpzerospam_registration_spam', $errors, $sanitized_user_login, $user_email );

      $data = [
        'errors'               => $errors,
        'sanitized_user_login' => $sanitized_user_login,
        'user_email'           => $user_email
      ];

      wpzerospam_spam_detected( 'registration', $data );
    }

    return $errors;
  }
}
add_filter( 'registration_errors', 'wpzerospam_preprocess_registration', 10, 3 );

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
