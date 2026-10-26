/**
 * The Settings tabs and cards: every setting of the area is on exactly one
 * card, links find their card, and unsaved changes are tracked across tabs.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	SHOWN_ELSEWHERE,
	TABS,
	buildCards,
	cardStatus,
	locate,
	lockCount,
	tabCounts,
} from '../cards';
import {
	byKey,
	dirtyPayload,
	holdsValue,
	isChange,
} from '../../app/fields/settings-model';
import { schema } from './fixture';

describe( 'buildCards', () => {
	const cards = buildCards( schema() );

	it( 'has cards on each of the six tabs, in the prototype order', () => {
		expect( TABS ).toEqual( [
			'general',
			'data',
			'api',
			'advanced',
			'debug',
			'io',
		] );
		TABS.forEach( ( tab ) =>
			expect( cards.some( ( c ) => c.tab === tab ) ).toBe( true )
		);
		expect(
			cards.filter( ( c ) => 'general' === c.tab ).map( ( c ) => c.key )
		).toEqual( [ 'quick', 'blocking', 'widget', 'news', 'display' ] );
		expect(
			cards.filter( ( c ) => 'advanced' === c.tab ).map( ( c ) => c.key )
		).toEqual( [ 'proxies', 'rescue', 'nolockout' ] );
	} );

	it( 'shows every setting of the area exactly once, and none of other areas', () => {
		const seen = {};
		cards.forEach( ( card ) =>
			card.entries.forEach( ( e ) => {
				seen[ e.key ] = ( seen[ e.key ] || 0 ) + 1;
			} )
		);
		schema().settings.forEach( ( e ) => {
			if ( 'settings' !== e.area || SHOWN_ELSEWHERE.includes( e.key ) ) {
				expect( seen[ e.key ], e.key ).toBeUndefined();
			} else {
				expect( seen[ e.key ], e.key ).toBe( 1 );
			}
		} );
	} );

	it( 'puts the seven settings that now take effect on real controls', () => {
		const api = cards.find( ( c ) => 'apimon' === c.key );
		const rules = cards.find( ( c ) => 'alertrules' === c.key );
		expect( api.entries.map( ( e ) => e.key ) ).toEqual(
			expect.arrayContaining( [
				'quota_reset_date',
				'delete_data_on_uninstall',
			] )
		);
		expect(
			rules.entries
				.filter( ( e ) => e.key.startsWith( 'threshold_' ) )
				.map( ( e ) => e.type )
		).toEqual( [ 'number', 'number', 'number', 'number', 'number' ] );
	} );

	it( "gives an extension's setting a card on its group's tab", () => {
		const more = cards.find( ( c ) => 'more-api' === c.key );
		expect( more.tab ).toBe( 'api' );
		expect( more.keys ).toEqual( [ 'acme_mode' ] );
		// After the tab's own cards.
		const api = cards.filter( ( c ) => 'api' === c.tab );
		expect( api[ api.length - 1 ] ).toBe( more );
	} );

	it( 'leaves out a card whose settings are all missing', () => {
		const data = schema();
		data.settings = data.settings.filter( ( e ) => 'debug' !== e.module );
		expect(
			buildCards( data ).find( ( c ) => 'debug' === c.key )
		).toBeUndefined();
		// Cards of the area's own content stay.
		expect( buildCards( undefined ).map( ( c ) => c.key ) ).toEqual( [
			'display',
			'rescue',
			'nolockout',
			'system',
			'io',
			'errlog',
		] );
	} );
} );

describe( 'locate', () => {
	const cards = buildCards( schema() );

	it( 'finds a card by its key and a setting by its key', () => {
		expect( locate( cards, 'proxies' ) ).toEqual( {
			tab: 'advanced',
			card: 'proxies',
			key: '',
		} );
		expect( locate( cards, 'max_logs' ) ).toEqual( {
			tab: 'data',
			card: 'logging',
			key: 'max_logs',
		} );
		expect( locate( cards, 'errlog' ).tab ).toBe( 'io' );
	} );

	it( 'returns null for nothing or an unknown key', () => {
		expect( locate( cards, '' ) ).toBeNull();
		expect( locate( cards, 'nope' ) ).toBeNull();
	} );
} );

describe( 'unsaved changes across tabs', () => {
	const data = schema();
	const cards = buildCards( data );
	const entries = byKey( data );

	it( 'keeps drafts of every tab in one payload and counts them per tab', () => {
		const draft = {
			show_news: false,
			max_logs: '500',
			threshold_slow_response: '7000',
			debug_ip: '192.0.2.10',
		};
		const payload = dirtyPayload( entries, draft );
		expect( payload ).toEqual( {
			show_news: false,
			max_logs: 500,
			threshold_slow_response: 7000,
			debug_ip: '192.0.2.10',
		} );
		expect( tabCounts( cards, payload ) ).toEqual( {
			general: 1,
			data: 1,
			api: 1,
			advanced: 0,
			debug: 1,
			io: 0,
		} );
	} );

	it( 'drops a draft that is back to the saved value', () => {
		expect( isChange( entries.max_logs, '10000' ) ).toBe( false );
		expect(
			dirtyPayload( entries, { max_logs: '10000', debug: false } )
		).toEqual( {} );
	} );

	it( 'never sends a locked setting', () => {
		const locked = byKey(
			schema( { share_data: { locked: true, source: 'locked' } } )
		);
		expect( dirtyPayload( locked, { share_data: false } ) ).toEqual( {} );
	} );
} );

describe( 'card badges', () => {
	const data = schema();
	const cards = buildCards( data );
	const entries = byKey( data );
	const valueOf = ( key ) => entries[ key ]?.value;

	it( "follows the card's main switch", () => {
		const logging = cards.find( ( c ) => 'logging' === c.key );
		const apimon = cards.find( ( c ) => 'apimon' === c.key );
		expect( cardStatus( logging, valueOf, entries ) ).toEqual( {
			label: 'On',
			tone: 'ok',
		} );
		expect( cardStatus( apimon, valueOf, entries ).label ).toBe( 'Off' );
		expect(
			cardStatus(
				cards.find( ( c ) => 'quick' === c.key ),
				valueOf,
				entries
			)
		).toBeNull();
	} );

	it( 'counts the locked settings of a card', () => {
		const locked = buildCards(
			schema( {
				log_blocked_ips: { locked: true },
				max_logs: { locked: true },
			} )
		).find( ( c ) => 'logging' === c.key );
		expect( lockCount( locked ) ).toEqual( { locked: 2, total: 2 } );
		const quick = cards.find( ( c ) => 'quick' === c.key );
		expect( quick.entries.filter( holdsValue ) ).toHaveLength( 0 );
		expect( lockCount( quick ) ).toEqual( { locked: 0, total: 0 } );
	} );
} );
