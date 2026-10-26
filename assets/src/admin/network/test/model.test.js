/**
 * The network page's model: preloaded paths, the settings cards and the
 * lock switch, the site counts ("(none)", never an empty box), the
 * comparison, template chips, paging, badges and the CSV export.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	PATHS,
	comparisonCell,
	comparisonRows,
	groupOf,
	levelBadge,
	lockText,
	lockToast,
	networkCards,
	networkEntry,
	pageInfo,
	parseImport,
	readLayer,
	sitesState,
	slugify,
	statsCsv,
	templateChips,
	trendBadge,
	validSlug,
	valueText,
} from '../model';
import { byKey } from '../../app/fields/settings-model';
import { comparison, layer, overview, schema, templates } from './fixture';

describe( 'paths', () => {
	it( 'are written exactly as Admin_App::network_preloads() preloads them', () => {
		expect( PATHS.schema ).toBe( '/zero-spam/v1/settings/schema' );
		expect( PATHS.values ).toBe( '/zero-spam/v1/settings?scope=network' );
		expect( PATHS.overview ).toBe( '/zero-spam/v1/network/overview' );
		expect( PATHS.templates ).toBe(
			'/zero-spam/v1/network-settings/templates'
		);
		expect( PATHS.exported ).toBe(
			'/zero-spam/v1/network-settings/export'
		);
		expect( PATHS.notifications ).toBe(
			'/zero-spam/v1/network/notifications'
		);
		expect( PATHS.audit() ).toBe(
			'/zero-spam/v1/network-settings/audit?page=1&per_page=25'
		);
		expect( PATHS.comparison() ).toBe(
			'/zero-spam/v1/network/comparison?page=1&per_page=5'
		);
		expect( PATHS.stats() ).toBe(
			'/zero-spam/v1/network/stats?period=30&page=1&per_page=20&orderby=blocked&order=desc'
		);
		expect( PATHS.blocks() ).toBe(
			'/zero-spam/v1/network/blocks?page=1&per_page=10'
		);
	} );

	it( 'add only the filters that are set', () => {
		expect(
			PATHS.audit(
				{ action: 'lock', key: '', user: 3, after: '2026-10-01' },
				2
			)
		).toBe(
			'/zero-spam/v1/network-settings/audit?page=2&per_page=25&action_type=lock&user_id=3&after=2026-10-01'
		);
		expect( PATHS.stats( 'today', 3, 'name:asc' ) ).toBe(
			'/zero-spam/v1/network/stats?period=today&page=3&per_page=20&orderby=name&order=asc'
		);
		expect( PATHS.sites( 'a&b' ) ).toBe(
			'/zero-spam/v1/network/sites?page=1&per_page=50&search=a%26b'
		);
	} );
} );

describe( 'settings cards', () => {
	const cards = networkCards( schema(), layer() );
	const byCard = Object.fromEntries( cards.map( ( c ) => [ c.key, c ] ) );

	it( 'group Protection by section and Settings and Rules by group, in tab order', () => {
		expect( cards.map( ( c ) => c.key ) ).toEqual( [
			'detection-comments',
			'integrations-wpforms',
			'intelligence-ipinfo',
			'settings-data',
			'settings-general',
			'rules-allowed-ips',
		] );
		expect( byCard[ 'detection-comments' ].title ).toBe( 'Comments' );
		expect( byCard[ 'settings-data' ].title ).toBe( 'Logging & data' );
		expect( byCard[ 'rules-allowed-ips' ].title ).toBe( 'Allowed IPs' );
		expect( groupOf( { area: 'protection', group: 'other' } ) ).toBe(
			'integrations'
		);
	} );

	it( 'leave out help text, buttons and settings that cannot be locked', () => {
		const keys = cards.flatMap( ( c ) => c.entries.map( ( e ) => e.key ) );
		expect( keys ).not.toContain( 'use_recommended_settings' );
		expect( keys ).not.toContain( 'not_lockable' );
	} );

	it( 'edit the network value, or the default when the network sets none', () => {
		const entries = byKey( {
			settings: cards.flatMap( ( c ) => c.entries ),
		} );
		expect( entries.max_logs ).toMatchObject( {
			value: 20000,
			has: true,
			netLocked: false,
			locked: false,
			source: 'default',
		} );
		expect( entries.block_method ).toMatchObject( {
			value: 'php',
			has: false,
		} );
		expect( entries.share_data.netLocked ).toBe( true );
		// The page itself never shows a setting as "locked" (it is where locks are set).
		expect( entries.share_data.locked ).toBe( false );
	} );

	it( 'keep secrets write-only but replaceable here', () => {
		const read = readLayer( layer() );
		const token = networkEntry(
			schema().settings.find( ( e ) => 'ipinfo_access_token' === e.key ),
			read
		);
		expect( token.value ).toEqual( {
			set: true,
			source: 'site',
			hint: '••••a1b2',
		} );
		const none = networkEntry(
			schema().settings.find( ( e ) => 'ipinfo_access_token' === e.key ),
			readLayer( { settings: {}, locked: [] } )
		);
		expect( none.value ).toEqual( { set: false, source: 'site' } );
	} );
} );

describe( 'the Sites column and the lock switch', () => {
	const entries = byKey( {
		settings: networkCards( schema(), layer() ).flatMap(
			( c ) => c.entries
		),
	} );

	it( 'says how many sites use a network value', () => {
		expect( sitesState( entries.max_logs, overview() ) ).toMatchObject( {
			label: '10 of 12 sites use it',
			tone: 'warn',
			custom: 2,
		} );
		expect(
			sitesState( entries.verify_comments, overview() )
		).toMatchObject( {
			label: '12 of 12 sites use it',
			tone: '',
			custom: 0,
		} );
	} );

	it( 'says when it is locked, and when the network sets nothing', () => {
		expect( sitesState( entries.share_data, overview() ) ).toMatchObject( {
			label: 'Locked on every site',
			tone: 'lock',
		} );
		expect( sitesState( entries.block_method, overview() ).label ).toBe(
			'Not set for the network'
		);
	} );

	it( 'shows an empty network value as "(none)", never an empty box', () => {
		expect( valueText( entries.blocked_message, '' ) ).toBe( '(none)' );
		expect( valueText( entries.max_logs, 20000 ) ).toBe(
			`${ ( 20000 ).toLocaleString() } entries`
		);
		expect( valueText( entries.share_data, false ) ).toBe( 'Off' );
		expect( valueText( entries.ipinfo_access_token, { set: true } ) ).toBe(
			'Set'
		);
		expect( valueText( entries.ipinfo_access_token, {} ) ).toBe( '(none)' );
	} );

	it( 'names the lock switch by its setting and says what it did', () => {
		// The name holds the visible word and stays the same in both states (WCAG 2.5.3); aria-pressed says which.
		expect( lockText( entries.share_data, true ) ).toEqual( {
			label: 'Lock',
			state: 'Locked',
			aria: 'Lock “Share data” for every site',
		} );
		expect( lockText( entries.max_logs, false ).label ).toBe( 'Lock' );
		expect( lockText( entries.max_logs, false ).state ).toBe( 'Unlocked' );
		expect( lockToast( entries.max_logs, true ) ).toBe(
			'Maximum logs: locked for every site'
		);
		expect( lockToast( entries.max_logs, false ) ).toBe(
			'Maximum logs: sites can change it'
		);
	} );
} );

describe( 'comparison', () => {
	const entries = byKey( schema() );

	it( 'sorts by name or by the most site changes', () => {
		expect( comparisonRows( comparison() ).map( ( r ) => r.key ) ).toEqual(
			[ 'blocked_message', 'max_logs', 'share_data' ]
		);
		expect(
			comparisonRows( comparison(), 'overrides' ).map( ( r ) => r.key )
		).toEqual( [ 'max_logs', 'blocked_message', 'share_data' ] );
	} );

	it( 'shows each site’s value and where it comes from', () => {
		const [ share, logs, message ] = comparison().settings;
		expect(
			comparisonCell( share, share.sites[ 1 ], entries.share_data )
		).toEqual( {
			text: 'On',
			badge: 'Locked',
			tone: 'lock',
		} );
		expect(
			comparisonCell( logs, logs.sites[ 2 ], entries.max_logs )
		).toEqual( {
			text: `${ ( 25000 ).toLocaleString() } entries`,
			badge: 'Changed',
			tone: 'warn',
		} );
		expect(
			comparisonCell(
				message,
				message.sites[ 1 ],
				entries.blocked_message
			).text
		).toBe( '(none)' );
		expect( comparisonCell( logs, undefined ).badge ).toBe( 'Default' );
	} );
} );

describe( 'templates', () => {
	it( 'show their first settings as chips, then "+N more"', () => {
		const entries = byKey( schema() );
		const { chips, more } = templateChips(
			templates().strict_protection,
			entries
		);
		expect( chips ).toHaveLength( 7 );
		expect( chips[ 0 ] ).toEqual( {
			key: 'verify_comments',
			title: 'Protect comments',
			value: 'On',
		} );
		expect( chips.find( ( c ) => 'blocked_message' === c.key ).value ).toBe(
			'(none)'
		);
		expect( more ).toBe( 1 );
	} );

	it( 'check and make slugs as the route does', () => {
		expect( validSlug( 'clinic-sites' ) ).toBe( true );
		expect( validSlug( 'strict_protection' ) ).toBe( true );
		expect( validSlug( 'Clinic Sites' ) ).toBe( false );
		expect( validSlug( '-x' ) ).toBe( false );
		expect( slugify( ' Clínic Sites! 2 ' ) ).toBe( 'clinic-sites-2' );
	} );
} );

describe( 'paging and badges', () => {
	it( 'pages from the total', () => {
		expect( pageInfo( 230, 2, 25, 25 ) ).toEqual( {
			page: 2,
			pages: 10,
			from: 26,
			to: 50,
		} );
		expect( pageInfo( 0, 3, 25, 0 ) ).toEqual( {
			page: 1,
			pages: 1,
			from: 0,
			to: 0,
		} );
	} );

	it( 'says a trend in words too, not by colour alone', () => {
		expect( trendBadge( 18 ) ).toMatchObject( {
			text: '↑ 18%',
			tone: 'warn',
		} );
		expect( trendBadge( -6 ) ).toMatchObject( {
			text: '↓ 6%',
			tone: 'ok',
		} );
		expect( trendBadge( 0 ).sr ).toBe( 'the same as the previous period' );
		expect( trendBadge( null ).text ).toBe( 'New' );
	} );

	it( 'labels a site Enhanced only when its license is valid', () => {
		expect( levelBadge( 'enhanced' ) ).toEqual( {
			text: 'Enhanced',
			tone: 'enh',
		} );
		expect( levelBadge( 'invalid' ).text ).toBe( 'License not valid' );
		expect( levelBadge( 'unverified' ).text ).toBe( 'Not verified yet' );
		expect( levelBadge( 'basic' ).text ).toBe( 'Basic' );
	} );
} );

describe( 'files', () => {
	it( 'export statistics as CSV that spreadsheets cannot run', () => {
		const csv = statsCsv( [
			{
				name: '=HYPERLINK("x")',
				url: 'a.example',
				blocked: 3,
				unique_ips: 2,
				top_type_label: 'Comment, reply',
				country_name: 'Germany',
				trend: null,
				level: 'enhanced',
			},
		] );
		const [ head, row ] = csv.split( '\r\n' );
		expect( head ).toContain( 'Spam blocked' );
		expect( row ).toBe(
			'"\'=HYPERLINK(""x"")",a.example,3,2,"Comment, reply",Germany,,Enhanced'
		);
	} );

	it( 'read an import file in either export shape', () => {
		expect(
			parseImport( '{"max_logs":{"value":5,"locked":true}}' )
		).toEqual( {
			max_logs: { value: 5, locked: true },
		} );
		expect( parseImport( '{"settings":{"max_logs":5}}' ) ).toEqual( {
			max_logs: 5,
		} );
		expect( () => parseImport( '[]' ) ).toThrow();
		expect( () => parseImport( '{}' ) ).toThrow();
		expect( () => parseImport( 'nope' ) ).toThrow();
	} );
} );
