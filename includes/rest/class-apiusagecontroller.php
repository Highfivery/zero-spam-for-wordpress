<?php
/**
 * API Usage REST Controller
 *
 * Handles REST API endpoints for API usage monitoring.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Rest;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * API Usage Controller class
 */
class API_Usage_Controller extends \WP_REST_Controller {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace = 'zero-spam/v1';

	/**
	 * Rest base
	 *
	 * @var string
	 */
	protected $rest_base = 'api-usage';

	/**
	 * Register routes
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_usage' ),
					'permission_callback' => array( $this, 'get_usage_permissions_check' ),
					'args'                => $this->get_usage_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_usage' ),
					'permission_callback' => array( $this, 'get_usage_permissions_check' ),
					'args'                => $this->get_export_params(),
				),
			)
		);
	}

	/**
	 * Get usage statistics
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_usage( $request ) {
		$period  = $request->get_param( 'period' ) ?: 'today';
		$site_id = $request->get_param( 'site_id' );
		$scope   = $request->get_param( 'scope' ) ?: 'site';

		// For network requests, check network admin permission.
		if ( 'network' === $scope && is_multisite() ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				return new \WP_Error(
					'rest_forbidden',
					__( 'You do not have permission to view network-wide usage.', 'zero-spam' ),
					array( 'status' => 403 )
				);
			}

			$stats     = \ZeroSpam\Includes\API_Usage_Tracker::get_network_usage_stats( $period );
			$hourly    = array(); // Network hourly data not yet implemented.
			$breakdown = \ZeroSpam\Includes\API_Usage_Tracker::get_per_site_breakdown( $period );
		} else {
			// Site-level request.
			if ( null === $site_id ) {
				$site_id = get_current_blog_id();
			}

			// Verify user has access to requested site.
			if ( is_multisite() && $site_id !== get_current_blog_id() ) {
				if ( ! current_user_can( 'manage_network_options' ) ) {
					return new \WP_Error(
						'rest_forbidden',
						__( 'You do not have permission to view usage for other sites.', 'zero-spam' ),
						array( 'status' => 403 )
					);
				}
			}

			$stats     = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, $period );
			$hourly    = \ZeroSpam\Includes\API_Usage_Tracker::get_hourly_usage( $site_id, $period );
			$breakdown = array();
		}

		// Get anomalies if site-level.
		$anomalies = 'site' === $scope ? \ZeroSpam\Includes\API_Usage_Tracker::detect_anomalies( $site_id ) : array();

		return new \WP_REST_Response(
			array(
				'period'    => $period,
				'scope'     => $scope,
				'site_id'   => $site_id,
				'stats'     => $stats,
				'hourly'    => $hourly,
				'anomalies' => $anomalies,
				'breakdown' => $breakdown,
			),
			200
		);
	}

	/**
	 * Export usage data
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|WP_Error Response object or error.
	 */
	public function export_usage( $request ) {
		$format  = $request->get_param( 'format' ) ?: 'json';
		$period  = $request->get_param( 'period' ) ?: 'today';
		$site_id = $request->get_param( 'site_id' ) ?: get_current_blog_id();

		// Get stats.
		$stats  = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, $period );
		$hourly = \ZeroSpam\Includes\API_Usage_Tracker::get_hourly_usage( $site_id, $period );

		if ( 'csv' === $format ) {
			// Return CSV format.
			$csv_data = $this->format_as_csv( $stats, $hourly );

			return new \WP_REST_Response(
				array(
					'format'   => 'csv',
					'filename' => 'zero-spam-api-usage-' . gmdate( 'Y-m-d' ) . '.csv',
					'data'     => $csv_data,
				),
				200
			);
		}

