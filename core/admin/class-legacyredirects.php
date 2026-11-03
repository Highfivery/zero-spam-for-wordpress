<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Legacy_Redirects to this file name.
/**
 * Redirects from the 5.x admin screens to the 6.0 admin.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\Settings_Schema;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Sends the addresses of the 5.x screens (bookmarks, links in old emails,
 * notes) to the 6.0 page that replaced them, with a 302 and their
 * arguments mapped (build plan §3, redirect map):
 *
 * - `index.php?page=wordpress-zero-spam-dashboard` (Reports, Log, Blocked
 *   IPs, Blocked Locations) → Activity and Rules;
 * - `options-general.php?page=wordpress-zero-spam-settings` (and the
 *   `admin.php` address some 5.x links used) → Settings, Protection, Help;
 * - network `settings.php?page=zerospam-network-settings` and
 *   `settings.php?page=wordpress-zero-spam-network-stats` → the network page.
 *
 * Runs first on `admin_menu` and `network_admin_menu`, before WordPress
 * checks the page (the old slugs are not registered any more). A user who
 * cannot open the target is not redirected: WordPress then refuses the old
 * address as it refuses any page the user may not see, so a site
 * administrator opening a network address still gets a permission error.
 *
 * The 5.x `zerospam-action` links of the settings screen (C3) are handled
 * for one release: `autoconfigure` and the two notice dismissals still do
 * their work (capability and nonce checked) before the redirect; the others
 * only redirect. One-shot message arguments (`zerospam-msg`, …) are dropped.
 *
 * @since 6.0.0
 */
final class Legacy_Redirects {

	/**
	 * Slug of the 5.x dashboard (Reports, Log, Blocked IPs, Blocked Locations).
	 *
	 * @var string
	 */
	const DASHBOARD = 'wordpress-zero-spam-dashboard';

	/**
	 * Slug of the 5.x settings screen.
	 *
	 * @var string
	 */
	const SETTINGS = 'wordpress-zero-spam-settings';

	/**
	 * Slug of the 5.x network settings screen.
	 *
	 * @var string
	 */
	const NETWORK_SETTINGS = 'zerospam-network-settings';

	/**
	 * Slug of the 5.x network statistics screen.
	 *
	 * @var string
	 */
	const NETWORK_STATS = 'wordpress-zero-spam-network-stats';

	/**
	 * Settings subviews that are a Settings tab or another page.
	 *
	 * @var array<string, array{0: string, 1: string}> Subview => [ page slug, tab ].
	 */
	const SETTINGS_SUBVIEWS = array(
		'settings'       => array( 'zero-spam-settings', '' ),
		'documentation'  => array( 'zero-spam-help', '' ),
		'api-monitoring' => array( 'zero-spam-settings', 'api' ),
		'debug'          => array( 'zero-spam-settings', 'debug' ),
		'export'         => array( 'zero-spam-settings', 'io' ),
		'errors'         => array( 'zero-spam-settings', 'io' ),
		// The Google Maps settings were removed in 6.0.0 (the drawer links to Google Maps instead).
		'google'         => array( 'zero-spam-protection', 'intelligence' ),
	);

	/**
	 * 5.x Log sort columns and the 6.0 Activity sort they become.
	 *
	 * @var array<string, string>
	 */
	const LOG_SORTS = array(
		'date_recorded' => 'date',
		'log_type'      => 'type',
		'user_ip'       => 'ip',
		'country'       => 'country',
		'region'        => 'region',
	);

	/**
	 * 5.x network statistics periods and the 6.0 ones.
	 *
	 * @var array<string|int, string>
	 */
	const PERIODS = array(
		'today'     => 'today',
		'yesterday' => 'yesterday',
		'week'      => '7',
		'7'         => '7',
		'month'     => '30',
		'30'        => '30',
	);

	/**
	 * 5.x network settings tabs and the 6.0 ones.
	 *
	 * @var array<string, string>
	 */
	const NETWORK_TABS = array(
		'overview'      => 'overview',
		'settings'      => 'settings',
		'templates'     => 'templates',
		'audit'         => 'audit',
		'comparison'    => 'comparison',
		'import-export' => 'io',
		'notifications' => 'notifications',
	);

