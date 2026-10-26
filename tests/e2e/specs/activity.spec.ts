/**
 * The 6.0 Activity area (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json): the log
 * loads without requests of its own, filters with live counts, sorting
 * (headers and the Location menu), paging past the end, the details
 * drawer (focus in, trap, Previous/Next, Escape returns focus to the row
 * shown), Block IP / Always allow / Delete with Undo, bulk delete with
 * Undo, logged markup shown as text, cards on phones, the live "N new"
 * bar, the Enhanced Network-only filter, and Reports.
 *
 * The REST checks of the legacy Log screen's defects (page past the end,
 * one blocked-IP query per row) live in PHPUnit (test-rest-activity.php);
 * the page clamp is also checked here, on the screen that replaces it.
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { AREA, activityReady, reportsReady, seedNetworkCatches } from '../support/screens';

const ACTIVITY = AREA( 'activity' );

/** Whether focus is inside an element. */
const focusInside = ( page: Page, selector: string ) => page.evaluate( ( sel ) => !! document.querySelector( sel )?.contains( document.activeElement ), selector );

/** Zero Spam REST requests made while a callback runs. */
async function restDuring( page: Page, run: () => Promise< void > ): Promise< string[] > {
	const seen: string[] = [];
	const on = ( r: Request ) => {
		const url = r.url();
		if ( url.includes( 'zero-spam/v1' ) || url.includes( 'zero-spam%2Fv1' ) ) {
			seen.push( `${ r.method() } ${ url }` );
		}
	};
	page.on( 'request', on );
	await run();
	page.off( 'request', on );
	return seen;
}

/** Opens the Activity log and waits for its rows. */
async function openLog( page: Page, extra = '' ) {
	await page.goto( ACTIVITY + extra );
	await activityReady( page );
}

/** The log's rows (table from 1,200px). */
const rows = ( page: Page ) => page.locator( '.zs-log-t tbody tr:not(.zs-empty-row)' );

