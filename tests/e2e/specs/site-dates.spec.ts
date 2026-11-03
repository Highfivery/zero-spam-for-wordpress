/**
 * Block dates in the site's time zone (data/site-time.js): an exact midnight
 * stays on its day whatever the zones of the site and the browser, summer and
 * winter; the drawer never moves a date it did not change; what is picked in
 * it means that wall clock on the site, also the nights the clocks change;
 * and a wrong browser clock moves nothing, while relative times stay right.
 */

import type { BrowserContext, Page } from '@playwright/test';

import type { ZsApi } from '../support/e2e-api';
import { expect, test } from '../support/fixtures';
import { AREA, emptyRules, rulesReady } from '../support/screens';

interface Block {
	id: number;
	ip: string;
	start: string;
	end: string | null;
}

const IP = '203.0.113.77';
const MINUTE = 60000;

/** Wide enough for the table whatever its breakpoint. */
const VIEWPORT = { width: 1600, height: 900 };

/**
 * Blocks IP through the REST route.
 *
 * @param zs    API client.
 * @param start Start as the form or a client sends it.
 * @param end   End as the form or a client sends it.
 */
const seed = ( zs: ZsApi, start: string, end: string ) => zs.plugin< Block >( 'POST', 'rules/blocks', { kind: 'ip', ip: IP, type: 'temporary', reason: 'E2E dates', start, end } );

/** The row of IP on Blocked IPs. */
const row = ( page: Page ) => page.locator( '.zs-rules-t tbody tr', { hasText: IP } );

/** The block drawer. */
const drawer = ( page: Page ) => page.locator( '.zs-drawer.zs-blockform' );

/**
 * Opens Blocked IPs.
 *
 * @param page Page.
 */
async function openBlocks( page: Page ) {
	await page.goto( AREA( 'rules', 'ips' ) );
	await rulesReady( page );
	await expect( row( page ) ).toBeVisible();
}

/**
 * Opens the drawer of IP on Blocked IPs.
 *
 * @param page Page.
 */
async function openDrawer( page: Page ) {
	await openBlocks( page );
	await page.getByRole( 'button', { name: `Edit block for ${ IP }` } ).click();
	await expect( drawer( page ).locator( '.zs-eyebrow' ) ).toHaveText( 'Edit block' );
}

/**
 * Saves the drawer and waits for the answer.
 *
 * @param page Page.
 */
async function saveDrawer( page: Page ) {
	// apiFetch sends PUT as POST with X-HTTP-Method-Override.
	const answer = page.waitForResponse( ( r ) => /rules(\/|%2F)blocks(\/|%2F)\d+/.test( r.url() ) && 'PUT' === ( r.request().headers()[ 'x-http-method-override' ] ?? r.request().method() ) );
	await drawer( page ).getByRole( 'button', { name: 'Save block' } ).click();
	expect( ( await answer ).status() ).toBe( 200 );
	await expect( drawer( page ) ).toHaveCount( 0 );
}

