/**
 * Runs the e2e suite against one or more targets and prints one result line.
 *
 * Usage:
 *   node tests/e2e/bin/run.mjs --target=wp-env,wp-env-ms [--wp=latest|min|latest,min]
 *                              [--project=a11y] [--update-snapshots] [-- <playwright args>]
 *   node tests/e2e/bin/run.mjs --switch-core=min|latest     (wp-env only, then exit)
 *
 * - Checks Node, then runs the preflight for every target (exit 1 with a
 *   one-line reason if one isn't ready).
 * - One Playwright process per target with E2E_TARGET set. wp-env targets run
 *   at the same time (separate Docker environments); Local targets one after
 *   the other.
 * - Output: artifacts/e2e/<timestamp>-<sha>/<target>[-min]/ plus a combined
 *   summary.md, and artifacts/e2e/latest pointing at the run.
 * - --wp=min switches wp-env to the minimum WordPress (WP_ENV_CORE +
 *   `wp-env start --update`) and back to the latest at the end.
 * - The last line is always `E2E RESULT: PASS|FAIL …`; the exit code matches.
 */

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { preflight } from './preflight.mjs';
import { ROOT, WP_ENV_BIN, checkNode, fail, run, targetDefs, wpVersions } from './lib.mjs';
import { summarizeRun } from './summarize.mjs';

checkNode();

const args = process.argv.slice( 2 );
const dashDash = args.indexOf( '--' );
const passthrough = dashDash >= 0 ? args.slice( dashDash + 1 ) : [];
const own = dashDash >= 0 ? args.slice( 0, dashDash ) : args;
const opt = ( name ) => {
	const hit = own.find( ( a ) => a.startsWith( `--${ name }=` ) );
	return hit ? hit.slice( name.length + 3 ) : undefined;
};
const flag = ( name ) => own.includes( `--${ name }` );

const defs = targetDefs();
const versions = wpVersions();

/**
 * Switches every running wp-env environment of this repo to a WordPress line.
 *
 * @param {string}   line    latest|min.
 * @param {string[]} configs Config files.
 */
function switchCore( line, configs ) {
	const zip = `https://wordpress.org/wordpress-${ versions[ line ] }.zip`;
	for ( const config of configs ) {
		process.stdout.write( `wp-env (${ config }): switching to WordPress ${ versions[ line ] }…\n` );
		const env = { ...process.env };
		if ( 'min' === line ) {
			env.WP_ENV_CORE = zip;
		} else {
			delete env.WP_ENV_CORE;
		}
		const res = run( WP_ENV_BIN, [ 'start', '--update', `--config=${ config }` ], { env, stdio: [ 'ignore', 'pipe', 'pipe' ] } );
		if ( 0 !== res.status ) {
			throw new Error( `could not switch ${ config } to WordPress ${ versions[ line ] }: ${ ( res.stderr || res.stdout ).trim().split( '\n' ).pop().replace( /^E2E RESULT: FAIL /, '' ) }` );
		}
	}
}

const switchTo = opt( 'switch-core' );
if ( switchTo ) {
	if ( ! versions[ switchTo ] ) {
		fail( `--switch-core must be latest or min.` );
	}
	try {
		switchCore( switchTo, [ '.wp-env.json', '.wp-env.multisite.json' ].filter( ( c ) => 0 === run( WP_ENV_BIN, [ 'status', `--config=${ c }` ] ).status ) );
	} catch ( error ) {
		fail( error.message );
	}
	process.stdout.write( `E2E RESULT: PASS switched wp-env to WordPress ${ versions[ switchTo ] }\n` );
	process.exit( 0 );
}

const targets = ( opt( 'target' ) || 'wp-env' ).split( ',' ).filter( Boolean );
const lines = ( opt( 'wp' ) || 'latest' ).split( ',' ).filter( Boolean );
for ( const t of targets ) {
	if ( ! defs[ t ] ) {
		fail( `unknown target "${ t }" (expected ${ Object.keys( defs ).join( ', ' ) }).` );
	}
}
for ( const l of lines ) {
	if ( ! versions[ l ] ) {
		fail( `--wp must be latest, min or both (got "${ l }").` );
	}
}

const sha = run( 'git', [ 'rev-parse', '--short', 'HEAD' ] ).stdout.trim() || 'nogit';
const stamp = new Date().toISOString().replace( /[-:]/g, '' ).replace( /\..*$/, '' );
const runDir = join( ROOT, 'artifacts/e2e', `${ stamp }-${ sha }` );
const latest = join( ROOT, 'artifacts/e2e/latest' );

/**
 * Creates the run directory and points artifacts/e2e/latest at it, once the
 * preflight has passed (a refused run leaves the previous results in place).
 */
function ensureRunDir() {
	if ( existsSync( runDir ) ) {
		return;
	}
	mkdirSync( runDir, { recursive: true } );
	rmSync( latest, { force: true } );
	symlinkSync( runDir, latest );
}

