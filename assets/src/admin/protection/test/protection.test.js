/**
 * The Protection page renders from preloaded answers only (no request on
 * load): the tabs, the cards of each tab, one primary action per screen,
 * and the Enhanced card at both levels.
 */

/**
 * External dependencies
 */
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

/**
 * Internal dependencies
 */
import { basicLicense, enhancedLicense, modules, schema } from './fixture';

let render;

/**
 * Preloaded answers, as Admin_App prints them.
 *
 * @param {Object} license license route answer.
 * @return {Object} zeroSpamPreload.
 */
const preload = ( license ) => ( {
	'/zero-spam/v1/settings/schema': { body: schema(), headers: {} },
	'/zero-spam/v1/protection/modules': { body: modules(), headers: {} },
	'/zero-spam/v1/license': { body: license, headers: {} },
	'/zero-spam/v1/ui/prefs': { body: { theme: 'auto' }, headers: {} },
	'/zero-spam/v1/stats/summary?range=1': { body: { total: 0 }, headers: {} },
} );

beforeAll( async () => {
	const win = {
		location: {
			search: '',
			href: 'http://example.org/wp-admin/admin.php?page=zero-spam-protection',
		},
		zeroSpamAdmin: {
			area: 'protection',
			newsEnabled: false,
			urls: {
				overview:
					'http://example.org/wp-admin/admin.php?page=zero-spam',
				protection:
					'http://example.org/wp-admin/admin.php?page=zero-spam-protection',
			},
		},
		matchMedia: () => ( {
			matches: false,
			addEventListener() {},
			removeEventListener() {},
		} ),
		addEventListener() {},
		removeEventListener() {},
		requestAnimationFrame() {},
		setTimeout() {},
	};
	vi.stubGlobal( 'window', win );

	const [ { AppProvider }, { FeedbackProvider }, { Protection } ] =
		await Promise.all( [
			import( '../../app/context' ),
			import( '../../app/primitives/feedback' ),
			import( '../protection' ),
		] );

	render = ( { tab = '', license = basicLicense() } = {} ) => {
		win.location.search = tab ? `?tab=${ tab }` : '';
		win.zeroSpamPreload = preload( license );
		return renderToStaticMarkup(
			<FeedbackProvider>
				<AppProvider root={ { dataset: {} } }>
					<Protection />
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

describe( 'Protection', () => {
	it( 'opens on Detection with its cards, from the preloaded answers', () => {
		const html = render();

		expect( html ).toContain(
			'Protection.<span>Every door, checked.</span>'
		);
		expect( html ).toContain( 'role="tablist"' );
		expect( html ).toMatch(
			/id="zs-pt-detection"[^>]*aria-selected="true"[^>]*tabindex="0"/i
		);
		expect( html ).toMatch( /id="zs-pt-integrations"[^>]*tabindex="-1"/i );
		expect( html ).toContain( 'role="tabpanel"' );
		for ( const title of [
			'Comments',
			'User login',
			'David Walsh browser check',
			'Honeypot',
			'Security',
		] ) {
			expect( html ).toContain( `>${ title }</h2>` );
		}
		expect( html ).toContain( 'Disable XML-RPC' );
		expect( html ).toContain( 'Hide the WordPress Version' );
		expect( html ).toContain( 'Fine-tune' );
		expect( html ).toContain( 'aria-label="Supported signals"' );
		expect( html ).toContain( 'Regenerate honeypot ID' );
		expect( html ).not.toContain( 'Loading' );
	} );

	it( 'has one primary action: Test my protection', () => {
		const html = render();
		const list = primaries( html );
		expect( list ).toHaveLength( 1 );
		expect( list[ 0 ] ).toContain( 'zs-test-btn' );
		expect( html ).toContain( 'Test my protection' );
	} );

	it( 'lists active integrations, a third-party section, and inactive ones', () => {
		const html = render( { tab: 'integrations' } );
		expect( html ).toContain( '>Widget Forms</h2>' );
		expect( html ).toContain( 'Protect widget forms' );
		expect( html ).toContain(
			'More integrations, shown when their plugin is active'
		);
		expect( html ).toContain( 'zs-pcard-na' );
		expect( html ).toContain( 'Install and activate Contact Form 7' );
		expect( html ).toContain( '2 active, 1 not active.' );
	} );

	it( 'shows Basic sites what Enhanced adds, with Compare levels as the one primary', () => {
		const html = render( { tab: 'intelligence' } );
		expect( html ).toContain( 'What Enhanced adds here' );
		expect( html ).toContain( 'I already have a license key' );
		expect( html ).toContain( 'No license key' );
		const list = primaries( html );
		expect( list ).toHaveLength( 1 );
		expect( html ).toMatch( />Compare levels<\/button>/ );
		expect( html ).not.toMatch( /\$|€|per month|\/mo\b/ );
		// The switch shows only once a key is saved.
		expect( html ).not.toContain( 'id="zs-f-zerospam"' );
		expect( html ).toContain( '••••a1b2' );
	} );

	it( 'shows Enhanced sites the license panel, without prices or quotas', () => {
		const html = render( {
			tab: 'intelligence',
			license: enhancedLicense(),
		} );
		expect( html ).toContain( 'License verified' );
		expect( html ).toContain( 'Enhanced member' );
		expect( html ).toContain( '••••7Q4K' );
		expect( html ).toContain( 'Business' );
		expect( html ).toContain( 'Network connected' );
		expect( html ).toContain( 'Early access' );
		expect( html ).toContain( 'Remove license key' );
		expect( html ).toContain( '>Advanced</summary>' );
		expect( html ).not.toContain( 'What Enhanced adds here' );
		expect( html ).not.toMatch( /18,800|31,200|50,000|Queries/ );
		expect( html ).toContain( 'id="zs-f-zerospam"' );
	} );
} );
