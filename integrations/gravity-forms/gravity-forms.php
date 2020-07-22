<?php
/**
 * Handles checking submitted Gravity Forms forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Validation for CF7 submissions
 */
if ( ! function_exists( 'wpzerospam_gform_validate' ) ) {
  function wpzerospam_gform_validate( $form ) {
    if ( is_user_logged_in() || wpzerospam_key_check() ) {
      return;
    }

    do_action( 'wpzerospam_gform_spam' );

    wpzerospam_spam_detected( 'gform', $form );
  }
}
add_action( 'gform_pre_submission', 'wpzerospam_gform_validate' );

/**
 * Enqueue the Gravity Forms JS
 */
if ( ! function_exists( 'wpzerospam_gravity_forms' ) ) {
  function wpzerospam_gravity_forms( $form ) {
    wp_enqueue_script(
      'wpzerospam-integration-gravity-forms',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        '/integrations/gravity-forms/js/gravity-forms.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'gform_enqueue_scripts', 'wpzerospam_gravity_forms' );
