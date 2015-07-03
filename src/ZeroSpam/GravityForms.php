<?php
class ZeroSpam_GravityForms extends ZeroSpam_Plugin {
  public function run() {
    add_action( 'gform_pre_submission', array( $this, 'gform_pre_submission' ) );
  }

  /**
   * Validate Gravity Form submissions.
   *
   * @since 2.0.0
   *
   * @link https://www.gravityhelp.com/documentation/article/gform_pre_submission/
   */
  public function gform_pre_submission( $form ) {
    if ( ! zerospam_is_valid() ) {
      do_action( 'zero_spam_found_spam_gf_form_submission' );
      zerospam_log_spam( 'gf' );
      die( __( $this->settings['spammer_msg_comment'], 'zerospam' ) );
    }
  }
}