	/**
	 * Registers the redirect before any other admin_menu callback.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'maybe_redirect' ), 0 );
		add_action( 'network_admin_menu', array( $this, 'maybe_redirect' ), 0 );
	}

	/**
	 * Redirects a 5.x screen's address, when the user can open the target.
	 */
	public function maybe_redirect(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing; the actions below check their own nonces.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$args = wp_unslash( $_GET );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$target = self::target( $page, is_array( $args ) ? $args : array(), is_network_admin() );

		if ( null === $target || ! current_user_can( $target['network'] ? 'manage_network_options' : 'manage_options' ) ) {
			return;
		}

		if ( self::SETTINGS === $page ) {
			self::run_legacy_action();
		}

		$url = $target['network'] ? network_admin_url( $target['path'] ) : admin_url( $target['path'] );

		wp_safe_redirect( $url, 302 );
		exit;
	}

	/**
	 * Where a 5.x screen's address goes, or null when the page is not one of
	 * them (or a network screen asked for outside the network admin).
	 *
	 * @param string               $page    The `page` argument.
	 * @param array<string, mixed> $args    The query arguments (unslashed).
	 * @param bool                 $network Whether the request is in the network admin.
	 * @return array{path: string, network: bool}|null Path below the (network) admin URL.
	 */
	public static function target( string $page, array $args, bool $network ): ?array {
		$arg = static function ( string $name ) use ( $args ): string {
			return isset( $args[ $name ] ) && is_scalar( $args[ $name ] ) ? trim( (string) $args[ $name ] ) : '';
		};

		if ( $network ) {
			if ( self::NETWORK_SETTINGS === $page ) {
				$tab = self::NETWORK_TABS[ sanitize_key( $arg( 'tab' ) ) ] ?? '';

				return self::path( 'zero-spam-network', array( 'tab' => $tab ), true );
			}

			if ( self::NETWORK_STATS === $page ) {
				// 6.0 has no custom date range: start_date and end_date are dropped, the period stays when it has a 6.0 twin.
				$period = self::PERIODS[ sanitize_key( $arg( 'period' ) ) ] ?? '';

				return self::path(
					'zero-spam-network',
					array(
						'tab'    => 'stats',
						'period' => '30' === $period ? '' : $period,
					),
					true
				);
			}

			return null;
		}

		if ( self::DASHBOARD === $page ) {
			return self::dashboard_target( sanitize_key( $arg( 'subview' ) ), $arg );
		}

		if ( self::SETTINGS === $page ) {
			$subview = sanitize_key( $arg( 'subview' ) );

			// Network Stats' "Block Network-Wide" linked here with an address: its detections on Activity.
			if ( '' === $subview && '' !== $arg( 'ip' ) ) {
				return self::path( 'zero-spam-activity', array( 'ip' => self::text( $arg( 'ip' ) ) ) );
			}

			return self::settings_target( $subview );
		}

		return null;
	}

	/**
	 * Target of a 5.x dashboard subview.
	 *
	 * @param string   $subview Subview.
	 * @param callable $arg     Reads a query argument.
	 * @return array{path: string, network: bool}
	 */
	private static function dashboard_target( string $subview, callable $arg ): array {
		if ( 'blocked-ips' === $subview ) {
			return self::path( 'zero-spam-rules', array( 'tab' => 'ips' ) );
		}

		if ( 'blocked-locations' === $subview ) {
			return self::path( 'zero-spam-rules', array( 'tab' => 'locations' ) );
		}

		if ( 'log' !== $subview ) {
			return self::path( 'zero-spam-activity', array( 'tab' => 'reports' ) );
		}

		$country = strtoupper( (string) $arg( 'country' ) );
		$orderby = self::LOG_SORTS[ sanitize_key( (string) $arg( 'orderby' ) ) ] ?? '';
		$order   = 'asc' === strtolower( (string) $arg( 'order' ) ) ? 'asc' : 'desc';
		$sort    = '' !== $orderby || 'asc' === $order ? ( '' !== $orderby ? $orderby : 'date' ) . ':' . $order : '';
		$paged   = absint( $arg( 'paged' ) );

		return self::path(
			'zero-spam-activity',
			array(
				'type'    => sanitize_key( (string) $arg( 'type' ) ),
				'country' => 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '',
				'ip'      => self::text( (string) $arg( 's' ) ),
				'sort'    => 'date:desc' === $sort ? '' : $sort,
				'paged'   => $paged > 1 ? (string) $paged : '',
			)
		);
	}

