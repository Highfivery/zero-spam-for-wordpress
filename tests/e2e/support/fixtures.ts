/**
 * Test fixtures shared by every spec.
 *
 * - `page` / `asAdmin`: administrator (super admin on a network).
 * - `asEditor`, `asSiteAdmin` (network only): other roles, from the stored logins.
 * - `visitor`: a fresh logged-out browser with its own TEST-NET visitor IP.
 *   Logged out matters: Access::process() skips logged-in users.
 * - `bot`: a logged-out API client (no JavaScript) with its own visitor IP.
 * - `zs`: the test mu-plugin's REST client (resets, seeding, inspection).
 * - `wpcli`: WP-CLI for the target.
 * - `gallery`: screenshots for the run's gallery.
 *
 * Automatic checks on every test:
 * 1. Plugin PHP notices, warnings, deprecations and _doing_it_wrong() calls
 *    recorded by the mu-plugin fail the test (works with WP_DEBUG off, as on
 *    Local); on wp-env, any displayed PHP error in a page fails it too.
 * 2. Browser requests to other hosts are aborted and recorded (the jsDelivr
 *    Chart.js request is served from the bundled copy instead).
 */

import { test as base, expect, type APIRequestContext, type Browser, type BrowserContext, type Locator, type Page, type TestInfo } from '@playwright/test';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { ZsApi } from './e2e-api';
import { ZsRequestUtils } from './request-utils';
import { ROOT, resolveTarget, storageStatePath, type Role, type Target } from './targets';
import { WpCli } from './wp-cli';

export { expect };

const BUNDLED_CHARTJS = join( ROOT, 'assets/js/vendor/chart.umd.min.js' );
/**
 * Plugin PHP errors that are confirmed defects (AGENTS.md section 8). The
 * automatic check reports them as annotations instead of failing every test
 * that opens the screen. smoke.spec asserts there are none, as an expected
 * failure: fixing the bug makes that test pass, which fails the run until its
 * test.fail marker is removed. Nothing enforces removing the entry here, so
 * delete it in the same change.
 */
export const KNOWN_PHP_ERRORS: Array< { match: RegExp; reason: string } > = [];

const PHP_ERROR_IN_HTML = /<b>(Notice|Warning|Deprecated|Fatal error|Parse error)<\/b>:\s+([^<]{0,300})/g;

export interface BlockedRequest {
	host: string;
	path: string;
	served: 'aborted' | 'bundled-copy';
}

interface Guard {
	blocked: BlockedRequest[];
	htmlErrors: string[];
}

export interface Visitor {
	page: Page;
	context: BrowserContext;
	ip: string;
}

export interface Bot {
	request: APIRequestContext;
	ip: string;
}

export interface Gallery {
	capture( subject: Page | Locator, caption: string ): Promise< void >;
}

/**
 * Visitor addresses, handed out in order: 203.0.113.10–249 (TEST-NET-3), then
 * 192.0.2.10–249 (TEST-NET-1). 198.51.100.0/24 (TEST-NET-2) is left for the
 * fixed addresses the specs block and seed.
 */
const VISITOR_POOL = [ '203.0.113.', '192.0.2.' ].flatMap( ( net ) => Array.from( { length: 240 }, ( _, i ) => `${ net }${ i + 10 }` ) );
const visitorIps = new Map< string, string >();

/**
 * A TEST-NET address of its own for each (test, attempt, salt). Allocated by
 * index from VISITOR_POOL, with the next index kept in the target's artifacts
 * directory so a retry in a new worker continues where the last one stopped;
 * no two allocations share an address until the 480-address pool wraps.
 *
 * @param testInfo Test info.
 * @param salt     Extra salt for a second address in one test.
 */
export function visitorIp( testInfo: TestInfo, salt = '' ): string {
	const key = `${ testInfo.testId }|${ testInfo.retry }|${ salt }`;
	const known = visitorIps.get( key );
	if ( known ) {
		return known;
	}
	const counter = join( resolveTarget().artifacts, 'visitor-ip-index' );
	let next = 0;
	try {
		next = Number.parseInt( readFileSync( counter, 'utf8' ), 10 ) || 0;
	} catch {
		// First allocation of this run directory.
	}
	const ip = VISITOR_POOL[ next % VISITOR_POOL.length ];
	mkdirSync( resolveTarget().artifacts, { recursive: true } );
	writeFileSync( counter, String( next + 1 ) );
	visitorIps.set( key, ip );
	return ip;
}

