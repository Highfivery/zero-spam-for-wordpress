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

		// Clear widget cache when settings are saved.
		add_action( 'update_option_zero-spam-settings', array( $this, 'clear_widget_cache' ) );
	}

	/**
	 * Determine if the current user has access to the dashboard widget.
	 *
	 * Checks the widget_enabled toggle first, then verifies the current user's
	 * roles against the widget_visibility setting. Super admins on multisite
	 * always have access when the widget is enabled.
	 *
	 * @since 5.7.9
	 *
	 * @return bool Whether the current user can see the widget.
	 */
	private function has_widget_access() {
		/**
		 * Filters whether the dashboard widget is visible to the current user.
		 *
		 * Returning a non-null value from this filter will short-circuit
		 * the built-in role checks entirely.
		 *
		 * @since 5.7.9
		 *
		 * @param bool|null $has_access Null to use default logic, or bool to override.
		 */
		$filtered = apply_filters( 'zerospam_dashboard_widget_visible', null );

		if ( null !== $filtered ) {
			return (bool) $filtered;
		}

		$settings = \ZeroSpam\Core\Settings::get_settings();

		// Check master toggle first.
		if ( empty( $settings['widget_enabled']['value'] ) || 'enabled' !== $settings['widget_enabled']['value'] ) {
			return false;
		}

		// Super admins always have access when widget is enabled.
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		// Determine visible roles.
		$visible_roles = $this->get_visible_roles( $settings );

		if ( empty( $visible_roles ) ) {
			return false;
		}

		$user = wp_get_current_user();

		if ( empty( $user->roles ) || ! is_array( $user->roles ) ) {
			return false;
		}

		return ! empty( array_intersect( $visible_roles, $user->roles ) );
	}

	/**
	 * Parse the widget_visibility setting into a clean array of role slugs.
	 *
	 * @since 5.7.9
	 *
	 * @param array $settings Full plugin settings array.
	 * @return array Role slugs that should see the widget.
	 */
	private function get_visible_roles( $settings ) {
		// Default to administrator when setting has never been configured.
		$default = array( 'administrator' );

		if ( ! isset( $settings['widget_visibility']['value'] ) ) {
			return $default;
		}

		$value = $settings['widget_visibility']['value'];

		// Explicitly saved empty array means "no roles".
		if ( is_array( $value ) ) {
			return $value;
		}

		// Single string value.
		if ( is_string( $value ) && '' !== $value ) {
			return array( $value );
		}

		// false or empty string from a never-configured state.
		if ( false === $value ) {
			return $default;
		}

		return $default;
	}

	/**
	 * Register the dashboard widget.
	 */
	public function register_widget() {
		if ( ! $this->has_widget_access() ) {
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
	 * Enqueue widget assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only on dashboard pages.
		if ( 'index.php' !== $hook || ! $this->has_widget_access() ) {
			return;
		}

		// Enqueue Chart.js 4.x (bundled).
		wp_enqueue_script(
			'zerospam-chartjs',
			plugins_url( 'assets/js/vendor/chart.umd.min.js', ZEROSPAM ),
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
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'zerospam_dashboard_refresh' ),
				'isNetwork' => ( is_multisite() && is_network_admin() ) ? '1' : '0',
				'i18n'      => array(
					'refreshing'  => __( 'Refreshing...', 'zero-spam' ),
					'refreshed'   => __( 'Data refreshed.', 'zero-spam' ),
					'refreshFail' => __( 'Could not refresh data. Please try again.', 'zero-spam' ),
				),
			)
		);
	}

	/**
	 * AJAX handler to refresh dashboard data.
	 *
	 * Clears the transient cache, re-fetches data, and returns it as JSON
	 * so the widget can update in-place without a page reload.
	 */
	public function ajax_refresh_data() {
		check_ajax_referer( 'zerospam_dashboard_refresh', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'zero-spam' ) ) );
		}

		// Clear both transient caches.
		delete_transient( 'zerospam_dashboard_data_site' );
		delete_transient( 'zerospam_dashboard_data_network' );

		// Determine context from the client since is_network_admin() is
		// unreliable during AJAX requests.
		$is_network = is_multisite() && ! empty( $_POST['is_network'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data       = $this->get_dashboard_data( $is_network );

		wp_send_json_success(
			array(
				'data'    => $data,
				'message' => __( 'Dashboard data refreshed.', 'zero-spam' ),
			)
		);
	}

	/**
	 * Clear widget data transients when settings change.
	 *
	 * Hooked to `update_option_zero-spam-settings` so visibility and other
	 * setting changes take effect immediately.
	 *
	 * @since 5.7.9
	 */
	public function clear_widget_cache() {
		delete_transient( 'zerospam_dashboard_data_site' );
		delete_transient( 'zerospam_dashboard_data_network' );
	}

	/**
	 * Render the dashboard widget.
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
						$body         = json_decode( wp_remote_retrieve_body( $response ), true );
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
			extract( // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
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
	 * Get dashboard data (cached).
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
	 * Check if a database table exists.
	 *
	 * @since 5.7.9
	 *
	 * @param \wpdb  $wpdb  WordPress database object.
	 * @param string $table Full table name including prefix.
	 * @return bool
	 */
	private function table_exists( $wpdb, $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Get network-wide dashboard data.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 * @return array Network data.
	 */
	private function get_network_data( $wpdb ) {
		$table = $wpdb->base_prefix . \ZeroSpam\Includes\DB::$tables['log'];

		if ( ! $this->table_exists( $wpdb, $table ) ) {
			return $this->get_empty_network_data( true );
		}

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
			'table_missing' => false,
		);
	}

	/**
	 * Get single site dashboard data.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 * @return array Site data.
	 */
	private function get_site_data( $wpdb ) {
		$table   = $wpdb->prefix . \ZeroSpam\Includes\DB::$tables['log'];
		$blog_id = get_current_blog_id();

		if ( ! $this->table_exists( $wpdb, $table ) ) {
			return $this->get_empty_site_data( true );
		}

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
			'table_missing' => false,
		);
	}

	/**
	 * Return an empty site data structure.
	 *
	 * Used when the log table does not exist or cannot be queried.
	 *
	 * @since 5.7.9
	 *
	 * @param bool $table_missing Whether the table is missing.
	 * @return array
	 */
	private function get_empty_site_data( $table_missing = false ) {
		return array(
			'total_blocked' => 0,
			'unique_ips'    => 0,
			'active_days'   => 0,
			'trend_data'    => array(),
			'spam_types'    => array(),
			'api_usage'     => false,
			'table_missing' => $table_missing,
		);
	}

	/**
	 * Return an empty network data structure.
	 *
	 * Used when the log table does not exist or cannot be queried.
	 *
	 * @since 5.7.9
	 *
	 * @param bool $table_missing Whether the table is missing.
	 * @return array
	 */
	private function get_empty_network_data( $table_missing = false ) {
		return array(
			'total_blocked' => 0,
			'total_sites'   => get_blog_count(),
			'top_sites'     => array(),
			'trend_data'    => array(),
			'api_usage'     => false,
			'table_missing' => $table_missing,
		);
	}

	/**
	 * Get API usage data.
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
