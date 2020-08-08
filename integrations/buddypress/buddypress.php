<?php
/**
 * Handles checking submitted BuddyPress registrations
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Add the 'bp_registration' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'bp_registration' => __( 'BuddyPress Registration', 'zero-spam' ) ] );
  return $types;
});

/**
 * Validation for BuddyPress registrations
 */
if ( ! function_exists( 'wpzerospam_bp_signup_validate' ) ) {
  function wpzerospam_bp_signup_validate() {
    if ( is_user_logged_in() || wpzerospam_key_check() ) {
      return;
    }

    do_action( 'wpzerospam_bp_registration_spam' );

    wpzerospam_spam_detected( 'bp_registration' );
  }
}
add_action( 'bp_signup_validate', 'wpzerospam_bp_signup_validate' );

/**
 * Enqueue the BuddyPress form JS
 */
if ( ! function_exists( 'wpzerospam_buddy_press' ) ) {
  function wpzerospam_buddy_press() {
    wp_enqueue_script(
      'wpzerospam-integration-buddy-press',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/buddypress/js/buddypress.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'bp_before_register_page', 'wpzerospam_buddy_press' );
