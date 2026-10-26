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
use ZeroSpam\Includes\Rest\Rules_Controller;

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
	 * Tabs of the Rules page (`&tab=`), in order; the first is the default.
	 *
	 * @var string[]
	 */
	const RULES_TABS = array( 'ips', 'locations', 'allowlist', 'email', 'words', 'no-lockout' );

	/**
	 * Rows per page of the Rules lists (assets/src/admin/rules/query.js).
	 *
	 * @var int
	 */
	const RULES_PER_PAGE = 50;

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
	 * Tabs of the Settings page (`&tab=`), in order; the first is the default.
	 *
	 * @var string[]
	 */
	const SETTINGS_TABS = array( 'general', 'data', 'api', 'advanced', 'debug', 'io' );

	/**
	 * Settings cards (assets/src/admin/settings/cards.js) that are not a
	 * setting, by `&section=`: the tab they are on. A setting key's tab
	 * comes from its schema group (see settings_tab_of()).
	 *
	 * @var array<string, string>
	 */
	const SETTINGS_CARDS = array(
		'quick'      => 'general',
		'blocking'   => 'general',
		'widget'     => 'general',
		'news'       => 'general',
		'display'    => 'general',
		'logging'    => 'data',
		'sharing'    => 'data',
		'uninstall'  => 'data',
		'apimon'     => 'api',
		'alertch'    => 'api',
		'alertrules' => 'api',
		'proxies'    => 'advanced',
		'rescue'     => 'advanced',
		'nolockout'  => 'advanced',
		'debug'      => 'debug',
		'system'     => 'debug',
		'io'         => 'io',
		'errlog'     => 'io',
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
	 * REST paths the Protection page reads on load besides the shell's
	 * (which already has the license and the protection modules).
	 *
	 * @var string[]
	 */
	const PROTECTION_PRELOADS = array(
		'/zero-spam/v1/settings/schema',
	);

	/**
	 * Tabs of the network page (`&tab=`), in order; the first is the default.
	 *
	 * @var string[]
	 */
	const NETWORK_TABS = array( 'overview', 'settings', 'templates', 'audit', 'comparison', 'io', 'notifications', 'stats' );

	/**
	 * Rows per page of the network audit log (assets/src/admin/network/model.js).
	 *
	 * @var int
	 */
	const AUDIT_PER_PAGE = 25;

	/**
	 * Sites per page of the network comparison.
	 *
	 * @var int
	 */
	const COMPARISON_PER_PAGE = 5;

	/**
	 * Sites per page of the network statistics.
	 *
	 * @var int
	 */
	const STATS_PER_PAGE = 20;

	/**
	 * Network blocks per page on the statistics tab.
	 *
	 * @var int
	 */
	const NETWORK_BLOCKS_PER_PAGE = 10;

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
	 * Address arguments that change the Activity log's list (assets/src/admin/activity/query.js).
	 *
	 * @var string[]
	 */
	const ACTIVITY_VIEW_ARGS = array( 'type', 'country', 'ip', 'after', 'before', 'network', 'sort', 'paged' );

	/**
	 * The view the address asks for: the tab, the activity entry to open,
	 * whether the log is filtered (its first page is then not the default
	 * one) and whether it is only sorted or paged.
	 *
	 * @return array{tab: string, item: int, filtered: bool, moved: bool}
	 */
	public static function view_args(): array {
		$set = array();
		foreach ( self::ACTIVITY_VIEW_ARGS as $arg ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state; only whether it is set is used.
			$set[ $arg ] = isset( $_GET[ $arg ] ) && '' !== $_GET[ $arg ];
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		return array(
			'tab'      => isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '',
			'item'     => isset( $_GET['item'] ) ? absint( wp_unslash( $_GET['item'] ) ) : 0,
			'filtered' => $set['type'] || $set['country'] || $set['ip'] || $set['after'] || $set['before'] || $set['network'],
			'moved'    => $set['sort'] || $set['paged'],
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
	 * Paths that only the default view asks for are left out when the
	 * address filters, sorts or pages the log (the app asks for those
	 * itself): the first page needs no filter, sort or page; the filter
	 * counts need no filter.
	 *
	 * @param array{tab?: string, item?: int, filtered?: bool, moved?: bool} $view View.
	 * @return string[]
	 */
	public static function activity_preloads( array $view = array() ): array {
		$paths    = array( '/zero-spam/v1/stats/summary?range=30' );
		$filtered = ! empty( $view['filtered'] );

		if ( 'reports' === ( $view['tab'] ?? '' ) ) {
			$paths[] = '/zero-spam/v1/stats/timeseries';
			$paths[] = '/zero-spam/v1/activity?facets=1&detections_only=1&days=' . self::REPORTS_DAYS;
		} else {
			if ( ! $filtered && empty( $view['moved'] ) ) {
				$paths[] = '/zero-spam/v1/activity?per_page=' . self::ACTIVITY_PER_PAGE . '&_fields=' . self::ACTIVITY_FIELDS;
			}
			if ( ! $filtered ) {
				$paths[] = '/zero-spam/v1/activity?facets=1';
			}
		}

		if ( ! empty( $view['item'] ) ) {
			$paths[] = '/zero-spam/v1/activity/' . (int) $view['item'];
		}

		return $paths;
	}

	/**
	 * The REST paths preloaded for an area.
	 *
	 * @param string                                                         $area Area.
	 * @param array{tab?: string, item?: int, filtered?: bool, moved?: bool} $view View the address asks for (see view_args()).
	 * @return string[]
	 */
	public static function preload_paths( string $area, array $view = array() ): array {
		$paths = 'network' === $area
			? array_merge( array( '/zero-spam/v1/ui/prefs' ), self::network_preloads( $view['tab'] ?? '' ) )
			: self::SHELL_PRELOADS;

		if ( 'rules' === $area ) {
			$paths = array_merge( $paths, self::rules_preloads( $view ) );
		}

		if ( 'overview' === $area ) {
			$paths = array_merge( $paths, self::OVERVIEW_PRELOADS );
		}

		if ( 'settings' === $area ) {
			$paths = array_merge( $paths, self::settings_preloads( $view['tab'] ?? '', self::section_arg() ) );
		}

		if ( 'activity' === $area ) {
			$paths = array_merge( $paths, self::activity_preloads( $view ) );
		}

		if ( 'protection' === $area ) {
			$paths = array_merge( $paths, self::PROTECTION_PRELOADS );
		}

		if ( News_Feed::is_enabled() ) {
			$paths[] = self::NEWS_PRELOAD;
		}

		return $paths;
	}

	/**
	 * The REST paths a network tab reads on load, written exactly as the app
	 * asks for them (assets/src/admin/network/model.js, PATHS).
	 *
	 * @param string $tab Tab the address asks for (`&tab=`); the Overview when unknown.
	 * @return string[]
	 */
	public static function network_preloads( string $tab = '' ): array {
		$tab = in_array( $tab, self::NETWORK_TABS, true ) ? $tab : self::NETWORK_TABS[0];

		switch ( $tab ) {
			case 'settings':
				return array( '/zero-spam/v1/settings/schema', '/zero-spam/v1/settings?scope=network', '/zero-spam/v1/network/overview' );
			case 'templates':
				return array( '/zero-spam/v1/settings/schema', '/zero-spam/v1/network-settings/templates', '/zero-spam/v1/settings?scope=network' );
			case 'audit':
				return array( '/zero-spam/v1/settings/schema', '/zero-spam/v1/network-settings/audit?page=1&per_page=' . self::AUDIT_PER_PAGE );
			case 'comparison':
				return array( '/zero-spam/v1/settings/schema', '/zero-spam/v1/network/comparison?page=1&per_page=' . self::COMPARISON_PER_PAGE );
			case 'io':
				return array( '/zero-spam/v1/network-settings/export' );
			case 'notifications':
				return array( '/zero-spam/v1/network/notifications' );
			case 'stats':
				return array(
					'/zero-spam/v1/network/stats?period=30&page=1&per_page=' . self::STATS_PER_PAGE . '&orderby=blocked&order=desc',
					'/zero-spam/v1/network/blocks?page=1&per_page=' . self::NETWORK_BLOCKS_PER_PAGE,
				);
			default:
				return array( '/zero-spam/v1/network/overview' );
		}
	}

	/**
	 * The REST paths the Settings page reads on load: the settings schema
	 * (every card) and the data of the tab the address opens, written
	 * exactly as the app asks for them (the preload store is read by path).
	 *
	 * @param string $tab     Tab the address asks for (`&tab=`).
	 * @param string $section Card or setting the address asks for (`&section=`), which picks the tab when there is none.
	 * @return string[]
	 */
	public static function settings_preloads( string $tab = '', string $section = '' ): array {
		if ( ! in_array( $tab, self::SETTINGS_TABS, true ) ) {
			$tab = self::settings_tab_of( $section );
		}

		$paths = array( '/zero-spam/v1/settings/schema' );

		if ( 'advanced' === $tab ) {
			$paths[] = '/zero-spam/v1/rules/no-lockout';
		} elseif ( 'io' === $tab ) {
			$paths[] = '/zero-spam/v1/settings/error-log';
		}

		return $paths;
	}

	/**
	 * The Settings tab a `&section=` opens: a card's, or a setting's from its
	 * schema group (the groups of the Settings area are its tabs; Trusted
	 * Proxy IPs, group `proxies`, is on Advanced). General when unknown.
	 *
	 * @param string $section Card or setting key.
	 */
	public static function settings_tab_of( string $section ): string {
		if ( isset( self::SETTINGS_CARDS[ $section ] ) ) {
			return self::SETTINGS_CARDS[ $section ];
		}

		$entry = '' !== $section ? \ZeroSpam\Core\Settings_Schema::setting( $section ) : null;
		$group = null !== $entry && 'settings' === $entry['area'] ? (string) $entry['group'] : '';
		$group = 'proxies' === $group ? 'advanced' : $group;

		return in_array( $group, self::SETTINGS_TABS, true ) ? $group : self::SETTINGS_TABS[0];
	}

	/**
	 * The `&section=` (or `&card=`) the address asks for, as a key.
	 */
	private static function section_arg(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		if ( isset( $_GET['section'] ) && is_string( $_GET['section'] ) ) {
			return sanitize_key( wp_unslash( $_GET['section'] ) );
		}

		return isset( $_GET['card'] ) && is_string( $_GET['card'] ) ? sanitize_key( wp_unslash( $_GET['card'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether the always-on rescue key is set: ZEROSPAM_RESCUE_KEY holds
	 * text, as Access reads it.
	 *
	 * @param mixed $key The key to judge (default: the constant's value).
	 */
	public static function rescue_key_set( $key = null ): bool {
		if ( null === $key ) {
			$key = defined( 'ZEROSPAM_RESCUE_KEY' ) ? constant( 'ZEROSPAM_RESCUE_KEY' ) : '';
		}

		return is_string( $key ) && '' !== trim( $key );
	}

	/**
	 * The REST paths the Rules page reads on load: the settings schema (the
	 * block method and location services the notes mention) and the first
	 * page of the tab the address opens.
	 *
	 * The paths are written exactly as the app asks for them
	 * (assets/src/admin/rules/query.js, firstPath()), because the page's
	 * preload store is read by path.
	 *
	 * @param array{tab?: string, item?: int} $view View.
	 * @return string[]
	 */
	public static function rules_preloads( array $view = array() ): array {
		$tab   = in_array( $view['tab'] ?? '', self::RULES_TABS, true ) ? (string) $view['tab'] : self::RULES_TABS[0];
		$lists = array(
			'allowlist' => 'allowed-ips',
			'email'     => 'email-domains',
			'words'     => 'allowed-words',
		);

		if ( 'ips' === $tab || 'locations' === $tab ) {
			$first = '/zero-spam/v1/rules/blocks?kind=' . ( 'ips' === $tab ? 'ip' : 'location' ) . '&per_page=' . self::RULES_PER_PAGE . '&orderby=date_added&order=desc';
		} elseif ( isset( $lists[ $tab ] ) ) {
			$first = '/zero-spam/v1/rules/lists/' . $lists[ $tab ] . '?per_page=' . self::RULES_PER_PAGE;
		} else {
			$first = '/zero-spam/v1/rules/no-lockout';
		}

		return array( '/zero-spam/v1/settings/schema', $first );
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
			// Subsites cannot refresh the network's shared .htaccess (the Rules screen hides the button).
			'isMainSite'        => is_main_site(),
			// The Rules tabs' counts (one cheap query or option read each), on that page only.
			'rules'             => 'rules' === $area ? array( 'counts' => Rules_Controller::counts() ) : null,
			// What the Settings page shows besides the schema, on that page only.
			'settings'          => 'settings' === $area ? array( 'rescueKey' => self::rescue_key_set() ) : null,
			// The network page: the number of sites and the network's name (super admins only, on that page only).
			'network'           => 'network' === $area && is_multisite() && current_user_can( 'manage_network_options' ) ? array(
				'sites' => (int) get_sites( array( 'count' => true ) ),
				'name'  => wp_specialchars_decode( (string) get_network()->site_name, ENT_QUOTES ),
			) : null,
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
