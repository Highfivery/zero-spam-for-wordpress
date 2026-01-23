<?php
/**
 * Plugin CLI Commands
 *
 * @package ZeroSpam
 */
class ZeroSpamCLI {
	/**
	 * Auto-configure the plugin with recommended settings
	 */
	public function autoconfigure() {
		\ZeroSpam\Core\Settings::auto_configure();
		WP_CLI::success( __( 'Zero Spam has been successfully auto-configured using the recommended defaults.', 'zero-spam' ) );
	}

	/**
	 * Outputs settings
	 */
	public function settings() {
		$modules  = \ZeroSpam\Core\Settings::get_settings_by_module();
		$settings = array();

		foreach ( $modules as $module => $module_settings ) {
			foreach ( $module_settings as $key => $setting ) {
				$settings[] = array(
					'module'  => $module,
					'setting' => $key,
					'value'   => isset( $setting['value'] ) ? $setting['value'] : false,
				);
			}
		}

		$fields = array( 'module', 'setting', 'value' );
		WP_CLI\Utils\format_items( 'table', $settings, $fields );
	}

	/**
	 * Update a plugin setting(s)
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Array of settings to update.
	 */
	public function set( $args, $assoc_args ) {
		$settings = \ZeroSpam\Core\Settings::get_settings();

		if ( $assoc_args ) {
			foreach ( $assoc_args as $key => $value ) {
				if ( ! isset( $settings[ $key ] ) ) {
					WP_CLI::error( $key . ' is not a valid setting.' );
				} elseif ( \ZeroSpam\Core\Utilities::update_setting( $key, $value ) ) {
						WP_CLI::success( '\'' . $key . '\' has been successfully updated to \'' . $value . '\'.' );
				} else {
					WP_CLI::error( 'There was a problem updating ' . $key . ' See the zerospam.log for more details.' );
				}
			}
		} else {
			WP_CLI::error( __( 'Oops! You didn\'t specify a setting to set (ex. wp zerospam set --share_data=enabled).', 'zero-spam' ) );
		}
	}

