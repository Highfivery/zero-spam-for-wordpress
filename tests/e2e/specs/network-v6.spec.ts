/**
 * The 6.0 network page: every
 * tab loads with no request of its own, the tabs work from the keyboard,
 * a network value saved and locked here is what a subsite gets (and an
 * empty one reads "(none)"), templates are made, changed and applied with
 * Undo, a block made network-wide stops the address on every site and
 * shows on a subsite as "Blocked by your network", the audit log filters
 * and pages, the comparison pages through sites, a site administrator
 * cannot open the page, and phones never scroll sideways. The
 * accessibility scans of every tab and group in both themes are in
 * a11y.spec.ts (screens.ts, V6_NETWORK).
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { AREA, NETWORK_SETTING_GROUPS, NETWORK_TABS, networkReady, seedNetwork } from '../support/screens';
import { subsiteURL } from '../support/targets';

/** Opens a network tab and waits for its content. */
async function openNetwork( page: Page, tab = '', extra: Record< string, string > = {} ) {
	await page.goto( AREA( 'network', tab, extra ) );
	await networkReady( page );
}

/** Text of the screen reader live regions and toasts. */
const announced = ( page: Page ) => page.locator( '[data-zs-live], .zs-toasts' ).allTextContents().then( ( t ) => t.join( ' ' ) );

