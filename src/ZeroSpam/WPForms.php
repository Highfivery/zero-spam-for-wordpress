<?php
class ZeroSpam_WPForms extends ZeroSpam_Plugin {
  public function run() {
    add_filter( 'wpforms_process_honeypot', array( $this, 'wpforms_process_honeypot' ), 10, 4 );
  }

  /**
   * Validate WPForms submissions.
   *
   * @since 2.0.3
   * @param boolean|string $honeypot String triggers honeypot and prevents submission
   * @param array $fields Form entry fields, raw and not formatted
   * @param array $entry Form entry $_POST
   * @param array $form_data Form data
   */
  public function wpforms_process_honeypot( $honeypot, $fields, $entry, $form_data ) {
    if ( ! zerospam_is_valid() ) {
      do_action( 'zero_spam_found_spam_wpf_form_submission' );
      zerospam_log_spam( 'wpf' );
      return __( 'WordPress Zero Spam honeypot triggered.', 'zerospam' );
    }
    return $honeypot;
  }
}