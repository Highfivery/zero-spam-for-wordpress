<?php
/**
 * Handles checking submitted BuddyPress registrations
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Validation for BuddyPress registrations
 */
if ( ! function_exists( 'wpzerospam_bp_signup_validate' ) ) {
  function wpzerospam_bp_signup_validate() {
    $options = wpzerospam_options();

    if (
      'enabled' != $options['verify_bp_registrations'] ||
      is_user_logged_in() ||
      wpzerospam_key_check()
    ) {
      return;
    }

    do_action( 'wpzerospam_bp_registration_spam' );

    wpzerospam_spam_detected( 'bp_registration' );
  }
}
add_action( 'bp_signup_validate', 'wpzerospam_bp_signup_validate' );
