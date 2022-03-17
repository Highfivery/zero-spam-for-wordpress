<?php
/**
 * Handles uninstalling the plugin
 *
 * @package ZeroSpam
 */

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

global $wpdb;

$tables = array(
	'log'       => 'wpzerospam_log',
	'blocked'   => 'wpzerospam_blocked',
	'blacklist' => 'wpzerospam_blacklist',
);

if ( is_multisite() ) {
	// @codingStandardsIgnoreLine
	$blogs = $wpdb->get_results( "SELECT blog_id FROM {$wpdb->blogs}", ARRAY_A );

	if ( $blogs ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog['blog_id'] );

			delete_option( 'wpzerospam' );
			delete_option( 'wpzerospam_honeypot' );
			delete_option( 'zerospam_db_version' );
			delete_option( 'zerospam_configured' );
			delete_option( 'zerospam_davidwalsh' );
			delete_option( 'zero_spam_last_api_report' );

			foreach ( $tables as $key => $table ) {
				// @codingStandardsIgnoreLine
				$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . $table );
			}
		}
		restore_current_blog();
	}
} else {
	delete_option( 'wpzerospam' );
	delete_option( 'wpzerospam_honeypot' );
	delete_option( 'zerospam_db_version' );
	delete_option( 'zerospam_configured' );
	delete_option( 'zerospam_davidwalsh' );
	delete_option( 'zero_spam_last_api_report' );

	foreach ( $tables as $key => $table ) {
		// @codingStandardsIgnoreLine
		$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . $table );
	}
}
