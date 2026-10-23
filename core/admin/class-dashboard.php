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
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_action_add_blocked_ip', array( $this, 'block_ip' ) );
		add_action( 'admin_post_zerospam_refresh_htaccess', array( $this, 'refresh_htaccess' ) );
	}

	/**
	 * Refreshes the .htaccess block rules when an administrator asks for it.
	 *
	 * Handles admin-post.php?action=zerospam_refresh_htaccess (the "Refresh
	 * .htaccess" button on the Blocked IPs screen). Requires `manage_options`
	 * and a valid `zerospam-refresh-htaccess` nonce in `_wpnonce`; a failed
	 * check ends the request with wp_die(). Redirects back to the Blocked IPs
	 * screen with a notice, so the nonce never stays in the page URL.
	 */
	public function refresh_htaccess() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to refresh the .htaccess file.', 'zero-spam' ),
				esc_html__( 'Forbidden', 'zero-spam' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'zerospam-refresh-htaccess' );

		if ( \ZeroSpam\Core\Utilities::refresh_htaccess() ) {
			self::redirect_with_message( 'blocked-ips', 'success', __( '.htaccess refreshed.', 'zero-spam' ) );
		}

		self::redirect_with_message( 'blocked-ips', 'error', __( 'The .htaccess file was not updated. Check that the IP block method is an .htaccess option, that the file is writable, and that at least one IP is blocked.', 'zero-spam' ) );
	}

	/**
	 * Block IP handler
	 *
	 * Handles the add/update block form (admin.php?action=add_blocked_ip).
	 * Requires `manage_options` and a valid `zerospam` nonce.
	 */
	public function block_ip() {
		$subview = 'blocked-ips';

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to block IP addresses or locations.', 'zero-spam' ),
				esc_html__( 'Forbidden', 'zero-spam' ),
				array( 'response' => 403 )
			);
		}

		$nonce = isset( $_POST['zerospam'] ) ? sanitize_text_field( wp_unslash( $_POST['zerospam'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zerospam' ) ) {
			self::redirect_with_message( $subview, 'error', __( 'Link expired, please try again.', 'zero-spam' ) );
		}

		$record = array();

		// Blocking by IP.
		if ( ! empty( $_POST['blocked_ip'] ) ) {
			$record['user_ip'] = sanitize_text_field( wp_unslash( $_POST['blocked_ip'] ) );
		}

		// Blocking by custom key.
		if ( ! empty( $_POST['key_type'] ) ) {
			$subview = 'blocked-locations';

			if ( empty( $_POST['blocked_key'] ) ) {
				self::redirect_with_message( $subview, 'error', __( 'Missing block record key.', 'zero-spam' ) );
			}

			$record['key_type']    = sanitize_text_field( wp_unslash( $_POST['key_type'] ) );
			$record['blocked_key'] = isset( $_POST['blocked_key'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_key'] ) ) : '';
		}

		if ( empty( $record['user_ip'] ) && empty( $record['key_type'] ) ) {
			self::redirect_with_message( $subview, 'error', __( 'Missing record key.', 'zero-spam' ) );
		}

		$blocked_type           = isset( $_POST['blocked_type'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_type'] ) ) : '';
		$record['blocked_type'] = in_array( $blocked_type, array( 'permanent', 'temporary' ), true ) ? $blocked_type : false;
		$record['reason']       = isset( $_POST['blocked_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_reason'] ) ) : '';
		$record['start_block']  = isset( $_POST['blocked_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_start_date'] ) ) : '';
		$record['end_block']    = isset( $_POST['blocked_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['blocked_end_date'] ) ) : '';

		if ( ! empty( $record['user_ip'] ) && ! rest_is_ip_address( $record['user_ip'] ) ) {
			self::redirect_with_message( $subview, 'error', __( 'Invalid IP address.', 'zero-spam' ) );
		}

		if ( ! $record['blocked_type'] ) {
			self::redirect_with_message( $subview, 'error', __( 'Missing block type.', 'zero-spam' ) );
		}

		if ( 'temporary' === $record['blocked_type'] && ! $record['end_block'] ) {
			self::redirect_with_message( $subview, 'error', __( 'Missing block end date.', 'zero-spam' ) );
		}

		if ( $record['start_block'] ) {
			$record['start_block'] = gmdate( 'Y-m-d G:i:s', strtotime( $record['start_block'] ) );
		} else {
			$record['start_block'] = current_time( 'mysql' );
		}

		if ( $record['end_block'] ) {
			$record['end_block'] = gmdate( 'Y-m-d G:i:s', strtotime( $record['end_block'] ) );
		}

		if ( 'temporary' === $record['blocked_type'] && ! $record['end_block'] ) {
			self::redirect_with_message( $subview, 'error', __( 'Missing block end date.', 'zero-spam' ) );
		}

		if ( ! \ZeroSpam\Includes\DB::blocked( $record ) ) {
			self::redirect_with_message( $subview, 'error', __( 'There was a problem adding the record, please try again.', 'zero-spam' ) );
		}

		// Add the the .htaccess file.
		\ZeroSpam\Core\Utilities::refresh_htaccess();

		self::redirect_with_message( $subview, 'success', __( 'Record has been successfully added/updated.', 'zero-spam' ) );
	}

	/**
	 * Redirects to a dashboard subview with a notice and ends the request.
	 *
	 * @param string $subview Dashboard subview (blocked-ips, blocked-locations, ...).
	 * @param string $type    Notice type (success or error).
	 * @param string $message Notice text, shown escaped by dashboard_page().
	 * @return never
	 */
	private static function redirect_with_message( string $subview, string $type, string $message ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'subview'       => $subview,
					'zerospam-type' => $type,
					'zerospam-msg'  => rawurlencode( $message ),
				),
				admin_url( self::$base_admin_link )
			)
		);
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameter; it only picks which view to render.
		$subview = ! empty( $_REQUEST['subview'] ) ? sanitize_key( wp_unslash( $_REQUEST['subview'] ) ) : 'reports';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice parameters set by block_ip()'s redirect; they are escaped on output and change nothing.
		$notice_message = ! empty( $_REQUEST['zerospam-msg'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['zerospam-msg'] ) ) : '';
		$notice_type    = ! empty( $_REQUEST['zerospam-type'] ) ? sanitize_key( wp_unslash( $_REQUEST['zerospam-type'] ) ) : 'default';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		?>
		<?php require ZEROSPAM_PATH . 'includes/templates/admin-header.php'; ?>
		<div class="wrap">
			<div class="zerospam-dashboard">
				<div class="zerospam-dashboard__col">
					<ul class="zerospam-dashboard__sections">
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=reports" ) ); ?>" class="zerospam-dashboard__menu-link
												<?php
												if ( 'reports' === $subview ) :
													echo 'zerospam-dashboard__menu-link--active';
endif;
												?>
							">
								<img src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-reports.svg' ); ?>" class="zerospam-dashboard__menu-icon" />
								<?php esc_html_e( 'Dashboard', 'zero-spam' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=log" ) ); ?>" class="zerospam-dashboard__menu-link
												<?php
												if ( 'log' === $subview ) :
													echo 'zerospam-dashboard__menu-link--active';
endif;
												?>
							">
								<img src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-log.svg' ); ?>" class="zerospam-dashboard__menu-icon" />
								<?php esc_html_e( 'Log', 'zero-spam' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=blocked-ips" ) ); ?>" class="zerospam-dashboard__menu-link
												<?php
												if ( 'blocked-ips' === $subview ) :
													echo 'zerospam-dashboard__menu-link--active';
endif;
												?>
							">
								<img src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-blocked.svg' ); ?>" class="zerospam-dashboard__menu-icon" />
								<?php esc_html_e( 'Blocked IPs', 'zero-spam' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url( admin_url( "$base_admin_link&subview=blocked-locations" ) ); ?>" class="zerospam-dashboard__menu-link
												<?php
												if ( 'blocked-locations' === $subview ) :
													echo 'zerospam-dashboard__menu-link--active';
endif;
												?>
							">
								<img src="<?php echo esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/img/icon-location.svg' ); ?>" class="zerospam-dashboard__menu-icon" />
							<?php esc_html_e( 'Blocked Locations', 'zero-spam' ); ?>
							</a>
						</li>
					</ul>

					<iframe src="https://www.facebook.com/plugins/page.php?href=https%3A%2F%2Fwww.facebook.com%2Fzerospamorg&tabs=timeline&width=300&height=635&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true&appId=2704301689814493" width="300" height="635" style="border:none;overflow:hidden;margin-bottom:40px;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>

					<a class="twitter-timeline" data-height="604" href="https://twitter.com/ZeroSpamOrg?ref_src=twsrc%5Etfw">Tweets by ZeroSpamOrg</a>
					<?php
					wp_enqueue_script(
						'zerospam-twitter-widgets',
						'https://platform.twitter.com/widgets.js',
						array(),
						ZEROSPAM_VERSION,
						array(
							'in_footer' => true,
							'strategy'  => 'async',
						)
					);
					?>
				</div>
				<div class="zerospam-dashboard__col">
					<?php if ( '' !== $notice_message ) : ?>
						<div class="zerospam-block zerospam-block--notice zerospam-block--<?php echo esc_attr( $notice_type ); ?>">
							<div class="zerospam-block__content">
								<?php echo esc_html( $notice_message ); ?>
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
											<p><a href="https://wordpress.org/support/plugin/zero-spam/reviews/?filter=5" target="_blank" rel="noreferrer noopener" class="button button-primary"><?php esc_html_e( 'Submit Review', 'zero-spam' ); ?> &#8594;</a></p>
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
											<strong><?php esc_html_e( 'Still seeing spam/malicious activity?', 'zero-spam' ); ?></strong><br />
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
								printf(
									wp_kses(
										/* translators: %s: url */
										__( 'When using .htaccess &amp; due to <a href="%s" target="_blank" rel="noreferrer noopener">character limit restrictions</a>, <strong>no more than 170 blocked IP addresses recommended</strong>.', 'zero-spam' ),
										array(
											'strong' => array(),
											'a'      => array(
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
										<?php echo wp_kses( __( '<strong>Blocking locations is currently disabled.</strong> A valid ipstack API key or IPinfo access token is required.', 'zero-spam' ), array( 'strong' => array() ) ); ?>
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
								<?php $table_data->search_box( __( 'Search locations', 'zero-spam' ), 'search-ip' ); ?>
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
				<h3 class="zerospam-block__headline"><?php esc_html_e( 'Add/Update Blocked IP', 'zero-spam' ); ?></h3>
				<div class="zerospam-block__content">
					<?php require ZEROSPAM_PATH . 'includes/templates/admin-block-ip.php'; ?>
				</div>
			</div>
		</div>

		<div class="zerospam-modal zerospam-modal-block" id="zerospam-block-location">
			<button class="zerospam-close-modal" aria-label="<?php echo esc_attr( __( 'Close Modal', 'zero-spam' ) ); ?>"></button>

			<div class="zerospam-block">
				<h3 class="zerospam-block__headline"><?php esc_html_e( 'Add/Update Blocked Location', 'zero-spam' ); ?></h3>
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
