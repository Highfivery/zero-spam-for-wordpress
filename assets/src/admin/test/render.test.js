/**
 * Renders the sprite and the shared styles in Chromium (Playwright's, the
 * same one the e2e suite uses; install it with npx playwright install
 * chromium) and checks what only a browser can tell:
 *
 * - <use href="#zs-mark"> draws the same mark as an inline copy of the symbol
 *   (page CSS cannot reach inside a <use> instance, so the animation layers
 *   must be hidden by attributes, not by logo.css);
 * - the focus ring and the eyebrow inside a dark block take the block's
 *   colours;
 * - data-zs-theme on a root wins over one on an ancestor.
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

const srcDir = join( __dirname, '..', '..' );
const read = ( file ) => readFileSync( join( srcDir, file ), 'utf8' );

const css = [
	'admin/styles/tokens.css',
	'admin/styles/base.css',
	'admin/styles/logo.css',
]
	.map( read )
	.join( '\n' );
const sprite = read( 'admin/icons/sprite.svg' );
const [ , viewBox, symbolBody ] = sprite.match(
	/<symbol id="zs-mark" viewBox="([^"]+)">([\s\S]*?)<\/symbol>/
);
const [ , , vbWidth, vbHeight ] = viewBox.split( ' ' ).map( Number );

/**
 * @param {string} body Markup for the page body.
 * @return {string} A complete document with the sprite and the styles.
 */
const page = ( body ) =>
	`<!doctype html><html><head><meta charset="utf-8"><style>${ css }</style></head>` +
	`<body style="margin:0;background:#fff">${ sprite }${ body }</body></html>`;

let browser;
let context;

beforeAll( async () => {
	try {
		browser = await chromium.launch();
	} catch ( error ) {
		throw new Error(
			`Chromium for Playwright is missing. Run: npx playwright install chromium\n${ error.message }`
		);
	}
	context = await browser.newContext( {
		viewport: { width: 640, height: 640 },
		deviceScaleFactor: 1,
	} );
}, 60000 );

afterAll( async () => {
	await browser?.close();
} );

describe( 'the mark from the sprite', () => {
	it.each( [ 32, 256 ] )(
		'<use href="#zs-mark"> matches the inline copy at %ipx',
		async ( width ) => {
			const height = Math.round( ( width * vbHeight ) / vbWidth );
			const box = `display:block;width:${ width }px;height:${ height }px`;
			const tab = await context.newPage();
			await tab.setContent(
				page(
					`<span class="zs-mark" id="use" style="${ box }"><svg viewBox="${ viewBox }" aria-hidden="true"><use href="#zs-mark"/></svg></span>` +
						`<span class="zs-mark" id="inline" style="${ box }"><svg viewBox="${ viewBox }" aria-hidden="true">${ symbolBody }</svg></span>`
				)
			);
			const shots = await Promise.all(
				[ '#use', '#inline' ].map( async ( selector ) =>
					( await tab.locator( selector ).screenshot() ).toString(
						'base64'
					)
				)
			);
			const result = await tab.evaluate( async ( pngs ) => {
				const pixels = await Promise.all(
					pngs.map( async ( png ) => {
						const img = new window.Image();
						img.src = `data:image/png;base64,${ png }`;
						await img.decode();
						const canvas = document.createElement( 'canvas' );
						canvas.width = img.width;
						canvas.height = img.height;
						const ctx = canvas.getContext( '2d' );
						ctx.drawImage( img, 0, 0 );
						return ctx.getImageData( 0, 0, img.width, img.height )
							.data;
					} )
				);
				let differing = 0;
				let painted = 0;
				for ( let i = 0; i < pixels[ 0 ].length; i += 4 ) {
					const delta = Math.max(
						Math.abs( pixels[ 0 ][ i ] - pixels[ 1 ][ i ] ),
						Math.abs( pixels[ 0 ][ i + 1 ] - pixels[ 1 ][ i + 1 ] ),
						Math.abs( pixels[ 0 ][ i + 2 ] - pixels[ 1 ][ i + 2 ] )
					);
					if ( delta > 16 ) {
						differing++;
					}
					if (
						pixels[ 1 ][ i ] < 250 ||
						pixels[ 1 ][ i + 1 ] < 250
					) {
						painted++;
					}
				}
				return {
					total: pixels[ 0 ].length / 4,
					differing,
					painted,
					sameSize: pixels[ 0 ].length === pixels[ 1 ].length,
				};
			}, shots );
			await tab.close();

			expect( result.sameSize ).toBe( true );
			// The inline copy really drew the mark (most of the box is bricks).
			expect( result.painted / result.total ).toBeGreaterThan( 0.5 );
			// At most 1% of the pixels differ by more than 16/255 per channel.
			expect( result.differing / result.total ).toBeLessThanOrEqual(
				0.01
			);
		},
		30000
	);
} );

describe( 'dark blocks and theme nesting', () => {
	it( 'uses the peach focus ring and eyebrow inside a dark block', async () => {
		const tab = await context.newPage();
		await tab.setContent(
			page(
				`<div class="zs-app">` +
					`<button id="outside">Outside</button>` +
					`<div class="zs-dark-block" style="background:var(--zs-enh-bg)">` +
					`<p class="zs-eyebrow" id="eyebrow">Enhanced</p><button id="inside">Inside</button></div>` +
					`<p class="zs-eyebrow" id="eyebrow-light">Light</p></div>`
			)
		);
		const outlineOf = async ( id ) => {
			await tab.keyboard.press( 'Tab' );
			return tab.evaluate( ( el ) => {
				const style = window.getComputedStyle(
					document.getElementById( el )
				);
				return {
					focused: document.activeElement.id,
					color: style.outlineColor,
					width: style.outlineWidth,
				};
			}, id );
		};
		const outside = await outlineOf( 'outside' );
		const inside = await outlineOf( 'inside' );
		const eyebrows = await tab.evaluate( () =>
			[ 'eyebrow', 'eyebrow-light' ].map(
				( id ) =>
					window.getComputedStyle( document.getElementById( id ) )
						.color
			)
		);
		await tab.close();

		expect( outside ).toEqual( {
			focused: 'outside',
			color: 'rgb(190, 0, 0)',
			width: '2px',
		} );
		expect( inside ).toEqual( {
			focused: 'inside',
			color: 'rgb(255, 161, 124)',
			width: '2px',
		} );
		expect( eyebrows ).toEqual( [
			'rgb(255, 161, 124)',
			'rgb(122, 90, 94)',
		] );
	} );

	it( 'lets the attribute on a root win over the one on an ancestor', async () => {
		const tab = await context.newPage();
		await tab.setContent(
			page(
				`<div data-zs-theme="dark">` +
					`<div class="zs-app" id="inherits"></div>` +
					`<div class="zs-app" id="light" data-zs-theme="light"></div>` +
					`</div><div class="zs-app" id="own" data-zs-theme="dark"></div>`
			)
		);
		const backgrounds = await tab.evaluate( () =>
			[ 'inherits', 'light', 'own' ].map( ( id ) =>
				window
					.getComputedStyle( document.getElementById( id ) )
					.getPropertyValue( '--zs-bg' )
					.trim()
			)
		);
		await tab.close();

		expect( backgrounds ).toEqual( [ '#0f0203', '#f7f3f3', '#0f0203' ] );
	} );
} );
