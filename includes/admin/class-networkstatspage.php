<?php
/**
 * Network Statistics Admin Page
 *
 * Displays detailed spam statistics for multisite networks
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Includes\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Network Statistics Page class
 */
class Network_Stats_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'network_admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_zerospam_export_network_stats', array( $this, 'ajax_export_stats' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		if ( ! is_multisite() ) {
			return;
		}

		add_submenu_page(
			'settings.php',
			__( 'Zero Spam Network Statistics', 'zero-spam' ),
			__( 'Zero Spam Stats', 'zero-spam' ),
			'manage_network_options',
			'wordpress-zero-spam-network-stats',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_wordpress-zero-spam-network-stats' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'zerospam-network-stats',
			plugin_dir_url( ZEROSPAM ) . 'assets/css/network-stats.css',
			array(),
			ZEROSPAM_VERSION
		);

		wp_enqueue_script(
			'zerospam-network-stats',
			plugin_dir_url( ZEROSPAM ) . 'assets/js/network-stats.js',
			array( 'jquery' ),
			ZEROSPAM_VERSION,
			true
		);

		wp_localize_script(
			'zerospam-network-stats',
			'zerospamNetworkStats',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'zerospam_network_stats' ),
			)
		);
	}

	/**
	 * Render the page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'zero-spam' ) );
		}

		// Get period from request (GET params for display-only, no nonce needed).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'month';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$end_date = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : null;

		// Get statistics.
		$network_stats  = \ZeroSpam\Includes\Network_Stats_Tracker::get_network_stats( $period, $start_date, $end_date );
		$site_breakdown = \ZeroSpam\Includes\Network_Stats_Tracker::get_site_breakdown( $period, 0, 'spam_count', $start_date, $end_date );
		$attackers      = \ZeroSpam\Includes\Network_Stats_Tracker::get_multi_site_attackers( 2, 7 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zero Spam Network Statistics', 'zero-spam' ); ?></h1>

			<!-- Period Selector -->
			<div class="period-selector">
				<label><?php esc_html_e( 'Time Period:', 'zero-spam' ); ?></label>
				<select id="period-select" name="period">
					<option value="today" <?php selected( $period, 'today' ); ?>><?php esc_html_e( 'Today', 'zero-spam' ); ?></option>
					<option value="yesterday" <?php selected( $period, 'yesterday' ); ?>><?php esc_html_e( 'Yesterday', 'zero-spam' ); ?></option>
					<option value="week" <?php selected( $period, 'week' ); ?>><?php esc_html_e( 'Last 7 Days', 'zero-spam' ); ?></option>
					<option value="month" <?php selected( $period, 'month' ); ?>><?php esc_html_e( 'Last 30 Days', 'zero-spam' ); ?></option>
				</select>
				<button type="button" class="button export-stats"><?php esc_html_e( 'Export CSV', 'zero-spam' ); ?></button>
			</div>

			<!-- Network Summary -->
			<div class="network-summary">
				<h2><?php esc_html_e( 'Network Summary', 'zero-spam' ); ?></h2>
				<div class="stats-cards">
					<div class="stat-card">
						<div class="stat-value"><?php echo esc_html( number_format( $network_stats['total_spam'] ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Total Spam Blocked', 'zero-spam' ); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-value"><?php echo esc_html( number_format( $network_stats['unique_ips'] ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Unique IP Addresses', 'zero-spam' ); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-value"><?php echo esc_html( number_format( count( $site_breakdown ) ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Active Sites', 'zero-spam' ); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-value"><?php echo esc_html( number_format( $network_stats['spam_types'] ) ); ?></div>
						<div class="stat-label"><?php esc_html_e( 'Spam Types', 'zero-spam' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Site Comparison Table -->
			<div class="site-comparison">
				<h2><?php esc_html_e( 'Site Comparison', 'zero-spam' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Site', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Spam Blocked', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Unique IPs', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Top Type', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Top Country', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Trend', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Protection', 'zero-spam' ); ?></th>
							<th><?php esc_html_e( 'Recommendation', 'zero-spam' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $site_breakdown ) ) : ?>
							<?php foreach ( $site_breakdown as $site ) : ?>
								<?php $recommendation = \ZeroSpam\Includes\Network_Stats_Tracker::get_recommendation_level( $site ); ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $site['site_name'] ); ?></strong><br>
										<small><a href="<?php echo esc_url( $site['site_url'] ); ?>" target="_blank"><?php echo esc_html( $site['site_url'] ); ?></a></small>
									</td>
									<td><?php echo esc_html( number_format( $site['spam_count'] ) ); ?></td>
									<td><?php echo esc_html( number_format( $site['unique_ips'] ) ); ?></td>
									<td><?php echo esc_html( ! empty( $site['top_type'] ) ? $site['top_type'] : '—' ); ?></td>
									<td><?php echo esc_html( ! empty( $site['top_country'] ) ? $site['top_country'] : '—' ); ?></td>
									<td>
										<?php if ( 'up' === $site['trend']['direction'] ) : ?>
											<span class="trend-badge up">↑ <?php echo esc_html( abs( $site['trend']['percentage'] ) ); ?>%</span>
										<?php elseif ( 'down' === $site['trend']['direction'] ) : ?>
											<span class="trend-badge down">↓ <?php echo esc_html( abs( $site['trend']['percentage'] ) ); ?>%</span>
										<?php else : ?>
											<span class="trend-badge neutral">—</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $site['has_enhanced'] ) : ?>
											<span class="badge enhanced"><?php esc_html_e( 'Enhanced', 'zero-spam' ); ?></span>
										<?php else : ?>
											<span class="badge free"><?php esc_html_e( 'Free', 'zero-spam' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'high' === $recommendation ) : ?>
											<span class="recommendation high"><?php esc_html_e( 'Strongly Recommended', 'zero-spam' ); ?></span>
										<?php elseif ( 'medium' === $recommendation ) : ?>
											<span class="recommendation medium"><?php esc_html_e( 'Recommended', 'zero-spam' ); ?></span>
										<?php elseif ( 'low' === $recommendation ) : ?>
											<span class="recommendation low"><?php esc_html_e( 'Consider', 'zero-spam' ); ?></span>
										<?php else : ?>
											<span class="recommendation none">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="8"><?php esc_html_e( 'No data available for the selected period.', 'zero-spam' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Multi-Site Attackers -->
			<?php if ( ! empty( $attackers ) ) : ?>
				<div class="multi-site-attackers">
					<h2><?php esc_html_e( 'Multi-Site Attackers', 'zero-spam' ); ?></h2>
					<p><?php esc_html_e( 'IP addresses that have attempted spam on multiple sites in your network (last 7 days).', 'zero-spam' ); ?></p>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'IP Address', 'zero-spam' ); ?></th>
								<th><?php esc_html_e( 'Country', 'zero-spam' ); ?></th>
								<th><?php esc_html_e( 'Attack Count', 'zero-spam' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'zero-spam' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $attackers as $attacker ) : ?>
								<tr>
									<td><code><?php echo esc_html( $attacker['user_ip'] ); ?></code></td>
									<td><?php echo esc_html( ! empty( $attacker['country_name'] ) ? $attacker['country_name'] : '—' ); ?></td>
									<td><?php echo esc_html( number_format( $attacker['attack_count'] ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wordpress-zero-spam-settings&ip=' . rawurlencode( $attacker['user_ip'] ) ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Block Network-Wide', 'zero-spam' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for exporting stats
	 */
	public function ajax_export_stats() {
		check_ajax_referer( 'zerospam_network_stats', 'nonce' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'zero-spam' ) ) );
		}

		$period     = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'month';
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null;
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null;

		$site_breakdown = \ZeroSpam\Includes\Network_Stats_Tracker::get_site_breakdown( $period, 0, 'spam_count', $start_date, $end_date );

		// Generate CSV.
		$csv_data   = array();
		$csv_data[] = array( 'Site Name', 'Site URL', 'Spam Count', 'Unique IPs', 'Top Type', 'Top Country', 'Trend %', 'Protection' );

		foreach ( $site_breakdown as $site ) {
			$csv_data[] = array(
				$site['site_name'],
				$site['site_url'],
				$site['spam_count'],
				$site['unique_ips'],
				$site['top_type'],
				$site['top_country'],
				$site['trend']['percentage'],
				$site['has_enhanced'] ? 'Enhanced' : 'Free',
			);
		}

		wp_send_json_success(
			array(
				'csv'      => $csv_data,
				'filename' => 'zero-spam-network-stats-' . gmdate( 'Y-m-d' ) . '.csv',
			)
		);
	}
}