/**
 * Blocks other hosts and scans HTML for displayed PHP errors.
 *
 * @param context Browser context.
 * @param target  Target.
 * @param guard   Collector.
 */
async function guardContext( context: BrowserContext, target: Target, guard: Guard ): Promise< void > {
	const origin = new URL( target.baseURL ).host;

	await context.route(
		( url ) => url.host !== origin && ( 'http:' === url.protocol || 'https:' === url.protocol ),
		async ( route ) => {
			const url = new URL( route.request().url() );
			if ( 'cdn.jsdelivr.net' === url.host && url.pathname.includes( '/chart.js@' ) ) {
				guard.blocked.push( { host: url.host, path: url.pathname, served: 'bundled-copy' } );
				await route.fulfill( { path: BUNDLED_CHARTJS, contentType: 'application/javascript' } );
				return;
			}
			guard.blocked.push( { host: url.host, path: url.pathname, served: 'aborted' } );
			await route.abort( 'blockedbyclient' );
		}
	);

	if ( 'wp-env' !== target.kind ) {
		return;
	}

	context.on( 'response', async ( response ) => {
		const req = response.request();
		if ( 'document' !== req.resourceType() || response.status() >= 300 && response.status() < 400 ) {
			return;
		}
		if ( new URL( response.url() ).host !== origin ) {
			return;
		}
		try {
			const html = await response.text();
			for ( const m of html.matchAll( PHP_ERROR_IN_HTML ) ) {
				guard.htmlErrors.push( `${ m[ 1 ] }: ${ m[ 2 ].trim() } (${ new URL( response.url() ).pathname }${ new URL( response.url() ).search })` );
			}
		} catch {
			// Navigated away before the body was read.
		}
	} );
}

type TestFixtures = {
	guard: Guard;
	asAdmin: Page;
	asEditor: Page;
	asSiteAdmin: Page;
	visitor: Visitor;
	bot: Bot;
	gallery: Gallery;
	phpErrorCheck: void;
	newRoleContext: ( role: Role | null, options?: { ip?: string; viewport?: { width: number; height: number } } ) => Promise< BrowserContext >;
};

type WorkerFixtures = {
	target: Target;
	wpcli: WpCli;
	adminUtils: ZsRequestUtils;
	zs: ZsApi;
};

/**
 * Creates a guarded browser context, logged in as a role or logged out.
 *
 * @param browser Browser.
 * @param target  Target.
 * @param guard   Collector.
 * @param role    Role, or null for a visitor.
 * @param options Visitor IP and viewport.
 * @param options.ip Visitor IP header.
 * @param options.viewport Viewport.
 */
async function roleContext( browser: Browser, target: Target, guard: Guard, role: Role | null, options: { ip?: string; viewport?: { width: number; height: number } } = {} ): Promise< BrowserContext > {
	const headers: Record< string, string > = { 'X-ZeroSpam-E2E-Token': target.token };
	if ( options.ip ) {
		headers[ 'X-ZeroSpam-E2E-IP' ] = options.ip;
	}
	const context = await browser.newContext( {
		baseURL: target.baseURL,
		storageState: role ? storageStatePath( target, role ) : undefined,
		extraHTTPHeaders: headers,
		viewport: options.viewport ?? { width: 1280, height: 900 },
		deviceScaleFactor: 1,
		locale: 'en-US',
		timezoneId: 'UTC',
		colorScheme: 'light',
		reducedMotion: 'reduce',
	} );
	await guardContext( context, target, guard );
	return context;
}

