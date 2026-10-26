<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- includes/class-autoloader.php maps Admin_App to this file name.
/**
 * The 6.0 admin app: menu, pages and assets.
 *
 * @package ZeroSpam
 */

declare( strict_types=1 );

namespace ZeroSpam\Core\Admin;

use ZeroSpam\Core\Utilities;
use ZeroSpam\Includes\News_Feed;

// Security Note: Blocks direct access to the plugin PHP files.
defined( 'ABSPATH' ) || die();

/**
 * Registers the 6.0 admin behind the `ZEROSPAM_ADMIN_V6` constant and the
 * `zerospam_admin_v6` filter (Utilities::admin_v6(); off by default).
 *
 * When it is on, a top-level "Zero Spam" menu sits directly below Comments
 * (position 26) with six pages, one per area: Overview (`zero-spam`),
 * Activity, Rules, Protection, Settings and Help (`zero-spam-{area}`); the
 * network admin gets one page (`zero-spam-network`). Every page prints the
 * same shell (the app root with a skeleton and a <noscript> message) and
 * loads the admin bundle from assets/dist/, with the first REST responses
 * preloaded so the Overview makes no request of its own on load.
 *
 * The legacy screens are not touched: they stay registered and reachable
 * until the cutover (build plan PR 13).
 *
 * @since 6.0.0
 */
final class Admin_App {

	/**
	 * Script and style handle of the admin bundle.
	 *
	 * @var string
	 */
	const HANDLE = 'zerospam-admin';

	/**
	 * Style handle of the self-hosted fonts.
	 *
	 * @var string
	 */
	const FONTS_HANDLE = 'zerospam-fonts';

	/**
	 * Menu position: directly below Comments (25).
	 *
	 * @var int
	 */
	const MENU_POSITION = 26;

	/**
	 * Areas and their page slugs, in menu order.
	 *
	 * @var array<string, string>
	 */
	const AREAS = array(
		'overview'   => 'zero-spam',
		'activity'   => 'zero-spam-activity',
		'rules'      => 'zero-spam-rules',
		'protection' => 'zero-spam-protection',
		'settings'   => 'zero-spam-settings',
		'help'       => 'zero-spam-help',
	);

	/**
	 * Slug of the network admin page.
	 *
	 * @var string
	 */
	const NETWORK_SLUG = 'zero-spam-network';

	/**
	 * REST paths preloaded on every site page (the shell: sidebar card,
	 * level chip, theme, the Activity count and the status pill).
	 *
	 * @var string[]
	 */
	const SHELL_PRELOADS = array(
		'/zero-spam/v1/license',
		'/zero-spam/v1/ui/prefs',
		'/zero-spam/v1/stats/summary?range=1',
		'/zero-spam/v1/protection/modules',
	);

	/**
	 * REST paths the Overview reads on load, preloaded on its page only.
	 *
	 * @var string[]
	 */
	const OVERVIEW_PRELOADS = array(
		'/zero-spam/v1/settings/schema',
		'/zero-spam/v1/stats/summary?range=7',
		'/zero-spam/v1/stats/summary?range=30',
		'/zero-spam/v1/stats/timeseries',
		'/zero-spam/v1/activity?per_page=6&no_count=1&_fields=id,date,type,type_label,ip,country,country_name,failed,failed_label',
	);

	/**
	 * Fields of an Activity log row (assets/src/admin/activity/query.js asks
	 * for the same list, so the preloaded answer is used).
	 *
	 * @var string
	 */
	const ACTIVITY_FIELDS = 'id,date,type,type_label,ip,blocked,network,failed,failed_label,country,country_name,region,region_name,city';

	/**
	 * Rows per page of the Activity log.
	 *
	 * @var int
	 */
	const ACTIVITY_PER_PAGE = 25;

	/**
	 * Period of the Reports tab on load, in days.
	 *
	 * @var int
	 */
	const REPORTS_DAYS = 30;

	/**
	 * The news route, preloaded when the news is on.
	 *
	 * @var string
	 */
	const NEWS_PRELOAD = '/zero-spam/v1/news';

