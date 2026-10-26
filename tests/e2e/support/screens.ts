/**
 * One list of screens, shared by the smoke crawl, the accessibility scans,
 * the visual baselines and the gallery.
 *
 * - `sites`: single = single-site targets only, multisite = network targets
 *   only, both = every target (visual baselines for "both" screens are
 *   recorded on the single site only).
 * - `state`: the data the screen is shown with. "empty" = baseline settings
 *   and no detections; "seeded" = baseline settings plus 120 log rows and two
 *   blocked IPs; each spec prepares a state once before its group.
 * - `prepare`: what to do after the page loads (open a modal, expand a group).
 * - `visual`: whether a baseline is kept (true, or options).
 * - `theme`: the 6.0 screens follow the user's theme; "both" (their default)
 *   adds a dark twin (`{id}-dark`, prefers-color-scheme dark with the
 *   "auto" theme) that is scanned too. Legacy screens are light only.
 * - `level`: basic (default) or enhanced (a license key the canned API
 *   accepts; set before the page opens, undone by the next state reset).
 */

import type { Locator, Page } from '@playwright/test';

import type { Level, ZsApi } from './e2e-api';
import { COMMENTS_POST_PATH } from './pages/front';
import { subsiteURL, type Target } from './targets';

export type ScreenState = 'empty' | 'seeded';

export interface Screen {
	id: string;
	title: string;
	area: 'admin' | 'network' | 'front';
	sites: 'single' | 'multisite' | 'both';
	state: ScreenState;
	path: ( target: Target ) => string;
	/** Who looks at it: the administrator (default), the subsite administrator or a logged-out visitor. */
	as?: 'admin' | 'siteadmin' | 'visitor';
	/** Extra set-up through the API before the page is opened. */
	before?: ( zs: ZsApi, target: Target, ip: string ) => Promise< void >;
	/** Runs after the page loads; may return the HTTP status of a page it submitted. */
	prepare?: ( page: Page ) => Promise< number | void >;
	/** Selectors axe checks on this screen (front end: only the plugin's own areas). */
	include?: string[];
	/** Expected HTTP status (blocked pages answer 403). */
	status?: number;
	visual?: boolean | { mobile?: boolean; mask?: string[]; selector?: string; dark?: boolean };
	/** prefers-color-scheme to emulate (default: the fixtures' light). Screens that follow the OS add a dark twin. */
	colorScheme?: 'light' | 'dark';
	/** Themes scanned (6.0 screens): both (default for them) adds a `-dark` twin. */
	theme?: 'light' | 'dark' | 'both';
	/** Protection level the screen is shown with (default basic). */
	level?: Level;
}

/**
 * Address of a 6.0 admin page: an area (overview, activity, rules,
 * protection, settings, help, or network for the network admin page), an
 * optional tab and extra query arguments.
 *
 * @param area  Area.
 * @param tab   Tab (?tab=).
 * @param extra Extra query arguments.
 */
export function AREA( area: string, tab = '', extra: Record< string, string > = {} ): string {
	const page = 'overview' === area ? 'zero-spam' : `zero-spam-${ area }`;
	const base = 'network' === area ? 'wp-admin/network/admin.php' : 'wp-admin/admin.php';
	const args = new URLSearchParams( { page, ...( tab ? { tab } : {} ), ...extra } );
	return `${ base }?${ args.toString() }`;
}

const SETTINGS = 'wp-admin/options-general.php?page=wordpress-zero-spam-settings&subview=';
const DASHBOARD = 'wp-admin/index.php?page=wordpress-zero-spam-dashboard&subview=';
const NETWORK = 'wp-admin/network/settings.php?page=zerospam-network-settings&tab=';

/** Settings subviews: the fixed ones plus every module section registered without a third-party plugin. */
export const SETTINGS_SUBVIEWS = [
	'documentation',
	'settings',
	'ipinfo',
	'project_honeypot',
	'zerospam',
	'ipstack',
	'api-monitoring',
	'stop_forum_spam',
	'google',
	'debug',
	'registration',
	'comments',
	'ipbase',
	'security',
	'login',
	'davidwalsh',
	'export',
	'errors',
] as const;

