<?php
/**
 * Handles checking submitted comments for spam
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Preprocess comment fields
 */
if ( ! function_exists( 'wpzerospam_preprocess_comment' ) ) {
  function wpzerospam_preprocess_comment( $commentdata ) {
    $options = wpzerospam_options();

    if ( 'enabled' != $options['verify_comments'] ) { return $commentdata; }

    if (
      is_user_logged_in() && current_user_can( 'moderate_comments' ) ||
      wpzerospam_key_check()
    ) {
      return $commentdata;
    }

    // Spam comment detected
    do_action( 'wpzerospam_comment_spam', $commentdata );

    wpzerospam_log_spam( 'comment', $commentdata );
    wpzerospam_spam_detected( 'comment', $commentdata );
  }
}
add_action( 'preprocess_comment', 'wpzerospam_preprocess_comment' );
