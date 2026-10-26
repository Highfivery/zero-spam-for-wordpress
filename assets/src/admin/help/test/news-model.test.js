/**
 * The news on Help: categories, filtering and the tile's states.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	displayCategory,
	filterNews,
	newsCategories,
	newsView,
} from '../news-model';

const ITEMS = [
	{ link: 'a', categories: [ 'Site Security' ], security_notice: false },
	{
		link: 'b',
		categories: [ 'Incident Response' ],
		security_notice: true,
	},
	{
		link: 'c',
		categories: [ 'Incident Response', 'Spam Protection' ],
		security_notice: true,
	},
	{ link: 'd', categories: [ 'Spam Protection' ], security_notice: false },
	{ link: 'e', categories: [], security_notice: false },
];

describe( 'categories', () => {
	it( 'prefers a category other than the security-notice one', () => {
		expect( displayCategory( ITEMS[ 2 ] ) ).toBe( 'Spam Protection' );
		expect( displayCategory( ITEMS[ 1 ] ) ).toBe( 'Incident Response' );
		expect( displayCategory( ITEMS[ 4 ] ) ).toBe( '' );
		expect( displayCategory( undefined ) ).toBe( '' );
	} );

	it( 'lists each category once, in order of first use', () => {
		expect( newsCategories( ITEMS ) ).toEqual( [
			'Site Security',
			'Incident Response',
			'Spam Protection',
		] );
	} );

	it( 'filters by the shown category', () => {
		expect(
			filterNews( ITEMS, 'Spam Protection' ).map( ( i ) => i.link )
		).toEqual( [ 'c', 'd' ] );
		expect( filterNews( ITEMS, '' ) ).toHaveLength( 5 );
		expect( filterNews( undefined, '' ) ).toEqual( [] );
	} );
} );

describe( 'newsView()', () => {
	it( 'is disabled when the news is off, or the route says so', () => {
		expect( newsView( { enabled: false, state: {} } ).state ).toBe(
			'disabled'
		);
		expect(
			newsView( {
				enabled: true,
				state: {
					error: {
						code: 'zerospam_news_disabled',
						data: { status: 404 },
					},
				},
			} ).state
		).toBe( 'disabled' );
		// A fetched error with parse: false is the Response itself.
		expect(
			newsView( { enabled: true, state: { error: { status: 404 } } } )
				.state
		).toBe( 'disabled' );
	} );

	it( 'is unavailable when no copy could be had, preloaded or fetched', () => {
		expect(
			newsView( {
				enabled: true,
				state: { error: { status: 503 }, data: undefined },
			} ).state
		).toBe( 'unavailable' );
		expect(
			newsView( {
				enabled: true,
				state: {
					data: {
						code: 'zerospam_news_unavailable',
						data: { status: 503 },
					},
				},
			} ).state
		).toBe( 'unavailable' );
	} );

	it( 'is ready with articles, and says when they are an older copy', () => {
		expect(
			newsView( { enabled: true, state: { data: ITEMS, headers: {} } } )
		).toEqual( { state: 'ready', stale: false } );
		expect(
			newsView( {
				enabled: true,
				state: {
					data: ITEMS,
					headers: { 'x-zerospam-news-stale': '1' },
				},
			} )
		).toEqual( { state: 'ready', stale: true } );
	} );

	it( 'waits for the first answer, and knows an empty feed', () => {
		expect(
			newsView( { enabled: true, state: { loading: true } } ).state
		).toBe( 'loading' );
		expect( newsView( { enabled: true, state: { data: [] } } ).state ).toBe(
			'empty'
		);
	} );
} );
