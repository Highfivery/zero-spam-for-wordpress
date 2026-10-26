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
	/** Viewport the screen is opened at (accessibility scans of phone layouts). */
	viewport?: { width: number; height: number };
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

/*
 * Baseline policy for the Overview, as for the other areas (AREA_BODY): its
 * body only (the in-app sidebar and top bar have their own baselines, taken
 * on Help), light 1280px for each state, 782px and dark 1280px for the key state (seeded) only.
 */
const OVERVIEW_BODY = '.zs-overview';

const V6_OVERVIEW: Screen[] = [
	{ id: 'v6-overview-empty', title: '6.0 Overview (fresh site)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'overview' ), prepare: overviewReady, visual: { mask: OVERVIEW_MASKS, selector: OVERVIEW_BODY } },
	// The area's key screen: 782 and dark here only.
	{ id: 'v6-overview-seeded', title: '6.0 Overview (Basic, with detections)', area: 'admin', sites: 'both', state: 'seeded', theme: 'both', path: () => AREA( 'overview' ), prepare: overviewReady, visual: { mobile: true, dark: true, mask: OVERVIEW_MASKS, selector: OVERVIEW_BODY } },
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
		visual: { mask: [ ...OVERVIEW_MASKS, '.zs-hn-spark', '.zs-ni-spark', '.zs-spark-mini', '.zs-ni-tiles li:nth-child(3)' ], selector: OVERVIEW_BODY },
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

/**
 * Activity parts that change between runs: relative times, the sidebar
 * count, detection IDs and hour-of-day charts. Table and card text is
 * hidden by visual.css.
 */
export const ACTIVITY_MASKS = [ '.zs-cchart-wrap', '.zs-drawer .zs-eyebrow', '.zs-drawer .zs-dkv' ];

/** Waits until the Activity log has its rows (or its empty state). */
export const activityReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
	await page.locator( '.zs-log-t tbody tr, .zs-cards li, .zs-log .zs-empty' ).first().waitFor();
};

/** Waits until the Reports tab has its cards. */
export const reportsReady = async ( page: Page ) => {
	await page.locator( '.zs-reports' ).waitFor();
	await page.locator( '.zs-rep:not([aria-busy])' ).waitFor();
	await page.locator( '#zs-rp-type' ).waitFor();
};

/**
 * The Activity screens' data (baseline policy: 8–10 rows): nine detections
 * from nine addresses, one of them blocked; with `network`, three of them
 * caught by the Zero Spam network.
 *
 * @param network Seed network catches too.
 */
const seedActivity =
	( network = false ) =>
	async ( zs: ZsApi ) => {
		await zs.seedLog( network ? 6 : 9, { spacingMinutes: 90 } );
		if ( network ) {
			await zs.seedLog( 3, { type: 'blocked', failed: 'zero_spam', ipPrefix: '192.0.2.', spacingMinutes: 240 } );
		}
		await zs.block( '203.0.113.2', 'Seeded block' );
	};

/*
 * Baseline policy for the area screens: the area body only (the in-app
 * sidebar and top bar have their own light and dark 1280px baselines, taken
 * on Help: v6-help-sidebar and v6-help-topbar), 782px for one key screen, one
 * dark 1280px screen per area.
 */
const AREA_BODY = '.zs-areabody';

const V6_ACTIVITY: Screen[] = [
	{ id: 'v6-activity-empty', title: '6.0 Activity (no detections)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'activity' ), prepare: activityReady, visual: { mask: ACTIVITY_MASKS, selector: AREA_BODY } },
	{ id: 'v6-activity-seeded', title: '6.0 Activity (with detections)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: seedActivity(), path: () => AREA( 'activity' ), prepare: activityReady, visual: { mobile: true, dark: true, mask: ACTIVITY_MASKS, selector: AREA_BODY } },
	{ id: 'v6-activity-phone', title: '6.0 Activity at 375px (cards)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', viewport: { width: 375, height: 812 }, before: seedActivity(), path: () => AREA( 'activity' ), prepare: activityReady },
	{
		id: 'v6-activity-drawer',
		title: '6.0 Activity › details drawer',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: seedActivity(),
		path: () => AREA( 'activity' ),
		prepare: async ( page ) => {
			await activityReady( page );
			await page.locator( '[data-zs-open]' ).first().click();
			await page.locator( '.zs-drawer.zs-details .zs-drawer-f .zs-btn' ).first().waitFor();
		},
		visual: { mask: ACTIVITY_MASKS, selector: '.zs-drawer' },
	},
	{
		id: 'v6-activity-enhanced-network',
		title: '6.0 Activity (Enhanced, Network-only filter)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		level: 'enhanced',
		before: seedActivity( true ),
		path: () => AREA( 'activity', '', { network: '1' } ),
		prepare: activityReady,
		visual: { mask: ACTIVITY_MASKS, selector: AREA_BODY },
	},
	{ id: 'v6-reports-seeded', title: '6.0 Activity › Reports (with detections)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: seedActivity(), path: () => AREA( 'activity', 'reports' ), prepare: reportsReady, visual: { mask: ACTIVITY_MASKS, selector: AREA_BODY } },
	{
		id: 'v6-reports-enhanced',
		title: '6.0 Activity › Reports (Enhanced)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		level: 'enhanced',
		before: seedActivity( true ),
		path: () => AREA( 'activity', 'reports' ),
		prepare: reportsReady,
		visual: { mask: ACTIVITY_MASKS, selector: AREA_BODY },
	},
];

/**
 * Protection parts that change between runs: relative times and counts,
 * the license check time and the self-test timings.
 */
export const PROTECTION_MASKS = [ '.zs-pcard-st', '.zs-lic-live', '.zs-st-time', '.zs-st-result', '#zs-row-regenerate_honeypot .zs-fdesc code' ];

/** Baseline policy (see AREA_BODY): 782px and the dark shot on Protection › Detection only. */
const PROTECTION_VISUAL = { mask: PROTECTION_MASKS, selector: AREA_BODY };

/** Phone width for the accessibility scans (label-in-name, 2.5.3, and reflow of the cards). */
const PHONE = { width: 375, height: 812 };

/** A few detections (8–10 rows per the baseline policy), so the cards' detection lines have data. */
const seedFew = async ( zs: ZsApi ) => {
	await zs.seedLog( 9 );
};

/** Waits until Protection has its cards (the app replaced the skeleton). */
export const protectionReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
	await page.locator( '#zs-prot-body .zs-pcard, #zs-prot-body .zs-empty' ).first().waitFor();
};

/** Runs "Test my protection" and waits for its result. */
export const runSelfTest = async ( page: Page ) => {
	await protectionReady( page );
	await page.locator( '.zs-test-btn' ).click();
	await page.locator( '#zs-selftest .zs-st-result:not(:empty)' ).waitFor();
	await page.locator( '#zs-selftest[aria-busy="true"]' ).waitFor( { state: 'detached' } );
};

const V6_PROTECTION: Screen[] = [
	{ id: 'v6-protection-detection', title: '6.0 Protection › Detection', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: seedFew, path: () => AREA( 'protection' ), prepare: protectionReady, visual: { ...PROTECTION_VISUAL, mobile: true, dark: true } },
	{
		id: 'v6-protection-integrations',
		title: '6.0 Protection › Integrations (with a third-party section)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: async ( zs ) => {
			await seedFew( zs );
			await zs.fakeSection( true );
		},
		path: () => AREA( 'protection', 'integrations' ),
		prepare: async ( page ) => {
			await protectionReady( page );
			await page.locator( '#zs-card-moreint > summary' ).click();
		},
		visual: PROTECTION_VISUAL,
	},
	{ id: 'v6-protection-intelligence', title: '6.0 Protection › Intelligence (Basic)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'protection', 'intelligence' ), prepare: protectionReady, visual: PROTECTION_VISUAL },
	{
		id: 'v6-protection-intelligence-enhanced',
		title: '6.0 Protection › Intelligence (Enhanced, license panel)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		level: 'enhanced',
		path: () => AREA( 'protection', 'intelligence' ),
		prepare: async ( page ) => {
			await protectionReady( page );
			await page.locator( '.zs-licpanel' ).waitFor();
			await page.locator( '.zs-lic-adv > summary' ).click();
		},
		visual: PROTECTION_VISUAL,
	},
	// Outside the area body: scanned, no baseline.
	{
		id: 'v6-protection-compare',
		title: '6.0 Protection › Compare levels drawer (from Intelligence)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		path: () => AREA( 'protection', 'intelligence' ),
		prepare: async ( page ) => {
			await protectionReady( page );
			await page.locator( '.zs-enh-mini' ).getByRole( 'button', { name: 'Compare levels' } ).click();
			await page.locator( '.zs-drawer.zs-compare' ).waitFor();
		},
	},
	{
		id: 'v6-protection-selftest',
		title: '6.0 Protection › Test my protection (result)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: async ( zs ) => {
			await zs.selfTestReset();
		},
		path: () => AREA( 'protection' ),
		prepare: runSelfTest,
	},
	// Phone width (375px): accessibility only.
	{ id: 'v6-protection-detection-375', title: '6.0 Protection › Detection at 375px', area: 'admin', sites: 'both', state: 'empty', theme: 'light', viewport: PHONE, path: () => AREA( 'protection' ), prepare: protectionReady },
	{
		id: 'v6-protection-intelligence-375',
		title: '6.0 Protection › Intelligence at 375px (key fields, Enhanced)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'light',
		level: 'enhanced',
		viewport: PHONE,
		before: async ( zs ) => {
			await zs.set( { ipstack_api: 'e2e-ipstack-key-1234' } );
		},
		path: () => AREA( 'protection', 'intelligence' ),
		prepare: async ( page ) => {
			await protectionReady( page );
			await page.locator( '#zs-card-ipstack' ).waitFor();
		},
	},
	{
		id: 'v6-protection-locked',
		title: '6.0 Protection on a subsite with locked settings',
		area: 'admin',
		sites: 'multisite',
		state: 'empty',
		theme: 'both',
		as: 'siteadmin',
		before: async ( zs ) => {
			await zs.networkSetting( 'verify_comments', 'enabled', true );
			await zs.networkSetting( 'davidwalsh_form_selectors', '.network-form', true );
		},
		path: ( t ) => subsiteURL( t, AREA( 'protection' ) ),
		prepare: protectionReady,
		visual: PROTECTION_VISUAL,
	},
];

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

/** Baseline policy (see AREA_BODY): light 1280 for every tab; 782px and the dark shot on General only. */
const SETTINGS_VISUAL = { mask: SETTINGS_MASKS, selector: AREA_BODY };

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

/** Waits until a network tab has its content (the app replaced the skeleton). */
export const networkReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
	await page.locator( '#zs-net-body > :not(.zs-cardcol[aria-hidden="true"])' ).first().waitFor();
};

/**
 * What the network page shows with data (every list 2–8 rows): network
 * values (one locked, one empty), a subsite that changed a default, a
 * custom template, a network-wide block (audit entries come with them),
 * and one TEST-NET address detected on both sites (a multi-site attacker).
 *
 * @param zs ZsApi of the main site (super admin).
 * @param t  Target (its subsite).
 */
export const seedNetwork = async ( zs: ZsApi, t: Target ) => {
	await zs.networkSetting( 'share_data', 'enabled', true );
	await zs.networkSetting( 'blocked_message', '' );
	// Through the settings route, so the audit log has "Change" entries.
	await zs.plugin( 'PATCH', 'settings', { max_logs: 20000, verify_comments: 'enabled' }, { scope: 'network' } );
	await zs.forSite( t.subsitePath! ).set( { max_logs: 25000 } );
	await zs.plugin( 'POST', 'network-settings/templates', {
		name: 'E2E clinic sites',
		slug: 'e2e-clinic-sites',
		description: 'Our standard for patient-facing sites.',
		settings: { max_logs: 10000, verify_comments: 'enabled' },
	} );
	await zs.plugin( 'POST', 'network/blocks', { ip: '198.51.100.24', reason: 'E2E: seen on several sites' } );
	await zs.seedLog( 6, { ip: '203.0.113.9', spacingMinutes: 60 } );
	await zs.forSite( t.subsitePath! ).seedLog( 4, { ip: '203.0.113.9', spacingMinutes: 60 } );
};

/** Parts of the network page that change between runs: dates and the site addresses (ports). */
export const NETWORK_MASKS = [ '.zs-c-date', '.zs-dom', '.zs-cmp-t thead .zs-hint', '.zs-nblocks .zs-cm-m', '.zs-tplcard .zs-hint' ];

/** Baseline policy (AREA_BODY): light 1280 for every tab; 782px and the dark shot on Settings only. */
const NETWORK_VISUAL = { mask: NETWORK_MASKS, selector: AREA_BODY };

/** The network tabs (Admin_App::NETWORK_TABS), with names. */
export const NETWORK_TABS = [
	[ 'overview', 'Overview' ],
	[ 'settings', 'Settings' ],
	[ 'templates', 'Templates' ],
	[ 'audit', 'Audit log' ],
	[ 'comparison', 'Comparison' ],
	[ 'io', 'Import/Export' ],
	[ 'notifications', 'Notifications' ],
	[ 'stats', 'Network stats' ],
] as const;

/** The groups of the network Settings tab (&group=), one per area/group of the settings schema it shows. */
export const NETWORK_SETTING_GROUPS = [ 'detection', 'integrations', 'intelligence', 'settings', 'rules' ] as const;

/** Waits until Help has its guides and news (or the news's empty state). */
export const helpReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
	await page.locator( '.zs-help-doc' ).first().waitFor();
	await page.locator( '#zs-help-news :is(.zs-news-i, .zs-empty)' ).first().waitFor();
	await page.locator( '.zs-help-level:not([aria-busy])' ).waitFor();
};

