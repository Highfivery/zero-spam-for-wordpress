<?php
/**
 * Zero Spam Dashboard Widget
 *
 * @package ZeroSpam
 */

// Security check.
defined( 'ABSPATH' ) || die();

// Debug output (remove after verifying)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $debug_info ) ) {
	echo '<!-- Zero Spam Widget Debug: ' . wp_json_encode( $debug_info ) . ' -->';
}

// Enqueue Chart.js 4.x (modern version)
wp_enqueue_script(
	'zerospam-chartjs',
	'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
	array(),
	'4.4.1',
	true
);

// Calculate stats
$total_blocked = count( $entries );
$unique_ips = array();
$spam_types = array();
$last_30_days = array();

foreach ( $entries as $entry ) {
	if ( ! empty( $entry['user_ip'] ) && ! in_array( $entry['user_ip'], $unique_ips ) ) {
		$unique_ips[] = $entry['user_ip'];
	}

	if ( ! empty( $entry['log_type'] ) ) {
		if ( ! isset( $spam_types[ $entry['log_type'] ] ) ) {
			$spam_types[ $entry['log_type'] ] = 0;
		}
		$spam_types[ $entry['log_type'] ]++;
	}

	// Last 30 days
	$entry_time = strtotime( $entry['date_recorded'] );
	if ( $entry_time >= strtotime( '-30 days' ) ) {
		$date_key = gmdate( 'Y-m-d', $entry_time );
		if ( ! isset( $last_30_days[ $date_key ] ) ) {
			$last_30_days[ $date_key ] = 0;
		}
		$last_30_days[ $date_key ]++;
	}
}

$unique_ip_count = count( $unique_ips );
?>

