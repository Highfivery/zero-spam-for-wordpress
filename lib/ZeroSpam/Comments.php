<?php
/**
 * ZeroSpam_Comments library
 *
 * Processes comments for spam.
 *
 * @package WordPress Zero Spam
 * @subpackage ZeroSpam_Comments
 * @since 1.0.0
 */

/**
 * Security Note: Blocks direct access to the plugin PHP files.
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Processes comments.
 *
 * This library process user comments and checks for spam.
 *
 * @since 1.0.0
 */
class ZeroSpam_Comments extends ZeroSpam_Plugin
{
  /**
   * Runs the library.
   *
   * Initializes & runs the ZeroSpam_Comments library.
   *
   * @since 1.0.0
   *
   * @see add_action
   */
  public function run()
  {
    add_action( 'preprocess_comment', array( $this, 'preprocess_comment' ) );
  }

  /**
   * Preprocess comment fields.
   *
   * An action hook that is applied to the comment data prior to any other processing of the
   * comment's information when saving a comment data to the database.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/preprocess_comment
   */
  public function preprocess_comment( $commentdata )
  {
    if (
      ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) ||
      ( zerospam_is_valid() )
    ) {
      // Allow moderaters to post comments.
      return $commentdata;
    }

    do_action( 'zero_spam_found_spam_comment', $commentdata );

    if ( ! empty(  $this->settings['log_spammers'] ) &&  $this->settings['log_spammers'] ) {
      zerospam_log_spam( 'comment' );
    }

    die( __( $this->settings['spammer_msg_comment'], 'zerospam' ) );
  }
}
