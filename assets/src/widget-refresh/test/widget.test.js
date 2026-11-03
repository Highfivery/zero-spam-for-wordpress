/**
 * The Refresh button in a real browser (Playwright's Chromium): the module
 * sources are served as they are, the REST routes are answered by the test,
 * and the widget markup mirrors includes/templates/dashboard-widget.php
 * (its data-zs-field hooks).
 *
 * Checks: the numbers, the sparkline and the latest detections are updated;
 * "Data refreshed." reaches the polite live region (AGENTS.md §8: 5.x only
 * swapped an icon); failures are announced; the button is busy while loading;
 * visitor data is written as text; the nonce is sent.
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { sparkPaths } from '../model';

const srcDir = join( __dirname, '..' );
const ORIGIN = 'http://zs.test';

const widgetHtml = `
<div class="zs-widget" data-zs-widget="site" data-zs-theme="auto">
	<p class="zs-w-figure"><span data-zs-field="total">5</span> spam blocked in the last 30 days</p>
	<p class="zs-w-delta" data-zs-field="delta" data-zs-direction="up">Last 7 days: 5, up 5 from the 7 days before.</p>
	<figure><svg aria-hidden="true"><path data-zs-field="spark-area" d=""/><path data-zs-field="spark-line" d=""/></svg>
	<figcaption data-zs-field="spark-text">old</figcaption></figure>
	<ul data-zs-field="latest"><li><span>Comment</span><span>192.0.2.9</span><time class="zs-w-when" datetime="2026-10-25T10:00:00+00:00">2 hours ago</time></li></ul>
	<p data-zs-field="empty">No spam detected yet.</p>
	<button type="button" data-zs-refresh>Refresh</button>
	<p role="status" aria-live="polite" data-zs-field="status"></p>
</div>`;

const config = {
	root: `${ ORIGIN }/?rest_route=/zero-spam/v1/`,
	nonce: 'test-nonce',
	skip: [ 'rescue' ],
	days: 30,
	latest: 3,
	locale: 'en-US',
	i18n: {
		deltaUp: 'Last 7 days: %1$s, up %2$s from the 7 days before.',
		deltaDown: 'Last 7 days: %1$s, down %2$s from the 7 days before.',
		deltaSame: 'Last 7 days: %s, the same as the 7 days before.',
		percent: '%s%%',
		spark: 'Spam blocked per day over the last 30 days: at most %1$s on one day, %2$s today.',
		empty: 'No spam detected yet.',
		refreshing: 'Refreshing…',
		refreshed: 'Data refreshed.',
		failed: 'Couldn’t refresh the data. Check your connection and try again.',
	},
};

const days = [ ...Array( 23 ).fill( 1 ), ...Array( 7 ).fill( 2 ) ];
const series = {
	days: days.map( ( total, i ) => ( {
		date: `d${ i }`,
		total,
		by_type: {},
	} ) ),
};
const activity = [
	{
		id: 4,
		type: 'rescue',
		type_label: 'Rescue link used',
		ip: '192.0.2.200',
		date_gmt: '2026-10-25T11:59:00+00:00',
	},
	{
		id: 3,
		type: 'comment',
		type_label: '<img src=x onerror="window.pwned=1">',
		ip: '"><b>203.0.113.9</b>',
		date_gmt: '2026-10-25T11:55:00+00:00',
	},
	{
		id: 2,
		type: 'login',
		type_label: 'Login',
		ip: '192.0.2.2',
		// The format #441's route returned (no offset): still UTC.
		date_gmt: '2026-10-25T10:00:00',
	},
];

let browser;

beforeAll( async () => {
	try {
		browser = await chromium.launch();
	} catch ( error ) {
		throw new Error(
			`Chromium for Playwright is missing. Run: npx playwright install chromium\n${ error.message }`
		);
	}
}, 60000 );

afterAll( async () => {
	await browser?.close();
} );

/**
 * Opens the widget with the REST routes answered by `answer`.
 *
 * @param {(url: URL, headers: Object<string, string>) => Promise<{status: number, body: Object}>} answer Answers a REST request.
 * @return {Promise<import('@playwright/test').Page>} Page.
 */
