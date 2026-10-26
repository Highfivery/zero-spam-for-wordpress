/**
 * The 6.0 admin shell and Overview (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json):
 * loading without extra requests, keyboard paths (skip link, navigation,
 * level chip and popover, the Compare drawer's focus trap, the chart and
 * its table, Live/Pause, toasts with Undo), phones, and the CPU budget of
 * an idle Overview (build plan §6).
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { AREA } from '../support/screens';
import { storageStatePath } from '../support/targets';

const OVERVIEW = AREA( 'overview' );

/**
 * Presses Tab until the focused element matches a selector.
 *
 * @param page     Page.
 * @param selector CSS selector.
 * @param max      Maximum key presses.
 */
async function tabTo( page: Page, selector: string, max = 120 ): Promise< boolean > {
	for ( let i = 0; i < max; i++ ) {
		await page.keyboard.press( 'Tab' );
		if ( await page.evaluate( ( sel ) => !! document.activeElement?.matches( sel ), selector ) ) {
			return true;
		}
	}
	return false;
}

/** Whether the focused element draws the focus ring (a visible outline). */
const focusRing = ( page: Page ) =>
	page.evaluate( () => {
		const style = getComputedStyle( document.activeElement as Element );
		return 'none' !== style.outlineStyle && parseFloat( style.outlineWidth ) >= 2;
	} );

/** Whether focus is inside an element. */
const focusInside = ( page: Page, selector: string ) => page.evaluate( ( sel ) => !! document.querySelector( sel )?.contains( document.activeElement ), selector );

/** Opens the Overview and waits for its data. */
async function openOverview( page: Page ) {
	await page.goto( OVERVIEW );
	await page.locator( '#zs-ov-h' ).waitFor();
	await page.locator( '.zs-zc, .zs-zc-member' ).first().waitFor( { state: 'attached' } );
}

