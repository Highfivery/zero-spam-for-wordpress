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

    // Make sure registration spam detection is enabled before loading
    if ( 'enabled' == $options['verify_registrations'] ) {
      // WordPress Zero Spam registration addon
      wp_enqueue_script(
        'wpzerospam-addon-registrations',
        plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
          '/assets/js/addons/wpzerospam-addon-registrations.js',
        [ 'wpzerospam' ],
        $plugin['Version'],
        true
      );
    }
  }
}
add_action( 'login_enqueue_scripts', 'wpzerospam_registration_form' );
