<?php
/**
 * Admin class
 *
 * @package ZeroSpam
 */

namespace ZeroSpam\Core\Admin;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Admin
 */
class Admin {
	/**
	 * Constructor
	 */
	public function __construct() {
		new \ZeroSpam\Core\Admin\Settings();
		new \ZeroSpam\Core\Admin\Dashboard();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 */
	public function init() {
		add_filter( 'plugin_action_links_' . ZEROSPAM_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'wp_ajax_zerospam_dismiss_promo_notice', array( $this, 'ajax_dismiss_promo_notice' ) );
		add_action( 'wp_ajax_zerospam_track_promo_click', array( $this, 'ajax_track_promo_click' ) );
	}

	/**
	 * Register the admin dashboard widget
	 */
	public function register_dashboard_widget() {
		$selected_user_roles = \ZeroSpam\Core\Settings::get_settings( 'widget_visibility' );
		$user                = wp_get_current_user();
		$roles               = (array) $user->roles;

		if ( is_array( $selected_user_roles ) && is_array( $roles ) ) {
			if ( ! empty( array_intersect( $roles, $selected_user_roles ) ) ) {
				wp_add_dashboard_widget(
					'zerospam_dashboard_widget',
					__( 'Zero Spam for WordPress', 'zero-spam' ),
					array( $this, 'dashboard_widget' )
				);
			}
		}
	}

	/**
	 * Output for the admin dashboard widget
	 */
	public function dashboard_widget() {
		$settings = \ZeroSpam\Core\Settings::get_settings();
		$entries  = \ZeroSpam\Includes\DB::query( 'log' );

		if ( 'enabled' !== $settings['zerospam']['value'] || empty( $settings['zerospam_license']['value'] ) ) {
			?>
			<div style="background-color: #f6f7f7; padding: 25px; margin-bottom: 20px; border-left: 4px solid #72aee6;">
				<h3>
					<?php
					printf(
						wp_kses(
							/* translators: %s: Zero Spam API link */
							__( '<strong>Super-charge WordPress Zero Spam with a <a href="%s" target="_blank" rel="noopener noreferrer">Zero Spam API License</a>.</strong>', 'zero-spam' ),
							array(
								'a'      => array(
									'target' => array(),
									'href'   => array(),
									'rel'    => array(),
								),
								'strong' => array(),
							)
						),
						esc_url( ZEROSPAM_URL . 'subscribe/' )
					);
					?>
				</h3>
				<?php
				printf(
					wp_kses(
						/* translators: %s: Zero Spam API link */
						__( '<p><strong>Enable enhanced protection</strong> and super-charge your site with the power of a global detection network that monitors traffic and usage in real-time to detect malicious activity.</p>', 'zero-spam' ),
						array(
							'a'      => array(
								'target' => array(),
								'href'   => array(),
								'rel'    => array(),
								'style'  => array(),
							),
							'p'      => array(),
							'strong' => array(),
						)
					)
				);
				?>
				<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>subscribe/?utm_source=wordpress_zero_spam&utm_medium=dashboard_widget&utm_campaign=license" target="_blank" rel="noreferrer noopener" class="button button-primary"><?php esc_html_e( 'Get a Zero Spam License', 'zero-spam' ); ?></a>
				<a href="<?php echo esc_url( ZEROSPAM_URL ); ?>?utm_source=wordpress_zero_spam&utm_medium=dashboard_widget&utm_campaign=license" target="_blank" rel="noreferrer noopener" class="button button-secondary"><?php esc_html_e( 'Learn More', 'zero-spam' ); ?></a>
			</div>
			<?php
		}

		require ZEROSPAM_PATH . 'includes/templates/admin-line-chart.php';
	}

	/**
	 * Display admin notices
	 */
	public function admin_notices() {
		// Clean up old transients from previous implementation (temporary cleanup code).
		$current_user_id = get_current_user_id();
		delete_transient( 'zerospam_promo_shown_' . $current_user_id );

		// Get the Enhanced Protection settings using the Settings API for consistency.
		$settings = \ZeroSpam\Core\Settings::get_settings();
		
		// Check if Enhanced Protection is enabled.
		$zerospam_enabled = isset( $settings['zerospam']['value'] ) && 'enabled' === $settings['zerospam']['value'];
		
		// Check for license key - include constant check like the settings definition does.
		$zerospam_license_key = false;
		if ( defined( 'ZEROSPAM_LICENSE_KEY' ) && ZEROSPAM_LICENSE_KEY ) {
			$zerospam_license_key = ZEROSPAM_LICENSE_KEY;
		} elseif ( ! empty( $settings['zerospam_license']['value'] ) ) {
			$zerospam_license_key = $settings['zerospam_license']['value'];
		}

		// Display enhanced promo notice or error notice based on state.
		if ( $zerospam_enabled && ! $zerospam_license_key ) {
			// Enhanced Protection enabled but no license key - show error.
			$this->display_license_error_notice();
		} elseif ( ! $zerospam_enabled && ! $zerospam_license_key ) {
			// Enhanced Protection disabled and no license - show promo notice.
			$this->display_promo_notice();
		}

		// Check if the plugin has been auto-configured.
		$configured = get_option( 'zerospam_configured' );
		
		if ( ! $configured ) {
			?>
			<div class="notice notice-info">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %1$s: Replaced with the Zero Spam settings page URL */
							__( '<strong>Thanks for installing Zero Spam for WordPress!</strong> Visit the <a href="%1$s">settings page</a> to configure your site\'s protection level or <strong><a href="%2$s">click here</a> to automatically configure recommended settings</strong>.', 'zero-spam' ),
							array(
								'strong' => array(),
								'a'      => array(
									'href'   => array(),
									'target' => array(),
								),
							)
						),
						esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ),
						wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=autoconfigure' ), 'autoconfigure', 'zero-spam' )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Display API monitoring feature notice (one-time).
		$this->display_api_monitoring_notice();
	}

