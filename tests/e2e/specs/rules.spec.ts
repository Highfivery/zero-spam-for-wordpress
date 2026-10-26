/**
 * The 6.0 Rules area (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json): six tabs
 * that load without requests of their own and follow the keyboard; the
 * shared block drawer to add and change IP and location blocks (checked as
 * typed, focus in and back); Remove and Remove selected with Undo; search
 * with Clear search; the list editors with the unsaved-changes bar; the
 * recommended lists behind an inline confirmation; the no-lockout list
 * with Undo; cards and no sideways scrolling at 375, 1024, 1100 and 1280;
 * and on a network, the locked and network-default states of a subsite.
 *
 * Replaces the legacy Blocked IPs modal's known defect (AGENTS.md §8: no
 * focus management): the drawer's focus handling is checked here and in
 * keyboard.spec.ts.
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { AREA, emptyRules, rulesReady, seedRules } from '../support/screens';
import { subsiteURL } from '../support/targets';

const TABS = [ 'ips', 'locations', 'allowlist', 'email', 'words', 'no-lockout' ];

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

/** Opens a Rules tab and waits for its list. */
async function openTab( page: Page, tab = 'ips' ) {
	await page.goto( AREA( 'rules', tab ) );
	await rulesReady( page );
}

/** Rows of the block table (from 1,200px). */
const rows = ( page: Page ) => page.locator( '.zs-rules-t tbody tr:not(.zs-empty-row)' );

/** The block drawer. */
const drawer = ( page: Page ) => page.locator( '.zs-drawer.zs-blockform' );

/** The latest toast. */
const toast = ( page: Page ) => page.locator( '.zs-toasts .zs-toast' ).last();

/** Whether focus is inside an element. */
const focusInside = ( page: Page, selector: string ) => page.evaluate( ( sel ) => !! document.querySelector( sel )?.contains( document.activeElement ), selector );

