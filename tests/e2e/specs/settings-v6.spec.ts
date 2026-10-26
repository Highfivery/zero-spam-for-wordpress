/**
 * The 6.0 Settings area (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json): no
 * request of its own on load, the six tabs from the keyboard, unsaved
 * changes across tabs with per-setting errors, display preferences applied
 * live, the quota reset date control, import and export with per-setting
 * results, the error log, rescue mode, a subsite's locked and
 * network-provided settings, and phones (375px, WCAG 2.4.11 and 2.5.8).
 * The accessibility scans of every tab in both themes are in a11y.spec.ts
 * (screens.ts, V6_SETTINGS).
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { AREA, settingsReady } from '../support/screens';
import { subsiteURL } from '../support/targets';

const SETTINGS = AREA( 'settings' );

/** Opens a Settings tab and waits for its cards. */
async function openSettings( page: Page, tab = '', extra: Record< string, string > = {} ) {
	await page.goto( AREA( 'settings', tab, extra ) );
	await settingsReady( page );
}

/**
 * Presses Tab until the focused element matches a selector.
 *
 * @param page     Page.
 * @param selector CSS selector.
 * @param max      Maximum key presses.
 */
async function tabTo( page: Page, selector: string, max = 150 ): Promise< boolean > {
	for ( let i = 0; i < max; i++ ) {
		await page.keyboard.press( 'Tab' );
		if ( await page.evaluate( ( sel ) => !! document.activeElement?.matches( sel ), selector ) ) {
			return true;
		}
	}
	return false;
}

/** Text of the screen reader live regions. */
const announced = ( page: Page ) => page.locator( '[data-zs-live]' ).allTextContents().then( ( t ) => t.join( ' ' ) );

