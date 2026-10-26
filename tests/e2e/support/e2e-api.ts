/**
 * Client for the test mu-plugin's REST routes (zero-spam-e2e/v1).
 *
 * Resets and seeding go through the mu-plugin because the plugin's own
 * zero-spam/v1/settings endpoint only accepts two keys and writes the second
 * network storage model (AGENTS.md section 8).
 */

import type { ZsRequestUtils } from './request-utils';

/** Protection level: Basic (no key) or Enhanced (canned Business license). */
export type Level = 'basic' | 'enhanced';

export type Preset = 'defaults' | 'recommended' | 'baseline';

export interface LogRow {
	log_id: string;
	log_type: string;
	user_ip: string;
	date_recorded: string;
	submission_data: Record< string, unknown > | null;
}

export interface PluginError {
	kind: string;
	message: string;
	file: string;
	line: number;
	uri: string;
}

export interface HttpCall {
	method: string;
	host: string;
	path: string;
	answer: 'canned' | 'blocked';
	request: boolean;
}

export interface Inventory {
	options: Record< string, string[] >;
	sitemeta: string[];
	tables: string[];
	cron: string[];
}

export class ZsApi {
	/**
	 * @param requestUtils Logged-in request utils (admin or super admin).
	 * @param sitePath     Path of the site to act on ('' for the main site, '/site2/' for a subsite).
	 */
	constructor(
		readonly requestUtils: ZsRequestUtils,
		private readonly sitePath = '',
		private readonly multisite = false
	) {}

	/**
	 * A client for another site of the same network.
	 *
	 * @param sitePath Subsite path such as /site2/.
	 */
	forSite( sitePath: string ): ZsApi {
		return new ZsApi( this.requestUtils, sitePath, this.multisite );
	}