	/**
	 * JSON flags for data printed into inline scripts: `<`, `>` and `&` are
	 * written as \u escapes, so logged text such as `</script>` or `<!--`
	 * (preloaded activity entries carry what visitors sent) can never end
	 * or change the script element.
	 *
	 * @var int
	 */
	const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP;

	/**
	 * Hook suffixes of the registered pages, by area.
	 *
	 * @var array<string, string>
	 */
	private array $hooks = array();

	/**
	 * Registers the hooks. Each callback checks the flag, so a filter added
	 * after this runs still decides.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'register_network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Page titles of the areas, translated.
	 *
	 * @return array<string, string>
	 */
	public static function area_titles(): array {
		return array(
			'overview'   => __( 'Overview', 'zero-spam' ),
			'activity'   => __( 'Activity', 'zero-spam' ),
			'rules'      => __( 'Rules', 'zero-spam' ),
			'protection' => __( 'Protection', 'zero-spam' ),
			'settings'   => __( 'Settings', 'zero-spam' ),
			'help'       => __( 'Help', 'zero-spam' ),
		);
	}

	/**
	 * The menu icon: the one-colour mark as a base64 SVG data URI, which
	 * WordPress's svg-painter recolours per admin colour scheme.
	 */
	public static function menu_icon(): string {
		$file = ZEROSPAM_PATH . 'assets/img/zero-spam-menu-icon.svg';
		$svg  = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file shipped with the plugin.

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress expects menu icons as base64 data URIs.
		return '' !== $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-shield';
	}

	/**
	 * Registers the top-level menu and its six pages.
	 */
	public function register_menu(): void {
		if ( ! Utilities::admin_v6() ) {
			return;
		}

		$titles = self::area_titles();
		$render = array( $this, 'render_page' );

		$top = add_menu_page(
			__( 'Zero Spam', 'zero-spam' ),
			__( 'Zero Spam', 'zero-spam' ),
			'manage_options',
			self::AREAS['overview'],
			$render,
			self::menu_icon(),
			self::MENU_POSITION
		);

		foreach ( self::AREAS as $area => $slug ) {
			$hook = add_submenu_page(
				self::AREAS['overview'],
				/* translators: %s: area name, such as Overview */
				sprintf( __( '%s ‹ Zero Spam', 'zero-spam' ), $titles[ $area ] ),
				$titles[ $area ],
				'manage_options',
				$slug,
				$render
			);

			if ( is_string( $hook ) && '' !== $hook ) {
				$this->hooks[ $area ] = $hook;
			}
		}

		if ( is_string( $top ) && '' !== $top ) {
			$this->hooks['overview'] = $top;
		}
	}

	/**
	 * Registers the network admin page (super admins only).
	 */
	public function register_network_menu(): void {
		if ( ! Utilities::admin_v6() ) {
			return;
		}

		$hook = add_menu_page(
			__( 'Zero Spam', 'zero-spam' ),
			__( 'Zero Spam', 'zero-spam' ),
			'manage_network_options',
			self::NETWORK_SLUG,
			array( $this, 'render_page' ),
			self::menu_icon(),
			self::MENU_POSITION
		);

		if ( is_string( $hook ) && '' !== $hook ) {
			$this->hooks['network'] = $hook;
		}
	}

	/**
	 * The area of the current screen, or null when it is not one of ours.
	 *
	 * @param string $hook_suffix Hook suffix of the screen.
	 */
	public function area_for_hook( string $hook_suffix ): ?string {
		$area = array_search( $hook_suffix, $this->hooks, true );

		return false === $area ? null : (string) $area;
	}

	/**
	 * Hook suffixes of the registered pages, by area.
	 *
	 * @return array<string, string>
	 */
	public function hooks(): array {
		return $this->hooks;
	}

