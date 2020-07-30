<?php
/**
 * Handles checking submitted Contact Form 7 forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Add the 'cf7' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'cf7' => 'Contact Form 7' ] );
  return $types;
});

/**
 * Validation for CF7 submissions
 */
if ( ! function_exists( 'wpzerospam_wpcf7_validate' ) ) {
  function wpzerospam_wpcf7_validate( $result ) {
    if ( is_user_logged_in() || wpzerospam_key_check() ) {
      return $result;
    }

    do_action( 'wpzerospam_cf7_spam' );

    wpzerospam_spam_detected( 'cf7', $result );
  }
}
add_action( 'wpcf7_validate', 'wpzerospam_wpcf7_validate' );

/**
 * Enqueue the CF7 form JS
 */
if ( ! function_exists( 'wpzerospam_cf7' ) ) {
  function wpzerospam_cf7() {
    wp_enqueue_script(
      'wpzerospam-integration-cf7',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/contact-form-7/js/cf7.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'wpcf7_enqueue_scripts', 'wpzerospam_cf7' );