test.describe( '6.0 admin', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'the Overview makes no REST request of its own on load (all preloaded)', async ( { page, zs } ) => {
		await zs.seedLog( 40 );
		const rest: string[] = [];
		const onRequest = ( r: Request ) => {
			const url = r.url();
			// Only Zero Spam's routes: WordPress's own scripts (the command palette) ask for users/me.
			if ( url.includes( 'zero-spam/v1' ) || url.includes( 'zero-spam%2Fv1' ) ) {
				rest.push( `${ r.method() } ${ url }` );
			}
		};
		page.on( 'request', onRequest );
		await openOverview( page );
		await page.locator( '.zs-feed li' ).first().waitFor();
		await page.waitForTimeout( 1500 );
		page.off( 'request', onRequest );
		expect( rest ).toEqual( [] );
	} );

	test( 'the menu sits below Comments with six pages, and each page shows its area in the shell', async ( { page } ) => {
		await page.goto( OVERVIEW );
		const menu = page.locator( '#adminmenu > li' );
		const ids = await menu.evaluateAll( ( items ) => items.map( ( li ) => li.id ) );
		const comments = ids.indexOf( 'menu-comments' );
		expect( comments ).toBeGreaterThan( -1 );
		expect( ids[ comments + 1 ] ).toBe( 'toplevel_page_zero-spam' );
		await expect( page.locator( '#toplevel_page_zero-spam .wp-submenu a' ) ).toHaveText( [ 'Overview', 'Activity', 'Rules', 'Protection', 'Settings', 'Help' ] );

		for ( const area of [ 'activity', 'rules', 'protection', 'settings', 'help' ] ) {
			await page.goto( AREA( area ) );
			await expect( page.locator( '#zerospam-app' ) ).toHaveAttribute( 'data-area', area );
			await expect( page.locator( '#zs-area-h' ) ).toBeVisible();
			await expect( page.locator( `.zs-side .zs-nav a[aria-current="page"]` ) ).toHaveAttribute( 'href', new RegExp( `page=zero-spam-${ area }$` ) );
		}
	} );

	test( 'skip link and sidebar navigation work from the keyboard with a visible focus ring', async ( { page } ) => {
		await openOverview( page );
		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );

		expect( await tabTo( page, '.zs-skip' ), 'Tab reaches the skip link' ).toBe( true );
		await expect( page.locator( '.zs-skip' ) ).toBeInViewport();
		await page.keyboard.press( 'Enter' );
		expect( await page.evaluate( () => document.activeElement?.id ) ).toBe( 'zs-content' );

		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );
		expect( await tabTo( page, '.zs-side .zs-nav a[href*="zero-spam-activity"]' ), 'Tab reaches Activity in the sidebar' ).toBe( true );
		expect( await focusRing( page ) ).toBe( true );
		await page.keyboard.press( 'Enter' );
		await page.waitForURL( /page=zero-spam-activity/ );
		await expect( page.locator( '#zerospam-app' ) ).toHaveAttribute( 'data-area', 'activity' );
	} );

	test( 'Upgrade opens Compare levels: focus moves in, stays in, Escape closes and returns it', async ( { page } ) => {
		await openOverview( page );
		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );
		expect( await tabTo( page, '.zs-topbar .zs-lvl-up' ) ).toBe( true );
		await page.keyboard.press( 'Enter' );

		const drawer = page.locator( '.zs-drawer.zs-compare' );
		await expect( drawer ).toBeVisible();
		await expect( drawer ).toHaveAttribute( 'aria-modal', 'true' );
		await expect( drawer.getByRole( 'heading', { level: 2 } ) ).toHaveText( /Protect smarter/ );
		expect( await focusInside( page, '.zs-drawer' ) ).toBe( true );
		await expect( drawer.getByRole( 'link', { name: /View plans on zerospam\.org/ } ) ).toHaveAttribute( 'href', /zerospam\.org\/pricing\/\?utm_source=plugin&utm_medium=compare-header/ );
		await expect( drawer ).not.toContainText( /\$\d/ );

		for ( let i = 0; i < 30; i++ ) {
			await page.keyboard.press( 'Tab' );
			expect( await focusInside( page, '.zs-drawer' ), `focus stays in the drawer after ${ i + 1 } Tab presses` ).toBe( true );
		}
		await page.keyboard.press( 'Shift+Tab' );
		expect( await focusInside( page, '.zs-drawer' ) ).toBe( true );

		await page.keyboard.press( 'Escape' );
		await expect( drawer ).toHaveCount( 0 );
		expect( await page.evaluate( () => document.activeElement?.classList.contains( 'zs-lvl-up' ) ) ).toBe( true );
	} );

	test( 'Enhanced: the level chip opens the license popover; Escape closes it and keeps focus', async ( { page, zs } ) => {
		await zs.seedLog( 20 );
		await zs.seedLog( 7, { type: 'blocked', failed: 'zero_spam', ipPrefix: '192.0.2.', spacingMinutes: 240 } );
		await zs.setLevel( 'enhanced' );
		await openOverview( page );
		await expect( page.locator( '.zs-zc-member' ) ).toContainText( 'Enhanced · Active' );
		// The network-only detections: hero module, sub-line and Basic vs Enhanced.
		await expect( page.locator( '.zs-hn-count b' ) ).toHaveText( '7' );
		await expect( page.locator( '.zs-hero-sub' ) ).toHaveText( 'The network caught 7.' );
		await expect( page.locator( '.zs-hn-count' ) ).toContainText( 'threats caught by the Zero Spam network · last 30 days' );
		await expect( page.locator( '.zs-ni-vs-row' ).first() ).toContainText( "Your site's own checks" );
		await expect( page.locator( '.zs-ni-vs-row' ).first() ).toContainText( '20' );
		await expect( page.locator( '.zs-ni-vs-row' ).nth( 1 ) ).toContainText( 'Zero Spam network' );
		await expect( page.locator( '.zs-ni-vs-row' ).nth( 1 ) ).toContainText( '7' );
		await expect( page.locator( '.zs-ni-vs-note' ) ).toHaveText( '7 caught by the network, 26% of all threats.' );
		// The license's query usage stays in the membership tile.
		await expect( page.locator( '.zs-membership' ) ).toContainText( '31,200 used' );

		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );
		expect( await tabTo( page, '.zs-topbar .zs-lvl-enh' ) ).toBe( true );
		await page.keyboard.press( 'Enter' );
		const chip = page.locator( '.zs-topbar .zs-lvl-enh' );
		const pop = page.locator( '.zs-topbar .zs-lvl-pop' );
		await expect( chip ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( pop ).toBeVisible();
		await expect( pop ).toContainText( 'Business' );
		await expect( pop ).toContainText( '18,800 of 50,000' );

		await page.keyboard.press( 'Escape' );
		await expect( pop ).toBeHidden();
		await expect( chip ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( chip ).toBeFocused();
	} );

	test( 'the chart is read with the arrow keys and its numbers are also a table', async ( { page, zs } ) => {
		await zs.seedLog( 60, { spacingMinutes: 600 } );
		await openOverview( page );
		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );

		expect( await tabTo( page, '.zs-chartbox' ) ).toBe( true );
		const spoken = page.locator( '.zs-chart-tile .zs-sr-only[aria-live="polite"]' );
		await expect( spoken ).toContainText( /threat/ );
		const today = await spoken.textContent();
		await page.keyboard.press( 'ArrowLeft' );
		await expect( spoken ).not.toHaveText( today ?? '' );
		await page.keyboard.press( 'Home' );
		await expect( page.locator( '.zs-chart .zs-col.zs-hot' ) ).toHaveCount( 1 );
		await expect( page.locator( '.zs-tip' ) ).toBeVisible();

		// The period buttons are toggle buttons.
		const week = page.locator( '.zs-stat' ).nth( 1 );
		await expect( week ).toHaveAttribute( 'aria-pressed', 'true' );
		await page.locator( '.zs-stat' ).nth( 2 ).click();
		await expect( page.locator( '.zs-stat' ).nth( 2 ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( page.locator( '.zs-bandlbl' ) ).toContainText( 'Last 30 days · 60' );

		const summary = page.locator( '.zs-data summary' );
		await summary.focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '.zs-data table tbody tr' ) ).toHaveCount( 30 );
	} );

	test( 'Live/Pause is a keyboard toggle, remembered for the user, with a message', async ( { page } ) => {
		await openOverview( page );
		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );
		expect( await tabTo( page, '.zs-live' ) ).toBe( true );
		await expect( page.locator( '.zs-live' ) ).toHaveAttribute( 'aria-pressed', 'true' );
		await page.keyboard.press( 'Space' );
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Live feed paused' );
		await expect( page.locator( '.zs-live' ) ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( page.locator( '.zs-live' ) ).toContainText( 'Resume' );

		await openOverview( page );
		await expect( page.locator( '.zs-live' ) ).toContainText( 'Paused' );
	} );

	test( 'a fresh install is not called protected: status, headline and an attention item per entry point', async ( { page, zs } ) => {
		await zs.reset( 'defaults' );
		await openOverview( page );
		await expect( page.locator( '#zs-ov-h' ) ).toContainText( 'is not protected yet.' );
		await expect( page.locator( '.zs-hero .zs-pill' ).first() ).toContainText( 'System status: No checks active' );
		const attention = page.locator( '.zs-na' );
		for ( const name of [ 'Comment', 'Login', 'Registration' ] ) {
			await expect( attention ).toContainText( `${ name } protection is off` );
		}
		await expect( attention.getByRole( 'link', { name: 'Turn it on' } ).first() ).toBeVisible();
		// The sidebar status says the same.
		await expect( page.locator( '.zs-hero' ) ).not.toContainText( 'is protected.' );
	} );

	test( 'with one entry point off the site is "partly protected"', async ( { page, zs } ) => {
		await zs.module( 'verify_registrations', false );
		await openOverview( page );
		await expect( page.locator( '#zs-ov-h' ) ).toContainText( 'is partly protected.' );
		await expect( page.locator( '.zs-hero .zs-pill' ).first() ).toContainText( 'Protection not fully on' );
		await expect( page.locator( '.zs-na' ) ).toContainText( 'Registration protection is off' );
	} );

	test( 'a dismissed milestone stays dismissed after a reload', async ( { page, zs } ) => {
		await zs.seedLog( 120 );
		await openOverview( page );
		const milestone = page.locator( '.zs-milestone' );
		await expect( milestone ).toContainText( '100' );
		const saved = page.waitForResponse( ( r ) => 'POST' === r.request().method() && /ui(%2F|\/)prefs/.test( r.url() ) );
		await milestone.getByRole( 'button', { name: 'Dismiss' } ).click();
		await expect( milestone ).toHaveCount( 0 );
		// The save is in before the reload.
		expect( JSON.stringify( await ( await saved ).json() ) ).toContain( 'milestone-100' );
		await openOverview( page );
		await page.locator( '.zs-week' ).waitFor();
		await expect( page.locator( '.zs-milestone' ) ).toHaveCount( 0 );
	} );

	test( 'live feed: a new detection lands on the mark and waits behind "1 new · Show" (reduced motion)', async ( { page, zs } ) => {
		test.setTimeout( 90_000 );
		// One row: the seeder numbers rows newest first, so older seeded rows would count as new.
		await zs.seedLog( 1 );
		await zs.uiPrefs( { live_paused: false } );
		await openOverview( page );
		await page.locator( '.zs-feed li' ).first().waitFor();
		const before = await page.locator( '.zs-feed li' ).count();
		await page.evaluate( () => {
			const w = window as unknown as { __zsLanded: number };
			w.__zsLanded = 0;
			new MutationObserver( ( list ) => {
				for ( const m of list ) {
					if ( 'data-landing' === m.attributeName && ( m.target as Element ).hasAttribute( 'data-landing' ) ) {
						w.__zsLanded++;
					}
				}
			} ).observe( document.body, { subtree: true, attributes: true, attributeFilter: [ 'data-landing' ] } );
		} );
		await zs.seedLog( 1, { type: 'login', failed: 'stop_forum_spam', ipPrefix: '198.51.100.', spacingMinutes: 1 } );
		// One poll: 15 s ± 20 %.
		const show = page.getByRole( 'button', { name: '1 new · Show' } );
		await expect( show ).toBeVisible( { timeout: 25_000 } );
		expect( await page.evaluate( () => ( window as unknown as { __zsLanded: number } ).__zsLanded ) ).toBeGreaterThan( 0 );
		await expect( page.locator( '.zs-feed li' ) ).toHaveCount( before );
		await show.click();
		await expect( page.locator( '.zs-feed li' ).first() ).toContainText( '198.51.100.1' );
		await expect( show ).toHaveCount( 0 );
	} );

	test( 'logged values are printed as text, never as markup', async ( { page, zs } ) => {
		await zs.seedLog( 3, { crafted: true } );
		await openOverview( page );
		await page.locator( '.zs-feed li' ).first().waitFor();
		await page.locator( '.zs-week' ).waitFor();
		await page.waitForTimeout( 500 );
		expect( await page.evaluate( () => ( window as unknown as { __zsXss?: number } ).__zsXss ) ).toBeUndefined();
		expect( await page.locator( '.zs-app img[src="x"], .zs-app b:text-is("zs-crafted")' ).count() ).toBe( 0 );
		// The week tile's top country is the crafted name, shown as plain text.
		await expect( page.locator( '.zs-week' ) ).toContainText( '<b>zs-crafted</b>' );
	} );

	test( 'a long site name wraps inside the hero at 375 px without widening the page', async ( { page, wpcli } ) => {
		const original = wpcli.exec( [ 'option', 'get', 'blogname' ] );
		const long = 'Supercalifragilisticexpialidocious Bakery'; // 40 characters, one 34-character word.
		wpcli.exec( [ 'option', 'update', 'blogname', long ] );
		try {
			await page.setViewportSize( { width: 375, height: 800 } );
			await openOverview( page );
			await expect( page.locator( '#zs-ov-h' ) ).toContainText( long );
			const sizes = await page.evaluate( () => {
				const hero = document.querySelector( '.zs-hero' ) as HTMLElement;
				const h1 = document.querySelector( '#zs-ov-h' ) as HTMLElement;
				return {
					page: document.documentElement.scrollWidth,
					hero: hero.getBoundingClientRect().right,
					h1: h1.getBoundingClientRect().right,
					h1Scroll: h1.scrollWidth - h1.clientWidth,
				};
			} );
			expect( sizes.page ).toBeLessThanOrEqual( 375 );
			expect( sizes.h1 ).toBeLessThanOrEqual( sizes.hero );
			expect( sizes.h1Scroll ).toBeLessThanOrEqual( 0 );
		} finally {
			wpcli.exec( [ 'option', 'update', 'blogname', original ] );
		}
	} );

	test( 'a toast with Undo can be reached and stays while it has focus', async ( { page, zs } ) => {
		test.setTimeout( 60_000 );
		await zs.seedLog( 120 );
		await openOverview( page );
		const milestone = page.locator( '.zs-milestone' );
		await expect( milestone ).toBeVisible();
		await milestone.getByRole( 'button', { name: 'Dismiss' } ).click();
		await expect( milestone ).toHaveCount( 0 );
		expect( await page.evaluate( () => document.activeElement?.id ) ).toBe( 'zs-wk-h' );

		const undo = page.locator( '.zs-toast-btn' );
		await expect( undo ).toBeVisible();
		expect( await tabTo( page, '.zs-toast-btn', 200 ) ).toBe( true );
		// Longer than the toast lasts (10 s): focus holds it open (WCAG 2.2.1).
		await page.waitForTimeout( 12_000 );
		await expect( undo ).toBeVisible();
		const saved = page.waitForResponse( ( r ) => r.url().includes( 'ui%2Fprefs' ) || r.url().includes( 'ui/prefs' ) );
		await page.keyboard.press( 'Enter' );
		await expect( milestone ).toBeVisible();
		await saved;

		await openOverview( page );
		await expect( page.locator( '.zs-milestone' ) ).toBeVisible();
	} );

	test( 'reduced motion: the wall stands still', async ( { page } ) => {
		await openOverview( page );
		await expect( page.locator( '.zs-hero-art' ) ).not.toHaveClass( /zs-drifting|zs-lay-in/ );
	} );

	test( 'phones: one 56px bar, bottom navigation, and the sidebar as a drawer behind More', async ( { page } ) => {
		await page.setViewportSize( { width: 375, height: 812 } );
		await openOverview( page );
		const bar = await page.locator( '.zs-topbar' ).boundingBox();
		expect( bar?.height ).toBe( 56 );
		await expect( page.locator( '.zs-bnav' ) ).toBeVisible();
		await expect( page.locator( '.zs-side' ) ).toBeHidden();

		const more = page.locator( '.zs-bnav button' );
		const box = await more.boundingBox();
		expect( box?.height ).toBeGreaterThanOrEqual( 44 );
		await more.focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '.zs-side' ) ).toBeVisible();
		await expect( page.locator( '.zs-side' ) ).toHaveAttribute( 'aria-modal', 'true' );
		await expect( page.locator( '.zs-side .zs-side-m .zs-lvl' ) ).toBeVisible();
		expect( await page.evaluate( () => document.activeElement?.closest( '.zs-side' ) !== null ) ).toBe( true );
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( '.zs-side' ) ).toBeHidden();
		await expect( more ).toBeFocused();

		for ( const width of [ 375, 320 ] ) {
			await page.setViewportSize( { width, height: 812 } );
			await page.waitForTimeout( 200 );
			const scroll = await page.evaluate( () => document.documentElement.scrollWidth );
			expect( scroll, `no sideways scrolling at ${ width }px` ).toBeLessThanOrEqual( width + 1 );
		}
	} );
} );

