/**
 * The 6.0 Rules area (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json): six tabs
 * that load without requests of their own and follow the keyboard; the
 * shared block drawer to add and change IP and location blocks (checked as
 * typed, focus in and back); Remove and Remove selected with Undo; search
 * with Clear search; the list editors with the unsaved-changes bar; the
 * recommended lists behind an inline confirmation; the no-lockout list
 * with Undo; cards below 1,440px and a table from there while it fits, with
 * nothing clipped at either side of the line, even with the widest content or
 * a longer language; focus kept across every switch; no sideways scrolling at
 * 375, 1024, 1100, 1280 and 1439; and on a network, the locked and
 * network-default states of a subsite.
 *
 * Replaces the legacy Blocked IPs modal's known defect (AGENTS.md §8: no
 * focus management): the drawer's focus handling is checked here and in
 * keyboard.spec.ts.
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import type { ZsApi } from '../support/e2e-api';
import { AREA, emptyRules, rulesReady, seedRules } from '../support/screens';
import { subsiteURL } from '../support/targets';
import type { WpCli } from '../support/wp-cli';

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

/** Rows of the block table (from 1,440px). */
const rows = ( page: Page ) => page.locator( '.zs-rules-t tbody tr:not(.zs-empty-row)' );

/** The block drawer. */
const drawer = ( page: Page ) => page.locator( '.zs-drawer.zs-blockform' );

/** The latest toast. */
const toast = ( page: Page ) => page.locator( '.zs-toasts .zs-toast' ).last();

/** Whether focus is inside an element. */
const focusInside = ( page: Page, selector: string ) => page.evaluate( ( sel ) => !! document.querySelector( sel )?.contains( document.activeElement ), selector );

/** The widest a table is at, and the row actions it cuts off. */
const clipping = ( page: Page ) => page.locator( '.zs-rules-list > .zs-tbl-wrap' ).evaluate( ( wrap ) => {
	const right = wrap.getBoundingClientRect().right;
	return {
		scrolls: wrap.scrollWidth - wrap.clientWidth,
		cutOff: Array.from( wrap.querySelectorAll( '.zs-btn' ) ).filter( ( b ) => b.getBoundingClientRect().right > right ).length,
	};
} );

/** A clipping check that allows for sub-pixel rounding (FIT_TOLERANCE). */
const expectNoClipping = async ( page: Page ) => {
	const { scrolls, cutOff } = await clipping( page );
	expect( scrolls, 'the list scrolls sideways' ).toBeLessThanOrEqual( 1 );
	expect( cutOff, 'row actions are cut off' ).toBe( 0 );
};

/** Which layout the list shows. */
const layout = ( page: Page ) => page.locator( '.zs-rules-list > .zs-tbl-wrap' ).evaluate( ( wrap ) => ( wrap.querySelector( '.zs-rules-t' ) ? 'table' : 'cards' ) );

/** Starts counting switches between table and cards. */
const countSwitches = ( page: Page ) => page.locator( '.zs-rules-list > .zs-tbl-wrap' ).evaluate( ( wrap ) => {
	const win = window as unknown as { zsSwitches: number };
	win.zsSwitches = 0;
	new MutationObserver( ( records ) => {
		win.zsSwitches += records.filter( ( r ) => Array.from( r.addedNodes ).some( ( n ) => [ 'zs-rules-t', 'zs-cards' ].some( ( c ) => ( n as Element ).querySelector?.( `.${ c }` ) || ( n as Element ).classList?.contains( c ) ) ) ).length;
	} ).observe( wrap, { childList: true, subtree: true } );
} );

/** Switches counted so far. */
const switches = ( page: Page ) => page.evaluate( () => ( window as unknown as { zsSwitches: number } ).zsSwitches );