		// Default to JSON.
		return new \WP_REST_Response(
			array(
				'format'  => 'json',
				'period'  => $period,
				'site_id' => $site_id,
				'stats'   => $stats,
				'hourly'  => $hourly,
			),
			200
		);
	}

	/**
	 * Permission check for getting usage
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, error otherwise.
	 */
	public function get_usage_permissions_check( $request ) {
		// Require authentication.
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Authentication required.', 'zero-spam' ),
				array( 'status' => 401 )
			);
		}

		// Check if monitoring is enabled.
		if ( ! \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled() ) {
			return new \WP_Error(
				'rest_monitoring_disabled',
				__( 'API usage monitoring is not enabled.', 'zero-spam' ),
				array( 'status' => 403 )
			);
		}

		// Site-level requires manage_options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view API usage.', 'zero-spam' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get usage endpoint parameters
	 *
	 * @return array Parameters.
	 */
	protected function get_usage_params() {
		return array(
			'period'  => array(
				'description'       => __( 'Time period for statistics (today, yesterday, week, month, all)', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'today', 'yesterday', 'week', 'month', 'all' ),
				'default'           => 'today',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'scope'   => array(
				'description'       => __( 'Scope of statistics (site or network)', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'site', 'network' ),
				'default'           => 'site',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'site_id' => array(
				'description'       => __( 'Site ID for multisite installations', 'zero-spam' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get export endpoint parameters
	 *
	 * @return array Parameters.
	 */
	protected function get_export_params() {
		return array(
			'format'  => array(
				'description'       => __( 'Export format (json or csv)', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'json', 'csv' ),
				'default'           => 'json',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'period'  => array(
				'description'       => __( 'Time period for statistics', 'zero-spam' ),
				'type'              => 'string',
				'enum'              => array( 'today', 'yesterday', 'week', 'month', 'all' ),
				'default'           => 'today',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'site_id' => array(
				'description'       => __( 'Site ID for multisite installations', 'zero-spam' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Format data as CSV
	 *
	 * @param array $stats   Statistics data.
	 * @param array $hourly  Hourly data.
	 * @return string CSV data.
	 */
	protected function format_as_csv( $stats, $hourly ) {
		$csv = array();

		// Header row.
		$csv[] = 'Type,Value';

		// Statistics rows.
		$csv[] = 'Total Events,' . $stats['total_events'];
		$csv[] = 'API Calls,' . $stats['api_calls'];
		$csv[] = 'Cache Hits,' . $stats['cache_hits'];
		$csv[] = 'Errors,' . $stats['errors'];
		$csv[] = 'Average Response Time (ms),' . round( $stats['avg_response_time'], 2 );

		if ( $stats['current_limit'] ) {
			$csv[] = 'Quota Limit,' . $stats['current_limit'];
			$csv[] = 'Quota Used,' . $stats['current_made'];
			$csv[] = 'Quota Remaining,' . $stats['current_remaining'];
		}

		// Add blank line.
		$csv[] = '';

		// Hourly data.
		if ( ! empty( $hourly ) ) {
			$csv[] = 'Hour,API Calls,Cache Hits,Errors';

			foreach ( $hourly as $hour ) {
				$csv[] = sprintf(
					'%s,%d,%d,%d',
					$hour['hour'],
					$hour['api_calls'],
					$hour['cache_hits'],
					$hour['errors']
				);
			}
		}

		return implode( "\n", $csv );
	}

	/**
	 * Get the schema for API usage data
	 *
	 * @return array Schema.
	 */
	public function get_public_item_schema() {
		if ( $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'api-usage',
			'type'       => 'object',
			'properties' => array(
				'period'    => array(
					'description' => __( 'Time period', 'zero-spam' ),
					'type'        => 'string',
				),
				'scope'     => array(
					'description' => __( 'Scope (site or network)', 'zero-spam' ),
					'type'        => 'string',
				),
				'site_id'   => array(
					'description' => __( 'Site ID', 'zero-spam' ),
					'type'        => 'integer',
				),
				'stats'     => array(
					'description' => __( 'Usage statistics', 'zero-spam' ),
					'type'        => 'object',
				),
				'hourly'    => array(
					'description' => __( 'Hourly usage data', 'zero-spam' ),
					'type'        => 'array',
				),
				'anomalies' => array(
					'description' => __( 'Detected anomalies', 'zero-spam' ),
					'type'        => 'array',
				),
			),
		);

		return $this->schema;
	}
}