/** Masks for screens with seeded data: dates, counts, charts and the map change from run to run. */
export const DYNAMIC_MASKS = [ '.zerospam-header__status', 'time' ];
export const REPORT_MASKS = [ ...DYNAMIC_MASKS, 'canvas', '#world-map', '.zerospam-block--review .zerospam-block__content', '.zerospam-block--list > .zerospam-block__content', '.zerospam-block--pie-chart .zerospam-block__content' ];
export const TABLE_MASKS = [ ...DYNAMIC_MASKS, 'td.column-date_recorded', '.tablenav .displaying-num' ];
/** The 6.0 dashboard widget: the figures, the sparkline (its days move at midnight) and the relative times. */
export const WIDGET_MASKS = [ '[data-zs-field="total"]', '[data-zs-field="delta"]', '.zs-w-spark', 'time' ];

/**
 * Waits until an element's CSS transitions and finite animations have ended.
 * The modals fade in over 0.5 s (opacity, assets/css/admin.css) and ignore
 * prefers-reduced-motion (AGENTS.md §8); scanned mid-fade, their text blends
 * with the overlay and axe reports contrast failures that depend on timing.
 *
 * @param locator Element.
 */
const settle = ( locator: Locator ) =>
	locator.evaluate( ( el ) =>
		Promise.all(
			el
				.getAnimations( { subtree: true } )
				.filter( ( a ) => Infinity !== a.effect?.getComputedTiming().endTime )
				.map( ( a ) => a.finished.catch( () => undefined ) )
		)
	);

const openModal = async ( page: Page, trigger: string, modal: string ) => {
	await page.locator( trigger ).first().click();
	await page.locator( modal ).waitFor();
	await settle( page.locator( modal ) );
};

const openFirstDetails = ( page: Page ) => openModal( page, '.zerospam-details-trigger', '.zerospam-modal.is-active' );

const openBlockModal = ( page: Page ) => openModal( page, '.zerospam-block-trigger', '#zerospam-block-ip.is-active' );

const openLocationModal = ( page: Page ) => openModal( page, '.zerospam-block-location-trigger', '#zerospam-block-location.is-active' );

const expandFirstGroup = async ( page: Page ) => {
	await page.locator( '.settings-group-toggle' ).first().click();
	await page.locator( '.settings-group-content' ).first().waitFor( { state: 'visible' } );
};

const settingsScreens: Screen[] = SETTINGS_SUBVIEWS.map( ( subview ) => ( {
	id: `settings-${ subview }`,
	title: `Settings › ${ subview }`,
	area: 'admin',
	sites: 'both',
	state: 'empty',
	path: () => SETTINGS + subview,
	visual: true,
} ) );

/** The rescue token the "Let me back in" screen opens (fetched in `before`, read by `path`). */
let rescueToken = '';

/** The Block_Page pages (core/front/class-blockpage.php): the copy, the wall and the bylines; IP, reference and time change. */
const FRONT_VISUAL = { mobile: true, selector: 'main.zs-blk', mask: [ '.zs-dl dd' ] };

/** Submits the blocked page's rescue form with an address (the browser's own validation is skipped, as a bot would). */
const submitRescue = async ( page: Page, email: string ): Promise< number > => {
	await page.locator( '.zs-owner summary' ).click();
	await page.locator( '#zerospam-rescue-email' ).fill( email );
	const response = page.waitForResponse( ( r ) => r.url().includes( 'admin-post.php' ) && 'POST' === r.request().method() );
	await page.locator( '.zs-owner form' ).evaluate( ( f: HTMLFormElement ) => HTMLFormElement.prototype.submit.call( f ) );
	const status = ( await response ).status();
	await page.waitForURL( /admin-post\.php/, { waitUntil: 'load' } );
	return status;
};

const blockVisitor = async ( zs: ZsApi, _t: Target, ip: string ) => {
	await zs.block( ip, 'Blocked page screen' );
};