test.describe( '6.0 dates in the site’s time zone', () => {
	let restore: { timezone_string: string; gmt_offset: number } | null = null;

	/**
	 * Sets the site's time zone until the end of the test.
	 *
	 * @param zs     API client.
	 * @param name   IANA name, or '' for a manual offset.
	 * @param offset Manual offset, in hours.
	 */
	const siteZone = async ( zs: ZsApi, name: string, offset = 0 ) => {
		const previous = await zs.timezone( name, offset );
		restore = restore ?? previous;
	};

	/**
	 * An administrator's page in a browser in a time zone, optionally with a
	 * clock that is off.
	 *
	 * @param newRoleContext The fixture.
	 * @param timezoneId     The browser's time zone.
	 * @param skew           Browser clock minus the real time, in milliseconds.
	 */
	const browserPage = async ( newRoleContext: ( role: 'admin', options: { viewport: { width: number; height: number }; timezoneId: string } ) => Promise< BrowserContext >, timezoneId: string, skew = 0 ) => {
		const context = await newRoleContext( 'admin', { viewport: VIEWPORT, timezoneId } );
		if ( skew ) {
			await context.clock.install( { time: new Date( Date.now() + skew ) } );
		}
		return context.newPage();
	};

	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await emptyRules( zs );
	} );

	test.afterEach( async ( { zs } ) => {
		if ( restore ) {
			await zs.timezone( restore.timezone_string, restore.gmt_offset );
			restore = null;
		}
	} );

	for ( const [ site, offset, browser ] of [
		[ 'UTC', 0, 'America/Los_Angeles' ],
		[ 'America/New_York', 0, 'Asia/Tokyo' ],
		[ 'Asia/Kolkata', 0, 'America/Los_Angeles' ],
		[ '', 5.5, 'Asia/Tokyo' ],
	] as Array< [ string, number, string ] > ) {
		test( `an exact midnight on a site in ${ site || `UTC+${ offset }` }, seen from ${ browser }, stays on its day, in summer and in winter`, async ( { zs, newRoleContext } ) => {
			await siteZone( zs, site, offset );
			const page = await browserPage( newRoleContext, browser );

			const summer = await seed( zs, '2026-07-15', '2099-07-15' );
			await openBlocks( page );
			await expect( row( page ) ).toContainText( 'Jul 15' );
			await expect( row( page ) ).not.toContainText( 'Jul 14' );
			await expect( row( page ) ).toContainText( 'Jul 15, 2099' );
			await zs.plugin( 'DELETE', `rules/blocks/${ summer.id }` );

			await seed( zs, '2025-11-26', '2099-01-15' );
			await openBlocks( page );
			await expect( row( page ) ).toContainText( 'Nov 26, 2025' );
			await expect( row( page ) ).not.toContainText( 'Nov 25' );
			await expect( row( page ) ).toContainText( 'Jan 15, 2099' );
		} );
	}

	test( 'the drawer shows a block’s wall clock, and saving it twice without edits changes nothing, to the second', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'America/New_York' );
		const page = await browserPage( newRoleContext, 'Asia/Tokyo' );
		// The second 01:30 of the night the clocks go back, and a date-only end (23:59:59).
		const seeded = await seed( zs, '2026-11-01T06:30:00Z', '2099-07-15' );
		expect( seeded.end ).toBe( '2099-07-16T03:59:59Z' );

		for ( const save of [ 'first', 'second' ] ) {
			await test.step( `${ save } save`, async () => {
				await openDrawer( page );
				await expect( page.locator( '#zs-bf-start' ) ).toHaveValue( '2026-11-01T01:30' );
				await expect( page.locator( '#zs-bf-end' ) ).toHaveValue( '2099-07-15T23:59' );
				await saveDrawer( page );
				const stored = await zs.plugin< Block >( 'GET', `rules/blocks/${ seeded.id }` );
				expect( stored.start ).toBe( '2026-11-01T06:30:00Z' );
				expect( stored.end ).toBe( '2099-07-16T03:59:59Z' );
			} );
		}
	} );

	test( 'with an exact midnight on a UTC site too, and with a wrong browser clock', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'UTC' );
		const page = await browserPage( newRoleContext, 'America/New_York', 10 * MINUTE );
		const seeded = await seed( zs, '2025-11-26', '2099-01-15' );

		for ( const save of [ 'first', 'second' ] ) {
			await test.step( `${ save } save`, async () => {
				await openDrawer( page );
				await expect( page.locator( '#zs-bf-start' ) ).toHaveValue( '2025-11-26T00:00' );
				await saveDrawer( page );
				const stored = await zs.plugin< Block >( 'GET', `rules/blocks/${ seeded.id }` );
				expect( stored.start ).toBe( '2025-11-26T00:00:00Z' );
				expect( stored.end ).toBe( '2099-01-15T23:59:59Z' );
			} );
		}
	} );

	test( 'a date picked in the drawer is that wall clock on the site: the clocks’ gap moves forward, a repeated time is its first occurrence', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'America/New_York' );
		const page = await browserPage( newRoleContext, 'Asia/Tokyo' );
		const seeded = await seed( zs, '2026-01-15', '2099-01-15' );

		for ( const [ picked, stored ] of [
			[ '2026-07-15T09:30', '2026-07-15T13:30:00Z' ],
			[ '2026-03-08T02:30', '2026-03-08T07:30:00Z' ],
			[ '2026-11-01T01:30', '2026-11-01T05:30:00Z' ],
		] ) {
			await test.step( picked, async () => {
				await openDrawer( page );
				await page.locator( '#zs-bf-start' ).fill( picked );
				await saveDrawer( page );
				const block = await zs.plugin< Block >( 'GET', `rules/blocks/${ seeded.id }` );
				expect( block.start ).toBe( stored );
				expect( block.end, 'The end was not edited.' ).toBe( '2099-01-16T04:59:59Z' );
			} );
		}
	} );

	test( 'an emptied start starts the block now, as the hint says, and keeps the end', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'America/New_York' );
		const page = await browserPage( newRoleContext, 'Asia/Tokyo' );
		// A scheduled block, not in effect yet.
		const seeded = await seed( zs, '2098-01-15', '2099-01-15' );

		await openDrawer( page );
		await expect( drawer( page ) ).toContainText( 'Empty starts the block now.' );
		await page.locator( '#zs-bf-start' ).fill( '' );
		const before = Date.now();
		await saveDrawer( page );
		const stored = await zs.plugin< Block >( 'GET', `rules/blocks/${ seeded.id }` );
		const start = Date.parse( stored.start );
		// Second precision on the server; a minute of slack for clock drift between the host and the container.
		expect( Math.abs( start - before ), `The start is now, not ${ seeded.start }.` ).toBeLessThan( MINUTE );
		expect( stored.end, 'The end was not edited.' ).toBe( seeded.end );
	} );

	test( 'an emptied end is refused in the drawer and changes nothing', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'America/New_York' );
		const page = await browserPage( newRoleContext, 'Asia/Tokyo' );
		const seeded = await seed( zs, '2026-01-15', '2099-01-15' );
		let sent = 0;
		page.on( 'request', ( r ) => {
			if ( /rules(\/|%2F)blocks(\/|%2F)\d+/.test( r.url() ) && 'GET' !== r.method() ) {
				sent++;
			}
		} );

		await openDrawer( page );
		await page.locator( '#zs-bf-end' ).fill( '' );
		await drawer( page ).getByRole( 'button', { name: 'Save block' } ).click();
		await expect( drawer( page ) ).toContainText( 'Choose when the block ends, or make it permanent.' );
		await expect( page.locator( '#zs-bf-end' ) ).toBeFocused();
		await expect( page.locator( '#zs-bf-end' ) ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( sent, 'Nothing was sent.' ).toBe( 0 );
		const stored = await zs.plugin< Block >( 'GET', `rules/blocks/${ seeded.id }` );
		expect( stored.start ).toBe( seeded.start );
		expect( stored.end ).toBe( seeded.end );
	} );

	for ( const [ name, skew, start, end, shows, notShown ] of [
		[ '10 minutes fast', 10 * MINUTE, '2026-07-15T00:05:00', '2099-01-15', 'Jul 15', 'Jul 14' ],
		[ '10 minutes slow', -10 * MINUTE, '2026-01-15', '2099-07-15T23:55:00', 'Jul 15, 2099', 'Jul 16, 2099' ],
	] as Array< [ string, number, string, string, string, string ] > ) {
		test( `a browser clock ${ name } moves no date`, async ( { zs, newRoleContext } ) => {
			await siteZone( zs, 'America/New_York' );
			const page = await browserPage( newRoleContext, 'Europe/Paris', skew );
			await seed( zs, start, end );
			await openBlocks( page );
			await expect( row( page ) ).toContainText( shows );
			await expect( row( page ) ).not.toContainText( notShown );
		} );
	}

	test( 'relative times stay right with a browser clock hours off', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'America/New_York' );
		await zs.seedLog( 1, { ip: IP } );

		for ( const skew of [ 3 * 60 * MINUTE, -3 * 60 * MINUTE ] ) {
			const page = await browserPage( newRoleContext, 'Asia/Tokyo', skew );
			await page.goto( AREA( 'activity' ) );
			await expect( page.locator( '.zs-log-t tbody td.zs-c-w span' ).first() ).toHaveText( /^(Just now|\d+ min ago)$/ );
		}
	} );

	test( 'Activity: the details line says the site’s day, and its Edit block keeps the dates', async ( { zs, newRoleContext } ) => {
		await siteZone( zs, 'America/New_York' );
		const page = await browserPage( newRoleContext, 'Asia/Tokyo' );
		await zs.seedLog( 1, { ip: IP } );
		const seeded = await seed( zs, '2026-07-15', '2099-07-15' );

		const id = ( await zs.logRows() )[ 0 ].log_id;
		await page.goto( AREA( 'activity', '', { item: String( id ) } ) );
		const details = page.locator( '.zs-drawer.zs-details' );
		await expect( details ).toContainText( /Temporary · ends \w+, Jul 15, 2099/ );

		await details.getByRole( 'button', { name: 'Edit block' } ).click();
		await expect( page.locator( '#zs-bf-start' ) ).toHaveValue( '2026-07-15T00:00' );
		await saveDrawer( page );
		const stored = await zs.plugin< Block >( 'GET', `rules/blocks/${ seeded.id }` );
		expect( stored.start ).toBe( seeded.start );
		expect( stored.end ).toBe( seeded.end );
	} );
} );
