<?php
/**
 * Handles checking submitted Contact Form 7 forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Validation for CF7 submissions
 */
if ( ! function_exists( 'wpzerospam_wpcf7_validate' ) ) {
  function wpzerospam_wpcf7_validate( $result ) {
    $options = wpzerospam_options();

    if (
      'enabled' != $options['verify_cf7'] ||
      is_user_logged_in() ||
      wpzerospam_key_check()
    ) {
      return $result;
    }

    do_action( 'wpzerospam_cf7_spam' );

    wpzerospam_spam_detected( 'cf7', $result );
  }
}
add_action( 'wpcf7_validate', 'wpzerospam_wpcf7_validate' );
