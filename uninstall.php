<?php
/**
 * Removes what the plugin stored when it is deleted.
 *
 * WordPress includes this file from uninstall_plugin() with
 * WP_UNINSTALL_PLUGIN defined. Every option, network (site) option, transient,
 * table, cron event and user meta key the plugin creates is listed below; keep
 * the lists in sync when adding one. The prefix sweeps are a safety net for
 * keys built at runtime (cache transients) and for anything added later.
 *
 * Since 6.0.0 the "Delete Data on Uninstall" setting (API Monitoring, on
 * unless turned off) decides whether the data goes: settings and other
 * options, tables, user preferences and the error log. When it is off they
 * stay for a later reinstall; caches (transients) and scheduled events are
 * removed either way, because nothing runs them once the plugin is gone.
 *
 * Runs on every site of a network, each following its own value of the
 * setting, then once for the network, following the network's value.
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
	'zerospam_admin_safe_ip_notice',
	'zerospam_admin_safe_ips',
	'zerospam_api_monitoring_notice_dismissed',
	'zerospam_api_notices',
	'zerospam_backup_5x',
	'zerospam_blocked_email_domains',
	'zerospam_completed_migrations',
	'zerospam_configured',
	'zerospam_davidwalsh',
	'zerospam_davidwalsh_data',
	'zerospam_davidwalsh_secret',
	'zerospam_db_version',
	'zerospam_migration_cursor_canonical_blocked_ips',
	'zerospam_migration_lock_canonical_blocked_ips',
	'zerospam_migration_lock_network',
	'zerospam_onboarding',
	'zerospam_proxy_detection_since',
	'zerospam_settings_audit',
	'zerospam_share_queue',
	'zerospam_shared_ip_warning_dismissed',
	'zerospam_show_settings_review_notice',
	'zerospam_whats_new',
	'zero-spam-last-update',
	'zero_spam_last_api_report',
);

// Per-site transients with fixed names. Deleted through the API so a persistent object cache is cleared too.
// Runtime-named transients that live only in a persistent object cache are out of reach of the SQL sweeps; they expire on their own.
// Runtime-named ones in the options table (Zero Spam API query cache, `zerospam_undo_{token}` copies of deleted rows) go with the prefix sweep.
$zerospam_site_transients = array(
	'zero_spam_circuit_open',
	'zero_spam_failure_count',
	'zerospam_dashboard_data_network',
	'zerospam_dashboard_data_site',
	'zerospam_license_status',
	'zerospam_protection_detections',
	'zerospam_rescue_site_sends',
	'zerospam_self_test_lock',
	'zerospam_shared_ip_warning',
	'zerospam_stats_summary',
	'zerospam_stats_timeseries',
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
	'zerospam_send_rescue_link',
	'zerospam_update_blacklist_terms',
);

// User meta keys; user meta is shared by every site of a network. The `zero_spam_dismiss_notice_*` keys were written by older versions.
$zerospam_user_meta = array(
	'zero_spam_dismiss_notice_enhanced_protection',
	'zero_spam_dismiss_notice_missing_license',
	'zerospam_promo_dismissed',
	'zerospam_ui',
);

// Option name prefixes swept on every site after the named options are gone: data first, then caches.
$zerospam_option_prefixes = array(
	'wpzerospam',
	'zero-spam-',
	'zerospam_',
	'zero_spam_',
);

// Cache (transient) prefixes, swept even when the data is kept.
$zerospam_transient_prefixes = array(
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
		'zerospam_backup_5x',
		'zerospam_network_completed_migrations',
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
	'zerospam_news',
	'zerospam_news_backoff',
);

// Sitemeta key prefixes swept on a network after the named network options are gone.
$zerospam_sitemeta_prefixes = array(
	'wpzerospam',
	'zero-spam-',
	'zerospam_',
	'zero_spam_',
);

// Network transient prefixes in sitemeta, swept even when the data is kept.
$zerospam_sitemeta_transient_prefixes = array(
	'_site_transient_zerospam_',
	'_site_transient_timeout_zerospam_',
);

/**
 * The network's entry of a setting (`value`, `locked`), or null.
 *
 * Reads the network settings store (`zerospam_network_settings`) in either
 * of its shapes, as Settings_Schema::normalize_network_store() does: the
 * plugin's classes are not loaded during uninstall.
 *
 * @param string $key Setting key.
 * @return array<string, mixed>|null
 */
$zerospam_network_entry = static function ( string $key ): ?array {
	if ( ! is_multisite() ) {
		return null;
	}

	$stored   = get_site_option( 'zerospam_network_settings', array() );
	$settings = is_array( $stored ) && isset( $stored['settings'] ) && is_array( $stored['settings'] ) ? $stored['settings'] : $stored;
	$entry    = is_array( $settings ) && isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : null;

	return null !== $entry && array_key_exists( 'value', $entry ) ? $entry : null;
};

/**
 * Whether a value of "Delete Data on Uninstall" says to delete.
 *
 * Only `enabled`, or any value 6.0's v6_uninstall_flag migration has not
 * looked at yet: 5.x never read the setting and always deleted, so a 5.x
 * `false` (an unticked box) on a site or network the migration never
 * reached (a subsite nobody visited after the update) still means delete.
 *
 * @param mixed $value    Stored value.
 * @param bool  $migrated Whether the migration ran where the value is stored.
 * @return bool
 */
