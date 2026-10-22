/**
 * RequestUtils that can log in while Zero Spam protects wp-login.php.
 *
 * The upstream helper posts the username and password straight to
 * wp-login.php. With "Protect Login Attempts" on, Zero Spam rejects that: no
 * honeypot field, no David Walsh key, no login-intent cookie. This subclass
 * logs in the way a browser does, through the plugin's real paths:
 *
 * 1. GET wp-login.php (sets wordpress_test_cookie and Zero Spam's
 *    zerospam_login_intent cookie, and renders the honeypot field),
 * 2. GET the public zero-spam/v5/davidwalsh-key endpoint, as davidwalsh.js does,
 * 3. POST the form with the empty honeypot and the key.
 *
 * Every request also carries the X-ZeroSpam-E2E-Token header so the test
 * mu-plugin's routes and safety nets apply.
 */

import { request as playwrightRequest } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';
import { mkdir, readFile } from 'node:fs/promises';
import { dirname } from 'node:path';

import type { Account } from './targets';

export class ZsRequestUtils extends RequestUtils {
	/**
	 * Creates a request context for a user, reusing a saved storage state.
	 *
	 * @param options                  Options.
	 * @param options.user             Account to log in as.
	 * @param options.baseURL          Site root with a trailing slash.
	 * @param options.token            E2E token.
	 * @param options.storageStatePath Where the cookies and REST nonce are cached.
	 */
	static async setupFor( options: { user: Account; baseURL: string; token: string; storageStatePath?: string } ): Promise< ZsRequestUtils > {
		let storageState: { cookies: [] } | undefined;
		if ( options.storageStatePath ) {
			await mkdir( dirname( options.storageStatePath ), { recursive: true } );
			try {
				storageState = JSON.parse( await readFile( options.storageStatePath, 'utf8' ) );
			} catch {
				storageState = undefined;
			}
		}

		const context = await playwrightRequest.newContext( {
			baseURL: options.baseURL,
			extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': options.token },
			storageState: storageState ? { cookies: storageState.cookies, origins: [] } : undefined,
		} );

		return new ZsRequestUtils( context, {
			user: options.user,
			// The REST helpers look for a cached nonce on this object; a browser
			// storage state has none, so setupRest() runs and logs in if needed.
			storageState: undefined,
			storageStatePath: undefined,
			baseURL: options.baseURL,
		} );
	}

	/**
	 * Logs in through Zero Spam's login protection and returns a REST nonce.
	 *
	 * @param user Account (defaults to the one passed to setupFor()).
	 */
	login = async ( user: Account = this.user ): Promise< string > => {
		// Already logged in (cookies from a storage state)? Then just get a nonce.
		let response = await this.request.get( 'wp-admin/admin-ajax.php?action=rest-nonce' );
		if ( response.ok() ) {
			const nonce = ( await response.text() ).trim();
			if ( /^[a-f0-9]{10}$/.test( nonce ) ) {
				return nonce;
			}
		}

		response = await this.request.get( 'wp-login.php' );
		const html = await response.text();
		const honeypot = html.match( /<input type="text" name="([^"]+)" value="" style="display: none !important;" \/>/ );

		let key = '';
		const keyResponse = await this.request.get( '?rest_route=/zero-spam/v5/davidwalsh-key' );
		if ( keyResponse.ok() ) {
			key = ( await keyResponse.json() ).key || '';
		}

		const form: Record< string, string > = {
			log: user.username,
			pwd: user.password,
			'wp-submit': 'Log In',
			testcookie: '1',
		};
		if ( honeypot ) {
			form[ honeypot[ 1 ] ] = '';
		}
		if ( key ) {
			form.zerospam_david_walsh_key = key;
		}

		response = await this.request.post( 'wp-login.php', { form, maxRedirects: 0 } );
		if ( 302 !== response.status() ) {
			const body = await response.text();
			const error = body.match( /<div id="login_error"[^>]*>([\s\S]*?)<\/div>/ );
			throw new Error( `Login as ${ user.username } failed (${ response.status() }): ${ error ? error[ 1 ].replace( /<[^>]+>/g, '' ).trim() : 'no redirect' }` );
		}

		response = await this.request.get( 'wp-admin/admin-ajax.php?action=rest-nonce', { failOnStatusCode: true } );
		return ( await response.text() ).trim();
	};
}
