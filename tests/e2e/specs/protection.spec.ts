/**
 * The 6.0 Protection area (ZEROSPAM_ADMIN_V6 is on in .wp-env*.json): no
 * request of its own on load, the tabs from the keyboard, cards built from
 * the settings schema (a third-party section included), saving with the
 * unsaved-changes bar (per-setting errors, Undo), the two new Security
 * switches, the license key flow, "Test my protection", the Compare drawer,
 * a locked card on a subsite, and the phone layout.
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { focusObscured } from '../support/focus';
import { AREA, protectionReady } from '../support/screens';
import { subsiteURL } from '../support/targets';

const PROTECTION = AREA( 'protection' );

/**
 * Opens a Protection tab and waits for its cards.
 *
 * @param page Page.
 * @param tab  Tab.
 */
async function open( page: Page, tab = '' ) {
	await page.goto( AREA( 'protection', tab ) );
	await protectionReady( page );
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

/** Whether the focused element draws the focus ring. */
const focusRing = ( page: Page ) =>
	page.evaluate( () => {
		const style = getComputedStyle( document.activeElement as Element );
		return 'none' !== style.outlineStyle && parseFloat( style.outlineWidth ) >= 2;
	} );

/** The text the app's polite live region speaks. */
const spoken = ( page: Page ) => page.locator( '[data-zs-live="polite"]' );

test.describe( '6.0 Protection', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await zs.selfTestReset();
	} );

	test( 'makes no REST request of its own on load, on any tab', async ( { page } ) => {
		const rest: string[] = [];
		const onRequest = ( r: Request ) => {
			const url = r.url();
			if ( url.includes( 'zero-spam/v1' ) || url.includes( 'zero-spam%2Fv1' ) ) {
				rest.push( `${ r.method() } ${ url }` );
			}
		};
		page.on( 'request', onRequest );
		for ( const tab of [ '', 'integrations', 'intelligence' ] ) {
			await open( page, tab );
			await page.waitForTimeout( 800 );
		}
		page.off( 'request', onRequest );
		expect( rest ).toEqual( [] );
	} );

	test( 'tabs move with the arrow keys, Home and End, and keep the address', async ( { page } ) => {
		await open( page );
		const detection = page.getByRole( 'tab', { name: 'Detection' } );
		await expect( detection ).toHaveAttribute( 'aria-selected', 'true' );
		await detection.focus();
		expect( await focusRing( page ) ).toBe( true );

		await page.keyboard.press( 'ArrowRight' );
		await expect( page.getByRole( 'tab', { name: 'Integrations' } ) ).toBeFocused();
		await expect( page.getByRole( 'tab', { name: 'Integrations' } ) ).toHaveAttribute( 'aria-selected', 'true' );
		await expect( page ).toHaveURL( /tab=integrations/ );
		await expect( page.getByRole( 'tabpanel' ) ).toHaveAttribute( 'aria-labelledby', 'zs-pt-integrations' );

		await page.keyboard.press( 'End' );
		await expect( page.getByRole( 'tab', { name: 'Intelligence' } ) ).toBeFocused();
		await page.keyboard.press( 'ArrowRight' );
		await expect( page.getByRole( 'tab', { name: 'Detection' } ) ).toBeFocused();
		await page.keyboard.press( 'End' );
		await page.keyboard.press( 'Home' );
		await expect( page.getByRole( 'tab', { name: 'Detection' } ) ).toHaveAttribute( 'aria-selected', 'true' );

		await page.goBack();
		await expect( page.getByRole( 'tab', { name: 'Intelligence' } ) ).toHaveAttribute( 'aria-selected', 'true' );
	} );

	test( 'Detection has a card per section with its switch, status, signals and Fine-tune', async ( { page } ) => {
		await open( page );
		for ( const title of [ 'Comments', 'Registration', 'User login', 'David Walsh browser check', 'Honeypot', 'Security' ] ) {
			await expect( page.getByRole( 'heading', { level: 2, name: title, exact: true } ) ).toBeVisible();
		}
		const comments = page.locator( '#zs-card-comments' );
		await expect( comments.getByRole( 'switch' ).first() ).toHaveAttribute( 'aria-checked', 'true' );
		await expect( comments.locator( '.zs-badge' ).first() ).toContainText( 'On' );
		await expect( comments.getByRole( 'list', { name: 'Supported signals' } ) ).toContainText( 'Honeypot' );
		await expect( comments.locator( '.zs-pcard-st' ) ).toContainText( /in the last 7 days|No detections/ );

		const fine = comments.locator( '.zs-fine > summary' );
		await expect( comments.locator( '.zs-fine' ) ).not.toHaveAttribute( 'open', '' );
		await fine.focus();
		await page.keyboard.press( 'Enter' );
		await expect( comments.locator( '.zs-fine' ) ).toHaveAttribute( 'open', '' );

		// Every control has an accessible name.
		const unnamed = await page.locator( '#zs-prot-body' ).evaluate( ( body ) =>
			Array.from( body.querySelectorAll( 'input, select, textarea, button, [role="switch"]' ) )
				.filter( ( el ) => ( el as HTMLElement ).offsetParent !== null )
				.filter( ( el ) => {
					const name = ( el.getAttribute( 'aria-label' ) || '' ) +
						( el.getAttribute( 'aria-labelledby' ) || '' ) +
						( ( el as HTMLInputElement ).labels?.length ? 'label' : '' ) +
						( el.textContent || '' ).trim();
					return '' === name.trim() && 'radio' !== ( el as HTMLInputElement ).type;
				} )
				.map( ( el ) => el.outerHTML.slice( 0, 120 ) )
		);
		expect( unnamed ).toEqual( [] );
	} );

	test( 'the new Security switches are on, and turning one off is saved as disabled', async ( { page, zs } ) => {
		await open( page );
		const card = page.locator( '#zs-card-security' );
		const xmlrpc = card.getByRole( 'switch', { name: 'Disable XML-RPC' } );
		const version = card.getByRole( 'switch', { name: 'Hide the WordPress Version' } );
		await expect( xmlrpc ).toHaveAttribute( 'aria-checked', 'true' );
		await expect( version ).toHaveAttribute( 'aria-checked', 'true' );

		await xmlrpc.focus();
		await page.keyboard.press( 'Space' );
		await expect( xmlrpc ).toHaveAttribute( 'aria-checked', 'false' );
		const bar = page.getByRole( 'form', { name: 'Unsaved changes' } );
		await expect( bar ).toContainText( 'You have 1 unsaved change.' );
		await expect( page.getByRole( 'tab', { name: /Detection/ } ) ).toContainText( '1 unsaved change' );

		// apiFetch sends PATCH as POST with X-HTTP-Method-Override.
		const patch = page.waitForRequest( ( r ) => /zero-spam(\/|%2F)v1(\/|%2F)settings(\?|&|$)/.test( r.url() ) && 'PATCH' === ( r.headers()[ 'x-http-method-override' ] ?? r.method() ) );
		await bar.getByRole( 'button', { name: 'Save changes' } ).click();
		expect( ( await patch ).postDataJSON() ).toEqual( { disable_xmlrpc: false } );
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '1 setting saved' );
		await expect( bar ).toHaveCount( 0 );

		const options = await zs.options( 'zero-spam-security' );
		expect( ( options[ 'zero-spam-security' ] as Record< string, unknown > ).disable_xmlrpc ).toBe( 'disabled' );

		await page.reload();
		await protectionReady( page );
		await expect( page.locator( '#zs-card-security' ).getByRole( 'switch', { name: 'Disable XML-RPC' } ) ).toHaveAttribute( 'aria-checked', 'false' );
	} );

	test( 'Undo restores a saved change; Discard drops unsaved ones', async ( { page, zs } ) => {
		await open( page );
		const login = page.locator( '#zs-card-login' ).getByRole( 'switch' ).first();
		await login.click();
		await page.getByRole( 'form', { name: 'Unsaved changes' } ).getByRole( 'button', { name: 'Discard' } ).click();
		await expect( login ).toHaveAttribute( 'aria-checked', 'true' );
		await expect( page.getByRole( 'form', { name: 'Unsaved changes' } ) ).toHaveCount( 0 );

		await login.click();
		await page.getByRole( 'form', { name: 'Unsaved changes' } ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '1 setting saved' );
		await page.locator( '.zs-toast', { hasText: '1 setting saved' } ).getByRole( 'button', { name: 'Undo' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'Changes undone' );
		await expect( login ).toHaveAttribute( 'aria-checked', 'true' );
		const options = await zs.options( 'zero-spam-login' );
		expect( ( options[ 'zero-spam-login' ] as Record< string, unknown > ).verify_login ).toBe( 'enabled' );
	} );

	test( 'an invalid value is refused with its message on the field, and nothing is saved', async ( { page } ) => {
		await open( page, 'intelligence' );
		const card = page.locator( '#zs-card-stop_forum_spam' );
		await card.locator( '.zs-fine > summary' ).click();
		const field = card.locator( 'input[type="number"]' ).first();
		const name = await field.getAttribute( 'id' );
		// The browser keeps any number; the server knows the limits.
		await field.fill( '-5' );
		await page.getByRole( 'form', { name: 'Unsaved changes' } ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		const error = page.locator( `#${ name?.replace( 'zs-f-', 'zs-e-' ) }` );
		await expect( error ).toBeVisible();
		await expect( field ).toHaveAttribute( 'aria-describedby', new RegExp( name?.replace( 'zs-f-', 'zs-e-' ) ?? 'x' ) );
		await expect( page.getByRole( 'form', { name: 'Unsaved changes' } ) ).toContainText( 'Nothing was saved' );
		await expect( field ).toBeFocused();
	} );

	test( 'Custom Form Selectors is a text field saved like any other', async ( { page, zs } ) => {
		await open( page );
		const card = page.locator( '#zs-card-davidwalsh' );
		await card.locator( '.zs-fine > summary' ).click();
		const field = card.getByLabel( 'Custom Form Selectors' );
		await expect( field ).toHaveAttribute( 'type', 'text' );
		await field.fill( '.e2e-form, #e2e-signup' );
		await expect( card.locator( '.zs-help > summary', { hasText: /How to Find Your Form/i } ) ).toBeVisible();
		await page.getByRole( 'form', { name: 'Unsaved changes' } ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'saved' );
		const options = await zs.options( 'zero-spam-davidwalsh' );
		expect( ( options[ 'zero-spam-davidwalsh' ] as Record< string, unknown > ).davidwalsh_form_selectors ).toBe( '.e2e-form, #e2e-signup' );
	} );

	test( 'the honeypot ID is regenerated from its card', async ( { page, zs } ) => {
		const before = await zs.honeypotName();
		await open( page );
		const card = page.locator( '#zs-card-honeypot' );
		await card.getByRole( 'button', { name: 'Regenerate honeypot ID' } ).click();
		await expect( card.getByRole( 'status' ) ).toContainText( 'New honeypot ID' );
		expect( await zs.honeypotName() ).not.toBe( before );
	} );

	test( 'a third-party section renders and saves through the generic renderer', async ( { page, zs } ) => {
		await zs.fakeSection( true );
		await open( page, 'integrations' );
		const card = page.locator( '#zs-card-zs_e2e_widgets' );
		await expect( card.getByRole( 'heading', { level: 2 } ) ).toHaveText( 'E2E Widget Forms' );
		await card.getByRole( 'switch', { name: 'Protect widget forms' } ).click();
		await card.locator( '.zs-fine > summary' ).click();
		await card.getByRole( 'radiogroup', { name: 'Mode' } ).getByText( 'Relaxed' ).click();
		await card.getByLabel( 'Blocked message' ).fill( 'Nope.' );
		await card.getByRole( 'combobox', { name: /Add to Roles/ } ).selectOption( 'editor' );
		await expect( card.getByRole( 'button', { name: 'Remove Editor' } ) ).toBeVisible();
		await expect( card.locator( 'a[href="https://example.com/docs"]' ) ).toHaveAttribute( 'rel', 'noopener noreferrer' );

		await page.getByRole( 'form', { name: 'Unsaved changes' } ).getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( '4 settings saved' );
		const options = await zs.options( 'zero-spam-zs_e2e_widgets' );
		expect( options[ 'zero-spam-zs_e2e_widgets' ] ).toEqual( {
			verify_zs_e2e_widgets: 'enabled',
			zs_e2e_widgets_mode: 'relaxed',
			zs_e2e_widgets_message: 'Nope.',
			zs_e2e_widgets_roles: [ 'editor' ],
		} );
	} );

	test( 'integrations whose plugin is not active wait behind "More integrations"', async ( { page } ) => {
		await open( page, 'integrations' );
		const more = page.locator( '#zs-card-moreint' );
		await expect( more.locator( 'summary' ) ).toContainText( 'More integrations, shown when their plugin is active' );
		await more.locator( 'summary' ).click();
		const cf7 = page.locator( '#zs-card-contactform7' );
		await expect( cf7 ).toHaveClass( /zs-pcard-na/ );
		await expect( cf7 ).toContainText( 'Install and activate Contact Form 7' );
		await expect( cf7.locator( '.zs-badge' ) ).toHaveText( 'Plugin not active' );
	} );

	test( 'Test my protection lays down each check and speaks the result', async ( { page } ) => {
		await open( page );
		const button = page.locator( '.zs-test-btn' );
		await expect( button ).toHaveText( /Test my protection/ );
		await button.click();
		const panel = page.locator( '#zs-selftest' );
		await expect( panel ).toBeVisible();
		await expect( spoken( page ) ).toContainText( /Running 5 checks/ );
		await expect( panel.locator( '.zs-st-steps li' ) ).toHaveCount( 5 );
		await expect( panel ).not.toHaveAttribute( 'aria-busy', 'true', { timeout: 20_000 } );
		await expect( panel.locator( '.zs-st-result' ) ).toHaveText( /checks (passed|failed)|need/ );
		await expect( spoken( page ) ).toContainText( /checks/ );
		await expect( panel.locator( 'li.zs-step-pending, li.zs-step-running' ) ).toHaveCount( 0 );
		await expect( button ).toHaveText( /Run the test again/ );

		// A second run within the minute is refused politely.
		await button.click();
		await expect( panel.locator( '.zs-st-result' ) ).toContainText( /just ran|Try again/ );
		await expect( panel.locator( '.zs-st-steps li' ) ).toHaveCount( 5 );
	} );

	test( 'with reduced motion the self-test shows every answer at once', async ( { page } ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page );
		await page.locator( '.zs-test-btn' ).click();
		const panel = page.locator( '#zs-selftest' );
		await expect( panel ).not.toHaveAttribute( 'aria-busy', 'true', { timeout: 20_000 } );
		await expect( panel.locator( 'li.zs-step-pass, li.zs-step-warn, li.zs-step-fail' ) ).toHaveCount( 5 );
	} );

	test( 'Basic: one primary (Compare levels), the drawer traps focus and returns it', async ( { page } ) => {
		await open( page, 'intelligence' );
		const primaries = page.locator( '.zs-protection .zs-btn:not(.zs-btn-secondary):not(.zs-btn-ghost):not(.zs-btn-darksec)' );
		await expect( primaries ).toHaveCount( 1 );
		await expect( primaries ).toHaveText( 'Compare levels' );
		await expect( page.locator( '.zs-protection' ) ).not.toContainText( /\$\d|€\d|per month/ );

		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );
		expect( await tabTo( page, '.zs-enh-mini .zs-btn:not(.zs-btn-darksec)' ), 'Tab reaches Compare levels' ).toBe( true );
		expect( await focusRing( page ) ).toBe( true );
		await page.keyboard.press( 'Enter' );
		const drawer = page.locator( '.zs-drawer.zs-compare' );
		await expect( drawer ).toBeVisible();
		await expect( drawer ).not.toContainText( /\$\d/ );
		for ( let i = 0; i < 20; i++ ) {
			await page.keyboard.press( 'Tab' );
			expect( await page.evaluate( () => !! document.querySelector( '.zs-drawer' )?.contains( document.activeElement ) ) ).toBe( true );
		}
		await page.keyboard.press( 'Escape' );
		await expect( drawer ).toHaveCount( 0 );
		await expect( primaries ).toBeFocused();
	} );

	test( 'a license key is verified: an invalid key is explained, a valid one turns Enhanced on, and it can be removed', async ( { page, zs } ) => {
		await open( page, 'intelligence' );
		const card = page.locator( '#zs-card-zerospam' );
		const opener = card.getByRole( 'button', { name: 'I already have a license key' } );
		await expect( opener ).toHaveAttribute( 'aria-expanded', 'false' );
		await opener.click();
		await expect( opener ).toHaveAttribute( 'aria-expanded', 'true' );
		const input = card.getByLabel( 'License key' );
		await expect( input ).toBeFocused();

		await input.fill( 'e2e-invalid' );
		await card.getByRole( 'button', { name: 'Verify and save' } ).click();
		await expect( card.locator( '#zs-license-e' ) ).toContainText( /invalid/i );
		await expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( input ).toBeFocused();

		await input.fill( 'e2e-license' );
		await card.getByRole( 'button', { name: 'Verify and save' } ).click();
		await expect( card.locator( '.zs-licpanel' ) ).toBeVisible();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( 'License verified · Business plan. Enhanced Protection is on.' );
		await expect( card.locator( '.zs-licpanel' ) ).toContainText( 'Business' );
		await expect( card.locator( '.zs-licpanel' ) ).not.toContainText( /31,200|18,800|50,000/ );
		await expect( card.locator( '.zs-badge' ).first() ).toContainText( 'Active' );
		// The form is gone; focus is on the card, not the body.
		await expect( card ).toBeFocused();
		const options = await zs.options( 'zero-spam-zerospam' );
		expect( ( options[ 'zero-spam-zerospam' ] as Record< string, unknown > ).zerospam ).toBe( 'enabled' );

		await card.locator( '.zs-lic-adv > summary' ).click();
		await card.getByRole( 'button', { name: 'Remove license key' } ).click();
		await card.getByRole( 'button', { name: 'Remove key' } ).click();
		await expect( page.locator( '.zs-toasts' ) ).toContainText( "You're on Basic protection" );
		await expect( card.getByText( 'What Enhanced adds here' ) ).toBeVisible();
		await expect( card ).toBeFocused();
	} );

	test( 'Enhanced: the license panel, early access and the self-test latency', async ( { page, zs } ) => {
		await zs.setLevel( 'enhanced' );
		await open( page, 'intelligence' );
		const panel = page.locator( '#zs-card-zerospam .zs-licpanel' );
		await expect( panel ).toContainText( 'License verified' );
		await expect( panel ).toContainText( 'Network connected' );
		await expect( panel.locator( 'dl' ) ).toContainText( 'Plan' );
		await expect( panel.locator( 'dl' ) ).not.toContainText( 'Renews' );
		await expect( page.locator( '.zs-protection .zs-btn:not(.zs-btn-secondary):not(.zs-btn-ghost):not(.zs-btn-darksec)' ) ).toHaveCount( 1 );

		await page.locator( '.zs-test-btn' ).click();
		await expect( page.locator( '#zs-selftest' ) ).not.toHaveAttribute( 'aria-busy', 'true', { timeout: 20_000 } );
		await expect( panel.locator( '.zs-lic-live' ) ).toContainText( / ms/ );

		await panel.getByRole( 'button', { name: /What's next for members/ } ).click();
		await expect( page.locator( '.zs-drawer.zs-compare' ) ).toBeVisible();
		await expect( page.locator( '#zs-rm-h' ) ).toBeFocused();
	} );

	test( 'the Compare drawer\'s "I already have a license key" opens the key form on the card', async ( { page } ) => {
		await page.goto( AREA( 'protection', 'intelligence', { card: 'zerospam' } ) );
		await protectionReady( page );
		await expect( page.locator( '#zs-card-zerospam' ).getByLabel( 'License key' ) ).toBeFocused();
		await expect( page.locator( '#zs-card-zerospam' ) ).toBeInViewport();
	} );

	for ( const width of [ 1280, 782 ] ) {
		test( `at ${ width }px no focused control is hidden under the top bar or the "On this page" chips, Tab and Shift+Tab (2.4.11)`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 800 } );
			for ( const tab of [ '', 'intelligence' ] ) {
				await open( page, tab );
				await page.getByRole( 'tab', { selected: true } ).focus();
				expect( await focusObscured( page, '#zs-prot-body' ), `${ tab || 'detection' } at ${ width }px` ).toEqual( [] );
			}
		} );
	}

	test( 'at 375px nothing scrolls sideways and controls stay usable', async ( { page } ) => {
		await page.setViewportSize( { width: 375, height: 812 } );
		for ( const tab of [ '', 'integrations', 'intelligence' ] ) {
			await open( page, tab );
			const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
			expect( overflow, `tab ${ tab || 'detection' }` ).toBeLessThanOrEqual( 0 );
		}
		const tabSize = await page.getByRole( 'tab', { name: 'Detection' } ).boundingBox();
		expect( tabSize?.height ?? 0 ).toBeGreaterThanOrEqual( 44 );
	} );
} );

