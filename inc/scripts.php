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
function wpzerospam_admin_scripts( $hook_suffix ) {
  $plugin = get_plugin_data( WORDPRESS_ZERO_SPAM );

  $admin_pages = [ 'toplevel_page_wordpress-zero-spam', 'wp-zero-spam_page_wordpress-zero-spam-blocked-ips' ];

  if (
    ! empty( $hook_suffix ) &&
    in_array( $hook_suffix, $admin_pages )
  ) {
    wp_enqueue_style( 'wpzerospam-admin', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/css/admin.css', false, $plugin['Version'] );
    wp_enqueue_script( 'wpzerospam-admin', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/js/admin.js', [ 'jquery' ], $plugin['Version'], true );
    wp_enqueue_style( 'wpzerospam-charts', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/css/Chart.min.css', false, '2.9.3' );
    wp_enqueue_script( 'wpzerospam-charts', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/js/Chart.bundle.min.js', [], '2.9.3' );
  }
}
add_action( 'admin_enqueue_scripts', 'wpzerospam_admin_scripts' );

/**
 * Add site scripts
 */
function wpzerospam_enqueue_scripts() {
  $plugin = get_plugin_data( WORDPRESS_ZERO_SPAM );

  wp_enqueue_script( 'wpzerospam', plugin_dir_url( WORDPRESS_ZERO_SPAM ) . '/assets/js/wpzerospam.js', [ 'jquery' ], $plugin['Version'], true );
  wp_localize_script( 'wpzerospam', 'wpzerospam', [ 'key' => wpzerospam_get_key() ] );
}
add_action( 'wp_enqueue_scripts', 'wpzerospam_enqueue_scripts' );
add_action( 'login_footer', 'wpzerospam_enqueue_scripts' );
