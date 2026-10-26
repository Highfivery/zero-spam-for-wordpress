<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps API_Usage_Tracker to this file name.
/**
 * API Usage Tracker class
 *
 * Tracks Zero Spam API usage, quota, and errors for monitoring and alerting.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * API Usage Tracker class
 */
class API_Usage_Tracker {

	/**
	 * Tracks an API call event
	 *
	 * @param string          $endpoint         The API endpoint called.
	 * @param array|\WP_Error $response         The wp_remote_get response.
	 * @param array           $request_params   Request parameters.
	 * @param int             $response_time_ms Response time in milliseconds.
	 */
	public static function track_api_call( $endpoint, $response, $request_params = array(), $response_time_ms = 0 ) {
		// Only track if monitoring is enabled.
		if ( ! self::is_monitoring_enabled() ) {
			return;
		}

		global $wpdb;

		$site_id       = get_current_blog_id();
		$response_code = is_wp_error( $response ) ? null : wp_remote_retrieve_response_code( $response );
		$body          = is_wp_error( $response ) ? null : wp_remote_retrieve_body( $response );
		$decoded       = $body ? json_decode( $body, true ) : null;

		// Extract quota information from response.
		$queries_limit     = null;
		$queries_made      = null;
		$queries_remaining = null;
		$error_message     = null;

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
		} elseif ( $decoded && isset( $decoded['body_response'] ) ) {
			$queries_limit     = $decoded['body_response']['queries_limit'] ?? null;
			$queries_made      = $decoded['body_response']['queries_made'] ?? null;
			$queries_remaining = $decoded['body_response']['queries_remaining'] ?? null;
		}

		$now = current_time( 'mysql' );