	/**
	 * Calls a route. Uses ?rest_route= so it works on subsites without rewrite rules.
	 *
	 * A dropped keep-alive connection (ECONNRESET, "socket hang up") is retried
	 * once: the worker-scoped API context reuses connections that the web
	 * server may have closed between tests, and Local runs have no retries.
	 *
	 * @param method HTTP method.
	 * @param route  Route below zero-spam-e2e/v1.
	 * @param data   JSON body or query parameters.
	 */
	async call< T >( method: 'GET' | 'POST' | 'DELETE', route: string, data?: Record< string, unknown > ): Promise< T > {
		const { requestUtils } = this;
		if ( ! requestUtils.storageState?.nonce ) {
			await requestUtils.setupRest();
		}
		// Go through index.php explicitly: on nginx hosts such as LocalWP a DELETE
		// to the site root (`/?rest_route=…`) is answered by nginx with 405 before
		// WordPress sees it, while `index.php?rest_route=…` accepts every method.
		const base = ( this.sitePath ? this.sitePath.replace( /^\//, '' ) : '' ) + 'index.php';
		const params = new URLSearchParams( { rest_route: `/zero-spam-e2e/v1/${ route }` } );
		if ( 'GET' === method && data ) {
			for ( const [ k, v ] of Object.entries( data ) ) {
				params.set( k, String( v ) );
			}
		}
		const send = () =>
			requestUtils.request.fetch( `${ base }?${ params.toString() }`, {
				method,
				headers: { 'X-WP-Nonce': requestUtils.storageState!.nonce },
				data: 'GET' === method ? undefined : data,
				failOnStatusCode: false,
			} );
		let response;
		try {
			response = await send();
		} catch ( error ) {
			if ( ! /ECONNRESET|socket hang up/i.test( String( ( error as Error )?.message ?? error ) ) ) {
				throw error;
			}
			response = await send();
		}
		const body = await response.json().catch( async () => ( { raw: await response.text() } ) );
		if ( ! response.ok() ) {
			if ( body && 'rest_cookie_invalid_nonce' === body.code ) {
				await requestUtils.setupRest();
				return this.call< T >( method, route, data );
			}
			throw new Error( `zero-spam-e2e ${ method } ${ route } failed (${ response.status() }): ${ JSON.stringify( body ) }` );
		}
		return body as T;
	}

	/**
	 * Resets plugin state.
	 *
	 * @param preset                defaults | recommended | baseline (see the mu-plugin).
	 * @param options               Options.
	 * @param options.network       Reset the network settings and every site (super admin; default on a network).
	 * @param options.promo         Let the Enhanced Protection promo notice show.
	 * @param options.clearErrorLog Delete the plugin's error log file.
	 * @param options.level         basic (no license key, the default) or enhanced (a key the canned API accepts as a Business license).
	 *
	 * Every reset also clears each user's 6.0 admin preferences (ui/prefs) and the cached news.
	 */
	reset( preset: Preset = 'baseline', options: { network?: boolean; promo?: boolean; clearErrorLog?: boolean; level?: Level } = {} ) {
		return this.call( 'POST', 'reset', {
			preset,
			network: options.network ?? this.multisite,
			promo: Boolean( options.promo ),
			clearErrorLog: Boolean( options.clearErrorLog ),
			level: options.level ?? 'basic',
		} );
	}

	/**
	 * Puts the site on a protection level without a reset (keeps seeded rows).
	 *
	 * @param level basic or enhanced.
	 */
	setLevel( level: Level ) {
		return 'enhanced' === level ? this.set( { zerospam_license: 'e2e-license', zerospam: 'enabled' } ) : this.set( { zerospam_license: '', zerospam: false } );
	}

	/**
	 * Merges preferences into the current user's ui/prefs (empty: back to the defaults).
	 *
	 * @param prefs Preferences, such as { theme: 'dark' }.
	 */
	uiPrefs( prefs: Record< string, unknown > = {} ) {
		return this.call< Record< string, unknown > >( 'POST', 'ui-prefs', { prefs } );
	}

	/**
	 * Turns the fake third-party settings section on or off (Protection ›
	 * Integrations renders it generically). Every reset turns it off.
	 *
	 * @param on On.
	 */
	fakeSection( on = true ) {
		return this.call< { on: boolean } >( 'POST', 'fake-section', { on } );
	}

	/** Lets the self-test run again at once (it runs once a minute per site). */
	selfTestReset() {
		return this.call( 'POST', 'self-test-reset' );
	}

	/**
	 * Sets a network value (super admin, multisite).
	 *
	 * @param key    Setting key.
	 * @param value  Value.
	 * @param locked Lock it for every site.
	 */
	networkSetting( key: string, value: unknown, locked = false ) {
		return this.call< { saved: boolean } >( 'POST', 'network-setting', { key, value, locked } );
	}

	/** A valid rescue-link token, as an emailed link carries (TEST-NET IPs only). */
	async rescueToken( ip: string ): Promise< string > {
		return ( await this.call< { token: string } >( 'POST', 'rescue-token', { ip } ) ).token;
	}

	/** Sets plugin settings by key (each goes to its module option). */
	set( settings: Record< string, unknown > ) {
		return this.call< Record< string, boolean > >( 'POST', 'settings', { settings } );
	}

	module( key: string, on: boolean ) {
		return this.set( { [ key ]: on ? 'enabled' : false } );
	}

	seedLog( count: number, options: { type?: string; failed?: string; ipPrefix?: string; ip?: string; spacingMinutes?: number; keepCache?: boolean; crafted?: boolean; data?: Record< string, string > } = {} ) {
		return this.call< { inserted: number } >( 'POST', 'seed-log', { count, ...options } );
	}

	logRows( filter: { type?: string; ip?: string } = {} ) {
		return this.call< LogRow[] >( 'GET', 'log', filter );
	}

	block( ip: string, reason = 'e2e' ) {
		return this.call< { blocked: boolean } >( 'POST', 'block', { ip, reason } );
	}

	options( search = '' ) {
		return this.call< Record< string, unknown > >( 'GET', 'options', search ? { search } : undefined );
	}

	async honeypotName(): Promise< string > {
		const options = await this.options( 'wpzerospam_honeypot' );
		return String( options.wpzerospam_honeypot || '' );
	}

	inventory() {
		return this.call< Inventory >( 'GET', 'inventory' );
	}

	httpCalls() {
		return this.call< HttpCall[] >( 'GET', 'http-calls' );
	}

	clearHttpCalls() {
		return this.call( 'DELETE', 'http-calls' );
	}

	errors() {
		return this.call< PluginError[] >( 'GET', 'errors' );
	}

	clearErrors() {
		return this.call( 'DELETE', 'errors' );
	}

	whoami() {
		return this.call< { id: number; login: string; roles: string[]; superAdmin: boolean; blogId: number } >( 'GET', 'whoami' );
	}
}