	/**
	 * Display license error notice
	 */
	private function display_license_error_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: %1$s: Replaced with the Zero Spam settings page URL */
						__( 'Zero Spam Enhanced Protection is currently enabled, but <strong>missing a valid license key</strong>. <a href="%1$s">Add your license key</a> to enable enhanced site protection.', 'zero-spam' ),
						array(
							'strong' => array(),
							'a'      => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
					esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=zerospam' ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display promotional notice for Enhanced Protection
	 */
	private function display_promo_notice() {
		$current_user_id = get_current_user_id();
		$screen          = get_current_screen();
		
		$is_zerospam_page = $screen && ( 
			strpos( $screen->id, 'wordpress-zero-spam' ) !== false || 
			'dashboard' === $screen->id 
		);

		// Only show on dashboard or Zero Spam pages.
		if ( ! $is_zerospam_page ) {
			return;
		}

		// Check if user should see the promo notice.
		if ( ! $this->should_display_promo_notice() ) {
			return;
		}

		// Build the pricing URL with UTM parameters and discount code.
		$pricing_url = add_query_arg(
			array(
				'utm_source'   => 'wordpress',
				'utm_medium'   => 'admin_notice',
				'utm_campaign' => 'license_promo',
				'coupon'       => 'WELCOME15',
			),
			ZEROSPAM_URL . 'pricing/'
		);

		$comparison_url = add_query_arg(
			array(
				'utm_source'   => 'wordpress',
				'utm_medium'   => 'admin_notice',
				'utm_campaign' => 'license_promo_comparison',
			),
			ZEROSPAM_URL . 'features/'
		);

		// Fire tracking hook.
		do_action( 'zerospam_promo_notice_displayed', $current_user_id );
		?>
		<style>
		/* Critical inline styles for promo notice */
		#zerospam-promo-wrapper {
			margin: 20px 20px 20px 0 !important;
		}
		.zerospam-promo-notice {
			position: relative;
			background: #fff;
			border-left: 4px solid #ff2929;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			opacity: 1;
			max-width: 100%;
			box-sizing: border-box;
			margin: 0 !important;
		}
		.zerospam-promo-notice__container {
			position: relative;
			padding: 24px;
			box-sizing: border-box;
		}
		.zerospam-promo-notice__dismiss {
			position: absolute;
			top: 12px;
			right: 12px;
			background: transparent;
			border: none;
			color: #787c82;
			cursor: pointer;
			font-size: 24px;
			line-height: 1;
			padding: 4px;
			transition: color 0.2s ease;
		}
		.zerospam-promo-notice__dismiss:hover {
			color: #ff2929;
		}
		.zerospam-promo-notice__content {
			display: flex;
			gap: 24px;
			align-items: flex-start;
		}
		.zerospam-promo-notice__icon {
			flex-shrink: 0;
		}
		.zerospam-promo-notice__icon svg {
			width: 48px;
			height: auto;
			display: block;
		}
		.zerospam-promo-notice__main {
			flex: 1;
			min-width: 0;
			max-width: 100%;
		}
		.zerospam-promo-notice__headline {
			margin: 0 0 8px 0;
			font-size: 18px;
			font-weight: 600;
			color: #3f0008;
			line-height: 1.4;
		}
		.zerospam-promo-notice__status {
			display: inline-block;
			margin-left: 8px;
			font-size: 14px;
			font-weight: 500;
			color: #d63638;
			background: #fcf0f1;
			padding: 2px 8px;
			border-radius: 3px;
		}
		.zerospam-promo-notice__description {
			margin: 0 0 16px 0;
			font-size: 14px;
			line-height: 1.6;
			color: #50575e;
		}
		.zerospam-promo-notice__features {
			list-style: none;
			margin: 0 0 20px 0;
			padding: 0;
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 8px;
		}
		.zerospam-promo-notice__features li {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 14px;
			color: #50575e;
		}
		.zerospam-promo-notice__features svg {
			flex-shrink: 0;
			color: #69b86b;
		}
		.zerospam-promo-notice__actions {
			margin: 0 0 16px 0;
		}
		.zerospam-promo-notice__cta {
			display: inline-block;
			background: #ff2929;
			color: #fff !important;
			padding: 12px 24px;
			border-radius: 4px;
			text-decoration: none;
			font-size: 15px;
			font-weight: 600;
			transition: all 0.2s ease;
			box-shadow: 0 2px 4px rgba(255, 41, 41, 0.2);
		}
		.zerospam-promo-notice__cta:hover {
			background: #be0000;
			color: #fff !important;
			transform: translateY(-2px);
			box-shadow: 0 4px 8px rgba(255, 41, 41, 0.3);
		}
		.zerospam-promo-notice__discount {
			display: inline-block;
			margin-left: 12px;
			padding: 8px 12px;
			background: #f6f7f7;
			border-radius: 3px;
			font-size: 13px;
			color: #50575e;
			vertical-align: middle;
		}
		.zerospam-promo-notice__discount strong {
			color: #ff2929;
			font-weight: 700;
			font-family: 'Courier New', monospace;
		}
		.zerospam-promo-notice__footer {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 12px;
			padding-top: 12px;
			border-top: 1px solid #dcdcde;
			font-size: 13px;
		}
		.zerospam-promo-notice__guarantee {
			color: #50575e;
		}
		.zerospam-promo-notice__learn-more {
			color: #be0000;
			text-decoration: none;
			font-weight: 500;
			transition: color 0.2s ease;
		}
		.zerospam-promo-notice__learn-more:hover {
			color: #ff2929;
			text-decoration: underline;
		}
		@media screen and (max-width: 782px) {
			#zerospam-promo-wrapper {
				margin: 5px 0 20px 0 !important;
			}
			.zerospam-promo-notice {
				width: 100% !important;
				max-width: 100% !important;
			}
			.zerospam-promo-notice__container {
				padding: 16px;
			}
			.zerospam-promo-notice__content {
				flex-direction: column;
				gap: 12px;
			}
			.zerospam-promo-notice__icon {
				display: none;
			}
			.zerospam-promo-notice__headline {
				font-size: 16px;
				padding-right: 30px;
			}
			.zerospam-promo-notice__status {
				display: block;
				margin: 8px 0 0 0;
			}
			.zerospam-promo-notice__description {
				font-size: 13px;
			}
			.zerospam-promo-notice__features {
				grid-template-columns: 1fr;
				gap: 6px;
				margin-bottom: 16px;
			}
			.zerospam-promo-notice__features li {
				font-size: 13px;
			}
			.zerospam-promo-notice__actions {
				margin-bottom: 12px;
			}
			.zerospam-promo-notice__cta {
				display: block;
				width: 100%;
				text-align: center;
				box-sizing: border-box;
			}
			.zerospam-promo-notice__discount {
				display: block;
				margin: 8px 0 0 0;
				text-align: center;
			}
			.zerospam-promo-notice__footer {
				flex-direction: column;
				align-items: flex-start;
				gap: 8px;
			}
		}
		@media screen and (max-width: 600px) {
			#zerospam-promo-wrapper {
				margin: 5px 0 15px 0 !important;
			}
			.zerospam-promo-notice__container {
				padding: 12px;
			}
			.zerospam-promo-notice__headline {
				font-size: 15px;
			}
		}
		</style>
		<div id="zerospam-promo-wrapper">
		<div class="zerospam-promo-notice">
			<div class="zerospam-promo-notice__container">
				<button type="button" class="zerospam-promo-notice__dismiss" aria-label="<?php esc_attr_e( 'Dismiss notice', 'zero-spam' ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'zero-spam' ); ?></span>
					<span aria-hidden="true">&times;</span>
				</button>

				<div class="zerospam-promo-notice__content">
					<div class="zerospam-promo-notice__icon">
						<svg width="48" height="auto" viewBox="0 0 512 435" xmlns="http://www.w3.org/2000/svg">
							<g transform="translate(256.000000, 217.500000) scale(-1, 1) translate(-256.000000, -217.500000)">
								<polygon fill="#FFA17C" points="176 351 176 435 0 435 0 351"/>
								<polygon fill="#FF2929" points="256 351 256 435 176 435 176 351"/>
								<polygon fill="#BE0000" points="336 351 336 435 256 435 256 351"/>
								<polygon fill="#63000D" points="512 351 512 435 336 435 336 351"/>
								<polygon fill="#FF2929" points="96 239 96 351 0 351 0 239"/>
								<polygon fill="#BE0000" points="256 239 256 351 96 351 96 239"/>
								<polygon fill="#840012" points="416 239 416 351 256 351 256 239"/>
								<polygon fill="#BE0000" points="512 239 512 351 416 351 416 239"/>
								<path d="M176,112 L15,112 C6.716,112 0,118.716 0,127 L0,239 L176,239 L176,112 Z" fill="#FF7038"/>
								<polygon fill="#FF2929" points="256 239 176 239 176 112 256 112"/>
								<polygon fill="#BE0000" points="336 239 256 239 256 112 336 112"/>
								<path d="M512,127 L512,239 L336,239 L336,112 L497,112 C505.28,112 512,118.72 512,127 Z" fill="#63000D"/>
								<path d="M241,0 L96,0 C87.716,0 81,5.29712676 81,11.8309859 C81,32.7599735 81,48.4567141 81,58.9212079 C81,68.0873871 81,85.8366558 81,112.169014 L256,112.169014 L256,11.8309859 C256,5.29712676 249.284,0 241,0 Z" fill="#BE0000"/>
							</g>
						</svg>
					</div>

					<div class="zerospam-promo-notice__main">
						<h3 class="zerospam-promo-notice__headline">
							<?php esc_html_e( 'Your Site Isn\'t Fully Protected Yet', 'zero-spam' ); ?>
							<span class="zerospam-promo-notice__status"><?php esc_html_e( '(Enhanced Protection: Disabled)', 'zero-spam' ); ?></span>
						</h3>
						
						<p class="zerospam-promo-notice__description">
							<?php
							printf(
								wp_kses(
									/* translators: Numbers represent site count and IP count */
									__( 'Join <strong>30,000+ sites</strong> using Enhanced Protection to stop threats in real-time. Our global detection network monitors <strong>10+ million malicious IPs</strong> to keep your site secure.', 'zero-spam' ),
									array( 'strong' => array() )
								)
							);
							?>
						</p>

						<ul class="zerospam-promo-notice__features">
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
								<?php esc_html_e( 'Real-time malicious IP blocking', 'zero-spam' ); ?>
							</li>
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
								<?php esc_html_e( 'Prevent DDoS attacks', 'zero-spam' ); ?>
							</li>
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
								<?php esc_html_e( 'Stop stolen credit card testing', 'zero-spam' ); ?>
							</li>
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
								<?php esc_html_e( 'Detailed threat intelligence reports', 'zero-spam' ); ?>
							</li>
							<li>
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
								<?php esc_html_e( '24/7 automated protection', 'zero-spam' ); ?>
							</li>
						</ul>

						<div class="zerospam-promo-notice__actions">
							<a href="<?php echo esc_url( $pricing_url ); ?>" class="zerospam-promo-notice__cta" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Get Enhanced Protection – Save 15% for Life', 'zero-spam' ); ?>
							</a>
							<div class="zerospam-promo-notice__discount">
								<?php
								printf(
									wp_kses(
										/* translators: %s: discount code */
										__( 'Use code: <strong>WELCOME15</strong> at checkout for lifetime discount', 'zero-spam' ),
										array( 'strong' => array() )
									)
								);
								?>
							</div>
						</div>

						<div class="zerospam-promo-notice__footer">
							<span class="zerospam-promo-notice__guarantee">
								<?php esc_html_e( 'Cancel anytime • No long-term contracts', 'zero-spam' ); ?>
							</span>
							<a href="<?php echo esc_url( $comparison_url ); ?>" class="zerospam-promo-notice__learn-more" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Compare Free vs Enhanced →', 'zero-spam' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function() {
			// Handle dismiss button click.
			var dismissBtn = document.querySelector('.zerospam-promo-notice__dismiss');
			if (dismissBtn) {
				dismissBtn.addEventListener('click', function(e) {
					e.preventDefault();
					
					var notice = document.querySelector('.zerospam-promo-notice');
					if (notice) {
						notice.style.opacity = '0';
						setTimeout(function() {
							notice.remove();
						}, 200);
					}

					// Send AJAX request to dismiss.
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxurl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=zerospam_dismiss_promo_notice&nonce=<?php echo esc_js( wp_create_nonce( 'zerospam_dismiss_promo' ) ); ?>');
				});
			}

			// Track CTA clicks.
			var ctaBtn = document.querySelector('.zerospam-promo-notice__cta');
			if (ctaBtn) {
				ctaBtn.addEventListener('click', function() {
					// Fire tracking hook via AJAX if needed.
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxurl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=zerospam_track_promo_click&type=cta&nonce=<?php echo esc_js( wp_create_nonce( 'zerospam_track_promo' ) ); ?>');
				});
			}

			// Track comparison link clicks.
			var learnMoreLink = document.querySelector('.zerospam-promo-notice__learn-more');
			if (learnMoreLink) {
				learnMoreLink.addEventListener('click', function() {
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxurl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send('action=zerospam_track_promo_click&type=learn_more&nonce=<?php echo esc_js( wp_create_nonce( 'zerospam_track_promo' ) ); ?>');
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Check if promo notice should be displayed
	 *
	 * @return bool
	 */
	private function should_display_promo_notice() {
		$current_user_id = get_current_user_id();

		// Check if dismissed within the last 30 days.
		$dismissed_time = get_user_meta( $current_user_id, 'zerospam_promo_dismissed', true );
		
		if ( $dismissed_time ) {
			$days_since_dismissed = ( time() - $dismissed_time ) / DAY_IN_SECONDS;
			if ( $days_since_dismissed < 30 ) {
				return false;
			}
		}

		// Check if plugin activated at least 3 days ago.
		$activation_time = get_option( 'zerospam_activation_time' );
		
		// FIX: If activation time is less than 3 days ago (existing install issue), reset it
		if ( $activation_time ) {
			$days_since_activation = ( time() - $activation_time ) / DAY_IN_SECONDS;
			if ( $days_since_activation < 3 ) {
				// This was set too recently (probably from our earlier attempt)
				// Reset it to 4 days ago for existing installations
				$activation_time = time() - ( 4 * DAY_IN_SECONDS );
				update_option( 'zerospam_activation_time', $activation_time );
			}
		} elseif ( ! $activation_time ) {
			// For existing installations, set activation time to 4 days ago
			// so the notice shows immediately. For new installations, this will
			// be set during plugin activation to the actual activation time.
			$activation_time = time() - ( 4 * DAY_IN_SECONDS );
			update_option( 'zerospam_activation_time', $activation_time );
		}

		$days_since_activation = ( time() - $activation_time ) / DAY_IN_SECONDS;
		
		if ( $days_since_activation < 3 ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle AJAX request to dismiss promo notice
	 */
	public function ajax_dismiss_promo_notice() {
		check_ajax_referer( 'zerospam_dismiss_promo', 'nonce' );

		if ( ! current_user_can( 'administrator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'zero-spam' ) ) );
		}

		$current_user_id = get_current_user_id();
		update_user_meta( $current_user_id, 'zerospam_promo_dismissed', time() );

		// Fire tracking hook.
		do_action( 'zerospam_promo_notice_dismissed', $current_user_id );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed', 'zero-spam' ) ) );
	}

	/**
	 * Handle AJAX request to track promo clicks
	 */
	public function ajax_track_promo_click() {
		check_ajax_referer( 'zerospam_track_promo', 'nonce' );

		if ( ! current_user_can( 'administrator' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'zero-spam' ) ) );
		}

		$click_type      = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$current_user_id = get_current_user_id();

		// Fire tracking hook.
		do_action( 'zerospam_promo_notice_clicked', $current_user_id, $click_type );

		wp_send_json_success( array( 'message' => __( 'Click tracked', 'zero-spam' ) ) );
	}

	/**
	 * Display API monitoring feature notice (one-time)
	 */
	private function display_api_monitoring_notice() {
		// Only show to admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if notice has been dismissed.
		if ( get_option( 'zerospam_api_monitoring_notice_dismissed', false ) ) {
			return;
		}

		// Check if Enhanced Protection is enabled (no point showing if they don't have a license).
		$settings = \ZeroSpam\Core\Settings::get_settings();
		if ( empty( $settings['zerospam']['value'] ) || 'enabled' !== $settings['zerospam']['value'] ) {
			return;
		}

		// Check if they have a license key.
		$has_license = ! empty( $settings['zerospam_license']['value'] ) || defined( 'ZEROSPAM_LICENSE_KEY' );
		if ( ! $has_license ) {
			return;
		}

		// Only show on dashboard or Zero Spam pages.
		$screen = get_current_screen();
		if ( ! $screen || ( 'dashboard' !== $screen->id && false === strpos( $screen->id, 'wordpress-zero-spam' ) ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin.php?page=wordpress-zero-spam-settings&zerospam-action=dismiss-api-monitoring-notice' ),
			'dismiss-api-monitoring-notice',
			'zero-spam'
		);

		$settings_url = admin_url( 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring' );
		?>
		<div class="notice notice-info is-dismissible" id="zerospam-api-monitoring-notice">
			<p><strong><?php esc_html_e( 'New Feature: API Usage Monitoring & Alerts!', 'zero-spam' ); ?></strong></p>
			<p>
				<?php
				esc_html_e( 'Track your Zero Spam API usage, monitor quota consumption, and receive proactive alerts before hitting limits. Get insights into cache performance, detect anomalies, and ensure uninterrupted protection.', 'zero-spam' );
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Enable API Monitoring', 'zero-spam' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Dismiss', 'zero-spam' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Scripts
	 */
	public function scripts( $hook_suffix ) {
		if (
			'dashboard_page_wordpress-zero-spam-dashboard' === $hook_suffix ||
			'settings_page_wordpress-zero-spam-settings' === $hook_suffix
		) {
			wp_enqueue_style(
				'zerospam-admin',
				plugin_dir_url( ZEROSPAM ) . 'assets/css/admin.css',
				false,
				ZEROSPAM_VERSION
			);

			wp_enqueue_script(
				'zerospam-admin',
				plugin_dir_url( ZEROSPAM ) . 'assets/js/admin.js',
				array(),
				ZEROSPAM_VERSION,
				true
			);
		}
	}

	/**
	 * Plugin action links
	 *
	 * Adds action links to the plugin list table
	 *
	 * Fired by `plugin_action_links` filter.
	 *
	 * @param array $links An array of plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ), __( 'Settings', 'zero-spam' ) );

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Plugin row meta.
	 *
	 * Adds row meta links to the plugin list table
	 *
	 * Fired by `plugin_row_meta` filter.
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata, including
	 *                            the version, author, author URI, and plugin URI.
	 * @param string $plugin_file Path to the plugin file, relative to the plugins
	 *                            directory.
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( ZEROSPAM_PLUGIN_BASE === $plugin_file ) {
			$row_meta = array(
				'docs' => '<a href="https://github.com/bmarshall511/wordpress-zero-spam/wiki" aria-label="' . esc_attr( __( 'View Documentation', 'zero-spam' ) ) . '" target="_blank">' . __( 'Docs & FAQs', 'zero-spam' ) . '</a>',
			);

			$plugin_meta = array_merge( $plugin_meta, $row_meta );
		}

		return $plugin_meta;
	}

	/**
	 * Admin footer text.
	 *
	 * Modifies the "Thank you" text displayed in the admin footer.
	 *
	 * Fired by `admin_footer_text` filter.
	 *
	 * @param string $footer_text The content that will be printed.
	 */
	public function admin_footer_text( $footer_text ) {
		$current_screen     = get_current_screen();
		$is_zerospam_screen = ( $current_screen && false !== strpos( $current_screen->id, 'wordpress-zero-spam' ) );

		if ( $is_zerospam_screen ) {
			$footer_text = sprintf(
				/* translators: 1: Elementor, 2: Link to plugin review */
				__( 'Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'zero-spam' ),
				'<strong>' . __( 'Zero Spam for WordPress', 'zero-spam' ) . '</strong>',
				'<a href="https://wordpress.org/plugins/zero-spam/#reviews" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}
}