test.describe( '6.0 Settings', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'every tab loads with no REST request of its own (all preloaded)', async ( { page } ) => {
		const rest: string[] = [];
		const onRequest = ( r: Request ) => {
			const url = r.url();
			if ( url.includes( 'zero-spam/v1' ) || url.includes( 'zero-spam%2Fv1' ) ) {
				rest.push( `${ r.method() } ${ url }` );
			}
		};
		page.on( 'request', onRequest );
		for ( const tab of [ '', 'data', 'api', 'advanced', 'debug', 'io' ] ) {
			await openSettings( page, tab );
			await page.waitForTimeout( 800 );
		}
		// The Overview's "Trusted proxies" link opens Advanced with its data.
		await openSettings( page, '', { section: 'proxies' } );
		await expect( page.locator( '#zs-st-advanced' ) ).toHaveAttribute( 'aria-selected', 'true' );
		await page.waitForTimeout( 800 );
		page.off( 'request', onRequest );
		expect( rest ).toEqual( [] );
	} );

	test( 'the tabs work from the keyboard and keep their place in the address', async ( { page } ) => {
		await openSettings( page );
		const general = page.getByRole( 'tab', { name: 'General' } );
		await general.focus();
		await page.keyboard.press( 'ArrowRight' );
		await expect( page.getByRole( 'tab', { name: 'Logging & data' } ) ).toBeFocused();
		await expect( page.getByRole( 'tab', { name: 'Logging & data' } ) ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( page ).toHaveURL( /tab=data/ );
		await expect( page.getByRole( 'tabpanel' ) ).toHaveAttribute( 'aria-labelledby', 'zs-st-data' );
		await page.keyboard.press( 'End' );
		await expect( page.getByRole( 'tab', { name: 'Import & errors' } ) ).toBeFocused();
		await page.keyboard.press( 'Home' );
		await expect( general ).toBeFocused();
		await page.keyboard.press( 'ArrowLeft' );
		await expect( page.getByRole( 'tab', { name: 'Import & errors' } ) ).toHaveAttribute( 'aria-selected', 'true' );

		// Only the selected tab is in the Tab order; Tab moves into the panel.
		await expect( page.locator( '[role="tab"][tabindex="0"]' ) ).toHaveCount( 1 );
		await page.goBack();
		await expect( general ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'changes on several tabs are saved together, with Undo', async ( { page, zs } ) => {
		await openSettings( page, 'data' );
		await page.locator( '#zs-f-max_logs' ).fill( '5000' );
		await page.getByRole( 'tab', { name: 'API monitoring' } ).click();
		await page.locator( '#zs-f-threshold_slow_response' ).fill( '7000' );

		const bar = page.locator( '.zs-savebar' );
		await expect( bar ).toContainText( 'You have 2 unsaved changes.' );
		await expect( page.locator( '#zs-st-data .zs-sr-only' ) ).toHaveText( '1 unsaved change' );
		await expect( page.locator( '#zs-st-api .zs-sr-only' ) ).toHaveText( '1 unsaved change' );

		await bar.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( bar ).toHaveCount( 0 );
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '2 settings saved' );

		const options = await zs.options( 'zero-spam-' );
		expect( JSON.stringify( options ) ).toContain( '7000' );

		await page.reload();
		await settingsReady( page );
		await expect( page.locator( '#zs-f-threshold_slow_response' ) ).toHaveValue( '7000' );

		// Undo restores both.
		await page.locator( '#zs-f-threshold_slow_response' ).fill( '8000' );
		await bar.getByRole( 'button', { name: 'Save changes' } ).click();
		await page.locator( '.zs-toast-btn' ).last().click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Changes undone' );
		await expect( page.locator( '#zs-f-threshold_slow_response' ) ).toHaveValue( '7000' );
	} );

	test( 'a refused value is marked, described, announced and focused; nothing is saved', async ( { page } ) => {
		await openSettings( page, 'debug' );
		await page.locator( '#zs-f-debug_ip' ).fill( '192.0.2.10' );
		await page.getByRole( 'tab', { name: 'Advanced' } ).click();
		await page.locator( '#zs-f-trusted_proxies' ).fill( 'not-an-ip\n0.0.0.0/0' );
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();

		const field = page.locator( '#zs-f-trusted_proxies' );
		await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( field ).toHaveAttribute( 'aria-describedby', /zs-e-trusted_proxies/ );
		await expect( page.locator( '#zs-e-trusted_proxies' ) ).toContainText( 'not-an-ip' );
		await expect( page.locator( '#zs-e-trusted_proxies' ) ).toContainText( 'too broad' );
		await expect( field ).toBeFocused();
		await expect( page.locator( '.zs-savebar' ) ).toContainText( 'Nothing was saved. Check the 1 highlighted setting.' );
		await expect.poll( () => announced( page ) ).toContain( 'Nothing was saved' );

		// Fixing it saves both changes.
		await field.fill( '203.0.113.5' );
		await expect( field ).not.toHaveAttribute( 'aria-invalid', 'true' );
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '2 settings saved' );
	} );

	test( 'Discard and the leave-page warning', async ( { page } ) => {
		await openSettings( page, 'data' );
		await page.locator( '#zs-f-max_logs' ).fill( '5000' );
		let warned = false;
		page.once( 'dialog', async ( dialog ) => {
			warned = 'beforeunload' === dialog.type();
			await dialog.dismiss();
		} );
		await page.locator( '.zs-side .zs-nav a[href*="zero-spam-activity"]' ).click();
		await expect.poll( () => warned ).toBe( true );
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Discard' } ).click();
		await expect( page.locator( '#zs-f-max_logs' ) ).toHaveValue( '10000' );
		await expect( page.locator( '.zs-savebar' ) ).toHaveCount( 0 );
	} );

	test( 'display preferences apply at once, without the unsaved bar, and persist', async ( { page } ) => {
		// The switch is locked on while the device asks for reduced motion (the suite's default).
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await openSettings( page );
		const app = page.locator( '#zerospam-app' );

		await page.getByRole( 'radio', { name: /^Dark/ } ).check();
		await expect( app ).toHaveAttribute( 'data-zs-theme', 'dark' );
		await expect( page.locator( '.zs-savebar' ) ).toHaveCount( 0 );
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Theme: Dark. Saved for you.' );

		const toggle = page.getByRole( 'switch', { name: 'Reduce animations' } );
		await toggle.focus();
		await page.keyboard.press( 'Space' );
		await expect( toggle ).toHaveAttribute( 'aria-checked', 'true' );
		await expect( app ).toHaveAttribute( 'data-zs-motion', 'reduce' );

		await page.waitForTimeout( 500 );
		await page.reload();
		await expect( app ).toHaveAttribute( 'data-zs-theme', 'dark' );
		await expect( app ).toHaveAttribute( 'data-zs-motion', 'reduce' );

		// Undo from the toast puts the theme back.
		await settingsReady( page );
		await page.getByRole( 'radio', { name: /^Light/ } ).check();
		await page.locator( '.zs-toast-btn' ).last().click();
		await expect( app ).toHaveAttribute( 'data-zs-theme', 'dark' );
	} );

	test( 'the quota reset date is a keyboard-usable date control with its next reset', async ( { page } ) => {
		await openSettings( page, 'api' );
		const date = page.getByLabel( 'Quota Reset Date (Override)' );
		await expect( date ).toHaveAttribute( 'type', 'date' );
		await expect( page.locator( '#zs-quota-next' ) ).toContainText( 'No reset date set' );

		await date.focus();
		await date.pressSequentially( '01152099' );
		await expect( date ).toHaveValue( '2099-01-15' );
		await expect( page.locator( '#zs-quota-next' ) ).toContainText( 'Next reset: Jan 15, 2099' );

		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '1 setting saved' );

		await page.getByRole( 'button', { name: 'Clear date' } ).click();
		await expect( date ).toBeFocused();
		await expect( date ).toHaveValue( '' );
	} );

	test( 'the five thresholds take numbers within their range, with units', async ( { page } ) => {
		await openSettings( page, 'api' );
		const spike = page.locator( '#zs-f-threshold_usage_spike' );
		await expect( spike ).toHaveAttribute( 'min', '100' );
		await expect( spike ).toHaveAttribute( 'aria-describedby', /zs-u-threshold_usage_spike/ );
		await expect( page.locator( '#zs-u-threshold_slow_response' ) ).toHaveText( 'ms' );

		await spike.fill( '50' );
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '#zs-e-threshold_usage_spike' ) ).toContainText( 'at least 100' );
	} );

	test( 'Delete Data on Uninstall is on by default and can be turned off', async ( { page, zs } ) => {
		await openSettings( page, 'data' );
		await expect( page.locator( '#zs-card-uninstall' ) ).toBeVisible();
		const toggle = page.getByRole( 'switch', { name: 'Delete Data on Uninstall' } );
		await expect( toggle ).toHaveAttribute( 'aria-checked', 'true' );
		await toggle.click();
		await page.locator( '.zs-savebar' ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '1 setting saved' );
		const options = ( await zs.options( 'zero-spam-api-monitoring' ) ) as unknown as Record< string, unknown >;
		expect( JSON.stringify( options ) ).not.toMatch( /"delete_data_on_uninstall":"enabled"/ );
	} );

	test( 'export downloads JSON; import reports applied and skipped settings', async ( { page } ) => {
		await openSettings( page, 'io' );

		const [ download ] = await Promise.all( [ page.waitForEvent( 'download' ), page.getByRole( 'button', { name: 'Download settings' } ).click() ] );
		expect( download.suggestedFilename() ).toMatch( /^zero-spam-settings-.+-\d{4}-\d{2}-\d{2}\.json$/ );

		const file = {
			name: 'settings.json',
			mimeType: 'application/json',
			buffer: Buffer.from(
				JSON.stringify( {
					format: 'zero-spam-settings',
					version: 1,
					settings: {
						settings: { max_logs: 600 },
						'api-monitoring': { threshold_quota_warning: 70, alert_webhook_url: 'https://hooks.example.com/e2e', quota_reset_date: '2026-02-30' },
					},
				} )
			),
		};
		const picker = page.getByLabel( /Import settings Choose a file/ );
		await expect( picker ).toHaveAttribute( 'type', 'file' );
		await picker.setInputFiles( file );
		await expect( page.locator( '#zs-imp-n' ) ).toHaveText( 'settings.json' );
		await page.locator( '.zs-io button', { hasText: 'Import settings' } ).click();
		await page.getByRole( 'group' ).getByRole( 'button', { name: 'Import' } ).click();

		const results = page.getByRole( 'region', { name: 'Import results' } );
		await expect( results ).toContainText( '2 settings applied' );
		await expect( results ).toContainText( 'Skipped: keys and webhook addresses' );
		await expect( results ).toContainText( 'Webhook URL' );
		await expect( results ).toContainText( 'Not applied: invalid' );
		await expect.poll( () => announced( page ) ).toContain( 'Import finished: 2 applied, 2 not applied.' );

		// With the opt-in, the webhook address is imported too.
		await page.getByLabel( 'Also import license and API keys and webhook addresses found in the file' ).check();
		await picker.setInputFiles( file );
		await page.locator( '.zs-io button', { hasText: 'Import settings' } ).click();
		await page.getByRole( 'group' ).getByRole( 'button', { name: 'Import' } ).click();
		await expect( results ).toContainText( '3 settings applied' );
		await expect( results ).not.toContainText( 'Skipped: keys' );

		await page.getByRole( 'tab', { name: 'Logging & data' } ).click();
		await expect( page.locator( '#zs-f-max_logs' ) ).toHaveValue( '600' );
	} );

	test( 'the file picker is reached with the keyboard and shows its focus on the button', async ( { page } ) => {
		await openSettings( page, 'io' );
		await page.getByRole( 'button', { name: 'Download settings' } ).focus();
		await page.keyboard.press( 'Tab' );
		await expect( page.locator( '#zs-imp-file' ) ).toBeFocused();
		const ring = await page.locator( '.zs-filebtn' ).evaluate( ( el ) => getComputedStyle( el ).outlineStyle );
		expect( ring ).not.toBe( 'none' );
	} );

	test( 'the error log shows the latest entries and can be cleared after a confirmation', async ( { page, zs } ) => {
		await zs.call( 'POST', 'error-log', { message: 'Zero Spam e2e: sample error log entry.' } );
		await openSettings( page, 'io' );
		await expect( page.getByLabel( 'Recent errors' ) ).toHaveValue( /sample error log entry/ );

		await page.getByRole( 'button', { name: 'Clear error log' } ).click();
		const confirm = page.getByRole( 'group', { name: /deletes the error log/ } );
		await expect( confirm.getByRole( 'button', { name: 'Clear' } ) ).toBeFocused();
		await confirm.getByRole( 'button', { name: 'Clear' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Error log cleared' );
		await expect( page.locator( '.zs-errlog' ) ).toContainText( 'The error log is empty.' );
	} );

	test( 'rescue mode is Active (wp-env defines ZEROSPAM_RESCUE_KEY); no-lockout addresses link to Rules', async ( { page } ) => {
		// "Rescue key not set" is covered by Vitest (settings/test/render.test.js) and PHPUnit (Admin_App::rescue_key_set()).
		await openSettings( page, 'advanced' );
		const card = page.locator( '#zs-card-rescue' );
		await expect( card.locator( '.zs-pcard-badges' ) ).toContainText( 'Active' );
		await expect( card ).toContainText( 'Emailed rescue links are on' );
		await expect( card ).not.toContainText( 'Rescue key not set' );
		await expect( page.locator( '#zs-card-nolockout' ).getByRole( 'link', { name: 'Manage in Rules' } ) ).toHaveAttribute( 'href', /page=zero-spam-rules&tab=no-lockout/ );
	} );

	test( 'the system report is copied to the clipboard', async ( { page, context } ) => {
		await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
		await openSettings( page, 'debug' );
		await page.getByRole( 'button', { name: 'Copy system report' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Copied to clipboard' );
		const text = await page.evaluate( () => navigator.clipboard.readText() );
		expect( text ).toMatch( /^Zero Spam \S+ · WordPress \S+ · PHP \S+ · Basic · / );
		expect( text ).not.toMatch( /\d+\.\d+\.\d+\.\d+/ );
	} );

	test( 'the classic settings page stays reachable', async ( { page } ) => {
		await page.goto( 'wp-admin/options-general.php?page=wordpress-zero-spam-settings&subview=settings' );
		await expect( page.locator( '#wpbody-content form' ).first() ).toBeVisible();
	} );

	test( 'phones: no sideways scrolling, 24px targets, focus never hidden behind the bars', async ( { page } ) => {
		await page.setViewportSize( { width: 375, height: 812 } );
		for ( const tab of [ '', 'api', 'io' ] ) {
			await openSettings( page, tab );
			expect( await page.evaluate( () => document.documentElement.scrollWidth ), `no sideways scrolling (${ tab || 'general' })` ).toBeLessThanOrEqual( 376 );
			const small = await page.evaluate( () =>
				Array.from( document.querySelectorAll( '#zs-set-body :is(button, input, select, textarea, a, [role="switch"])' ) )
					.filter( ( el ) => {
						const r = el.getBoundingClientRect();
						return r.width > 0 && r.height > 0 && ( r.width < 24 || r.height < 24 ) && 'checkbox' !== ( el as HTMLInputElement ).type;
					} )
					.map( ( el ) => el.id || el.textContent?.trim() || el.tagName )
			);
			expect( small, 'WCAG 2.5.8: targets of at least 24 by 24 px' ).toEqual( [] );
		}

		// With the unsaved bar open, a focused control is not covered by the top bar, "On this page" or the bar (2.4.11).
		await openSettings( page, 'api' );
		await page.locator( '#zs-f-api_retention' ).fill( '30' );
		await expect( page.locator( '.zs-savebar' ) ).toBeVisible();
		expect( await tabTo( page, '#zs-f-threshold_slow_response' ) ).toBe( true );
		const hidden = await page.evaluate( () => {
			const el = document.activeElement as Element;
			const r = el.getBoundingClientRect();
			const top = Math.max( ...Array.from( document.querySelectorAll( '.zs-topbar, .zs-jump' ) ).map( ( b ) => b.getBoundingClientRect().bottom ) );
			const bottom = Math.min( ...Array.from( document.querySelectorAll( '.zs-savebar, .zs-bnav' ) ).map( ( b ) => b.getBoundingClientRect().top ) );
			return r.top < top || r.bottom > bottom;
		} );
		expect( hidden ).toBe( false );
	} );
} );

test.describe( '6.0 Settings on a subsite', { tag: '@multisite' }, () => {
	test.beforeAll( async ( { zs } ) => {
		await zs.reset( 'baseline', { network: true } );
		await zs.networkSetting( 'share_data', 'enabled', true );
		await zs.networkSetting( 'max_logs', 20000 );
		await zs.networkSetting( 'alert_webhook_url', 'https://hooks.example.com/e2e-network-hook' );
	} );

	test.afterAll( async ( { zs } ) => {
		await zs.reset( 'baseline', { network: true } );
	} );

	test( 'locked settings are badged and disabled; network secrets are masked', async ( { asSiteAdmin, target } ) => {
		const page = asSiteAdmin;
		await page.goto( subsiteURL( target, AREA( 'settings', 'data' ) ) );
		await settingsReady( page );

		await expect( page.locator( '.zs-netact' ) ).toContainText( 'locked by your network administrator' );
		const share = page.locator( '#zs-row-share_data' );
		await expect( share.locator( '.zs-badge-lock' ) ).toHaveText( 'Locked by network' );
		await expect( share.getByRole( 'switch' ) ).toBeDisabled();
		await expect( share.getByRole( 'switch' ) ).toHaveAttribute( 'aria-describedby', /zs-l-share_data/ );
		await expect( page.locator( '#zs-row-max_logs' ) ).toContainText( 'Using the network default: 20,000' );

		await page.getByRole( 'tab', { name: 'API monitoring' } ).click();
		const hook = page.locator( '#zs-row-alert_webhook_url' );
		await expect( hook ).toContainText( '••••' );
		await expect( hook ).toContainText( 'Set by your network administrator' );
		await expect( hook ).not.toContainText( 'e2e-network-hook' );
		expect( await page.content() ).not.toContain( 'e2e-network-hook' );
	} );
} );

test( 'the Settings page needs manage_options', async ( { asEditor } ) => {
	const response = await asEditor.goto( SETTINGS );
	expect( response?.status() ).toBe( 403 );
} );
