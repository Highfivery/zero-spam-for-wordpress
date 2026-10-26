/**
 * WCAG 2.4.11 (Focus Not Obscured): Tab through an area's body, then back
 * with Shift+Tab, and report the first focused control that the admin bar,
 * the sticky top bar or the "On this page" chips cover.
 *
 * The backward pass matters: moving focus up scrolls a control to the top
 * of the viewport, under anything sticky there, which a forward-only pass
 * (controls arrive from below) never shows.
 */

import type { Page } from '@playwright/test';

/** What covers a focused control, or null. */
const covered = ( page: Page, body: string ) =>
	page.evaluate( ( bodySelector ) => {
		const el = document.activeElement as HTMLElement | null;
		if ( ! el || ! el.closest( bodySelector ) || el.closest( '.zs-jump' ) ) {
			return null;
		}
		const box = el.getBoundingClientRect();
		for ( const sel of [ '#wpadminbar', '.zs-topbar', '.zs-jump' ] ) {
			const r = document.querySelector( sel )?.getBoundingClientRect();
			if ( r && r.height > 0 && box.top < r.bottom && box.bottom > r.top && box.left < r.right && box.right > r.left ) {
				return `${ el.outerHTML.slice( 0, 80 ) } (top ${ Math.round( box.top ) }) under ${ sel } (bottom ${ Math.round( r.bottom ) })`;
			}
		}
		return null;
	}, body );

/**
 * Tabs forward `steps` times from the focused element, then back with
 * Shift+Tab as many times.
 *
 * @param page  Page (focus already on the starting control).
 * @param body  Selector of the area body whose controls are checked.
 * @param steps Key presses each way.
 * @return The covered controls, with the pass and step.
 */
export async function focusObscured( page: Page, body: string, steps = 60 ): Promise< string[] > {
	const found: string[] = [];
	for ( const [ key, pass ] of [ [ 'Tab', 'Tab' ], [ 'Shift+Tab', 'Shift+Tab' ] ] as const ) {
		for ( let i = 0; i < steps; i++ ) {
			await page.keyboard.press( key );
			const hit = await covered( page, body );
			if ( hit ) {
				found.push( `${ pass } step ${ i + 1 }: ${ hit }` );
			}
		}
	}
	return found;
}
