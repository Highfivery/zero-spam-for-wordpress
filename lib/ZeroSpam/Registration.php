<?php
class ZeroSpam_Registration extends ZeroSpam_Plugin {
  public function run() {
    add_filter( 'registration_errors', array( $this, 'preprocess_registration' ), 10, 3 );
  }

  /**
   * Pre-process registration fields.
   *
   * Used to create custom validation rules on user registration. This fires
   * when the form is submitted but before user information is saved to the
   * database.
   *
   * @since 2.0.0
   *
   * @link http://codex.wordpress.org/Plugin_API/Action_Reference/register_post
   */
  public function preprocess_registration( $errors, $sanitized_user_login, $user_email ) {
    if ( ! isset( $_POST['zerospam_key'] ) || ( $_POST['zerospam_key'] != zerospam_get_key() ) ) {
      do_action( 'zero_spam_found_spam_registration', $errors, $sanitized_user_login, $user_email );

      if ( isset( $this->settings['log_spammers'] ) && ( '1' == $this->settings['log_spammers'] ) ) {
        zerospam_log_spam( 'registration' );
      }

      $errors->add( 'spam_error', __( $this->settings['spammer_msg_registration'], 'zerospam' ) );
    }

    return $errors;
  }
}