export const test = base.extend< TestFixtures, WorkerFixtures >( {
	target: [
		// eslint-disable-next-line no-empty-pattern
		async ( {}, use ) => {
			await use( resolveTarget() );
		},
		{ scope: 'worker' },
	],

	wpcli: [
		async ( { target }, use ) => {
			await use( new WpCli( target ) );
		},
		{ scope: 'worker' },
	],

	adminUtils: [
		async ( { target }, use ) => {
			const utils = await ZsRequestUtils.setupFor( {
				user: target.users.admin!,
				baseURL: target.baseURL,
				token: target.token,
				storageStatePath: storageStatePath( target, 'admin' ),
			} );
			await use( utils );
			await utils.request.dispose();
		},
		{ scope: 'worker' },
	],

	zs: [
		async ( { adminUtils, target }, use ) => {
			await use( new ZsApi( adminUtils, '', target.multisite ) );
		},
		{ scope: 'worker' },
	],

	guard: async ( {}, use, testInfo ) => {
		const guard: Guard = { blocked: [], htmlErrors: [] };
		await use( guard );
		if ( guard.blocked.length ) {
			await testInfo.attach( 'zs-blocked-requests', { body: JSON.stringify( guard.blocked ), contentType: 'application/json' } );
		}
	},

	newRoleContext: async ( { browser, target, guard }, use ) => {
		const contexts: BrowserContext[] = [];
		await use( async ( role, options ) => {
			const context = await roleContext( browser, target, guard, role, options );
			contexts.push( context );
			return context;
		} );
		await Promise.all( contexts.map( ( c ) => c.close() ) );
	},

	context: async ( { browser, target, guard }, use ) => {
		const context = await roleContext( browser, target, guard, 'admin' );
		await use( context );
		await context.close();
	},

	asAdmin: async ( { page }, use ) => {
		await use( page );
	},

	asEditor: async ( { newRoleContext }, use ) => {
		const context = await newRoleContext( 'editor' );
		await use( await context.newPage() );
	},

	asSiteAdmin: async ( { newRoleContext, target }, use ) => {
		test.skip( ! target.users.siteadmin, 'Only networks have a site administrator account.' );
		const context = await newRoleContext( 'siteadmin' );
		await use( await context.newPage() );
	},

	visitor: async ( { newRoleContext }, use, testInfo ) => {
		const ip = visitorIp( testInfo, 'visitor' );
		const context = await newRoleContext( null, { ip } );
		await use( { page: await context.newPage(), context, ip } );
	},

	bot: async ( { playwright, target }, use, testInfo ) => {
		const ip = visitorIp( testInfo, 'bot' );
		const request = await playwright.request.newContext( {
			baseURL: target.baseURL,
			extraHTTPHeaders: { 'X-ZeroSpam-E2E-Token': target.token, 'X-ZeroSpam-E2E-IP': ip, 'User-Agent': 'ZeroSpamE2EBot/1.0' },
		} );
		await use( { request, ip } );
		await request.dispose();
	},

	gallery: async ( { target }, use, testInfo ) => {
		const dir = join( target.artifacts, 'gallery' );
		mkdirSync( dir, { recursive: true } );
		let n = 0;
		await use( {
			async capture( subject, caption ) {
				n++;
				const slug = `${ testInfo.titlePath.slice( 1 ).join( '-' ) }-${ n }-${ caption }`
					.toLowerCase()
					.replace( /[^a-z0-9]+/g, '-' )
					.replace( /^-|-$/g, '' )
					.slice( 0, 120 );
				const file = join( dir, `${ slug }.png` );
				await subject.screenshot( { path: file, animations: 'disabled' } );
				await testInfo.attach( `gallery:${ caption }`, { path: file, contentType: 'image/png' } );
			},
		} );
	},

	phpErrorCheck: [
		async ( { zs, guard }, use, testInfo ) => {
			await zs.clearErrors();
			await use();
			const all = [
				...( await zs.errors() ).map( ( e ) => `${ e.kind }: ${ e.message } in ${ e.file }${ e.line ? `:${ e.line }` : '' } (${ e.uri })` ),
				...guard.htmlErrors,
			];
			const lines: string[] = [];
			for ( const line of all ) {
				const known = KNOWN_PHP_ERRORS.find( ( k ) => k.match.test( line ) );
				if ( known ) {
					testInfo.annotations.push( { type: 'known-php-error', description: `${ known.reason }: ${ line }` } );
				} else {
					lines.push( line );
				}
			}
			if ( lines.length ) {
				await testInfo.attach( 'zs-php-errors', { body: JSON.stringify( lines, null, 2 ), contentType: 'application/json' } );
			}
			expect( lines, 'Zero Spam raised PHP notices, warnings, deprecations or _doing_it_wrong() calls' ).toEqual( [] );
		},
		{ auto: true },
	],
} );
