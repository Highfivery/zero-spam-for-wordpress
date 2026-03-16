<?php
/**
 * Network Statistics Tracker
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Statistics Tracker
 *
 * Handles aggregation and retrieval of spam statistics across multisite networks.
 */
class Network_Stats_Tracker {

	/**
	 * Get network-wide statistics
	 *
	 * @param string $period Time period (today, yesterday, week, month, custom).
	 * @param string $start_date Custom start date (Y-m-d format).
	 * @param string $end_date Custom end date (Y-m-d format).
	 * @return array Network statistics.
	 */
	public static function get_network_stats( $period = 'month', $start_date = null, $end_date = null ) {
		if ( ! is_multisite() ) {
			return array(
				'total_spam'    => 0,
				'unique_ips'    => 0,
				'spam_types'    => 0,
				'spam_by_type'  => array(),
				'top_countries' => array(),
				'top_ips'       => array(),
			);
		}

		$cache_key = 'zerospam_network_stats_' . $period;
		if ( $start_date && $end_date ) {
			$cache_key .= '_' . $start_date . '_' . $end_date;
		}

		$stats = get_transient( $cache_key );

		if ( false === $stats ) {
			global $wpdb;

			// Try to use aggregated data for better performance.
			$use_aggregated = ( ! $start_date && ! $end_date && in_array( $period, array( 'week', 'month' ), true ) );

			if ( $use_aggregated ) {
				// Calculate date range for aggregation query.
				$days      = ( 'week' === $period ) ? 7 : 30;
				$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );
				$date_to   = gmdate( 'Y-m-d' );

				// Try daily aggregation table first.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$aggregated = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT 
							site_id,
							SUM(total_spam_blocked) as total_spam,
							SUM(unique_ips) as unique_ips,
							spam_by_type,
							top_countries,
							top_ips
						FROM {$wpdb->base_prefix}wpzerospam_stats_daily
						WHERE stat_date >= %s AND stat_date <= %s
						GROUP BY site_id",
						$date_from,
						$date_to
					),
					ARRAY_A
				);