async function open( answer ) {
	// A browser west of UTC: a UTC time read as local would be hours off.
	const page = await (
		await browser.newContext( { timezoneId: 'America/Chicago' } )
	).newPage();
	await page.route( `${ ORIGIN }/**`, async ( route ) => {
		const url = new URL( route.request().url() );
		if ( url.pathname.startsWith( '/src/' ) ) {
			const file = url.pathname.slice( 5 ).replace( /(\.js)?$/, '.js' );
			return route.fulfill( {
				contentType: 'text/javascript',
				body: readFileSync( join( srcDir, file ), 'utf8' ),
			} );
		}
		if ( url.searchParams.has( 'rest_route' ) ) {
			const { status, body } = await answer(
				url,
				route.request().headers()
			);
			return route.fulfill( {
				status,
				contentType: 'application/json',
				body: JSON.stringify( body ),
			} );
		}
		return route.fulfill( {
			contentType: 'text/html; charset=utf-8',
			body: `<!doctype html><html><head><meta charset="utf-8"></head><body>${ widgetHtml }<script type="module">
				import { initWidget } from '/src/widget.js';
				window.controls = initWidget( document.querySelector( '[data-zs-widget]' ), ${ JSON.stringify( config ) }, { now: () => Date.parse( '2026-10-25T12:00:00Z' ) } );
			</script></body></html>`,
		} );
	} );
	await page.goto( `${ ORIGIN }/` );
	await page.waitForFunction( () => window.controls );
	return page;
}

const ok = async ( url ) => ( {
	status: 200,
	body: url.searchParams.get( 'rest_route' ).endsWith( 'stats/timeseries' )
		? series
		: activity,
} );

