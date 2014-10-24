<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @since 		1.5.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	exit;
}

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Important: Check if the file is the one
// that was registered during the uninstall hook.
if ( basename(__DIR__) . '/zero-spam.php' !== WP_UNINSTALL_PLUGIN )  {
	exit;
}

// Check if the $_REQUEST content actually is the plugin name
if ( ! in_array( basename(__DIR__) . '/zero-spam.php', $_REQUEST['checked'] ) ) {
	exit;
}

if ( 'delete-selected' !== $_REQUEST['action'] ) {
	exit;
}

// Check user roles.
if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

// Run an admin referrer check to make sure it goes through authentication
check_admin_referer( 'bulk-plugins' );

// Safe to carry on
if ( false != get_option( 'zerospam_general_settings' ) || '' == get_option( 'zerospam_general_settings' ) ) {
	delete_site_option( 'zerospam_general_settings' );
	delete_option( 'zerospam_general_settings' );
}

if ( false != get_option( 'zerospam_key' ) || '' == get_option( 'zerospam_key' ) ) {
  delete_option( 'zerospam_key' );
}

if ( false != get_option( 'zerospam_db_version' ) || '' == get_option( 'zerospam_db_version' ) ) {
  delete_option( 'zerospam_db_version' );
}

// Delete database tables
global $wpdb;
$log_table_name = $wpdb->prefix . 'zerospam_log';
$ip_table_name = $wpdb->prefix . 'zerospam_blocked_ips';
$wpdb->query( "DROP TABLE IF EXISTS $log_table_name" );
$wpdb->query( "DROP TABLE IF EXISTS $ip_table_name" );