test.describe( '6.0 Rules', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await emptyRules( zs );
	} );

	test( 'every tab loads without a REST request of its own', async ( { page, zs } ) => {
		await seedRules( zs );
		for ( const tab of TABS ) {
			const seen = await restDuring( page, async () => {
				await openTab( page, tab );
				await page.waitForTimeout( 800 );
			} );
			expect( seen, `${ tab } made requests on load` ).toEqual( [] );
		}
	} );

	test( 'tabs: arrow keys move and select, the address keeps the tab, Back returns', async ( { page, zs } ) => {
		await seedRules( zs );
		await openTab( page );
		const ips = page.getByRole( 'tab', { name: /^Blocked IPs/ } );
		await expect( ips ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( page.getByRole( 'tab', { name: /^Allowed IPs/ } ) ).toContainText( '2' );

		await ips.focus();
		await page.keyboard.press( 'ArrowRight' );
		const locations = page.getByRole( 'tab', { name: /^Blocked locations/ } );
		await expect( locations ).toBeFocused();
		await expect( locations ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( page ).toHaveURL( /tab=locations/ );
		await expect( page.getByRole( 'tabpanel' ) ).toHaveAttribute( 'aria-labelledby', 'zs-rt-locations' );

		await page.keyboard.press( 'End' );
		await expect( page.getByRole( 'tab', { name: /^No-lockout addresses/ } ) ).toBeFocused();
		await page.keyboard.press( 'Home' );
		await expect( ips ).toBeFocused();

		await page.goBack();
		await page.goBack();
		await expect( locations ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'block an IP: ranges and bad addresses are explained, the visitor is blocked, Edit changes the block, Remove has Undo', async ( { page, visitor, gallery } ) => {
		await openTab( page );
		const add = page.getByRole( 'button', { name: 'Block an IP' } );

		await test.step( 'the drawer takes focus and explains what is wrong', async () => {
			await add.click();
			await expect( drawer( page ) ).toBeVisible();
			expect( await focusInside( page, '.zs-drawer' ) ).toBe( true );
			const ip = drawer( page ).getByLabel( 'IP address' );

			await ip.fill( '203.0.113.0/24' );
			await drawer( page ).getByRole( 'button', { name: 'Block IP' } ).click();
			await expect( page.locator( '#zs-bf-ip-e' ) ).toHaveText( 'Blocks are for single addresses; ranges (CIDR) are not supported yet.' );
			await expect( ip ).toHaveAttribute( 'aria-invalid', 'true' );
			await expect( ip ).toBeFocused();

			await ip.fill( '203.0.113.999' );
			await drawer( page ).getByRole( 'button', { name: 'Block IP' } ).click();
			await expect( page.locator( '#zs-bf-ip-e' ) ).toHaveText( 'Must be an IPv4 or IPv6 address.' );
		} );

		await test.step( 'a permanent block applies to the visitor', async () => {
			await drawer( page ).getByLabel( 'IP address' ).fill( visitor.ip );
			await drawer( page ).getByText( 'Permanent', { exact: true } ).click();
			await drawer( page ).getByLabel( 'Reason' ).fill( 'E2E: blocked from Rules' );
			await gallery.capture( drawer( page ), 'block an IP drawer' );
			await drawer( page ).getByRole( 'button', { name: 'Block IP' } ).click();
			await expect( drawer( page ) ).toBeHidden();
			await expect( toast( page ) ).toContainText( `${ visitor.ip } blocked permanently` );
			await expect( add ).toBeFocused();
			await expect( rows( page ).filter( { hasText: visitor.ip } ) ).toHaveCount( 1 );
			await expect( page.getByRole( 'tab', { name: /^Blocked IPs/ } ) ).toContainText( '1' );
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 403 );
		} );

		await test.step( 'Edit block makes it temporary; focus returns to its Edit button', async () => {
			const edit = page.getByRole( 'button', { name: `Edit block for ${ visitor.ip }` } );
			await edit.click();
			await expect( drawer( page ).locator( '.zs-eyebrow' ) ).toHaveText( 'Edit block' );
			await drawer( page ).getByText( 'Temporary', { exact: true } ).click();
			await drawer( page ).getByRole( 'button', { name: 'Save block' } ).click();
			await expect( toast( page ) ).toContainText( 'Block saved' );
			await expect( edit ).toBeFocused();
			await expect( rows( page ).filter( { hasText: visitor.ip } ) ).toContainText( 'Temporary' );
		} );

		await test.step( 'Remove lets the visitor in; Undo blocks them again', async () => {
			await page.getByRole( 'button', { name: `Remove block for ${ visitor.ip }` } ).click();
			await expect( toast( page ) ).toContainText( `Removed the block on ${ visitor.ip }` );
			await expect( rows( page ) ).toHaveCount( 0 );
			await expect( page.locator( '.zs-rules .zs-empty' ) ).toContainText( 'No blocked IPs yet.' );
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 200 );

			await toast( page ).getByRole( 'button', { name: 'Undo' } ).click();
			await expect( toast( page ) ).toContainText( 'Block restored' );
			await expect( rows( page ).filter( { hasText: visitor.ip } ) ).toHaveCount( 1 );
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 403 );
		} );
	} );

	test( 'remove selected with Undo, search with Clear search, sort by end', async ( { page, zs } ) => {
		for ( const ip of [ '198.51.100.11', '198.51.100.12', '198.51.100.13' ] ) {
			await zs.block( ip, 'E2E bulk' );
		}
		await zs.plugin( 'POST', 'rules/blocks', { ip: '198.51.100.14', type: 'temporary', end: new Date( Date.now() + 2 * 864e5 ).toISOString() } );
		await openTab( page );
		await expect( rows( page ) ).toHaveCount( 4 );

		await test.step( 'sorting by Ends puts the temporary block first', async () => {
			await page.locator( '[data-zs-sort="end"]' ).click();
			await expect( page.locator( 'th', { has: page.locator( '[data-zs-sort="end"]' ) } ) ).toHaveAttribute( 'aria-sort', 'ascending' );
			await expect( rows( page ).first() ).toContainText( '198.51.100.14' );
		} );

		await test.step( 'remove two selected blocks, then Undo', async () => {
			const remove = page.getByRole( 'button', { name: 'Remove selected' } );
			await expect( remove ).toHaveAttribute( 'aria-disabled', 'true' );
			await page.getByLabel( 'Select 198.51.100.11' ).check();
			await page.getByLabel( 'Select 198.51.100.12' ).check();
			await expect( page.locator( '.zs-selcount' ) ).toHaveText( '2 selected' );
			await remove.click();
			await expect( toast( page ) ).toContainText( 'Removed 2 blocks' );
			await expect( rows( page ) ).toHaveCount( 2 );
			await toast( page ).getByRole( 'button', { name: 'Undo' } ).click();
			await expect( toast( page ) ).toContainText( 'Blocks restored' );
			await expect( rows( page ) ).toHaveCount( 4 );
		} );

		await test.step( 'a search with no match offers Clear search', async () => {
			const search = page.getByRole( 'searchbox', { name: 'Search IPs' } );
			await search.fill( '203.0.113.' );
			await expect( page.locator( '.zs-rules .zs-empty' ) ).toContainText( 'No blocked IPs match your search.' );
			await page.getByRole( 'button', { name: 'Clear search' } ).click();
			await expect( search ).toBeFocused();
			await expect( search ).toHaveValue( '' );
			await expect( rows( page ) ).toHaveCount( 4 );
		} );
	} );

	test( 'block a location: the country code is checked, the block shows its country', async ( { page } ) => {
		await openTab( page, 'locations' );
		await page.getByRole( 'button', { name: 'Block a location' } ).click();
		const form = drawer( page );
		await expect( form.locator( '.zs-eyebrow' ) ).toHaveText( 'Block a location' );
		await form.getByLabel( 'Location type' ).selectOption( 'country_code' );
		await form.getByLabel( 'Location', { exact: true } ).fill( 'Germany' );
		await form.getByRole( 'button', { name: 'Block location' } ).click();
		await expect( page.locator( '#zs-bf-key-e' ) ).toHaveText( 'Must be a two-letter country code, such as DE or US.' );

		await form.getByLabel( 'Location', { exact: true } ).fill( 'de' );
		await form.getByText( 'Permanent', { exact: true } ).click();
		await form.getByRole( 'button', { name: 'Block location' } ).click();
		await expect( toast( page ) ).toContainText( 'Germany blocked permanently' );
		await expect( rows( page ).first() ).toContainText( 'Germany' );
		await expect( rows( page ).first() ).toContainText( 'Country code' );
		await expect( page.getByRole( 'tab', { name: /^Blocked locations/ } ) ).toContainText( '1' );
	} );

	test( 'Allowed IPs: entries are checked, wait in the unsaved bar across tabs, then save; Discard drops them', async ( { page } ) => {
		await openTab( page, 'allowlist' );
		const field = page.getByLabel( 'Add IP addresses' );
		const add = page.getByRole( 'button', { name: 'Add', exact: true } );
		const bar = page.locator( '.zs-savebar' );

		await test.step( 'ranges and bad addresses are explained and nothing is added', async () => {
			await field.fill( '203.0.113.0/24' );
			await add.click();
			await expect( page.locator( '#zs-le-allowed-ips-add-e' ) ).toContainText( 'Ranges (CIDR) are not supported in the IP Allowlist' );
			await expect( field ).toBeFocused();
			await field.fill( '192.0.2.5, nope' );
			await add.click();
			await expect( page.locator( '#zs-le-allowed-ips-add-e' ) ).toHaveText( 'These are not IP addresses: nope' );
			await expect( bar ).toHaveCount( 0 );
		} );

		await test.step( 'added entries wait for Save, also after a change of tab', async () => {
			await field.fill( '192.0.2.5 2001:DB8::0:7' );
			await add.click();
			await expect( page.locator( '.zs-elist' ) ).toContainText( '2001:db8::7' );
			await expect( bar ).toContainText( '2 unsaved changes' );
			await page.getByRole( 'tab', { name: /^Words/ } ).click();
			await expect( bar ).toContainText( '2 unsaved changes' );
			await page.getByRole( 'tab', { name: /^Allowed IPs/ } ).click();
			await expect( page.locator( '.zs-elist' ) ).toContainText( 'New, not saved' );
			await bar.getByRole( 'button', { name: 'Save changes' } ).click();
			await expect( toast( page ) ).toContainText( 'Changes saved' );
			await expect( bar ).toHaveCount( 0 );
		} );

		await test.step( 'saved for real; a removal can be discarded', async () => {
			await page.reload();
			await rulesReady( page );
			await expect( page.locator( '.zs-elist li' ) ).toHaveCount( 2 );
			await page.getByRole( 'button', { name: 'Remove 192.0.2.5' } ).click();
			await expect( page.locator( '.zs-elist' ) ).toContainText( 'Removed, not saved' );
			await bar.getByRole( 'button', { name: 'Discard' } ).click();
			await expect( bar ).toHaveCount( 0 );
			await expect( page.locator( '.zs-elist' ) ).not.toContainText( 'Removed, not saved' );
		} );

		await test.step( 'a focused control is never hidden by the unsaved bar (WCAG 2.4.11)', async () => {
			await page.getByRole( 'button', { name: 'Remove 2001:db8::7' } ).click();
			const remove = page.getByRole( 'button', { name: 'Remove 192.0.2.5' } );
			await remove.focus();
			const covered = await page.evaluate( () => {
				const el = document.activeElement!.getBoundingClientRect();
				const barBox = document.querySelector( '.zs-savebar' )!.getBoundingClientRect();
				return el.bottom > barBox.top && el.top < barBox.bottom;
			} );
			expect( covered ).toBe( false );
		} );
	} );

	test( 'Email domains: Replace with recommended asks first; Cancel returns focus; confirming fills the list', async ( { page } ) => {
		await openTab( page, 'email' );
		await expect( page.locator( '.zs-rules .zs-empty' ) ).toContainText( 'No blocked email domains.' );
		const replace = page.getByRole( 'button', { name: 'Replace with recommended domains' } );
		await replace.click();
		const confirm = page.getByRole( 'button', { name: 'Replace domains' } );
		await expect( confirm ).toBeFocused();
		await page.getByRole( 'button', { name: 'Cancel' } ).click();
		await expect( replace ).toBeFocused();

		await replace.click();
		await page.getByRole( 'button', { name: 'Replace domains' } ).click();
		await expect( page.locator( '.zs-actres' ) ).toContainText( 'now match the recommended list' );
		await expect( page.locator( '.zs-elist li' ).first() ).toBeVisible();
		await expect( page.getByRole( 'tab', { name: /^Email domains/ } ) ).not.toContainText( /\b0\b/ );
	} );

	test( 'Words: the minimum length is checked when saving', async ( { page } ) => {
		await openTab( page, 'words' );
		const min = page.getByLabel( 'Minimum disallowed word length' );
		await min.fill( '101' );
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '#zs-minlen-e' ) ).toHaveText( 'Must be a whole number from 0 to 100.' );
		await expect( min ).toBeFocused();
		await min.fill( '5' );
		await page.getByLabel( 'Add allowed words' ).fill( 'van gogh, ugg' );
		await page.getByRole( 'button', { name: 'Add', exact: true } ).click();
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( toast( page ) ).toContainText( 'Changes saved' );
		await page.reload();
		await rulesReady( page );
		await expect( min ).toHaveValue( '5' );
		await expect( page.locator( '.zs-elist li' ) ).toHaveCount( 2 );
	} );

	test( 'No-lockout addresses: Remove, then Undo puts it back', async ( { page, zs } ) => {
		await zs.noLockout( [ '198.51.100.200', '192.0.2.10' ] );
		await openTab( page, 'no-lockout' );
		await expect( page.locator( '.zs-safelist li' ) ).toHaveCount( 2 );
		await page.getByRole( 'button', { name: 'Remove 192.0.2.10 from the administrator addresses that can never be locked out' } ).click();
		await expect( toast( page ) ).toContainText( 'Removed 192.0.2.10. It can be blocked again.' );
		await expect( page.locator( '.zs-safelist li' ) ).toHaveCount( 1 );
		await toast( page ).getByRole( 'button', { name: 'Undo' } ).click();
		await expect( toast( page ) ).toContainText( 'Address restored' );
		await expect( page.locator( '.zs-safelist li' ) ).toHaveCount( 2 );
	} );

	test( 'narrow screens: cards with Select all and a Sort menu, Edit block from a card; no sideways scrolling', async ( { zs, newRoleContext } ) => {
		await seedRules( zs );
		for ( const [ width, height ] of [
			[ 1280, 900 ],
			[ 1100, 900 ],
			[ 1024, 900 ],
			[ 375, 812 ],
		] ) {
			const narrow = await ( await newRoleContext( 'admin', { viewport: { width, height } } ) ).newPage();
			for ( const tab of TABS ) {
				await narrow.goto( AREA( 'rules', tab ) );
				await rulesReady( narrow );
				const overflow = await narrow.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
				expect( overflow, `${ tab } at ${ width }px scrolls sideways` ).toBeLessThanOrEqual( 1 );
			}
			if ( 375 === width ) {
				await narrow.goto( AREA( 'rules', 'ips' ) );
				await rulesReady( narrow );
				await expect( narrow.locator( '.zs-cards li' ) ).toHaveCount( 6 );
				await expect( narrow.getByLabel( 'Select all' ) ).toBeVisible();
				await narrow.getByLabel( 'Sort' ).selectOption( 'key:asc' );
				await narrow.locator( '.zs-cardmain' ).first().click();
				await expect( narrow.locator( '.zs-drawer.zs-blockform' ) ).toBeVisible();
				await narrow.keyboard.press( 'Escape' );
				await expect( narrow.locator( '.zs-cardmain' ).first() ).toBeFocused();
			}
			await narrow.context().close();
		}
	} );
} );

