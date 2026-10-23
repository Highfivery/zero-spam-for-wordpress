<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Shared_IP_Warning to this file name.
/**
 * Shared IP warning.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\User;
use ZeroSpam\Includes\DB;
use ZeroSpam\Includes\Migrations;
use ZeroSpam\Includes\Network_Settings;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Warns administrators when visitors appear to share one IP address.
 *
 * Behind a proxy or CDN that Zero Spam does not know about, every visitor
 * gets the proxy's address, so one block or one bad reputation entry locks
 * everyone out. The check looks at the most recent detections only, runs on
 * the Zero Spam settings and dashboard screens only, and caches its verdict
 * for an hour. Only detections logged since this version was installed
 * count (Migrations::PROXY_DETECTION_SINCE_OPTION): before, every visitor
 * behind a proxy was logged with the proxy's address by design. A verdict
 * says why it was given:
 * - `trusted_proxy`: at least two recent detections were recorded with the
 *   address of a trusted proxy (Cloudflare or the Trusted Proxy IPs
 *   setting), so the proxy's address was recorded instead of the visitor's;
 * - `shared_ip`: the last SAMPLE_SIZE detections all have the same address
 *   and the current request arrived with a forwarding header from an
 *   address that is not a trusted proxy.
 */
class Shared_IP_Warning {

	/**
	 * Transient caching the verdict for an hour.
	 */
	const TRANSIENT = 'zerospam_shared_ip_warning';

	/**
	 * Option holding the dismissed verdict (see dismissal_key()).
	 */
	const DISMISSED_OPTION = 'zerospam_shared_ip_warning_dismissed';

	/**
	 * Action (admin-post and nonce) of the Dismiss button.
	 */
	const ACTION = 'zerospam_dismiss_shared_ip_warning';

	/**
	 * Number of most recent detections looked at.
	 */
	const SAMPLE_SIZE = 20;

	/**
	 * Screens the warning is checked and shown on.
	 *
	 * @var string[]
	 */
	const SCREENS = array(
		'settings_page_wordpress-zero-spam-settings',
		'dashboard_page_wordpress-zero-spam-dashboard',
	);

	/**
	 * Headers a proxy adds to the requests it forwards ($_SERVER keys).
	 *
	 * @var string[]
	 */
	const FORWARDING_HEADERS = array(
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_FORWARDED',
		'HTTP_TRUE_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
	);

