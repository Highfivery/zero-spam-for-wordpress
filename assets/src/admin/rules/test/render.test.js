/**
 * The Rules screens rendered to markup (react-dom/server, with the page's
 * preloaded answers): the list editors' network states (locked: read-only
 * with the "Locked by network" badge; the network's list on a subsite:
 * read-only until "Customize for this site"; the site's own list:
 * editable), unsaved entries, the block lists' notes and cards, the
 * no-lockout list and the six tabs.
 */

/**
 * External dependencies
 */
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { ListEditor } from '../list-editor';
import { Blocks } from '../blocks';
import { NoLockout } from '../no-lockout';
import { Rules } from '../rules';
import { blocksPath, listPath, NO_LOCKOUT_PATH } from '../query';

/**
 * Renders with preloaded answers (path => body, or { body, headers }).
 *
 * @param {Object} element Element.
 * @param {Object} answers Answers by path.
 * @param {string} search  location.search.
 * @return {string} Markup.
 */
function render( element, answers = {}, search = '' ) {
	const store = {};
	Object.entries( answers ).forEach( ( [ path, answer ] ) => {
		store[ path ] =
			answer && answer.body ? answer : { body: answer, headers: {} };
	} );
	globalThis.window = {
		zeroSpamPreload: store,
		location: {
			search,
			href: `https://example.com/wp-admin/admin.php${ search }`,
		},
	};
	return renderToStaticMarkup( element );
}

afterEach( () => {
	delete globalThis.window;
} );

const noop = () => {};
const draft = { add: [], remove: [] };
const list = ( extra = {} ) => ( {
	list: 'allowed-ips',
	key: 'ip_whitelist',
	items: [ '203.0.113.5', '2001:db8::1' ],
	total: 2,
	locked: false,
	source: 'site',
	...extra,
} );
const editor = ( props = {} ) => (
	<ListEditor
		list="allowed-ips"
		draft={ draft }
		setDraft={ noop }
		onCount={ noop }
		onReload={ noop }
		{ ...props }
	/>
);

describe( 'list editors', () => {
	it( 'the site’s own list can be added to and edited', () => {
		const html = render( editor(), {
			[ listPath( 'allowed-ips' ) ]: list(),
		} );
		expect( html ).toContain( 'IP Allowlist' );
		expect( html ).toContain( 'Add IP addresses' );
		expect( html ).toContain( 'aria-label="Remove 203.0.113.5"' );
		expect( html ).not.toContain( 'Locked by network' );
	} );

	it( 'a locked list is read-only with the "Locked by network" badge', () => {
		const html = render( editor(), {
			[ listPath( 'allowed-ips' ) ]: list( {
				locked: true,
				source: 'locked',
			} ),
		} );
		expect( html ).toContain( 'Locked by network' );
		expect( html ).toContain(
			'Locked by the network administrator. This list cannot be changed on this site.'
		);
		expect( html ).toContain( '203.0.113.5' );
		expect( html ).not.toContain( 'zs-addrow' );
		expect( html ).not.toContain( 'aria-label="Remove' );
		expect( html ).not.toContain( 'Customize for this site' );
	} );

	it( 'the network’s list on a subsite is read-only until customized', () => {
		const html = render( editor(), {
			[ listPath( 'allowed-ips' ) ]: list( { source: 'network' } ),
		} );
		expect( html ).toContain( 'Customize for this site' );
		expect( html ).toContain( 'uses the network' );
		expect( html ).not.toContain( 'zs-addrow' );
		expect( html ).not.toContain( 'aria-label="Remove' );
	} );

	it( 'shows unsaved entries and removals', () => {
		const html = render(
			editor( {
				draft: { add: [ '192.0.2.44' ], remove: [ '203.0.113.5' ] },
			} ),
			{ [ listPath( 'allowed-ips' ) ]: list() }
		);
		expect( html ).toContain( 'New, not saved' );
		expect( html ).toContain( 'Removed, not saved' );
		expect( html ).toContain( 'aria-label="Keep 203.0.113.5"' );
		expect( html ).toContain( '2 after saving' );
	} );

	it( 'an empty list says what to do', () => {
		const html = render( editor( { list: 'email-domains' } ), {
			[ listPath( 'email-domains' ) ]: list( {
				list: 'email-domains',
				items: [],
				total: 0,
			} ),
		} );
		expect( html ).toContain( 'No blocked email domains.' );
		expect( html ).not.toContain( 'Clear search' );
	} );
} );

