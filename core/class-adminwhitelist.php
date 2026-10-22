<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Admin_Whitelist to this file name.
/**
 * Admin_Whitelist class
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Adds the administrator's IP to the IP Whitelist so they can't lock themselves out.
 *
 * Runs when the plugin is activated and once after updating to the version
 * that introduced it (Migrations, key `whitelist_admin_ip`). Only a public
 * IP of a user who can manage options is added, never twice, and nothing
 * is ever removed. A one-time notice tells that administrator.
 *
 * Behind a proxy Zero Spam does not trust yet, the IP it sees is the
 * proxy's, and whitelisting it would exempt every visitor: such requests
 * (see is_untrusted_proxy_request()) are skipped, and the migration stays
 * pending until a request without that problem, for example once the proxy
 * is configured as trusted.
 *
 * Multisite: a site activation writes to that site. A network activation
 * writes to the main site only; other sites get the IP of the first
 * administrator who visits them after the update (the migration runs per
 * site). A whitelist the network administrator manages (locked, or a network
 * default the site does not override) is left alone, because a site value
 * would either be ignored or stop the site from following the network list.
 */
final class Admin_Whitelist {

	/**
	 * Option holding the pending notice: the IP added and the user it was added for.
	 *
	 * @var string
	 */
	const NOTICE_OPTION = 'zerospam_admin_whitelist_notice';

	/**
	 * The admin-post.php action and nonce action that dismisses the notice.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'zerospam_dismiss_whitelist_notice';

	/**
	 * Registers the notice and its dismiss handler.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Whitelists the activating administrator.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 */
	public static function on_activation( bool $network_wide ): void {
		if ( $network_wide && is_multisite() && ! is_main_site() ) {
			switch_to_blog( get_main_site_id() );
			self::whitelist_current_admin();
			restore_current_blog();

			return;
		}

		self::whitelist_current_admin();
	}

	/**
	 * Update migration: whitelists the first administrator to load the site after the update.
	 *
	 * Cron runs and requests without a visitor IP (WP-CLI, even with --user)
	 * leave it pending too.
	 *
	 * @return bool False while no administrator has loaded the site (the migration stays pending).
	 */
	public static function migrate(): bool {
		if ( wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$ip = User::get_ip();
		if ( ! is_string( $ip ) || self::is_untrusted_proxy_request( $ip ) ) {
			return false;
		}

		self::whitelist_current_admin();

		return true;
	}

	/**
	 * Adds the current administrator's IP to the current site's IP Whitelist.
	 *
	 * @return bool Whether the IP was added.
	 */
	public static function whitelist_current_admin(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$ip = User::get_ip();
		if ( ! is_string( $ip ) || ! self::is_public_ip( $ip ) || self::is_untrusted_proxy_request( $ip ) ) {
			return false;
		}

		/**
		 * Filters whether the administrator's IP is added to the IP Whitelist on activation and update.
		 *
		 * @param bool   $whitelist Whether to add it. Default true.
		 * @param string $ip        The administrator's IP address.
		 */
		if ( ! apply_filters( 'zerospam_whitelist_activating_admin', true, $ip ) ) {
			return false;
		}

		if ( self::is_network_managed() ) {
			return false;
		}

		$options = get_option( 'zero-spam-settings', array() );
		$options = is_array( $options ) ? $options : array();
		$current = isset( $options['ip_whitelist'] ) && is_string( $options['ip_whitelist'] ) ? $options['ip_whitelist'] : '';

		$listed = array_map( 'trim', (array) preg_split( '/\r\n|\r|\n/', $current ) );
		if ( in_array( $ip, $listed, true ) ) {
			return false;
		}

		$options['ip_whitelist'] = '' === trim( $current ) ? $ip : rtrim( $current ) . "\n" . $ip;
		update_option( 'zero-spam-settings', $options );

		update_option(
			self::NOTICE_OPTION,
			array(
				'ip'      => $ip,
				'user_id' => get_current_user_id(),
			),
			false
		);

		return true;
	}

	/**
	 * Shows the notice to the administrator whose IP was added.
	 */
	public function admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_option( self::NOTICE_OPTION );
		if (
			! is_array( $notice ) ||
			empty( $notice['ip'] ) ||
			! isset( $notice['user_id'] ) ||
			get_current_user_id() !== (int) $notice['user_id']
		) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=settings' );
		$dismiss_url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
		?>
		<div class="notice notice-info is-dismissible" id="zerospam-admin-whitelist-notice">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: IP address, 2: URL of the Zero Spam settings page */
						__( 'Your IP address (%1$s) was added to Zero Spam\'s IP Whitelist so you can\'t lock yourself out. You can change this under <a href="%2$s">Settings → Zero Spam</a>.', 'zero-spam' ),
						esc_html( (string) $notice['ip'] ),
						esc_url( $settings_url )
					),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Dismiss', 'zero-spam' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismisses the notice for good.
	 */
	public function dismiss_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'zero-spam' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::DISMISS_ACTION );

		delete_option( self::NOTICE_OPTION );

		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url() );
		exit;
	}

	/**
	 * Whether the request came through a proxy that Zero Spam does not trust.
	 *
	 * True when the request carries a forwarding header (X-Forwarded-For,
	 * X-Real-IP, True-Client-IP or CF-Connecting-IP) but the visitor IP is
	 * still REMOTE_ADDR, so no trusted-proxy resolution happened and the IP is
	 * most likely the proxy's. Reads $_SERVER only.
	 *
	 * @param string $ip Visitor IP from User::get_ip().
	 */
	private static function is_untrusted_proxy_request( string $ip ): bool {
		$forwarded = false;
		foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_CF_CONNECTING_IP' ) as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$forwarded = true;
				break;
			}
		}

		if ( ! $forwarded ) {
			return false;
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';

		return '' !== $remote && $remote === $ip;
	}

	/**
	 * Whether an IP is public: not private, loopback, link-local or reserved.
	 *
	 * @param string $ip IP address.
	 */
	private static function is_public_ip( string $ip ): bool {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Whether the network administrator manages this site's IP Whitelist.
	 *
	 * Mirrors Network_Settings::resolve_all_settings(): a locked network value
	 * always applies, and a network default applies until the site stores
	 * its own value.
	 */
	private static function is_network_managed(): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		$network = get_site_option( \ZeroSpam\Includes\Network_Settings::META_KEY, array() );
		$config  = is_array( $network ) && isset( $network['settings']['ip_whitelist'] ) && is_array( $network['settings']['ip_whitelist'] ) ? $network['settings']['ip_whitelist'] : null;

		if ( null === $config ) {
			return false;
		}

		if ( ! empty( $config['locked'] ) ) {
			return true;
		}

		$site = get_option( 'zero-spam-settings', array() );

		return isset( $config['value'] ) && ! ( is_array( $site ) && isset( $site['ip_whitelist'] ) );
	}
}
