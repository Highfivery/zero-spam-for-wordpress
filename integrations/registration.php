<?php
/**
 * Handles checking registration submissions for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Preprocess registration fields
 */
if ( ! function_exists( 'wpzerospam_preprocess_registration' ) ) {
  function wpzerospam_preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
    $options = get_option( 'wpzerospam' );
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
    $options = get_option( 'wpzerospam' );
    if ( 'enabled' != $options['verify_registrations'] ) { return; }

    // WordPress Zero Spam registration integration
    wp_enqueue_script(
      'wpzerospam-integration-registrations',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        '/assets/js/integrations/wpzerospam-integration-registrations.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'login_enqueue_scripts', 'wpzerospam_registration_form' );
