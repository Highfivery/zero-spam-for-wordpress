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

// User meta is shared across a network, so it only needs deleting once.
delete_metadata( 'user', 0, 'zerospam_promo_dismissed', '', true );

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
			delete_option( 'zerospam_completed_migrations' );
			delete_option( 'zerospam_show_settings_review_notice' );
			delete_option( 'zerospam_activation_time' );
			delete_option( 'zerospam_api_monitoring_notice_dismissed' );
			delete_option( 'zerospam_share_queue' );
			wp_unschedule_hook( 'zerospam_async_share_detection' );

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
	wp_unschedule_hook( 'zerospam_async_share_detection' );
} else {
	delete_option( 'wpzerospam' );
	delete_option( 'wpzerospam_honeypot' );
	delete_option( 'zerospam_db_version' );
	delete_option( 'zerospam_configured' );
	delete_option( 'zerospam_davidwalsh' );
	delete_option( 'zero_spam_last_api_report' );
	delete_option( 'zero-spam-last-update' );
	delete_option( 'zerospam_completed_migrations' );
	delete_option( 'zerospam_show_settings_review_notice' );
	delete_option( 'zerospam_activation_time' );
	delete_option( 'zerospam_api_monitoring_notice_dismissed' );
	delete_option( 'zerospam_share_queue' );

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
	wp_unschedule_hook( 'zerospam_async_share_detection' );
}