				if ( ! empty( $aggregated ) ) {
					// Use aggregated data.
					$total_spam     = 0;
					$all_ips        = array();
					$spam_by_type   = array();
					$country_counts = array();
					$ip_counts      = array();

					foreach ( $aggregated as $site_data ) {
						$total_spam += absint( $site_data['total_spam'] );

						// Note: unique_ips per site can't be simply summed (same IP across sites).
						// We'd need the actual IPs, which we don't store in aggregation.
						// For now, this is an estimate.
						$all_ips[] = absint( $site_data['unique_ips'] );

						// Merge spam types.
						$site_spam_types = json_decode( $site_data['spam_by_type'], true );
						if ( is_array( $site_spam_types ) ) {
							foreach ( $site_spam_types as $type_data ) {
								$type = $type_data['log_type'] ?? 'unknown';
								if ( ! isset( $spam_by_type[ $type ] ) ) {
									$spam_by_type[ $type ] = 0;
								}
								$spam_by_type[ $type ] += absint( $type_data['count'] ?? 0 );
							}
						}

						// Merge countries.
						$site_countries = json_decode( $site_data['top_countries'], true );
						if ( is_array( $site_countries ) ) {
							foreach ( $site_countries as $country_data ) {
								$country_key = $country_data['country'] ?? '';
								if ( $country_key ) {
									if ( ! isset( $country_counts[ $country_key ] ) ) {
										$country_counts[ $country_key ] = array(
											'country'      => $country_data['country'],
											'country_name' => $country_data['country_name'],
											'count'        => 0,
										);
									}
									$country_counts[ $country_key ]['count'] += absint( $country_data['count'] ?? 0 );
								}
							}
						}

						// Merge IPs.
						$site_ips = json_decode( $site_data['top_ips'], true );
						if ( is_array( $site_ips ) ) {
							foreach ( $site_ips as $ip_data ) {
								$ip_key = $ip_data['user_ip'] ?? '';
								if ( $ip_key ) {
									if ( ! isset( $ip_counts[ $ip_key ] ) ) {
										$ip_counts[ $ip_key ] = array(
											'user_ip' => $ip_data['user_ip'],
											'country' => $ip_data['country'] ?? '',
											'count'   => 0,
										);
									}
									$ip_counts[ $ip_key ]['count'] += absint( $ip_data['count'] ?? 0 );
								}
							}
						}
					}

					// Sort and format.
					arsort( $spam_by_type );
					$spam_by_type_formatted = array();
					foreach ( array_slice( $spam_by_type, 0, 10, true ) as $type => $count ) {
						$spam_by_type_formatted[] = array(
							'log_type' => $type,
							'count'    => $count,
						);
					}

					usort(
						$country_counts,
						function ( $a, $b ) {
							return $b['count'] - $a['count'];
						}
					);
					$top_countries = array_slice( $country_counts, 0, 10 );

					usort(
						$ip_counts,
						function ( $a, $b ) {
							return $b['count'] - $a['count'];
						}
					);
					$top_ips = array_slice( $ip_counts, 0, 10 );

					$stats = array(
						'total_spam'    => $total_spam,
						'unique_ips'    => array_sum( $all_ips ), // Approximate.
						'spam_types'    => count( $spam_by_type ),
						'spam_by_type'  => $spam_by_type_formatted,
						'top_countries' => $top_countries,
						'top_ips'       => $top_ips,
					);

					// Cache for 1 hour.
					set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
					return $stats;
				}
			}

			// Fall back to raw log queries if aggregation not available.
			$sites = get_sites( array( 'number' => 1000 ) );

			$total_spam     = 0;
			$all_ips        = array();
			$spam_by_type   = array();
			$country_counts = array();
			$ip_counts      = array();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				$date_filter = self::get_date_filter( $period, $start_date, $end_date );
				$log_table   = $wpdb->prefix . DB::$tables['log'];

				// Get site data.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$site_logs = $wpdb->get_results(
					"SELECT log_type, user_ip, country, country_name
					FROM {$log_table}
					WHERE {$date_filter}",
					ARRAY_A
				);

				foreach ( $site_logs as $log ) {
					++$total_spam;
					$all_ips[ $log['user_ip'] ] = true;

					// Count by type.
					$type = $log['log_type'] ?? 'unknown';
					if ( ! isset( $spam_by_type[ $type ] ) ) {
						$spam_by_type[ $type ] = 0;
					}
					++$spam_by_type[ $type ];

					// Count by country.
					if ( ! empty( $log['country'] ) ) {
						$country_key = $log['country'];
						if ( ! isset( $country_counts[ $country_key ] ) ) {
							$country_counts[ $country_key ] = array(
								'country'      => $log['country'],
								'country_name' => $log['country_name'],
								'count'        => 0,
							);
						}
						++$country_counts[ $country_key ]['count'];
					}

					// Count by IP.
					$ip_key = $log['user_ip'];
					if ( ! isset( $ip_counts[ $ip_key ] ) ) {
						$ip_counts[ $ip_key ] = array(
							'user_ip' => $log['user_ip'],
							'country' => $log['country'],
							'count'   => 0,
						);
					}
					++$ip_counts[ $ip_key ]['count'];
				}

				restore_current_blog();
			}

			// Sort and limit spam by type.
			arsort( $spam_by_type );
			$spam_by_type_formatted = array();
			foreach ( array_slice( $spam_by_type, 0, 10, true ) as $type => $count ) {
				$spam_by_type_formatted[] = array(
					'log_type' => $type,
					'count'    => $count,
				);
			}

			// Sort and limit countries.
			usort(
				$country_counts,
				function ( $a, $b ) {
					return $b['count'] - $a['count'];
				}
			);
			$top_countries = array_slice( $country_counts, 0, 10 );

			// Sort and limit IPs.
			usort(
				$ip_counts,
				function ( $a, $b ) {
					return $b['count'] - $a['count'];
				}
			);
			$top_ips = array_slice( $ip_counts, 0, 10 );

			$stats = array(
				'total_spam'    => $total_spam,
				'unique_ips'    => count( $all_ips ),
				'spam_types'    => count( $spam_by_type ),
				'spam_by_type'  => $spam_by_type_formatted,
				'top_countries' => $top_countries,
				'top_ips'       => $top_ips,
			);

			// Cache for 1 hour.
			set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
		}

		return $stats;
	}

	/**
	 * Get per-site statistics breakdown
	 *
	 * @param string $period Time period.
	 * @param int    $limit Number of sites to return (0 for all).
	 * @param string $order_by Order by column (spam_count, site_name).
	 * @param string $start_date Custom start date.
	 * @param string $end_date Custom end date.
	 * @return array Site statistics.
	 */
	public static function get_site_breakdown( $period = 'month', $limit = 10, $order_by = 'spam_count', $start_date = null, $end_date = null ) {
		if ( ! is_multisite() ) {
			return array();
		}

		$cache_key = 'zerospam_site_breakdown_' . $period . '_' . $limit . '_' . $order_by;
		if ( $start_date && $end_date ) {
			$cache_key .= '_' . $start_date . '_' . $end_date;
		}

		$breakdown = get_transient( $cache_key );

		if ( false === $breakdown ) {
			$sites     = get_sites( array( 'number' => 1000 ) );
			$breakdown = array();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				$site_stats = self::get_site_stats( $site->blog_id, $period, $start_date, $end_date );

				$breakdown[] = array(
					'site_id'      => $site->blog_id,
					'site_name'    => get_bloginfo( 'name' ),
					'site_url'     => get_site_url(),
					'spam_count'   => $site_stats['total_spam'],
					'unique_ips'   => $site_stats['unique_ips'],
					'top_type'     => $site_stats['top_spam_type'],
					'top_country'  => $site_stats['top_country'],
					'trend'        => $site_stats['trend'],
					'has_enhanced' => self::has_enhanced_protection( $site->blog_id ),
				);

				restore_current_blog();
			}

			// Sort by spam count (descending) or site name.
			if ( 'spam_count' === $order_by ) {
				usort(
					$breakdown,
					function ( $a, $b ) {
						return $b['spam_count'] - $a['spam_count'];
					}
				);
			} elseif ( 'site_name' === $order_by ) {
				usort(
					$breakdown,
					function ( $a, $b ) {
						return strcasecmp( $a['site_name'], $b['site_name'] );
					}
				);
			}

			// Limit results.
			if ( $limit > 0 ) {
				$breakdown = array_slice( $breakdown, 0, $limit );
			}

			// Cache for 1 hour.
			set_transient( $cache_key, $breakdown, HOUR_IN_SECONDS );
		}

		return $breakdown;
	}

	/**
	 * Get statistics for a specific site
	 *
	 * @param int    $site_id Site ID.
	 * @param string $period Time period.
	 * @param string $start_date Custom start date.
	 * @param string $end_date Custom end date.
	 * @return array Site statistics.
	 */
	public static function get_site_stats( $site_id, $period = 'month', $start_date = null, $end_date = null ) {
		// Switch to the site's context to access its tables.
		if ( is_multisite() ) {
			switch_to_blog( $site_id );
		}

		global $wpdb;

		$date_filter = self::get_date_filter( $period, $start_date, $end_date );
		$log_table   = $wpdb->prefix . DB::$tables['log'];

		// Get site totals.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$totals = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_spam,
				COUNT(DISTINCT user_ip) as unique_ips
			FROM {$log_table}
			WHERE {$date_filter}",
			ARRAY_A
		);

		// Get top spam type for this site.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_type = $wpdb->get_var(
			"SELECT log_type
			FROM {$log_table}
			WHERE {$date_filter}
			GROUP BY log_type
			ORDER BY COUNT(*) DESC
			LIMIT 1"
		);

		// Get top country for this site.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_country = $wpdb->get_row(
			"SELECT country, country_name, COUNT(*) as count
			FROM {$log_table}
			WHERE {$date_filter} AND country IS NOT NULL
			GROUP BY country, country_name
			ORDER BY count DESC
			LIMIT 1",
			ARRAY_A
		);

		$result = array(
			'total_spam'       => absint( $totals['total_spam'] ?? 0 ),
			'unique_ips'       => absint( $totals['unique_ips'] ?? 0 ),
			'top_spam_type'    => $top_type ?? '',
			'top_country'      => $top_country['country_name'] ?? '',
			'top_country_code' => $top_country['country'] ?? '',
			'trend'            => self::calculate_trend( $site_id, $period, $start_date, $end_date ),
		);

		// Restore original blog context.
		if ( is_multisite() ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Calculate trend percentage
	 *
	 * @param int    $site_id Site ID.
	 * @param string $period Period.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Trend data.
	 */
	private static function calculate_trend( $site_id, $period, $start_date = null, $end_date = null ) {
		// Switch to the site's context to access its tables.
		if ( is_multisite() ) {
			switch_to_blog( $site_id );
		}

		global $wpdb;

		$log_table = $wpdb->prefix . DB::$tables['log'];

		// Get current period count.
		$current_filter = self::get_date_filter( $period, $start_date, $end_date );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$log_table} WHERE {$current_filter}"
		);

		// Get previous period count.
		$previous_filter = self::get_previous_period_filter( $period, $start_date, $end_date );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$previous_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$log_table} WHERE {$previous_filter}"
		);

		$current_count  = absint( $current_count );
		$previous_count = absint( $previous_count );

		if ( 0 === $previous_count ) {
			$percentage = $current_count > 0 ? 100 : 0;
		} else {
			$percentage = round( ( ( $current_count - $previous_count ) / $previous_count ) * 100, 1 );
		}

		$result = array(
			'current'    => $current_count,
			'previous'   => $previous_count,
			'percentage' => $percentage,
			'direction'  => $percentage > 0 ? 'up' : ( $percentage < 0 ? 'down' : 'neutral' ),
		);

		// Restore original blog context.
		if ( is_multisite() ) {
			restore_current_blog();
		}

		return $result;
	}

	/**
	 * Get date filter SQL
	 *
	 * @param string $period Period.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return string SQL WHERE clause.
	 */
	private static function get_date_filter( $period, $start_date = null, $end_date = null ) {
		if ( $start_date && $end_date ) {
			return "date_recorded >= '" . esc_sql( $start_date ) . " 00:00:00' AND date_recorded <= '" . esc_sql( $end_date ) . " 23:59:59'";
		}

		switch ( $period ) {
			case 'today':
				return 'DATE(date_recorded) = CURDATE()';
			case 'yesterday':
				return 'DATE(date_recorded) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
			case 'week':
				return 'date_recorded >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
			case 'month':
				return 'date_recorded >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
			default:
				return '1=1'; // All time.
		}
	}

	/**
	 * Get previous period filter SQL
	 *
	 * @param string $period Period.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return string SQL WHERE clause.
	 */
	private static function get_previous_period_filter( $period, $start_date = null, $end_date = null ) {
		if ( $start_date && $end_date ) {
			$days_diff  = ( strtotime( $end_date ) - strtotime( $start_date ) ) / DAY_IN_SECONDS;
			$prev_end   = gmdate( 'Y-m-d', strtotime( $start_date ) - DAY_IN_SECONDS );
			$prev_start = gmdate( 'Y-m-d', strtotime( $prev_end ) - ( $days_diff * DAY_IN_SECONDS ) );
			return "date_recorded >= '" . esc_sql( $prev_start ) . " 00:00:00' AND date_recorded <= '" . esc_sql( $prev_end ) . " 23:59:59'";
		}

		switch ( $period ) {
			case 'today':
				return 'DATE(date_recorded) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
			case 'yesterday':
				return 'DATE(date_recorded) = DATE_SUB(CURDATE(), INTERVAL 2 DAY)';
			case 'week':
				return 'date_recorded >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND date_recorded < DATE_SUB(NOW(), INTERVAL 7 DAY)';
			case 'month':
				return 'date_recorded >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND date_recorded < DATE_SUB(NOW(), INTERVAL 30 DAY)';
			default:
				return '1=0'; // No previous for all time.
		}
	}

	/**
	 * Check if site has Enhanced Protection enabled
	 *
	 * @param int $site_id Site ID.
	 * @return bool True if enabled.
	 */
	private static function has_enhanced_protection( $site_id ) {
		switch_to_blog( $site_id );
		$settings = \ZeroSpam\Core\Settings::get_settings();
		$enabled  = ! empty( $settings['zerospam']['value'] ) && 'enabled' === $settings['zerospam']['value'];
		restore_current_blog();
		return $enabled;
	}

	/**
	 * Clear network statistics cache
	 */
	public static function clear_cache() {
		global $wpdb;

		// Delete all network stats transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s",
				'_transient_zerospam_network_stats_%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s",
				'_transient_timeout_zerospam_network_stats_%'
			)
		);

		// Delete site breakdown transients.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s",
				'_transient_zerospam_site_breakdown_%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s",
				'_transient_timeout_zerospam_site_breakdown_%'
			)
		);
	}

	/**
	 * Get IPs attacking multiple sites
	 *
	 * @param int $min_sites Minimum number of sites attacked.
	 * @param int $period_days Days to look back.
	 * @return array IPs attacking multiple sites.
	 */
	public static function get_multi_site_attackers( $min_sites = 2, $period_days = 7 ) {
		if ( ! is_multisite() ) {
			return array();
		}

		$cache_key = "zerospam_multi_site_attackers_{$min_sites}_{$period_days}";
		$attackers = get_transient( $cache_key );

		if ( false === $attackers ) {
			$sites = get_sites( array( 'number' => 1000 ) );

			// Track which IPs appear on which sites.
			$ip_site_map = array();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				global $wpdb;
				$log_table = $wpdb->prefix . DB::$tables['log'];

				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$site_ips = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT user_ip, country, country_name
						FROM {$log_table}
						WHERE date_recorded >= DATE_SUB(NOW(), INTERVAL %d DAY)",
						$period_days
					),
					ARRAY_A
				);

				foreach ( $site_ips as $ip_data ) {
					$ip = $ip_data['user_ip'];
					if ( ! isset( $ip_site_map[ $ip ] ) ) {
						$ip_site_map[ $ip ] = array(
							'user_ip'      => $ip,
							'country'      => $ip_data['country'],
							'country_name' => $ip_data['country_name'],
							'sites'        => array(),
							'site_count'   => 0,
						);
					}
					if ( ! in_array( $site->blog_id, $ip_site_map[ $ip ]['sites'], true ) ) {
						$ip_site_map[ $ip ]['sites'][] = $site->blog_id;
						++$ip_site_map[ $ip ]['site_count'];
					}
				}

				restore_current_blog();
			}

			// Filter for IPs hitting minimum number of sites.
			$attackers = array();
			foreach ( $ip_site_map as $ip_data ) {
				if ( $ip_data['site_count'] >= $min_sites ) {
					$attackers[] = array(
						'user_ip'      => $ip_data['user_ip'],
						'country'      => $ip_data['country'],
						'country_name' => $ip_data['country_name'],
						'attack_count' => $ip_data['site_count'], // Number of sites attacked.
					);
				}
			}

			// Sort by site count descending.
			usort(
				$attackers,
				function ( $a, $b ) {
					return $b['attack_count'] - $a['attack_count'];
				}
			);

			$attackers = array_slice( $attackers, 0, 20 );

			// Cache for 1 hour.
			set_transient( $cache_key, $attackers, HOUR_IN_SECONDS );
		}

		return $attackers;
	}

	/**
	 * Get recommendation level for a site
	 *
	 * @param array $site_data Site data.
	 * @return string Recommendation level (high, medium, low, none).
	 */
	public static function get_recommendation_level( $site_data ) {
		$spam_count   = $site_data['spam_count'] ?? 0;
		$has_enhanced = $site_data['has_enhanced'] ?? false;

		// Already has Enhanced Protection.
		if ( $has_enhanced ) {
			return 'none';
		}

		// High spam (>300/month) - strongly recommend.
		if ( $spam_count > 300 ) {
			return 'high';
		}

		// Medium spam (100-300/month) - recommend.
		if ( $spam_count > 100 ) {
			return 'medium';
		}

		// Low spam (<100/month) - consider.
		if ( $spam_count > 50 ) {
			return 'low';
		}

		return 'none';
	}
}
