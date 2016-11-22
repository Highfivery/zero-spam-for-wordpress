<?php
class ZeroSpam_ContactForm7 extends ZeroSpam_Plugin {
  public function run() {
    add_action( 'wpcf7_validate', array( $this, 'wpcf7_validate' ) );
  }

  /**
   * Validate Contact Form 7 form submissions.
   *
   * Validates the Contact Form 7 (https://wordpress.org/plugins/contact-form-7/)
   * form submission, and flags the form submission as invalid if the zero-spam
   * post data isn't present.
   *
   * @since  2.0.0
   *
   */
  public function wpcf7_validate( $result ) {
    if  ( ! zerospam_is_valid() ) {
      do_action( 'zero_spam_found_spam_cf7_form_submission' );

      // Temp. fix for the following issue: http://contactform7.com/2015/01/06/contact-form-7-41-beta/
      echo __( $this->settings['spammer_msg_contact_form_7'], 'zerospam' );

      zerospam_log_spam( 'cf7' );
      die();
    }

    return $result;
  }
}