/** What the active element is in the list: its row and control. */
const focused = ( page: Page ) => page.evaluate( () => {
	const el = document.activeElement as HTMLElement;
	return { row: el.closest< HTMLElement >( '[data-zs-row]' )?.dataset.zsRow ?? null, control: el.closest< HTMLElement >( '[data-zs-ctl]' )?.dataset.zsCtl ?? el.tagName };
} );

/** German strings for the block lists, longer than the English ones. */
const GERMAN = {
	'Edit block': 'Block bearbeiten',
	Remove: 'Entfernen',
	Date: 'Hinzugefügt am',
	Type: 'Sperrtyp',
	'IP address': 'IP-Adresse',
	Location: 'Standort',
	Starts: 'Beginnt am',
	Ends: 'Endet am',
	Reason: 'Begründung',
	Temporary: 'Vorübergehend',
	Permanent: 'Dauerhaft',
	Never: 'Niemals',
	'Look up': 'Nachschlagen',
};

/** Words broken over two lines ("Germ/any", "Comme/nt") in the table's country names and reasons. */
const brokenWords = ( page: Page, selector = '.zs-rules-t :is(.zs-cty > span, .zs-clamp2)' ) => page.locator( selector ).evaluateAll( ( names ) => names.flatMap( ( name ) => {
	const text = name.firstChild as Text;
	const broken: string[] = [];
	// A line may end after a hyphen ("Credential-" / "stuffing"): each part counts as a word.
	for ( const word of text.data.matchAll( /[^\s-]+-?/g ) ) {
		const range = document.createRange();
		range.setStart( text, word.index );
		range.setEnd( text, word.index + word[ 0 ].length );
		if ( range.getClientRects().length > 1 ) {
			broken.push( word[ 0 ] );
		}
	}
	return broken;
} ) );

/**
 * The widest content the lists show: dates with their year in all three date
 * columns, a 2099 end, the longest addresses, country names and reasons, and
 * place names with no room to wrap.
 *
 * @param zs    Test API.
 * @param wpcli WP-CLI.
 */
async function seedWidest( zs: ZsApi, wpcli: WpCli ) {
	const wide = { type: 'temporary', start: '2025-11-26', end: '2099-11-25T12:00:00Z', reason: 'Comment spam from this place, again and again, '.repeat( 5 ) };
	for ( const ip of [ '2001:db8:85a3:1234:5678:8a2e:370:7334', '2001:db8:85a3:1234:5678:8a2e:370:7335', '255.255.255.254', '198.51.100.222', '203.0.113.222', '203.0.113.223' ] ) {
		await zs.plugin( 'POST', 'rules/blocks', { ...wide, ip } );
	}
	await zs.plugin( 'POST', 'rules/blocks', { ...wide, ip: '192.0.2.222', reason: 'Credential-stuffing attempts through residential proxies, permanently blocked after investigation' } );
	for ( const key of [ 'DE', 'LI', 'TM', 'VC', 'GS' ] ) {
		await zs.plugin( 'POST', 'rules/blocks', { ...wide, kind: 'location', key_type: 'country_code', key } );
	}
	await zs.plugin( 'POST', 'rules/blocks', { ...wide, kind: 'location', key_type: 'city', key: 'Llanfairpwllgwyngyllgogerychwyrndrobwllllantysiliogogogoch', reason: 'Credential-stuffing attempts through residential proxies, permanently blocked after investigation' } );
	await zs.plugin( 'POST', 'rules/blocks', { ...wide, kind: 'location', key_type: 'region_code', key: 'North Rhine-Westphalia and a very long region name somewhere else' } );
	// The route always dates a block today; the Date column needs a year too.
	wpcli.exec( [ 'eval', 'global $wpdb; $wpdb->query( "UPDATE {$wpdb->prefix}wpzerospam_blocked SET date_added = \'2025-11-26 12:00:00\'" );' ] );
}

