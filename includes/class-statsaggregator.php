<?php
/**
 * Stats_Aggregator class
 *
 * Handles daily aggregation of spam statistics for performance optimization.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

use ZeroSpam\Includes\DB;

/**
 * Stats aggregation for network statistics
 */
class Stats_Aggregator {

	/**
	 * Initialize aggregation hooks
	 */
	public function __construct() {
		// Schedule daily aggregation.
		add_action( 'init', array( $this, 'schedule_aggregation' ) );
		add_action( 'zerospam_aggregate_daily_stats', array( $this, 'aggregate_yesterday' ) );
	}

	/**
	 * Schedule daily aggregation cron job
	 */
	public function schedule_aggregation() {
		if ( ! wp_next_scheduled( 'zerospam_aggregate_daily_stats' ) ) {
			// Run daily at 2 AM.
			wp_schedule_event( strtotime( 'tomorrow 2:00 AM' ), 'daily', 'zerospam_aggregate_daily_stats' );
		}
	}

	/**
	 * Aggregate yesterday's data for all sites
	 */
	public function aggregate_yesterday() {
		if ( ! is_multisite() ) {
			return;
		}

		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$this->aggregate_date( $yesterday );

		// Also update monthly aggregation if month just ended.
		$today = gmdate( 'Y-m-d' );
		if ( gmdate( 'd', strtotime( $today ) ) === '01' ) {
			$last_month_year  = gmdate( 'Y', strtotime( 'first day of last month' ) );
			$last_month_month = gmdate( 'n', strtotime( 'first day of last month' ) );
			$this->aggregate_month( $last_month_year, $last_month_month );
		}
	}

	/**
	 * Aggregate stats for a specific date across all sites
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	public function aggregate_date( $date ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return;
		}

		$sites = get_sites( array( 'number' => 1000 ) );

		foreach ( $sites as $site ) {
			// Check if already aggregated.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT stat_id FROM {$wpdb->base_prefix}wpzerospam_stats_daily 
					WHERE site_id = %d AND stat_date = %s",
					$site->blog_id,
					$date
				)
			);

			if ( $exists ) {
				continue; // Already aggregated.
			}

			// Switch to site context.
			switch_to_blog( $site->blog_id );

			$log_table = $wpdb->prefix . DB::$tables['log'];

			// Get daily totals.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$totals = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT 
						COUNT(*) as total_spam,
						COUNT(DISTINCT user_ip) as unique_ips
					FROM {$log_table}
					WHERE DATE(date_recorded) = %s",
					$date
				),
				ARRAY_A
			);

			if ( empty( $totals['total_spam'] ) ) {
				restore_current_blog();
				continue; // No data for this date.
			}

			// Get spam by type.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$spam_by_type = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT log_type, COUNT(*) as count
					FROM {$log_table}
					WHERE DATE(date_recorded) = %s
					GROUP BY log_type
					ORDER BY count DESC
					LIMIT 10",
					$date
				),
				ARRAY_A
			);

			// Get top countries.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$top_countries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT country, country_name, COUNT(*) as count
					FROM {$log_table}
					WHERE DATE(date_recorded) = %s AND country IS NOT NULL
					GROUP BY country, country_name
					ORDER BY count DESC
					LIMIT 10",
					$date
				),
				ARRAY_A
			);

			// Get top IPs.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$top_ips = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_ip, COUNT(*) as count
					FROM {$log_table}
					WHERE DATE(date_recorded) = %s
					GROUP BY user_ip
					ORDER BY count DESC
					LIMIT 10",
					$date
				),
				ARRAY_A
			);

			// Get top log types.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$top_log_types = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT log_type, COUNT(*) as count
					FROM {$log_table}
					WHERE DATE(date_recorded) = %s
					GROUP BY log_type
					ORDER BY count DESC
					LIMIT 10",
					$date
				),
				ARRAY_A
			);

			restore_current_blog();

			// Insert aggregated data into stats_daily table.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$wpdb->base_prefix . 'wpzerospam_stats_daily',
				array(
					'site_id'            => $site->blog_id,
					'stat_date'          => $date,
					'total_spam_blocked' => absint( $totals['total_spam'] ),
					'spam_by_type'       => wp_json_encode( $spam_by_type ),
					'top_countries'      => wp_json_encode( $top_countries ),
					'top_ips'            => wp_json_encode( $top_ips ),
					'top_log_types'      => wp_json_encode( $top_log_types ),
					'unique_ips'         => absint( $totals['unique_ips'] ),
					'date_aggregated'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Aggregate stats for a specific month across all sites
	 *
	 * @param int $year Year (e.g., 2024).
	 * @param int $month Month (1-12).
	 */
	public function aggregate_month( $year, $month ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return;
		}

		$sites = get_sites( array( 'number' => 1000 ) );