const playwrightArgs = [ 'test', '-c', 'tests/e2e/playwright.config.ts' ];
const project = opt( 'project' );
if ( project ) {
	// Setup (logins) always runs; it is a dependency of every project.
	playwrightArgs.push( `--project=${ project }` );
}
if ( flag( 'update-snapshots' ) ) {
	playwrightArgs.push( '--update-snapshots' );
}
playwrightArgs.push( ...passthrough );

/**
 * Runs Playwright for one target and prefixes its output.
 *
 * @param {string} target Target name.
 * @param {string} line   latest|min.
 * @param {string} dir    Artifacts directory.
 * @return {Promise<number>} Exit code.
 */
function runTarget( target, line, dir ) {
	return new Promise( ( resolve ) => {
		const env = { ...process.env, E2E_TARGET: target, E2E_WP: line, E2E_ARTIFACTS: dir, WP_BASE_URL: defs[ target ].baseURL, FORCE_COLOR: '0' };
		const child = spawn( join( ROOT, 'node_modules/.bin/playwright' ), playwrightArgs, { cwd: ROOT, env } );
		const prefix = `[${ target }${ 'min' === line ? '@min' : '' }] `;
		const pipe = ( stream, out ) => {
			let buffer = '';
			stream.on( 'data', ( chunk ) => {
				buffer += chunk.toString();
				const parts = buffer.split( '\n' );
				buffer = parts.pop();
				for ( const l of parts ) {
					out.write( prefix + l + '\n' );
				}
			} );
			stream.on( 'end', () => buffer && out.write( prefix + buffer + '\n' ) );
		};
		pipe( child.stdout, process.stdout );
		pipe( child.stderr, process.stderr );
		child.on( 'close', ( code ) => resolve( null === code ? 1 : code ) );
	} );
}

const started = Date.now();
const results = [];
let switched = false;
let aborted = null;

try {
	for ( const line of lines ) {
		const wpEnvTargets = targets.filter( ( t ) => 'wp-env' === defs[ t ].kind );
		const localTargets = targets.filter( ( t ) => 'local' === defs[ t ].kind );

		if ( wpEnvTargets.length ) {
			// Make sure the environments run the WordPress line under test.
			const configs = [];
			for ( const t of wpEnvTargets ) {
				const check = await preflight( t, line );
				if ( ! check.ok && /WordPress .* is running, expected/.test( check.reason ) ) {
					configs.push( defs[ t ].config );
				}
			}
			// A min run always ends on the latest WordPress, even if the
			// environments were already on the minimum when it started.
			switched = switched || 'min' === line;
			if ( configs.length ) {
				switchCore( line, configs );
			}
		}

		for ( const t of [ ...wpEnvTargets, ...localTargets ] ) {
			const check = await preflight( t, line );
			if ( ! check.ok ) {
				throw new Error( check.reason );
			}
			process.stdout.write( `preflight ${ t }: OK (WordPress ${ check.ping.wp }, PHP ${ check.ping.php }, Zero Spam ${ check.ping.plugin })\n` );
		}

		ensureRunDir();
		const dirFor = ( t ) => join( runDir, `${ t }${ 'min' === line ? '-min' : '' }` );
		const codes = await Promise.all( wpEnvTargets.map( ( t ) => runTarget( t, line, dirFor( t ) ).then( ( code ) => ( { target: t, line, code } ) ) ) );
		results.push( ...codes );
		for ( const t of localTargets ) {
			results.push( { target: t, line, code: await runTarget( t, line, dirFor( t ) ) } );
		}
	}
} catch ( error ) {
	aborted = error.message;
} finally {
	if ( switched ) {
		try {
			switchCore( 'latest', [ ...new Set( targets.filter( ( t ) => 'wp-env' === defs[ t ].kind ).map( ( t ) => defs[ t ].config ) ) ] );
		} catch ( error ) {
			aborted = aborted || error.message;
		}
	}
}

if ( aborted ) {
	fail( aborted );
}

const summary = summarizeRun( runDir );
writeFileSync( join( runDir, 'summary.md' ), summary.markdown );
process.stdout.write( `\n${ summary.markdown }\n` );
process.stdout.write( `Artifacts: ${ runDir }${ existsSync( latest ) ? ' (artifacts/e2e/latest)' : '' }\n` );

const failed = results.filter( ( r ) => 0 !== r.code );
const minutes = ( ( Date.now() - started ) / 60000 ).toFixed( 1 );
const label = results.map( ( r ) => `${ r.target }${ 'min' === r.line ? '@min' : '' }=${ 0 === r.code ? 'pass' : 'fail' }` ).join( ' ' );
process.stdout.write( `E2E RESULT: ${ failed.length ? 'FAIL' : 'PASS' } ${ label } (${ summary.totals }; ${ minutes } min)\n` );
process.exit( failed.length ? 1 : 0 );
