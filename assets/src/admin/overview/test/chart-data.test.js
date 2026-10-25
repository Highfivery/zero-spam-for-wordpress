/**
 * The Overview's data mapping: the brick chart, its band and axis, the
 * "This week" buckets, live additions and milestones.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	CHART,
	addToDays,
	axisTicks,
	bandFor,
	brickValue,
	busiestDay,
	mapChart,
	milestones,
	weekBuckets,
} from '../chart-data';

const days = ( totals ) =>
	totals.map( ( total, i ) => ( {
		date: `2026-10-${ String( i + 1 ).padStart( 2, '0' ) }`,
		total,
	} ) );

describe( 'brickValue', () => {
	it( 'keeps the busiest day within 13 bricks with a round value', () => {
		expect( brickValue( 0 ) ).toBe( 1 );
		expect( brickValue( 13 ) ).toBe( 1 );
		expect( brickValue( 14 ) ).toBe( 2 );
		expect( brickValue( 63 ) ).toBe( 5 ); // The prototype: 1 brick = 5 threats.
		expect( brickValue( 130 ) ).toBe( 10 );
		expect( brickValue( 5000 ) ).toBe( 500 );
		expect(
			Math.ceil( 123456 / brickValue( 123456 ) )
		).toBeLessThanOrEqual( 13 );
	} );
} );

describe( 'mapChart', () => {
	it( 'builds each day from full bricks plus a partial one', () => {
		const chart = mapChart( days( [ 12, 0, 63 ] ) );
		expect( chart.per ).toBe( 5 );
		expect( chart.rows ).toBe( 14 );
		expect( chart.columns ).toHaveLength( 3 );
		expect( chart.columns[ 0 ].bricks ).toHaveLength( 3 ); // 5 + 5 + 2.
		expect( chart.columns[ 0 ].bricks[ 2 ].partial ).toBe( true );
		expect( chart.columns[ 1 ].bricks ).toHaveLength( 0 );
		expect( chart.columns[ 2 ].bricks ).toHaveLength( 13 );
		expect( chart.empty ).toBe( false );
	} );

	it( 'darkens courses every three bricks (five shades)', () => {
		const shades = mapChart( days( [ 65 ] ) ).columns[ 0 ].bricks.map(
			( b ) => b.shade
		);
		expect( shades.slice( 0, 7 ) ).toEqual( [ 0, 0, 0, 1, 1, 1, 2 ] );
		expect( Math.max( ...shades ) ).toBe( 4 );
	} );

	it( 'stays inside the drawing box', () => {
		const chart = mapChart(
			days( Array.from( { length: 30 }, ( _, i ) => i * 3 ) )
		);
		chart.columns.forEach( ( col ) => {
			expect( col.x ).toBeGreaterThanOrEqual( CHART.left );
			expect( col.x + col.width ).toBeLessThanOrEqual(
				CHART.width - CHART.right
			);
			col.bricks.forEach( ( b ) =>
				expect( b.y ).toBeGreaterThanOrEqual( CHART.top - 1 )
			);
		} );
		chart.grid.forEach( ( g ) =>
			expect( g.value % ( chart.per * 4 ) ).toBe( 0 )
		);
	} );

	it( 'reports an empty chart for a fresh site', () => {
		const chart = mapChart( days( Array( 30 ).fill( 0 ) ) );
		expect( chart.empty ).toBe( true );
		expect( chart.columns.every( ( c ) => 0 === c.bricks.length ) ).toBe(
			true
		);
		expect( mapChart( undefined ).columns ).toEqual( [] );
	} );
} );

describe( 'bandFor and axisTicks', () => {
	it( 'highlights the last 1, 7 or 30 days', () => {
		const w = ( CHART.width - CHART.left - CHART.right ) / 30;
		expect( bandFor( 7, 30, w ).x ).toBeCloseTo( CHART.left + 23 * w - 3 );
		expect( bandFor( 7, 30, w ).width ).toBeCloseTo( 7 * w + 3 );
		expect( bandFor( 30, 30, w ).x ).toBeCloseTo( CHART.left - 3 );
		expect( bandFor( 99, 30, w ).width ).toBeCloseTo( 30 * w + 3 );
	} );

	it( 'labels the first and last day and three between', () => {
		expect( axisTicks( 30 ) ).toEqual( [ 0, 8, 15, 22, 29 ] );
		expect( axisTicks( 1 ) ).toEqual( [ 0 ] );
		expect( axisTicks( 2 ) ).toEqual( [ 0, 1 ] );
	} );
} );

describe( 'busiestDay', () => {
	it( 'picks the latest of the busiest days and ignores empty ones', () => {
		expect( busiestDay( days( [ 3, 9, 9, 1 ] ) ).date ).toBe(
			'2026-10-03'
		);
		expect( busiestDay( days( [ 0, 0 ] ) ) ).toBeNull();
	} );
} );

describe( 'weekBuckets', () => {
	it( 'groups detection types into the five rows, in order', () => {
		expect(
			weekBuckets( [
				{ type: 'comment', count: 4 },
				{ type: 'wpforms', count: 2 },
				{ type: 'woocommerce_checkout', count: 1 },
				{ type: 'login', count: 3 },
				{ type: 'blocked', count: 5 },
			] )
		).toEqual( [
			{ bucket: 'comments', count: 4 },
			{ bucket: 'login', count: 3 },
			{ bucket: 'registration', count: 0 },
			{ bucket: 'forms', count: 3 },
			{ bucket: 'blocked', count: 5 },
		] );
		expect( weekBuckets( undefined ).every( ( b ) => 0 === b.count ) ).toBe(
			true
		);
	} );
} );

describe( 'addToDays', () => {
	it( 'adds live detections to their day', () => {
		const before = days( [ 1, 2 ] );
		const after = addToDays( before, [
			{ date: '2026-10-02T10:00:00' },
			{ date: '2026-10-02T11:00:00' },
			{ date: '2026-09-01T00:00:00' },
		] );
		expect( after.map( ( d ) => d.total ) ).toEqual( [ 1, 4 ] );
		expect( before[ 1 ].total ).toBe( 2 );
	} );
} );

describe( 'milestones', () => {
	it( 'steps 100, 250, 500, 1,000, 2,500 …', () => {
		expect( milestones( 0 ) ).toEqual( { reached: 0, next: 100 } );
		expect( milestones( 157 ) ).toEqual( { reached: 100, next: 250 } );
		expect( milestones( 1284 ) ).toEqual( { reached: 1000, next: 2500 } );
		expect( milestones( 2500 ) ).toEqual( { reached: 2500, next: 5000 } );
	} );
} );
