/**
 * Help, first-run setup and "What's new in 6.0" (build plan PR 10):
 * the guides and the developer reference, the news (canned feed,
 * unavailable, turned off), the support and follow links; the setup on a
 * fresh install, driven from the keyboard, writing real settings, and
 * Skip setup; the "What's new" tile and drawer on an upgraded site.
 */

import type { Page, Request } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import type { ZsApi } from '../support/e2e-api';
import { AREA, helpReady, onboardingReady } from '../support/screens';

const HELP = AREA( 'help' );
const OVERVIEW = AREA( 'overview' );

/** Whether focus is inside an element. */
const focusInside = ( page: Page, selector: string ) => page.evaluate( ( sel ) => !! document.querySelector( sel )?.contains( document.activeElement ), selector );

/**
 * Moves focus to the step's submit button and presses Enter (keyboard only).
 *
 * @param page Page.
 * @param name Button name.
 */
async function submitStep( page: Page, name: string | RegExp = 'Continue' ) {
	await page.getByRole( 'button', { name } ).focus();
	await page.keyboard.press( 'Enter' );
}

/** The focused element's id. */
const focusedId = ( page: Page ) => page.evaluate( () => document.activeElement?.id || '' );

/**
 * A stored setting of a module (zero-spam-{module}).
 *
 * @param zs     API.
 * @param module Module.
 * @param key    Setting key.
 */
async function stored( zs: ZsApi, module: string, key: string ): Promise< unknown > {
	const options = await zs.options( `zero-spam-${ module }` );
	const value = options[ `zero-spam-${ module }` ] as Record< string, unknown > | undefined;
	return value?.[ key ];
}

/** Whether first-run setup is still pending (ui/onboarding). */
async function pending( zs: ZsApi ): Promise< boolean > {
	const state = ( await zs.requestUtils.rest( { path: '/zero-spam/v1/ui/onboarding' } ) ) as { pending: boolean };
	return state.pending;
}