const FRONT_PAGES: Screen[] = [
	{ id: 'front-blocked-403', title: 'Blocked visitor (403 page)', area: 'front', sites: 'both', state: 'empty', as: 'visitor', status: 403, before: blockVisitor, path: () => '', include: [ 'body' ], visual: FRONT_VISUAL },
	{
		id: 'front-rescue-form-error',
		title: 'Blocked page › rescue form error',
		area: 'front',
		sites: 'both',
		state: 'empty',
		as: 'visitor',
		status: 400,
		before: blockVisitor,
		path: () => '',
		prepare: ( page ) => submitRescue( page, 'not an email' ),
		include: [ 'body' ],
		visual: FRONT_VISUAL,
	},
	{
		id: 'front-rescue-sent',
		title: 'Blocked page › "Check your email"',
		area: 'front',
		sites: 'both',
		state: 'empty',
		as: 'visitor',
		before: blockVisitor,
		path: () => '',
		prepare: ( page ) => submitRescue( page, 'nobody@example.com' ),
		include: [ 'body' ],
		visual: FRONT_VISUAL,
	},
	{
		id: 'front-rescue-confirm',
		title: 'Rescue link › "Let me back in"',
		area: 'front',
		sites: 'both',
		state: 'empty',
		as: 'visitor',
		before: async ( zs, _t, ip ) => {
			await zs.block( ip, 'Blocked page screen' );
			rescueToken = await zs.rescueToken( ip );
		},
		path: () => `?zerospam_rescue_token=${ rescueToken }`,
		include: [ 'body' ],
		visual: FRONT_VISUAL,
	},
	{
		id: 'front-rescue-expired',
		title: 'Rescue link › "This link has expired"',
		area: 'front',
		sites: 'both',
		state: 'empty',
		as: 'visitor',
		status: 403,
		before: blockVisitor,
		// A well-formed token that was never issued (used and expired tokens look the same).
		path: () => `?zerospam_rescue_token=${ 'ab'.repeat( 32 ) }`,
		include: [ 'body' ],
		visual: FRONT_VISUAL,
	},
	{
		id: 'front-comment-blocked',
		title: 'Comment blocked page',
		area: 'front',
		sites: 'both',
		state: 'empty',
		as: 'visitor',
		status: 403,
		path: () => COMMENTS_POST_PATH,
		prepare: async ( page ) => {
			await page.locator( '#commentform' ).waitFor();
			await page.evaluate( () => {
				const form = document.querySelector< HTMLFormElement >( '#commentform' )!;
				const hp = form.querySelector< HTMLInputElement >( 'input[style*="display: none"]' );
				if ( hp ) {
					hp.value = 'i-am-a-bot';
				}
				( form.querySelector( '#comment' ) as HTMLTextAreaElement ).value = 'Blocked page screen ' + Date.now();
				( form.querySelector( '#author' ) as HTMLInputElement | null )?.setAttribute( 'value', 'Bot' );
				( form.querySelector( '#email' ) as HTMLInputElement | null )?.setAttribute( 'value', 'bot@example.com' );
			} );
			const response = page.waitForResponse( ( r ) => r.url().includes( 'wp-comments-post.php' ) && 'POST' === r.request().method() );
			// The comment form has a button named "submit", which shadows form.submit().
			await page.locator( '#commentform' ).evaluate( ( f: HTMLFormElement ) => HTMLFormElement.prototype.submit.call( f ) );
			const status = ( await response ).status();
			await page.waitForURL( /wp-comments-post\.php/, { waitUntil: 'load' } );
			return status;
		},
		include: [ 'body' ],
		visual: FRONT_VISUAL,
	},
];

/**
 * Overview parts that change between runs: relative times, the chart's
 * day boundaries (seeded rows are dated relative to "now"), the 24-hour
 * count (a row can sit right on the boundary), and the license and news
 * check times.
 */
export const OVERVIEW_MASKS = [
	'.zs-when',
	'.zs-hero-meta',
	'.zs-chartbox',
	'.zs-axis-m',
	'.zs-chart-foot',
	'.zs-stat:first-child .zs-stat-v',
	'.zs-count',
	'.zs-week .zs-kv',
	'.zs-news-upd .zs-hint',
	'.zs-sysfoot .zs-hint',
	'.zs-ni-pulse',
	'.zs-lvl-pop dd',
];