	/**
	 * Display API usage statistics
	 *
	 * ## OPTIONS
	 *
	 * [--period=<period>]
	 * : Time period: today, yesterday, week, month, all
	 * ---
	 * default: today
	 * options:
	 *   - today
	 *   - yesterday
	 *   - week
	 *   - month
	 *   - all
	 * ---
	 *
	 * [--site=<site_id>]
	 * : Site ID (multisite only)
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--scope=<scope>]
	 * : Scope: site or network (multisite only)
	 * ---
	 * default: site
	 * options:
	 *   - site
	 *   - network
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show today's usage
	 *     wp zerospam api_usage
	 *
	 *     # Show weekly usage in JSON format
	 *     wp zerospam api_usage --period=week --format=json
	 *
	 *     # Show network-wide usage (multisite)
	 *     wp zerospam api_usage --scope=network
	 *
	 *     # Export usage data as CSV
	 *     wp zerospam api_usage --format=csv > usage.csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function api_usage( $args, $assoc_args ) {
		// Check if monitoring is enabled.
		if ( ! \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled() ) {
			WP_CLI::error( __( 'API usage monitoring is not enabled. Enable it in Zero Spam settings.', 'zero-spam' ) );
		}

		$period  = isset( $assoc_args['period'] ) ? $assoc_args['period'] : 'today';
		$format  = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$scope   = isset( $assoc_args['scope'] ) ? $assoc_args['scope'] : 'site';
		$site_id = isset( $assoc_args['site'] ) ? absint( $assoc_args['site'] ) : get_current_blog_id();

		// Validate period.
		$valid_periods = array( 'today', 'yesterday', 'week', 'month', 'all' );
		if ( ! in_array( $period, $valid_periods, true ) ) {
			WP_CLI::error( sprintf( 'Invalid period. Must be one of: %s', implode( ', ', $valid_periods ) ) );
		}

		// Get statistics.
		if ( 'network' === $scope && is_multisite() ) {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_network_usage_stats( $period );
			WP_CLI::log( WP_CLI::colorize( '%BNetwork-wide API Usage Statistics%n' ) );
		} else {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, $period );
			WP_CLI::log( WP_CLI::colorize( sprintf( '%%BAPI Usage Statistics (Site %d)%%n', $site_id ) ) );
		}

		WP_CLI::log( WP_CLI::colorize( sprintf( '%%yPeriod: %s%%n', ucfirst( $period ) ) ) );
		WP_CLI::log( '' );

		// Format output based on format parameter.
		if ( 'table' === $format ) {
			$this->display_usage_table( $stats );

			// Show anomalies if site-level.
			if ( 'site' === $scope ) {
				$anomalies = \ZeroSpam\Includes\API_Usage_Tracker::detect_anomalies( $site_id );
				if ( ! empty( $anomalies ) ) {
					WP_CLI::log( '' );
					WP_CLI::log( WP_CLI::colorize( '%R⚠ Anomalies Detected:%n' ) );
					foreach ( $anomalies as $anomaly ) {
						$icon = 'critical' === $anomaly['severity'] ? '✗' : '⚠';
						WP_CLI::log( WP_CLI::colorize( sprintf( '  %%y%s %s%%n', $icon, $anomaly['message'] ) ) );
					}
				}
			}
		} else {
			// For JSON/CSV/YAML formats.
			$output_data = array(
				array(
					'metric' => 'Total Events',
					'value'  => $stats['total_events'],
				),
				array(
					'metric' => 'API Calls',
					'value'  => $stats['api_calls'],
				),
				array(
					'metric' => 'Cache Hits',
					'value'  => $stats['cache_hits'],
				),
				array(
					'metric' => 'Errors',
					'value'  => $stats['errors'],
				),
				array(
					'metric' => 'Avg Response Time (ms)',
					'value'  => round( $stats['avg_response_time'], 2 ),
				),
			);

			if ( $stats['current_limit'] ) {
				$output_data[] = array(
					'metric' => 'Quota Limit',
					'value'  => $stats['current_limit'],
				);
				$output_data[] = array(
					'metric' => 'Quota Used',
					'value'  => $stats['current_made'],
				);
				$output_data[] = array(
					'metric' => 'Quota Remaining',
					'value'  => $stats['current_remaining'],
				);
			}

			WP_CLI\Utils\format_items( $format, $output_data, array( 'metric', 'value' ) );
		}
	}

	/**
	 * Display usage statistics in table format
	 *
	 * @param array $stats Statistics data.
	 */
	private function display_usage_table( $stats ) {
		// Statistics table.
		WP_CLI::log( WP_CLI::colorize( '%BUsage Statistics:%n' ) );
		WP_CLI::log( sprintf( '  API Calls:          %s', WP_CLI::colorize( '%G' . number_format( $stats['api_calls'] ) . '%n' ) ) );
		WP_CLI::log( sprintf( '  Cache Hits:         %s', WP_CLI::colorize( '%G' . number_format( $stats['cache_hits'] ) . '%n' ) ) );
		WP_CLI::log( sprintf( '  Errors:             %s', $stats['errors'] > 0 ? WP_CLI::colorize( '%R' . number_format( $stats['errors'] ) . '%n' ) : '0' ) );
		WP_CLI::log( sprintf( '  Total Events:       %s', number_format( $stats['total_events'] ) ) );

		if ( $stats['avg_response_time'] > 0 ) {
			$response_color = $stats['avg_response_time'] > 5000 ? '%R' : ( $stats['avg_response_time'] > 2000 ? '%Y' : '%G' );
			WP_CLI::log( sprintf( '  Avg Response Time:  %s', WP_CLI::colorize( $response_color . round( $stats['avg_response_time'], 2 ) . 'ms%n' ) ) );
		}

		// Quota information.
		if ( $stats['current_limit'] ) {
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%BAPI Quota:%n' ) );
			WP_CLI::log( sprintf( '  Limit:              %s', number_format( $stats['current_limit'] ) ) );
			WP_CLI::log( sprintf( '  Used:               %s', number_format( $stats['current_made'] ) ) );

			$remaining_pct   = ( $stats['current_remaining'] / $stats['current_limit'] ) * 100;
			$remaining_color = $remaining_pct < 10 ? '%R' : ( $remaining_pct < 20 ? '%Y' : '%G' );
			WP_CLI::log(
				sprintf(
					'  Remaining:          %s (%s)',
					WP_CLI::colorize( $remaining_color . number_format( $stats['current_remaining'] ) . '%n' ),
					WP_CLI::colorize( $remaining_color . number_format( $remaining_pct, 1 ) . '%%%n' )
				)
			);
		}

		// Performance indicators.
		$total_requests = $stats['api_calls'] + $stats['cache_hits'];
		if ( $total_requests > 0 ) {
			$cache_hit_rate = ( $stats['cache_hits'] / $total_requests ) * 100;
			$cache_color    = $cache_hit_rate >= 70 ? '%G' : ( $cache_hit_rate >= 50 ? '%Y' : '%R' );

			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%BPerformance:%n' ) );
			WP_CLI::log(
				sprintf(
					'  Cache Efficiency:   %s',
					WP_CLI::colorize( $cache_color . number_format( $cache_hit_rate, 1 ) . '%%%n' )
				)
			);

			if ( $stats['errors'] > 0 ) {
				$error_rate  = ( $stats['errors'] / $stats['total_events'] ) * 100;
				$error_color = $error_rate >= 10 ? '%R' : ( $error_rate >= 5 ? '%Y' : '%G' );
				WP_CLI::log(
					sprintf(
						'  Error Rate:         %s',
						WP_CLI::colorize( $error_color . number_format( $error_rate, 1 ) . '%%%n' )
					)
				);
			}
		}
	}
}

WP_CLI::add_command( 'zerospam', 'ZeroSpamCLI' );