describe( 'Refresh', { timeout: 20000 }, () => {
	it( 'updates the numbers, the sparkline and the list, and announces "Data refreshed."', async () => {
		const seen = [];
		const page = await open( async ( url, headers ) => {
			seen.push( [
				url.searchParams.get( 'rest_route' ),
				headers[ 'x-wp-nonce' ],
			] );
			return ok( url );
		} );

		await page.click( '[data-zs-refresh]' );
		await page.waitForFunction(
			() =>
				document.querySelector( '[role="status"][aria-live="polite"]' )
					.textContent === 'Data refreshed.'
		);

		expect( await page.textContent( '[data-zs-field="total"]' ) ).toBe(
			'37'
		);
		expect( await page.textContent( '[data-zs-field="delta"]' ) ).toBe(
			'Last 7 days: 14, up 100% from the 7 days before.'
		);
		expect(
			await page.getAttribute( '[data-zs-field="spark-line"]', 'd' )
		).toBe( sparkPaths( days ).line );
		expect(
			await page.getAttribute( '[data-zs-field="spark-area"]', 'd' )
		).toBe( sparkPaths( days ).area );
		expect( await page.textContent( '[data-zs-field="spark-text"]' ) ).toBe(
			'Spam blocked per day over the last 30 days: at most 2 on one day, 2 today.'
		);

		const items = await page.$$eval(
			'[data-zs-field="latest"] li',
			( lis ) =>
				lis.map( ( li ) =>
					[ ...li.children ].map( ( c ) => c.textContent )
				)
		);
		expect( items ).toEqual( [
			[
				'<img src=x onerror="window.pwned=1">',
				'"><b>203.0.113.9</b>',
				'5 minutes ago',
			],
			[ 'Login', '192.0.2.2', '2 hours ago' ],
		] );
		expect(
			await page.$$(
				'[data-zs-field="latest"] img, [data-zs-field="latest"] b'
			)
		).toHaveLength( 0 );
		expect( await page.evaluate( () => window.pwned ) ).toBeUndefined();
		expect( await page.isHidden( '[data-zs-field="empty"]' ) ).toBe( true );
		expect( await page.isVisible( '[data-zs-field="latest"]' ) ).toBe(
			true
		);

		expect(
			seen.map( ( [ route ] ) => route.replace( /^.*v1\//, '' ) ).sort()
		).toEqual( [ 'activity', 'stats/timeseries' ] );
		expect( seen.every( ( [ , nonce ] ) => nonce === 'test-nonce' ) ).toBe(
			true
		);
	} );

	it( 'rewrites the server-rendered times in the same wording as a refresh', async () => {
		const page = await open( ok );

		expect( await page.textContent( 'time.zs-w-when' ) ).toBe(
			'2 hours ago'
		);
		expect( await page.getAttribute( 'time.zs-w-when', 'datetime' ) ).toBe(
			'2026-10-25T10:00:00+00:00'
		);
	} );

	it( 'is busy while loading and ignores clicks meanwhile', async () => {
		let release;
		const gate = new Promise( ( resolve ) => ( release = resolve ) );
		let calls = 0;
		const page = await open( async ( url ) => {
			calls++;
			await gate;
			return ok( url );
		} );

		await page.click( '[data-zs-refresh]' );
		await page.waitForFunction(
			() =>
				document.querySelector( '[role="status"]' ).textContent ===
				'Refreshing…'
		);
		expect(
			await page.getAttribute( '[data-zs-refresh]', 'aria-disabled' )
		).toBe( 'true' );
		expect(
			await page.getAttribute( '[data-zs-widget]', 'data-zs-busy' )
		).toBe( '' );
		// Playwright waits for an aria-disabled button to be enabled; a user's click still lands.
		await page.click( '[data-zs-refresh]', { force: true } );

		release();
		await page.waitForFunction(
			() =>
				document.querySelector( '[role="status"]' ).textContent ===
				'Data refreshed.'
		);
		expect( calls ).toBe( 2 );
		expect(
			await page.getAttribute( '[data-zs-refresh]', 'aria-disabled' )
		).toBeNull();
		expect(
			await page.getAttribute( '[data-zs-widget]', 'data-zs-busy' )
		).toBeNull();
	} );

	it( 'announces a failure and keeps the old numbers', async () => {
		const page = await open( async ( url ) =>
			url.searchParams.get( 'rest_route' ).endsWith( 'activity' )
				? { status: 403, body: { code: 'rest_cookie_invalid_nonce' } }
				: ok( url )
		);

		await page.click( '[data-zs-refresh]' );
		await page.waitForFunction( () =>
			document
				.querySelector( '[role="status"]' )
				.textContent.startsWith( 'Couldn’t refresh' )
		);

		expect(
			await page.getAttribute( '[role="status"]', 'data-zs-error' )
		).toBe( '' );
		expect( await page.textContent( '[data-zs-field="total"]' ) ).toBe(
			'5'
		);
	} );

	it( 'announces the same message again on the next refresh', async () => {
		const page = await open( ok );
		await page.evaluate( () => {
			window.announced = 0;
			const region = document.querySelector( '[role="status"]' );
			new window.MutationObserver( () => {
				if ( region.textContent === 'Data refreshed.' ) {
					window.announced++;
				}
			} ).observe( region, {
				childList: true,
				characterData: true,
				subtree: true,
			} );
		} );

		await page.evaluate( () => window.controls.refresh() );
		await page.waitForFunction( () => window.announced === 1 );
		await page.evaluate( () => window.controls.refresh() );
		await page.waitForFunction( () => window.announced === 2 );

		expect( await page.evaluate( () => window.announced ) ).toBe( 2 );
	} );
} );

describe( 'keyboard focus', { timeout: 20000 }, () => {
	// The widget's styles as built (index.css imports), and WordPress's own a:focus rule, which outranks base.css's :where() ring.
	const css = [
		'../admin/styles/tokens.css',
		'../admin/styles/base.css',
		'widget.css',
	]
		.map( ( file ) => readFileSync( join( srcDir, file ), 'utf8' ) )
		.join( '\n' );
	const core =
		'a:focus{color:#043959;border-radius:2px;box-shadow:0 0 0 1.5px #3858e9;outline:2px solid transparent}';

	it.each( [ 'light', 'dark' ] )(
		'links and Refresh show the Zero Spam ring (%s)',
		async ( theme ) => {
			const page = await ( await browser.newContext() ).newPage();
			await page.setContent(
				`<!doctype html><html><head><meta charset="utf-8"><style>${ core }${ css }</style></head><body>` +
					`<div class="zs-widget" data-zs-theme="${ theme }"><p class="zs-w-level">Protection level: <b>Enhanced · License not valid</b> <a class="zs-w-level-link" href="#l">Check license</a></p>` +
					`<div class="zs-w-foot"><a class="zs-w-link" href="#a">View activity</a><button type="button" class="zs-w-btn">Refresh</button></div></div></body></html>`
			);
			const expected =
				theme === 'dark' ? 'rgb(255, 161, 124)' : 'rgb(190, 0, 0)';

			for ( const selector of [
				'.zs-w-level-link',
				'.zs-w-link',
				'.zs-w-btn',
			] ) {
				await page.keyboard.press( 'Tab' );
				const ring = await page.$eval( selector, ( el ) => {
					const style =
						el.ownerDocument.defaultView.getComputedStyle( el );
					return {
						focused: el.ownerDocument.activeElement === el,
						style: style.outlineStyle,
						width: style.outlineWidth,
						color: style.outlineColor,
						shadow: style.boxShadow,
					};
				} );
				expect( ring ).toEqual( {
					focused: true,
					style: 'solid',
					width: '2px',
					color: expected,
					shadow: 'none',
				} );
			}
		}
	);
} );