		$data = array(
			'site_id'           => $site_id,
			'event_type'        => is_wp_error( $response ) ? 'error' : 'api_call',
			'endpoint'          => sanitize_text_field( $endpoint ),
			'response_code'     => $response_code,
			'response_time_ms'  => intval( $response_time_ms ),
			'queries_limit'     => $queries_limit,
			'queries_made'      => $queries_made,
			'queries_remaining' => $queries_remaining,
			'error_message'     => $error_message ? sanitize_text_field( $error_message ) : null,
			'request_params'    => wp_json_encode( $request_params ),
			'date_recorded'     => $now,
			'hour_bucket'       => gmdate( 'Y-m-d H:00:00', strtotime( $now ) ),
			'day_bucket'        => gmdate( 'Y-m-d', strtotime( $now ) ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The plugin's own table; there is no API for it.
		$wpdb->insert( $wpdb->base_prefix . DB::$tables['api_usage'], $data );

		// Clear usage cache after tracking.
		self::clear_usage_cache( $site_id );
	}

	/**
	 * Tracks a cache hit event
	 *
	 * @param string $endpoint       The API endpoint that would have been called.
	 * @param array  $request_params Request parameters.
	 */
	public static function track_cache_hit( $endpoint, $request_params = array() ) {
		// Only track if monitoring is enabled.
		if ( ! self::is_monitoring_enabled() ) {
			return;
		}

		global $wpdb;

		$site_id = get_current_blog_id();
		$now     = current_time( 'mysql' );

		$data = array(
			'site_id'        => $site_id,
			'event_type'     => 'cache_hit',
			'endpoint'       => sanitize_text_field( $endpoint ),
			'response_code'  => null,
			'request_params' => wp_json_encode( $request_params ),
			'date_recorded'  => $now,
			'hour_bucket'    => gmdate( 'Y-m-d H:00:00', strtotime( $now ) ),
			'day_bucket'     => gmdate( 'Y-m-d', strtotime( $now ) ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The plugin's own table; there is no API for it.
		$wpdb->insert( $wpdb->base_prefix . DB::$tables['api_usage'], $data );

		// Clear usage cache after tracking.
		self::clear_usage_cache( $site_id );
	}

	/**
	 * Checks if API usage monitoring is enabled
	 *
	 * @return bool True if monitoring is enabled.
	 */
	public static function is_monitoring_enabled() {
		// The registry built once per request (this runs on every API call).
		return 'enabled' === \ZeroSpam\Core\Settings_Schema::value( 'api_monitoring' );
	}

	/**
	 * Gets usage statistics for a site
	 *
	 * @param int    $site_id Site ID (default current site).
	 * @param string $period  Period: 'today', 'yesterday', 'week', 'month', 'all'.
	 * @return array Usage statistics.
	 */
	public static function get_usage_stats( $site_id = null, $period = 'today' ) {
		global $wpdb;

		if ( null === $site_id ) {
			$site_id = get_current_blog_id();
		}

		// Check cache first (1 hour cache).
		$cache_key = "zerospam_usage_stats_{$site_id}_{$period}";
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = $wpdb->base_prefix . DB::$tables['api_usage'];
		$range = self::date_range( $period );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table; the result is cached in a transient above.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_events,
					SUM(CASE WHEN event_type = 'api_call' THEN 1 ELSE 0 END) as api_calls,
					SUM(CASE WHEN event_type = 'cache_hit' THEN 1 ELSE 0 END) as cache_hits,
					SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors,
					AVG(CASE WHEN response_time_ms > 0 THEN response_time_ms ELSE NULL END) as avg_response_time,
					MAX(queries_limit) as current_limit,
					MAX(queries_made) as current_made,
					MAX(queries_remaining) as current_remaining
				FROM %i
				WHERE site_id = %d AND date_recorded >= %s AND date_recorded < %s",
				$table,
				$site_id,
				$range[0],
				$range[1]
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			$stats = array(
				'total_events'      => 0,
				'api_calls'         => 0,
				'cache_hits'        => 0,
				'errors'            => 0,
				'avg_response_time' => 0,
				'current_limit'     => null,
				'current_made'      => null,
				'current_remaining' => null,
			);
		}

		// Cache for 1 hour.
		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Gets network-wide usage statistics (for multisite super admins)
	 *
	 * @param string $period Period: 'today', 'yesterday', 'week', 'month', 'all'.
	 * @return array Network-wide usage statistics.
	 */
	public static function get_network_usage_stats( $period = 'today' ) {
		global $wpdb;

		// Check cache first (1 hour cache).
		$cache_key = "zerospam_network_usage_stats_{$period}";
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = $wpdb->base_prefix . DB::$tables['api_usage'];
		$range = self::date_range( $period );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table; the result is cached in a transient above.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_events,
					SUM(CASE WHEN event_type = 'api_call' THEN 1 ELSE 0 END) as api_calls,
					SUM(CASE WHEN event_type = 'cache_hit' THEN 1 ELSE 0 END) as cache_hits,
					SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors,
					AVG(CASE WHEN response_time_ms > 0 THEN response_time_ms ELSE NULL END) as avg_response_time,
					MAX(queries_limit) as current_limit,
					MAX(queries_made) as current_made,
					MAX(queries_remaining) as current_remaining,
					COUNT(DISTINCT site_id) as total_sites
				FROM %i
				WHERE date_recorded >= %s AND date_recorded < %s",
				$table,
				$range[0],
				$range[1]
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			$stats = array(
				'total_events'      => 0,
				'api_calls'         => 0,
				'cache_hits'        => 0,
				'errors'            => 0,
				'avg_response_time' => 0,
				'current_limit'     => null,
				'current_made'      => null,
				'current_remaining' => null,
				'total_sites'       => 0,
			);
		}

		// Cache for 1 hour.
		set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

		return $stats;
	}

	/**
	 * Gets per-site breakdown for network admin
	 *
	 * @param string $period Period filter.
	 * @return array Array of per-site statistics.
	 */
	public static function get_per_site_breakdown( $period = 'today' ) {
		global $wpdb;

		$table = $wpdb->base_prefix . DB::$tables['api_usage'];
		$range = self::date_range( $period );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table; one row per site, network admin screens only.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					site_id,
					COUNT(*) as total_events,
					SUM(CASE WHEN event_type = 'api_call' THEN 1 ELSE 0 END) as api_calls,
					SUM(CASE WHEN event_type = 'cache_hit' THEN 1 ELSE 0 END) as cache_hits,
					SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
				FROM %i
				WHERE date_recorded >= %s AND date_recorded < %s
				GROUP BY site_id
				ORDER BY api_calls DESC",
				$table,
				$range[0],
				$range[1]
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * The dates a period covers, in site time (the format of `date_recorded`):
	 * from (inclusive) and to (exclusive).
	 *
	 * @since 6.0.0 Replaces the SQL fragment of get_date_filter().
	 *
	 * @param string $period today, yesterday, week (the last 7 days), month (the last 30 days) or all.
	 * @return array{0: string, 1: string}
	 */
	private static function date_range( $period ): array {
		$today    = new \DateTimeImmutable( substr( current_time( 'mysql' ), 0, 10 ) . ' 00:00:00' );
		$tomorrow = $today->modify( '+1 day' )->format( 'Y-m-d H:i:s' );

		switch ( $period ) {
			case 'today':
				return array( $today->format( 'Y-m-d H:i:s' ), $tomorrow );
			case 'yesterday':
				return array( $today->modify( '-1 day' )->format( 'Y-m-d H:i:s' ), $today->format( 'Y-m-d H:i:s' ) );
			case 'week':
				return array( $today->modify( '-7 days' )->format( 'Y-m-d H:i:s' ), $tomorrow );
			case 'month':
				return array( $today->modify( '-30 days' )->format( 'Y-m-d H:i:s' ), $tomorrow );
			case 'all':
			default:
				return array( '1000-01-01 00:00:00', '9999-12-31 23:59:59' );
		}
	}

	/**
	 * Clears usage cache for a site
	 *
	 * @param int $site_id Site ID.
	 */
	public static function clear_usage_cache( $site_id = null ) {
		if ( null === $site_id ) {
			$site_id = get_current_blog_id();
		}

		$periods = array( 'today', 'yesterday', 'week', 'month', 'all' );

		foreach ( $periods as $period ) {
			delete_transient( "zerospam_usage_stats_{$site_id}_{$period}" );
			delete_transient( "zerospam_network_usage_stats_{$period}" );
		}
	}

	/**
	 * Gets hourly usage data for charts
	 *
	 * @param int    $site_id Site ID.
	 * @param string $period  Period: 'today', 'week', 'month'.
	 * @return array Hourly usage data.
	 */
	public static function get_hourly_usage( $site_id = null, $period = 'today' ) {
		global $wpdb;

		if ( null === $site_id ) {
			$site_id = get_current_blog_id();
		}

		$table = $wpdb->base_prefix . DB::$tables['api_usage'];
		$range = self::date_range( $period );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table; at most one row per hour of the period.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					hour_bucket as hour,
					COUNT(*) as total_events,
					SUM(CASE WHEN event_type = 'api_call' THEN 1 ELSE 0 END) as api_calls,
					SUM(CASE WHEN event_type = 'cache_hit' THEN 1 ELSE 0 END) as cache_hits,
					SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
				FROM %i
				WHERE site_id = %d AND date_recorded >= %s AND date_recorded < %s
				GROUP BY hour_bucket
				ORDER BY hour_bucket ASC",
				$table,
				$site_id,
				$range[0],
				$range[1]
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Detects usage anomalies
	 *
	 * Each check compares today's use with its threshold setting in API
	 * Monitoring (API_Usage_Alerts::threshold(); before 6.0.0 the values
	 * were fixed and the settings were never read):
	 *
	 * - usage_spike: today's calls above the threshold's percentage of the
	 *   daily average of the last 7 days (default 300%);
	 * - high_error_rate: failed requests above the threshold's share of
	 *   today's events (default 10%);
	 * - quota_warning / quota_critical: the share of the quota used, from
	 *   the latest quota the API reported in the current quota period (see
	 *   quota_snapshot()), above the warning threshold (default 80%) or at
	 *   least the critical one (default 90%);
	 * - slow_response: today's average response time above the threshold in
	 *   milliseconds (default 5000).
	 *
	 * @param int $site_id Site ID.
	 * @return array Array of detected anomalies.
	 */
	public static function detect_anomalies( $site_id = null ) {
		if ( null === $site_id ) {
			$site_id = get_current_blog_id();
		}

		$anomalies = array();

		// Get today's stats vs. last 7 days average.
		$today_stats    = self::get_usage_stats( $site_id, 'today' );
		$week_stats     = self::get_usage_stats( $site_id, 'week' );
		$week_avg_calls = ceil( $week_stats['api_calls'] / 7 );
		$spike          = API_Usage_Alerts::threshold( 'usage_spike' );

		// Spike detection: today's calls above the threshold's share of the weekly average.
		if ( $week_avg_calls > 0 && $today_stats['api_calls'] > ( $week_avg_calls * $spike / 100 ) ) {
			$anomalies[] = array(
				'type'     => 'usage_spike',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: 1: today's calls, 2: weekly average */
					__( 'Usage spike detected: %1$d calls today vs %2$d daily average', 'zero-spam' ),
					$today_stats['api_calls'],
					$week_avg_calls
				),
				'value'    => $today_stats['api_calls'],
				'baseline' => $week_avg_calls,
			);
		}

		// Error rate detection.
		if ( $today_stats['total_events'] > 0 ) {
			$error_rate = ( $today_stats['errors'] / $today_stats['total_events'] ) * 100;

			if ( $error_rate > API_Usage_Alerts::threshold( 'high_error_rate' ) ) {
				$anomalies[] = array(
					'type'     => 'high_error_rate',
					'severity' => 'critical',
					'message'  => sprintf(
						/* translators: %s: error rate percentage */
						__( 'High error rate detected: %s%% of requests failing', 'zero-spam' ),
						number_format( $error_rate, 1 )
					),
					'value'    => $error_rate,
				);
			}
		}

		// Quota check: the share of the quota used in the current quota period.
		$quota = self::quota_snapshot( (int) $site_id, $today_stats );
		if ( null !== $quota ) {
			$remaining_pct = ( $quota['remaining'] / $quota['limit'] ) * 100;
			$used_pct      = 100 - $remaining_pct;

			if ( $used_pct >= API_Usage_Alerts::threshold( 'quota_critical' ) ) {
				$anomalies[] = array(
					'type'     => 'quota_critical',
					'severity' => 'critical',
					'message'  => sprintf(
						/* translators: %s: percentage remaining */
						__( 'API quota critically low: %s%% remaining', 'zero-spam' ),
						number_format( $remaining_pct, 1 )
					),
					'value'    => $remaining_pct,
				);
			} elseif ( $used_pct > API_Usage_Alerts::threshold( 'quota_warning' ) ) {
				$anomalies[] = array(
					'type'     => 'quota_warning',
					'severity' => 'warning',
					'message'  => sprintf(
						/* translators: %s: percentage remaining */
						__( 'API quota running low: %s%% remaining', 'zero-spam' ),
						number_format( $remaining_pct, 1 )
					),
					'value'    => $remaining_pct,
				);
			}
		}

		// Slow response time.
		if ( $today_stats['avg_response_time'] > API_Usage_Alerts::threshold( 'slow_response' ) ) {
			$anomalies[] = array(
				'type'     => 'slow_response',
				'severity' => 'warning',
				'message'  => sprintf(
					/* translators: %s: average response time in seconds */
					__( 'Slow API responses detected: %ss average', 'zero-spam' ),
					number_format( $today_stats['avg_response_time'] / 1000, 2 )
				),
				'value'    => $today_stats['avg_response_time'],
			);
		}

		return $anomalies;
	}

	/**
	 * The quota the API last reported for a site: its limit and what remained.
	 *
	 * With a Quota Reset Date set (API Monitoring), the latest report since
	 * the current quota period started (License_Status::quota_period()), so
	 * a report from before the last reset no longer counts. Without one,
	 * today's reports, as before 6.0.0.
	 *
	 * @since 6.0.0
	 *
	 * @param int                  $site_id     Site ID.
	 * @param array<string, mixed> $today_stats get_usage_stats() for today.
	 * @return array{limit: int, remaining: int}|null Null when nothing usable was reported.
	 */
	public static function quota_snapshot( int $site_id, array $today_stats ): ?array {
		global $wpdb;

		$period = \ZeroSpam\Core\License_Status::quota_period();

		if ( null === $period ) {
			$limit     = $today_stats['current_limit'] ?? null;
			$remaining = $today_stats['current_remaining'] ?? null;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row of the plugin's own table, read by the hourly cron.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT queries_limit, queries_remaining FROM %i WHERE site_id = %d AND queries_limit IS NOT NULL AND date_recorded >= %s ORDER BY date_recorded DESC, usage_id DESC LIMIT 1',
					$wpdb->base_prefix . DB::$tables['api_usage'],
					$site_id,
					$period['start'] . ' 00:00:00'
				),
				ARRAY_A
			);

			$limit     = is_array( $row ) ? $row['queries_limit'] : null;
			$remaining = is_array( $row ) ? $row['queries_remaining'] : null;
		}

		// Nothing to judge without a limit or a remaining count (0 remaining is a used-up quota).
		if ( ! is_numeric( $limit ) || (int) $limit <= 0 || ! is_numeric( $remaining ) ) {
			return null;
		}

		return array(
			'limit'     => (int) $limit,
			'remaining' => max( 0, min( (int) $remaining, (int) $limit ) ),
		);
	}

	/**
	 * Aggregates old data to save space
	 *
	 * Runs daily via WP-Cron to aggregate records older than 30 days.
	 */
	public static function aggregate_old_data() {
		global $wpdb;

		$table = $wpdb->base_prefix . DB::$tables['api_usage'];

		// Get retention setting (default 90 days).
		$retention      = \ZeroSpam\Core\Settings_Schema::value( 'api_retention' );
		$retention_days = is_numeric( $retention ) && (int) $retention > 0 ? (int) $retention : 90;

		// Delete records older than retention period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Daily cleanup of the plugin's own table.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE date_recorded < DATE_SUB(NOW(), INTERVAL %d DAY)',
				$table,
				$retention_days
			)
		);

		// Log aggregation.
		\ZeroSpam\Core\Utilities::log( sprintf( 'API usage data cleanup: Deleted records older than %d days', $retention_days ) );
	}
}
