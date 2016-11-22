<?php
class ZeroSpam_BuddyPress extends ZeroSpam_Plugin {
  public function run() {
    add_action( 'bp_signup_validate', array( $this, 'bp_signup_validate' ) );
  }

  /**
   * Preprocess comment fields.
   *
   * An action hook that is applied to the comment data prior to any other processing of the
   * comment's information when saving a comment data to the database.
   *
   * @since 2.0.0
   *
   * @link http://etivite.com/api-hooks/buddypress/trigger/do_action/bp_signup_validate/
   */
  public function bp_signup_validate() {
    global $bp;

    if ( ! zerospam_is_valid() ) {
      do_action( 'zero_spam_found_spam_buddypress_registration' );

      if ( isset( $this->settings['log_spammers'] ) && ( '1' == $this->settings['log_spammers'] ) ) {
        zerospam_log_spam( 'buddypress-registration' );
      }

      die( __( $this->settings['buddypress_msg_registration'], 'zerospam' ) );
    }
  }
}