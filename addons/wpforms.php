<?php
/**
 * Handles checking submitted WPForms submissions
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Validation for WPForms submissions
 */
if ( ! function_exists( 'wpzerospam_wpforms_process_honeypot' ) ) {
  function wpzerospam_wpforms_process_honeypot( $honeypot, $fields, $entry, $form_data ) {
    if ( is_user_logged_in() || wpzerospam_key_check() ) {
      return $honeypot;
    }

    do_action( 'wpzerospam_wpform_spam' );

    $data = [
      'honeypot'  => $honeypot,
      'fields'    => $fields,
      'entry'     => $entry,
      'form_data' => $form_data
    ];
    wpzerospam_spam_detected( 'wpform', $data );
  }
}
add_filter( 'wpforms_process_honeypot', 'wpzerospam_wpforms_process_honeypot', 10, 4 );