test.describe( '6.0 Protection on a subsite', { tag: '@multisite' }, () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline', { network: true } );
	} );

	test( 'a locked setting is disabled, badged and explained, and a save of it is refused', async ( { asSiteAdmin, target, zs } ) => {
		await zs.networkSetting( 'verify_comments', 'enabled', true );
		await zs.networkSetting( 'davidwalsh_form_selectors', '.network-form', true );

		await asSiteAdmin.goto( subsiteURL( target, PROTECTION ) );
		await protectionReady( asSiteAdmin );
		const card = asSiteAdmin.locator( '#zs-card-comments' );
		const sw = card.getByRole( 'switch' ).first();
		await expect( sw ).toBeDisabled();
		await expect( card.locator( '.zs-pcard-badges' ) ).toContainText( 'Locked by network' );
		const described = ( await sw.getAttribute( 'aria-describedby' ) ) ?? '';
		expect( described ).toMatch( /zs-l-verify_comments/ );
		await expect( asSiteAdmin.locator( '#zs-l-verify_comments' ) ).toHaveText( /is locked by the network administrator, so it cannot be changed on this site/ );

		const dw = asSiteAdmin.locator( '#zs-card-davidwalsh' );
		await dw.locator( '.zs-fine > summary' ).click();
		await expect( dw.getByLabel( 'Custom Form Selectors' ) ).toBeDisabled();
		await expect( dw.getByLabel( 'Custom Form Selectors' ) ).toHaveValue( '.network-form' );

		// The route refuses it too (the UI never sends it).
		const response = await asSiteAdmin.evaluate( async () => {
			const wp = ( window as unknown as { wp: { apiFetch: ( options: Record< string, unknown > ) => Promise< unknown > } } ).wp;
			const r = await wp.apiFetch( { path: '/zero-spam/v1/settings', method: 'PATCH', data: { verify_comments: false } } ).catch( ( e: { code?: string } ) => e );
			return ( r as { code?: string } ).code ?? 'saved';
		} );
		expect( response ).toBe( 'rest_setting_locked' );
	} );
} );
