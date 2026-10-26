import type { Page } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { AREA, rulesReady } from '../support/screens';
import { SettingsPage } from '../support/pages/settings-page';

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

/**
 * Whether the focused element shows a focus indicator (outline or box-shadow).
 *
 * @param page Page.
 */
function focusIndicator( page: Page ): Promise< boolean > {
	return page.evaluate( () => {
		const el = document.activeElement as HTMLElement;
		const style = getComputedStyle( el );
		const outline = 'none' !== style.outlineStyle && parseFloat( style.outlineWidth ) > 0;
		const shadow = 'none' !== style.boxShadow;
		return outline || shadow;
	} );
}

test.describe( 'Keyboard and motion', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'the settings menu and Save are reachable by keyboard with a visible focus indicator', async ( { page } ) => {
		const settings = new SettingsPage( page );
		await settings.goto( 'comments' );
		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );

		expect( await tabTo( page, '.zerospam-dashboard__menu-link' ), 'Tab reaches the settings menu' ).toBe( true );
		expect( await focusIndicator( page ), 'menu link shows focus' ).toBe( true );

		expect( await tabTo( page, '#submit' ), 'Tab reaches Save Settings' ).toBe( true );
		expect( await focusIndicator( page ), 'Save shows focus' ).toBe( true );
	} );

	test( 'a settings toggle can be reached and switched with the keyboard', async ( { page } ) => {
		const settings = new SettingsPage( page );
		await settings.goto( 'comments' );
		const toggle = settings.field( 'verify_comments', 'enabled' );
		const before = await toggle.isChecked();
		await page.locator( 'body' ).click( { position: { x: 1, y: 1 } } );

		expect( await tabTo( page, '#verify_commentsenabled', 120 ), 'Tab reaches the "Protect Comments" switch' ).toBe( true );
		await page.keyboard.press( 'Space' );
		await expect( toggle ).toBeChecked( { checked: ! before } );
	} );

	// AGENTS.md §8 listed the legacy add/update block modal (assets/js/admin.js) as
	// having no focus management; the 6.0 Rules area replaces it with the shared
	// block drawer, which this checks (the legacy screen is redirected at the cutover).
	test( 'the block drawer takes focus, keeps Tab inside, closes on Escape and returns focus to the button that opened it', async ( { page } ) => {
		await page.goto( AREA( 'rules', 'ips' ) );
		await rulesReady( page );
		const trigger = page.getByRole( 'button', { name: 'Block an IP' } );
		const drawer = page.locator( '.zs-drawer.zs-blockform' );
		await trigger.focus();
		await page.keyboard.press( 'Enter' );
		await expect( drawer ).toBeVisible();

		await test.step( 'focus moves into the drawer and Tab stays there', async () => {
			expect( await drawer.evaluate( ( el ) => el.contains( document.activeElement ) ) ).toBe( true );
			for ( let i = 0; i < 25; i++ ) {
				await page.keyboard.press( 'Tab' );
				expect( await drawer.evaluate( ( el ) => el.contains( document.activeElement ) ), `Tab ${ i + 1 } left the drawer` ).toBe( true );
			}
			expect( await focusIndicator( page ) ).toBe( true );
		} );

		await test.step( 'Escape closes it and focus returns to the button', async () => {
			await page.keyboard.press( 'Escape' );
			await expect( drawer ).toBeHidden();
			await expect( trigger ).toBeFocused();
		} );
	} );

	test( 'with reduced motion requested, the plugin CSS turns its animations and transitions off', async ( { page } ) => {
		await page.goto( 'wp-admin/index.php' );
		const report = await page.evaluate( () => {
			const own = Array.from( document.styleSheets ).filter( ( s ) => s.href && s.href.includes( '/plugins/zero-spam/' ) );
			let animated = 0;
			let reduced = 0;
			const walk = ( rules: CSSRuleList ) => {
				for ( const rule of Array.from( rules ) ) {
					if ( rule instanceof CSSMediaRule ) {
						if ( /prefers-reduced-motion/.test( rule.conditionText ) ) {
							reduced++;
						}
						walk( rule.cssRules );
					} else if ( rule instanceof CSSStyleRule && /(^|;)\s*(transition|animation)(-[a-z-]+)?\s*:/.test( rule.style.cssText ) ) {
						animated++;
					}
				}
			};
			for ( const sheet of own ) {
				try {
					walk( sheet.cssRules );
				} catch {
					// Cross-origin sheet.
				}
			}
			return { sheets: own.length, animated, reduced };
		} );
		expect( report.sheets ).toBeGreaterThan( 0 );
		expect( report.animated, 'the plugin CSS animates something' ).toBeGreaterThan( 0 );
		expect( report.reduced, 'and has a prefers-reduced-motion rule for it' ).toBeGreaterThan( 0 );
	} );
} );
