/**
 * Nobody works out a time zone from the clocks again: `nowLocal - Date.now()`
 * holds the page's latency and the browser clock's error, and is only today's
 * offset (AGENTS.md section 2, Data). Absolute dates go through site-time.js.
 */

/**
 * External dependencies
 */
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const ROOT = join( __dirname, '..', '..', '..' );

const sources = ( dir ) =>
	readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const path = join( dir, entry.name );
		if ( entry.isDirectory() ) {
			return 'test' === entry.name ? [] : sources( path );
		}
		return entry.name.endsWith( '.js' ) ? [ path ] : [];
	} );

describe( 'the sources', () => {
	it( 'never subtract the browser’s clock from the site’s', () => {
		const found = sources( ROOT ).filter( ( file ) => {
			const code = readFileSync( file, 'utf8' )
				// Comments may name what not to do.
				.replace( /\/\*[\s\S]*?\*\//g, '' )
				.replace( /\/\/.*$/gm, '' );
			return /nowLocal(\(\s*\))?\s*-\s*Date\.now/.test( code );
		} );
		expect( found.map( ( file ) => file.replace( ROOT, '' ) ) ).toEqual( [
			// The one place: it is what nowLocal() corrects the clock with.
			'/admin/data/api.js',
		] );
	} );
} );
