<?php
/**
 * Handles checking submitted comments for spam
 *
 * @package WordPressZeroSpam
 * @since 4.3.7
 */

/**
 * Preprocess comment fields
 */
if ( ! function_exists( 'wpzerospam_preprocess_comment' ) ) {
  function wpzerospam_preprocess_comment( $commentdata ) {
    $options = get_option( 'wpzerospam' );
    if ( 'enabled' != $options['verify_comments'] ) { return $commentdata; }

    if (
      is_user_logged_in() && current_user_can( 'moderate_comments' ) ||
      wpzerospam_key_check()
    ) {
      return $commentdata;
    }

    // Spam comment detected
    do_action( 'wpzerospam_comment_spam', $commentdata );

    wpzerospam_spam_detected( 'comment', $commentdata );
  }
}
add_action( 'preprocess_comment', 'wpzerospam_preprocess_comment' );

/**
 * Enqueue the comment form JS
 */
if ( ! function_exists( 'wpzerospam_comment_form' ) ) {
  function wpzerospam_comment_form() {
    $options = get_option( 'wpzerospam' );
    if ( 'enabled' != $options['verify_comments'] ) { return; }

    // WordPress Zero Spam comment addon
    wp_enqueue_script(
      'wpzerospam-addon-comments',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        '/assets/js/addons/wpzerospam-addon-comments.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'comment_form', 'wpzerospam_comment_form' );