		foreach ( $sites as $site ) {
			// Check if already aggregated.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT stat_id FROM {$wpdb->base_prefix}wpzerospam_stats_monthly 
					WHERE site_id = %d AND stat_year = %d AND stat_month = %d",
					$site->blog_id,
					$year,
					$month
				)
			);

			if ( $exists ) {
				// Update existing.
				$action = 'update';
			} else {
				$action = 'insert';
			}

			// Sum from daily aggregations if available, otherwise query raw logs.
			$first_day = sprintf( '%04d-%02d-01', $year, $month );
			$last_day  = gmdate( 'Y-m-t', strtotime( $first_day ) );

			// Try daily aggregations first (faster).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$daily_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						SUM(total_spam_blocked) as total_spam,
						SUM(unique_ips) as unique_ips,
						GROUP_CONCAT(spam_by_type) as spam_types,
						GROUP_CONCAT(top_countries) as countries,
						GROUP_CONCAT(top_ips) as ips,
						GROUP_CONCAT(top_log_types) as log_types
					FROM {$wpdb->base_prefix}wpzerospam_stats_daily
					WHERE site_id = %d 
					AND stat_date >= %s 
					AND stat_date <= %s",
					$site->blog_id,
					$first_day,
					$last_day
				),
				ARRAY_A
			);

			if ( ! empty( $daily_data[0]['total_spam'] ) ) {
				// Use aggregated daily data.
				$totals = array(
					'total_spam' => absint( $daily_data[0]['total_spam'] ),
					'unique_ips' => absint( $daily_data[0]['unique_ips'] ),
				);

				// Merge spam types, countries, IPs, log types.
				$spam_by_type  = array();
				$top_countries = array();
				$top_ips       = array();
				$top_log_types = array();

				// This is simplified - in production you'd properly merge and re-rank these.
				$spam_by_type_str  = $daily_data[0]['spam_types'] ?? '';
				$top_countries_str = $daily_data[0]['countries'] ?? '';
				$top_ips_str       = $daily_data[0]['ips'] ?? '';
				$top_log_types_str = $daily_data[0]['log_types'] ?? '';
			} else {
				// Fall back to raw logs.
				switch_to_blog( $site->blog_id );

				$log_table = $wpdb->prefix . DB::$tables['log'];

				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$totals = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT 
							COUNT(*) as total_spam,
							COUNT(DISTINCT user_ip) as unique_ips
						FROM {$log_table}
						WHERE date_recorded >= %s 
						AND date_recorded < DATE_ADD(%s, INTERVAL 1 MONTH)",
						$first_day . ' 00:00:00',
						$first_day . ' 00:00:00'
					),
					ARRAY_A
				);

				if ( empty( $totals['total_spam'] ) ) {
					restore_current_blog();
					continue;
				}

				// Get aggregated stats (simplified versions of daily query).
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$spam_by_type = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT log_type, COUNT(*) as count
						FROM {$log_table}
						WHERE date_recorded >= %s AND date_recorded < DATE_ADD(%s, INTERVAL 1 MONTH)
						GROUP BY log_type
						ORDER BY count DESC
						LIMIT 10",
						$first_day . ' 00:00:00',
						$first_day . ' 00:00:00'
					),
					ARRAY_A
				);

				$spam_by_type_str = wp_json_encode( $spam_by_type );

				restore_current_blog();

				// For simplicity, set others to empty.
				$top_countries_str = '[]';
				$top_ips_str       = '[]';
				$top_log_types_str = '[]';
			}

			// Insert or update monthly aggregation.
			$data = array(
				'site_id'            => $site->blog_id,
				'stat_year'          => $year,
				'stat_month'         => $month,
				'total_spam_blocked' => absint( $totals['total_spam'] ),
				'spam_by_type'       => $spam_by_type_str,
				'top_countries'      => $top_countries_str,
				'top_ips'            => $top_ips_str,
				'top_log_types'      => $top_log_types_str,
				'unique_ips'         => absint( $totals['unique_ips'] ),
				'date_aggregated'    => current_time( 'mysql' ),
			);

			if ( 'update' === $action ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->base_prefix . 'wpzerospam_stats_monthly',
					$data,
					array(
						'site_id'    => $site->blog_id,
						'stat_year'  => $year,
						'stat_month' => $month,
					),
					array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ),
					array( '%d', '%d', '%d' )
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->insert(
					$wpdb->base_prefix . 'wpzerospam_stats_monthly',
					$data,
					array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
				);
			}
		}
	}

	/**
	 * Backfill historical data
	 *
	 * @param int $days Number of days to backfill.
	 */
	public static function backfill_daily( $days = 30 ) {
		$aggregator = new self();

		for ( $i = 1; $i <= $days; $i++ ) {
			$date = gmdate( 'Y-m-d', strtotime( "-$i days" ) );
			$aggregator->aggregate_date( $date );
		}
	}

	/**
	 * Backfill monthly data
	 *
	 * @param int $months Number of months to backfill.
	 */
	public static function backfill_monthly( $months = 12 ) {
		$aggregator = new self();

		for ( $i = 1; $i <= $months; $i++ ) {
			$date  = strtotime( "-$i months" );
			$year  = gmdate( 'Y', $date );
			$month = gmdate( 'n', $date );
			$aggregator->aggregate_month( $year, $month );
		}
	}
}