<div class="zerospam-dashboard-widget">
	<?php if ( ! $zerospam_enabled || ! $zerospam_license ) : ?>
		<!-- Promotional CTA for non-Enhanced Protection users -->
		<div class="zerospam-promo-banner">
			<div class="zerospam-promo-icon">
				<svg width="48" height="48" viewBox="0 0 48 48" fill="none">
					<circle cx="24" cy="24" r="22" fill="#3F0008" opacity="0.1"/>
					<path d="M24 12C17.373 12 12 17.373 12 24C12 30.627 17.373 36 24 36C30.627 36 36 30.627 36 24C36 17.373 30.627 12 24 12ZM28 27.172L26.172 29L24 26.828L21.828 29L20 27.172L22.172 25L20 22.828L21.828 21L24 23.172L26.172 21L28 22.828L25.828 25L28 27.172Z" fill="#3F0008"/>
				</svg>
			</div>
			<div class="zerospam-promo-content">
				<h3><?php esc_html_e( 'Unlock Enhanced Protection', 'zero-spam' ); ?></h3>
				<p><?php esc_html_e( 'Stop sophisticated spam with real-time global threat intelligence. Our AI-powered network blocks attacks before they reach your site.', 'zero-spam' ); ?></p>
				<div class="zerospam-promo-features">
					<span>✓ Real-time IP reputation</span>
					<span>✓ 99.9% accuracy</span>
					<span>✓ Zero false positives</span>
				</div>
				<div class="zerospam-promo-actions">
					<a href="https://www.zerospam.org/pricing/?utm_source=plugin&utm_medium=dashboard&utm_campaign=widget" target="_blank" rel="noopener noreferrer" class="button button-primary zerospam-cta-button">
						<?php esc_html_e( 'View Pricing', 'zero-spam' ); ?>
					</a>
					<a href="https://www.zerospam.org/?utm_source=plugin&utm_medium=dashboard&utm_campaign=widget" target="_blank" rel="noopener noreferrer" class="button button-secondary">
						<?php esc_html_e( 'Learn More', 'zero-spam' ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php elseif ( ! $license_valid && ! empty( $license_status_message ) ) : ?>
		<!-- License Issue Notice -->
		<div class="zerospam-notice zerospam-notice-warning">
			<svg width="20" height="20" viewBox="0 0 20 20">
				<path fill="#856404" d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm1 15H9v-2h2v2zm0-4H9V5h2v6z"/>
			</svg>
			<div>
				<strong><?php esc_html_e( 'License Issue', 'zero-spam' ); ?></strong>
				<p><?php echo esc_html( $license_status_message ); ?></p>
				<a href="https://www.zerospam.org/account/?utm_source=plugin&utm_medium=dashboard&utm_campaign=license_issue" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Manage Your License', 'zero-spam' ); ?> →
				</a>
			</div>
		</div>
	<?php elseif ( $zerospam_enabled && $zerospam_license && ! $license_valid ) : ?>
		<!-- License Validation Pending -->
		<div class="zerospam-notice zerospam-notice-info">
			<svg width="20" height="20" viewBox="0 0 20 20">
				<path fill="#0073aa" d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm1 15H9v-2h2v2zm0-4H9V5h2v6z"/>
			</svg>
			<div>
				<strong><?php esc_html_e( 'Validating License', 'zero-spam' ); ?></strong>
				<p><?php esc_html_e( 'Your license is being verified. This usually takes a few moments.', 'zero-spam' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<!-- Stats Overview -->
	<div class="zerospam-stats-grid">
		<div class="zerospam-stat-card">
			<div class="zerospam-stat-icon" style="background: rgba(63, 0, 8, 0.1);">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="#3F0008">
					<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
				</svg>
			</div>
			<div class="zerospam-stat-content">
				<div class="zerospam-stat-value"><?php echo esc_html( number_format( $total_blocked ) ); ?></div>
				<div class="zerospam-stat-label"><?php esc_html_e( 'Spam Blocked', 'zero-spam' ); ?></div>
			</div>
		</div>

		<div class="zerospam-stat-card">
			<div class="zerospam-stat-icon" style="background: rgba(63, 0, 8, 0.1);">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="#3F0008">
					<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
				</svg>
			</div>
			<div class="zerospam-stat-content">
				<div class="zerospam-stat-value"><?php echo esc_html( number_format( $unique_ip_count ) ); ?></div>
				<div class="zerospam-stat-label"><?php esc_html_e( 'Unique IPs', 'zero-spam' ); ?></div>
			</div>
		</div>

		<div class="zerospam-stat-card">
			<div class="zerospam-stat-icon" style="background: rgba(63, 0, 8, 0.1);">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="#3F0008">
					<path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
				</svg>
			</div>
			<div class="zerospam-stat-content">
				<div class="zerospam-stat-value"><?php echo esc_html( count( array_filter( $last_30_days ) ) ); ?></div>
				<div class="zerospam-stat-label"><?php esc_html_e( 'Active Days (30d)', 'zero-spam' ); ?></div>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $entries ) ) : ?>
		<!-- Spam Trend Chart (Last 30 Days) -->
		<div class="zerospam-chart-container">
			<h4><?php esc_html_e( 'Spam Activity (Last 30 Days)', 'zero-spam' ); ?></h4>
			<canvas id="zerospam-trend-chart"></canvas>
		</div>

		<!-- Spam Types Breakdown -->
		<?php if ( ! empty( $spam_types ) ) : ?>
			<div class="zerospam-chart-container">
				<h4><?php esc_html_e( 'Spam Types', 'zero-spam' ); ?></h4>
				<canvas id="zerospam-types-chart"></canvas>
			</div>
		<?php endif; ?>

		<script>
		(function() {
			// Prepare trend data (last 30 days)
			const trendLabels = [];
			const trendData = [];
			const today = new Date();
			
			for (let i = 29; i >= 0; i--) {
				const date = new Date(today);
				date.setDate(date.getDate() - i);
				const dateKey = date.toISOString().split('T')[0];
				const label = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
				trendLabels.push(label);
				trendData.push(<?php echo wp_json_encode( $last_30_days ); ?>[dateKey] || 0);
			}

			// Trend Chart
			const trendCtx = document.getElementById('zerospam-trend-chart');
			if (trendCtx) {
				new Chart(trendCtx, {
					type: 'line',
					data: {
						labels: trendLabels,
						datasets: [{
							label: '<?php esc_html_e( 'Blocked', 'zero-spam' ); ?>',
							data: trendData,
							borderColor: '#3F0008',
							backgroundColor: 'rgba(63, 0, 8, 0.1)',
							fill: true,
							tension: 0.4,
							pointRadius: 3,
							pointHoverRadius: 5,
							pointBackgroundColor: '#3F0008',
							pointBorderColor: '#fff',
							pointBorderWidth: 2
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						interaction: {
							intersect: false,
							mode: 'index'
						},
						plugins: {
							legend: {
								display: false
							},
							tooltip: {
								backgroundColor: 'rgba(0, 0, 0, 0.8)',
								padding: 12,
								titleFont: { size: 14, weight: 'bold' },
								bodyFont: { size: 13 },
								borderColor: 'rgba(255, 255, 255, 0.1)',
								borderWidth: 1
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									precision: 0,
									font: { size: 11 },
									color: '#6c757d'
								},
								grid: {
									color: 'rgba(0, 0, 0, 0.05)',
									drawBorder: false
								}
							},
							x: {
								ticks: {
									maxRotation: 45,
									minRotation: 0,
									font: { size: 10 },
									color: '#6c757d'
								},
								grid: {
									display: false
								}
							}
						}
					}
				});
			}

			<?php if ( ! empty( $spam_types ) ) : ?>
			// Types Chart
			const typesCtx = document.getElementById('zerospam-types-chart');
			if (typesCtx) {
				const typesData = <?php echo wp_json_encode( array_values( $spam_types ) ); ?>;
				const typesLabels = <?php echo wp_json_encode( array_keys( $spam_types ) ); ?>;
				
				// Generate colors
				const baseHue = 0; // Red hue for Zero Spam brand
				const colors = typesLabels.map((_, i) => {
					const lightness = 20 + (i * 10);
					return `hsl(${baseHue}, 100%, ${Math.min(lightness, 50)}%)`;
				});

				new Chart(typesCtx, {
					type: 'doughnut',
					data: {
						labels: typesLabels,
						datasets: [{
							data: typesData,
							backgroundColor: colors,
							borderWidth: 2,
							borderColor: '#fff',
							hoverOffset: 8
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: {
							legend: {
								position: 'right',
								labels: {
									padding: 15,
									font: { size: 12 },
									color: '#1d2327',
									usePointStyle: true,
									pointStyle: 'circle',
									generateLabels: function(chart) {
										const data = chart.data;
										const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
										return data.labels.map((label, i) => {
											const value = data.datasets[0].data[i];
											const percentage = ((value / total) * 100).toFixed(1);
											return {
												text: `${label}: ${value} (${percentage}%)`,
												fillStyle: data.datasets[0].backgroundColor[i],
												hidden: false,
												index: i
											};
										});
									}
								}
							},
							tooltip: {
								backgroundColor: 'rgba(0, 0, 0, 0.8)',
								padding: 12,
								titleFont: { size: 14, weight: 'bold' },
								bodyFont: { size: 13 },
								callbacks: {
									label: function(context) {
										const total = context.dataset.data.reduce((a, b) => a + b, 0);
										const percentage = ((context.parsed / total) * 100).toFixed(1);
										return `${context.label}: ${context.parsed} (${percentage}%)`;
									}
								}
							}
						}
					}
				});
			}
			<?php endif; ?>
		})();
		</script>
	<?php else : ?>
		<div class="zerospam-empty-state">
			<svg width="64" height="64" viewBox="0 0 64 64" fill="none">
				<circle cx="32" cy="32" r="30" fill="#f6f7f7"/>
				<path d="M32 16C23.163 16 16 23.163 16 32C16 40.837 23.163 48 32 48C40.837 48 48 40.837 48 32C48 23.163 40.837 16 32 16ZM38 35.828L35.828 38L32 34.172L28.172 38L26 35.828L29.828 32L26 28.172L28.172 26L32 29.828L35.828 26L38 28.172L34.172 32L38 35.828Z" fill="#ccc"/>
			</svg>
			<h3><?php esc_html_e( 'No Spam Detected Yet', 'zero-spam' ); ?></h3>
			<p><?php esc_html_e( 'Your site is protected. Spam will appear here when blocked.', 'zero-spam' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Footer -->
	<div class="zerospam-widget-footer">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ); ?>">
			<?php esc_html_e( 'View Settings', 'zero-spam' ); ?> →
		</a>
	</div>
</div>