test.describe( '6.0 Overview layout stability', () => {
	for ( const [ level, width ] of [
		[ 'basic', 1280 ],
		[ 'basic', 375 ],
		[ 'enhanced', 1280 ],
	] as const ) {
		test( `loading the Overview shifts the layout by at most 0.05 CLS (${ level }, ${ width } px)`, async ( { browser, target, zs }, testInfo ) => {
			await zs.reset( 'baseline', { level } );
			await zs.seedLog( 60 );
			const context = await browser.newContext( {
				baseURL: target.baseURL,
				storageState: storageStatePath( target, 'admin' ),
				extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': target.token },
				viewport: { width, height: 900 },
				reducedMotion: 'reduce',
			} );
			const page = await context.newPage();
			await page.addInitScript( () => {
				const w = window as unknown as { __zsCls: number };
				w.__zsCls = 0;
				new PerformanceObserver( ( list ) => {
					for ( const entry of list.getEntries() as unknown as Array< { value: number; hadRecentInput: boolean } > ) {
						if ( ! entry.hadRecentInput ) {
							w.__zsCls += entry.value;
						}
					}
				} ).observe( { type: 'layout-shift', buffered: true } );
			} );
			await openOverview( page );
			await page.locator( '.zs-feed li' ).first().waitFor();
			await page.waitForTimeout( 2000 );
			const cls = await page.evaluate( () => ( window as unknown as { __zsCls: number } ).__zsCls );
			testInfo.annotations.push( { type: 'perf', description: `CLS ${ cls.toFixed( 4 ) } (${ level }, ${ width } px)` } );
			await context.close();
			expect( cls ).toBeLessThanOrEqual( 0.05 );
		} );
	}
} );

