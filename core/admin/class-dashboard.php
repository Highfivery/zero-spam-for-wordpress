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
	 * Base admin link
	 *
	 * @var string $base_admin_link Base admin link
	 */
	public static $base_admin_link = 'index.php?page=wordpress-zero-spam-dashboard';

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
		$base_admin_link = self::$base_admin_link;
		$subview         = 'blocked-ips';

		if ( ! isset( $_POST['zerospam'] ) || ! wp_verify_nonce( $_POST['zerospam'], 'zerospam' ) ) {
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Link expired, please try again.' );
			exit;
		}

		$record = array();

		// Blocking by IP.
		if ( ! empty( $_POST['blocked_ip'] ) ) {
			$record['user_ip'] = sanitize_text_field( $_POST['blocked_ip'] );
		}

		// Blocking by custom key.
		if ( ! empty( $_POST['key_type'] ) ) {
			$subview = 'blocked-locations';

			if ( empty( $_POST['blocked_key'] ) ) {
				wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Missing block record key.' );
				exit;
			}

			$record['key_type']    = sanitize_text_field( $_POST['key_type'] );
			$record['blocked_key'] = sanitize_text_field( $_POST['blocked_key'] );
		}

		if ( empty( $record['user_ip'] ) && empty( $record['key_type'] ) ) {
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Missing record key.' );
			exit;
		}

		$record['blocked_type'] = in_array( sanitize_text_field( $_POST['blocked_type'] ), [ 'permanent', 'temporary' ] ) ? sanitize_text_field( $_POST['blocked_type'] ) : false;
		$record['reason']       = sanitize_text_field( $_POST['blocked_reason'] );
		$record['start_block']  = sanitize_text_field( $_POST['blocked_start_date'] );
		$record['end_block']    = sanitize_text_field( $_POST['blocked_end_date'] );


		if ( ! empty( $record['user_ip'] ) && ! rest_is_ip_address( $record['user_ip'] ) ) {
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Invalid IP address.' );
			exit;
		}

		if ( ! $record['blocked_type'] ) {
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Missing block type.' );
			exit;
		}

		if ( 'temporary' === $record['blocked_type'] && ! $record['end_block'] ) {
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Missing block end date.' );
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
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=Missing block end date.' );
			exit;
		}

		if ( ! \ZeroSpam\Includes\DB::blocked( $record ) ) {
			wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=error&zerospam-msg=There was a problem adding the record, please try again.' );
			exit;
		}

		// Add the the .htaccess file.
		\ZeroSpam\Core\Utilities::refresh_htaccess();

		wp_redirect( $base_admin_link . '&subview=' . $subview . '&zerospam-type=success&zerospam-msg=Record has been successfully added/updated.' );
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

		$base_admin_link = self::$base_admin_link;

		// @codingStandardsIgnoreLine
		$subview = ! empty( $_REQUEST['subview'] ) ? sanitize_text_field( $_REQUEST['subview'] ) : 'reports';

		?>
		<?php require ZEROSPAM_PATH . 'includes/templates/admin-header.php'; ?>
		<div class="wrap">
			<div class="zerospam-dashboard">
				<div class="zerospam-dashboard__col">
					<ul class="zerospam-dashboard__sections">
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=reports" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'reports' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>">
								<img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-reports.svg" class="zerospam-dashboard__menu-icon" />
								<?php esc_html_e( 'Dashboard', 'zero-spam' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=log" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'log' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>">
								<img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-log.svg" class="zerospam-dashboard__menu-icon" />
								<?php esc_html_e( 'Log', 'zero-spam' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=blocked-ips" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'blocked-ips' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>">
								<img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-blocked.svg" class="zerospam-dashboard__menu-icon" />
								<?php esc_html_e( 'Blocked IPs', 'zero-spam' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=blocked-locations" ) ); ?>" class="zerospam-dashboard__menu-link <?php if ( 'blocked-locations' === $subview ) : echo 'zerospam-dashboard__menu-link--active'; endif; ?>">
								<img src="<?php echo plugin_dir_url( ZEROSPAM ); ?>assets/img/icon-location.svg" class="zerospam-dashboard__menu-icon" />
							<?php esc_html_e( 'Blocked Locations', 'zero-spam' ); ?>
							</a>
						</li>
					</ul>

					<iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fzerospamorg&tabs=timeline&width=300&height=635&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId=2704301689814493" width="300" height="635" style="border:none;overflow:hidden;margin-bottom:40px;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>

					<a class="twitter-timeline" data-height="604" href="https://twitter.com/ZeroSpamOrg?ref_src=twsrc%5Etfw">Tweets by ZeroSpamOrg</a> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
				</div>
				<div class="zerospam-dashboard__col">
					<?php if ( ! empty( $_REQUEST['zerospam-msg'] ) ) : ?>
						<div class="zerospam-block zerospam-block--notice zerospam-block--<?php echo ! empty( $_REQUEST['zerospam-type'] ) ? esc_attr( $_REQUEST['zerospam-type'] ) : 'default' ?>">
							<div class="zerospam-block__content">
								<?php echo sanitize_text_field( wp_unslash( $_REQUEST['zerospam-msg'] ) ); ?>
							</div>
						</div>
					<?php endif; ?>

					<?php
					$entries = \ZeroSpam\Includes\DB::query( 'log' );
					switch ( $subview ) :
						case 'reports':
							?>
							<div class="zerospam-blocks">
								<div class="zerospam-block zerospam-block--review">
									<h3 class="zerospam-block__headline"><?php esc_html_e( 'Detections by the Hour', 'zero-spam' ); ?></h3>
									<div class="zerospam-block__content">
										<div class="zerospam-block__content-col">
											<?php require ZEROSPAM_PATH . 'includes/templates/admin-bar-chart.php'; ?>
										</div>
										<div class="zerospam-block__content-col">
											<?php require ZEROSPAM_PATH . 'includes/templates/admin-hours-list.php'; ?>
											<p style="margin-top: 20px">
												<?php
												printf(
													wp_kses(
														/* translators: %s: Replaced with the Zero Spam URL */
														__( '<strong>We need you!</strong> Help support development and new features by reviewing us on WordPress.org &mdash; once reviewed, contact us to get a <strong>10&#37; off coupon</strong> on any <a href="%1$s" target="_blank" rel="noreferrer noopener">Zero Spam license</a>.', 'zero-spam' ),
														array(
															'strong' => array(),
															'a'      => array(
																'target' => array(),
																'href'   => array(),
																'rel'    => array(),
															),
															'em'     => array(),
															'br'     => array(),
														)
													),
													'https://www.zerospam.org/subscribe/'
												);
												?>
											</p>
											<p><a href="https://wordpress.org/support/plugin/zero-spam/reviews/?filter=5" target="_blank" rel="noreferrer noopener" class="button button-primary"><?php _e( 'Submit Review', 'zero-spam' ); ?> &#8594;</a></p>
										</div>
									</div>
								</div>

								<div class="zerospam-block zerospam-block--map">
									<h3 class="zerospam-block__headline"><?php esc_html_e( 'Detection Heat Map', 'zero-spam' ); ?></h3>
									<div class="zerospam-block__content">
										<?php require ZEROSPAM_PATH . 'includes/templates/admin-map.php'; ?>
									</div>
								</div>

								<div class="zerospam-block zerospam-block--list">
									<h3 class="zerospam-block__headline"><?php esc_html_e( 'Most Detections the Last 30 Days', 'zero-spam' ); ?></h3>
									<div class="zerospam-block__content">
										<?php require ZEROSPAM_PATH . 'includes/templates/admin-ips.php'; ?>
										<p style="margin-top: 25px;">
											<strong><?php _e( 'Still seeing spam/malicious activity?', 'zero-spam' ); ?></strong><br />
											<?php
											printf(
												wp_kses(
													/* translators: %s: Replaced with the Zero Spam URL */
													__( 'We use the latest techniques available, but <em>nothing is 100&#37;</em>. <a href="%1$s" target="_blank" rel="noreferrer noopener">Contact us</a> if seeing a large amount a malicious activity for help.', 'zero-spam' ),
													array(
														'strong' => array(),
														'a'      => array(
															'target' => array(),
															'href'   => array(),
															'rel'    => array(),
														),
														'em'     => array(),
														'br'     => array(),
													)
												),
												esc_url( ZEROSPAM_URL )
											)
											?>
										</p>
									</div>
								</div>

								<div class="zerospam-block zerospam-block--pie-chart">
									<h3 class="zerospam-block__headline"><?php esc_html_e( 'Last 14 Days by Location', 'zero-spam' ); ?></h3>
									<div class="zerospam-block__content">
										<?php require ZEROSPAM_PATH . 'includes/templates/admin-pie.php'; ?>
									</div>
								</div>

								<div class="zerospam-block zerospam-block--line-chart">
									<h3 class="zerospam-block__headline"><?php esc_html_e( 'Last 14 Days of Detections', 'zero-spam' ); ?></h3>
									<div class="zerospam-block__content">
										<?php require ZEROSPAM_PATH . 'includes/templates/admin-line-chart.php'; ?>
									</div>
								</div>
							</div>
							<?php
							break;
						case 'log':
							$table_data = new \ZeroSpam\Core\Admin\Tables\LogTable();
							$table_data->prepare_items();
							?>
							<form class="zerospam-table-form" method="post">
								<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
								<input type="hidden" name="paged" value="1" />
								<?php $table_data->search_box( __( 'Search IPs', 'zero-spam' ), 'search-ip' ); ?>
								<?php $table_data->display(); ?>
							</form>
							<?php
							break;
						case 'blocked-ips':
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

							$table_data = new \ZeroSpam\Core\Admin\Tables\BlockedTable();
							$table_data->prepare_items();
							?>
							<form class="zerospam-table-form" method="post">
								<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
								<input type="hidden" name="paged" value="1" />
								<?php $table_data->search_box( __( 'Search IPs', 'zero-spam' ), 'search-ip' ); ?>
								<?php $table_data->display(); ?>
							</form>
							<?php
							break;
							case 'blocked-locations':
								if (
									! \ZeroSpam\Core\Settings::get_settings( 'ipstack_api' ) &&
									! \ZeroSpam\Core\Settings::get_settings( 'ipinfo_access_token' )
								) :
									?>
									<div class="zerospam-block zerospam-block--error">
										<div class="zerospam-block__content">
											<?php _e( '<strong>Blocking locations is currently disabled.</strong> A valid ipstack API key or IPinfo access token is required.', 'zero-spam' ); ?>
										</div>
									</div>
								<?php endif; ?>
								<?php
								$table_data = new \ZeroSpam\Core\Admin\Tables\BlockedLocations();
								$table_data->prepare_items();
								?>
								<form class="zerospam-table-form" method="post">
									<?php wp_nonce_field( 'zerospam_nonce', 'zerospam_nonce' ); ?>
									<input type="hidden" name="paged" value="1" />
									<?php $table_data->search_box( __( 'Search IPs', 'zero-spam' ), 'search-ip' ); ?>
									<?php $table_data->display(); ?>
								</form>
								<?php
							break;
					endswitch;
					?>
				</div>
			</div>
		</div>

		<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-ip">
			<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zero-spam' ) ); ?>"></button>

			<div class="zerospam-block">
				<h3 class="zerospam-block__headline"><?php echo __( 'Add/Update Blocked IP', 'zero-spam' ); ?></h3>
				<div class="zerospam-block__content">
					<?php require ZEROSPAM_PATH . 'includes/templates/admin-block-ip.php'; ?>
				</div>
			</div>
		</div>

		<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-location">
			<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zero-spam' ) ); ?>"></button>

			<div class="zerospam-block">
				<h3 class="zerospam-block__headline"><?php echo __( 'Add/Update Blocked Location', 'zero-spam' ); ?></h3>
				<div class="zerospam-block__content">
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
