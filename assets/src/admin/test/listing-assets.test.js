/**
 * The WordPress.org listing art in .wordpress-org/: the committed files meet the
 * listing's rules, and a fresh render still gives them.
 *
 * This is in the pre-push gate, so a machine that draws text differently must
 * not fail it. The icons have no text and are compared by bytes. The banners
 * are compared by pixels (compareBanner() in the render script), which allows
 * for moved glyph edges and little else. On the macOS machine that renders the
 * art, node bin/render-listing-assets.mjs --check is the strict byte comparison
 * of all six.
 *
 * After a change to the sources, the tokens, the fonts, the mark or the
 * Playwright version, run node bin/render-listing-assets.mjs and commit the
 * result.
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
	compareBanner,
	renderListingAssets,
} from '../../../../bin/render-listing-assets.mjs';

const committed = ( file ) => readFileSync( join( LISTING_DIR, file ) );

const files = TARGETS.map( ( target ) => target.file );
const icons = TARGETS.filter( ( target ) => ! target.banner ).map(
	( target ) => target.file
);
const banners = TARGETS.filter( ( target ) => target.banner ).map(
	( target ) => target.file
);

/**
 * @param {Buffer} png A PNG file.
 * @return {number[]} Its width and height, from the IHDR chunk.
 */
const pngSize = ( png ) => [ png.readUInt32BE( 16 ), png.readUInt32BE( 20 ) ];

/**
 * @param {string} markup SVG or HTML.
 * @return {string[]} Every filled brick as "x y width height colour", sorted.
 *                    A gradient counts as the palette colour it is named after;
 *                    the core brick is the palette's #FF2929.
 */
const bricks = ( markup ) =>
	[
		...markup.matchAll(
			/<rect x="(\d+)" y="(\d+)" width="(\d+)" height="(\d+)" rx="\d+" fill="(?!none)([^"]+)"/g
		),
	]
		.map( ( match ) => {
			const colour = match[ 5 ]
				.replace( 'url(#core)', '#ff2929' )
				.replace( /^url\(#g-(\w+)\)$/, '#$1' )
				.toLowerCase();
			return [ ...match.slice( 1, 5 ), colour ].join( ' ' );
		} )
		.sort();

describe( 'the committed listing art', () => {
	it.each( files )(
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

	it( 'icon.svg is flat, with the bricks and colours of the PNG icon', () => {
		const svg = committed( 'icon.svg' ).toString();
		expect( svg ).toContain( 'viewBox="0 0 256 256"' );
		expect( svg ).not.toMatch(
			/gradient|transform|url\(|style|<text|<image/i
		);
		expect( svg ).toContain(
			'<rect width="256" height="256" fill="#1A0506"/>'
		);
		const expected = bricks( committed( 'src/icon.html' ).toString() );
		expect( expected ).toHaveLength( 13 );
		expect( bricks( svg ) ).toEqual( expected );
	} );

	it( 'never ships in the plugin', () => {
		const ignored = readFileSync(
			join( LISTING_DIR, '..', '.distignore' ),
			'utf8'
		);
		expect( ignored.split( '\n' ) ).toContain( '.wordpress-org' );
	} );
} );

describe( 'a fresh render', () => {
	let outDir;
	let measurements;

	beforeAll( async () => {
		outDir = mkdtempSync( join( tmpdir(), 'zero-spam-listing-' ) );
		try {
			measurements = await renderListingAssets( outDir );
		} catch ( error ) {
			throw new Error(
				`Could not render the listing art. If Chromium for Playwright is missing, run: npx playwright install chromium\n${ error.message }`
			);
		}
	}, 120000 );

	afterAll( () => {
		rmSync( outDir, { recursive: true, force: true } );
	} );

	it.each( icons )(
		'%s is what the source renders, byte for byte',
		( file ) => {
			expect(
				committed( file ).equals(
					readFileSync( join( outDir, file ) )
				),
				`${ file } is stale: run node bin/render-listing-assets.mjs and commit it`
			).toBe( true );
		}
	);

	it.each( banners )(
		'%s is what the sources render, within the pixel tolerance',
		async ( file ) => {
			const result = await compareBanner(
				committed( file ),
				readFileSync( join( outDir, file ) ),
				measurements[ file ].lines
			);
			expect(
				result.within,
				`${ file } is stale (${ JSON.stringify(
					result
				) }): run node bin/render-listing-assets.mjs and commit it`
			).toBe( true );
		},
		60000
	);

	it.each( banners )(
		'%s keeps the mark and text inside the central 1200 px, text legible',
		( file ) => {
			const width = 772 * ( file.includes( '1544' ) ? 2 : 1 );
			const margin = ( ( 1544 - 1200 ) / 2 ) * ( width / 1544 );
			const { lines, mark } = measurements[ file ];
			expect( lines.map( ( line ) => line.text ) ).toEqual( [
				'Zero Spam',
				'for WordPress',
			] );
			for ( const box of [ mark, ...lines ] ) {
				expect( box.left ).toBeGreaterThanOrEqual( margin );
				expect( box.right ).toBeLessThanOrEqual( width - margin );
			}
			for ( const line of lines ) {
				expect( line.contrast ).toBeGreaterThanOrEqual( 4.5 );
				if ( file.includes( '-rtl' ) ) {
					expect( mark.left ).toBeGreaterThanOrEqual( line.right );
				} else {
					expect( mark.right ).toBeLessThanOrEqual( line.left );
				}
			}
		}
	);
} );
