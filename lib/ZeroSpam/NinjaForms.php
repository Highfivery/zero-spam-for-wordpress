<?php
class ZeroSpam_NinjaForms extends ZeroSpam_Plugin {
  public function run() {
    add_action( 'init', array( $this, 'init' ) );
  }

  public function init() {
    add_action( 'ninja_forms_process', array( $this, 'ninja_forms_process' ) );
  }

  /**
   * Validate Ninja Forms submissions.
   *
   * Validates the Ninja Forms (https://wordpress.org/plugins/ninja-forms/)
   * form submission, and flags the form submission as invalid if the zero-spam
   * post data isn't present.
   *
   * @link http://docs.ninjaforms.com/article/105-ninjaformsprocess
   * @since  2.0.0
   *
   */
  public function ninja_forms_process() {
    if ( ! zerospam_is_valid() ) {
      do_action( 'zero_spam_found_spam_nf_form_submission' );

      if ( ! empty( $this->settings['log_spammers'] ) && $this->settings['log_spammers'] )  {
        zerospam_log_spam( 'nf' );
      }

      die( __( $this->settings['spammer_msg_nf'], 'zerospam' ) );
    }
  }
}