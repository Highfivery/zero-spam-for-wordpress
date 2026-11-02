/**
 * The WordPress.org listing art in .wordpress-org/: a fresh render must equal
 * the committed PNGs byte for byte, and the art must meet the listing's rules
 * (dimensions, file size, text inside the central 1200 px at 4.5:1 or better,
 * a flat icon.svg on the same bricks as the PNG icon).
 *
 * The byte comparison runs on macOS only, where the committed files are
 * rendered: other platforms draw text differently. After a change to the
 * sources, the tokens, the fonts or the Playwright version, run
 * node bin/render-listing-assets.mjs and commit the result.
 */

/**
 * External dependencies
 */
import { mkdtempSync, readFileSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	LISTING_DIR,
	TARGETS,
	renderListingAssets,
} from '../../../../bin/render-listing-assets.mjs';

const committed = ( file ) => readFileSync( join( LISTING_DIR, file ) );

/**
 * @param {Buffer} png A PNG file.
 * @return {number[]} Its width and height, from the IHDR chunk.
 */
const pngSize = ( png ) => [ png.readUInt32BE( 16 ), png.readUInt32BE( 20 ) ];

/**
 * @param {string} markup SVG or HTML.
 * @return {string[]} The position and size of every filled brick, sorted.
 */
const bricks = ( markup ) =>
	[
		...markup.matchAll(
			/<rect x="(\d+)" y="(\d+)" width="(\d+)" height="(\d+)" rx="\d+" fill="(?!none)/g
		),
	]
		.map( ( match ) => match.slice( 1, 5 ).join( ' ' ) )
		.sort();

describe( 'WordPress.org listing assets', () => {
	let outDir;
	let measurements;

	beforeAll( async () => {
		outDir = mkdtempSync( join( tmpdir(), 'zero-spam-listing-' ) );
		measurements = await renderListingAssets( outDir );
	}, 120000 );

	afterAll( () => {
		rmSync( outDir, { recursive: true, force: true } );
	} );

	it
		.runIf( process.platform === 'darwin' )
		.each( TARGETS.map( ( target ) => target.file ) )(
		'%s is what the sources render',
		( file ) => {
			const same = committed( file ).equals(
				readFileSync( join( outDir, file ) )
			);
			expect(
				same,
				`${ file } is stale: run node bin/render-listing-assets.mjs and commit it`
			).toBe( true );
		}
	);

	it.each( TARGETS.map( ( target ) => target.file ) )(
		'%s has the right dimensions and is under 500 KB',
		( file ) => {
			const [ , width, height ] = file.match( /-(\d+)x(\d+)/ );
			expect( pngSize( committed( file ) ) ).toEqual( [
				Number( width ),
				Number( height ),
			] );
			expect( statSync( join( LISTING_DIR, file ) ).size ).toBeLessThan(
				500 * 1024
			);
		}
	);

	it.each( TARGETS.filter( ( t ) => t.banner ).map( ( t ) => t.file ) )(
		'%s keeps its text legible and inside the central 1200 px',
		( file ) => {
			const width = 772 * ( file.includes( '1544' ) ? 2 : 1 );
			const margin = ( ( 1544 - 1200 ) / 2 ) * ( width / 1544 );
			const lines = measurements[ file ];
			expect( lines.map( ( line ) => line.text ) ).toEqual( [
				'Zero Spam',
				'for WordPress',
			] );
			for ( const line of lines ) {
				expect( line.left ).toBeGreaterThanOrEqual( margin );
				expect( line.right ).toBeLessThanOrEqual( width - margin );
				expect( line.contrast ).toBeGreaterThanOrEqual( 4.5 );
			}
		}
	);

	it( 'icon.svg is flat, on the same bricks as the PNG icon', () => {
		const svg = committed( 'icon.svg' ).toString();
		expect( svg ).toContain( 'viewBox="0 0 256 256"' );
		expect( svg ).not.toMatch(
			/gradient|transform|url\(|style|<text|<image/i
		);
		const expected = bricks( committed( 'src/icon.html' ).toString() );
		expect( bricks( svg ) ).toEqual( expected );
		expect( expected ).toHaveLength( 13 );
	} );

	it( 'never ships in the plugin', () => {
		const ignored = readFileSync(
			join( LISTING_DIR, '..', '.distignore' ),
			'utf8'
		);
		expect( ignored.split( '\n' ) ).toContain( '.wordpress-org' );
	} );
} );
