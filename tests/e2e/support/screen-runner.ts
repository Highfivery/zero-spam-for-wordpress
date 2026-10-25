/**
 * Puts the site into a screen state and opens a screen as the right user.
 * Shared by a11y.spec and visual.spec.
 */

import { expect, type BrowserContext, type Page, type TestInfo } from '@playwright/test';

import type { ZsApi } from './e2e-api';
import { visitorIp } from './fixtures';
import type { Screen, ScreenState } from './screens';
import type { Role, Target } from './targets';

export const SEEDED_BLOCKS = [ '198.51.100.7', '198.51.100.8' ];

/**
 * Resets the site (all sites on a network) into a screen state.
 *
 * @param zs    API client.
 * @param state State.
 */
export async function prepareState( zs: ZsApi, state: ScreenState ): Promise< void > {
	await zs.reset( 'baseline', { clearErrorLog: true } );
	if ( 'seeded' === state ) {
		await zs.seedLog( 120 );
		for ( const ip of SEEDED_BLOCKS ) {
			await zs.block( ip, 'Seeded block' );
		}
	}
}

/**
 * Opens a screen and returns the page showing it.
 *
 * @param screen         Screen.
 * @param ctx            Fixtures.
 * @param ctx.page       Admin page.
 * @param ctx.zs         API client.
 * @param ctx.target     Target.
 * @param ctx.newRoleContext Context factory.
 * @param testInfo       Test info.
 * @param viewport       Optional viewport.
 */
export async function openScreen(
	screen: Screen,
	ctx: { page: Page; zs: ZsApi; target: Target; newRoleContext: ( role: Role | null, options?: { ip?: string; viewport?: { width: number; height: number } } ) => Promise< BrowserContext > },
	testInfo: TestInfo,
	viewport?: { width: number; height: number }
): Promise< Page > {
	const ip = visitorIp( testInfo, screen.id );
	if ( screen.before ) {
		await screen.before( ctx.zs, ctx.target, ip );
	}

	let page = ctx.page;
	if ( 'visitor' === screen.as ) {
		page = await ( await ctx.newRoleContext( null, { ip, viewport } ) ).newPage();
	} else if ( 'siteadmin' === screen.as ) {
		page = await ( await ctx.newRoleContext( 'siteadmin', { viewport } ) ).newPage();
	} else if ( viewport ) {
		await page.setViewportSize( viewport );
	}
	if ( screen.colorScheme ) {
		await page.emulateMedia( { colorScheme: screen.colorScheme } );
	}

	const response = await page.goto( screen.path( ctx.target ) );
	let status = response?.status();
	if ( screen.prepare ) {
		const prepared = await screen.prepare( page );
		if ( 'number' === typeof prepared ) {
			status = prepared;
		}
	}
	expect( status, `${ screen.title } answers ${ screen.status ?? 200 }` ).toBe( screen.status ?? 200 );
	await page.evaluate( () => document.fonts.ready );
	return page;
}
