<?php
/**
 * Handles checking registration submissions for spam
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
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

      wpzerospam_log_spam( 'registration', $data );
      wpzerospam_spam_detected( 'registration', $data );
    }

    return $errors;
  }
}
add_filter( 'registration_errors', 'wpzerospam_preprocess_registration', 10, 3 );
