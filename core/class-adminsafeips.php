<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Admin_Safe_IPs to this file name.
/**
 * Admin_Safe_IPs class
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Administrator addresses that Zero Spam never locks out.
 *
 * When an administrator activates the plugin, and once after updating to the
 * version that introduced this (Migrations, key `admin_safe_ip`), their
 * public IP is saved in the non-autoloaded option `zerospam_admin_safe_ips`.
 * Only the lockout checks read it (Access::get_access(), and only when a
 * block applies): blocked IPs, blocked locations and the reputation checks
 * never lock that address out. Everything sent from it (comments,
 * registrations, forms) is still checked and logged, and the IP Whitelist
 * setting is never changed. Nothing expires, but only the 10 most recent
 * addresses are kept; the Settings page lists them under the IP Whitelist
 * with a Remove control. In an .htaccess block mode the deny rules leave
 * them out too (Utilities::htaccess_rules()), and are rewritten when the
 * list changes.
 *
 * Only a public IP (see is_public_ip()) of a user who can manage options is
 * saved, never twice. Behind a proxy Zero Spam does not trust yet, the IP it
 * sees is the proxy's, and saving it would exempt every visitor from
 * lockouts: such requests (see is_untrusted_proxy_request()) are skipped, and
 * the migration stays pending until a request without that problem, for
 * example once the proxy is configured as trusted.
 *
 * Multisite: the list is per site. A site activation saves to that site, a
 * network activation to the main site; other sites get the IP of the first
 * administrator who visits them after the update (the migration runs per
 * site). A network-managed IP Whitelist does not matter: this list is separate.
 */
final class Admin_Safe_IPs {

	/**
	 * Option holding the saved addresses (a list of IP strings, not autoloaded).
	 *
	 * @var string
	 */
	const OPTION = 'zerospam_admin_safe_ips';

	/**
	 * Option holding the pending notice: the IP saved and the user it was saved for.
	 *
	 * @var string
	 */
	const NOTICE_OPTION = 'zerospam_admin_safe_ip_notice';

	/**
	 * The admin-post.php action and nonce action that dismisses the notice.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'zerospam_dismiss_admin_safe_ip_notice';

	/**
	 * The admin-post.php action that removes an address; its nonce action is this plus `|{ip}`.
	 *
	 * @var string
	 */
	const REMOVE_ACTION = 'zerospam_remove_admin_safe_ip';

	/**
	 * Most addresses kept; the oldest is dropped when a new one is saved.
	 *
	 * @var int
	 */
	const MAX_IPS = 10;

	/**
	 * Registers the notice, the Settings page list and their handlers.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss_notice' ) );
		add_action( 'admin_post_' . self::REMOVE_ACTION, array( $this, 'remove_ip' ) );
		add_action( 'zerospam_setting_field_after', array( $this, 'settings_list' ) );
	}

	/**
	 * Saves the activating administrator's address.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 */
	public static function on_activation( bool $network_wide ): void {
		if ( $network_wide && is_multisite() && ! is_main_site() ) {
			switch_to_blog( get_main_site_id() );
			self::protect_current_admin();
			restore_current_blog();

			return;
		}

		self::protect_current_admin();
	}