test.describe( '6.0 Rules on a subsite', { tag: '@multisite' }, () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline', { network: true } );
	} );

	test( 'a list the network locked is read-only; the network’s list can be customized for the site', async ( { asSiteAdmin, target, zs } ) => {
		await test.step( 'locked: the badge, no add field, no Remove', async () => {
			await zs.call( 'POST', 'network-setting', { key: 'ip_whitelist', value: '203.0.113.5', locked: true } );
			await asSiteAdmin.goto( subsiteURL( target, AREA( 'rules', 'allowlist' ) ) );
			await rulesReady( asSiteAdmin );
			await expect( asSiteAdmin.locator( '.zs-badge-lock [aria-hidden="true"]' ) ).toHaveText( 'Allowlist locked by network' );
			await expect( asSiteAdmin.locator( '.zs-elist' ) ).toContainText( '203.0.113.5' );
			await expect( asSiteAdmin.getByLabel( 'Add IP addresses' ) ).toHaveCount( 0 );
			await expect( asSiteAdmin.getByRole( 'button', { name: /^Remove / } ) ).toHaveCount( 0 );
		} );

		await test.step( 'network default: read-only until Customize for this site', async () => {
			await zs.call( 'POST', 'network-setting', { key: 'ip_whitelist', value: '203.0.113.5', locked: false } );
			await asSiteAdmin.goto( subsiteURL( target, AREA( 'rules', 'allowlist' ) ) );
			await rulesReady( asSiteAdmin );
			await expect( asSiteAdmin.getByLabel( 'Add IP addresses' ) ).toHaveCount( 0 );
			await asSiteAdmin.getByRole( 'button', { name: 'Customize for this site' } ).click();
			await expect( asSiteAdmin.getByLabel( 'Add IP addresses' ) ).toBeFocused();
			await asSiteAdmin.getByLabel( 'Add IP addresses' ).fill( '192.0.2.77' );
			await asSiteAdmin.getByRole( 'button', { name: 'Add', exact: true } ).click();
			await asSiteAdmin.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
			await expect( asSiteAdmin.locator( '.zs-toasts' ) ).toContainText( 'Changes saved' );
			const stored = JSON.stringify( await zs.forSite( target.subsitePath! ).options( 'zero-spam-settings' ) );
			expect( stored ).toContain( '192.0.2.77' );
			expect( stored ).toContain( '203.0.113.5' );
		} );
	} );
} );
