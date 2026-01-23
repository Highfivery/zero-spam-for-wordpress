<?php
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
	 * @param string $endpoint        The API endpoint called.
	 * @param array  $response        The wp_remote_get response.
	 * @param array  $request_params  Request parameters.
	 * @param int    $response_time_ms Response time in milliseconds.
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

		// @codingStandardsIgnoreLine
		$wpdb->insert( $wpdb->prefix . DB::$tables['api_usage'], $data );

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

		// @codingStandardsIgnoreLine
		$wpdb->insert( $wpdb->prefix . DB::$tables['api_usage'], $data );

		// Clear usage cache after tracking.
		self::clear_usage_cache( $site_id );
	}

	/**
	 * Checks if API usage monitoring is enabled
	 *
	 * @return bool True if monitoring is enabled.
	 */
	public static function is_monitoring_enabled() {
		$settings = \ZeroSpam\Core\Settings::get_settings();
		return ! empty( $settings['api_monitoring']['value'] ) && 'enabled' === $settings['api_monitoring']['value'];
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

		$table = $wpdb->prefix . DB::$tables['api_usage'];

		// Build date filter based on period.
		$date_filter = self::get_date_filter( $period );

		$stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
				FROM {$table}
				WHERE site_id = %d {$date_filter}",
				$site_id
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

		$table = $wpdb->prefix . DB::$tables['api_usage'];

		// Build date filter based on period.
		$date_filter = self::get_date_filter( $period );

		$stats = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			FROM {$table}
			WHERE 1=1 {$date_filter}",
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

		$table       = $wpdb->prefix . DB::$tables['api_usage'];
		$date_filter = self::get_date_filter( $period );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT 
				site_id,
				COUNT(*) as total_events,
				SUM(CASE WHEN event_type = 'api_call' THEN 1 ELSE 0 END) as api_calls,
				SUM(CASE WHEN event_type = 'cache_hit' THEN 1 ELSE 0 END) as cache_hits,
				SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
			FROM {$table}
			WHERE 1=1 {$date_filter}
			GROUP BY site_id
			ORDER BY api_calls DESC",
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Helper function to build date filter SQL
	 *
	 * @param string $period Period string.
	 * @return string SQL WHERE clause fragment.
	 */
	private static function get_date_filter( $period ) {
		$now = current_time( 'mysql' );

		switch ( $period ) {
			case 'today':
				return " AND DATE(date_recorded) = DATE('" . esc_sql( $now ) . "')";
			case 'yesterday':
				return " AND DATE(date_recorded) = DATE('" . esc_sql( $now ) . "' - INTERVAL 1 DAY)";
			case 'week':
				return " AND date_recorded >= DATE('" . esc_sql( $now ) . "' - INTERVAL 7 DAY)";
			case 'month':
				return " AND date_recorded >= DATE('" . esc_sql( $now ) . "' - INTERVAL 30 DAY)";
			case 'all':
			default:
				return '';
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

		$table       = $wpdb->prefix . DB::$tables['api_usage'];
		$date_filter = self::get_date_filter( $period );

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT 
					hour_bucket as hour,
					COUNT(*) as total_events,
					SUM(CASE WHEN event_type = 'api_call' THEN 1 ELSE 0 END) as api_calls,
					SUM(CASE WHEN event_type = 'cache_hit' THEN 1 ELSE 0 END) as cache_hits,
					SUM(CASE WHEN event_type = 'error' THEN 1 ELSE 0 END) as errors
				FROM {$table}
				WHERE site_id = %d {$date_filter}
				GROUP BY hour_bucket
				ORDER BY hour_bucket ASC",
				$site_id
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Detects usage anomalies
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

		// Spike detection: Today's calls > 3x weekly average.
		if ( $week_avg_calls > 0 && $today_stats['api_calls'] > ( $week_avg_calls * 3 ) ) {
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

		// Error rate detection: >10% errors.
		if ( $today_stats['total_events'] > 0 ) {
			$error_rate = ( $today_stats['errors'] / $today_stats['total_events'] ) * 100;

			if ( $error_rate > 10 ) {
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

		// Quota check: <20% remaining.
		if ( $today_stats['current_limit'] && $today_stats['current_remaining'] ) {
			$remaining_pct = ( $today_stats['current_remaining'] / $today_stats['current_limit'] ) * 100;

			if ( $remaining_pct < 20 && $remaining_pct > 10 ) {
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
			} elseif ( $remaining_pct <= 10 ) {
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
			}
		}

		// Slow response time: >5000ms average.
		if ( $today_stats['avg_response_time'] > 5000 ) {
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
	 * Aggregates old data to save space
	 *
	 * Runs daily via WP-Cron to aggregate records older than 30 days.
	 */
	public static function aggregate_old_data() {
		global $wpdb;

		$table = $wpdb->prefix . DB::$tables['api_usage'];

		// Get retention setting (default 90 days).
		$settings        = \ZeroSpam\Core\Settings::get_settings();
		$retention_days  = ! empty( $settings['api_retention']['value'] ) ? intval( $settings['api_retention']['value'] ) : 90;
		$aggregate_after = 30; // Aggregate data older than 30 days.

		// Delete records older than retention period.
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE date_recorded < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);

		// Log aggregation.
		\ZeroSpam\Core\Utilities::log( sprintf( 'API usage data cleanup: Deleted records older than %d days', $retention_days ) );
	}
}
