<?php
/**
 * CSS & JS
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Add admin scripts
 */
function wpzerospam_admin_scripts() {
  wp_enqueue_style( 'wpzerospam-admin', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/css/admin.css', false, '4.0.0' );
}
add_action( 'admin_enqueue_scripts', 'wpzerospam_admin_scripts' );

/**
 * Add site scripts
 */
function wpzerospam_enqueue_scripts() {
  wp_enqueue_script( 'wpzerospam', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/js/wpzerospam.js', [ 'jquery' ], '4.0.0', true );
  wp_localize_script( 'wpzerospam', 'wpzerospam', [ 'key' => wpzerospam_get_key() ] );
}
add_action( 'wp_enqueue_scripts', 'wpzerospam_enqueue_scripts' );
add_action( 'login_footer', 'wpzerospam_enqueue_scripts' );
