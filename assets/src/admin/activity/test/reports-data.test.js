/**
 * The Reports tab's mappers: one period for every card.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	barWidth,
	countryBars,
	countryNames,
	enhancedColumns,
	hourData,
	hourLabel,
	keyStep,
	reportPaths,
	seriesTotal,
	topIps,
	typeBars,
} from '../reports-data';

const series = {
	types: [
		{ type: 'login', label: 'Login', count: 4 },
		{ type: 'comment', label: 'Comment', count: 9 },
		{ type: 'gone', label: 'Gone', count: 0 },
	],
	days: [
		{ date: '2026-10-23', total: 6, network_only: 2 },
		{ date: '2026-10-24', total: 0, network_only: 0 },
		{ date: '2026-10-25', total: 7, network_only: 9 },
	],
	hours: [ 1, 0, 0, 5, ...Array( 19 ).fill( 0 ), 5 ],
};

const facets = {
	total: 13,
	country: [
		{ value: 'de', label: 'Germany', count: 8 },
		{ value: 'US', label: 'United States', count: 3 },
		{ value: 'BR', label: 'Brazil', count: 0 },
	],
	ip: [
		{ value: '198.51.100.9', country: 'US', count: 2, blocked: false },
		{ value: '203.0.113.5', country: 'DE', count: 7, blocked: true },
		{ value: '192.0.2.1', country: null, count: 2, blocked: false },
	],
};

describe( 'reportPaths', () => {
	it( 'uses the preloaded 30-day paths', () => {
		expect( reportPaths( 30 ) ).toEqual( {
			series: '/zero-spam/v1/stats/timeseries',
			facets: '/zero-spam/v1/activity?facets=1&detections_only=1&days=30',
		} );
	} );

	it( 'asks for the shorter periods', () => {
		expect( reportPaths( 7 ).series ).toBe(
			'/zero-spam/v1/stats/timeseries?range=7'
		);
		expect( reportPaths( 14 ).facets ).toContain( 'days=14' );
		expect( reportPaths( 99 ) ).toEqual( reportPaths( 30 ) );
	} );
} );

describe( 'cards', () => {
	it( 'counts the period', () => {
		expect( seriesTotal( series ) ).toBe( 13 );
		expect( seriesTotal( undefined ) ).toBe( 0 );
	} );

	it( 'By type: most first, empty types left out', () => {
		expect( typeBars( series ) ).toEqual( {
			rows: [
				{ key: 'comment', label: 'Comment', count: 9 },
				{ key: 'login', label: 'Login', count: 4 },
			],
			max: 9,
			total: 13,
		} );
	} );

	it( 'By location: the top countries, upper-case codes', () => {
		const bars = countryBars( facets, 1 );
		expect( bars.rows ).toEqual( [
			{ code: 'DE', name: 'Germany', count: 8 },
		] );
		expect( bars.more ).toBe( 1 );
		expect( countryNames( facets ).DE ).toBe( 'Germany' );
	} );

	it( 'Most detections: sorted by count, address or country', () => {
		const names = countryNames( facets );
		expect( topIps( facets, 'count', names ).map( ( r ) => r.ip ) ).toEqual(
			[ '203.0.113.5', '192.0.2.1', '198.51.100.9' ]
		);
		expect( topIps( facets, 'ip', names ).map( ( r ) => r.ip ) ).toEqual( [
			'192.0.2.1',
			'198.51.100.9',
			'203.0.113.5',
		] );
		expect(
			topIps( facets, 'country', names ).map( ( r ) => r.country )
		).toEqual( [ 'DE', 'US', null ] );
		expect( topIps( facets )[ 0 ].blocked ).toBe( true );
	} );

	it( 'By the hour: 24 columns and the busiest hours', () => {
		const data = hourData( series, 2 );
		expect( data.columns ).toHaveLength( 24 );
		expect( data.total ).toBe( 11 );
		expect( data.busiest ).toEqual( [
			{ hour: 3, count: 5 },
			{ hour: 23, count: 5 },
		] );
		expect( hourData( undefined ).busiest ).toEqual( [] );
	} );

	it( 'Enhanced vs Basic: never more network than the day had', () => {
		const ev = enhancedColumns( series );
		expect( ev.columns[ 0 ] ).toEqual( {
			key: '2026-10-23',
			basic: 4,
			network: 2,
			value: 6,
		} );
		expect( ev.columns[ 2 ] ).toEqual( {
			key: '2026-10-25',
			basic: 0,
			network: 7,
			value: 7,
		} );
		expect( ev.basic ).toBe( 4 );
		expect( ev.network ).toBe( 9 );
		expect( ev.max ).toBe( 7 );
	} );

	it( 'names hours in the site language', () => {
		expect( hourLabel( 0, 'en-US' ) ).toBe( '12 AM' );
		expect( hourLabel( 15, 'en-GB' ) ).toBe( '15' );
	} );

	it( 'sizes bars against the longest', () => {
		expect( barWidth( 5, 10 ) ).toBe( '50.0%' );
		expect( barWidth( 20, 10 ) ).toBe( '100.0%' );
		expect( barWidth( 0, 0 ) ).toBe( '0.0%' );
	} );
} );

describe( 'chart keyboard reading', () => {
	it( 'starts at the last column and moves with the arrows, Home and End', () => {
		expect( keyStep( 'ArrowLeft', null, 24 ) ).toBe( 22 );
		expect( keyStep( 'ArrowRight', null, 24 ) ).toBe( 23 );
		expect( keyStep( 'ArrowLeft', 0, 24 ) ).toBe( 0 );
		expect( keyStep( 'Home', 10, 24 ) ).toBe( 0 );
		expect( keyStep( 'End', 3, 24 ) ).toBe( 23 );
		expect( keyStep( 'a', 3, 24 ) ).toBeNull();
	} );
} );
