#!/usr/bin/env node
/**
 * Renders the WordPress.org listing art in .wordpress-org/ from the HTML sources
 * in .wordpress-org/src/ with Playwright's Chromium.
 *
 * Usage: node bin/render-listing-assets.mjs [--out=<directory>] [--measure]
 *   --out      write the PNGs somewhere else (default .wordpress-org/)
 *   --measure  print each banner's text position and worst-case contrast
 *
 * Reproducible on one machine: no timestamps, a pinned browser, sRGB output, no
 * LCD text or hinting, and no network (every request outside this checkout
 * stops the render). macOS font rendering differs from other platforms, so the
 * committed files are rendered on macOS; assets/src/admin/test/listing-assets.test.js
 * compares a fresh render with them.
 */

import { mkdirSync, writeFileSync } from 'node:fs';
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
 * colour against every pixel of its box with the text hidden.
 *
 * @param {import('@playwright/test').Page} page   The banner page.
 * @param {Object}                          target One of TARGETS.
 * @return {Promise<Object[]>} Per line: text, box in output pixels, contrast.
 */
async function measureText( page, target ) {
	const lines = await page.evaluate( () =>
		[ ...document.querySelectorAll( '[data-text]' ) ].map( ( element ) => {
			const range = document.createRange();
			range.selectNodeContents( element );
			const box = range.getBoundingClientRect();
			return {
				text: element.textContent,
				color: getComputedStyle( element ).color,
				left: box.left,
				right: box.right,
				top: box.top,
				bottom: box.bottom,
			};
		} )
	);
	await page.addStyleTag( {
		content: '[data-text]{color:transparent!important}',
	} );
	const shot = ( await page.screenshot( { type: 'png' } ) ).toString(
		'base64'
	);
	const results = [];
	for ( const line of lines ) {
		const scale = target.scale;
		const box = {
			x: Math.floor( line.left * scale ),
			y: Math.floor( line.top * scale ),
			width: Math.ceil( ( line.right - line.left ) * scale ),
			height: Math.ceil( ( line.bottom - line.top ) * scale ),
		};
		const pixels = await page.evaluate(
			async ( { data, area } ) => {
				const image = new Image();
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
		const text = luminance(
			line.color.match( /\d+/g ).slice( 0, 3 ).map( Number )
		);
		let contrast = Infinity;
		for ( let i = 0; i < pixels.length; i += 4 ) {
			const back = luminance( [
				pixels[ i ],
				pixels[ i + 1 ],
				pixels[ i + 2 ],
			] );
			contrast = Math.min(
				contrast,
				( Math.max( text, back ) + 0.05 ) /
					( Math.min( text, back ) + 0.05 )
			);
		}
		results.push( {
			text: line.text,
			left: line.left * scale,
			right: line.right * scale,
			top: line.top * scale,
			bottom: line.bottom * scale,
			contrast,
		} );
	}
	return results;
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

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const out = process.argv.find( ( arg ) => arg.startsWith( '--out=' ) );
	const measurements = await renderListingAssets(
		out ? resolve( out.slice( 6 ) ) : LISTING_DIR
	);
	for ( const target of TARGETS ) {
		console.log( `rendered ${ target.file }` );
	}
	if ( process.argv.includes( '--measure' ) ) {
		for ( const [ file, lines ] of Object.entries( measurements ) ) {
			for ( const line of lines ) {
				console.log(
					`${ file }  "${ line.text }"  x ${ line.left.toFixed( 1 ) }-${ line.right.toFixed( 1 ) }  y ${ line.top.toFixed( 1 ) }-${ line.bottom.toFixed( 1 ) }  contrast ${ line.contrast.toFixed( 2 ) }:1`
				);
			}
		}
	}
}
