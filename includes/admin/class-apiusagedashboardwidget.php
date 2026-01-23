<?php
/**
 * API Usage Dashboard Widget
 *
 * Displays API usage statistics on the WordPress dashboard.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Dashboard Widget class
 */
class API_Usage_Dashboard_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_zerospam_refresh_api_usage', array( $this, 'ajax_refresh_usage' ) );
	}

	/**
	 * Register the dashboard widget
	 */
	public function register_widget() {
		// Only show if monitoring is enabled.
		if ( ! \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled() ) {
			return;
		}

		// Check Screen Options visibility setting.
		$settings      = \ZeroSpam\Core\Settings::get_settings();
		$visible_roles = ! empty( $settings['widget_visibility']['value'] ) ? $settings['widget_visibility']['value'] : array( 'administrator' );

		// Check if current user has any of the allowed roles.
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

		// Register widget for network admin or site admin based on permissions.
		if ( is_multisite() && is_network_admin() && current_user_can( 'manage_network_options' ) ) {
			wp_add_dashboard_widget(
				'zerospam_api_usage_widget',
				__( 'Zero Spam API Usage (Network-Wide)', 'zero-spam' ),
				array( $this, 'render_widget' )
			);
		} elseif ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget(
				'zerospam_api_usage_widget',
				__( 'Zero Spam API Usage', 'zero-spam' ),
				array( $this, 'render_widget' )
			);
		}
	}

	/**
	 * Enqueue widget assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only on dashboard.
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Only if monitoring is enabled.
		if ( ! \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled() ) {
			return;
		}

		wp_enqueue_style(
			'zerospam-api-widget',
			plugin_dir_url( ZEROSPAM ) . 'assets/css/api-widget.css',
			array(),
			ZEROSPAM_VERSION
		);

		wp_enqueue_script(
			'zerospam-api-widget',
			plugin_dir_url( ZEROSPAM ) . 'assets/js/api-widget.js',
			array( 'jquery' ),
			ZEROSPAM_VERSION,
			true
		);

		wp_localize_script(
			'zerospam-api-widget',
			'zerospamApiWidget',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'zerospam_api_usage_refresh' ),
			)
		);
	}

	/**
	 * Render the dashboard widget
	 */
	public function render_widget() {
		$is_network = is_multisite() && is_network_admin();
		$site_id    = get_current_blog_id();

		// Get statistics.
		if ( $is_network ) {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_network_usage_stats( 'today' );
		} else {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, 'today' );
		}

		// Get hourly data for chart.
		$hourly_data = \ZeroSpam\Includes\API_Usage_Tracker::get_hourly_usage( $site_id, 'today' );

		// Calculate percentages.
		$quota_used_pct = 0;
		$quota_status   = 'unknown';

		if ( $stats['current_limit'] && $stats['current_made'] ) {
			$quota_used_pct = ( $stats['current_made'] / $stats['current_limit'] ) * 100;

			if ( $quota_used_pct >= 90 ) {
				$quota_status = 'critical';
			} elseif ( $quota_used_pct >= 80 ) {
				$quota_status = 'warning';
			} else {
				$quota_status = 'good';
			}
		}

		// Cache efficiency.
		$total_requests = $stats['api_calls'] + $stats['cache_hits'];
		$cache_hit_rate = $total_requests > 0 ? ( $stats['cache_hits'] / $total_requests ) * 100 : 0;

		// Error rate.
		$error_rate = $stats['total_events'] > 0 ? ( $stats['errors'] / $stats['total_events'] ) * 100 : 0;

		// Get anomalies.
		if ( $is_network ) {
			$anomalies = array(); // Network anomalies are handled separately.
		} else {
			$anomalies = \ZeroSpam\Includes\API_Usage_Tracker::detect_anomalies( $site_id );
		}

		// Get cache timestamp.
		$cache_key = $is_network ? 'zerospam_network_usage_stats_today' : "zerospam_usage_stats_{$site_id}_today";
		$cached    = get_transient( $cache_key );
		$cache_age = $cached ? __( 'Cached', 'zero-spam' ) : __( 'Live', 'zero-spam' );

		?>
		<div class="zerospam-api-usage-widget">
			<!-- Header with Refresh -->
			<div class="widget-header">
				<div class="last-updated">
					<span class="cache-status"><?php echo esc_html( $cache_age ); ?></span>
					<button type="button" class="button button-small refresh-usage" aria-label="<?php esc_attr_e( 'Refresh Usage Data', 'zero-spam' ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'zero-spam' ); ?>
					</button>
				</div>
			</div>

			<!-- Quota Meter -->
			<?php if ( $stats['current_limit'] ) : ?>
				<div class="quota-section">
					<h3><?php esc_html_e( 'API Quota', 'zero-spam' ); ?></h3>
					<div class="quota-meter <?php echo esc_attr( $quota_status ); ?>">
						<div class="quota-bar">
							<div class="quota-fill" style="width: <?php echo esc_attr( min( $quota_used_pct, 100 ) ); ?>%"></div>
						</div>
						<div class="quota-labels">
							<span class="quota-used">
								<?php
								printf(
									/* translators: 1: used queries, 2: total queries */
									esc_html__( '%1$s / %2$s used', 'zero-spam' ),
									'<strong>' . esc_html( number_format( $stats['current_made'] ) ) . '</strong>',
									esc_html( number_format( $stats['current_limit'] ) )
								);
								?>
							</span>
							<span class="quota-remaining">
								<?php
								printf(
									/* translators: %s: remaining queries */
									esc_html__( '%s remaining', 'zero-spam' ),
									'<strong>' . esc_html( number_format( $stats['current_remaining'] ) ) . '</strong>'
								);
								?>
							</span>
						</div>
					</div>
					<div class="quota-percentage <?php echo esc_attr( $quota_status ); ?>">
						<?php
						printf(
							/* translators: %s: percentage used */
							esc_html__( '%s%% of quota used', 'zero-spam' ),
							esc_html( number_format( $quota_used_pct, 1 ) )
						);
						?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Statistics Grid -->
			<div class="stats-grid">
				<div class="stat-box">
					<div class="stat-value"><?php echo esc_html( number_format( $stats['api_calls'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'API Calls', 'zero-spam' ); ?></div>
				</div>
				<div class="stat-box">
					<div class="stat-value"><?php echo esc_html( number_format( $stats['cache_hits'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Cache Hits', 'zero-spam' ); ?></div>
				</div>
				<div class="stat-box <?php echo $stats['errors'] > 0 ? 'has-errors' : ''; ?>">
					<div class="stat-value"><?php echo esc_html( number_format( $stats['errors'] ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Errors', 'zero-spam' ); ?></div>
				</div>
				<?php if ( $stats['avg_response_time'] > 0 ) : ?>
					<div class="stat-box">
						<div class="stat-value"><?php echo esc_html( number_format( $stats['avg_response_time'] ) ); ?>ms</div>
						<div class="stat-label"><?php esc_html_e( 'Avg Response', 'zero-spam' ); ?></div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Performance Indicators -->
			<div class="performance-indicators">
				<div class="indicator cache-efficiency <?php echo $cache_hit_rate >= 70 ? 'good' : ( $cache_hit_rate >= 50 ? 'warning' : 'poor' ); ?>">
					<span class="indicator-label"><?php esc_html_e( 'Cache Efficiency:', 'zero-spam' ); ?></span>
					<span class="indicator-value"><?php echo esc_html( number_format( $cache_hit_rate, 1 ) ); ?>%</span>
				</div>
				<?php if ( $stats['errors'] > 0 ) : ?>
					<div class="indicator error-rate <?php echo $error_rate >= 10 ? 'critical' : ( $error_rate >= 5 ? 'warning' : 'good' ); ?>">
						<span class="indicator-label"><?php esc_html_e( 'Error Rate:', 'zero-spam' ); ?></span>
						<span class="indicator-value"><?php echo esc_html( number_format( $error_rate, 1 ) ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>

			<!-- Anomalies Alert -->
			<?php if ( ! empty( $anomalies ) ) : ?>
				<div class="anomalies-section">
					<h4><?php esc_html_e( 'Alerts Detected', 'zero-spam' ); ?></h4>
					<?php foreach ( $anomalies as $anomaly ) : ?>
						<div class="anomaly-item <?php echo esc_attr( $anomaly['severity'] ); ?>">
							<span class="dashicons dashicons-<?php echo 'critical' === $anomaly['severity'] ? 'warning' : 'info'; ?>"></span>
							<?php echo esc_html( $anomaly['message'] ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Hourly Chart -->
			<?php if ( ! empty( $hourly_data ) ) : ?>
				<div class="hourly-chart-section">
					<h4><?php esc_html_e( 'Today\'s Activity', 'zero-spam' ); ?></h4>
					<div class="hourly-chart">
						<?php
						$max_value = 0;
						foreach ( $hourly_data as $hour ) {
							$max_value = max( $max_value, $hour['api_calls'] + $hour['cache_hits'] );
						}
						$max_value = max( $max_value, 1 ); // Avoid division by zero.

						foreach ( $hourly_data as $hour ) :
							$total_height = ( ( $hour['api_calls'] + $hour['cache_hits'] ) / $max_value ) * 100;
							$api_height   = $max_value > 0 ? ( $hour['api_calls'] / $max_value ) * 100 : 0;
							$cache_height = $max_value > 0 ? ( $hour['cache_hits'] / $max_value ) * 100 : 0;
							$hour_label   = gmdate( 'ga', strtotime( $hour['hour'] ) );
							?>
							<div class="chart-bar" title="<?php echo esc_attr( $hour_label . ': ' . ( $hour['api_calls'] + $hour['cache_hits'] ) . ' total' ); ?>">
								<div class="bar-stack">
									<?php if ( $hour['api_calls'] > 0 ) : ?>
										<div class="bar-segment api-calls" style="height: <?php echo esc_attr( $api_height ); ?>%"></div>
									<?php endif; ?>
									<?php if ( $hour['cache_hits'] > 0 ) : ?>
										<div class="bar-segment cache-hits" style="height: <?php echo esc_attr( $cache_height ); ?>%"></div>
									<?php endif; ?>
								</div>
								<div class="bar-label"><?php echo esc_html( $hour_label ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="chart-legend">
						<span class="legend-item"><span class="legend-color api-calls"></span> <?php esc_html_e( 'API Calls', 'zero-spam' ); ?></span>
						<span class="legend-item"><span class="legend-color cache-hits"></span> <?php esc_html_e( 'Cache Hits', 'zero-spam' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<!-- Footer with Settings Link -->
			<div class="widget-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure Monitoring', 'zero-spam' ); ?>
				</a>
				<?php if ( $is_network ) : ?>
					<p class="network-note">
						<?php
						printf(
							/* translators: %d: number of sites */
							esc_html__( 'Showing network-wide usage across %d sites', 'zero-spam' ),
							esc_html( $stats['total_sites'] )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for refreshing usage data
	 */
	public function ajax_refresh_usage() {
		check_ajax_referer( 'zerospam_api_usage_refresh', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'zero-spam' ) ) );
		}

		$is_network = isset( $_POST['is_network'] ) && '1' === $_POST['is_network'];
		$site_id    = get_current_blog_id();

		// Clear cache.
		\ZeroSpam\Includes\API_Usage_Tracker::clear_usage_cache( $site_id );

		if ( $is_network ) {
			// Clear network cache too.
			$periods = array( 'today', 'yesterday', 'week', 'month', 'all' );
			foreach ( $periods as $period ) {
				delete_transient( "zerospam_network_usage_stats_{$period}" );
			}
		}

		// Return fresh data (will be fetched without cache).
		if ( $is_network ) {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_network_usage_stats( 'today' );
		} else {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, 'today' );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Usage data refreshed', 'zero-spam' ),
				'stats'   => $stats,
			)
		);
	}
}