/** Waits until the Overview has its data (the app replaced the skeleton). */
const overviewReady = async ( page: Page ) => {
	await page.locator( '#zs-ov-h' ).waitFor();
	await page.locator( '.zs-zc, .zs-zc-member' ).first().waitFor( { state: 'attached' } );
	await page.locator( '.zs-feed, .zs-feed-tile .zs-empty' ).first().waitFor();
};

/** Seeds 15 blocked visits that only the Zero Spam network caught (TEST-NET IPs). */
export const seedNetworkCatches = async ( zs: ZsApi ) => {
	await zs.seedLog( 15, { type: 'blocked', failed: 'zero_spam', ipPrefix: '192.0.2.', spacingMinutes: 180 } );
};

/** Waits until a placeholder area has rendered. */
const areaReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
};

const V6_OVERVIEW: Screen[] = [
	{ id: 'v6-overview-empty', title: '6.0 Overview (fresh site)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'overview' ), prepare: overviewReady, visual: { mobile: true, mask: OVERVIEW_MASKS, selector: '#zerospam-app' } },
	{ id: 'v6-overview-seeded', title: '6.0 Overview (Basic, with detections)', area: 'admin', sites: 'both', state: 'seeded', theme: 'both', path: () => AREA( 'overview' ), prepare: overviewReady, visual: { mobile: true, dark: true, mask: OVERVIEW_MASKS, selector: '#zerospam-app' } },
	{
		id: 'v6-overview-enhanced',
		title: '6.0 Overview (Enhanced, with detections)',
		area: 'admin',
		sites: 'both',
		state: 'seeded',
		theme: 'both',
		level: 'enhanced',
		// 15 visits only the Zero Spam network caught (failed check zero_spam).
		before: seedNetworkCatches,
		path: () => AREA( 'overview' ),
		prepare: overviewReady,
		visual: { mobile: true, mask: [ ...OVERVIEW_MASKS, '.zs-hn-spark', '.zs-ni-spark', '.zs-spark-mini', '.zs-ni-tiles li:nth-child(3)' ], selector: '#zerospam-app' },
	},
	{
		id: 'v6-overview-milestone',
		title: '6.0 Overview › milestone and review ask',
		area: 'admin',
		sites: 'both',
		state: 'seeded',
		theme: 'both',
		path: () => AREA( 'overview' ),
		// The tile shows from 100 threats in 30 days (the seeded state has 120).
		prepare: overviewReady,
		visual: { selector: '.zs-milestone' },
	},
	{
		id: 'v6-overview-compare',
		title: '6.0 Overview › Compare levels drawer',
		area: 'admin',
		sites: 'both',
		state: 'seeded',
		theme: 'both',
		path: () => AREA( 'overview' ),
		prepare: async ( page ) => {
			await overviewReady( page );
			await page.locator( '.zs-zc-cta' ).click();
			await page.locator( '.zs-drawer.zs-compare' ).waitFor();
		},
		visual: { selector: '.zs-drawer' },
	},
	{
		id: 'v6-overview-level-popover',
		title: '6.0 Overview › license health popover (Enhanced)',
		area: 'admin',
		sites: 'both',
		state: 'seeded',
		theme: 'both',
		level: 'enhanced',
		path: () => AREA( 'overview' ),
		prepare: async ( page ) => {
			await overviewReady( page );
			await page.locator( '.zs-topbar .zs-lvl-enh' ).click();
			await page.locator( '.zs-topbar .zs-lvl-pop' ).waitFor();
		},
	},
];

/** The areas built in later PRs: the shell with a placeholder. */
const V6_AREAS: Screen[] = [ 'activity', 'rules', 'protection', 'help' ].map(
	( area ): Screen => ( {
		id: `v6-${ area }`,
		title: `6.0 ${ area } (shell)`,
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		path: () => AREA( area ),
		prepare: areaReady,
	} )
);

/** Waits until Settings has its cards (the app replaced the skeleton). */
export const settingsReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
	await page.locator( '#zs-set-body .zs-pcard, #zs-set-body .zs-empty' ).first().waitFor();
};

