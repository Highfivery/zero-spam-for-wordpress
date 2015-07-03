<?php
class ZeroSpam_Comments extends ZeroSpam_Plugin {
  public function run() {
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
  public function preprocess_comment( $commentdata ) {
    if (
      ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) ||
      ( zerospam_is_valid() )
    ) {
      return $commentdata;
    }

    do_action( 'zero_spam_found_spam_comment', $commentdata );

    if ( ! empty(  $this->settings['log_spammers'] ) &&  $this->settings['log_spammers'] ) {
      zerospam_log_spam( 'comment' );
    }

    die( __( $this->settings['spammer_msg_comment'], 'zerospam' ) );
  }
}