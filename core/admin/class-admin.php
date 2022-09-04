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
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
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
					echo sprintf(
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
				echo sprintf(
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
	 * Display not configured notice
	 */
	public function admin_notices() {
		// Only display notices for administrators.
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		$messages = array();

		// Check Zero Spam license key.
		$zerospam_protection = \ZeroSpam\Core\Settings::get_settings( 'zero-spam-zerospam' );
		if ( 'enabled' === $zerospam_protection ) {
			$zerospam_license_key = \ZeroSpam\Core\Settings::get_settings( 'zerospam_license' );
			if ( ! $zerospam_license_key ) {
				$messages['license'] = array(
					'type'        => 'error',
					'dismissible' => false,
					'content'     => sprintf(
						wp_kses(
							/* translators: %1$s: Replaced with the Zero Spam settings page URL */
							__( 'Zero Spam Enhanced Protection is currenlty enabled, but <strong>missing a valid license key</strong>. <a href="%1$s">Add your license key</a> to enable enhanced site protection.', 'zero-spam' ),
							array(
								'strong' => array(),
								'a'      => array(
									'href'   => array(),
									'target' => array(),
								),
							)
						),
						esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ),
						wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=autoconfigure' ), 'autoconfigure', 'zero-spam' ),
						esc_url( ZEROSPAM_URL . 'product/premium/' )
					),
				);
			}
		}

		// Check if the plugin has been auto-configured.
		$configured = get_option( 'zerospam_configured' );
		if ( ! $configured ) {
			$messages['configuration'] = array(
				'type'        => 'info',
				'dismissible' => false,
				'content'     => sprintf(
					wp_kses(
						/* translators: %1$s: Replaced with the Zero Spam settings page URL */
						__( '<strong>Thanks for installing Zero Spam for WordPress!</strong> Visit the <a href="%1$s">setting page</a> to configure your site\'s protection level or <strong><a href="%2$s">click here</a> to automatically configure recommended settings</strong>.', 'zero-spam' ),
						array(
							'strong' => array(),
							'a'      => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					),
					esc_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) ),
					wp_nonce_url( admin_url( 'options-general.php?page=wordpress-zero-spam-settings&zerospam-action=autoconfigure' ), 'autoconfigure', 'zero-spam' ),
					esc_url( ZEROSPAM_URL . 'product/premium/' )
				),
			);
		}

		if ( $messages ) {
			$classes = array( 'notice' );
			foreach ( $messages as $key => $message ) {
				$classes[] = 'notice-' . $message['type'];
				if ( $message['dismissible'] ) {
					$classes[] = 'is-dismissible';
				}

				printf(
					'<div class="%1$s"><p>%2$s</p></div>',
					esc_attr( implode( ' ', $classes ) ),
					// @codingStandardsIgnoreLine
					$message['content']
				);
			}
		}
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