test.describe( '6.0 Rules', () => {
	test.beforeEach( async ( { page, zs } ) => {
		await zs.reset( 'baseline' );
		await emptyRules( zs );
		// Most of this file works on the block table, which starts here.
		await page.setViewportSize( { width: 1440, height: 900 } );
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

	for ( const [ tab, seeded ] of [ [ 'ips', 7 ], [ 'locations', 7 ] ] as Array< [ string, number ] > ) {
		test( `${ tab }: a table from 1,440px with nothing clipped, even with the widest content; cards at 1,439px`, async ( { page, zs, wpcli, gallery } ) => {
			await seedWidest( zs, wpcli );
			await test.step( '1,440px: the table, every action inside it, no country name broken', async () => {
				await openTab( page, tab );
				await expect( rows( page ) ).toHaveCount( seeded );
				await expectNoClipping( page );
				expect( await brokenWords( page ) ).toEqual( [] );
				await gallery.capture( page.locator( '.zs-areabody' ), `Rules ${ tab } at 1,440px, widest content` );
			} );

			await test.step( '1,439px: cards, and nothing scrolls sideways', async () => {
				await page.setViewportSize( { width: 1439, height: 900 } );
				await openTab( page, tab );
				await expect( page.locator( '.zs-rules-list .zs-cards li' ) ).toHaveCount( seeded );
				await expect( page.locator( '.zs-rules-t' ) ).toHaveCount( 0 );
				expect( await clipping( page ) ).toEqual( { scrolls: 0, cutOff: 0 } );
				expect( await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth ) ).toBeLessThanOrEqual( 0 );
			} );
		} );
	}

	test( 'at 1,280px: cards, every action visible and reachable by keyboard; selection, bulk bar and sort menu work', async ( { page, zs, wpcli } ) => {
		await seedWidest( zs, wpcli );
		await page.setViewportSize( { width: 1280, height: 900 } );

		for ( const tab of [ 'ips', 'locations' ] ) {
			await openTab( page, tab );
			await expect( page.locator( '.zs-rules-t' ) ).toHaveCount( 0 );
			const cards = page.locator( '.zs-rules-list .zs-cards li' );
			await expect( cards ).toHaveCount( 7 );
			expect( await clipping( page ) ).toEqual( { scrolls: 0, cutOff: 0 } );

			await test.step( `${ tab }: Tab goes through every card's controls, each one in sight`, async () => {
				await page.getByLabel( 'Select all' ).focus();
				const seen: string[] = [];
				// Remove selected and the Sort menu, then each card's controls (IP cards add Look up).
				for ( let i = 0; i < 2 + ( 7 * ( 'ips' === tab ? 5 : 4 ) ); i++ ) {
					await page.keyboard.press( 'Tab' );
					const stop = await page.evaluate( () => {
						const el = document.activeElement as HTMLElement;
						const box = el.getBoundingClientRect();
						const hit = document.elementFromPoint( box.left + ( box.width / 2 ), box.top + ( box.height / 2 ) );
						return { name: el.getAttribute( 'aria-label' ) ?? el.textContent ?? '', inSight: !! hit && ( el === hit || el.contains( hit ) || hit.contains( el ) ) };
					} );
					expect( stop.inSight, `${ stop.name } is covered` ).toBe( true );
					seen.push( stop.name );
				}
				expect( seen.filter( ( n ) => n.startsWith( 'Select ' ) ) ).toHaveLength( 7 );
				expect( seen.filter( ( n ) => n.startsWith( 'Edit block: ' ) ) ).toHaveLength( 7 );
				expect( seen.filter( ( n ) => n.startsWith( 'Edit block for ' ) ) ).toHaveLength( 7 );
				expect( seen.filter( ( n ) => n.startsWith( 'Remove block for ' ) ) ).toHaveLength( 7 );
				expect( seen.filter( ( n ) => n.startsWith( 'Look up ' ) ) ).toHaveLength( 'ips' === tab ? 7 : 0 );
			} );

			await test.step( `${ tab }: Edit block and Remove answer the keyboard`, async () => {
				const first = cards.first();
				const edit = first.locator( '[data-zs-edit]' );
				await edit.focus();
				await page.keyboard.press( 'Enter' );
				await expect( drawer( page ) ).toBeVisible();
				await page.keyboard.press( 'Escape' );
				await expect( edit ).toBeFocused();

				await first.getByRole( 'button', { name: /^Remove block for / } ).focus();
				await page.keyboard.press( 'Enter' );
				await expect( toast( page ) ).toContainText( 'Removed the block on' );
				await expect( cards ).toHaveCount( 6 );
				await toast( page ).getByRole( 'button', { name: 'Undo' } ).click();
				await expect( cards ).toHaveCount( 7 );
			} );

			await test.step( `${ tab }: Select all and Remove selected, with Undo`, async () => {
				await page.getByLabel( 'Select all' ).check();
				await expect( page.locator( '.zs-selcount' ) ).toHaveText( '7 selected' );
				await page.getByRole( 'button', { name: 'Remove selected' } ).click();
				await expect( toast( page ) ).toContainText( 'Removed 7 blocks' );
				await toast( page ).getByRole( 'button', { name: 'Undo' } ).click();
				// The rows come back with the toast; sorting earlier would race Undo's refresh.
				await expect( toast( page ) ).toContainText( 'Blocks restored' );
				await expect( cards ).toHaveCount( 7 );
			} );

			await test.step( `${ tab }: the Sort menu changes the order`, async () => {
				await page.getByLabel( 'Sort', { exact: true } ).selectOption( 'key:asc' );
				await expect( page.locator( '.zs-cm-t' ).first() ).toHaveText( 'ips' === tab ? '192.0.2.222' : 'Germany' );
			} );
		}
	} );

	test( 'resizing across 1,440px swaps table and cards, keeping the selection and the order', async ( { page, zs } ) => {
		await seedRules( zs );
		await openTab( page );
		await page.locator( '[data-zs-sort="type"]' ).click();
		await expect( page.locator( 'th', { has: page.locator( '[data-zs-sort="type"]' ) } ) ).toHaveAttribute( 'aria-sort', 'ascending' );
		await expect( page.locator( '.zs-rules-t' ) ).not.toHaveAttribute( 'aria-busy', 'true' );
		await page.getByLabel( 'Select 198.51.100.21' ).check();

		await page.setViewportSize( { width: 1439, height: 900 } );
		await expect( page.locator( '.zs-rules-list .zs-cards li' ) ).toHaveCount( 6 );
		await expect( page.locator( '.zs-rules-t' ) ).toHaveCount( 0 );
		await expect( page.locator( '.zs-selcount' ) ).toHaveText( '1 selected' );
		await expect( page.getByLabel( 'Select 198.51.100.21' ) ).toBeChecked();
		const sort = page.getByLabel( 'Sort', { exact: true } );
		await expect( sort ).toHaveValue( 'type:asc' );
		await expect( sort.locator( 'option:checked' ) ).toHaveText( 'Sort: Custom order' );

		await page.setViewportSize( { width: 1440, height: 900 } );
		await expect( rows( page ) ).toHaveCount( 6 );
		await expect( page.locator( '.zs-rules-list .zs-cards' ) ).toHaveCount( 0 );
		await expect( page.getByLabel( 'Select 198.51.100.21' ) ).toBeChecked();
		await expect( page.locator( 'th', { has: page.locator( '[data-zs-sort="type"]' ) } ) ).toHaveAttribute( 'aria-sort', 'ascending' );
	} );

	test( 'focus stays on the same row\'s control when the layout switches, and a drawer returns to it', async ( { page, zs } ) => {
		await seedRules( zs );
		await openTab( page );
		const row = await page.locator( '[data-zs-row]' ).nth( 2 ).getAttribute( 'data-zs-row' );
		const inRow = ( control: string ) => page.locator( `[data-zs-row="${ row }"] [data-zs-ctl="${ control }"]` );

		for ( const control of [ 'remove', 'select', 'edit', 'lookup' ] ) {
			await test.step( `${ control }: table to cards and back`, async () => {
				await page.setViewportSize( { width: 1440, height: 900 } );
				await expect( page.locator( '.zs-rules-t' ) ).toBeVisible();
				await inRow( control ).focus();
				await page.setViewportSize( { width: 1439, height: 900 } );
				await expect( page.locator( '.zs-rules-t' ) ).toHaveCount( 0 );
				await expect( inRow( control ) ).toBeFocused();
				await page.setViewportSize( { width: 1440, height: 900 } );
				await expect( page.locator( '.zs-rules-t' ) ).toBeVisible();
				await expect( inRow( control ) ).toBeFocused();
			} );
		}

		await test.step( 'a card\'s main button lands on Edit block', async () => {
			await page.setViewportSize( { width: 1439, height: 900 } );
			await inRow( 'main' ).focus();
			await page.setViewportSize( { width: 1440, height: 900 } );
			await expect( page.locator( '.zs-rules-t' ) ).toBeVisible();
			await expect( inRow( 'edit' ) ).toBeFocused();
		} );

		await test.step( 'Select all and sorting have their match', async () => {
			await page.locator( '.zs-rules-t [data-zs-ctl="select-all"]' ).focus();
			await page.setViewportSize( { width: 1439, height: 900 } );
			await expect( page.getByLabel( 'Select all' ) ).toBeFocused();
			await page.setViewportSize( { width: 1440, height: 900 } );
			await page.locator( '[data-zs-sort="date_added"]' ).focus();
			await page.setViewportSize( { width: 1439, height: 900 } );
			await expect( page.getByLabel( 'Sort', { exact: true } ) ).toBeFocused();
		} );

		await test.step( 'a drawer opened on the table and closed on the cards returns to the row\'s Edit block', async () => {
			await page.setViewportSize( { width: 1440, height: 900 } );
			await expect( page.locator( '.zs-rules-t' ) ).toBeVisible();
			await inRow( 'edit' ).click();
			await expect( drawer( page ) ).toBeVisible();
			await page.setViewportSize( { width: 1439, height: 900 } );
			await expect( page.locator( '.zs-rules-t' ) ).toHaveCount( 0 );
			await page.keyboard.press( 'Escape' );
			await expect( drawer( page ) ).toBeHidden();
			await expect( inRow( 'edit' ) ).toBeFocused();
		} );
	} );

	test( 'IP cards keep the lookup link the table has', async ( { page, zs } ) => {
		await seedRules( zs );
		for ( const [ width, height ] of [ [ 1280, 900 ], [ 375, 812 ] ] ) {
			await page.setViewportSize( { width, height } );
			await openTab( page );
			const link = page.getByRole( 'link', { name: 'Look up 192.0.2.41 (opens in a new tab)' } );
			await expect( link ).toBeVisible();
			await expect( link ).toHaveAttribute( 'target', '_blank' );
			await expect( link ).toHaveAttribute( 'href', /192\.0\.2\.41/ );
			await expectNoClipping( page );
		}
	} );

	test( 'a longer language: cards at 1,440px with nothing clipped, the table where it fits, no repeated switching', async ( { page, zs } ) => {
		await seedRules( zs );
		await zs.scriptTranslations( GERMAN );

		for ( const tab of [ 'ips', 'locations' ] ) {
			await page.setViewportSize( { width: 1440, height: 900 } );
			await openTab( page, tab );
			await expect( page.locator( '.zs-rules-list' ) ).toContainText( 'Entfernen' );

			await test.step( `${ tab }: cards at 1,440px, nothing clipped, and they stay`, async () => {
				await expect.poll( () => layout( page ) ).toBe( 'cards' );
				await expectNoClipping( page );
				await countSwitches( page );
				await page.waitForTimeout( 1000 );
				expect( await switches( page ), 'switched while idle' ).toBe( 0 );
			} );

			await test.step( `${ tab }: a small widening does not try the table, a real one shows it, unclipped`, async () => {
				await page.setViewportSize( { width: 1450, height: 900 } );
				await page.waitForTimeout( 300 );
				expect( await layout( page ) ).toBe( 'cards' );
				await page.setViewportSize( { width: 1700, height: 900 } );
				await expect.poll( () => layout( page ) ).toBe( 'table' );
				await expectNoClipping( page );
			} );

			await test.step( `${ tab }: narrowing goes back to cards once, and it stays`, async () => {
				const before = await switches( page );
				await page.setViewportSize( { width: 1440, height: 900 } );
				await expect.poll( () => layout( page ) ).toBe( 'cards' );
				await page.waitForTimeout( 1000 );
				expect( await switches( page ) - before, 'switches on the way back' ).toBe( 1 );
				await expectNoClipping( page );
			} );
		}
	} );

	test( 'new rows can make the table overflow: cards, with the selection, the sort and focus kept', async ( { page, zs } ) => {
		await seedRules( zs );
		await zs.scriptTranslations( GERMAN );

		// The narrowest window that shows the table on load: all rows are Dauerhaft and Niemals so far.
		let [ low, high ] = [ 1440, 1700 ];
		while ( high - low > 4 ) {
			const width = Math.round( ( low + high ) / 2 );
			await page.setViewportSize( { width, height: 900 } );
			await openTab( page );
			if ( 'table' === await layout( page ) ) {
				high = width;
			} else {
				low = width;
			}
		}
		await page.setViewportSize( { width: high, height: 900 } );
		await openTab( page );
		expect( await layout( page ) ).toBe( 'table' );
		await expectNoClipping( page );

		await page.locator( '[data-zs-sort="key"]' ).click();
		await expect( page.locator( 'th', { has: page.locator( '[data-zs-sort="key"]' ) } ) ).toHaveAttribute( 'aria-sort', 'ascending' );
		await expect( page.locator( '.zs-rules-t' ) ).not.toHaveAttribute( 'aria-busy', 'true' );
		await page.getByLabel( 'Select 198.51.100.21' ).check();

		const add = page.getByRole( 'button', { name: 'Block an IP' } );
		await add.click();
		await page.locator( '#zs-bf-ip' ).fill( '192.0.2.77' );
		await drawer( page ).getByRole( 'button', { name: 'Block IP' } ).click();
		await expect( drawer( page ) ).toBeHidden();

		await expect.poll( () => layout( page ) ).toBe( 'cards' );
		await expectNoClipping( page );
		await expect( page.getByLabel( 'Select 198.51.100.21' ) ).toBeChecked();
		await expect( page.locator( '.zs-selcount' ) ).toHaveText( '1 selected' );
		await expect( page.getByLabel( 'Sort', { exact: true } ) ).toHaveValue( 'key:asc' );
		await expect( add ).toBeFocused();
		expect( ( await focused( page ) ).control ).toBe( 'BUTTON' );
	} );

	test( 'a change of rows tries the table before paint: never seen, focus kept', async ( { page, zs } ) => {
		await seedRules( zs );
		await zs.scriptTranslations( GERMAN );
		await openTab( page );
		await expect.poll( () => layout( page ) ).toBe( 'cards' );

		// What each painted frame shows, while the rows change.
		await page.evaluate( () => {
			const win = window as unknown as { zsFrames: number; zsTableFrames: number };
			win.zsFrames = 0;
			win.zsTableFrames = 0;
			const sample = () => {
				win.zsFrames++;
				win.zsTableFrames += document.querySelector( '.zs-rules-t' ) ? 1 : 0;
				window.requestAnimationFrame( sample );
			};
			window.requestAnimationFrame( sample );
		} );
		await countSwitches( page );

		const sort = page.getByLabel( 'Sort', { exact: true } );
		await sort.focus();
		await sort.selectOption( 'key:asc' );
		await expect( page.locator( '.zs-cm-t' ).first() ).toHaveText( '192.0.2.41' );
		await expect.poll( () => switches( page ), 'the table was tried' ).toBeGreaterThan( 0 );
		expect( await layout( page ) ).toBe( 'cards' );
		await expect( sort ).toBeFocused();
		const frames = await page.evaluate( () => {
			const win = window as unknown as { zsFrames: number; zsTableFrames: number };
			return { all: win.zsFrames, table: win.zsTableFrames };
		} );
		expect( frames.all ).toBeGreaterThan( 0 );
		expect( frames.table, 'frames that showed the table' ).toBe( 0 );

		const before = await switches( page );
		await page.waitForTimeout( 1000 );
		expect( await switches( page ) - before, 'switched while idle' ).toBe( 0 );
	} );

	test( 'a reason with one very long word: cards at 1,440px rather than a broken or clipped table', async ( { page, zs } ) => {
		await zs.plugin( 'POST', 'rules/blocks', { ip: '192.0.2.250', type: 'permanent', reason: 'x'.repeat( 250 ) } );
		await zs.plugin( 'POST', 'rules/blocks', { ip: '192.0.2.251', type: 'permanent', reason: 'Steady comment spam, permanently blocked' } );
		await openTab( page );
		await expect.poll( () => layout( page ) ).toBe( 'cards' );
		await expectNoClipping( page );
		await page.setViewportSize( { width: 1900, height: 900 } );
		await expect.poll( () => layout( page ) ).toBe( 'table' );
		await expectNoClipping( page );
		// Only the word wider than the cell breaks.
		expect( await brokenWords( page ) ).toEqual( [ 'x'.repeat( 250 ) ] );
	} );

	test( '320px, in English and in a longer language: cards that fit, every action on its card', async ( { page, zs } ) => {
		await seedRules( zs );
		await page.setViewportSize( { width: 320, height: 800 } );
		for ( const language of [ 'English', 'German' ] ) {
			if ( 'German' === language ) {
				await zs.scriptTranslations( GERMAN );
			}
			for ( const tab of [ 'ips', 'locations' ] ) {
				await openTab( page, tab );
				const cards = page.locator( '.zs-rules-list .zs-cards > li' );
				await expect( cards ).toHaveCount( 'ips' === tab ? 6 : 8 );
				if ( 'German' === language ) {
					await expect( page.locator( '.zs-rules-list' ) ).toContainText( 'Entfernen' );
				}
				const where = `${ tab } in ${ language } at 320px`;
				expect( await page.evaluate( () => document.documentElement.scrollWidth - window.innerWidth ), `${ where } scrolls sideways` ).toBeLessThanOrEqual( 0 );
				await expectNoClipping( page );
				const outside = await cards.evaluateAll( ( items ) => items.flatMap( ( li ) => {
					const card = li.getBoundingClientRect();
					return Array.from( li.querySelectorAll< HTMLElement >( '.zs-cm-a > *' ) )
						.filter( ( b ) => {
							const r = b.getBoundingClientRect();
							return ! r.width || r.left < card.left - 0.5 || r.right > card.right + 0.5 || r.right > window.innerWidth;
						} )
						.map( ( b ) => b.getAttribute( 'aria-label' ) ?? b.textContent ?? '' );
				} ) );
				expect( outside, `${ where }: actions off their card` ).toEqual( [] );
				const perCard = 'ips' === tab ? 3 : 2;
				await expect( page.locator( '.zs-rules-list .zs-cm-a > *' ) ).toHaveCount( await cards.count() * perCard );
			}
		}
	} );

	test( 'a collapsed WordPress menu does not bring the table below 1,440px', async ( { page, zs } ) => {
		await seedRules( zs );
		await page.setViewportSize( { width: 1439, height: 900 } );
		await openTab( page );
		await page.evaluate( () => document.body.classList.add( 'folded' ) );
		const wider = await page.locator( '.zs-rules-list' ).evaluate( ( list ) => list.clientWidth );
		expect( wider, 'the folded menu gives the list more room than the table needs' ).toBeGreaterThan( 1000 );
		await page.waitForTimeout( 300 );
		expect( await layout( page ) ).toBe( 'cards' );

		await page.setViewportSize( { width: 1440, height: 900 } );
		await expect.poll( () => layout( page ) ).toBe( 'table' );
		await expectNoClipping( page );
	} );

	test( 'narrow screens: cards with Select all and a Sort menu, Edit block from a card; no sideways scrolling', async ( { zs, newRoleContext } ) => {
		await seedRules( zs );
		for ( const [ width, height ] of [
			[ 1439, 900 ],
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
				await narrow.getByLabel( 'Sort', { exact: true } ).selectOption( 'key:asc' );
				await expect( narrow.locator( '.zs-cards-wrap' ) ).not.toHaveAttribute( 'aria-busy', 'true' );
				await expect( narrow.locator( '.zs-cm-t' ).first() ).toHaveText( '192.0.2.41' );
				const card = narrow.getByRole( 'button', { name: /^Edit block: 192\.0\.2\.41/ } );
				await card.click();
				await expect( narrow.locator( '.zs-drawer.zs-blockform' ) ).toBeVisible();
				await narrow.keyboard.press( 'Escape' );
				await expect( card ).toBeFocused();
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
			await expect( asSiteAdmin.locator( '.zs-badge-lock > span[aria-hidden="true"]' ) ).toHaveText( 'Allowlist locked by network' );
			await expect( asSiteAdmin.locator( '.zs-elist' ) ).toContainText( '203.0.113.5' );
			await expect( asSiteAdmin.getByLabel( 'Add IP addresses' ) ).toHaveCount( 0 );
			await expect( asSiteAdmin.getByRole( 'button', { name: /^Remove / } ) ).toHaveCount( 0 );
		} );

		await test.step( 'locked, the badge, the list and the block drawer fit at 1280 and 375px', async () => {
			for ( const [ width, height ] of [
				[ 1280, 900 ],
				[ 375, 812 ],
			] ) {
				await asSiteAdmin.setViewportSize( { width, height } );
				await asSiteAdmin.goto( subsiteURL( target, AREA( 'rules', 'allowlist' ) ) );
				await rulesReady( asSiteAdmin );
				await expect( asSiteAdmin.locator( '.zs-badge-lock' ) ).toBeVisible();
				expect( await asSiteAdmin.evaluate( () => document.documentElement.scrollWidth - window.innerWidth ), `allowlist at ${ width }px` ).toBeLessThanOrEqual( 1 );

				await asSiteAdmin.goto( subsiteURL( target, AREA( 'rules', 'ips' ) ) );
				await rulesReady( asSiteAdmin );
				await asSiteAdmin.getByRole( 'button', { name: 'Block an IP' } ).click();
				const footer = asSiteAdmin.locator( '.zs-drawer.zs-blockform .zs-drawer-f' );
				await expect( footer ).toBeVisible();
				const fits = await footer.evaluate( ( el ) => Array.from( el.querySelectorAll( '.zs-btn' ) ).every( ( b ) => {
					const r = b.getBoundingClientRect();
					const f = el.getBoundingClientRect();
					return r.left >= f.left - 1 && r.right <= f.right + 1;
				} ) );
				expect( fits, `drawer footer buttons fit at ${ width }px` ).toBe( true );
				await asSiteAdmin.keyboard.press( 'Escape' );
			}
			await asSiteAdmin.setViewportSize( { width: 1280, height: 900 } );
		} );

		await test.step( 'network default: read-only until Customize for this site', async () => {
			// The subsite keeps no settings of its own (the baseline stores the recommended ones), so the network's value applies.
			await zs.forSite( target.subsitePath! ).call( 'POST', 'reset', { preset: 'defaults', network: false } );
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