/** Help parts that change between runs: when the news was fetched. */
export const HELP_MASKS = [ '.zs-news-upd .zs-hint' ];

/** Waits until first-run setup shows a step (or the done screen). */
export const onboardingReady = async ( page: Page ) => {
	await page.locator( '.zs-onb #zs-ob-h' ).waitFor();
};

/**
 * Goes through first-run setup up to a step (Basic, everything ticked).
 *
 * @param step 1, 2, 3 or 4 (the done screen).
 */
export const onboardingTo = ( step: number ) => async ( page: Page ) => {
	await onboardingReady( page );
	for ( let at = 1; at < step; at++ ) {
		await page.locator( '.zs-onb form button[type="submit"]' ).click();
		// The next step is current; after step 3 every step is done.
		const next = at < 3 ? `.zs-ob-steps li:nth-child(${ at + 1 })[aria-current="step"]` : '.zs-ob-steps li:nth-child(3).zs-done';
		await page.locator( next ).waitFor();
	}
	await onboardingReady( page );
	// The mark's bricks drop in (not under reduced motion): wait for them to land.
	await page.locator( '.zs-onb-mark' ).evaluate( ( el ) => Promise.all( el.getAnimations( { subtree: true } ).map( ( a ) => a.finished.catch( () => undefined ) ) ) );
};

