<?php
/**
 * Uninstall
 *
 * Contains all plugin uninstall functionality.
 *
 * @package WordPress Zero Spam
 * @since 1.0.0
 */

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
 * @since 1.5.0
 */

if ( ! current_user_can( 'activate_plugins' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_site_option( 'zerospam_general_settings' );
delete_option( 'zerospam_general_settings' );
delete_option( 'zerospam_key' );
delete_option( 'zerospam_db_version' );

// Delete database tables
global $wpdb;
$log_table_name = $wpdb->prefix . 'zerospam_log';
$ip_table_name = $wpdb->prefix . 'zerospam_blocked_ips';
$ip_data = $wpdb->prefix . 'zerospam_ip_data';
$wpdb->query( "DROP TABLE IF EXISTS $log_table_name" );
$wpdb->query( "DROP TABLE IF EXISTS $ip_table_name" );
$wpdb->query( "DROP TABLE IF EXISTS $ip_data" );
