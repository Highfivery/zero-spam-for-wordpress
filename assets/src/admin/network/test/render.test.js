/**
 * The network page renders every tab from the preloaded answers only (no
 * request on load), with at most one primary action per screen, the lock
 * switch on every setting, empty network values as "(none)", and the
 * stats' Enhanced label only for valid licenses.
 */

/**
 * External dependencies
 */
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

/**
 * Internal dependencies
 */
import * as fixture from './fixture';

let render;

/**
 * Preloaded answers, as Admin_App::network_preloads() prints them.
 *
 * @return {Object} zeroSpamPreload.
 */
const preload = () => {
	const body = ( data, headers = {} ) => ( { body: data, headers } );
	return {
		'/zero-spam/v1/ui/prefs': body( { theme: 'auto' } ),
		'/zero-spam/v1/settings/schema': body( fixture.schema() ),
		'/zero-spam/v1/settings?scope=network': body( fixture.layer() ),
		'/zero-spam/v1/network/overview': body( fixture.overview() ),
		'/zero-spam/v1/network-settings/templates': body( fixture.templates() ),
		'/zero-spam/v1/network-settings/audit?page=1&per_page=25': body(
			fixture.audit(),
			{ 'X-WP-Total': '2', 'X-WP-TotalPages': '1' }
		),
		'/zero-spam/v1/network/comparison?page=1&per_page=5': body(
			fixture.comparison(),
			{ 'X-WP-Total': '12', 'X-WP-TotalPages': '3' }
		),
		'/zero-spam/v1/network-settings/export': body( {
			max_logs: { value: 20000, locked: false },
		} ),
		'/zero-spam/v1/network/notifications': body( {
			weekly: true,
			changes: false,
		} ),
		'/zero-spam/v1/network/stats?period=30&page=1&per_page=20&orderby=blocked&order=desc':
			body( fixture.stats(), {
				'X-WP-Total': '2',
				'X-WP-TotalPages': '1',
			} ),
		'/zero-spam/v1/network/blocks?page=1&per_page=10': body(
			fixture.blocks(),
			{ 'X-WP-Total': '1', 'X-WP-TotalPages': '1' }
		),
	};
};

beforeAll( async () => {
	const win = {
		location: {
			search: '',
			href: 'http://example.org/wp-admin/network/admin.php?page=zero-spam-network',
		},
		zeroSpamAdmin: {
			area: 'network',
			newsEnabled: false,
			network: { sites: 12, name: 'Lakeside' },
			urls: {
				network:
					'http://example.org/wp-admin/network/admin.php?page=zero-spam-network',
			},
		},
		matchMedia: () => ( {
			matches: true,
			addEventListener() {},
			removeEventListener() {},
		} ),
		addEventListener() {},
		removeEventListener() {},
		requestAnimationFrame() {},
		setTimeout() {},
		history: { state: null, replaceState() {}, pushState() {} },
	};
	vi.stubGlobal( 'window', win );

	const [ { AppProvider }, { FeedbackProvider }, { Network } ] =
		await Promise.all( [
			import( '../../app/context' ),
			import( '../../app/primitives/feedback' ),
			import( '../network' ),
		] );

	render = ( tab = 'overview', search = '' ) => {
		win.location.search = search;
		win.zeroSpamPreload = preload();
		return renderToStaticMarkup(
			<FeedbackProvider>
				<AppProvider root={ { dataset: {} } }>
					<Network tab={ tab } setTab={ () => {} } />
				</AppProvider>
			</FeedbackProvider>
		);
	};
} );

afterAll( () => {
	vi.unstubAllGlobals();
} );

/**
 * Primary buttons in markup (zs-btn without a variant class).
 *
 * @param {string} html Markup.
 * @return {string[]} Their opening tags.
 */
const primaries = ( html ) =>
	(
		html.match( /<(button|a)\b[^>]*class="zs-btn(?: [^"]*)?"[^>]*>/g ) || []
	).filter( ( tag ) => ! /zs-btn-(secondary|ghost|darksec)/.test( tag ) );

