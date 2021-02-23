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
 * Dashboard.
 *
 * @since 5.0.0
 */
class Dashboard {

	/**
	 * Dashboard constructor.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_action_add_blocked_ip', array( $this, 'block_ip' ) );
	}

	/**
	 * Block IP handler.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function block_ip() {
		$url = parse_url( $_POST['redirect'] );
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

		wp_safe_redirect( $url . '&zerospam-success=1' );
  	exit;
	}

	/**
	 * Admin menu.
	 *
	 * @since 5.0.0
	 * @access public
	 */
	public function admin_menu() {
		add_submenu_page(
			'index.php',
			__( 'WordPress Zero Spam Dashboard', 'zerospam' ),
			__( 'Zero Spam', 'zerospam' ),
			'manage_options',
			'wordpress-zero-spam-dashboard',
			array( $this, 'dashboard_page' )
		);
	}

	/**
	 * Dashboard page.
	 *
	 * @since 5.0.0
	 * @access public
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
								esc_html_e( 'Please enter a valid IP address.', 'zerospam' );
								break;
							case 2:
								esc_html_e( 'Please select a valid type.', 'zerospam' );
								break;
							case 3:
								esc_html_e( 'Please select a date & time when the temporary block should end.', 'zerospam' );
								break;
							case 4:
								esc_html_e( 'There was a problem adding the record to the database. Please try again.', 'zerospam' );
								break;
							case 5:
								esc_html_e( 'Temporary blocks require an end date.', 'zerospam' );
								break;
							case 6:
								esc_html_e( 'You must enter a valid location key (ex. US, TX, etc.).', 'zerospam' );
								break;
							case 7:
								esc_html_e( 'Missing required fields. Please try again.', 'zerospam' );
								break;
						endswitch;
						?>
					</strong></p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zerospam' ); ?></span></button>
				</div>
			<?php elseif ( ! empty( $_GET['zerospam-success'] ) ): ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php _e( 'The blocked record has been successfully added.', 'wpzerospam' ); ?></strong></p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zerospam' ); ?>.</span></button>
				</div>
			<?php endif; ?>

			<?php
			$active_tab = 'log';
			if ( ! empty( $_REQUEST['tab'] ) ) {
				$active_tab = sanitize_text_field( $_REQUEST['tab'] );
			}
			?>
			<div class="nav-tab-wrapper">
				<a id="zerospam-settings-tab-log" class="nav-tab<?php if ( 'log' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=log' ) ); ?>"><?php echo __( 'Log', 'zerospam' ); ?></a>
				<a id="zerospam-settings-tab-blocked-ips" class="nav-tab<?php if ( 'blocked' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=blocked' ) ); ?>"><?php echo __( 'Blocked IPs', 'zerospam' ); ?></a>
				<a id="zerospam-settings-tab-blocked-locations" class="nav-tab<?php if ( 'blocked-locations' === $active_tab ) : ?> nav-tab-active<?php endif; ?>" href="<?php echo esc_url( admin_url( 'index.php?page=wordpress-zero-spam-dashboard&tab=blocked-locations' ) ); ?>"><?php echo __( 'Blocked Locations', 'zerospam' ); ?></a>
			</div>

			<div class="zerospam-tabs">
				<?php if ( 'log' === $active_tab ) : ?>
					<div id="tab-log" class="zerospam-tab is-active">
						<h2><?php echo __( 'WordPress Zero Spam Log', 'zerospam' ); ?></h2>
						<?php
						$table_data = new ZeroSpam\Core\Admin\Tables\LogTable();
						$table_data->prepare_items();
						?>
						<form id="zerospam-log-table" method="post">
							<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
							<input type="hidden" name="paged" value="1" />
							<?php $table_data->search_box( __( 'Search IPs', 'zerospam' ), 'search-ip' ); ?>
							<?php $table_data->display(); ?>
						</form>
					</div>
				<?php endif; ?>

				<?php if ( 'blocked' === $active_tab ) : ?>
					<div id="tab-blocked-ips" class="zerospam-tab is-active">
						<h2><?php echo __( 'Blocked IPs', 'zerospam' ); ?></h2>
						<?php
						$table_data = new ZeroSpam\Core\Admin\Tables\BlockedTable();
						$table_data->prepare_items();
						?>
						<form id="zerospam-blocked-table" method="post">
							<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
							<input type="hidden" name="paged" value="1" />
							<?php $table_data->search_box( __( 'Search IPs', 'zerospam' ), 'search-ip' ); ?>
							<?php $table_data->display(); ?>
						</form>
					</div>
				<?php endif; ?>

				<?php if ( 'blocked-locations' === $active_tab ) : ?>
					<div id="tab-blocked-locations" class="zerospam-tab is-active">
						<h2><?php echo __( 'Blocked Locations', 'zerospam' ); ?></h2>
						<?php
						if ( ! ZeroSpam\Core\Settings::get_settings( 'ipstack_api' ) ) {
							_e( '<strong>Blocking locations is currently disabled.</strong> A valid ipstack API key is required (defined in the plugin settings).', 'zerospam' );
						}

						$table_data = new ZeroSpam\Core\Admin\Tables\BlockedLocations();
						$table_data->prepare_items();
						?>
						<form id="zerospam-blocked-table" method="post">
							<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
							<input type="hidden" name="paged" value="1" />
							<?php $table_data->search_box( __( 'Search IPs', 'zerospam' ), 'search-ip' ); ?>
							<?php $table_data->display(); ?>
						</form>
					</div>
				<?php endif; ?>
			</div>

			<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-ip">
				<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zerospam' ) ); ?>"></button>
				<div class="zerospam-modal-details">
					<div class="zerospam-modal-title">
						<h3><?php echo __( 'Add/Update Blocked IP', 'zerospam' ); ?></h3>
					</div>
					<div class="zerospam-modal-subtitle">

					</div>

					<?php require ZEROSPAM_PATH . 'includes/templates/admin-block-ip.php'; ?>
				</div>
			</div>

			<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-location">
				<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zerospam' ) ); ?>"></button>
				<div class="zerospam-modal-details">
					<div class="zerospam-modal-title">
						<h3><?php echo __( 'Add/Update Blocked Location', 'zerospam' ); ?></h3>
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
