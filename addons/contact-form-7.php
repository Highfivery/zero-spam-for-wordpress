<?php
/**
 * Handles checking submitted Contact Form 7 forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
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

/**
 * Enqueue the CF7 form JS
 */
if ( ! function_exists( 'wpzerospam_cf7' ) ) {
  function wpzerospam_cf7() {
    $options = wpzerospam_options();

    // Make sure registration spam detection is enabled before loading
    if ( 'enabled' == $options['verify_cf7'] ) {
      // WordPress Zero Spam registration addon
      wp_enqueue_script(
        'wpzerospam-addon-cf7',
        plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
          '/assets/js/addons/wpzerospam-addon-cf7.js',
        [ 'wpzerospam' ],
        $plugin['Version'],
        true
      );
    }
  }
}
add_action( 'wpcf7_enqueue_scripts', 'wpzerospam_cf7' );
