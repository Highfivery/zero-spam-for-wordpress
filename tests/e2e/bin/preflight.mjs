/**
 * Checks that a target can be tested before Playwright starts, and explains
 * in one line what to do when it can't.
 *
 * Usage: node tests/e2e/bin/preflight.mjs <target> [--wp=latest|min]
 * (run.mjs calls preflight() for every target first).
 */

import { resolveMx } from 'node:dns/promises';
import { existsSync, readFileSync, realpathSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { E2E_DIR, ROOT, checkNode, currentRunMarker, fail, localPaths, readEnvFile, repoVersion, run, targetDefs, wpVersions } from './lib.mjs';

/**
 * GET with a timeout.
 *
 * @param {string} url     URL.
 * @param {Object} headers Headers.
 * @param {number} ms      Timeout.
 * @return {Promise<Response|null>}
 */
async function get( url, headers = {}, ms = 3000 ) {
	try {
		return await fetch( url, { headers, redirect: 'manual', signal: AbortSignal.timeout( ms ) } );
	} catch {
		return null;
	}
}

/**
 * Checks one target.
 *
 * @param {string} name   Target name.
 * @param {string} wp     latest|min (wp-env only).
 * @param {string} marker Run marker the runner wrote into this checkout (tests/e2e/.run-marker).
 * @return {Promise<{ok: boolean, reason?: string, ping?: Object}>}
 */
export async function preflight( name, wp = 'latest', marker = currentRunMarker() ) {
	const defs = targetDefs();
	const def = defs[ name ];
	if ( ! def ) {
		return { ok: false, reason: `unknown target "${ name }" (expected ${ Object.keys( defs ).join( ', ' ) }).` };
	}
	const baseURL = ( process.env.E2E_BASE_URL || def.baseURL ).replace( /\/?$/, '/' );
	let token;

	if ( 'wp-env' === def.kind ) {
		if ( 0 !== run( 'docker', [ 'info', '--format', '{{.ServerVersion}}' ] ).status ) {
			return { ok: false, reason: `${ name }: Docker is not running; start Docker Desktop, then npm run env:start${ def.multisite ? ':ms' : '' }.` };
		}
		token = JSON.parse( readFileSync( join( E2E_DIR, 'wp-env-users.json' ), 'utf8' ) ).token;
	} else {
		const envFile = join( E2E_DIR, '.env' );
		if ( ! existsSync( envFile ) ) {
			return { ok: false, reason: `${ name }: tests/e2e/.env is missing; run npm run e2e:setup:local first (AGENTS.md section 6).` };
		}
		const env = readEnvFile( envFile );
		token = env.E2E_TOKEN;
		if ( ! token || ! env[ `${ def.envPrefix }_ADMIN_USER` ] ) {
			return { ok: false, reason: `${ name }: tests/e2e/.env has no token or ${ def.envPrefix }_ADMIN_USER; rerun npm run e2e:setup:local.` };
		}
		if ( ! existsSync( localPaths( def.siteId ).socket ) ) {
			return { ok: false, reason: `${ name }: Local's MySQL socket for ${ def.siteId } is missing; start "${ def.localName }" in Local.` };
		}
	}

	const home = await get( baseURL );
	if ( ! home ) {
		return {
			ok: false,
			reason:
				'wp-env' === def.kind
					? `${ name }: nothing answers at ${ baseURL }; run npm run env:start${ def.multisite ? ':ms' : '' } (Docker Desktop must be running).`
					: `${ name }: nothing answers at ${ baseURL }; start "${ def.localName }" in Local.`,
		};
	}

	const pingRes = await get( `${ baseURL }?rest_route=/zero-spam-e2e/v1/ping`, { 'X-ZeroSpam-E2E-Token': token } );
	if ( ! pingRes || 200 !== pingRes.status ) {
		return { ok: false, reason: `${ name }: the test mu-plugin does not answer at ${ baseURL } (status ${ pingRes ? pingRes.status : 'none' }); ${ 'wp-env' === def.kind ? 'restart with npm run env:start' : 'rerun npm run e2e:setup:local' }.` };
	}
	const ping = await pingRes.json();

	const version = repoVersion();
	if ( ! ping.active ) {
		return { ok: false, reason: `${ name }: Zero Spam is not active on ${ baseURL }.` };
	}
	if ( ping.plugin !== version ) {
		return { ok: false, reason: `${ name }: the site runs Zero Spam ${ ping.plugin } at ${ ping.pluginPath }, not this checkout (${ version }); see AGENTS.md section 6.` };
	}
	if ( 'local' === def.kind && ping.pluginPath && realpathSync( ping.pluginPath ) !== realpathSync( ROOT ) ) {
		return { ok: false, reason: `${ name }: the site loads Zero Spam from ${ ping.pluginPath }, not ${ ROOT }; see AGENTS.md section 6.` };
	}
	// The same version and URL can belong to another checkout (another wp-env
	// project on these ports, or a Local site loading a different copy); only
	// this checkout's plugin directory holds the marker just written.
	if ( ping.runMarker !== marker ) {
		return {
			ok: false,
			reason: `${ name }: the site at ${ baseURL } does not load this checkout (${ ROOT }): its run marker is ${ ping.runMarker ? `"${ ping.runMarker }"` : 'missing' }, expected "${ marker }" from tests/e2e/.run-marker${ 'wp-env' === def.kind ? `; another checkout's wp-env may be using port ${ new URL( baseURL ).port } (stop it, then npm run env:start${ def.multisite ? ':ms' : '' } here)` : '; see AGENTS.md section 6' }.`,
		};
	}
	if ( 'wp-env' === def.kind ) {
		const expected = wpVersions()[ wp ];
		if ( ping.wp !== expected ) {
			return { ok: false, reason: `${ name }: WordPress ${ ping.wp } is running, expected ${ expected } (${ wp }); run npm run env:core:${ wp }.` };
		}
	}

	try {
		await resolveMx( 'example.com' );
	} catch {
		return { ok: false, reason: 'DNS lookup of example.com failed; the plugin\'s email checks need DNS (check the network connection).' };
	}

	return { ok: true, ping };
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	checkNode();
	const name = process.argv[ 2 ] || 'wp-env';
	const wpArg = process.argv.find( ( a ) => a.startsWith( '--wp=' ) );
	const result = await preflight( name, wpArg ? wpArg.slice( 5 ) : 'latest' );
	if ( ! result.ok ) {
		fail( result.reason );
	}
	process.stdout.write( `preflight ${ name }: OK (WordPress ${ result.ping.wp }, PHP ${ result.ping.php }, Zero Spam ${ result.ping.plugin })\n` );
}