describe( 'block lists', () => {
	const blocks = [
		{
			id: 3,
			kind: 'ip',
			key_type: 'ip',
			key: '203.0.113.9',
			ip: '203.0.113.9',
			label: '203.0.113.9',
			type: 'temporary',
			start: '2026-10-20T10:00:00Z',
			end: '2026-10-30T10:00:00Z',
			reason: 'Repeated comment spam',
			date_added: '2026-10-20T10:00:00',
			active: true,
		},
		{
			id: 4,
			kind: 'ip',
			key_type: 'ip',
			key: '2001:db8::4f',
			ip: '2001:db8::4f',
			label: '2001:db8::4f',
			type: 'permanent',
			start: '2026-10-01T10:00:00Z',
			end: null,
			reason: null,
			date_added: '2026-10-01T10:00:00',
			active: true,
		},
	];

	it( 'lists blocks as cards on narrow screens with Edit block and Remove', () => {
		const html = render( <Blocks kind="ip" onCount={ noop } />, {
			[ blocksPath( { kind: 'ip' } ) ]: {
				body: blocks,
				headers: { 'X-WP-Total': '2', 'X-WP-TotalPages': '1' },
			},
		} );
		expect( html ).toContain( 'aria-label="Edit block for 203.0.113.9"' );
		expect( html ).toContain(
			'aria-label="Remove block for 2001:db8::4f"'
		);
		expect( html ).toContain( 'Permanent' );
		expect( html ).toContain( 'No reason given' );
		expect( html ).toContain( '2 blocks' );
		expect( html ).toContain( 'Block an IP' );
		expect( html ).toContain( 'aria-disabled="true"' );
	} );

	it( 'an empty list says what to do', () => {
		const html = render( <Blocks kind="ip" onCount={ noop } />, {
			[ blocksPath( { kind: 'ip' } ) ]: {
				body: [],
				headers: { 'X-WP-Total': '0' },
			},
		} );
		expect( html ).toContain( 'No blocked IPs yet.' );
	} );

	it( 'notes .htaccess blocking and whether location lookups work', () => {
		const schema = ( method, geo ) => ( {
			settings: [
				{ key: 'block_method', value: method },
				{ key: 'ipinfo_access_token', value: { set: geo } },
				{ key: 'ipstack_api', value: { set: false } },
			],
		} );
		const ip = render(
			<Blocks
				kind="ip"
				schema={ schema( 'htaccess_modern', false ) }
				onCount={ noop }
			/>
		);
		expect( ip ).toContain( '.htaccess blocking is on' );
		expect( ip ).toContain( 'Refresh .htaccess' );
		expect(
			render(
				<Blocks
					kind="ip"
					schema={ schema( 'php', false ) }
					onCount={ noop }
				/>
			)
		).not.toContain( '.htaccess' );

		const off = render(
			<Blocks
				kind="location"
				schema={ schema( 'php', false ) }
				onCount={ noop }
			/>
		);
		expect( off ).toContain( 'Blocking locations is currently off.' );
		expect( off ).toContain( 'Block a location' );
		const on = render(
			<Blocks
				kind="location"
				schema={ schema( 'php', true ) }
				onCount={ noop }
			/>
		);
		expect( on ).toContain( 'Location lookups use IPinfo.' );
	} );
} );

describe( 'no-lockout addresses', () => {
	it( 'lists them with Remove, marking the current address', () => {
		const html = render( <NoLockout onCount={ noop } />, {
			[ NO_LOCKOUT_PATH ]: {
				items: [
					{ ip: '198.51.100.200', current: true },
					{ ip: '192.0.2.10', current: false },
				],
				max: 10,
			},
		} );
		expect( html ).toContain( 'Your address now' );
		expect( html ).toContain(
			'aria-label="Remove 192.0.2.10 from the administrator addresses that can never be locked out"'
		);
		expect( html ).toContain( 'The last 10 are kept.' );
	} );

	it( 'says when there are none', () => {
		const html = render( <NoLockout onCount={ noop } />, {
			[ NO_LOCKOUT_PATH ]: { items: [], max: 10 },
		} );
		expect( html ).toContain( 'No addresses saved.' );
	} );
} );

describe( 'the Rules area', () => {
	it( 'has six tabs, the one in the address selected', () => {
		const html = render( <Rules />, {}, '?tab=email' );
		const tabs = html.match( /role="tab"/g ) || [];
		expect( tabs ).toHaveLength( 6 );
		expect( html ).toMatch(
			/id="zs-rt-email"[^>]*aria-selected="true"[^>]*tabindex="0"|id="zs-rt-email"[^>]*tabindex="0"[^>]*aria-selected="true"|aria-selected="true"[^>]*id="zs-rt-email"/
		);
		expect( html ).toContain( 'aria-labelledby="zs-rt-email"' );
		expect( html ).toContain( 'Who gets in, who doesn’t.' );
	} );

	it( 'an unknown tab falls back to Blocked IPs', () => {
		const html = render( <Rules />, {}, '?tab=nope' );
		expect( html ).toContain( 'aria-labelledby="zs-rt-ips"' );
	} );
} );
