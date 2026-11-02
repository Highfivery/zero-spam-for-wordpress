/**
 * The admin areas are listed in three places that must agree: `adminAreas`
 * in webpack.config.js (the entry points), `admin_areas` in
 * bin/check-dist.sh (budgets and the translation check) and Admin_App
 * (`AREAS` plus the network page). Each also has its entry source.
 */

/**
 * External dependencies
 */
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = join( __dirname, '../../../..' );
const read = ( file ) => readFileSync( join( root, file ), 'utf8' );

/** @return {string[]} adminAreas of webpack.config.js. */
const webpackAreas = () => {
	const list = read( 'webpack.config.js' ).match(
		/const adminAreas = \[([^\]]*)\]/
	);
	return [ ...( list?.[ 1 ] ?? '' ).matchAll( /'([a-z-]+)'/g ) ].map(
		( m ) => m[ 1 ]
	);
};

/** @return {string[]} admin_areas of bin/check-dist.sh. */
const checkDistAreas = () => {
	const list = read( 'bin/check-dist.sh' ).match(
		/^admin_areas=\( ([^)]*) \)/m
	);
	return ( list?.[ 1 ] ?? '' ).split( /\s+/ ).filter( Boolean );
};

/** @return {string[]} Admin_App::AREAS keys, and the network page. */
const adminAppAreas = () => {
	const php = read( 'core/admin/class-adminapp.php' );
	const list = php.match( /const AREAS = array\(([\s\S]*?)\);/ );
	const areas = [
		...( list?.[ 1 ] ?? '' ).matchAll( /'([a-z-]+)'\s*=>/g ),
	].map( ( m ) => m[ 1 ] );
	expect( php ).toContain( "const NETWORK_SLUG = 'zero-spam-network';" );
	return [ ...areas, 'network' ];
};

describe( 'admin areas', () => {
	it( 'are the same in webpack.config.js, bin/check-dist.sh and Admin_App', () => {
		const expected = adminAppAreas().slice().sort();
		expect( expected.length ).toBeGreaterThan( 1 );
		expect( webpackAreas().slice().sort() ).toEqual( expected );
		expect( checkDistAreas().slice().sort() ).toEqual( expected );
	} );

	it( 'each have an entry source', () => {
		for ( const area of adminAppAreas() ) {
			expect(
				existsSync(
					join( root, `assets/src/admin/${ area }/index.js` )
				),
				`assets/src/admin/${ area }/index.js`
			).toBe( true );
		}
	} );
} );
