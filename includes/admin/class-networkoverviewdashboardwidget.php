<?php
/**
 * Network Overview Dashboard Widget
 *
 * Combined widget displaying spam statistics, API usage, and network insights.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Overview Dashboard Widget class
 */
class Network_Overview_Dashboard_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_zerospam_refresh_overview', array( $this, 'ajax_refresh_overview' ) );
	}

	/**
	 * Register the dashboard widget
	 */
	public function register_widget() {
		// Only show if multisite with 2+ sites, or API monitoring enabled.
		$show_spam_tab = is_multisite() && count( get_sites( array( 'number' => 2 ) ) ) >= 2;
		$show_api_tab  = \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled();

		if ( ! $show_spam_tab && ! $show_api_tab ) {
			return;
		}

		// Check permissions.
		$user       = wp_get_current_user();
		$has_access = false;

		if ( is_multisite() && is_network_admin() && current_user_can( 'manage_network_options' ) ) {
			$has_access = true;
		} elseif ( current_user_can( 'manage_options' ) ) {
			$has_access = true;
		}

		if ( ! $has_access ) {
			return;
		}

		// Determine widget title.
		$title = is_multisite() && is_network_admin() ? __( 'Zero Spam Network Overview', 'zero-spam' ) : __( 'Zero Spam Overview', 'zero-spam' );

		wp_add_dashboard_widget(
			'zerospam_network_overview_widget',
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
		// Only on dashboard.
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'zerospam-network-widget',
			plugin_dir_url( ZEROSPAM ) . 'assets/css/network-widget.css',
			array(),
			ZEROSPAM_VERSION
		);

		wp_enqueue_script(
			'zerospam-network-widget',
			plugin_dir_url( ZEROSPAM ) . 'assets/js/network-widget.js',
			array( 'jquery' ),
			ZEROSPAM_VERSION,
			true
		);

		wp_localize_script(
			'zerospam-network-widget',
			'zerospamNetworkWidget',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'zerospam_overview_refresh' ),
			)
		);
	}

	/**
	 * Render the dashboard widget
	 */
	public function render_widget() {
		$is_network    = is_multisite() && is_network_admin();
		$show_spam_tab = is_multisite() && count( get_sites( array( 'number' => 2 ) ) ) >= 2;
		$show_api_tab  = \ZeroSpam\Includes\API_Usage_Tracker::is_monitoring_enabled();

		// Determine which tab to show first.
		$default_tab = $show_spam_tab ? 'spam' : ( $show_api_tab ? 'api' : 'spam' );

		?>
		<div class="zerospam-network-overview-widget">
			<!-- Tab Navigation -->
			<?php if ( $show_spam_tab || $show_api_tab ) : ?>
				<div class="widget-tabs">
					<?php if ( $show_spam_tab ) : ?>
						<button class="tab-button <?php echo 'spam' === $default_tab ? 'active' : ''; ?>" data-tab="spam">
							<span class="dashicons dashicons-shield-alt"></span>
							<?php esc_html_e( 'Spam Activity', 'zero-spam' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $show_api_tab ) : ?>
						<button class="tab-button <?php echo 'api' === $default_tab ? 'active' : ''; ?>" data-tab="api">
							<span class="dashicons dashicons-cloud"></span>
							<?php esc_html_e( 'API Usage', 'zero-spam' ); ?>
						</button>
					<?php endif; ?>
					<?php if ( $show_spam_tab && $show_api_tab ) : ?>
						<button class="tab-button" data-tab="combined">
							<span class="dashicons dashicons-chart-bar"></span>
							<?php esc_html_e( 'Combined', 'zero-spam' ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="button button-small refresh-overview" aria-label="<?php esc_attr_e( 'Refresh Data', 'zero-spam' ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'zero-spam' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<!-- Spam Activity Tab -->
			<?php if ( $show_spam_tab ) : ?>
				<div class="tab-content <?php echo 'spam' === $default_tab ? 'active' : ''; ?>" data-tab="spam">
					<?php $this->render_spam_tab( $is_network ); ?>
				</div>
			<?php endif; ?>

			<!-- API Usage Tab -->
			<?php if ( $show_api_tab ) : ?>
				<div class="tab-content <?php echo 'api' === $default_tab ? 'active' : ''; ?>" data-tab="api">
					<?php $this->render_api_tab( $is_network ); ?>
				</div>
			<?php endif; ?>

			<!-- Combined Analysis Tab -->
			<?php if ( $show_spam_tab && $show_api_tab ) : ?>
				<div class="tab-content" data-tab="combined">
					<?php $this->render_combined_tab( $is_network ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render spam activity tab
	 *
	 * @param bool $is_network Whether this is network admin.
	 */
	private function render_spam_tab( $is_network ) {
		if ( $is_network ) {
			$stats = \ZeroSpam\Includes\Network_Stats_Tracker::get_network_stats( 'month' );
			$sites = \ZeroSpam\Includes\Network_Stats_Tracker::get_site_breakdown( 'month', 10, 'spam_count' );
		} else {
			$site_id = get_current_blog_id();
			$stats   = \ZeroSpam\Includes\Network_Stats_Tracker::get_site_stats( $site_id, 'month' );
		}

		?>
		<div class="spam-stats-section">
			<!-- Network Stats -->
			<?php if ( $is_network ) : ?>
				<div class="stats-grid">
					<div class="stat-box">
						<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['total_spam'] ?? 0 ) ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Spam Blocked (30d)', 'zero-spam' ); ?></div>
					</div>
					<div class="stat-box">
						<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['unique_ips'] ?? 0 ) ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Unique IPs', 'zero-spam' ); ?></div>
					</div>
					<div class="stat-box">
						<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['spam_types'] ?? 0 ) ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Spam Types', 'zero-spam' ); ?></div>
					</div>
				</div>

				<!-- Top Sites -->
				<?php if ( ! empty( $sites ) ) : ?>
					<div class="top-sites-section">
						<h4><?php esc_html_e( 'Top Sites by Spam', 'zero-spam' ); ?></h4>
						<table class="sites-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Site', 'zero-spam' ); ?></th>
									<th><?php esc_html_e( 'Spam Count', 'zero-spam' ); ?></th>
									<th><?php esc_html_e( 'Trend', 'zero-spam' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $sites, 0, 5 ) as $site ) : ?>
									<tr>
										<td>
									<strong><?php echo esc_html( $site['site_name'] ); ?></strong>
									<?php if ( ! $site['has_enhanced'] ) : ?>
										<span class="badge free"><?php esc_html_e( 'Free', 'zero-spam' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format( (float) ( $site['spam_count'] ?? 0 ) ) ); ?></td>
								<td>
											<?php if ( 'up' === $site['trend']['direction'] ) : ?>
												<span class="trend up">↑ <?php echo esc_html( abs( $site['trend']['percentage'] ) ); ?>%</span>
											<?php elseif ( 'down' === $site['trend']['direction'] ) : ?>
												<span class="trend down">↓ <?php echo esc_html( abs( $site['trend']['percentage'] ) ); ?>%</span>
											<?php else : ?>
												<span class="trend neutral">—</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			<?php else : ?>
			<!-- Single Site Stats -->
			<div class="stats-grid">
				<div class="stat-box">
					<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['total_spam'] ?? 0 ) ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Spam Blocked (30d)', 'zero-spam' ); ?></div>
				</div>
				<div class="stat-box">
					<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['unique_ips'] ?? 0 ) ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Unique IPs', 'zero-spam' ); ?></div>
				</div>
				</div>

				<?php if ( 'neutral' !== $stats['trend']['direction'] ) : ?>
					<div class="trend-indicator <?php echo esc_attr( $stats['trend']['direction'] ); ?>">
						<?php if ( 'up' === $stats['trend']['direction'] ) : ?>
							<?php
							printf(
								/* translators: %s: percentage increase */
								esc_html__( '↑ %s%% increase from last period', 'zero-spam' ),
								esc_html( abs( $stats['trend']['percentage'] ) )
							);
							?>
						<?php else : ?>
							<?php
							printf(
								/* translators: %s: percentage decrease */
								esc_html__( '↓ %s%% decrease from last period', 'zero-spam' ),
								esc_html( abs( $stats['trend']['percentage'] ) )
							);
							?>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Footer -->
			<div class="tab-footer">
				<?php if ( $is_network ) : ?>
					<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wordpress-zero-spam-network-stats' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View Full Statistics', 'zero-spam' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View Settings', 'zero-spam' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render API usage tab
	 *
	 * @param bool $is_network Whether this is network admin.
	 */
	private function render_api_tab( $is_network ) {
		$site_id = get_current_blog_id();

		// Get statistics.
		if ( $is_network ) {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_network_usage_stats( 'today' );
		} else {
			$stats = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, 'today' );
		}

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

		?>
		<div class="api-usage-section">
			<!-- Quota Meter -->
			<?php if ( $stats['current_limit'] ) : ?>
				<div class="quota-section">
					<h3><?php esc_html_e( 'API Quota (Today)', 'zero-spam' ); ?></h3>
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
								'<strong>' . esc_html( number_format( (float) ( $stats['current_made'] ?? 0 ) ) ) . '</strong>',
								esc_html( number_format( (float) ( $stats['current_limit'] ?? 0 ) ) )
							);
							?>
						</span>
						<span class="quota-remaining">
							<?php
							printf(
								/* translators: %s: remaining queries */
								esc_html__( '%s remaining', 'zero-spam' ),
								'<strong>' . esc_html( number_format( (float) ( $stats['current_remaining'] ?? 0 ) ) ) . '</strong>'
								);
								?>
							</span>
						</div>
					</div>
				</div>
			<?php endif; ?>

		<!-- Statistics Grid -->
		<div class="stats-grid">
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['api_calls'] ?? 0 ) ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'API Calls', 'zero-spam' ); ?></div>
			</div>
			<div class="stat-box">
				<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['cache_hits'] ?? 0 ) ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Cache Hits', 'zero-spam' ); ?></div>
			</div>
			<div class="stat-box <?php echo $stats['errors'] > 0 ? 'has-errors' : ''; ?>">
				<div class="stat-value"><?php echo esc_html( number_format( (float) ( $stats['errors'] ?? 0 ) ) ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Errors', 'zero-spam' ); ?></div>
			</div>
		</div>

			<!-- Performance Indicators -->
			<div class="performance-indicators">
				<div class="indicator cache-efficiency <?php echo $cache_hit_rate >= 70 ? 'good' : ( $cache_hit_rate >= 50 ? 'warning' : 'poor' ); ?>">
					<span class="indicator-label"><?php esc_html_e( 'Cache Efficiency:', 'zero-spam' ); ?></span>
					<span class="indicator-value"><?php echo esc_html( number_format( $cache_hit_rate, 1 ) ); ?>%</span>
				</div>
			</div>

			<!-- Footer -->
			<div class="tab-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure API Monitoring', 'zero-spam' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render combined analysis tab
	 *
	 * @param bool $is_network Whether this is network admin.
	 */
	private function render_combined_tab( $is_network ) {
		$site_id = get_current_blog_id();

		// Get both datasets.
		if ( $is_network ) {
			$spam_stats = \ZeroSpam\Includes\Network_Stats_Tracker::get_network_stats( 'week' );
			$api_stats  = \ZeroSpam\Includes\API_Usage_Tracker::get_network_usage_stats( 'week' );
		} else {
			$spam_stats = \ZeroSpam\Includes\Network_Stats_Tracker::get_site_stats( $site_id, 'week' );
			$api_stats  = \ZeroSpam\Includes\API_Usage_Tracker::get_usage_stats( $site_id, 'week' );
		}

		?>
		<div class="combined-analysis-section">
			<h3><?php esc_html_e( 'Weekly Summary', 'zero-spam' ); ?></h3>

		<div class="combined-stats">
			<div class="combined-stat-box spam">
				<div class="stat-icon"><span class="dashicons dashicons-shield-alt"></span></div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( (float) ( $spam_stats['total_spam'] ?? 0 ) ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Spam Blocked', 'zero-spam' ); ?></div>
				</div>
			</div>
			<div class="combined-stat-box api">
				<div class="stat-icon"><span class="dashicons dashicons-cloud"></span></div>
				<div class="stat-content">
					<div class="stat-value"><?php echo esc_html( number_format( (float) ( $api_stats['api_calls'] ?? 0 ) ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'API Calls Made', 'zero-spam' ); ?></div>
				</div>
			</div>
			</div>

			<!-- Insights -->
			<div class="insights-section">
				<h4><?php esc_html_e( 'Insights', 'zero-spam' ); ?></h4>
				<ul class="insights-list">
					<?php if ( $spam_stats['total_spam'] > 0 ) : ?>
						<li class="insight-item">
							<span class="dashicons dashicons-yes-alt"></span>
						<?php
						printf(
							/* translators: %s: spam count */
							esc_html__( 'Blocked %s spam attempts this week', 'zero-spam' ),
							'<strong>' . esc_html( number_format( (float) ( $spam_stats['total_spam'] ?? 0 ) ) ) . '</strong>'
						);
						?>
						</li>
					<?php endif; ?>
					<?php if ( $api_stats['api_calls'] > 0 ) : ?>
						<li class="insight-item">
							<span class="dashicons dashicons-cloud"></span>
							<?php
							$cache_rate = ( $api_stats['cache_hits'] / ( $api_stats['api_calls'] + $api_stats['cache_hits'] ) ) * 100;
							printf(
								/* translators: %s: cache percentage */
								esc_html__( 'Cache efficiency at %s%%', 'zero-spam' ),
								'<strong>' . esc_html( number_format( $cache_rate, 1 ) ) . '</strong>'
							);
							?>
						</li>
					<?php endif; ?>
				</ul>
			</div>

			<!-- Footer -->
			<div class="tab-footer">
				<?php if ( $is_network ) : ?>
					<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=wordpress-zero-spam-network-stats' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'View Detailed Reports', 'zero-spam' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for refreshing overview data
	 */
	public function ajax_refresh_overview() {
		check_ajax_referer( 'zerospam_overview_refresh', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'zero-spam' ) ) );
		}

		// Clear all caches.
		\ZeroSpam\Includes\API_Usage_Tracker::clear_usage_cache( get_current_blog_id() );
		\ZeroSpam\Includes\Network_Stats_Tracker::clear_cache();

		wp_send_json_success(
			array(
				'message' => __( 'Data refreshed successfully', 'zero-spam' ),
			)
		);
	}
}