	/**
	 * Target of a 5.x settings subview: a Settings tab, Help, or a module's
	 * card on Protection (its tab from the settings schema; sections of other
	 * plugins are on Integrations).
	 *
	 * @param string $subview Subview ('' for the first one).
	 * @return array{path: string, network: bool}
	 */
	private static function settings_target( string $subview ): array {
		if ( '' === $subview ) {
			$subview = 'settings';
		}

		if ( isset( self::SETTINGS_SUBVIEWS[ $subview ] ) ) {
			list( $slug, $tab ) = self::SETTINGS_SUBVIEWS[ $subview ];

			return self::path( $slug, array( 'tab' => $tab ) );
		}

		foreach ( Settings_Schema::get( false )['sections'] as $section ) {
			if ( $section['key'] !== $subview ) {
				continue;
			}

			if ( 'settings' === $section['area'] ) {
				$tab = 'proxies' === $section['group'] ? 'advanced' : (string) $section['group'];

				return self::path( 'zero-spam-settings', array( 'tab' => in_array( $tab, Admin_App::SETTINGS_TABS, true ) && 'general' !== $tab ? $tab : '' ) );
			}

			if ( 'protection' === $section['area'] ) {
				return self::path(
					'zero-spam-protection',
					array(
						'tab'  => (string) $section['group'],
						'card' => $subview,
					)
				);
			}
		}

		return self::path( 'zero-spam-settings' );
	}

	/**
	 * A target: the page with its non-empty arguments, in order.
	 *
	 * @param string                $slug    Page slug.
	 * @param array<string, string> $args    Arguments; empty ones are left out.
	 * @param bool                  $network Whether it is a network admin page.
	 * @return array{path: string, network: bool}
	 */
	private static function path( string $slug, array $args = array(), bool $network = false ): array {
		$query = array( 'page' => $slug ) + array_filter(
			$args,
			static function ( $value ): bool {
				return '' !== $value;
			}
		);

		return array(
			'path'    => 'admin.php?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ),
			'network' => $network,
		);
	}

	/**
	 * A short text argument (an address or search), as the 6.0 screens read it.
	 *
	 * @param string $value Value.
	 */
	private static function text( string $value ): string {
		return substr( sanitize_text_field( $value ), 0, 100 );
	}

	/**
	 * Does the work of the 5.x settings screen's `zerospam-action` links that
	 * can still be followed (C3, removed in 6.1): applying the recommended
	 * settings and dismissing the two 5.x notices. Each checks its own nonce,
	 * as the 5.x screen did; the caller checked `manage_options`.
	 */
	private static function run_legacy_action(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Each action below verifies its nonce.
		$action = isset( $_GET['zerospam-action'] ) && is_string( $_GET['zerospam-action'] ) ? sanitize_key( wp_unslash( $_GET['zerospam-action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The nonce itself, checked by wp_verify_nonce().
		$nonce = isset( $_GET['zero-spam'] ) && is_string( $_GET['zero-spam'] ) ? wp_unslash( $_GET['zero-spam'] ) : '';

		if ( '' === $action ) {
			return;
		}

		$replacements = array(
			'autoconfigure'                  => 'POST zero-spam/v1/settings/actions/autoconfigure',
			'dismiss-settings-review-notice' => '',
			'dismiss-api-monitoring-notice'  => '',
		);

		if ( ! isset( $replacements[ $action ] ) || ! wp_verify_nonce( $nonce, $action ) ) {
			return;
		}

		_deprecated_argument(
			__METHOD__,
			'6.0.0',
			esc_html(
				sprintf(
					/* translators: 1: the 5.x action, 2: what replaces it */
					__( 'The %1$s link of the 5.x settings screen is deprecated and will be removed in 6.1.0. %2$s', 'zero-spam' ),
					'zerospam-action=' . $action,
					'' !== $replacements[ $action ] ? $replacements[ $action ] : __( 'The notice it dismissed was removed.', 'zero-spam' )
				)
			)
		);

		if ( 'autoconfigure' === $action ) {
			\ZeroSpam\Core\Settings::auto_configure();
		} elseif ( 'dismiss-settings-review-notice' === $action ) {
			delete_option( \ZeroSpam\Includes\Migrations::SETTINGS_REVIEW_NOTICE_OPTION );
		} else {
			update_option( 'zerospam_api_monitoring_notice_dismissed', true );
		}
	}
}
