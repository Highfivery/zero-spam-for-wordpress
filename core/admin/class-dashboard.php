<?php
/**
 * Dashboard class.
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

use ZeroSpam;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Admin Dashboard
 */
class Dashboard {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_action_add_blocked_ip', array( $this, 'block_ip' ) );
	}

	public function admin_init() {
		if ( ! empty( $_REQUEST['zerospam-refresh-htaccess'] ) ) {
			\ZeroSpam\Core\Utilities::refresh_htaccess();
		}
	}

	/**
	 * Block IP handler
	 */
	public function block_ip() {
		$url = parse_url( sanitize_url( $_POST['redirect'] ) );

		$url['query'] = str_replace(
			array(
				'zerospam-success=1',
				'zerospam-error=1',
				'zerospam-error=2',
				'zerospam-error=3',
				'zerospam-error=4',
				'zerospam-error=5',
				'zerospam-error=6',
				'zerospam-error=7',
			),
			'',
			$url['query']
		);

		$url = $url['scheme'] . '://' . $url['host'] . ( ! empty( $url['port'] ) ? ':' . $url['port'] : '' ) . $url['path'] . '?' . $url['query'];

		if ( ! isset( $_POST['zerospam'] ) || ! wp_verify_nonce( $_POST['zerospam'], 'zerospam' ) ) {
			wp_redirect( $url . '&zerospam-error=1' );
			exit;
		}

		$record = array();

		// Blocking by IP.
		if ( ! empty( $_POST['blocked_ip'] ) ) {
			$record['user_ip'] = sanitize_text_field( $_POST['blocked_ip'] );
		}

		// Blocking by custom key.
		if ( ! empty( $_POST['key_type'] ) ) {
			if ( empty( $_POST['blocked_key'] ) ) {
				wp_safe_redirect( $url . '&zerospam-error=6' );
				exit;
			}

			$record['key_type']    = sanitize_text_field( $_POST['key_type'] );
			$record['blocked_key'] = sanitize_text_field( $_POST['blocked_key'] );
		}

		if ( empty( $record['user_ip'] ) && empty( $record['key_type'] ) ) {
			wp_safe_redirect( $url . '&zerospam-error=7' );
			exit;
		}

		$record['blocked_type'] = in_array( sanitize_text_field( $_POST['blocked_type'] ), [ 'permanent', 'temporary' ] ) ? sanitize_text_field( $_POST['blocked_type'] ) : false;
		$record['reason']       = sanitize_text_field( $_POST['blocked_reason'] );
		$record['start_block']  = sanitize_text_field( $_POST['blocked_start_date'] );
		$record['end_block']    = sanitize_text_field( $_POST['blocked_end_date'] );


		if ( ! empty( $record['user_ip'] ) && ! rest_is_ip_address( $record['user_ip'] ) ) {
			wp_safe_redirect( $url . '&zerospam-error=1' );
			exit;
		}

		if ( ! $record['blocked_type'] ) {
			wp_safe_redirect( $url . '&zerospam-error=2' );
			exit;
		}

		if ( 'temporary' === $record['blocked_type'] && ! $record['end_block'] ) {
			wp_safe_redirect( $url . '&zerospam-error=5' );
			exit;
		}

		if ( $record['start_block'] ) {
			$record['start_block'] = gmdate( 'Y-m-d G:i:s', strtotime( $record['start_block'] ) );
		} else {
			$record['start_block'] = current_time( 'mysql' );
		}

		if ( $record['end_block'] ) {
			$record['end_block'] = gmdate( 'Y-m-d G:i:s', strtotime( $record['end_block'] ) );
		}

		if ( 'temporary' === $record['blocked_type'] && ! $record['end_block']  ) {
			wp_safe_redirect( $url . '&error=3' );
			exit;
		}

		if ( ! ZeroSpam\Includes\DB::blocked( $record ) ) {
			wp_safe_redirect( $url . '&zerospam-error=4' );
			exit;
		}

		// Add the the .htaccess file.
		\ZeroSpam\Core\Utilities::refresh_htaccess();

		wp_safe_redirect( $url . '&zerospam-success=1' );
  	exit;
	}

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		add_submenu_page(
			'index.php',
			__( 'Zero Spam for WordPress Dashboard', 'zero-spam' ),
			__( 'Zero Spam', 'zero-spam' ),
			'manage_options',
			'wordpress-zero-spam-dashboard',
			array( $this, 'dashboard_page' )
		);
	}

	/**
	 * Dashboard page
	 */
	public function dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php require ZEROSPAM_PATH . 'includes/templates/admin-callout.php'; ?>

			<?php if ( ! empty( $_GET['zerospam-error'] ) ): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong>
						<?php
						switch( intval( $_GET['zerospam-error'] ) ) :
							case 1:
								esc_html_e( 'Please enter a valid IP address.', 'zero-spam' );
								break;
							case 2:
								esc_html_e( 'Please select a valid type.', 'zero-spam' );
								break;
							case 3:
								esc_html_e( 'Please select a date & time when the temporary block should end.', 'zero-spam' );
								break;
							case 4:
								esc_html_e( 'There was a problem adding the record to the database. Please try again.', 'zero-spam' );
								break;
							case 5:
								esc_html_e( 'Temporary blocks require an end date.', 'zero-spam' );
								break;
							case 6:
								esc_html_e( 'You must enter a valid location key (ex. US, TX, etc.).', 'zero-spam' );
								break;
							case 7:
								esc_html_e( 'Missing required fields. Please try again.', 'zero-spam' );
								break;
						endswitch;
						?>
					</strong></p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?></span></button>
				</div>
			<?php elseif ( ! empty( $_GET['zerospam-success'] ) ): ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php _e( 'The blocked record has been successfully added.', 'wpzerospam' ); ?></strong></p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?>.</span></button>
				</div>
			<?php endif; ?>

			<?php
			$active_tab = 'stats';
			if ( ! empty( $_REQUEST['tab'] ) ) {
				$active_tab = sanitize_text_field( $_REQUEST['tab'] );
			}
			?>
			<div class="nav-tab-wrapper">
				<a id="zerospam-settings-tab-stats" class="nav-tab<?php if ( 'stats' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=stats' ) ); ?>"><?php echo __( 'Statistics', 'zero-spam' ); ?></a>
				<a id="zerospam-settings-tab-log" class="nav-tab<?php if ( 'log' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=log' ) ); ?>"><?php echo __( 'Log', 'zero-spam' ); ?></a>
				<a id="zerospam-settings-tab-blocked-ips" class="nav-tab<?php if ( 'blocked' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=blocked' ) ); ?>"><?php echo __( 'Blocked IPs', 'zero-spam' ); ?></a>
				<a id="zerospam-settings-tab-blocked-locations" class="nav-tab<?php if ( 'blocked-locations' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=blocked-locations' ) ); ?>"><?php echo __( 'Blocked Locations', 'zero-spam' ); ?></a>
			</div>

			<div class="zerospam-tabs">
				<?php
				if ( 'stats' === $active_tab ) :
					$entries = ZeroSpam\Includes\DB::query( 'log' );

					if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_ips' ) ) :
						echo '<div class="zerospam-modules">';

						echo '<div class="zerospam-module zerospam-module-map">';
						echo sprintf(
							wp_kses(
								__( '<h3>Detections World Map</h3>', 'zero-spam' ),
								array(
									'h3' => array(),
								)
							)
						);
						require ZEROSPAM_PATH . 'includes/templates/admin-map.php';
						echo '</div>';

						?>
						<div class="zerospam-module zerospam-module-ip">
							<h3><?php esc_html_e( 'Most Detections by IP Address', 'zero-spam' ); ?></h3>
							<?php require ZEROSPAM_PATH . 'includes/templates/admin-ips.php'; ?>
						</div>

						<div class="zerospam-module zerospam-module-pie">
							<h3><?php esc_html_e( 'Detections by Location', 'zero-spam' ); ?></h3>
							<?php require ZEROSPAM_PATH . 'includes/templates/admin-pie.php'; ?>
						</div>

						<div class="zerospam-module zerospam-module-line-chart">
							<h3><?php esc_html_e( 'Detection History', 'zero-spam' ); ?></h3>
							<?php require ZEROSPAM_PATH . 'includes/templates/admin-line-chart.php'; ?>
						</div>
						<?php

						echo '</div>';
					else :
						?>
						<div class="zerospam-notice">
							<?php
							echo sprintf(
								wp_kses(
									/* translators: %s: url */
									__( 'Zero Spam for WordPress logging is currently <strong>disabled</strong>. It can be enabled on the <a href="%s">settings page</a>.', 'zero-spam' ),
									array(
										'strong' => array(),
										'a'    => array(
											'href'   => array(),
										),
									)
								),
								esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) )
							);
							?>
						</div>
						<?php
					endif;
				endif;
				?>

				<?php if ( 'log' === $active_tab ) : ?>
					<div id="tab-log" class="zerospam-tab is-active">
						<?php if ( 'enabled' === ZeroSpam\Core\Settings::get_settings( 'log_blocked_ips' ) ) : ?>

							<h2><?php echo __( 'Zero Spam for WordPress Log', 'zero-spam' ); ?></h2>
							<?php
							$table_data = new ZeroSpam\Core\Admin\Tables\LogTable();
							$table_data->prepare_items();
							?>
							<form id="zerospam-log-table" method="post">
								<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
								<input type="hidden" name="paged" value="1" />
								<?php $table_data->search_box( __( 'Search IPs', 'zero-spam' ), 'search-ip' ); ?>
								<?php $table_data->display(); ?>
							</form>

						<?php else : ?>
							<div class="zerospam-notice">
								<?php
								echo sprintf(
									wp_kses(
										/* translators: %s: url */
										__( 'Zero Spam for WordPress logging is currently <strong>disabled</strong>. It can be enabled on the <a href="%s">settings page</a>.', 'zero-spam' ),
										array(
											'strong' => array(),
											'a'    => array(
												'href'   => array(),
											),
										)
									),
									esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) )
								);
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( 'blocked' === $active_tab ) : ?>
					<div id="tab-blocked-ips" class="zerospam-tab is-active">
						<h2><?php echo __( 'Blocked IPs', 'zero-spam' ); ?></h2>
						<?php
						$block_method = \ZeroSpam\Core\Settings::get_settings( 'block_method' );
						if ( ! empty( $block_method ) && 'php' !== $block_method ) :
							echo sprintf(
								wp_kses(
									/* translators: %s: url */
									__( '<p>When using .htaccess &amp; due to <a href="%s" target="_blank" rel="noreferrer noopener">character limit restrictions</a>, <strong>no more than 170 blocked IP addresses recommended</strong>.</p>', 'zero-spam' ),
									array(
										'strong' => array(),
										'a'    => array(
											'target' => array(),
											'href'   => array(),
											'rel'    => array(),
										),
									)
								),
								esc_url( 'https://httpd.apache.org/docs/current/en/configuring.html' ),
							);
						endif;

						$table_data = new ZeroSpam\Core\Admin\Tables\BlockedTable();
						$table_data->prepare_items();
						?>
						<form id="zerospam-blocked-table" method="post">
							<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
							<input type="hidden" name="paged" value="1" />
							<?php $table_data->search_box( __( 'Search IPs', 'zero-spam' ), 'search-ip' ); ?>
							<?php $table_data->display(); ?>
						</form>
					</div>
				<?php endif; ?>

				<?php if ( 'blocked-locations' === $active_tab ) : ?>
					<div id="tab-blocked-locations" class="zerospam-tab is-active">
						<h2><?php echo __( 'Blocked Locations', 'zero-spam' ); ?></h2>
						<?php
						if (
							! ZeroSpam\Core\Settings::get_settings( 'ipstack_api' ) &&
							! ZeroSpam\Core\Settings::get_settings( 'ipinfo_access_token' )
						) {
							_e( '<strong>Blocking locations is currently disabled.</strong> A valid ipstack API key or IPinfo access token is required (defined in the plugin settings).', 'zero-spam' );
						}

						$table_data = new ZeroSpam\Core\Admin\Tables\BlockedLocations();
						$table_data->prepare_items();
						?>
						<form id="zerospam-blocked-table" method="post">
							<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
							<input type="hidden" name="paged" value="1" />
							<?php $table_data->search_box( __( 'Search IPs', 'zero-spam' ), 'search-ip' ); ?>
							<?php $table_data->display(); ?>
						</form>
					</div>
				<?php endif; ?>
			</div>

			<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-ip">
				<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zero-spam' ) ); ?>"></button>
				<div class="zerospam-modal-details">
					<div class="zerospam-modal-title">
						<h3><?php echo __( 'Add/Update Blocked IP', 'zero-spam' ); ?></h3>
					</div>
					<div class="zerospam-modal-subtitle">

					</div>

					<?php require ZEROSPAM_PATH . 'includes/templates/admin-block-ip.php'; ?>
				</div>
			</div>

			<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-location">
				<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zero-spam' ) ); ?>"></button>
				<div class="zerospam-modal-details">
					<div class="zerospam-modal-title">
						<h3><?php echo __( 'Add/Update Blocked Location', 'zero-spam' ); ?></h3>
					</div>
					<div class="zerospam-modal-subtitle">

					</div>

					<?php
					$location_form = true;
					require ZEROSPAM_PATH . 'includes/templates/admin-block-ip.php';
					?>
				</div>
			</div>
		</div>
		<?php
	}
}