	/**
	 * Hooks the notice, the Dismiss button and the cache invalidation.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'dismiss' ) );
		add_action( 'add_option_zero-spam-settings', array( self::class, 'clear' ), 10, 0 );
		add_action( 'update_option_zero-spam-settings', array( self::class, 'clear' ), 10, 0 );
	}

	/**
	 * Prints the warning on the Zero Spam screens for administrators.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_zero_spam_screen() ) {
			return;
		}

		$verdict = self::get_verdict();
		if ( null === $verdict || get_option( self::DISMISSED_OPTION ) === self::dismissal_key( $verdict ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=wordpress-zero-spam-settings&subview=settings#trusted_proxies' );
		$dismiss_url  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
		$locked       = self::is_setting_locked();
		$address      = '<code>' . esc_html( $verdict['ip'] ) . '</code>';

		if ( 'trusted_proxy' === $verdict['reason'] ) {
			$summary = sprintf(
				/* translators: %s: IP address */
				esc_html__( 'The address %s, which belongs to a proxy Zero Spam already trusts, was logged as a visitor. Either those detections were recorded before the proxy was trusted, or the proxy is not forwarding visitor addresses: check its X-Forwarded-For setup.', 'zero-spam' ),
				$address
			);
			$action = $locked
				? esc_html__( 'Trusted Proxy IPs is locked by your network administrator: ask your network administrator to check the proxy if the warning comes back.', 'zero-spam' )
				: esc_html__( 'You can review the list under Settings → Zero Spam → Settings → Trusted Proxy IPs.', 'zero-spam' );
			$button = esc_html__( 'Review trusted proxies', 'zero-spam' );
		} else {
			$summary = sprintf(
				/* translators: 1: number of detections, 2: IP address */
				esc_html__( 'Many different visitors appear to share one IP address, which usually means your site is behind a proxy or CDN that Zero Spam does not know about. The last %1$d detections all came from %2$s.', 'zero-spam' ),
				self::SAMPLE_SIZE,
				$address
			);
			$action = $locked
				? esc_html__( 'Trusted Proxy IPs is locked by your network administrator: ask your network administrator to add the proxy\'s address there, or every visitor may be blocked together.', 'zero-spam' )
				: esc_html__( 'Add its address under Settings → Zero Spam → Settings → Trusted Proxy IPs, or every visitor may be blocked together.', 'zero-spam' );
			$button = esc_html__( 'Add a trusted proxy', 'zero-spam' );
		}
		?>
		<div class="notice notice-warning" id="zerospam-shared-ip-warning">
			<p>
				<strong><?php esc_html_e( 'Zero Spam:', 'zero-spam' ); ?></strong>
				<?php echo wp_kses( $summary, array( 'code' => array() ) ); ?>
			</p>
			<p><?php echo esc_html( $action ); ?></p>
			<p>
				<?php if ( ! $locked ) : ?>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary"><?php echo esc_html( $button ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary" aria-label="<?php esc_attr_e( 'Dismiss the shared IP address warning', 'zero-spam' ); ?>">
					<?php esc_html_e( 'Dismiss', 'zero-spam' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismisses the current warning (admin-post handler).
	 *
	 * The same kind of warning for another address is shown again.
	 */
	public function dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'zero-spam' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$verdict = self::get_verdict();
		if ( null !== $verdict ) {
			update_option( self::DISMISSED_OPTION, self::dismissal_key( $verdict ), false );
		}

		$redirect = wp_get_referer();
		wp_safe_redirect( $redirect ? $redirect : admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ) );
		exit;
	}

	/**
	 * Clears the cached verdict, for example when the trusted proxies change.
	 */
	public static function clear(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * The warning to show, if any.
	 *
	 * @return array{ip: string, reason: string}|null
	 */
	public static function get_verdict(): ?array {
		$verdict = get_transient( self::TRANSIENT );

		if ( ! is_array( $verdict ) ) {
			$verdict = self::detect();
			set_transient( self::TRANSIENT, $verdict, HOUR_IN_SECONDS );
		}

		$verdict = ! empty( $verdict['ip'] ) ? $verdict : false;

		/**
		 * Filters the warning about visitors sharing one IP address.
		 *
		 * Return false to never show it.
		 *
		 * @since 5.7.14
		 *
		 * @param array|false $verdict {
		 *     The warning, or false for none.
		 *
		 *     @type string $ip     The shared address.
		 *     @type string $reason `trusted_proxy` or `shared_ip`.
		 * }
		 */
		$verdict = apply_filters( 'zerospam_shared_ip_warning', $verdict );

		if ( ! is_array( $verdict ) || empty( $verdict['ip'] ) || ! is_string( $verdict['ip'] ) ) {
			return null;
		}

		return array(
			'ip'     => $verdict['ip'],
			'reason' => isset( $verdict['reason'] ) && 'trusted_proxy' === $verdict['reason'] ? 'trusted_proxy' : 'shared_ip',
		);
	}

	/**
	 * Looks at the most recent detections.
	 *
	 * @return array{ip: string, reason: string} The verdict; an empty `ip` means no warning.
	 */
	private static function detect(): array {
		$none = array(
			'ip'     => '',
			'reason' => '',
		);

		// Only detections logged with proxy-aware IP detection count: older rows
		// hold proxy addresses (for example Cloudflare edges) by design.
		$since = get_option( Migrations::PROXY_DETECTION_SINCE_OPTION );
		if ( ! is_string( $since ) || '' === $since ) {
			return $none;
		}

		$rows = DB::query(
			'log',
			array(
				'where'   => array(
					'date_recorded' => array(
						'value'    => $since,
						'relation' => '>=',
					),
				),
				'select'  => array( 'user_ip' ),
				'orderby' => 'log_id',
				'order'   => 'DESC',
				'limit'   => self::SAMPLE_SIZE,
			)
		);

		$ips = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( is_array( $row ) && ! empty( $row['user_ip'] ) && rest_is_ip_address( (string) $row['user_ip'] ) ) {
				$ips[] = (string) $row['user_ip'];
			}
		}

		if ( ! $ips ) {
			return $none;
		}

		$counts = array_count_values( $ips );
		arsort( $counts );

		foreach ( $counts as $ip => $count ) {
			if ( $count >= 2 && User::is_trusted_proxy( (string) $ip ) ) {
				return array(
					'ip'     => (string) $ip,
					'reason' => 'trusted_proxy',
				);
			}
		}

		if ( count( $ips ) >= self::SAMPLE_SIZE && 1 === count( $counts ) && self::is_forwarded_by_unknown_proxy() ) {
			return array(
				'ip'     => $ips[0],
				'reason' => 'shared_ip',
			);
		}

		return $none;
	}

	/**
	 * Whether this request carries a forwarding header from an address that is
	 * not a trusted proxy.
	 *
	 * @return bool
	 */
	private static function is_forwarded_by_unknown_proxy(): bool {
		$remote = User::get_remote_addr();
		if ( '' === $remote || User::is_trusted_proxy( $remote ) ) {
			return false;
		}

		foreach ( self::FORWARDING_HEADERS as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the network administrator has locked Trusted Proxy IPs for this site.
	 *
	 * Reads the `zerospam_network_settings` site option directly instead of
	 * calling Network_Settings::is_locked(), which reads the same data through
	 * its site-transient cache: constructing Network_Settings would register
	 * another settings resolver for the rest of the request. The option is
	 * the source of truth, so the answer is never staler than the cache's.
	 *
	 * @return bool
	 */
	private static function is_setting_locked(): bool {
		if ( ! is_multisite() ) {
			return false;
		}

		$network = get_site_option( Network_Settings::META_KEY, array() );

		return is_array( $network ) && ! empty( $network['settings']['trusted_proxies']['locked'] );
	}

	/**
	 * Whether the current screen is a Zero Spam settings or dashboard screen.
	 *
	 * @return bool
	 */
	private static function is_zero_spam_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen instanceof \WP_Screen && in_array( $screen->id, self::SCREENS, true );
	}

	/**
	 * What a dismissal covers: every `trusted_proxy` warning (a site behind
	 * Cloudflare has many edge addresses), or a `shared_ip` warning for one
	 * address.
	 *
	 * @param array{ip: string, reason: string} $verdict Verdict.
	 * @return string
	 */
	private static function dismissal_key( array $verdict ): string {
		return 'trusted_proxy' === $verdict['reason'] ? 'trusted_proxy' : 'shared_ip:' . $verdict['ip'];
	}
}