test.describe( '6.0 Overview CPU budget (build plan §6)', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await zs.seedLog( 120 );
	} );

	for ( const [ motion, budget ] of [
		[ 'no-preference', 0.3 ],
		[ 'reduce', 0.05 ],
	] as const ) {
		test( `an idle Overview uses at most ${ budget } s of main-thread tasks in 10 s (${ 'reduce' === motion ? 'reduced motion' : 'animated' })`, async ( { browser, target, zs }, testInfo ) => {
			test.setTimeout( 60_000 );
			await zs.uiPrefs( { live_paused: true } );
			const context = await browser.newContext( {
				baseURL: target.baseURL,
				storageState: storageStatePath( target, 'admin' ),
				extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': target.token },
				viewport: { width: 1280, height: 900 },
				reducedMotion: motion,
			} );
			const page = await context.newPage();
			await openOverview( page );
			// Let the lay-in finish and the drift start.
			await page.waitForTimeout( 2500 );
			await page.mouse.move( 2, 880 );

			const cdp = await context.newCDPSession( page );
			await cdp.send( 'Performance.enable' );
			const task = async () => ( await cdp.send( 'Performance.getMetrics' ) ).metrics.find( ( m ) => 'TaskDuration' === m.name )?.value ?? 0;
			const before = await task();
			await page.waitForTimeout( 10_000 );
			const used = ( await task() ) - before;
			testInfo.annotations.push( { type: 'perf', description: `${ motion }: ${ used.toFixed( 3 ) } s TaskDuration over 10 s` } );
			await context.close();
			expect( used ).toBeLessThanOrEqual( budget );
		} );
	}
} );