const TABS = [
	'overview',
	'settings',
	'templates',
	'audit',
	'comparison',
	'io',
	'notifications',
	'stats',
];

describe( 'the network page', () => {
	it.each( TABS )(
		'renders %s from the preloaded answers, with at most one primary action',
		( tab ) => {
			const html = render( tab );
			expect( html ).toContain(
				'Network.<span>One wall, 12 sites.</span>'
			);
			expect( html ).toContain(
				`id="zs-nt-${ tab }" type="button" role="tab" aria-selected="true"`
			);
			// No skeleton: every answer the tab reads on load was preloaded.
			expect( html ).not.toContain( 'zs-pcard-skel' );
			expect( primaries( html ).length ).toBeLessThanOrEqual( 1 );
		}
	);

	it( 'shows the overview numbers and the site changes', () => {
		const html = render( 'overview' );
		expect( html ).toContain(
			'There are 3 setting changes across your network.'
		);
		expect( html ).toContain( 'Apply to all sites' );
	} );

	it( 'gives every setting a lock switch and says how many sites use it', () => {
		const html = render( 'settings', '?group=settings' );
		// The group filter shows Settings cards only; the first one is open.
		expect( html ).toContain( 'Logging &amp; data' );
		expect( html ).not.toContain( 'Protect comments' );
		expect( html ).toMatch(
			/class="zs-lockbtn" data-zs-lock="share_data" aria-pressed="true" aria-label="Lock “Share data” for every site"/
		);
		expect( html ).toMatch(
			/data-zs-lock="max_logs" aria-pressed="false"/
		);
		expect( html ).toContain( 'Locked on every site' );
		expect( html ).toContain( '10 of 12 sites use it' );
		expect( html ).toContain( '2 sites changed it' );
	} );

	it( 'shows "(none)" for an empty network value, never an empty box', () => {
		const html = render( 'settings', '?group=rules' );
		expect( html ).toContain( 'Network value: (none).' );
		expect( html ).toContain( '12 of 12 sites use it' );
		const unset = render( 'settings', '?group=detection' );
		expect( unset ).toContain( 'Not set for the network' );
	} );

	it( 'keeps network keys write-only', () => {
		const html = render( 'settings', '?group=intelligence' );
		expect( html ).toContain( '••••a1b2' );
		expect( html ).not.toContain( 'type="password" value="' );
		expect( html ).toContain( 'Replace' );
	} );

	it( 'lists templates with their chips and a copy for built-ins', () => {
		const html = render( 'templates' );
		expect( html ).toContain( 'Strict Protection' );
		expect( html ).toContain( 'Built-in' );
		expect( html ).toContain( 'Edit a copy' );
		expect( html ).toContain( '+1 more' );
		expect( html ).toMatch( /Delete<\/button>/ );
	} );

	it( 'writes audit entries in plain language', () => {
		const html = render( 'audit' );
		expect( html ).toContain( '<code>5000</code>' );
		expect( html ).toContain( 'Show only changes by bmarshall' );
		expect( html ).toContain( '2 entries' );
	} );

	it( 'compares a page of sites', () => {
		const html = render( 'comparison' );
		expect( html ).toContain( 'Showing sites 1–2 of 12.' );
		expect( html ).toContain( 'Riverside Ortho' );
		expect( html ).toContain( 'Changed' );
	} );

	it( 'labels only licensed sites Enhanced and offers Block network-wide', () => {
		const html = render( 'stats' );
		expect( html ).toContain( '>Enhanced<' );
		expect( html ).toContain( '>Basic<' );
		expect( html ).toContain( 'data-zs-netblock="203.0.113.9"' );
		expect( html ).toContain( 'Blocked on all sites' );
		expect( html ).not.toMatch( /<iframe|<canvas|maps\.google/ );
	} );

	it( 'saves the notification switches', () => {
		const html = render( 'notifications' );
		expect( html ).toMatch(
			/role="switch" id="zs-nn-weekly-sw" class="zs-switch" aria-checked="true"/
		);
		expect( html ).toMatch(
			/role="switch" id="zs-nn-changes-sw" class="zs-switch" aria-checked="false"/
		);
	} );
} );
