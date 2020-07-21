<?php
/**
 * Contains all JS & CSS scripts for the WordPress Zero Spam plugin.
 *
 * @package WordPressZeroSpam
 * @since 4.0.0
 */

/**
 * Register & enqueue the WordPress Zero Spam JS & CSS for the admin dashboard
 */
if ( ! function_exists( 'wpzerospam_admin_scripts' ) ) {
  function wpzerospam_admin_scripts( $hook_suffix ) {
    // Retrieve the current plugin data (used to get the scripts version)
    if(  ! function_exists('get_plugin_data') ) {
      require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin = get_plugin_data( WORDPRESS_ZERO_SPAM );

    // Handle registering & enqueuing scripts based on the current admin page
    switch( $hook_suffix ) {
      case 'toplevel_page_wordpress-zero-spam':
        // Enqueue Chart.js for graphs
        wp_register_script(
          'wpzerospam-charts',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            '/assets/js/Chart.bundle.min.js',
          [],
          '2.9.3'
        );

        // Enqueue Chart.css for graphs
        wp_enqueue_style(
          'wpzerospam-charts',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            '/assets/css/Chart.min.css',
          false,
          '2.9.3'
        );

        // Enqueue the JS for the WordPress Zero Spam dashboard
        wp_enqueue_script(
          'wpzerospam-admin-dashboard',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            '/assets/js/admin-dashboard.js',
          [ 'jquery', 'wpzerospam-charts' ],
          $plugin['Version'],
          true
        );

        // Enqueue the CSS for the WordPress Zero Spam dashboard
        wp_enqueue_style(
          'wpzerospam-admin-dashboard',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            '/assets/css/admin-dashboard.css',
          false,
          $plugin['Version']
        );
      break;
      case 'wp-zero-spam_page_wordpress-zero-spam-blocked-ips':
        // Enqueue the JS for the WordPress Zero Spam blocked IPs page
        wp_enqueue_script(
          'wpzerospam-admin-blocked-ips',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            '/assets/js/admin-blocked-ips.js',
          [ 'jquery' ],
          $plugin['Version'],
          true
        );

        // Enqueue the CSS for the WordPress Zero Spam blocked IPs page
        wp_enqueue_style(
          'wpzerospam-admin-dashboard',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            '/assets/css/admin-blocked-ips.css',
          false,
          $plugin['Version']
        );
      break;
    }
  }
}
add_action( 'admin_enqueue_scripts', 'wpzerospam_admin_scripts' );

/**
 * Register & enqueue the WordPress Zero Spam JS & CSS for the frontend
 */
if ( ! function_exists( 'wpzerospam_enqueue_scripts' ) ) {
  function wpzerospam_enqueue_scripts() {
    // Retrieve the current plugin data (used to get the scripts version)
    if(  ! function_exists('get_plugin_data') ) {
      require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin = get_plugin_data( WORDPRESS_ZERO_SPAM );

    // Load the JS that contains the WordPressZeroSpam oject, needed on all
    // pages
    wp_enqueue_script(
      'wpzerospam',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        '/assets/js/wpzerospam.js',
      [ 'jquery' ],
      $plugin['Version'],
      true
    );

    // Pass the latest generate key to the frontend script
    wp_localize_script(
      'wpzerospam',
      'wpzerospam',
      [ 'key' => wpzerospam_get_key() ]
    );
  }
}
add_action( 'wp_enqueue_scripts', 'wpzerospam_enqueue_scripts' );
add_action( 'login_footer', 'wpzerospam_enqueue_scripts' );
