<?php
/**
 * Unified Dashboard Widget Template
 *
 * @package ZeroSpam
 */

// Security check.
defined( 'ABSPATH' ) || die();

// Variables passed from class-dashboard-widget.php
// $is_network, $api_monitoring, $zerospam_enabled, $zerospam_license, $license_valid, $license_status_message, $data
?>

<div class="zerospam-dashboard-widget">
	
	<?php // License/Status Banners ?>
	<?php if ( ! $zerospam_enabled || ! $zerospam_license ) : ?>
		<div class="zerospam-notice zerospam-notice-promo">
			<div class="zerospam-notice-icon">
				<span class="dashicons dashicons-shield-alt"></span>
			</div>
			<div class="zerospam-notice-content">
				<h4><?php esc_html_e( 'Unlock Enhanced Protection', 'zero-spam' ); ?></h4>
				<p><?php esc_html_e( 'Stop sophisticated spam with real-time global threat intelligence powered by AI.', 'zero-spam' ); ?></p>
				<p>
					<a href="https://www.zerospam.org/pricing/?utm_source=plugin&utm_medium=dashboard&utm_campaign=widget" target="_blank" rel="noopener noreferrer" class="button button-primary button-small">
						<?php esc_html_e( 'View Pricing', 'zero-spam' ); ?>
					</a>
					<a href="https://www.zerospam.org/?utm_source=plugin&utm_medium=dashboard&utm_campaign=widget" target="_blank" rel="noopener noreferrer" class="button button-secondary button-small">
						<?php esc_html_e( 'Learn More', 'zero-spam' ); ?>
					</a>
				</p>
			</div>
		</div>
	<?php elseif ( $zerospam_enabled && $zerospam_license && ! $license_valid ) : ?>
		<div class="zerospam-notice zerospam-notice-<?php echo ! empty( $license_status_message ) ? 'error' : 'info'; ?>">
			<div class="zerospam-notice-icon">
				<span class="dashicons dashicons-<?php echo ! empty( $license_status_message ) ? 'warning' : 'info'; ?>"></span>
			</div>
			<div class="zerospam-notice-content">
				<h4><?php esc_html_e( ! empty( $license_status_message ) ? 'License Issue' : 'Validating License', 'zero-spam' ); ?></h4>
				<p><?php echo esc_html( ! empty( $license_status_message ) ? $license_status_message : __( 'Your license is being verified. This usually takes a few moments.', 'zero-spam' ) ); ?></p>
				<?php if ( ! empty( $license_status_message ) ) : ?>
					<p>
						<a href="https://www.zerospam.org/account/?utm_source=plugin&utm_medium=dashboard&utm_campaign=license_issue" target="_blank" rel="noopener noreferrer" class="button button-small">
							<?php esc_html_e( 'Manage License', 'zero-spam' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php // API Usage Warning ?>
	<?php if ( ! empty( $data['api_usage'] ) && in_array( $data['api_usage']['warning_level'], array( 'warning', 'critical' ), true ) ) : ?>
		<div class="zerospam-notice zerospam-notice-<?php echo 'critical' === $data['api_usage']['warning_level'] ? 'error' : 'warning'; ?>">
			<div class="zerospam-notice-icon">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="zerospam-notice-content">
				<h4><?php esc_html_e( 'API Usage Alert', 'zero-spam' ); ?></h4>
				<p>
					<?php
					printf(
						/* translators: %s: percentage used */
						esc_html__( 'You have used %s of your API quota.', 'zero-spam' ),
						'<strong>' . esc_html( $data['api_usage']['percentage'] ) . '%</strong>'
					);
					?>
					<?php if ( 'critical' === $data['api_usage']['warning_level'] ) : ?>
						<?php esc_html_e( 'Upgrade your plan to avoid service interruption.', 'zero-spam' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<?php // Key Metrics ?>
	<div class="zerospam-stats-grid">
		<?php if ( $is_network ) : ?>
			<div class="zerospam-stat-card">
				<div class="zerospam-stat-icon zerospam-brand">
					<span class="dashicons dashicons-shield"></span>
				</div>
				<div class="zerospam-stat-content">
					<div class="zerospam-stat-value"><?php echo esc_html( number_format( $data['total_blocked'] ) ); ?></div>
					<div class="zerospam-stat-label"><?php esc_html_e( 'Total Spam Blocked', 'zero-spam' ); ?></div>
				</div>
			</div>

			<div class="zerospam-stat-card">
				<div class="zerospam-stat-icon">
					<span class="dashicons dashicons-admin-multisite"></span>
				</div>
				<div class="zerospam-stat-content">
					<div class="zerospam-stat-value"><?php echo esc_html( number_format( $data['total_sites'] ) ); ?></div>
					<div class="zerospam-stat-label"><?php esc_html_e( 'Network Sites', 'zero-spam' ); ?></div>
				</div>
			</div>

			<?php if ( ! empty( $data['api_usage'] ) ) : ?>
				<div class="zerospam-stat-card">
					<div class="zerospam-stat-icon">
						<span class="dashicons dashicons-cloud"></span>
					</div>
					<div class="zerospam-stat-content">
						<div class="zerospam-stat-value"><?php echo esc_html( number_format( $data['api_usage']['used'] ) ); ?></div>
						<div class="zerospam-stat-label">
							<?php
							printf(
								/* translators: %s: API limit */
								esc_html__( 'of %s API Calls', 'zero-spam' ),
								esc_html( number_format( $data['api_usage']['limit'] ) )
							);
							?>
						</div>
					</div>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="zerospam-stat-card">
				<div class="zerospam-stat-icon zerospam-brand">
					<span class="dashicons dashicons-shield"></span>
				</div>
				<div class="zerospam-stat-content">
					<div class="zerospam-stat-value"><?php echo esc_html( number_format( $data['total_blocked'] ) ); ?></div>
					<div class="zerospam-stat-label"><?php esc_html_e( 'Spam Blocked', 'zero-spam' ); ?></div>
				</div>
			</div>

			<div class="zerospam-stat-card">
				<div class="zerospam-stat-icon">
					<span class="dashicons dashicons-admin-users"></span>
				</div>
				<div class="zerospam-stat-content">
					<div class="zerospam-stat-value"><?php echo esc_html( number_format( $data['unique_ips'] ) ); ?></div>
					<div class="zerospam-stat-label"><?php esc_html_e( 'Unique IPs', 'zero-spam' ); ?></div>
				</div>
			</div>

			<div class="zerospam-stat-card">
				<div class="zerospam-stat-icon">
					<span class="dashicons dashicons-calendar-alt"></span>
				</div>
				<div class="zerospam-stat-content">
					<div class="zerospam-stat-value"><?php echo esc_html( number_format( $data['active_days'] ) ); ?></div>
					<div class="zerospam-stat-label"><?php esc_html_e( 'Active Days (30d)', 'zero-spam' ); ?></div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php // API Usage Progress Bar (if monitoring enabled) ?>
	<?php if ( ! empty( $data['api_usage'] ) ) : ?>
		<div class="zerospam-section zerospam-api-usage">
			<h4 class="zerospam-section-title">
				<span class="dashicons dashicons-cloud"></span>
				<?php esc_html_e( 'API Usage', 'zero-spam' ); ?>
			</h4>
			<div class="zerospam-progress-bar">
				<div class="zerospam-progress-fill zerospam-progress-<?php echo esc_attr( $data['api_usage']['warning_level'] ); ?>" style="width: <?php echo esc_attr( $data['api_usage']['percentage'] ); ?>%;"></div>
			</div>
			<div class="zerospam-progress-info">
				<span>
					<?php
					printf(
						/* translators: 1: used count, 2: limit count */
						esc_html__( '%1$s of %2$s requests', 'zero-spam' ),
						'<strong>' . esc_html( number_format( $data['api_usage']['used'] ) ) . '</strong>',
						esc_html( number_format( $data['api_usage']['limit'] ) )
					);
					?>
				</span>
				<span class="zerospam-progress-percentage"><?php echo esc_html( $data['api_usage']['percentage'] ); ?>%</span>
			</div>
			<?php if ( ! empty( $data['api_usage']['reset_date'] ) ) : ?>
				<p class="zerospam-api-reset">
					<?php
					printf(
						/* translators: %s: reset date */
						esc_html__( 'Resets: %s', 'zero-spam' ),
						esc_html( gmdate( 'M j, Y', strtotime( $data['api_usage']['reset_date'] ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php // 30-Day Trend Chart ?>
	<?php if ( ! empty( $data['trend_data'] ) ) : ?>
		<div class="zerospam-section zerospam-chart-section">
			<h4 class="zerospam-section-title">
				<span class="dashicons dashicons-chart-line"></span>
				<?php esc_html_e( 'Spam Activity (Last 30 Days)', 'zero-spam' ); ?>
			</h4>
			<div class="zerospam-chart-container">
				<canvas id="zerospam-trend-chart" height="200"></canvas>
			</div>
		</div>
	<?php endif; ?>

	<?php // Top Sites (Network Admin) ?>
	<?php if ( $is_network && ! empty( $data['top_sites'] ) ) : ?>
		<div class="zerospam-section zerospam-collapsible">
			<h4 class="zerospam-section-title zerospam-toggle-trigger">
				<span class="dashicons dashicons-arrow-down-alt2 zerospam-toggle-icon"></span>
				<?php esc_html_e( 'Top 10 Sites by Spam Volume', 'zero-spam' ); ?>
			</h4>
			<div class="zerospam-collapsible-content">
				<ul class="zerospam-top-sites-list">
					<?php foreach ( $data['top_sites'] as $site ) : ?>
						<li class="zerospam-top-site-item">
							<?php if ( ! empty( $site['site_url'] ) ) : ?>
								<a href="<?php echo esc_url( $site['site_url'] ); ?>" class="zerospam-site-link">
									<span class="zerospam-site-name"><?php echo esc_html( $site['site_name'] ); ?></span>
									<span class="zerospam-site-count"><?php echo esc_html( number_format( $site['spam_count'] ) ); ?> <span class="screen-reader-text"><?php esc_html_e( 'spam blocked', 'zero-spam' ); ?></span></span>
								</a>
							<?php else : ?>
								<span class="zerospam-site-name"><?php echo esc_html( $site['site_name'] ); ?></span>
								<span class="zerospam-site-count"><?php echo esc_html( number_format( $site['spam_count'] ) ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<?php // Spam Types Breakdown (Single Site) ?>
	<?php if ( ! $is_network && ! empty( $data['spam_types'] ) ) : ?>
		<div class="zerospam-section zerospam-collapsible">
			<h4 class="zerospam-section-title zerospam-toggle-trigger">
				<span class="dashicons dashicons-arrow-down-alt2 zerospam-toggle-icon"></span>
				<?php esc_html_e( 'Spam Types Breakdown', 'zero-spam' ); ?>
			</h4>
			<div class="zerospam-collapsible-content">
				<div class="zerospam-chart-container zerospam-chart-small">
					<canvas id="zerospam-types-chart" height="180"></canvas>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php // Empty State ?>
	<?php if ( empty( $data['total_blocked'] ) || 0 === $data['total_blocked'] ) : ?>
		<div class="zerospam-empty-state">
			<span class="dashicons dashicons-shield-alt zerospam-empty-icon"></span>
			<h3><?php esc_html_e( 'No Spam Detected Yet', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Your site is protected. Blocked spam will appear here.', 'zero-spam' ); ?></p>
		</div>
	<?php endif; ?>

	<?php // Footer ?>
	<div class="zerospam-widget-footer">
		<a href="<?php echo esc_url( $is_network ? network_admin_url( 'settings.php?page=zerospam-network-settings' ) : admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ); ?>" class="zerospam-settings-link">
			<?php esc_html_e( 'View Settings', 'zero-spam' ); ?>
			<span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>
	</div>

</div>

<?php // Chart Initialization Data ?>
<script type="text/javascript">
	var zerospamChartData = {
		trendData: <?php echo wp_json_encode( $data['trend_data'] ); ?>,
		spamTypes: <?php echo wp_json_encode( ! empty( $data['spam_types'] ) ? $data['spam_types'] : array() ); ?>,
		isNetwork: <?php echo $is_network ? 'true' : 'false'; ?>
	};
</script>
