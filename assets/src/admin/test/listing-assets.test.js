/**
 * The WordPress.org listing art in .wordpress-org/: the committed files meet the
 * listing's rules, and a fresh render still looks like them.
 *
 * This is in the pre-push gate on every push that reaches the unit tests, and
 * machines draw text and encode PNGs differently, so the comparison is a plain
 * pixel one that only catches art that is clearly stale or broken. A small
 * change (an underline, a slightly different colour) passes here: bin/check-dist.sh
 * catches it with node bin/render-listing-assets.mjs --check, the byte comparison
 * it runs only when the art or its inputs changed.
 */

/**
 * External dependencies
 */
import { mkdtempSync, readFileSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	LISTING_DIR,
	TARGETS,
	renderListingAssets,
} from '../../../../bin/render-listing-assets.mjs';

// A pixel differs when a channel moves by more than this, as in render.test.js.
const PIXEL_DELTA = 16;
// The share of a file's pixels that may differ. Simulating another platform's
// text moved up to 1.6% of a banner; the smallest broken one tried (the wrong
// mark) moved 4.4%.
const MAX_SHARE = 0.03;

const committed = ( file ) => readFileSync( join( LISTING_DIR, file ) );

const files = TARGETS.map( ( target ) => target.file );
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
	let browser;

	beforeAll( async () => {
		outDir = mkdtempSync( join( tmpdir(), 'zero-spam-listing-' ) );
		try {
			measurements = await renderListingAssets( outDir );
			browser = await chromium.launch();
		} catch ( error ) {
			throw new Error(
				`Could not render the listing art. If Chromium for Playwright is missing, run: npx playwright install chromium\n${ error.message }`
			);
		}
	}, 120000 );

	afterAll( async () => {
		await browser?.close();
		rmSync( outDir, { recursive: true, force: true } );
	}, 60000 );

	it.each( files )(
		'%s still looks like what the sources render',
		async ( file ) => {
			const page = await browser.newPage();
			const share = await page.evaluate(
				async ( { pngs, delta } ) => {
					const [ a, b ] = await Promise.all(
						pngs.map( async ( png ) => {
							const image = new window.Image();
							image.src = `data:image/png;base64,${ png }`;
							await image.decode();
							const canvas = document.createElement( 'canvas' );
							canvas.width = image.width;
							canvas.height = image.height;
							const context = canvas.getContext( '2d' );
							context.drawImage( image, 0, 0 );
							return context.getImageData(
								0,
								0,
								image.width,
								image.height
							).data;
						} )
					);
					if ( a.length !== b.length ) {
						return 1;
					}
					let differing = 0;
					for ( let i = 0; i < a.length; i += 4 ) {
						if (
							Math.abs( a[ i ] - b[ i ] ) > delta ||
							Math.abs( a[ i + 1 ] - b[ i + 1 ] ) > delta ||
							Math.abs( a[ i + 2 ] - b[ i + 2 ] ) > delta
						) {
							differing++;
						}
					}
					return differing / ( a.length / 4 );
				},
				{
					pngs: [
						committed( file ),
						readFileSync( join( outDir, file ) ),
					].map( ( png ) => png.toString( 'base64' ) ),
					delta: PIXEL_DELTA,
				}
			);
			await page.close();
			expect(
				share,
				`${ file } is stale or broken: run node bin/render-listing-assets.mjs, look at it and commit it`
			).toBeLessThanOrEqual( MAX_SHARE );
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
