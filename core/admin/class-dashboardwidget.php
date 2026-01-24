<?php
/**
 * Unified Dashboard Widget
 *
 * Intelligently adapts based on context (network admin vs single site, API monitoring status).
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Dashboard Widget class
 */
class Dashboard_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into both regular and network admin dashboard setup.
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ), 10 );
		add_action( 'wp_network_dashboard_setup', array( $this, 'register_widget' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_zerospam_refresh_dashboard', array( $this, 'ajax_refresh_data' ) );
	}

	/**
	 * Register the dashboard widget
	 */
	public function register_widget() {
		// Check visibility permissions.
		$settings      = \ZeroSpam\Core\Settings::get_settings();
		$visible_roles = ! empty( $settings['widget_visibility']['value'] ) ? $settings['widget_visibility']['value'] : array( 'administrator' );

		$user       = wp_get_current_user();
		$has_access = false;

		if ( is_array( $visible_roles ) ) {
			foreach ( $visible_roles as $role ) {
				if ( in_array( $role, $user->roles, true ) ) {
					$has_access = true;
					break;
				}
			}
		}

		if ( ! $has_access ) {
			return;
		}

		// Determine widget title based on context.
		$is_network = is_multisite() && is_network_admin();
		$title      = $is_network ? __( 'Zero Spam Network Overview', 'zero-spam' ) : __( 'Zero Spam Overview', 'zero-spam' );

		wp_add_dashboard_widget(
			'zerospam_unified_widget',
			$title,
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Enqueue widget assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only on dashboard pages (index.php for regular admin, index.php for network admin).
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Check if user has access to widget.
		$settings      = \ZeroSpam\Core\Settings::get_settings();
		$visible_roles = ! empty( $settings['widget_visibility']['value'] ) ? $settings['widget_visibility']['value'] : array( 'administrator' );
		$user          = wp_get_current_user();
		$has_access    = false;

		if ( is_array( $visible_roles ) ) {
			foreach ( $visible_roles as $role ) {
				if ( in_array( $role, $user->roles, true ) ) {
					$has_access = true;
					break;
				}
			}
		}

		if ( ! $has_access ) {
			return;
		}

		// Enqueue Chart.js 4.x from CDN.
		wp_enqueue_script(
			'zerospam-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);

		// Enqueue widget styles.
		wp_enqueue_style(
			'zerospam-dashboard-widget',
			plugins_url( 'assets/css/unified-dashboard-widget.css', ZEROSPAM ),
			array(),
			ZEROSPAM_VERSION
		);

		// Enqueue widget JavaScript.
		wp_enqueue_script(
			'zerospam-dashboard-widget',
			plugins_url( 'assets/js/unified-dashboard-widget.js', ZEROSPAM ),
			array( 'jquery', 'zerospam-chartjs' ),
			ZEROSPAM_VERSION,
			true
		);

		// Localize script for AJAX.
		wp_localize_script(
			'zerospam-dashboard-widget',
			'zerospamDashboard',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'zerospam_dashboard_refresh' ),
			)
		);
	}

	/**
	 * AJAX handler to refresh dashboard data
	 */
	public function ajax_refresh_data() {
		check_ajax_referer( 'zerospam_dashboard_refresh', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'zero-spam' ) ) );
		}

		// Delete transient to force refresh.
		delete_transient( 'zerospam_dashboard_data' );

		wp_send_json_success( array( 'message' => __( 'Data refreshed. Reload the page to see updates.', 'zero-spam' ) ) );
	}

	/**
	 * Render the dashboard widget
	 */
	public function render_widget() {
		try {
			$is_network      = is_multisite() && is_network_admin();
			$api_monitoring  = \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled();
			$settings        = \ZeroSpam\Core\Settings::get_settings();
			$zerospam_enabled = isset( $settings['zerospam']['value'] ) && 'enabled' === $settings['zerospam']['value'];
			
			// Check for license key.
			$zerospam_license = false;
			if ( defined( 'ZEROSPAM_LICENSE_KEY' ) && ZEROSPAM_LICENSE_KEY ) {
				$zerospam_license = ZEROSPAM_LICENSE_KEY;
			} elseif ( ! empty( $settings['zerospam_license']['value'] ) ) {
				$zerospam_license = $settings['zerospam_license']['value'];
			}

			// Validate license.
			$license_valid          = false;
			$license_status_message = '';

			if ( $zerospam_license && function_exists( 'wp_remote_get' ) ) {
				// Check cache first (12 hour cache).
				$cache_key    = 'zerospam_license_check_' . md5( $zerospam_license );
				$license_data = get_transient( $cache_key );

				if ( false === $license_data ) {
					// Make API call.
					$response = wp_remote_get(
						'https://www.zerospam.org/?wpserviceapi=license-check&license_key=' . rawurlencode( $zerospam_license ),
						array( 'timeout' => 5 )
					);

					if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
						$body = json_decode( wp_remote_retrieve_body( $response ), true );
						$license_data = array(
							'valid'   => ! empty( $body['success'] ) && true === $body['success'],
							'message' => ! empty( $body['message'] ) ? $body['message'] : '',
						);
						// Cache for 12 hours.
						set_transient( $cache_key, $license_data, 12 * HOUR_IN_SECONDS );
					} else {
						// API error - assume valid to avoid blocking users on temporary API issues.
						$license_data = array(
							'valid'   => true,
							'message' => '',
						);
						// Cache for shorter time (15 minutes) on errors.
						set_transient( $cache_key, $license_data, 15 * MINUTE_IN_SECONDS );
					}
				}

				$license_valid          = $license_data['valid'];
				$license_status_message = $license_data['message'];
			}

			// Get spam log data.
			$data = $this->get_dashboard_data( $is_network );

			// Extract data for template.
			extract(
				array(
					'is_network'             => $is_network,
					'api_monitoring'         => $api_monitoring,
					'zerospam_enabled'       => $zerospam_enabled,
					'zerospam_license'       => $zerospam_license,
					'license_valid'          => $license_valid,
					'license_status_message' => $license_status_message,
					'data'                   => $data,
				)
			);

			require ZEROSPAM_PATH . 'includes/templates/unified-dashboard-widget.php';
		} catch ( \Exception $e ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( sprintf( __( 'Error loading Zero Spam dashboard widget: %s', 'zero-spam' ), $e->getMessage() ) );
			echo '</p></div>';
		}
	}

	/**
	 * Get dashboard data (cached)
	 *
	 * @param bool $is_network Whether this is network admin context.
	 * @return array Dashboard data.
	 */
	private function get_dashboard_data( $is_network ) {
		$cache_key = $is_network ? 'zerospam_dashboard_data_network' : 'zerospam_dashboard_data_site';
		$data      = get_transient( $cache_key );

		if ( false !== $data ) {
			return $data;
		}

		global $wpdb;

		if ( $is_network ) {
			$data = $this->get_network_data( $wpdb );
		} else {
			$data = $this->get_site_data( $wpdb );
		}

		// Cache for 5 minutes.
		set_transient( $cache_key, $data, 5 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Get network-wide dashboard data
	 *
	 * @param wpdb $wpdb WordPress database object.
	 * @return array Network data.
	 */
	private function get_network_data( $wpdb ) {
		$table = $wpdb->base_prefix . 'zerospam_log';

		// Total spam blocked across network.
		$total_blocked = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Total sites.
		$total_sites = get_blog_count();

		// Top 10 sites by spam volume.
		$top_sites = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT blog_id, COUNT(*) as spam_count 
			FROM {$table} 
			GROUP BY blog_id 
			ORDER BY spam_count DESC 
			LIMIT 10",
			ARRAY_A
		);

		// Add site names.
		foreach ( $top_sites as &$site ) {
			$site_details      = get_blog_details( $site['blog_id'] );
			$site['site_name'] = $site_details ? $site_details->blogname : __( 'Unknown Site', 'zero-spam' );
			$site['site_url']  = $site_details ? get_admin_url( $site['blog_id'] ) : '';
		}

		// 30-day trend.
		$trend_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT DATE(date_recorded) as date, COUNT(*) as count 
				FROM {$table} 
				WHERE date_recorded >= %s 
				GROUP BY DATE(date_recorded) 
				ORDER BY date ASC",
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			),
			ARRAY_A
		);

		// API usage (if monitoring enabled).
		$api_usage = $this->get_api_usage_data( true );

		return array(
			'total_blocked' => $total_blocked,
			'total_sites'   => $total_sites,
			'top_sites'     => $top_sites,
			'trend_data'    => $trend_data,
			'api_usage'     => $api_usage,
		);
	}

	/**
	 * Get single site dashboard data
	 *
	 * @param wpdb $wpdb WordPress database object.
	 * @return array Site data.
	 */
	private function get_site_data( $wpdb ) {
		$table   = $wpdb->prefix . 'zerospam_log';
		$blog_id = get_current_blog_id();

		// Total spam blocked.
		$total_blocked = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE blog_id = %d",
				$blog_id
			)
		);

		// Unique IPs.
		$unique_ips = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_ip) FROM {$table} WHERE blog_id = %d",
				$blog_id
			)
		);

		// Active days (last 30).
		$active_days = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT DATE(date_recorded)) 
				FROM {$table} 
				WHERE blog_id = %d 
				AND date_recorded >= %s",
				$blog_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			)
		);

		// 30-day trend.
		$trend_data = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT DATE(date_recorded) as date, COUNT(*) as count 
				FROM {$table} 
				WHERE blog_id = %d 
				AND date_recorded >= %s 
				GROUP BY DATE(date_recorded) 
				ORDER BY date ASC",
				$blog_id,
				gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
			),
			ARRAY_A
		);

		// Spam types breakdown.
		$spam_types = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT log_type, COUNT(*) as count 
				FROM {$table} 
				WHERE blog_id = %d 
				GROUP BY log_type 
				ORDER BY count DESC 
				LIMIT 8",
				$blog_id
			),
			ARRAY_A
		);

		// API usage (if monitoring enabled).
		$api_usage = $this->get_api_usage_data( false );

		return array(
			'total_blocked' => $total_blocked,
			'unique_ips'    => $unique_ips,
			'active_days'   => $active_days,
			'trend_data'    => $trend_data,
			'spam_types'    => $spam_types,
			'api_usage'     => $api_usage,
		);
	}

	/**
	 * Get API usage data
	 *
	 * @param bool $network Whether to get network-wide usage.
	 * @return array|false API usage data or false if not available.
	 */
	private function get_api_usage_data( $network ) {
		// Get license key.
		$license_key = \ZeroSpam\Core\Settings::get_settings( 'zerospam_license' );
		
		if ( ! $license_key ) {
			return false;
		}

		// Use the same method as the settings page header.
		$license = \ZeroSpam\Modules\Zero_Spam::get_license( $license_key );

		if ( empty( $license ) || empty( $license['license_key'] ) ) {
			return false;
		}

		// Extract quota information from license data.
		$queries_limit     = isset( $license['queries_limit'] ) ? (int) $license['queries_limit'] : 0;
		$queries_made      = isset( $license['queries_made'] ) ? (int) $license['queries_made'] : 0;
		$queries_remaining = isset( $license['queries_remaining'] ) ? (int) $license['queries_remaining'] : 0;

		if ( 0 === $queries_limit ) {
			return false;
		}

		// Calculate percentage used.
		$percentage = $queries_limit > 0 ? min( 100, round( ( $queries_made / $queries_limit ) * 100, 1 ) ) : 0;

		// Determine warning level.
		$warning_level = 'normal';
		if ( $percentage >= 90 ) {
			$warning_level = 'critical';
		} elseif ( $percentage >= 80 ) {
			$warning_level = 'warning';
		}

		return array(
			'used'          => $queries_made,
			'remaining'     => $queries_remaining,
			'limit'         => $queries_limit,
			'percentage'    => $percentage,
			'warning_level' => $warning_level,
		);
	}
}
