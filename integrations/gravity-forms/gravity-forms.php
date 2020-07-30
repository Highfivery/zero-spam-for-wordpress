<?php
/**
 * Handles checking submitted Gravity Forms forms for spam
 *
 * @package WordPressZeroSpam
 * @since 4.1.0
 */

/**
 * Add the 'gform' spam type
 */
add_filter( 'wpzerospam_types', function( $types ) {
  $types = array_merge( $types, [ 'gform' => 'Gravity Forms' ] );
  return $types;
});

/**
 * Validation for Gravity Form submissions
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
  function wpzerospam_gravity_forms( $form, $is_ajax ) {
    wp_enqueue_script(
      'wpzerospam-integration-gravity-forms',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'integrations/gravity-forms/js/gravity-forms.js',
      [ 'wpzerospam' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );
  }
}
add_action( 'gform_enqueue_scripts', 'wpzerospam_gravity_forms', 10, 2 );
