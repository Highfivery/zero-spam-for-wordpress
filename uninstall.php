<?php
/**
 * Removes everything the plugin stored when it is deleted.
 *
 * WordPress includes this file from uninstall_plugin() with
 * WP_UNINSTALL_PLUGIN defined. Every option, network (site) option, transient,
 * table, cron event and user meta key the plugin creates is listed below; keep
 * the lists in sync when adding one. The prefix sweeps are a safety net for
 * keys built at runtime (cache transients) and for anything added later.
 *
 * Runs on every site of a network, then once for the network.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Every module key used in the source (`'module' => '…'`); each module stores its settings in `zero-spam-{module}`.
$zerospam_modules = array(
	'api-monitoring',
	'comments',
	'contactform7',
	'davidwalsh',
	'debug',
	'elementor',
	'fluent_form',
	'formidable',
	'givewp',
	'google',
	'gravityforms',
	'ipbase',
	'ipinfo',
	'ipstack',
	'login',
	'mailchimp4wp',
	'project_honeypot',
	'registration',
	'security',
	'settings',
	'stop_forum_spam',
	'woocommerce',
	'wp-user-avatar',
	'wpforms',
	'zerospam',
);

// Per-site options besides the module options (some only written by older versions).
$zerospam_site_options = array(
	'wpzerospam',
	'wpzerospam_honeypot',
	'zerospam_activation_time',
	'zerospam_api_monitoring_notice_dismissed',
	'zerospam_api_notices',
	'zerospam_blocked_email_domains',
	'zerospam_completed_migrations',
	'zerospam_configured',
	'zerospam_davidwalsh',
	'zerospam_davidwalsh_data',
	'zerospam_davidwalsh_secret',
	'zerospam_db_version',
	'zerospam_settings_audit',
	'zerospam_share_queue',
	'zerospam_show_settings_review_notice',
	'zero-spam-last-update',
	'zero_spam_last_api_report',
);

// Per-site transients with fixed names. Deleted through the API so a persistent object cache is cleared too.
// Runtime-named transients that live only in a persistent object cache are out of reach of the SQL sweeps; they expire on their own.
$zerospam_site_transients = array(
	'zero_spam_circuit_open',
	'zero_spam_failure_count',
	'zerospam_dashboard_data_network',
	'zerospam_dashboard_data_site',
	'zerospam_woo_options_migrated',
);

// Cron hooks; WordPress keeps a cron list per site. `zerospam_api_usage_cleanup` and `zerospam_update_blacklist_terms` were scheduled by older versions.
$zerospam_cron_hooks = array(
	'zerospam_aggregate_api_data',
	'zerospam_aggregate_daily_stats',
	'zerospam_api_usage_cleanup',
	'zerospam_async_share_detection',
	'zerospam_check_api_anomalies',
	'zerospam_davidwalsh_rotate_key',
	'zerospam_network_weekly_summary',
	'zerospam_update_blacklist_terms',
);

// User meta keys; user meta is shared by every site of a network. The `zero_spam_dismiss_notice_*` keys were written by older versions.
$zerospam_user_meta = array(
	'zero_spam_dismiss_notice_enhanced_protection',
	'zero_spam_dismiss_notice_missing_license',
	'zerospam_promo_dismissed',
);

// Option name prefixes swept on every site after the named options are gone.
$zerospam_option_prefixes = array(
	'wpzerospam',
	'zero-spam-',
	'zerospam_',
	'zero_spam_',
	'_transient_zerospam_',
	'_transient_timeout_zerospam_',
	'_transient_zero_spam_',
	'_transient_timeout_zero_spam_',
	// Zero Spam API query cache: Utilities::cache_key( array( 'zero_spam', … ) ) starts with an underscore.
	'_transient__zero_spam_',
	'_transient_timeout__zero_spam_',
	// Site transients live in the options table on a single site.
	'_site_transient_zerospam_',
	'_site_transient_timeout_zerospam_',
);

// Per-site tables ({prefix}), see DB::$tables. `wpzerospam_blacklist` was created by 4.x and is no longer created.
$zerospam_site_tables = array(
	'wpzerospam_blacklist',
	'wpzerospam_blocked',
	'wpzerospam_log',
);

// Network-wide tables ({base_prefix}), see DB::$tables.
$zerospam_network_tables = array(
	'wpzerospam_api_usage',
	'wpzerospam_network_audit',
	'wpzerospam_network_templates',
	'wpzerospam_stats_daily',
	'wpzerospam_stats_monthly',
);

// Network options (sitemeta on a network, the options table on a single site).
$zerospam_network_options = array_merge(
	array(
		'zero_spam_last_api_request',
		'zerospam_network_notifications_enabled',
		'zerospam_network_settings',
		'zerospam_network_settings_notifications_enabled',
	),
	array_map(
		static fn ( string $module ): string => "zero-spam-network-{$module}",
		$zerospam_modules
	)
);

// Network (site) transients with fixed names.
$zerospam_network_transients = array(
	'zerospam_network_settings',
);

// Sitemeta key prefixes swept on a network after the named network options are gone.
$zerospam_sitemeta_prefixes = array(
	'wpzerospam',
	'zero-spam-',
	'zerospam_',
	'zero_spam_',
	'_site_transient_zerospam_',
	'_site_transient_timeout_zerospam_',
);

/**
 * Drops one table if it exists.
 *
 * @param string $table Full table name, prefix included.
 */