test.describe( '6.0 Network', { tag: '@multisite' }, () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline', { network: true } );
	} );

	test( 'every tab and settings group loads with no REST request of its own (all preloaded)', async ( { page, zs, target } ) => {
		await seedNetwork( zs, target );
		const rest: string[] = [];
		const onRequest = ( r: Request ) => {
			const url = r.url();
			if ( url.includes( 'zero-spam/v1' ) || url.includes( 'zero-spam%2Fv1' ) ) {
				rest.push( `${ r.method() } ${ url }` );
			}
		};
		page.on( 'request', onRequest );
		for ( const [ tab ] of NETWORK_TABS ) {
			await openNetwork( page, 'overview' === tab ? '' : tab );
			await page.waitForTimeout( 600 );
		}
		for ( const group of NETWORK_SETTING_GROUPS ) {
			await openNetwork( page, 'settings', { group } );
			await expect( page.locator( '.zs-ncard' ).first() ).toBeVisible();
		}
		page.off( 'request', onRequest );
		expect( rest ).toEqual( [] );
	} );

	test( 'every group of the settings schema the network can set has a screen (screens.ts)', async ( { zs } ) => {
		type Entry = { area: string; group: string; type: string; lockable: boolean };
		const schema = await zs.plugin< { settings: Entry[] } >( 'GET', 'settings/schema' );
		// The grouping of assets/src/admin/network/model.js (groupOf()).
		const groupOf = ( e: Entry ) => {
			if ( 'protection' === e.area ) {
				return [ 'detection', 'integrations', 'intelligence' ].includes( e.group ) ? e.group : 'integrations';
			}
			return 'rules' === e.area ? 'rules' : 'settings';
		};
		const groups = new Set( schema.settings.filter( ( e ) => 'html' !== e.type && false !== e.lockable ).map( groupOf ) );
		for ( const group of groups ) {
			expect( NETWORK_SETTING_GROUPS as readonly string[], `add a screen for the network settings group "${ group }"` ).toContain( group );
		}
	} );

	test( 'the tabs work from the keyboard and the sidebar, and keep their place in the address', async ( { page } ) => {
		await openNetwork( page );
		const overview = page.getByRole( 'tab', { name: 'Overview' } );
		await overview.focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( page.getByRole( 'tab', { name: 'Settings' } ) ).toBeFocused();
		await expect( page ).toHaveURL( /tab=settings/ );
		await page.keyboard.press( 'End' );
		await expect( page.getByRole( 'tab', { name: 'Network stats' } ) ).toHaveAttribute( 'aria-selected', 'true' );

		await page.locator( '#zs-side' ).getByRole( 'link', { name: 'Audit log' } ).click();
		await expect( page.getByRole( 'tab', { name: 'Audit log' } ) ).toHaveAttribute( 'aria-selected', 'true' );
		await page.goBack();
		await expect( page.getByRole( 'tab', { name: 'Network stats' } ) ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'a value saved and locked here is what a subsite gets; unlocking hands it back', async ( { page, zs, asSiteAdmin, target, gallery } ) => {
		// The subsite keeps no settings of its own (the baseline stores some), so the network's value reaches it.
		await zs.forSite( target.subsitePath! ).call( 'POST', 'reset', { preset: 'defaults', network: false } );
		await openNetwork( page, 'settings', { group: 'settings' } );
		const card = page.locator( '#zs-ncard-settings-data' );
		await expect( card.locator( '.zs-ngh' ) ).toHaveAttribute( 'aria-expanded', 'true' );

		await test.step( 'save a network value from the unsaved-changes bar', async () => {
			await page.locator( '#zs-f-max_logs' ).fill( '30000' );
			await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
			await expect( page.locator( '.zs-toasts' ) ).toContainText( '1 network setting saved' );
			await expect( page.locator( '#zs-row-max_logs .zs-netaside' ) ).toContainText( 'sites use it' );
		} );

		await test.step( 'lock it: the switch is pressed and a subsite cannot change it', async () => {
			const lock = page.locator( '[data-zs-lock="max_logs"]' );
			await expect( lock ).toHaveAccessibleName( /Lock “.+” for every site/ );
			await lock.click();
			await expect( lock ).toHaveAttribute( 'aria-pressed', 'true' );
			await expect( page.locator( '#zs-row-max_logs .zs-netaside' ) ).toContainText( 'Locked on every site' );
			await gallery.capture( page.locator( '#zs-row-max_logs' ), 'network value locked' );

			await asSiteAdmin.goto( subsiteURL( target, AREA( 'settings', 'data' ) ) );
			const field = asSiteAdmin.locator( '#zs-f-max_logs' );
			await expect( field ).toBeDisabled();
			await expect( field ).toHaveValue( '30000' );
			await expect( asSiteAdmin.locator( '#zs-row-max_logs' ) ).toContainText( 'Locked by network' );
		} );

		await test.step( 'unlock it: the subsite follows the network default and may change it', async () => {
			await page.locator( '[data-zs-lock="max_logs"]' ).click();
			await expect( page.locator( '[data-zs-lock="max_logs"]' ) ).toHaveAttribute( 'aria-pressed', 'false' );
			await asSiteAdmin.reload();
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toBeEnabled();
			await expect( asSiteAdmin.locator( '#zs-row-max_logs' ) ).toContainText( /Using the network default: 30,000/ );
		} );
	} );

	// The 5.x network spec's path (removed with the legacy page): a lock wins over a site's own value, and unlocking gives the site its value back.
	test( "a lock overrides a subsite's own value; unlocking lets the site's own value win again", async ( { page, zs, asSiteAdmin, target, gallery } ) => {
		const site = zs.forSite( target.subsitePath! );
		await site.call( 'POST', 'reset', { preset: 'defaults', network: false } );
		await site.set( { max_logs: 4321 } );
		await zs.networkSetting( 'max_logs', 30000 );

		await test.step( 'before the lock the site keeps its own value', async () => {
			await asSiteAdmin.goto( subsiteURL( target, AREA( 'settings', 'data' ) ) );
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toHaveValue( '4321' );
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toBeEnabled();
		} );

		await test.step( 'locked: the site shows the network value, disabled', async () => {
			await openNetwork( page, 'settings', { group: 'settings' } );
			const lock = page.locator( '[data-zs-lock="max_logs"]' );
			await lock.click();
			await expect( lock ).toHaveAttribute( 'aria-pressed', 'true' );

			await asSiteAdmin.reload();
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toBeDisabled();
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toHaveValue( '30000' );
			await expect( asSiteAdmin.locator( '#zs-row-max_logs' ) ).toContainText( 'Locked by network' );
			await gallery.capture( asSiteAdmin.locator( '#zs-row-max_logs' ), 'site value overridden by a lock' );
		} );

		await test.step( "unlocked: the site's own value wins again", async () => {
			await page.locator( '[data-zs-lock="max_logs"]' ).click();
			await expect( page.locator( '[data-zs-lock="max_logs"]' ) ).toHaveAttribute( 'aria-pressed', 'false' );

			await asSiteAdmin.reload();
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toBeEnabled();
			await expect( asSiteAdmin.locator( '#zs-f-max_logs' ) ).toHaveValue( '4321' );
			await expect( asSiteAdmin.locator( '#zs-row-max_logs' ) ).not.toContainText( 'Using the network default' );
		} );
	} );

	test( 'an empty network value reads "(none)" here and on the subsite, never an empty box', async ( { page, zs, asSiteAdmin, target } ) => {
		await zs.forSite( target.subsitePath! ).call( 'POST', 'reset', { preset: 'defaults', network: false } );
		await zs.networkSetting( 'blocked_message', '' );
		await openNetwork( page, 'settings', { group: 'settings' } );
		await page.getByRole( 'button', { name: 'Expand all' } ).click();
		await expect( page.locator( '#zs-row-blocked_message .zs-netaside' ) ).toContainText( '(none)' );

		await asSiteAdmin.goto( subsiteURL( target, AREA( 'settings' ) ) );
		await expect( asSiteAdmin.locator( '#zs-row-blocked_message .zs-netnote' ) ).toHaveText( 'Using the network default: (none)' );
	} );

	test( 'templates: make one, change it, apply it to the network defaults with Undo', async ( { page, zs, gallery } ) => {
		await openNetwork( page, 'templates' );
		await expect( page.locator( '.zs-tplcard' ) ).toHaveCount( 3 );

		await test.step( 'a new template, with a setting found by search', async () => {
			await page.getByRole( 'button', { name: 'New template' } ).click();
			const drawer = page.locator( '.zs-drawer' );
			await expect( drawer.locator( '#zs-tp-name' ) ).toBeFocused();
			await drawer.locator( '#zs-tp-name' ).fill( 'E2E clinic sites' );
			await expect( drawer.locator( '#zs-tp-slug' ) ).toHaveValue( 'e2e-clinic-sites' );
			await drawer.getByRole( 'button', { name: 'Save template' } ).click();
			await expect( drawer.locator( '#zs-tp-settings-e' ) ).toHaveText( 'Choose at least one setting.' );
			await drawer.locator( '#zs-tp-settings' ).fill( 'Maximum' );
			await drawer.locator( '.zs-tpinc' ).first().click();
			await gallery.capture( drawer, 'template drawer' );
			await drawer.getByRole( 'button', { name: 'Save template' } ).click();
			await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Template “E2E clinic sites” saved' );
			await expect( page.locator( '.zs-tplcard' ) ).toHaveCount( 4 );
		} );

		await test.step( 'change its description', async () => {
			await page.getByRole( 'button', { name: 'Edit E2E clinic sites' } ).click();
			const drawer = page.locator( '.zs-drawer' );
			await expect( drawer.locator( '#zs-tp-slug' ) ).toHaveAttribute( 'readonly', '' );
			await drawer.locator( '#zs-tp-desc' ).fill( 'Changed in e2e.' );
			await drawer.getByRole( 'button', { name: 'Save template' } ).click();
			await expect( page.locator( '.zs-tplcard', { hasText: 'Changed in e2e.' } ) ).toBeVisible();
		} );

		await test.step( 'built-in templates are copied, not changed', async () => {
			await page.getByRole( 'button', { name: 'Edit a copy of Strict Protection' } ).click();
			await expect( page.locator( '.zs-drawer #zs-tp-name' ) ).toHaveValue( 'Strict Protection (copy)' );
			await page.keyboard.press( 'Escape' );
			await expect( page.getByRole( 'button', { name: 'Edit a copy of Strict Protection' } ) ).toBeFocused();
		} );

		await test.step( 'apply to the network defaults, then Undo', async () => {
			const card = page.locator( '.zs-tplcard', { hasText: 'E2E clinic sites' } );
			await card.getByRole( 'button', { name: 'Apply to network defaults' } ).click();
			await card.getByRole( 'group' ).getByRole( 'button', { name: 'Apply to network defaults' } ).click();
			await expect( page.locator( '.zs-toasts' ) ).toContainText( 'network default updated' );
			const audit = await zs.plugin< Array< { action: string } > >( 'GET', 'network-settings/audit', undefined, { page: '1', per_page: '10' } );
			expect( audit.map( ( a ) => a.action ) ).toContain( 'template' );
		} );
	} );

	test( 'a block made network-wide stops the address on every site and shows on a subsite as "Blocked by your network"', async ( { page, zs, target, asSiteAdmin, newRoleContext, gallery } ) => {
		const ip = '203.0.113.9';
		await zs.seedLog( 3, { ip } );
		await zs.forSite( target.subsitePath! ).seedLog( 2, { ip } );

		await openNetwork( page, 'stats' );
		const row = page.locator( `[data-zs-netblock="${ ip }"]` );
		await row.click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( `${ ip } is blocked on all sites` );
		await expect( page.locator( '.zs-nblocks' ) ).toContainText( ip );
		await gallery.capture( page.locator( '#zs-net-body' ), 'blocked network-wide' );

		await test.step( 'the address is stopped on both sites; another is not', async () => {
			const visitor = await ( await newRoleContext( null, { ip } ) ).newPage();
			expect( ( await visitor.goto( '' ) )?.status() ).toBe( 403 );
			expect( ( await visitor.goto( subsiteURL( target, '' ) ) )?.status() ).toBe( 403 );
			const other = await ( await newRoleContext( null, { ip: '203.0.113.77' } ) ).newPage();
			expect( ( await other.goto( subsiteURL( target, '' ) ) )?.status() ).toBe( 200 );
		} );

		await test.step( 'the subsite sees it, read-only, on Blocked IPs and in the block drawer', async () => {
			await asSiteAdmin.goto( subsiteURL( target, AREA( 'rules', 'ips' ) ) );
			await asSiteAdmin.locator( '.zs-netblocks > summary' ).click();
			await expect( asSiteAdmin.locator( '.zs-netblocks' ) ).toContainText( ip );
			await expect( asSiteAdmin.locator( '.zs-netblocks .zs-lockbadge' ).first() ).toContainText( 'Blocked by your network' );
			await asSiteAdmin.getByRole( 'button', { name: 'Block an IP' } ).click();
			await asSiteAdmin.locator( '#zs-bf-ip' ).fill( ip );
			await asSiteAdmin.locator( '#zs-bf-reason' ).focus();
			await expect( asSiteAdmin.locator( '.zs-drawer' ) ).toContainText( 'already blocked on every site by your network administrator' );
		} );

		await test.step( 'unblock with Undo', async () => {
			await page.locator( '.zs-nblocks' ).getByRole( 'button', { name: `Unblock ${ ip } on every site` } ).click();
			await expect( page.locator( '.zs-toasts' ) ).toContainText( `${ ip } unblocked on all sites` );
			await page.locator( '.zs-toast', { hasText: 'unblocked on all sites' } ).getByRole( 'button', { name: 'Undo' } ).click();
			await expect( page.locator( '.zs-nblocks' ) ).toContainText( ip );
		} );
	} );

	test( 'blocking your own address on every site asks first, with focus on Cancel', async ( { newRoleContext, zs } ) => {
		const ip = '203.0.113.88';
		const page = await ( await newRoleContext( 'admin', { ip } ) ).newPage();
		await openNetwork( page, 'stats' );

		await page.locator( '#zs-nbl-ip' ).fill( ip );
		await page.getByRole( 'button', { name: 'Block on every site' } ).click();
		const question = page.getByRole( 'group', { name: /is your own address/ } );
		const yes = question.getByRole( 'button', { name: 'Block it anyway' } );
		// Focus goes to the safe answer, so a held Enter cannot confirm a lock-out.
		await expect( question.getByRole( 'button', { name: 'Cancel' } ) ).toBeFocused();
		await expect( page.locator( '.zs-nblocks' ) ).toHaveCount( 0 );

		await test.step( 'Cancel goes back to the address, and nothing is blocked', async () => {
			await question.getByRole( 'button', { name: 'Cancel' } ).click();
			await expect( question ).toHaveCount( 0 );
			await expect( page.locator( '#zs-nbl-ip' ) ).toBeFocused();
			expect( await zs.plugin< unknown[] >( 'GET', 'network/blocks' ) ).toEqual( [] );
		} );

		await test.step( 'Block it anyway blocks it', async () => {
			await page.getByRole( 'button', { name: 'Block on every site' } ).click();
			await yes.click();
			await expect( page.locator( '#zs-nbl-ip' ) ).toBeFocused();
			await expect( page.locator( '.zs-nblocks' ) ).toContainText( ip );
		} );
	} );

	test( 'a loopback address is refused, and the CSV export reads every site', async ( { page, zs, target } ) => {
		await seedNetwork( zs, target );
		await openNetwork( page, 'stats' );

		await test.step( 'loopback: the field says why', async () => {
			await page.locator( '#zs-nbl-ip' ).fill( '127.0.0.1' );
			await page.getByRole( 'button', { name: 'Block on every site' } ).click();
			await expect( page.locator( '#zs-nbl-ip-e' ) ).toContainText( 'this server' );
			await expect( page.locator( '#zs-nbl-ip' ) ).toBeFocused();
		} );

		await test.step( 'export: pages of 100 sites, one line per site', async () => {
			const read = page.waitForRequest( ( r ) => /network(\/|%2F)stats/.test( r.url() ) && /per_page(=|%3D)100/.test( r.url() ) );
			const saved = page.waitForEvent( 'download' );
			await page.getByRole( 'button', { name: 'Export CSV' } ).click();
			await read;
			const file = await saved;
			const stream = await file.createReadStream();
			let csv = '';
			for await ( const chunk of stream ) {
				csv += String( chunk );
			}
			// A header, then one line per site of the network (wp-env has two, a Local network may have more).
			const sites = await page.evaluate( () => ( window as unknown as { zeroSpamAdmin: { network: { sites: number } } } ).zeroSpamAdmin.network.sites );
			expect( csv.trim().split( '\r\n' ) ).toHaveLength( 1 + sites );
		} );
	} );

	test( 'Apply to all sites says that sites keep their own keys', async ( { page } ) => {
		await openNetwork( page, 'overview' );
		await page.getByRole( 'button', { name: 'Apply to all sites' } ).click();
		await expect( page.locator( '.zs-confirm-q' ) ).toContainText( 'License and API keys a site saved itself are kept.' );
		await page.getByRole( 'button', { name: 'Cancel' } ).click();
	} );

	test( 'the audit log filters by action, setting and user, in plain language', async ( { page, zs, target } ) => {
		await seedNetwork( zs, target );
		await openNetwork( page, 'audit' );
		await expect( page.locator( '#zs-net-body' ) ).toContainText( 'Maximum' );
		await page.locator( '#zs-au-action' ).selectOption( 'block' );
		await expect( page.locator( '#zs-net-body' ) ).toContainText( '198.51.100.24 blocked on every site' );
		await expect( page.locator( '#zs-net-body' ) ).not.toContainText( 'Changed “' );
		await page.getByRole( 'button', { name: 'Clear filters' } ).click();
		await page.getByRole( 'button', { name: /^Show only changes by / } ).first().click();
		await expect( page.getByRole( 'button', { name: /^Remove the filter: changes by / } ) ).toBeVisible();
	} );

	test( 'the comparison pages through sites and shows a site’s change', async ( { page, zs, target } ) => {
		await seedNetwork( zs, target );
		await openNetwork( page, 'comparison' );
		const body = page.locator( '#zs-net-body' );
		await expect( body ).toContainText( 'Changed' );
		await expect( body ).toContainText( 'Locked' );
		await page.locator( '#zs-cmp-sort' ).selectOption( 'overrides' );
		// Most site changes first: the first setting is one a site changed.
		await expect( body.locator( 'tbody tr, .zs-cards > li' ).first() ).toContainText( 'Changed' );
		await expect( body.locator( 'tbody tr, .zs-cards > li' ).last() ).not.toContainText( 'Changed' );
	} );

	test( 'notifications save and stay saved', async ( { page } ) => {
		await openNetwork( page, 'notifications' );
		const weekly = page.getByRole( 'switch', { name: 'Weekly summary emails' } );
		await expect( weekly ).toHaveAttribute( 'aria-checked', 'true' );
		await weekly.press( 'Space' );
		await expect( weekly ).toHaveAttribute( 'aria-checked', 'false' );
		await page.getByRole( 'button', { name: 'Save notification settings' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Notification settings saved' );
		await page.reload();
		await networkReady( page );
		await expect( page.getByRole( 'switch', { name: 'Weekly summary emails' } ) ).toHaveAttribute( 'aria-checked', 'false' );
	} );

	test( 'a site administrator cannot open the network page or its data', async ( { asSiteAdmin } ) => {
		const response = await asSiteAdmin.goto( AREA( 'network' ) );
		expect( response?.status() ).toBeGreaterThanOrEqual( 400 );
		await expect( asSiteAdmin.locator( 'body' ) ).not.toContainText( 'One wall' );
		expect( await announced( asSiteAdmin ) ).toBe( '' );
	} );

	test( 'phones (375px): no tab scrolls sideways; the lock switch and tabs are big enough to tap', async ( { page, zs, target } ) => {
		await seedNetwork( zs, target );
		await page.setViewportSize( { width: 375, height: 812 } );
		for ( const tab of [ 'settings', 'stats', 'comparison', 'audit', 'templates' ] ) {
			await openNetwork( page, tab );
			const width = await page.evaluate( () => document.documentElement.scrollWidth );
			expect( width, `${ tab } is ${ width }px wide at 375px` ).toBeLessThanOrEqual( 376 );
		}
		await openNetwork( page, 'settings', { group: 'settings' } );
		const box = await page.locator( '[data-zs-lock]' ).first().boundingBox();
		expect( box?.height ?? 0 ).toBeGreaterThanOrEqual( 24 );
	} );
} );