/**
 * Help, first-run setup and What's new. Baselines (area-PR policy): Help's
 * area body (`.zs-areabody`) at 1280, plus 782 and dark 1280 for Help
 * only; the setup steps (`.zs-onb`, a standalone view without an area body)
 * and the What's new drawer at light 1280 only. The news is the mu-plugin's
 * canned feed (eight fixed articles); its fetch time is masked.
 */
/**
 * The shell's own baselines (light and dark 1280px), taken on Help: the
 * in-app sidebar (nav, the Enhanced Protection card, Follow) with its
 * Activity count and the latest news masked, and the top bar with its count.
 */
const SHELL_MASKS = [ '.zs-count', '.zs-sn', 'time' ];

const V6_HELP: Screen[] = [
	{ id: 'v6-help-sidebar', title: '6.0 shell › in-app sidebar (on Help)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'help' ), prepare: helpReady, visual: { dark: true, mask: SHELL_MASKS, selector: '.zs-side' } },
	{ id: 'v6-help-topbar', title: '6.0 shell › top bar (on Help)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'help' ), prepare: helpReady, visual: { dark: true, mask: SHELL_MASKS, selector: '.zs-topbar' } },
	{ id: 'v6-help', title: '6.0 Help (Basic, canned news)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'help' ), prepare: helpReady, visual: { mobile: true, dark: true, mask: HELP_MASKS, selector: '.zs-areabody' } },
	{ id: 'v6-help-enhanced', title: '6.0 Help (Enhanced, member card)', area: 'admin', sites: 'both', state: 'empty', theme: 'both', level: 'enhanced', path: () => AREA( 'help' ), prepare: helpReady },
	{
		id: 'v6-help-news-unavailable',
		title: '6.0 Help › news unavailable',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: async ( zs ) => {
			await zs.newsDown( true );
		},
		path: () => AREA( 'help', '', { section: 'news' } ),
		prepare: helpReady,
		visual: { mask: HELP_MASKS, selector: '.zs-areabody' },
	},
	{
		id: 'v6-help-news-off',
		title: '6.0 Help › Zero Spam News turned off',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: async ( zs ) => {
			await zs.set( { show_news: 'disabled' } );
		},
		path: () => AREA( 'help', '', { section: 'news' } ),
		prepare: helpReady,
	},
	{ id: 'v6-onboarding-1', title: '6.0 First-run setup › 1 protection level', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: ( zs ) => zs.firstRun( 'fresh' ).then( () => undefined ), path: () => AREA( 'overview' ), prepare: onboardingTo( 1 ), visual: { selector: '.zs-onb' } },
	{ id: 'v6-onboarding-2', title: '6.0 First-run setup › 2 what to protect', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: ( zs ) => zs.firstRun( 'fresh' ).then( () => undefined ), path: () => AREA( 'overview' ), prepare: onboardingTo( 2 ), visual: { selector: '.zs-onb' } },
	{ id: 'v6-onboarding-3', title: '6.0 First-run setup › 3 how to block', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: ( zs ) => zs.firstRun( 'fresh' ).then( () => undefined ), path: () => AREA( 'overview' ), prepare: onboardingTo( 3 ), visual: { selector: '.zs-onb' } },
	{ id: 'v6-onboarding-done', title: '6.0 First-run setup › done', area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: ( zs ) => zs.firstRun( 'fresh' ).then( () => undefined ), path: () => AREA( 'overview' ), prepare: onboardingTo( 4 ) },
	{
		id: 'v6-whats-new',
		title: '6.0 Overview › "What\'s new in 6.0" drawer (upgraded site)',
		area: 'admin',
		sites: 'both',
		state: 'empty',
		theme: 'both',
		before: ( zs ) => zs.firstRun( 'upgraded' ).then( () => undefined ),
		path: () => AREA( 'overview' ),
		prepare: async ( page ) => {
			await overviewReady( page );
			await page.locator( '.zs-whatsnew' ).getByRole( 'button', { name: "See what's new" } ).click();
			await page.locator( '.zs-drawer.zs-whatsnew-drawer' ).waitFor();
		},
		visual: { selector: '.zs-drawer' },
	},
];

const V6_NETWORK: Screen[] = [
	...NETWORK_TABS.map(
		( [ tab, name ] ): Screen => ( {
			id: `v6-network-${ tab }`,
			title: `6.0 Network › ${ name }`,
			area: 'network',
			sites: 'multisite',
			state: 'empty',
			theme: 'both',
			before: seedNetwork,
			path: () => AREA( 'network', 'overview' === tab ? '' : tab ),
			prepare: networkReady,
			visual: 'settings' === tab ? { ...NETWORK_VISUAL, mobile: true, dark: true } : NETWORK_VISUAL,
		} )
	),
	...NETWORK_SETTING_GROUPS.map(
		( group ): Screen => ( {
			id: `v6-network-settings-${ group }`,
			title: `6.0 Network › Settings › ${ group }`,
			area: 'network',
			sites: 'multisite',
			state: 'empty',
			theme: 'both',
			before: seedNetwork,
			path: () => AREA( 'network', 'settings', { group } ),
			prepare: async ( page ) => {
				await networkReady( page );
				await page.getByRole( 'button', { name: 'Expand all' } ).click();
			},
		} )
	),
	{ id: 'v6-network-empty', title: '6.0 Network › Overview (no network values)', area: 'network', sites: 'multisite', state: 'empty', theme: 'both', path: () => AREA( 'network' ), prepare: networkReady },
	...( [ 'settings', 'stats', 'audit', 'comparison', 'templates' ] as const ).flatMap( ( tab ): Screen[] => [
		{ id: `v6-network-${ tab }-782`, title: `6.0 Network › ${ tab } at 782px`, area: 'network', sites: 'multisite', state: 'empty', theme: 'both', viewport: { width: 782, height: 900 }, before: seedNetwork, path: () => AREA( 'network', tab ), prepare: networkReady },
		{ id: `v6-network-${ tab }-375`, title: `6.0 Network › ${ tab } at 375px`, area: 'network', sites: 'multisite', state: 'empty', theme: 'both', viewport: PHONE, before: seedNetwork, path: () => AREA( 'network', tab ), prepare: networkReady },
	] ),
	{
		id: 'v6-network-template-drawer',
		title: '6.0 Network › Templates › edit drawer',
		area: 'network',
		sites: 'multisite',
		state: 'empty',
		theme: 'both',
		before: seedNetwork,
		path: () => AREA( 'network', 'templates' ),
		prepare: async ( page ) => {
			await networkReady( page );
			await page.getByRole( 'button', { name: 'Edit E2E clinic sites' } ).click();
			await page.locator( '.zs-drawer #zs-tp-name' ).waitFor();
		},
	},
	{
		id: 'v6-network-subsite-blocked-ips',
		title: '6.0 Rules › Blocked IPs on a subsite with a network-wide block',
		area: 'admin',
		sites: 'multisite',
		state: 'empty',
		theme: 'both',
		as: 'siteadmin',
		before: async ( zs ) => {
			await zs.plugin( 'POST', 'network/blocks', { ip: '198.51.100.24', reason: 'E2E: seen on several sites' } );
		},
		path: ( t ) => subsiteURL( t, AREA( 'rules', 'ips' ) ),
		prepare: async ( page ) => {
			await rulesReady( page );
			await page.locator( '.zs-netblocks > summary' ).click();
			await page.locator( '.zs-netblocks .zs-cards li' ).first().waitFor();
		},
	},
];

/** Waits until a Rules tab has its list (rows, cards, entries or its empty state). */
export const rulesReady = async ( page: Page ) => {
	await page.locator( '#zs-area-h' ).waitFor();
	await page.locator( '#zs-rules-body :is(tbody tr, .zs-cards li, .zs-safelist li, .zs-empty)' ).first().waitFor();
};

/**
 * Seeds what each Rules tab shows, 2–8 rows each (the seeded state already
 * has two blocked IPs). Baselines are of the tab panel (.zs-areabody);
 * dates are hidden by support/visual.css.
 */
export const seedRules = async ( zs: ZsApi ) => {
	// 8 blocked IPs in all on the key screen (the seeded state has two).
	for ( const ip of [ '198.51.100.21', '198.51.100.22', '203.0.113.31', '203.0.113.32', '192.0.2.41', '2001:db8::51' ] ) {
		await zs.block( ip, 'E2E: rules sample' );
	}
	await zs.plugin( 'POST', 'rules/blocks', { kind: 'location', key_type: 'country_code', key: 'DE', type: 'permanent', reason: 'E2E: steady comment spam' } );
	await zs.plugin( 'POST', 'rules/blocks', { kind: 'location', key_type: 'zip', key: '90210', type: 'temporary', end: new Date( Date.now() + 7 * 864e5 ).toISOString(), reason: 'E2E: card testing' } );
	await zs.plugin( 'PUT', 'rules/lists/allowed-ips', { items: [ '203.0.113.5', '2001:db8::1' ] } );
	await zs.plugin( 'PUT', 'rules/lists/allowed-words', { items: [ 'patel', 'van gogh' ], min_length: 4 } );
	await zs.noLockout( [ '198.51.100.200', '192.0.2.10' ] );
	// A short list (8 rows), not the ~3,000 recommended domains the baseline stores.
	await zs.plugin( 'PUT', 'rules/lists/email-domains', { items: [ 'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'yopmail.com', 'trashmail.com', 'sharklasers.com', 'dispostable.com', 'tempmail.dev' ] } );
};

/** Empties the lists the baseline fills (the recommended email domains). */
export const emptyRules = async ( zs: ZsApi ) => {
	await zs.plugin( 'PUT', 'rules/lists/email-domains', { items: [] } );
};

const RULES_TABS: Array< [ string, string ] > = [
	[ 'ips', 'Blocked IPs' ],
	[ 'locations', 'Blocked locations' ],
	[ 'allowlist', 'Allowed IPs' ],
	[ 'email', 'Email domains' ],
	[ 'words', 'Words' ],
	[ 'no-lockout', 'No-lockout addresses' ],
];

/** Opens the block drawer from a button and waits for its form. */
const openBlockDrawer = ( trigger: string ) => async ( page: Page ) => {
	await rulesReady( page );
	await page.locator( trigger ).filter( { visible: true } ).first().click();
	await page.locator( '.zs-drawer.zs-blockform form' ).waitFor();
};

const V6_RULES: Screen[] = [
	...RULES_TABS.flatMap( ( [ tab, name ] ): Screen[] => [
		{ id: `v6-rules-${ tab }-empty`, title: `6.0 Rules › ${ name } (empty)`, area: 'admin', sites: 'both', state: 'empty', theme: 'both', before: emptyRules, path: () => AREA( 'rules', tab ), prepare: rulesReady },
		{
			id: `v6-rules-${ tab }-seeded`,
			title: `6.0 Rules › ${ name }`,
			area: 'admin',
			sites: 'both',
			state: 'seeded',
			theme: 'both',
			before: seedRules,
			path: () => AREA( 'rules', tab ),
			prepare: rulesReady,
			// The tab panel only; Blocked IPs is the area's key screen: 782 and dark there only.
			visual: { mobile: 'ips' === tab, dark: 'ips' === tab, selector: '.zs-areabody' },
		},
	] ),
	{ id: 'v6-rules-block-add', title: '6.0 Rules › Block an IP drawer', area: 'admin', sites: 'both', state: 'seeded', theme: 'both', path: () => AREA( 'rules', 'ips' ), prepare: openBlockDrawer( '.zs-rules .zs-tbar > .zs-btn' ), visual: { selector: '.zs-drawer', mask: [ 'input[type="datetime-local"]' ] } },
	{
		id: 'v6-rules-block-edit',
		title: '6.0 Rules › Edit block drawer',
		area: 'admin',
		sites: 'both',
		state: 'seeded',
		theme: 'both',
		// A block to edit, whatever the state holds.
		before: async ( zs ) => {
			await zs.block( '198.51.100.21', 'E2E: rules sample' );
		},
		path: () => AREA( 'rules', 'ips' ), prepare: openBlockDrawer( '[data-zs-edit]' ),
		visual: { selector: '.zs-drawer', mask: [ 'input[type="datetime-local"]' ] },
	},
	{ id: 'v6-rules-location-add', title: '6.0 Rules › Block a location drawer', area: 'admin', sites: 'both', state: 'empty', theme: 'both', path: () => AREA( 'rules', 'locations' ), prepare: openBlockDrawer( '.zs-rules .zs-tbar > .zs-btn' ), visual: { selector: '.zs-drawer', mask: [ 'input[type="datetime-local"]' ] } },
	{
		id: 'v6-rules-subsite-locked',
		title: '6.0 Rules › Allowed IPs on a subsite, locked by the network',
		area: 'admin',
		sites: 'multisite',
		state: 'empty',
		theme: 'both',
		as: 'siteadmin',
		before: async ( zs ) => {
			await zs.call( 'POST', 'network-setting', { key: 'ip_whitelist', value: '203.0.113.5', locked: true } );
		},
		path: ( t ) => subsiteURL( t, AREA( 'rules', 'allowlist' ) ),
		prepare: rulesReady,
		visual: { selector: '.zs-areabody' },
	},
	{
		id: 'v6-rules-subsite-network-list',
		title: '6.0 Rules › Allowed IPs on a subsite that uses the network list',
		area: 'admin',
		sites: 'multisite',
		state: 'empty',
		theme: 'both',
		as: 'siteadmin',
		before: async ( zs, t ) => {
			// The subsite keeps no settings of its own, so the network's value applies.
			await zs.forSite( t.subsitePath! ).call( 'POST', 'reset', { preset: 'defaults', network: false } );
			await zs.call( 'POST', 'network-setting', { key: 'ip_whitelist', value: '203.0.113.5', locked: false } );
		},
		path: ( t ) => subsiteURL( t, AREA( 'rules', 'allowlist' ) ),
		prepare: rulesReady,
	},
];

/**
 * Adds the dark twin of every screen that follows the theme ("both"): the
 * same screen under prefers-color-scheme dark (the default theme is
 * "auto"). Only key screens keep a dark baseline (`dark: true`; build plan decision 9).
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

const V6_SCREENS: Screen[] = withDarkTwins( [ ...V6_OVERVIEW, ...V6_ACTIVITY, ...V6_RULES, ...V6_PROTECTION, ...V6_SETTINGS, ...V6_HELP, ...V6_NETWORK ] );

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