$zerospam_drop_table = static function ( string $table ) use ( $wpdb ): void {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall removes the plugin's own tables; there is no API for this.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
};

/**
 * Deletes the rows of a key/value table whose key starts with one of the prefixes.
 *
 * @param string   $table    Table name ($wpdb->options or $wpdb->sitemeta).
 * @param string   $column   Key column (option_name or meta_key).
 * @param string[] $prefixes Key prefixes, matched literally.
 * @return int Number of rows deleted.
 */
$zerospam_delete_by_prefix = static function ( string $table, string $column, array $prefixes ) use ( $wpdb ): int {
	$deleted = 0;

	foreach ( $prefixes as $prefix ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix sweep at uninstall; the named keys were already deleted through the API.
		$deleted += (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE %i LIKE %s',
				$table,
				$column,
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
	}

	return $deleted;
};

/**
 * Removes the plugin's data from the current site.
 */
$zerospam_uninstall_site = static function () use (
	$wpdb,
	$zerospam_modules,
	$zerospam_site_options,
	$zerospam_site_transients,
	$zerospam_cron_hooks,
	$zerospam_option_prefixes,
	$zerospam_site_tables,
	$zerospam_drop_table,
	$zerospam_delete_by_prefix
): void {
	// The license check is cached under a key built from the license itself, so read it before the settings go.
	$zerospam_settings = get_option( 'zero-spam-zerospam' );
	$licenses          = array(
		is_array( $zerospam_settings ) ? (string) ( $zerospam_settings['zerospam_license'] ?? '' ) : '',
		defined( 'ZEROSPAM_LICENSE_KEY' ) ? (string) ZEROSPAM_LICENSE_KEY : '',
	);
	foreach ( array_filter( $licenses ) as $license ) {
		delete_transient( sanitize_title( 'license_' . $license ) );
	}

	foreach ( $zerospam_modules as $module ) {
		delete_option( "zero-spam-{$module}" );
	}

	foreach ( $zerospam_site_options as $option ) {
		delete_option( $option );
	}

	foreach ( $zerospam_site_transients as $transient ) {
		delete_transient( $transient );
	}

	foreach ( $zerospam_cron_hooks as $hook ) {
		wp_unschedule_hook( $hook );
	}

	if ( $zerospam_delete_by_prefix( $wpdb->options, 'option_name', $zerospam_option_prefixes ) > 0 ) {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	foreach ( $zerospam_site_tables as $table ) {
		$zerospam_drop_table( $wpdb->prefix . $table );
	}

	// Utilities::log() writes this site's error log (it can contain visitor IPs) to uploads/, or uploads/sites/{id}/ on a subsite.
	$uploads = wp_upload_dir( null, false );
	wp_delete_file( trailingslashit( $uploads['basedir'] ) . 'zerospam.log' );
};

if ( is_multisite() ) {
	// Every site of every network: the plugin files are gone for all of them.
	$zerospam_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $zerospam_site_ids as $zerospam_site_id ) {
		switch_to_blog( (int) $zerospam_site_id );
		$zerospam_uninstall_site();
		restore_current_blog();
	}
} else {
	$zerospam_uninstall_site();
}

// Network-wide data, removed once (on a single site these are ordinary options and tables).
foreach ( $zerospam_network_options as $zerospam_option ) {
	delete_site_option( $zerospam_option );
}

foreach ( $zerospam_network_transients as $zerospam_transient ) {
	delete_site_transient( $zerospam_transient );
}

if ( is_multisite() ) {
	// Every network's sitemeta, matching the every-site loop above.
	$zerospam_delete_by_prefix( $wpdb->sitemeta, 'meta_key', $zerospam_sitemeta_prefixes );
}

foreach ( $zerospam_network_tables as $zerospam_table ) {
	$zerospam_drop_table( $wpdb->base_prefix . $zerospam_table );
}

foreach ( $zerospam_user_meta as $zerospam_meta_key ) {
	delete_metadata( 'user', 0, $zerospam_meta_key, '', true );
}