/**
 * Settings parts that change between runs or versions: the error log, the
 * system report's versions, the no-lockout addresses (the runner's IP).
 */
export const SETTINGS_MASKS = [ '#zs-errlog-box', '.zs-errlog .zs-hint', '.zs-sysrep > .zs-hint', '.zs-iplist', '.zs-nolock > p:first-child' ];

/** Baselines capture the tab panel: light 1280 for every tab, 782 and dark for General (decision 9). */
const SETTINGS_VISUAL = { mask: SETTINGS_MASKS, selector: '#zs-set-body' };

const SETTINGS_TABS = [
	[ 'general', 'General' ],
	[ 'data', 'Logging & data' ],
	[ 'api', 'API monitoring' ],
	[ 'advanced', 'Advanced' ],
	[ 'debug', 'Debug' ],
	[ 'io', 'Import & errors' ],
] as const;

const V6_SETTINGS: Screen[] = [
	...SETTINGS_TABS.map(
		( [ tab, name ] ): Screen => ( {
			id: `v6-settings-${ tab }`,
			title: `6.0 Settings › ${ name }`,
			area: 'admin',
			sites: 'both',
			state: 'empty',
			theme: 'both',
			path: () => AREA( 'settings', 'general' === tab ? '' : tab ),
			prepare: settingsReady,
			visual: 'general' === tab ? { ...SETTINGS_VISUAL, mobile: true, dark: true } : SETTINGS_VISUAL,
		} )
	),
	{
		id: 'v6-settings-error-log',
		title: '6.0 Settings › Import & errors with an error log',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: async ( zs ) => {
			await zs.call( 'POST', 'error-log', { message: 'Zero Spam e2e: sample error log entry.' } );
		},
		path: () => AREA( 'settings', 'io' ),
		prepare: async ( page ) => {
			await settingsReady( page );
			await page.locator( 'textarea#zs-errlog-box' ).waitFor();
		},
	},
	{
		id: 'v6-settings-unsaved',
		title: '6.0 Settings › unsaved changes on two tabs',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		path: () => AREA( 'settings', 'data' ),
		prepare: async ( page ) => {
			await settingsReady( page );
			await page.locator( '#zs-f-max_logs' ).fill( '5000' );
			await page.locator( '#zs-st-debug' ).click();
			await page.locator( '#zs-f-debug_ip' ).fill( '192.0.2.10' );
			await page.locator( '.zs-savebar' ).waitFor();
		},
	},
	{
		id: 'v6-settings-proxies-error',
		title: '6.0 Settings › Trusted proxies with dropped entries',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		path: () => AREA( 'settings', '', { section: 'proxies' } ),
		prepare: async ( page ) => {
			await settingsReady( page );
			await page.locator( '#zs-f-trusted_proxies' ).fill( 'not-an-ip\n0.0.0.0/0' );
			await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
			await page.locator( '#zs-e-trusted_proxies' ).waitFor();
		},
	},
	{
		id: 'v6-settings-locked',
		title: '6.0 Settings on a subsite with locked and network-provided settings',
		area: 'admin',
		sites: 'multisite',
		state: 'empty',
		theme: 'both',
		as: 'siteadmin',
		before: async ( zs ) => {
			await zs.networkSetting( 'share_data', 'enabled', true );
			await zs.networkSetting( 'log_blocked_ips', 'enabled', true );
			await zs.networkSetting( 'max_logs', 20000 );
			await zs.networkSetting( 'alert_webhook_url', 'https://hooks.example.com/e2e-network-hook' );
		},
		path: ( t ) => subsiteURL( t, AREA( 'settings', 'data' ) ),
		prepare: settingsReady,
	},
];

const V6_NETWORK: Screen[] = [
	{ id: 'v6-network', title: '6.0 Network (shell)', area: 'network', sites: 'multisite', state: 'empty', theme: 'both', path: () => AREA( 'network' ), prepare: areaReady },
];

