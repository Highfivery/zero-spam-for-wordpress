/**
 * Logs every role in through the real wp-login.php form (with Zero Spam's
 * login protection in whatever state the site is in) and stores the logins
 * for the other projects. On a Local target it also snapshots the plugin
 * state so the `restore` teardown can put it back, and marks the run as in
 * progress, so the test mu-plugin stubs outgoing HTTP for every request
 * (cron included) until the teardown clears the mark.
 */

import { test as setup, expect } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { ZsApi } from '../support/e2e-api';
import { ZsRequestUtils } from '../support/request-utils';
import { resolveTarget, storageStatePath, type Role } from '../support/targets';

const target = resolveTarget();

for ( const role of Object.keys( target.users ) as Role[] ) {
	setup( `log in as ${ role } through wp-login.php`, async ( { browser } ) => {
		const account = target.users[ role ]!;
		const context = await browser.newContext( { baseURL: target.baseURL, extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': target.token } } );
		const page = await context.newPage();

		await setup.step( 'open the login form', async () => {
			await page.goto( 'wp-login.php' );
			await expect( page.locator( '#loginform' ) ).toBeVisible();
			// wp-login.php focuses the username 200 ms after load; wait so typing isn't redirected.
			await expect( page.locator( '#user_login' ) ).toBeFocused();
		} );

		await setup.step( 'type the username and password like a person', async () => {
			await page.locator( '#user_login' ).fill( account.username );
			await page.locator( '#user_pass' ).fill( account.password );
			await Promise.all( [ page.waitForURL( /wp-admin/ ), page.locator( '#wp-submit' ).click() ] );
		} );

		mkdirSync( target.authDir, { recursive: true } );
		await context.storageState( { path: storageStatePath( target, role ) } );
		await context.close();
	} );
}

setup( 'record the WordPress and PHP versions under test', async ( { playwright } ) => {
	const request = await playwright.request.newContext( { baseURL: target.baseURL, extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': target.token } } );
	const response = await request.get( '?rest_route=/zero-spam-e2e/v1/ping' );
	expect( response.ok(), 'the test mu-plugin answers /ping' ).toBeTruthy();
	const ping = await response.json();
	await setup.info().attach( 'zs-env', { body: JSON.stringify( { wp: ping.wp, php: ping.php, plugin: ping.plugin, multisite: ping.multisite } ), contentType: 'application/json' } );
	await request.dispose();
} );

setup( 'snapshot the Local site so the run can restore it', async () => {
	setup.skip( 'local' !== target.kind, 'wp-env sites are disposable (and always stubbed).' );
	const utils = await ZsRequestUtils.setupFor( { user: target.users.admin!, baseURL: target.baseURL, token: target.token, storageStatePath: storageStatePath( target, 'admin' ) } );
	const api = new ZsApi( utils );
	const snapshot = await api.call( 'GET', 'snapshot' );
	const dir = join( target.artifacts, 'local-backup' );
	mkdirSync( dir, { recursive: true } );
	writeFileSync( join( dir, 'snapshot.json' ), JSON.stringify( snapshot ) );
	await api.call( 'POST', 'run', { active: true } );
	await utils.request.dispose();
} );
