/**
 * The widget's arithmetic and sentences. The sparkline and figure cases are
 * the same as tests/phpunit/unit/test-sparkline.php, so the server and a
 * refresh draw and say the same thing.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	deltaText,
	endpoint,
	figures,
	format,
	latestItems,
	numberFormat,
	relativeTime,
	sparkPaths,
	sparkText,
} from '../model';

const i18n = {
	deltaUp: 'Last 7 days: %1$s, up %2$s from the 7 days before.',
	deltaDown: 'Last 7 days: %1$s, down %2$s from the 7 days before.',
	deltaSame: 'Last 7 days: %s, the same as the 7 days before.',
	percent: '%s%%',
	spark: 'Spam blocked per day over the last 30 days: at most %1$s on one day, %2$s today.',
};
const number = numberFormat( 'en-US' );
const fill = ( n, v ) => Array( n ).fill( v );

describe( 'sparkPaths (Sparkline::paths)', () => {
	it.each( [
		[
			'five days',
			[ 0, 2, 4, 1, 3 ],
			240,
			48,
			'M0.0 46.0L60.0 24.0L120.0 2.0L180.0 35.0L240.0 13.0',
		],
		[
			'thirds round',
			[ 1, 2, 3 ],
			100,
			20,
			'M0.0 12.7L50.0 7.3L100.0 2.0',
		],
		[ 'empty series', [], 240, 48, 'M0.0 46.0L240.0 46.0' ],
		[ 'one value', [ 5 ], 240, 48, 'M0.0 2.0L240.0 2.0' ],
		[
			'only zeros',
			[ 0, 0, 0 ],
			240,
			48,
			'M0.0 46.0L120.0 46.0L240.0 46.0',
		],
		[ 'negatives are zero', [ -3, 2 ], 240, 48, 'M0.0 46.0L240.0 2.0' ],
	] )( '%s', ( name, values, width, height, line ) => {
		const paths = sparkPaths( values, width, height );
		expect( paths.line ).toBe( line );
		expect( paths.area ).toBe(
			`${ line }L${ width } ${ height }L0 ${ height }Z`
		);
	} );

	it( 'spreads thirty days over the width', () => {
		const { line } = sparkPaths( fill( 30, 1 ) );
		expect( line.startsWith( 'M0.0 2.0L8.3 2.0L16.6 2.0' ) ).toBe( true );
		expect( line.endsWith( 'L240.0 2.0' ) ).toBe( true );
		expect( line.split( 'L' ) ).toHaveLength( 30 );
	} );
} );

describe( 'figures and deltaText (Dashboard_Widget::figures, delta_text)', () => {
	it.each( [
		[
			'up by half',
			[ ...fill( 16, 1 ), ...fill( 7, 2 ), ...fill( 7, 3 ) ],
			{ total: 51, week: 21, previous: 14, delta: 7, percent: 50 },
			'up',
			'Last 7 days: 21, up 50% from the 7 days before.',
		],
		[
			'nothing the week before',
			[ ...fill( 23, 0 ), ...fill( 7, 1 ) ],
			{ total: 7, week: 7, previous: 0, delta: 7, percent: null },
			'up',
			'Last 7 days: 7, up 7 from the 7 days before.',
		],
		[
			'down',
			[ ...fill( 16, 0 ), ...fill( 7, 4 ), ...fill( 7, 1 ) ],
			{ total: 35, week: 7, previous: 28, delta: -21, percent: 75 },
			'down',
			'Last 7 days: 7, down 75% from the 7 days before.',
		],
		[
			'short series',
			fill( 10, 1 ),
			{ total: 10, week: 7, previous: 3, delta: 4, percent: 133 },
			'up',
			'Last 7 days: 7, up 133% from the 7 days before.',
		],
		[
			'no detections',
			fill( 30, 0 ),
			{ total: 0, week: 0, previous: 0, delta: 0, percent: null },
			'same',
			'Last 7 days: 0, the same as the 7 days before.',
		],
	] )( '%s', ( name, days, expected, direction, text ) => {
		const fig = figures( days );
		expect( fig ).toEqual( expected );
		expect( deltaText( fig, i18n, number ) ).toEqual( { text, direction } );
	} );

	it( 'formats large numbers for the locale', () => {
		const fig = figures( [ ...fill( 23, 0 ), ...fill( 7, 1000 ) ] );
		expect( deltaText( fig, i18n, number ).text ).toBe(
			'Last 7 days: 7,000, up 7,000 from the 7 days before.'
		);
		expect( deltaText( fig, i18n, numberFormat( 'de-DE' ) ).text ).toBe(
			'Last 7 days: 7.000, up 7.000 from the 7 days before.'
		);
	} );
} );

describe( 'sparkText (Dashboard_Widget::spark_text)', () => {
	it( 'names the busiest day and today', () => {
		expect( sparkText( [ 0, 2, 4, 1, 3 ], i18n, number ) ).toBe(
			'Spam blocked per day over the last 30 days: at most 4 on one day, 3 today.'
		);
		expect( sparkText( [], i18n, number ) ).toBe(
			'Spam blocked per day over the last 30 days: at most 0 on one day, 0 today.'
		);
	} );
} );

describe( 'format', () => {
	it( 'fills numbered and plain placeholders and %%', () => {
		expect( format( '%2$s before %1$s', 'a', 'b' ) ).toBe( 'b before a' );
		expect( format( '%s and %s', 'a', 'b' ) ).toBe( 'a and b' );
		expect( format( '%s%%', '12' ) ).toBe( '12%' );
		expect( format( '%s', '<b>x</b>' ) ).toBe( '<b>x</b>' );
	} );
} );

describe( 'endpoint', () => {
	it( 'adds the query to a pretty-permalink root', () => {
		expect(
			endpoint(
				'https://example.org/wp-json/zero-spam/v1/',
				'stats/timeseries',
				{ range: '30', _fields: 'days' }
			)
		).toBe(
			'https://example.org/wp-json/zero-spam/v1/stats/timeseries?range=30&_fields=days'
		);
	} );

	it( 'keeps ?rest_route= roots working', () => {
		expect(
			endpoint(
				'https://example.org/?rest_route=/zero-spam/v1/',
				'activity',
				{ per_page: '10' }
			)
		).toBe(
			'https://example.org/?rest_route=/zero-spam/v1/activity&per_page=10'
		);
	} );
} );

describe( 'latestItems', () => {
	it( 'leaves out non-detections and keeps the newest three', () => {
		const entries = [
			{
				type: 'rescue',
				type_label: 'Rescue link used',
				ip: '192.0.2.200',
				date_gmt: 'a',
			},
			{
				type: 'comment',
				type_label: 'Comment',
				ip: '192.0.2.1',
				date_gmt: 'b',
			},
			{
				type: 'login',
				type_label: 'Login',
				ip: '192.0.2.2',
				date_gmt: 'c',
			},
			{
				type: 'rescue',
				type_label: 'Rescue link used',
				ip: '192.0.2.200',
				date_gmt: 'd',
			},
			{ type: 'comment', type_label: '', ip: '192.0.2.3', date_gmt: 'e' },
			{
				type: 'comment',
				type_label: 'Comment',
				ip: '192.0.2.4',
				date_gmt: 'f',
			},
		];
		expect( latestItems( entries, [ 'rescue' ], 3 ) ).toEqual( [
			{ label: 'Comment', ip: '192.0.2.1', date: 'b' },
			{ label: 'Login', ip: '192.0.2.2', date: 'c' },
			{ label: 'comment', ip: '192.0.2.3', date: 'e' },
		] );
		expect( latestItems( null, [ 'rescue' ], 3 ) ).toEqual( [] );
	} );
} );

describe( 'relativeTime', () => {
	const now = Date.parse( '2026-10-25T12:00:00Z' );

	it.each( [
		[ '2026-10-25T12:00:00Z', 'now' ],
		[ '2026-10-25T11:55:00Z', '5 minutes ago' ],
		[ '2026-10-25T10:00:00Z', '2 hours ago' ],
		[ '2026-10-24T12:00:00Z', 'yesterday' ],
		[ '2026-10-20T12:00:00Z', '5 days ago' ],
	] )( '%s is "%s"', ( iso, text ) => {
		expect( relativeTime( iso, now, 'en-US' ) ).toBe( text );
	} );

	it( 'returns nothing for an unreadable date', () => {
		expect( relativeTime( 'not a date', now, 'en-US' ) ).toBe( '' );
	} );
} );
