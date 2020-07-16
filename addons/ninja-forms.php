<?php
/**
 * Handles checking submitted Ninja Forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Validation for Ninja Forms submissions
 */
if ( ! function_exists( 'wpzerospam_ninja_forms_validate' ) ) {
  function wpzerospam_ninja_forms_validate() {
    $options = wpzerospam_options();

    if (
      'enabled' != $options['verify_ninja_forms'] ||
      is_user_logged_in() ||
      wpzerospam_key_check()
    ) {
      return;
    }

    do_action( 'wpzerospam_ninja_forms_spam' );

    wpzerospam_spam_detected( 'ninja_forms' );
  }
}
add_action( 'ninja_forms_process', 'wpzerospam_ninja_forms_validate' );
