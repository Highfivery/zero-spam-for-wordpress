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
	'log'           => 'wpzerospam_log',
	'blocked'       => 'wpzerospam_blocked',
	'blacklist'     => 'wpzerospam_blacklist',
	'api_usage'     => 'wpzerospam_api_usage',
	'stats_daily'   => 'wpzerospam_stats_daily',
	'stats_monthly' => 'wpzerospam_stats_monthly',
);

$modules = array(
	'comments',
	'contactform7',
	'davidwalsh',
	'fluentforms',
	'formidable',
	'givewp',
	'gravityforms',
	'login',
	'mailchimp4wp',
	'registration',
	'woocommerce',
	'wpforms',
	'debug',
	'google',
	'ipinfo',
	'ipstack',
	'project_honeypot',
	'security',
	'stop_forum_spam',
	'zerospam',
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
			delete_option( 'zero-spam-last-update' );

		foreach ( $modules as $key => $module ) {
				delete_option( "zero-spam-$module" );
			}

			foreach ( $tables as $key => $table ) {
				// @codingStandardsIgnoreLine
				$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . $table );
			}

			// Clean up transients for this site.
			$wpdb->query(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE '_transient_zerospam_%' 
				OR option_name LIKE '_transient_timeout_zerospam_%'"
			);
		}
		restore_current_blog();
	}

	// Clean up network-wide transients and tables.
	$wpdb->query(
		"DELETE FROM {$wpdb->sitemeta} 
		WHERE meta_key LIKE '_site_transient_zerospam_%' 
		OR meta_key LIKE '_site_transient_timeout_zerospam_%'"
	);

	// Drop network-wide tables (api_usage, stats_daily, stats_monthly).
	foreach ( array( 'api_usage', 'stats_daily', 'stats_monthly' ) as $table ) {
		// @codingStandardsIgnoreLine
		$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->base_prefix . 'wpzerospam_' . $table );
	}

	// Clear scheduled cron jobs.
	wp_clear_scheduled_hook( 'zerospam_aggregate_daily_stats' );
	wp_clear_scheduled_hook( 'zerospam_api_usage_cleanup' );
	wp_clear_scheduled_hook( 'zerospam_check_api_anomalies' );
	wp_clear_scheduled_hook( 'zerospam_aggregate_api_data' );
} else {
	delete_option( 'wpzerospam' );
	delete_option( 'wpzerospam_honeypot' );
	delete_option( 'zerospam_db_version' );
	delete_option( 'zerospam_configured' );
	delete_option( 'zerospam_davidwalsh' );
	delete_option( 'zero_spam_last_api_report' );
	delete_option( 'zero-spam-last-update' );

	foreach ( $modules as $module => $settings ) {
		delete_option( "zero-spam-$module" );
	}

	foreach ( $tables as $key => $table ) {
		// @codingStandardsIgnoreLine
		$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->prefix . $table );
	}

	// Clean up transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE '_transient_zerospam_%' 
		OR option_name LIKE '_transient_timeout_zerospam_%'"
	);

	// Clear scheduled cron jobs.
	wp_clear_scheduled_hook( 'zerospam_aggregate_daily_stats' );
	wp_clear_scheduled_hook( 'zerospam_api_usage_cleanup' );
	wp_clear_scheduled_hook( 'zerospam_check_api_anomalies' );
	wp_clear_scheduled_hook( 'zerospam_aggregate_api_data' );
}