$zerospam_says_delete = static function ( $value, bool $migrated ): bool {
	return 'enabled' === $value || ! $migrated;
};

/**
 * Whether data is deleted, by the "Delete Data on Uninstall" setting:
 * the network's locked value, else the current site's own value (unless
 * `$network_scope`), else the network's value, else on (the default).
 * A value is only "keep" once the v6_uninstall_flag migration ran there.
 *
 * @param bool $network_scope Decide for the network-wide data (network value or the default only).
 * @return bool
 */
$zerospam_deletes_data = static function ( bool $network_scope = false ) use ( $zerospam_network_entry, $zerospam_says_delete ): bool {
	$network          = $zerospam_network_entry( 'delete_data_on_uninstall' );
	$network_done     = get_site_option( 'zerospam_network_completed_migrations', array() );
	$network_migrated = is_array( $network_done ) && in_array( 'v6_uninstall_flag', $network_done, true );

	if ( null !== $network && ! empty( $network['locked'] ) ) {
		return $zerospam_says_delete( $network['value'], $network_migrated );
	}

	if ( ! $network_scope || ! is_multisite() ) {
		$options = get_option( 'zero-spam-api-monitoring' );

		if ( is_array( $options ) && array_key_exists( 'delete_data_on_uninstall', $options ) ) {
			$site_done = get_option( 'zerospam_completed_migrations', array() );

			return $zerospam_says_delete( $options['delete_data_on_uninstall'], is_array( $site_done ) && in_array( 'v6_uninstall_flag', $site_done, true ) );
		}
	}

	return null === $network || $zerospam_says_delete( $network['value'], $network_migrated );
};

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
 * Removes the plugin's caches and scheduled events from the current site,
 * and its data unless "Delete Data on Uninstall" is off there.
 */
$zerospam_uninstall_site = static function () use (
	$wpdb,
	$zerospam_modules,
	$zerospam_site_options,
	$zerospam_site_transients,
	$zerospam_cron_hooks,
	$zerospam_option_prefixes,
	$zerospam_transient_prefixes,
	$zerospam_site_tables,
	$zerospam_drop_table,
	$zerospam_delete_by_prefix,
	$zerospam_deletes_data
): void {
	// Read first: the setting is one of the options deleted below.
	$delete_data = $zerospam_deletes_data();

	// The license check is cached under a key built from the license itself, so read it before the settings go.
	$zerospam_settings = get_option( 'zero-spam-zerospam' );
	$licenses          = array(
		is_array( $zerospam_settings ) ? (string) ( $zerospam_settings['zerospam_license'] ?? '' ) : '',
		defined( 'ZEROSPAM_LICENSE_KEY' ) ? (string) ZEROSPAM_LICENSE_KEY : '',
	);
	foreach ( array_filter( $licenses ) as $license ) {
		delete_transient( sanitize_title( 'license_' . $license ) );
	}

	foreach ( $zerospam_site_transients as $transient ) {
		delete_transient( $transient );
	}

	foreach ( $zerospam_cron_hooks as $hook ) {
		wp_unschedule_hook( $hook );
	}

	$swept = $zerospam_delete_by_prefix( $wpdb->options, 'option_name', $zerospam_transient_prefixes );

	if ( $delete_data ) {
		foreach ( $zerospam_modules as $module ) {
			delete_option( "zero-spam-{$module}" );
		}

		foreach ( $zerospam_site_options as $option ) {
			delete_option( $option );
		}

		$swept += $zerospam_delete_by_prefix( $wpdb->options, 'option_name', $zerospam_option_prefixes );

		foreach ( $zerospam_site_tables as $table ) {
			$zerospam_drop_table( $wpdb->prefix . $table );
		}

		// Utilities::log() writes this site's error log (it can contain visitor IPs) to uploads/, or uploads/sites/{id}/ on a subsite.
		$uploads = wp_upload_dir( null, false );
		wp_delete_file( trailingslashit( $uploads['basedir'] ) . 'zerospam.log' );
	}

	if ( $swept > 0 ) {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
};

// Network-wide data follows the network's value (on a single site, the site's). Read before any site's options go.
$zerospam_delete_network_data = $zerospam_deletes_data( true );

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

// Network-wide caches, removed once.
foreach ( $zerospam_network_transients as $zerospam_transient ) {
	delete_site_transient( $zerospam_transient );
}

if ( is_multisite() ) {
	// Every network's sitemeta, matching the every-site loop above.
	$zerospam_delete_by_prefix( $wpdb->sitemeta, 'meta_key', $zerospam_sitemeta_transient_prefixes );
}

if ( ! $zerospam_delete_network_data ) {
	return;
}

// Network-wide data, removed once (on a single site these are ordinary options and tables).
foreach ( $zerospam_network_options as $zerospam_option ) {
	delete_site_option( $zerospam_option );
}

if ( is_multisite() ) {
	$zerospam_delete_by_prefix( $wpdb->sitemeta, 'meta_key', $zerospam_sitemeta_prefixes );
}

foreach ( $zerospam_network_tables as $zerospam_table ) {
	$zerospam_drop_table( $wpdb->base_prefix . $zerospam_table );
}

foreach ( $zerospam_user_meta as $zerospam_meta_key ) {
	delete_metadata( 'user', 0, $zerospam_meta_key, '', true );
}