	/**
	 * Enqueues the bundle, its translations, the fonts and the preloaded
	 * REST responses on the app's pages only.
	 *
	 * @param string $hook_suffix Hook suffix of the screen.
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! Utilities::admin_v6() ) {
			return;
		}

		$area = $this->area_for_hook( (string) $hook_suffix );
		if ( null === $area ) {
			return;
		}

		$asset = self::asset_file();
		$base  = plugin_dir_url( ZEROSPAM );

		wp_enqueue_style(
			self::FONTS_HANDLE,
			$base . 'assets/fonts/fonts.css',
			array(),
			Utilities::asset_version( 'assets/fonts/fonts.css' )
		);

		wp_enqueue_style(
			self::HANDLE,
			$base . 'assets/dist/admin.css',
			array( self::FONTS_HANDLE ),
			$asset['version']
		);
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_enqueue_script(
			self::HANDLE,
			$base . 'assets/dist/admin.js',
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);

		wp_set_script_translations( self::HANDLE, 'zero-spam' );

		wp_add_inline_script(
			self::HANDLE,
			'var zeroSpamAdmin = ' . wp_json_encode( $this->bootstrap( $area ), self::JSON_FLAGS ) . ';',
			'before'
		);

		$preloaded = array_reduce( self::preload_paths( $area, self::view_args() ), 'rest_preload_api_request', array() );

		wp_add_inline_script(
			'wp-api-fetch',
			// One copy: the middleware answers apiFetch, useResource() paints the first render from it.
			sprintf( 'var zeroSpamPreload = %s; wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( zeroSpamPreload ) );', wp_json_encode( $preloaded, self::JSON_FLAGS ) ),
			'after'
		);

		add_action( 'admin_head', array( $this, 'preload_font' ) );
	}

	/**
	 * Preloads the interface font (Latin), so the first paint already uses it.
	 */
	public function preload_font(): void {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( plugin_dir_url( ZEROSPAM ) . 'assets/fonts/plus-jakarta-sans/plus-jakarta-sans-latin.woff2' )
		);
	}

	/**
	 * The view the address asks for: the tab and the activity entry to open.
	 *
	 * @return array{tab: string, item: int}
	 */
	public static function view_args(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		return array(
			'tab'  => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '',
			'item' => isset( $_GET['item'] ) ? absint( wp_unslash( $_GET['item'] ) ) : 0,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The REST paths the Activity page reads on load: the heading's 30-day
	 * count; on the Log tab the first page of entries and the filter counts;
	 * on the Reports tab the 30-day series and breakdowns; and the entry an
	 * address opens (`&item=`, from the Overview's feed).
	 *
	 * The paths are written exactly as the app asks for them
	 * (assets/src/admin/activity/query.js); the preloading middleware
	 * matches them with their query arguments in any order.
	 *
	 * @param array{tab?: string, item?: int} $view View.
	 * @return string[]
	 */
	public static function activity_preloads( array $view = array() ): array {
		$paths = array( '/zero-spam/v1/stats/summary?range=30' );

		if ( 'reports' === ( $view['tab'] ?? '' ) ) {
			$paths[] = '/zero-spam/v1/stats/timeseries';
			$paths[] = '/zero-spam/v1/activity?facets=1&detections_only=1&days=' . self::REPORTS_DAYS;
		} else {
			$paths[] = '/zero-spam/v1/activity?per_page=' . self::ACTIVITY_PER_PAGE . '&_fields=' . self::ACTIVITY_FIELDS;
			$paths[] = '/zero-spam/v1/activity?facets=1';
		}

		if ( ! empty( $view['item'] ) ) {
			$paths[] = '/zero-spam/v1/activity/' . (int) $view['item'];
		}

		return $paths;
	}

	/**
	 * The REST paths preloaded for an area.
	 *
	 * @param string                          $area Area.
	 * @param array{tab?: string, item?: int} $view View the address asks for (see view_args()).
	 * @return string[]
	 */
	public static function preload_paths( string $area, array $view = array() ): array {
		$paths = 'network' === $area
			? array( '/zero-spam/v1/ui/prefs' )
			: self::SHELL_PRELOADS;

		if ( 'overview' === $area ) {
			$paths = array_merge( $paths, self::OVERVIEW_PRELOADS );
		}

		if ( 'activity' === $area ) {
			$paths = array_merge( $paths, self::activity_preloads( $view ) );
		}

		if ( News_Feed::is_enabled() ) {
			$paths[] = self::NEWS_PRELOAD;
		}

		return $paths;
	}

	/**
	 * Dependencies and version of the bundle, from its .asset.php file.
	 *
	 * @return array{dependencies: string[], version: string}
	 */
	public static function asset_file(): array {
		$file  = ZEROSPAM_PATH . 'assets/dist/admin.asset.php';
		$asset = is_readable( $file ) ? require $file : array();

		return array(
			'dependencies' => isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? array_values( array_map( 'strval', $asset['dependencies'] ) ) : array(),
			'version'      => isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : ZEROSPAM_VERSION,
		);
	}

	/**
	 * What the app needs before its first request: the area, the page
	 * addresses, the site and the environment.
	 *
	 * @param string $area Area.
	 * @return array<string, mixed>
	 */
	public function bootstrap( string $area ): array {
		$urls = array();
		foreach ( self::AREAS as $key => $slug ) {
			$urls[ $key ] = admin_url( 'admin.php?page=' . $slug );
		}

		return array(
			'area'              => $area,
			'tab'               => self::view_args()['tab'],
			'version'           => ZEROSPAM_VERSION,
			// Site-local "now" in milliseconds (read as UTC), so relative times match the log's site-local dates.
			'nowLocal'          => ( time() + ( new \DateTimeImmutable( 'now', wp_timezone() ) )->getOffset() ) * 1000,
			'site'              => array(
				'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'  => home_url( '/' ),
			),
			'urls'              => $urls + array(
				'network'   => is_multisite() && current_user_can( 'manage_network_options' ) ? network_admin_url( 'admin.php?page=' . self::NETWORK_SLUG ) : '',
				'siteAdmin' => admin_url( 'admin.php?page=' . self::AREAS['overview'] ),
				'legacy'    => admin_url( 'options-general.php?page=wordpress-zero-spam-settings' ),
				'flags'     => plugins_url( 'assets/img/flags/', ZEROSPAM ),
				'ipLookup'  => ZEROSPAM_URL . 'ip-lookup/',
			),
			'isMultisite'       => is_multisite(),
			'newsEnabled'       => News_Feed::is_enabled(),
			// Log types that are not detections (rescue links): left out of the live counts, as in statistics.
			'nonDetectionTypes' => \ZeroSpam\Includes\DB::NON_DETECTION_TYPES,
			'environment'       => array(
				'wordpress' => get_bloginfo( 'version' ),
				'php'       => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
				'ipSource'  => self::ip_source(),
			),
		);
	}

	/**
	 * Where visitor addresses come from: cloudflare, trusted_proxy,
	 * untrusted_proxy, direct or none (as the self-test reports it).
	 */
	public static function ip_source(): string {
		$remote = \ZeroSpam\Core\User::get_remote_addr();

		if ( '' === $remote ) {
			return 'none';
		}

		if ( \ZeroSpam\Core\User::is_cloudflare_ip( $remote ) ) {
			return 'cloudflare';
		}

		if ( \ZeroSpam\Core\User::is_configured_proxy( $remote ) ) {
			return 'trusted_proxy';
		}

		foreach ( \ZeroSpam\Core\User::PROXY_HEADERS as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				return 'untrusted_proxy';
			}
		}

		return 'direct';
	}

	/**
	 * Prints the shell: the icon sprite, then the app root with a skeleton
	 * the app replaces and a message for browsers without JavaScript.
	 */
	public function render_page(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$area = self::NETWORK_SLUG === $page ? 'network' : array_search( $page, self::AREAS, true );
		$area = false === $area ? 'overview' : (string) $area;

		if ( ! current_user_can( 'network' === $area ? 'manage_network_options' : 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'zero-spam' ), 403 );
		}

		$prefs  = Utilities::display_prefs( true );
		$sprite = ZEROSPAM_PATH . 'assets/dist/sprite.svg';

		if ( is_readable( $sprite ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- The icon sprite shipped with the plugin (assets/dist/sprite.svg), built from assets/src/admin/icons/sprite.svg; no user data.
			echo file_get_contents( $sprite );
		}

		$titles = self::area_titles();
		$title  = 'network' === $area ? __( 'Network', 'zero-spam' ) : $titles[ $area ];

		include ZEROSPAM_PATH . 'includes/templates/admin-app-shell.php';
	}
}
