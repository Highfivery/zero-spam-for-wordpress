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
 */

import type { Locator, Page } from '@playwright/test';

import type { ZsApi } from './e2e-api';
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
	visual?: boolean | { mobile?: boolean; mask?: string[]; selector?: string };
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
export const WIDGET_MASKS = [ 'canvas', '.zerospam-stat-value' ];

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
	{ id: 'wp-dashboard-widget', title: 'WordPress Dashboard › Zero Spam Overview widget', area: 'admin', sites: 'both', state: 'seeded', path: () => 'wp-admin/index.php', include: [ '#zerospam_unified_widget' ], visual: { mobile: true, mask: WIDGET_MASKS, selector: '#zerospam_unified_widget' } },
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
	{ id: 'network-dashboard-widget', title: 'Network Dashboard › Zero Spam Network Overview widget', area: 'network', sites: 'multisite', state: 'seeded', path: () => 'wp-admin/network/index.php', include: [ '#zerospam_unified_widget' ], visual: { mask: WIDGET_MASKS, selector: '#zerospam_unified_widget' } },
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
	{
		id: 'front-blocked-403',
		title: 'Blocked visitor (403 page)',
		area: 'front',
		sites: 'both',
		state: 'empty',
		as: 'visitor',
		status: 403,
		before: async ( zs, _t, ip ) => {
			await zs.block( ip, 'Blocked page screen' );
		},
		path: () => '',
		include: [ 'body' ],
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
	},
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