test.describe( '6.0 Activity', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'the log, the Reports tab and an entry link make no REST request of their own on load', async ( { page, zs } ) => {
		await zs.seedLog( 40 );
		const log = await restDuring( page, async () => {
			await openLog( page );
			await page.waitForTimeout( 1500 );
		} );
		expect( log ).toEqual( [] );
		await expect( rows( page ) ).toHaveCount( 25 );

		const reports = await restDuring( page, async () => {
			await page.goto( AREA( 'activity', 'reports' ) );
			await reportsReady( page );
			await page.waitForTimeout( 1000 );
		} );
		expect( reports ).toEqual( [] );

		const id = ( await zs.logRows() )[ 0 ].log_id;
		const item = await restDuring( page, async () => {
			await page.goto( AREA( 'activity', '', { item: String( id ) } ) );
			await page.locator( '.zs-drawer.zs-details .zs-drawer-f .zs-btn' ).first().waitFor();
		} );
		expect( item ).toEqual( [] );
	} );

	test( 'filters show live counts, change the list and the address, and Clear filters empties them', async ( { page, zs } ) => {
		await zs.seedLog( 30 );
		await zs.seedLog( 4, { type: 'login', ip: '198.51.100.9' } );
		await openLog( page );

		const type = page.getByLabel( 'Filter by type' );
		await expect( type.locator( 'option' ).first() ).toHaveText( 'All types (34)' );
		await expect( type.locator( 'option[value="login"]' ) ).toHaveText( 'Login (4)' );
		await type.selectOption( 'login' );
		await expect( rows( page ) ).toHaveCount( 4 );
		await expect( page ).toHaveURL( /type=login/ );
		await expect( page.locator( '.zs-bulk .zs-badge' ) ).toHaveText( '4 entries' );

		await page.getByLabel( 'Search IPs' ).fill( '203.0.113.' );
		await expect( page.locator( '.zs-log .zs-empty' ) ).toContainText( 'No detections match these filters.' );
		await page.getByRole( 'button', { name: 'Clear filters' } ).click();
		await expect( rows( page ) ).toHaveCount( 25 );
		await expect( page.getByLabel( 'Search IPs' ) ).toBeFocused();
		await expect( page ).not.toHaveURL( /type=/ );

		// Dates come from the address (no date control on the screen).
		await openLog( page, '&after=2000-01-01&before=2000-01-02' );
		await expect( page.locator( '.zs-log .zs-empty' ) ).toContainText( 'No detections match these filters.' );
	} );

	test( 'sorting from the keyboard: column headers set aria-sort, the Location menu works with arrows', async ( { page, zs } ) => {
		await zs.seedLog( 30 );
		await openLog( page );

		const ipHeader = page.locator( 'th', { has: page.locator( '[data-zs-sort="ip"]' ) } );
		await page.locator( '[data-zs-sort="ip"]' ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( ipHeader ).toHaveAttribute( 'aria-sort', 'ascending' );
		await expect( page.locator( 'th', { has: page.locator( '[data-zs-sort="date"]' ) } ) ).toHaveAttribute( 'aria-sort', 'none' );

		const menuButton = page.locator( '[aria-controls="zs-loc-menu"]' );
		await menuButton.focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( menuButton ).toHaveAttribute( 'aria-expanded', 'true' );
		await page.keyboard.press( 'ArrowDown' );
		await expect( page.getByRole( 'menuitemradio', { name: 'Country Z–A' } ) ).toBeFocused();
		await page.keyboard.press( 'Escape' );
		await expect( menuButton ).toBeFocused();
		await expect( menuButton ).toHaveAttribute( 'aria-expanded', 'false' );

		await page.keyboard.press( 'ArrowDown' );
		await page.getByRole( 'menuitemradio', { name: 'Region A–Z' } ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( menuButton ).toBeFocused();
		await expect( page.locator( 'th', { has: menuButton } ) ).toHaveAttribute( 'aria-sort', 'ascending' );
		await expect( page ).toHaveURL( /sort=region%3Aasc|sort=region:asc/ );
	} );

	test( 'a page past the last one shows the last page (5.x showed "No items found")', async ( { page, zs } ) => {
		await zs.seedLog( 60 );
		await openLog( page, '&paged=99' );
		await expect( rows( page ) ).toHaveCount( 10 );
		await expect( page.locator( '.zs-pager [aria-current="page"]' ) ).toHaveText( '3' );
		await expect( page.locator( '.zs-pager' ) ).toContainText( 'Showing 51–60 of 60' );
	} );

	test( 'the details drawer: focus moves in and stays, Alt+Down moves on, Escape returns focus to the row shown', async ( { page, zs } ) => {
		await zs.seedLog( 30 );
		await openLog( page );

		await page.locator( '[data-zs-open]' ).nth( 2 ).focus();
		await page.keyboard.press( 'Enter' );
		const drawer = page.locator( '.zs-drawer.zs-details' );
		await expect( drawer ).toHaveAttribute( 'aria-modal', 'true' );
		expect( await focusInside( page, '.zs-drawer' ) ).toBe( true );
		await expect( drawer.locator( '.zs-detpos' ) ).toHaveText( '3 of 25' );
		await expect( page ).toHaveURL( /item=\d+/ );

		for ( let i = 0; i < 25; i++ ) {
			await page.keyboard.press( 'Tab' );
			expect( await focusInside( page, '.zs-drawer' ), `focus stays in the drawer after ${ i + 1 } Tab presses` ).toBe( true );
		}

		const third = await drawer.locator( '#zs-dr-title' ).textContent();
		await page.keyboard.press( 'Alt+ArrowDown' );
		await expect( drawer.locator( '.zs-detpos' ) ).toHaveText( '4 of 25' );
		await expect( drawer.locator( '#zs-dr-title' ) ).not.toHaveText( third ?? '' );
		const fourthId = await page.evaluate( () => new URL( location.href ).searchParams.get( 'item' ) );

		await page.keyboard.press( 'Escape' );
		await expect( drawer ).toHaveCount( 0 );
		expect( await page.evaluate( () => ( document.activeElement as HTMLElement | null )?.dataset.zsOpen ) ).toBe( fourthId );
		await expect( page ).not.toHaveURL( /item=/ );

		// The first entry has no Previous.
		await page.locator( '[data-zs-open]' ).first().click();
		await expect( drawer.getByRole( 'button', { name: 'Previous detection (Alt+Up)' } ) ).toBeDisabled();
	} );

	test( 'Block IP from the drawer, then Undo removes the block', async ( { page, zs } ) => {
		await zs.seedLog( 5 );
		await openLog( page );
		await page.locator( '[data-zs-open]' ).first().click();
		const ip = ( await page.locator( '#zs-dr-title' ).textContent() ) ?? '';

		await page.locator( '.zs-details .zs-drawer-f' ).getByRole( 'button', { name: 'Block IP' } ).click();
		const form = page.locator( '.zs-drawer.zs-blockform' );
		await expect( form.locator( '#zs-bf-ip' ) ).toHaveValue( ip );
		await form.locator( '#zs-bf-reason' ).fill( 'e2e block from Activity' );
		await form.getByRole( 'button', { name: 'Block IP' } ).click();

		await expect( form ).toHaveCount( 0 );
		await expect( page.locator( '.zs-toasts' ) ).toContainText( `${ ip } blocked until` );
		await expect( page.locator( `[data-zs-block]` ).first() ).toHaveText( 'Edit block' );
		await expect( page.locator( '[data-zs-block]' ).first() ).toBeFocused();

		await page.locator( '.zs-toast-btn' ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( `${ ip } is no longer blocked` );
		await expect( page.locator( '[data-zs-block]' ).first() ).toHaveText( 'Block IP' );
	} );

	test( 'Always allow adds the address to the IP allowlist, with Undo', async ( { page, zs } ) => {
		await zs.seedLog( 3 );
		await openLog( page );
		await page.locator( '[data-zs-open]' ).first().click();
		const ip = ( await page.locator( '#zs-dr-title' ).textContent() ) ?? '';

		await page.getByRole( 'button', { name: 'Always allow' } ).click();
		await expect( page.locator( '.zs-details' ) ).toContainText( 'On your IP allowlist' );
		await expect( page.locator( '.zs-toasts' ) ).toContainText( `${ ip } added to your IP allowlist` );
		expect( JSON.stringify( await zs.options( 'zero-spam-settings' ) ) ).toContain( ip );

		await page.locator( '.zs-toast-btn' ).click();
		await expect( page.locator( '.zs-details' ) ).toContainText( 'Always allow' );
	} );

	test( 'Delete entry and bulk delete, each with Undo', async ( { page, zs } ) => {
		await zs.seedLog( 8 );
		await openLog( page );

		await page.locator( '[data-zs-open]' ).first().click();
		await page.getByRole( 'button', { name: 'Delete entry' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Entry deleted' );
		await expect( rows( page ) ).toHaveCount( 7 );
		expect( await page.evaluate( () => !! ( document.activeElement as HTMLElement | null )?.dataset.zsOpen ) ).toBe( true );
		await page.locator( '.zs-toast-btn' ).click();
		await expect( rows( page ) ).toHaveCount( 8 );

		await page.getByLabel( 'Select all shown' ).check();
		await expect( page.locator( '.zs-selcount' ) ).toHaveText( '8 selected' );
		await page.getByLabel( 'Bulk action' ).selectOption( 'delete' );
		await page.getByRole( 'button', { name: 'Apply' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Deleted 8 entries' );
		await expect( page.locator( '.zs-log .zs-empty' ) ).toContainText( 'Nothing blocked yet' );
		await page.locator( '.zs-toast-btn' ).last().click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '8 entries restored' );
		await expect( rows( page ) ).toHaveCount( 8 );
	} );

	test( 'Block selected IPs: each address once for 7 days, already-blocked ones reported, one Undo removes them all', async ( { page, zs } ) => {
		await zs.seedLog( 2, { ip: '198.51.100.30' } );
		await zs.seedLog( 1, { ip: '198.51.100.31' } );
		await zs.seedLog( 1, { ip: '198.51.100.32' } );
		await zs.block( '198.51.100.32', 'Already blocked' );
		await openLog( page );

		await page.getByLabel( 'Select all shown' ).check();
		await page.getByLabel( 'Bulk action' ).selectOption( 'block' );
		await page.getByRole( 'button', { name: 'Apply' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Blocked 2 IP addresses for 7 days · 1 already blocked' );
		await expect( page.locator( '[data-zs-block]' ) ).toHaveText( [ 'Edit block', 'Edit block', 'Edit block', 'Edit block' ] );

		await page.locator( '.zs-toast-btn' ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '2 blocks removed' );
		await expect( page.locator( '[data-zs-block]', { hasText: 'Block IP' } ) ).toHaveCount( 3 );
	} );

	test( 'logged markup is shown as text and never runs (stored XSS fixture)', async ( { page, zs } ) => {
		// crafted: markup in failed, country_name, region_name, city and page_url; data: in the submission.
		await zs.seedLog( 1, {
			crafted: true,
			data: {
				comment_content: '<img src=x onerror="window.__zsXss=1"><script>window.__zsXss=2</script>',
				comment_author: '</script><svg onload="window.__zsXss=3">',
			},
		} );
		const dialogs: string[] = [];
		page.on( 'dialog', ( d ) => {
			dialogs.push( d.message() );
			d.dismiss();
		} );
		const noXss = async () => {
			await expect( page.locator( '.zs-app img[src="x"], .zs-app svg[onload], .zs-app b:text-is("zs-crafted")' ) ).toHaveCount( 0 );
			expect( await page.evaluate( () => ( window as unknown as { __zsXss?: number } ).__zsXss ) ).toBeUndefined();
		};

		await openLog( page );
		// The row: country and region in the table, as text.
		await expect( rows( page ).first() ).toContainText( '<b>zs-crafted</b>' );
		await noXss();

		await page.locator( '[data-zs-open]' ).first().click();
		const drawer = page.locator( '.zs-drawer.zs-details' );
		// The reason is the failed check made readable (underscores and dashes become spaces), as text.
		await expect( drawer.locator( '.zs-why' ) ).toContainText( '<img src="x" onerror=' );
		await expect( drawer.locator( '.zs-why' ) ).toContainText( '<b>zs crafted</b>' );
		await expect( drawer ).toContainText( '/e2e-comments/?q=<img src="x"' );
		await expect( drawer.locator( '.zs-dkv' ).nth( 1 ) ).toContainText( '<b>zs-crafted</b>' );
		await expect( drawer.locator( '.zs-code' ) ).toContainText( '<img src=x onerror="window.__zsXss=1">' );
		await expect( drawer.locator( '.zs-code' ) ).toContainText( '</script><svg onload="window.__zsXss=3">' );
		await noXss();

		// The same entry preloaded into the page (&item=) is escaped in the inline script too.
		const id = ( await zs.logRows() )[ 0 ].log_id;
		await page.goto( AREA( 'activity', '', { item: String( id ) } ) );
		await expect( page.locator( '.zs-code' ) ).toContainText( '<script>window.__zsXss=2</script>' );
		await noXss();

		// Reports: the crafted country name in By location and Most detections.
		await page.goto( AREA( 'activity', 'reports' ) );
		await reportsReady( page );
		await expect( page.locator( 'section[aria-labelledby="zs-rp-loc"]' ) ).toContainText( '<b>zs-crafted</b>' );
		await noXss();
		expect( dialogs ).toEqual( [] );
	} );

	test( 'on a phone the log is a list of cards whose rows are buttons of at least 44px', async ( { page, zs } ) => {
		await zs.seedLog( 30 );
		await page.setViewportSize( { width: 375, height: 812 } );
		await openLog( page );
		const cards = page.locator( '.zs-cards button.zs-cardmain' );
		await expect( cards ).toHaveCount( 25 );
		await expect( page.locator( '.zs-log-t' ) ).toHaveCount( 0 );
		const box = await cards.first().boundingBox();
		expect( box?.height ?? 0 ).toBeGreaterThanOrEqual( 44 );
		expect( await page.evaluate( () => document.documentElement.scrollWidth ) ).toBeLessThanOrEqual( 375 );
		await page.getByLabel( 'Sort detections' ).selectOption( 'ip:asc' );
		await expect( page ).toHaveURL( /sort=ip/ );
		await cards.first().click();
		await expect( page.locator( '.zs-drawer.zs-details' ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( cards.first() ).toBeFocused();
	} );

	test( 'new detections wait behind "N new · Show", announced, and Show lists them', async ( { page, zs } ) => {
		test.setTimeout( 90_000 );
		await zs.seedLog( 10 );
		await openLog( page );
		await zs.seedLog( 2, { type: 'login', ip: '192.0.2.200' } );
		const bar = page.locator( '.zs-newbar' );
		await expect( bar ).toContainText( '2 new · Show', { timeout: 45_000 } );
		await expect( page.locator( '[data-zs-live="polite"]' ) ).toContainText( '2 new detections' );
		await bar.getByRole( 'button' ).click();
		await expect( bar ).toHaveCount( 0 );
		await expect( page.locator( '.zs-bulk .zs-badge' ) ).toHaveText( '12 entries' );
	} );

	test( 'Enhanced: the Network-only chip counts and filters what the network caught', async ( { page, zs } ) => {
		await zs.seedLog( 20 );
		await seedNetworkCatches( zs );
		await zs.setLevel( 'enhanced' );
		await openLog( page );
		const chip = page.locator( '.zs-netchip' );
		await expect( chip ).toContainText( '15' );
		await chip.click();
		await expect( chip ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( rows( page ) ).toHaveCount( 15 );
		await expect( rows( page ).locator( '.zs-netbadge' ) ).toHaveCount( 15 );
		await expect( page ).toHaveURL( /network=1/ );
	} );

	test( 'Basic: the Network-only chip is not offered', async ( { page, zs } ) => {
		await zs.seedLog( 5 );
		await openLog( page );
		await expect( page.locator( '.zs-netchip' ) ).toHaveCount( 0 );
	} );

	test( 'Reports: one period for every card, a chart read with the keyboard, its table, and blocked addresses', async ( { page, zs } ) => {
		await zs.seedLog( 60, { spacingMinutes: 600 } );
		await zs.block( '203.0.113.1', 'Reports e2e' );
		await page.goto( AREA( 'activity', 'reports' ) );
		await reportsReady( page );

		const hint = page.locator( '.zs-rep-bar .zs-hint' );
		await expect( hint ).toContainText( 'last 30 days · 60 detections' );
		await page.locator( '.zs-rep-bar' ).getByRole( 'button', { name: '7 days' } ).click();
		await expect( hint ).toContainText( 'last 7 days' );
		await expect( page.locator( '.zs-tile .zs-eyebrow' ).first() ).toHaveText( /last 7 days/i );

		await page.locator( '.zs-hours' ).focus();
		const spoken = page.locator( '.zs-cchart-wrap .zs-sr-only[aria-live="polite"]' ).last();
		await expect( spoken ).toContainText( /detection/ );
		await page.keyboard.press( 'Home' );
		await expect( spoken ).toContainText( /12\s?AM/ );
		const hours = page.locator( '.zs-cchart-wrap', { has: page.locator( '.zs-hours' ) } );
		await hours.locator( '.zs-data summary' ).click();
		await expect( hours.locator( '.zs-data tbody tr' ) ).toHaveCount( 24 );

		await expect( page.locator( '.zs-toptbl' ) ).toContainText( 'Blocked' );
		await expect( page.locator( 'section[aria-labelledby="zs-rp-loc"] .zs-flag' ).first() ).toBeVisible();
	} );

	test( 'Reports (Enhanced) adds Enhanced vs Basic from the network-only counts', async ( { page, zs } ) => {
		await zs.seedLog( 20 );
		await seedNetworkCatches( zs );
		await zs.setLevel( 'enhanced' );
		await page.goto( AREA( 'activity', 'reports' ) );
		await reportsReady( page );
		await expect( page.locator( '#zs-rp-ev' ) ).toHaveText( 'Enhanced vs Basic' );
		await expect( page.locator( '#zs-evc-sum' ) ).toContainText( 'caught 15 more' );
	} );
} );

test.describe( '6.0 Activity on a network', { tag: '@multisite' }, () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'with the IP allowlist locked, every drawer action fits inside the drawer at 1280 and 375px', async ( { page, zs } ) => {
		await zs.seedLog( 3 );
		await zs.call( 'POST', 'network-setting', { key: 'ip_whitelist', value: '', locked: true } );

		for ( const size of [
			{ width: 1280, height: 900 },
			{ width: 375, height: 812 },
		] ) {
			await page.setViewportSize( size );
			await openLog( page );
			await page.locator( '[data-zs-open]' ).first().click();
			const footer = page.locator( '.zs-drawer.zs-details .zs-drawer-f' );
			await expect( footer.locator( '.zs-lockbadge' ) ).toContainText( 'Allowlist locked by network' );
			await expect( footer.getByRole( 'button', { name: 'Always allow' } ) ).toHaveCount( 0 );

			const clipped = await page.evaluate( () => {
				const drawer = document.querySelector( '.zs-drawer.zs-details' )!.getBoundingClientRect();
				return Array.from( document.querySelectorAll( '.zs-drawer.zs-details .zs-drawer-f :is(.zs-btn, .zs-badge)' ) )
					.map( ( el ) => {
						const box = el.getBoundingClientRect();
						const inside = box.left >= drawer.left - 1 && box.right <= drawer.right + 1;
						const whole = ( el as HTMLElement ).scrollWidth <= ( el as HTMLElement ).clientWidth + 1;
						return inside && whole ? '' : `${ el.textContent?.trim() } (${ Math.round( box.left ) }–${ Math.round( box.right ) } in ${ Math.round( drawer.left ) }–${ Math.round( drawer.right ) })`;
					} )
					.filter( Boolean );
			} );
			expect( clipped, `at ${ size.width }px` ).toEqual( [] );
			await page.keyboard.press( 'Escape' );
		}
	} );
} );
