/**
 * The admin app inserts HTML it did not build itself in one place only: a
 * guide's body on Help (help/docs.js), sanitised on the server with
 * wp_kses_post() (tests/phpunit/integration/rest/test-rest-help-docs.php).
 * The mark (app/mark.js) inserts the plugin's own static icon sprite.
 */

/**
 * External dependencies
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { describe, expect, it } from 'vitest';

const ROOT = join( __dirname, '..', '..', '..' );
const ALLOWED = [ 'admin/app/mark.js', 'admin/help/docs.js' ];

/**
 * Every source file below a directory (tests left out).
 *
 * @param {string} dir Directory.
 * @return {string[]} Paths.
 */
const sources = ( dir ) =>
	readdirSync( dir ).flatMap( ( name ) => {
		const path = join( dir, name );
		if ( statSync( path ).isDirectory() ) {
			return 'test' === name ? [] : sources( path );
		}
		return name.endsWith( '.js' ) ? [ path ] : [];
	} );

describe( 'dangerouslySetInnerHTML', () => {
	it( 'is used only by the mark and the server-sanitised guides', () => {
		const users = sources( ROOT )
			.filter( ( path ) =>
				readFileSync( path, 'utf8' ).includes(
					'dangerouslySetInnerHTML'
				)
			)
			.map( ( path ) => relative( ROOT, path ).split( '\\' ).join( '/' ) )
			.sort();
		expect( users ).toEqual( ALLOWED );
	} );

	it( 'is used once in the guides, on the route’s html field', () => {
		const docs = readFileSync(
			join( ROOT, 'admin', 'help', 'docs.js' ),
			'utf8'
		);
		const uses = docs.match( /dangerouslySetInnerHTML=\{/g ) || [];
		expect( uses ).toHaveLength( 1 );
		expect( docs ).toContain( 'wp_kses_post()' );
		expect( docs ).toMatch( /<GuideHtml html=\{ doc\.html \} \/>/ );
	} );
} );
