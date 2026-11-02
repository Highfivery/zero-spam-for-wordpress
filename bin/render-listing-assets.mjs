#!/usr/bin/env node
/**
 * Renders the WordPress.org listing art in .wordpress-org/ from the HTML sources
 * in .wordpress-org/src/ with Playwright's Chromium.
 *
 * Usage: node bin/render-listing-assets.mjs [--out=<directory>] [--measure] [--check]
 *   --out      write the PNGs somewhere else (default .wordpress-org/)
 *   --measure  print each banner's mark and text position and text contrast
 *   --check    write nothing; exit 1 unless a fresh render equals every committed
 *              PNG byte for byte
 *
 * Reproducible on one machine: no timestamps, a pinned browser, sRGB output, no
 * LCD text or hinting, and no network (every request outside this checkout
 * stops the render). Text is drawn differently from one platform to another,
 * so the committed files are rendered on macOS and --check is meant for that
 * machine. assets/src/admin/test/listing-assets.test.js, which is in the
 * pre-push gate, compares the icons by bytes and the banners with
 * compareBanner()'s pixel tolerance.
 */

import {
	mkdirSync,
	mkdtempSync,
	readFileSync,
	realpathSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
export const LISTING_DIR = join( root, '.wordpress-org' );

const icon = ( size ) => ( {
	file: `icon-${ size }x${ size }.png`,
	source: 'icon.html',
	viewport: { width: size, height: size },
	scale: 1,
} );
const banner = ( scale, rtl ) => ( {
	file: `banner-${ 772 * scale }x${ 250 * scale }${ rtl ? '-rtl' : '' }.png`,
	source: 'banner.html',
	query: rtl ? '?dir=rtl' : '',
	viewport: { width: 772, height: 250 },
	scale,
	banner: true,
} );

export const TARGETS = [
	icon( 256 ),
	icon( 128 ),
	banner( 2, false ),
	banner( 2, true ),
	banner( 1, false ),
	banner( 1, true ),
];

/**
 * compareBanner()'s limits, in the style of admin/test/render.test.js: a pixel
 * differs when a channel moves by more than `delta`.
 *
 * - Outside the text boxes only stray edge pixels may move.
 * - Inside a text line, another platform's rasteriser moves the glyph edges
 *   (about 22% of the small line's box at 772 px when macOS's glyph dilation is
 *   turned off), so up to `textEdges` of a box may differ. The glyph interiors
 *   (pixels of the committed PNG that are the text colour, with all eight
 *   neighbours) barely move, so at most `textInterior` of them may. A changed
 *   text colour leaves no interior pixels at all, which also counts as changed.
 */
export const TOLERANCE = {
	delta: 6,
	outsideText: 0.0005,
	textEdges: 0.25,
	textInterior: 0.03,
};

const LAUNCH_ARGS = [
	'--force-color-profile=srgb',
	'--font-render-hinting=none',
	'--disable-lcd-text',
	'--disable-gpu',
];

/**
 * @param {import('@playwright/test').Browser} browser The browser.
 * @param {Object}                             target  One of TARGETS.
 * @return {Promise<import('@playwright/test').Page>} The loaded page.
 */
async function openTarget( browser, target ) {
	const context = await browser.newContext( {
		viewport: target.viewport,
		deviceScaleFactor: target.scale,
		colorScheme: 'dark',
		reducedMotion: 'reduce',
		locale: 'en-US',
		timezoneId: 'UTC',
	} );
	const outside = [];
	await context.route( '**/*', ( route ) => {
		const url = route.request().url();
		if ( url.startsWith( pathToFileURL( root ).href + '/' ) ) {
			return route.continue();
		}
		outside.push( url );
		return route.abort();
	} );
	const page = await context.newPage();
	const url = pathToFileURL( join( LISTING_DIR, 'src', target.source ) ).href;
	await page.goto( url + ( target.query || '' ), { waitUntil: 'load' } );
	if ( target.banner ) {
		const loaded = await page.evaluate( async () => {
			await Promise.all( [
				document.fonts.load(
					'800 66px "Plus Jakarta Sans"',
					'Zero Spam'
				),
				document.fonts.load(
					'600 27px "Plus Jakarta Sans"',
					'for WordPress'
				),
			] );
			await document.fonts.ready;
			return (
				document.fonts.check(
					'800 66px "Plus Jakarta Sans"',
					'Zero Spam'
				) && document.querySelector( '.mark img' ).naturalWidth > 0
			);
		} );
		if ( ! loaded ) {
			throw new Error(
				`${ target.file }: the font or the mark did not load`
			);
		}
	}
	if ( outside.length ) {
		throw new Error(
			`${ target.file }: requests outside the checkout: ${ outside.join( ', ' ) }`
		);
	}
	return page;
}

const channel = ( value ) => {
	const v = value / 255;
	return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
};
const luminance = ( [ r, g, b ] ) =>
	0.2126 * channel( r ) + 0.7152 * channel( g ) + 0.0722 * channel( b );

/**
 * Measures each text line against the pixels really behind it: the line's
 * colour, with its transparency, against every pixel of its box with the text
 * hidden. Also gives the mark's horizontal position.
 *
 * @param {import('@playwright/test').Page} page   The banner page.
 * @param {Object}                          target One of TARGETS.
 * @return {Promise<Object>} `lines` (text, box in output pixels, contrast) and `mark`.
 */
async function measureText( page, target ) {
	const lines = await page.evaluate( () =>
		[ ...document.querySelectorAll( '[data-text]' ) ].map( ( element ) => {
			const range = document.createRange();
			range.selectNodeContents( element );
			const box = range.getBoundingClientRect();
			let opacity = 1;
			for ( let node = element; node; node = node.parentElement ) {
				opacity *= Number( window.getComputedStyle( node ).opacity );
			}
			return {
				text: element.textContent,
				color: window.getComputedStyle( element ).color,
				opacity,
				left: box.left,
				right: box.right,
				top: box.top,
				bottom: box.bottom,
			};
		} )
	);
	const mark = await page.evaluate( ( scale ) => {
		const box = document
			.querySelector( '.mark img' )
			.getBoundingClientRect();
		return { left: box.left * scale, right: box.right * scale };
	}, target.scale );
	await page.addStyleTag( {
		content: '[data-text]{color:transparent!important}',
	} );
	const shot = ( await page.screenshot( { type: 'png' } ) ).toString(
		'base64'
	);
	const results = [];
	const scale = target.scale;
	for ( const line of lines ) {
		const box = {
			x: Math.floor( line.left * scale ),
			y: Math.floor( line.top * scale ),
			width: Math.ceil( ( line.right - line.left ) * scale ),
			height: Math.ceil( ( line.bottom - line.top ) * scale ),
		};
		const pixels = await page.evaluate(
			async ( { data, area } ) => {
				const image = new window.Image();
				image.src = `data:image/png;base64,${ data }`;
				await image.decode();
				const canvas = document.createElement( 'canvas' );
				canvas.width = image.width;
				canvas.height = image.height;
				const context = canvas.getContext( '2d' );
				context.drawImage( image, 0, 0 );
				return Array.from(
					context.getImageData(
						area.x,
						area.y,
						area.width,
						area.height
					).data
				);
			},
			{ data: shot, area: box }
		);
		const [ red, green, blue, alpha = 1 ] = line.color
			.match( /[\d.]+/g )
			.map( Number );
		// What the text really paints: its colour blended over each pixel.
		const paint = alpha * line.opacity;
		let contrast = Infinity;
		for ( let i = 0; i < pixels.length; i += 4 ) {
			const back = [ pixels[ i ], pixels[ i + 1 ], pixels[ i + 2 ] ];
			const text = luminance(
				[ red, green, blue ].map(
					( value, c ) => paint * value + ( 1 - paint ) * back[ c ]
				)
			);
			const under = luminance( back );
			contrast = Math.min(
				contrast,
				( Math.max( text, under ) + 0.05 ) /
					( Math.min( text, under ) + 0.05 )
			);
		}
		results.push( {
			text: line.text,
			rgb: [ red, green, blue ],
			left: line.left * scale,
			right: line.right * scale,
			top: line.top * scale,
			bottom: line.bottom * scale,
			contrast,
		} );
	}
	return { lines: results, mark };
}

/**
 * @param {string} outDir Where the PNGs go.
 * @return {Promise<Object>} The written paths and, per banner, its measurements.
 */
export async function renderListingAssets( outDir = LISTING_DIR ) {
	mkdirSync( outDir, { recursive: true } );
	const browser = await chromium.launch( { args: LAUNCH_ARGS } );
	const measurements = {};
	try {
		for ( const target of TARGETS ) {
			const page = await openTarget( browser, target );
			const png = await page.screenshot( {
				type: 'png',
				scale: 'device',
			} );
			writeFileSync( join( outDir, target.file ), png );
			if ( target.banner ) {
				measurements[ target.file ] = await measureText( page, target );
			}
			await page.context().close();
		}
	} finally {
		await browser.close();
	}
	return measurements;
}

/**
 * Compares two renders of a banner, pixel by pixel.
 *
 * @param {Buffer}   expected The committed PNG.
 * @param {Buffer}   actual   The fresh PNG.
 * @param {Object[]} lines    The text lines (box in output pixels, `rgb`), from
 *                            the fresh render's measurements.
 * @return {Promise<Object>} The share of differing pixels outside the text and,
 *                           per line, at the glyph edges and interiors, and
 *                           whether all are within TOLERANCE.
 */
export async function compareBanner( expected, actual, lines ) {
	const browser = await chromium.launch( { args: LAUNCH_ARGS } );
	try {
		const page = await browser.newPage();
		const counts = await page.evaluate(
			async ( { pngs, boxes, delta } ) => {
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
						);
					} )
				);
				if ( a.width !== b.width || a.height !== b.height ) {
					return null;
				}
				const at = ( x, y ) => ( y * a.width + x ) * 4;
				const moved = ( i ) =>
					[ 0, 1, 2 ].some(
						( c ) =>
							Math.abs( a.data[ i + c ] - b.data[ i + c ] ) >
							delta
					);
				const isText = ( x, y, rgb ) =>
					[ 0, 1, 2 ].every(
						( c ) =>
							Math.abs( a.data[ at( x, y ) + c ] - rgb[ c ] ) <= 3
					);
				const inBox = ( x, y, box ) =>
					x >= box.left &&
					x < box.right &&
					y >= box.top &&
					y < box.bottom;
				const outside = { moved: 0, total: 0 };
				const perLine = boxes.map( () => ( {
					moved: 0,
					total: 0,
					interiorMoved: 0,
					interior: 0,
				} ) );
				for ( let y = 0; y < a.height; y++ ) {
					for ( let x = 0; x < a.width; x++ ) {
						const i = at( x, y );
						const line = boxes.findIndex( ( box ) =>
							inBox( x, y, box )
						);
						if ( line < 0 ) {
							outside.total++;
							outside.moved += moved( i ) ? 1 : 0;
							continue;
						}
						const count = perLine[ line ];
						count.total++;
						count.moved += moved( i ) ? 1 : 0;
						let interior = x > 0 && y > 0;
						for ( let dy = -1; interior && dy <= 1; dy++ ) {
							for ( let dx = -1; interior && dx <= 1; dx++ ) {
								interior =
									x + dx < a.width &&
									y + dy < a.height &&
									isText( x + dx, y + dy, boxes[ line ].rgb );
							}
						}
						if ( interior ) {
							count.interior++;
							count.interiorMoved += moved( i ) ? 1 : 0;
						}
					}
				}
				return { outside, perLine };
			},
			{
				pngs: [ expected, actual ].map( ( png ) =>
					png.toString( 'base64' )
				),
				boxes: lines.map( ( line ) => ( {
					left: Math.floor( line.left ),
					right: Math.ceil( line.right ),
					top: Math.floor( line.top ),
					bottom: Math.ceil( line.bottom ),
					rgb: line.rgb,
				} ) ),
				delta: TOLERANCE.delta,
			}
		);
		if ( ! counts ) {
			return { outside: 1, lines: [], within: false };
		}
		const outside = counts.outside.moved / counts.outside.total;
		const perLine = counts.perLine.map( ( count, index ) => ( {
			text: lines[ index ].text,
			edges: count.moved / count.total,
			interior: count.interiorMoved / Math.max( 1, count.interior ),
			interiorPixels: count.interior,
		} ) );
		return {
			outside,
			lines: perLine,
			within:
				outside <= TOLERANCE.outsideText &&
				perLine.every(
					( line ) =>
						line.interiorPixels > 0 &&
						line.edges <= TOLERANCE.textEdges &&
						line.interior <= TOLERANCE.textInterior
				),
		};
	} finally {
		await browser.close();
	}
}

