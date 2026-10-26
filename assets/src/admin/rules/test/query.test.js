/**
 * The Rules tabs and REST paths, and that Admin_App preloads exactly the
 * paths the screen asks for on load (the preload store is read by path, so
 * one character apart means an extra request).
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	PER_PAGE,
	TABS,
	ariaSort,
	blocksPath,
	firstPath,
	listPath,
	paging,
	splitSort,
	toggleSort,
} from '../query';

const php = readFileSync(
	join( __dirname, '../../../../../core/admin/class-adminapp.php' ),
	'utf8'
);

/**
 * The path Admin_App::rules_preloads() builds for a tab, read from its
 * source (the constants and the three branches).
 *
 * @param {string} tab Tab.
 * @return {string} Path.
 */
function phpPath( tab ) {
	const perPage = php.match( /const RULES_PER_PAGE = (\d+);/ )[ 1 ];
	if ( 'ips' === tab || 'locations' === tab ) {
		return `/zero-spam/v1/rules/blocks?kind=${
			'ips' === tab ? 'ip' : 'location'
		}&per_page=${ perPage }&orderby=date_added&order=desc`;
	}
	const lists = {
		allowlist: 'allowed-ips',
		email: 'email-domains',
		words: 'allowed-words',
	};
	if ( lists[ tab ] ) {
		return `/zero-spam/v1/rules/lists/${ lists[ tab ] }?per_page=${ perPage }`;
	}
	return '/zero-spam/v1/rules/no-lockout';
}

describe( 'preloads', () => {
	it( 'Admin_App knows the same tabs and page size', () => {
		const tabs = php.match( /const RULES_TABS = array\( ([^)]+) \);/ )[ 1 ];
		expect(
			tabs.split( ', ' ).map( ( t ) => t.replace( /'/g, '' ) )
		).toEqual( TABS );
		expect( php ).toContain( `const RULES_PER_PAGE = ${ PER_PAGE };` );
	} );

	it( 'the PHP builds every tab’s first path as the screen asks for it', () => {
		expect( php ).toContain(
			"'/zero-spam/v1/rules/blocks?kind=' . ( 'ips' === $tab ? 'ip' : 'location' ) . '&per_page=' . self::RULES_PER_PAGE . '&orderby=date_added&order=desc'"
		);
		expect( php ).toContain(
			"'/zero-spam/v1/rules/lists/' . $lists[ $tab ] . '?per_page=' . self::RULES_PER_PAGE"
		);
		TABS.forEach( ( tab ) => {
			expect( firstPath( tab ) ).toBe( phpPath( tab ) );
		} );
	} );
} );

describe( 'paths', () => {
	it( 'adds the page and search only when set', () => {
		expect(
			blocksPath( { kind: 'location', page: 3, search: ' de ' } )
		).toBe(
			'/zero-spam/v1/rules/blocks?kind=location&per_page=50&orderby=date_added&order=desc&page=3&search=de'
		);
		expect( blocksPath( { kind: 'ip', sort: 'end:asc' } ) ).toBe(
			'/zero-spam/v1/rules/blocks?kind=ip&per_page=50&orderby=end&order=asc'
		);
		expect( listPath( 'email-domains', { search: 'mail&co' } ) ).toBe(
			'/zero-spam/v1/rules/lists/email-domains?per_page=50&search=mail%26co'
		);
	} );
} );

describe( 'sorting', () => {
	it( 'falls back to newest first for unknown values', () => {
		expect( splitSort( 'bogus:sideways' ) ).toEqual( [
			'date_added',
			'desc',
		] );
	} );

	it( 'toggles the current column and starts others A–Z (dates newest first)', () => {
		expect( toggleSort( 'date_added:desc', 'date_added' ) ).toBe(
			'date_added:asc'
		);
		expect( toggleSort( 'date_added:desc', 'key' ) ).toBe( 'key:asc' );
		expect( toggleSort( 'key:asc', 'key' ) ).toBe( 'key:desc' );
		expect( toggleSort( 'key:asc', 'date_added' ) ).toBe(
			'date_added:desc'
		);
	} );

	it( 'reports aria-sort', () => {
		expect( ariaSort( 'end:asc', 'end' ) ).toBe( 'ascending' );
		expect( ariaSort( 'end:desc', 'end' ) ).toBe( 'descending' );
		expect( ariaSort( 'end:asc', 'key' ) ).toBe( 'none' );
	} );
} );

describe( 'paging', () => {
	it( 'clamps the page and counts what is shown', () => {
		expect( paging( 0, 1 ) ).toEqual( {
			page: 1,
			pages: 1,
			from: 0,
			to: 0,
		} );
		expect( paging( 120, 3, 20 ) ).toEqual( {
			page: 3,
			pages: 3,
			from: 101,
			to: 120,
		} );
		expect( paging( 120, 9, 20 ).page ).toBe( 3 );
		expect( paging( 51, 1, 50 ) ).toEqual( {
			page: 1,
			pages: 2,
			from: 1,
			to: 50,
		} );
	} );
} );