/**
 * Adds the dark twin of every screen that follows the theme ("both"): the
 * same screen under prefers-color-scheme dark (the default theme is
 * "auto"). Only the screens that ask for one (`visual.dark`) keep a dark
 * baseline (build plan decision 9).
 *
 * @param screens Screens.
 */
function withDarkTwins( screens: Screen[] ): Screen[] {
	return screens.flatMap( ( screen ): Screen[] => {
		if ( 'both' !== ( screen.theme ?? 'light' ) ) {
			return [ screen ];
		}
		const options = 'object' === typeof screen.visual ? screen.visual : {};
		return [
			{ ...screen, theme: 'light' },
			{
				...screen,
				id: `${ screen.id }-dark`,
				title: `${ screen.title } (dark)`,
				theme: 'dark',
				colorScheme: 'dark',
				visual: options.dark ? { mask: options.mask, selector: options.selector } : false,
			},
		];
	} );
}

const V6_SCREENS: Screen[] = withDarkTwins( [ ...V6_OVERVIEW, ...V6_AREAS, ...V6_SETTINGS, ...V6_NETWORK ] );

export const SCREENS: Screen[] = [
	...settingsScreens,
	{
		id: 'settings-errors-with-entries',
		title: 'Settings › Error Log with entries',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		before: async ( zs ) => {
			await zs.call( 'POST', 'error-log', { message: 'Zero Spam e2e: sample error log entry.' } );
		},
		path: () => SETTINGS + 'errors',
		visual: { mask: [ 'textarea' ] },
	},
	{ id: 'dashboard-reports-empty', title: 'Dashboard (no detections)', area: 'admin', sites: 'both', state: 'empty', path: () => DASHBOARD + 'reports', visual: { mobile: true, mask: REPORT_MASKS } },
	{ id: 'dashboard-log-empty', title: 'Log (empty)', area: 'admin', sites: 'both', state: 'empty', path: () => DASHBOARD + 'log', visual: true },
	{ id: 'dashboard-blocked-ips-empty', title: 'Blocked IPs (empty)', area: 'admin', sites: 'both', state: 'empty', path: () => DASHBOARD + 'blocked-ips', visual: true },
	{ id: 'dashboard-blocked-locations-empty', title: 'Blocked Locations (empty)', area: 'admin', sites: 'both', state: 'empty', path: () => DASHBOARD + 'blocked-locations', visual: true },
	{ id: 'dashboard-reports-seeded', title: 'Dashboard (with detections)', area: 'admin', sites: 'both', state: 'seeded', path: () => DASHBOARD + 'reports', visual: { mobile: true, mask: REPORT_MASKS } },
	{ id: 'dashboard-log-seeded', title: 'Log (with detections)', area: 'admin', sites: 'both', state: 'seeded', path: () => DASHBOARD + 'log', visual: { mobile: true, mask: TABLE_MASKS } },
	{ id: 'dashboard-log-details-modal', title: 'Log › detection details modal', area: 'admin', sites: 'both', state: 'seeded', path: () => DASHBOARD + 'log', prepare: openFirstDetails, visual: { mask: [ '.zerospam-modal.is-active .zerospam-list__value' ], selector: '.zerospam-modal.is-active' } },
	{ id: 'dashboard-blocked-ips-seeded', title: 'Blocked IPs (with blocks)', area: 'admin', sites: 'both', state: 'seeded', path: () => DASHBOARD + 'blocked-ips', visual: { mask: [ ...TABLE_MASKS, 'td.column-start_block', 'td.column-end_block', 'td.column-date_added' ] } },
	{ id: 'dashboard-block-ip-modal', title: 'Blocked IPs › add/update modal', area: 'admin', sites: 'both', state: 'seeded', path: () => DASHBOARD + 'blocked-ips', prepare: openBlockModal, visual: { selector: '#zerospam-block-ip' } },
	{ id: 'dashboard-block-location-modal', title: 'Blocked Locations › add/update modal', area: 'admin', sites: 'both', state: 'empty', path: () => DASHBOARD + 'blocked-locations', prepare: openLocationModal, visual: { selector: '#zerospam-block-location' } },
	{ id: 'wp-dashboard-widget', title: 'WordPress Dashboard › Zero Spam overview widget', area: 'admin', sites: 'both', state: 'seeded', path: () => 'wp-admin/index.php', include: [ '#zerospam_unified_widget' ], visual: { mobile: true, mask: WIDGET_MASKS, selector: '#zerospam_unified_widget' } },
	{
		id: 'wp-dashboard-promo-notice',
		title: 'WordPress Dashboard with the Enhanced Protection notice',
		area: 'admin',
		sites: 'single',
		state: 'empty',
		before: async ( zs ) => {
			await zs.reset( 'baseline', { promo: true } );
		},
		path: () => 'wp-admin/index.php',
		include: [ '.zerospam-promo-notice' ],
		visual: { selector: '.zerospam-promo-notice' },
	},

	// The 6.0 admin (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json).
	...V6_SCREENS,

	// Network admin.
	...[ 'overview', 'settings', 'templates', 'audit', 'comparison', 'import-export', 'notifications' ].map(
		( tab ): Screen => ( {
			id: `network-${ tab }`,
			title: `Network Settings › ${ tab }`,
			area: 'network',
			sites: 'multisite',
			state: 'empty',
			path: () => NETWORK + tab,
			visual: { mask: [ '.status-text', 'time', '.zerospam-audit-table td:first-child' ] },
		} )
	),
	{ id: 'network-settings-group-expanded', title: 'Network Settings › settings group expanded', area: 'network', sites: 'multisite', state: 'empty', path: () => NETWORK + 'settings', prepare: expandFirstGroup, visual: { mask: [ '.status-text' ] } },
	{ id: 'network-stats', title: 'Network Stats', area: 'network', sites: 'multisite', state: 'seeded', path: () => 'wp-admin/network/settings.php?page=wordpress-zero-spam-network-stats', visual: { mask: [ ...DYNAMIC_MASKS, 'canvas', 'td', '.stat-value', '.stat-card' ] } },
	{ id: 'network-dashboard-widget', title: 'Network Dashboard › Zero Spam network overview widget', area: 'network', sites: 'multisite', state: 'seeded', path: () => 'wp-admin/network/index.php', include: [ '#zerospam_unified_widget' ], visual: { mask: WIDGET_MASKS, selector: '#zerospam_unified_widget' } },
	{
		id: 'subsite-settings-locked',
		title: 'Subsite settings while "Protect Comments" is locked by the network',
		area: 'admin',
		sites: 'multisite',
		state: 'empty',
		as: 'siteadmin',
		before: async ( zs ) => {
			await zs.call( 'POST', 'network-setting', { key: 'verify_comments', value: 'enabled', locked: true } );
		},
		path: ( t ) => subsiteURL( t, SETTINGS + 'comments' ),
		visual: true,
	},

	// Front end (only the plugin's own areas are scanned).
	{ id: 'front-comment-form', title: 'Post with the comment form', area: 'front', sites: 'both', state: 'empty', as: 'visitor', path: () => COMMENTS_POST_PATH, include: [ '#commentform' ] },
	{ id: 'front-login', title: 'wp-login.php', area: 'front', sites: 'both', state: 'empty', as: 'visitor', path: () => 'wp-login.php', include: [ '#loginform' ] },
	{ id: 'front-register', title: 'Registration form', area: 'front', sites: 'single', state: 'empty', as: 'visitor', path: () => 'wp-login.php?action=register', include: [ '#registerform' ] },
	...FRONT_PAGES.flatMap( ( screen ): Screen[] => [
		screen,
		// The pages follow the visitor's operating system: scan the dark theme too (no baseline; decision 9).
		{ ...screen, id: `${ screen.id }-dark`, title: `${ screen.title } (dark)`, colorScheme: 'dark', visual: false },
	] ),
];

/**
 * Screens that apply to a target.
 *
 * @param target Target.
 */
export function screensFor( target: Target ): Screen[] {
	return SCREENS.filter( ( s ) => {
		if ( 'single' === s.sites && target.multisite ) {
			return false;
		}
		if ( 'multisite' === s.sites && ! target.multisite ) {
			return false;
		}
		return true;
	} );
}