	/**
	 * Update migration: saves the address of the first administrator to load the site after the update.
	 *
	 * Cron runs, requests without a visitor IP (WP-CLI, even with --user) and
	 * requests through a proxy Zero Spam does not trust leave it pending.
	 *
	 * @return bool False while it should stay pending.
	 */
	public static function migrate(): bool {
		if ( wp_doing_cron() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$ip = User::get_ip();
		if ( ! is_string( $ip ) || self::is_untrusted_proxy_request( $ip ) ) {
			return false;
		}

		self::protect_current_admin();

		return true;
	}

	/**
	 * Saves the current administrator's address on the current site.
	 *
	 * @return bool Whether the address was added.
	 */
	public static function protect_current_admin(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$ip = User::get_ip();
		if ( ! is_string( $ip ) || ! self::is_public_ip( $ip ) || self::is_untrusted_proxy_request( $ip ) ) {
			return false;
		}

		/**
		 * Filters whether the activating or updating administrator's address is
		 * saved as one Zero Spam never locks out (Admin_Safe_IPs).
		 *
		 * @since 5.7.14
		 *
		 * @param bool   $enabled Whether to save it. Default true.
		 * @param string $ip      The administrator's IP address.
		 */
		if ( ! apply_filters( 'zerospam_admin_safe_ip_enabled', true, $ip ) ) {
			return false;
		}

		$ips = self::get_ips();
		if ( in_array( $ip, $ips, true ) ) {
			return false;
		}

		$ips[] = $ip;
		update_option( self::OPTION, array_slice( $ips, -self::MAX_IPS ), false );
		Utilities::refresh_htaccess();

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
	 * The saved addresses of the current site.
	 *
	 * @return string[]
	 */
	public static function get_ips(): array {
		$ips = get_option( self::OPTION, array() );

		if ( ! is_array( $ips ) ) {
			return array();
		}

		return array_values( array_filter( $ips, 'is_string' ) );
	}

	/**
	 * Whether an address is saved on the current site.
	 *
	 * @param string $ip IP address.
	 */
	public static function contains( string $ip ): bool {
		return '' !== $ip && in_array( $ip, self::get_ips(), true );
	}

	/**
	 * Shows the notice to the administrator whose address was saved.
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
		<div class="notice notice-info" id="zerospam-admin-safe-ip-notice">
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: IP address, 2: URL of the Zero Spam settings page */
						__( 'Your IP address (%1$s) was saved, so you can\'t be locked out by Zero Spam from this address. Visitors from it are still checked. You can remove it under <a href="%2$s">Settings → Zero Spam → Settings</a>.', 'zero-spam' ),
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
	 * Lists the saved addresses under the IP Whitelist setting, each with a Remove control.
	 *
	 * Links, not buttons: the list sits inside the settings form.
	 *
	 * @param string $field_key Setting key whose control was just rendered.
	 */
	public function settings_list( $field_key ): void {
		if ( 'ip_whitelist' !== $field_key || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ips = self::get_ips();
		if ( ! $ips ) {
			return;
		}
		?>
		<div class="zerospam-admin-safe-ips">
			<p><strong id="zerospam-admin-safe-ips-title"><?php esc_html_e( 'Administrator addresses that can never be locked out', 'zero-spam' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Saved when an administrator activated or updated Zero Spam. Zero Spam never blocks these addresses from the site, but still checks comments, registrations and forms sent from them.', 'zero-spam' ); ?></p>
			<ul aria-labelledby="zerospam-admin-safe-ips-title">
				<?php
				foreach ( $ips as $ip ) :
					$remove_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::REMOVE_ACTION . '&ip=' . rawurlencode( $ip ) ), self::REMOVE_ACTION . '|' . $ip );
					/* translators: %s: IP address */
					$remove_label = sprintf( __( 'Remove %s from the administrator addresses that can never be locked out', 'zero-spam' ), $ip );
					?>
					<li>
						<code><?php echo esc_html( $ip ); ?></code>
						<a class="button button-small" href="<?php echo esc_url( $remove_url ); ?>" aria-label="<?php echo esc_attr( $remove_label ); ?>"><?php esc_html_e( 'Remove', 'zero-spam' ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Removes a saved address (admin-post.php, capability and nonce checked).
	 */
	public function remove_ip(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'zero-spam' ), '', array( 'response' => 403 ) );
		}

		$ip = isset( $_GET['ip'] ) && is_string( $_GET['ip'] ) ? sanitize_text_field( wp_unslash( $_GET['ip'] ) ) : '';
		check_admin_referer( self::REMOVE_ACTION . '|' . $ip );

		$remaining = array_values( array_diff( self::get_ips(), array( $ip ) ) );
		if ( $remaining ) {
			update_option( self::OPTION, $remaining, false );
		} else {
			delete_option( self::OPTION );
		}

		// In .htaccess mode the address may be blocked by the web server again.
		Utilities::refresh_htaccess();

		$notice = get_option( self::NOTICE_OPTION );
		if ( is_array( $notice ) && isset( $notice['ip'] ) && $ip === $notice['ip'] ) {
			delete_option( self::NOTICE_OPTION );
		}

		$referer = wp_get_referer();
		wp_safe_redirect( $referer ? $referer : admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=settings' ) );
		exit;
	}

	/**
	 * Whether the request came through a proxy that Zero Spam does not trust.
	 *
	 * True when the request carries a forwarding header (User::PROXY_HEADERS)
	 * but the visitor IP is still the connecting address (REMOTE_ADDR, in
	 * User::get_remote_addr()'s canonical form), so no trusted-proxy resolution
	 * happened and the IP is most likely the proxy's.
	 *
	 * @param string $ip Visitor IP from User::get_ip().
	 */
	private static function is_untrusted_proxy_request( string $ip ): bool {
		$forwarded = false;
		foreach ( User::PROXY_HEADERS as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$forwarded = true;
				break;
			}
		}

		if ( ! $forwarded ) {
			return false;
		}

		$remote = User::get_remote_addr();

		return '' !== $remote && User::normalize_ip( $ip ) === $remote;
	}

	/**
	 * Whether an IP is public: not private, loopback, link-local, reserved,
	 * shared (CGNAT) or documentation space (RFC 6890 non-global ranges).
	 *
	 * The 6to4 ranges (192.88.99.0/24, 2002::/16) are rejected explicitly,
	 * because PHP versions disagree on them. User has a private-range check
	 * of its own, but it accepts the IPv4 documentation ranges.
	 *
	 * @param string $ip IP address.
	 */
	private static function is_public_ip( string $ip ): bool {
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE ) ) {
			return false;
		}

		return ! Utilities::ip_in_range( $ip, '192.88.99.0/24' ) && ! Utilities::ip_in_range( $ip, '2002::/16' );
	}
}
