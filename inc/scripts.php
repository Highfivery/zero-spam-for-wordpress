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
    wp_enqueue_style(
      'wpzerospam-admin',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/css/admin.css',
      false,
      WORDPRESS_ZERO_SPAM_VERSION
    );

    // Register Chart.js for graphs
    wp_register_script(
      'wpzerospam-charts',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/js/Chart.bundle.min.js',
      [],
      '2.9.3'
    );

    // Register Chart.css for graphs
    wp_register_style(
      'wpzerospam-charts',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/css/Chart.min.css',
      false,
      '2.9.3'
    );

    // Register jvectormap for world map
    wp_register_script(
      'wpzerospam-map',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/js/jquery-jvectormap-2.0.5.min.js',
      [ 'jquery' ],
      '2.0.5'
    );

    wp_register_script(
      'wpzerospam-world-map',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/js/jquery-jvectormap-world-mill.js',
      [ 'wpzerospam-map' ],
      '2.0.5'
    );

    // Register jvectormap for world map
    wp_register_style(
      'wpzerospam-map',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/css/jquery-jvectormap-2.0.5.css',
      false,
      '2.9.3'
    );

    wp_register_script(
      'wpzerospam-admin-tables',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/js/admin-tables.js',
      [ 'jquery' ],
      WORDPRESS_ZERO_SPAM_VERSION,
      true
    );

    wp_register_style(
      'wpzerospam-admin-tables',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/css/admin-tables.css',
      false,
      WORDPRESS_ZERO_SPAM_VERSION
    );

    // Handle registering & enqueuing scripts based on the current admin page
    switch( $hook_suffix ) {
      case 'wp-zero-spam_page_wordpress-zero-spam-detections':
      case 'wp-zero-spam_page_wordpress-zero-spam-blacklisted':
        wp_enqueue_script( 'wpzerospam-admin-tables' );
        wp_enqueue_style( 'wpzerospam-admin-tables' );
      break;
      case 'toplevel_page_wordpress-zero-spam':
        // Enqueue Chart.js
        wp_enqueue_script( 'wpzerospam-charts' );
        wp_enqueue_style( 'wpzerospam-charts' );

        // Enqueue jvectormap.js
        wp_enqueue_script( 'wpzerospam-world-map' );
        wp_enqueue_style( 'wpzerospam-map' );

        wp_enqueue_style(
          'wpzerospam-admin-dashboard',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            'assets/css/admin-dashboard.css',
          false,
          WORDPRESS_ZERO_SPAM_VERSION
        );
      break;
      case 'wp-zero-spam_page_wordpress-zero-spam-blocked-ips':
        wp_enqueue_script( 'wpzerospam-admin-tables' );
        wp_enqueue_style( 'wpzerospam-admin-tables' );

        // Enqueue the JS for the WordPress Zero Spam blocked IPs page
        wp_enqueue_script(
          'wpzerospam-admin-blocked-ips',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            'assets/js/admin-blocked-ips.js',
          [ 'jquery' ],
          WORDPRESS_ZERO_SPAM_VERSION,
          true
        );

        // Enqueue the CSS for the WordPress Zero Spam blocked IPs page
        wp_enqueue_style(
          'wpzerospam-admin-block_ips',
          plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
            'assets/css/admin-blocked-ips.css',
          false,
          WORDPRESS_ZERO_SPAM_VERSION
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
    // Load the JS that contains the WordPressZeroSpam oject, needed on all
    // pages
    wp_enqueue_script(
      'wpzerospam',
      plugin_dir_url( WORDPRESS_ZERO_SPAM ) .
        'assets/js/wpzerospam.js',
      [ 'jquery' ],
      WORDPRESS_ZERO_SPAM_VERSION,
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
