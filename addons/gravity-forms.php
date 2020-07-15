<?php
/**
 * Handles checking submitted Gravity Forms forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Validation for CF7 submissions
 */
if ( ! function_exists( 'wpzerospam_wpcf7_validate' ) ) {
  function wpzerospam_wpcf7_validate( $form ) {
    $options = wpzerospam_options();

    if (
      'enabled' != $options['verify_gform'] ||
      is_user_logged_in() ||
      wpzerospam_key_check()
    ) {
      return;
    }

    do_action( 'wpzerospam_gform_spam' );

    wpzerospam_log_spam( 'gform', $form );
    wpzerospam_spam_detected( 'gform', $form );
  }
}
add_action( 'gform_pre_submission', 'wpzerospam_gform_validate' );