test.describe( 'Help', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'makes no REST request of its own on load (guides and news are preloaded)', async ( { page } ) => {
		const rest: string[] = [];
		const onRequest = ( r: Request ) => {
			if ( r.url().includes( 'zero-spam/v1' ) || r.url().includes( 'zero-spam%2Fv1' ) ) {
				rest.push( `${ r.method() } ${ r.url() }` );
			}
		};
		page.on( 'request', onRequest );
		await page.goto( HELP );
		await helpReady( page );
		await page.waitForTimeout( 1000 );
		page.off( 'request', onRequest );
		expect( rest ).toEqual( [] );
	} );

	test( 'lists every guide, including the network settings guide and the developer hooks, as headed disclosures', async ( { page } ) => {
		await page.goto( HELP );
		await helpReady( page );

		const guides = page.locator( '.zs-help-doc-h' );
		await expect( guides ).toHaveText( [
			'REST API for settings',
			'API monitoring and alerts',
			'Network statistics and insights',
			'Getting back in after a lockout',
			'Developer hooks',
			'Network settings and management',
		] );
		// Each guide is an h3 under the tile's h2 (heading order h1 → h2 → h3 → h4).
		await expect( page.getByRole( 'heading', { level: 3, name: 'Developer hooks' } ) ).toBeVisible();

		const hooks = page.getByRole( 'button', { name: 'Developer hooks' } );
		await expect( hooks ).toHaveAttribute( 'aria-expanded', 'false' );
		await hooks.focus();
		await page.keyboard.press( 'Enter' );
		await expect( hooks ).toHaveAttribute( 'aria-expanded', 'true' );
		const body = page.locator( '#zs-doc-p-developer-hooks' );
		await expect( body.locator( 'code', { hasText: 'zerospam_login_skip_check' } ) ).toBeVisible();
		await body.locator( 'summary', { hasText: 'Login protection' } ).click();
		// External links open in a new tab and say so.
		const wiki = body.locator( 'a[href*="github.com/Highfivery/zero-spam-for-wordpress/wiki/Hooks"]' );
		await expect( wiki ).toHaveAttribute( 'target', '_blank' );
		await expect( wiki ).toContainText( '(opens in a new tab)' );
		// Nothing in a guide can run script.
		expect( await page.locator( '.zs-doc script, .zs-doc [onclick], .zs-doc iframe' ).count() ).toBe( 0 );
	} );

	test( 'the developer reference lists hooks, REST routes and WP-CLI commands with copy buttons', async ( { page } ) => {
		await page.goto( HELP );
		await helpReady( page );

		await expect( page.getByRole( 'list', { name: 'Hooks' } ).locator( 'code' ).first() ).toHaveText( 'zerospam_settings' );
		await expect( page.getByRole( 'list', { name: 'REST routes' } ).locator( 'li' ) ).toHaveCount( 8 );
		await expect( page.getByRole( 'list', { name: 'WP-CLI commands' } ).locator( 'li' ) ).toHaveCount( 8 );
		await expect( page.getByRole( 'button', { name: 'Copy wp zerospam autoconfigure' } ) ).toBeVisible();
	} );

	test( 'news: eight articles, category chips, the security badge and the alerts link', async ( { page } ) => {
		await page.goto( AREA( 'help', '', { section: 'news' } ) );
		await helpReady( page );
		expect( await focusedId( page ) ).toBe( 'zs-hn-h' );

		const news = page.locator( '#zs-help-news' );
		await expect( news.locator( '.zs-news-i' ) ).toHaveCount( 8 );
		await expect( news.locator( '.zs-badge-warn', { hasText: 'Security notice' } ) ).toHaveCount( 3 );
		const chips = news.getByRole( 'group', { name: 'Filter news by category' } ).getByRole( 'button' );
		await expect( chips.first() ).toHaveAttribute( 'aria-pressed', 'true' );
		await news.getByRole( 'button', { name: 'Spam Protection' } ).click();
		await expect( news.locator( '.zs-news-i' ) ).toHaveCount( 2 );
		await expect( news.getByRole( 'button', { name: 'Spam Protection' } ) ).toHaveAttribute( 'aria-pressed', 'true' );

		const alerts = news.getByRole( 'link', { name: /Get alerts in your inbox/ } );
		await expect( alerts ).toHaveAttribute( 'href', /^https:\/\/www\.zerospam\.org\/articles\/\?utm_source=plugin&utm_medium=help-alerts/ );
		await expect( alerts ).toHaveAttribute( 'target', '_blank' );
		await expect( news.locator( '.zs-news-t' ).first() ).toHaveAttribute( 'href', /^https:\/\/www\.zerospam\.org\// );
	} );

	test( 'news unavailable and news turned off have their own states', async ( { page, zs } ) => {
		await zs.newsDown( true );
		await page.goto( HELP );
		await helpReady( page );
		await expect( page.locator( '#zs-help-news .zs-empty' ) ).toContainText( "The news isn't available right now" );

		await zs.newsDown( false );
		await zs.set( { show_news: 'disabled' } );
		await page.goto( HELP );
		await helpReady( page );
		await expect( page.locator( '#zs-help-news .zs-empty' ) ).toContainText( 'Zero Spam News is off' );
		await expect( page.locator( '#zs-help-news' ).getByRole( 'link', { name: /Turn it on/ } ) ).toHaveAttribute( 'href', /page=zero-spam-settings&tab=general/ );
	} );

	test( 'support, review and follow links; the review ask is the only one on the page', async ( { page } ) => {
		await page.goto( HELP );
		await helpReady( page );

		await expect( page.getByRole( 'link', { name: /Open the support forum/ } ) ).toHaveAttribute( 'href', 'https://wordpress.org/support/plugin/zero-spam/' );
		await expect( page.getByRole( 'link', { name: /zerospam\.org/, exact: false } ).filter( { hasText: 'Or write to the team' } ) ).toHaveAttribute( 'href', /zerospam\.org\/contact\// );
		const rate = page.getByRole( 'link', { name: /Rate Zero Spam/ } );
		await expect( rate ).toHaveCount( 1 );
		await expect( rate ).toHaveAttribute( 'href', 'https://wordpress.org/support/plugin/zero-spam/reviews/#new-post' );
		await expect( page.locator( '.zs-follow a' ) ).toHaveCount( 4 );
		for ( const link of await page.locator( '.zs-follow a' ).all() ) {
			await expect( link ).toHaveAttribute( 'target', '_blank' );
			await expect( link ).toContainText( '(opens in a new tab)' );
		}
		// No prices, no billing links.
		await expect( page.locator( '#zerospam-app' ) ).not.toContainText( /\$\d/ );
		await expect( page.locator( 'a[href*="subscription"], a[href*="billing"]' ) ).toHaveCount( 0 );
	} );

	test( 'Enhanced shows the member card with the early-access list', async ( { page, zs } ) => {
		await zs.setLevel( 'enhanced' );
		await page.goto( HELP );
		await helpReady( page );

		const card = page.locator( '.zs-member-card' );
		await expect( card ).toContainText( 'Thank you for being a member' );
		await expect( card.locator( '.zs-mc-list li' ) ).toHaveCount( 4 );
		await card.getByRole( 'button', { name: /What's next/ } ).click();
		await expect( page.locator( '.zs-drawer.zs-compare' ) ).toBeVisible();
	} );

	test( 'fits a 375px phone without sideways scrolling', async ( { page } ) => {
		await page.setViewportSize( { width: 375, height: 812 } );
		await page.goto( HELP );
		await helpReady( page );
		const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect( overflow ).toBeLessThanOrEqual( 0 );
	} );
} );

test.describe( 'First-run setup', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'an existing site never sees it', async ( { page, zs } ) => {
		await zs.firstRun( 'none' );
		await page.goto( OVERVIEW );
		await page.locator( '#zs-ov-h' ).waitFor();
		await expect( page.locator( '.zs-onb' ) ).toHaveCount( 0 );
	} );

	test( 'keyboard only: three steps write the settings, then the done screen and the Overview', async ( { page, zs } ) => {
		await zs.firstRun( 'fresh' );
		await page.goto( OVERVIEW );
		await onboardingReady( page );

		// The legacy welcome notice gives way to the setup.
		await expect( page.locator( '.notice', { hasText: 'Thanks for installing Zero Spam' } ) ).toHaveCount( 0 );

		const steps = page.locator( '.zs-ob-steps li' );
		await expect( steps.nth( 0 ) ).toHaveAttribute( 'aria-current', 'step' );

		// Step 1: Enhanced without a key is refused, with the message on the field.
		await page.locator( '#zs-ob-level-basic' ).focus();
		await page.keyboard.press( 'ArrowDown' );
		await expect( page.locator( '#zs-ob-level-enhanced' ) ).toBeChecked();
		await submitStep( page );
		await expect( page.locator( '#zs-ob-key' ) ).toBeFocused();
		await expect( page.locator( '#zs-ob-key' ) ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( page.locator( '#zs-ob-key-e' ) ).toContainText( 'Enter your license key' );

		// Back to Basic, Enter continues; focus moves to the next step's heading.
		await page.locator( '#zs-ob-level-enhanced' ).focus();
		await page.keyboard.press( 'ArrowUp' );
		await expect( page.locator( '#zs-ob-level-basic' ) ).toBeChecked();
		await submitStep( page );
		await expect( steps.nth( 1 ) ).toHaveAttribute( 'aria-current', 'step' );
		await expect( page.locator( '#zs-ob-h' ) ).toBeFocused();
		await expect( page.locator( '#zs-ob-h' ) ).toContainText( /We found \d+ doors?\./ );

		// Step 2: untick Login with Space, continue.
		await page.locator( '#zs-ob-c-login' ).focus();
		await page.keyboard.press( 'Space' );
		await expect( page.locator( '#zs-ob-c-login' ) ).not.toBeChecked();
		await submitStep( page );
		await expect( steps.nth( 2 ) ).toHaveAttribute( 'aria-current', 'step' );
		await expect( page.locator( '#zs-ob-h' ) ).toBeFocused();

		// Step 3: the switches are switches; turn on protection.
		await expect( page.getByRole( 'switch', { name: 'Share anonymous spam data' } ) ).toHaveAttribute( 'aria-checked', 'true' );
		await page.getByRole( 'switch', { name: 'Share anonymous spam data' } ).focus();
		await page.keyboard.press( 'Space' );
		await expect( page.getByRole( 'switch', { name: 'Share anonymous spam data' } ) ).toHaveAttribute( 'aria-checked', 'false' );
		await submitStep( page, 'Turn on protection' );

		await expect( page.locator( '#zs-ob-h' ) ).toContainText( 'Your wall is up.' );
		await expect( page.locator( '#zs-ob-h' ) ).toBeFocused();
		await expect( steps.locator( '[aria-current]' ) ).toHaveCount( 0 );

		expect( await stored( zs, 'comments', 'verify_comments' ) ).toBe( 'enabled' );
		expect( await stored( zs, 'login', 'verify_login' ) ).toBeFalsy();
		expect( await stored( zs, 'settings', 'share_data' ) ).toBeFalsy();
		expect( await stored( zs, 'settings', 'log_blocked_ips' ) ).toBe( 'enabled' );
		expect( await stored( zs, 'settings', 'block_method' ) ).toBe( 'php' );
		expect( await pending( zs ) ).toBe( false );

		await page.getByRole( 'link', { name: 'Go to Overview' } ).click();
		await page.locator( '#zs-ov-h' ).waitFor();
		await expect( page.locator( '.zs-whatsnew' ) ).toHaveCount( 0 );
		await expect( page.locator( '.zs-milestone' ) ).toHaveCount( 0 );
	} );

	test( 'Back keeps the choices; the mark builds one course per step', async ( { page, zs } ) => {
		await zs.firstRun( 'fresh' );
		await page.goto( OVERVIEW );
		await onboardingReady( page );
		const ghosts = () => page.locator( '.zs-onb-mark .zs-b[data-ghost]' ).count();

		const atStep1 = await ghosts();
		await page.getByRole( 'button', { name: 'Continue' } ).click();
		await expect( page.locator( '.zs-ob-steps li' ).nth( 1 ) ).toHaveAttribute( 'aria-current', 'step' );
		expect( await ghosts() ).toBeLessThan( atStep1 );
		await page.locator( '#zs-ob-c-comments' ).uncheck();
		await page.getByRole( 'button', { name: 'Back' } ).click();
		await page.getByRole( 'button', { name: 'Continue' } ).click();
		await expect( page.locator( '#zs-ob-c-comments' ) ).not.toBeChecked();
	} );

	test( 'Skip setup applies the recommended settings and ends the setup', async ( { page, zs } ) => {
		await zs.firstRun( 'fresh' );
		await page.goto( OVERVIEW );
		await onboardingReady( page );
		await page.getByRole( 'button', { name: 'Skip setup' } ).click();
		await page.locator( '#zs-ov-h' ).waitFor();

		expect( await stored( zs, 'comments', 'verify_comments' ) ).toBe( 'enabled' );
		expect( await stored( zs, 'login', 'verify_login' ) ).toBe( 'enabled' );
		expect( await pending( zs ) ).toBe( false );
	} );
} );

test.describe( "What's new in 6.0", () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await zs.firstRun( 'upgraded' );
	} );

	test( 'the drawer: focus moves in and stays, Escape returns it; Got it hides the tile for this administrator', async ( { page } ) => {
		await page.goto( OVERVIEW );
		await page.locator( '#zs-ov-h' ).waitFor();
		const tile = page.locator( '.zs-whatsnew' );
		await expect( tile ).toBeVisible();

		const open = tile.getByRole( 'button', { name: "See what's new" } );
		await open.focus();
		await page.keyboard.press( 'Enter' );
		const drawer = page.locator( '.zs-drawer.zs-whatsnew-drawer' );
		await expect( drawer ).toHaveAttribute( 'aria-modal', 'true' );
		await expect( drawer.getByRole( 'heading', { level: 2 } ) ).toHaveText( "What's new in 6.0" );
		await expect( drawer.getByRole( 'button', { name: 'Got it' } ) ).toBeFocused();
		await expect( drawer.locator( '.zs-wn-map tbody tr' ) ).toHaveCount( 12 );
		await expect( drawer.getByRole( 'link', { name: 'Rules › Blocked IPs' } ) ).toHaveAttribute( 'href', /page=zero-spam-rules&tab=ips/ );

		for ( let i = 0; i < 40; i++ ) {
			await page.keyboard.press( 'Tab' );
			expect( await focusInside( page, '.zs-drawer' ) ).toBe( true );
		}
		await page.keyboard.press( 'Escape' );
		await expect( drawer ).toHaveCount( 0 );
		await expect( open ).toBeFocused();

		await page.keyboard.press( 'Enter' );
		await drawer.getByRole( 'button', { name: 'Got it' } ).click();
		await expect( tile ).toHaveCount( 0 );
		await expect( page.locator( '#zs-ov-h' ) ).toBeFocused();

		await page.reload();
		await page.locator( '#zs-ov-h' ).waitFor();
		await expect( page.locator( '.zs-whatsnew' ) ).toHaveCount( 0 );
	} );

	test( 'nothing about it outside Zero Spam screens', async ( { page } ) => {
		await page.goto( 'wp-admin/index.php' );
		await expect( page.locator( 'body' ) ).not.toContainText( "What's new in 6.0" );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Zero Spam has a new home' );
	} );
} );
