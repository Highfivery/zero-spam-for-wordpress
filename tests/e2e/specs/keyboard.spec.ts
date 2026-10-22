import type { Page } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { BlockIpModal, DashboardPage } from '../support/pages/dashboard-page';
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

	test( 'the blocked-IP modal takes focus, closes on Escape and returns focus to the button that opened it', async ( { page } ) => {
		test.fail( true, 'AGENTS.md §8: the add/update block modal has no focus management (focus stays behind the modal and is not returned)' );
		const dashboard = new DashboardPage( page );
		const modal = new BlockIpModal( page );
		await dashboard.goto( 'blocked-ips' );
		const trigger = page.getByRole( 'button', { name: /Add Blocked IP Address/ } );
		await trigger.focus();
		await page.keyboard.press( 'Enter' );
		await expect( modal.root ).toHaveClass( /is-active/ );

		await test.step( 'focus moves into the modal', async () => {
			expect( await modal.root.evaluate( ( el ) => el.contains( document.activeElement ) ) ).toBe( true );
		} );

		await test.step( 'Escape closes it and focus returns to the button', async () => {
			await page.keyboard.press( 'Escape' );
			await expect( modal.root ).not.toHaveClass( /is-active/ );
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