/**
 * Strict check: a fresh render equals every committed PNG byte for byte.
 *
 * @return {Promise<string[]>} The files that differ.
 */
export async function staleFiles() {
	const outDir = mkdtempSync( join( tmpdir(), 'zero-spam-listing-' ) );
	try {
		await renderListingAssets( outDir );
		return TARGETS.map( ( target ) => target.file ).filter(
			( file ) =>
				! readFileSync( join( LISTING_DIR, file ) ).equals(
					readFileSync( join( outDir, file ) )
				)
		);
	} finally {
		rmSync( outDir, { recursive: true, force: true } );
	}
}

const say = ( line ) => process.stdout.write( `${ line }\n` );

if (
	process.argv[ 1 ] &&
	realpathSync( process.argv[ 1 ] ) ===
		realpathSync( fileURLToPath( import.meta.url ) )
) {
	if ( process.argv.includes( '--check' ) ) {
		const stale = await staleFiles();
		for ( const file of stale ) {
			say( `differs from a fresh render: ${ file }` );
		}
		say(
			stale.length
				? 'Listing art is stale: run node bin/render-listing-assets.mjs and commit it.'
				: `All ${ TARGETS.length } PNGs equal a fresh render byte for byte.`
		);
		process.exit( stale.length ? 1 : 0 );
	}
	const out = process.argv.find( ( arg ) => arg.startsWith( '--out=' ) );
	const measurements = await renderListingAssets(
		out ? resolve( out.slice( 6 ) ) : LISTING_DIR
	);
	for ( const target of TARGETS ) {
		say( `rendered ${ target.file }` );
	}
	if ( process.argv.includes( '--measure' ) ) {
		for ( const [ file, { lines, mark } ] of Object.entries(
			measurements
		) ) {
			say(
				`${ file }  mark  x ${ mark.left.toFixed( 1 ) }-${ mark.right.toFixed( 1 ) }`
			);
			for ( const line of lines ) {
				say(
					`${ file }  "${ line.text }"  x ${ line.left.toFixed( 1 ) }-${ line.right.toFixed( 1 ) }  y ${ line.top.toFixed( 1 ) }-${ line.bottom.toFixed( 1 ) }  contrast ${ line.contrast.toFixed( 2 ) }:1`
				);
			}
		}
	}
}
