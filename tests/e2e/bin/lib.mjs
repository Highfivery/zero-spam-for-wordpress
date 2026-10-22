/**
 * Shared helpers for the e2e runner scripts (Node 22, no dependencies).
 *
 * Everything here runs on the host. WP-CLI calls go either to a wp-env
 * container (docker compose exec, the same thing `wp-env run` does but without
 * its spinner on stdout) or to a LocalWP site through Local's own PHP binary
 * and MySQL socket.
 */

import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '../../..' );
export const E2E_DIR = join( ROOT, 'tests/e2e' );
export const WP_ENV_BIN = join( ROOT, 'node_modules/.bin/wp-env' );
export const MIN_NODE = [ 22, 19 ];

/**
 * Exits with one line when the running Node is too old (the dependencies need 22.19+).
 */
export function checkNode() {
	const [ major, minor ] = process.versions.node.split( '.' ).map( Number );
	if ( major < MIN_NODE[ 0 ] || ( major === MIN_NODE[ 0 ] && minor < MIN_NODE[ 1 ] ) ) {
		fail( `Node ${ process.versions.node } is too old; the e2e suite needs Node ${ MIN_NODE.join( '.' ) }+ (run \`nvm use\`, .nvmrc pins 22).` );
	}
}

/**
 * Prints a one-line reason and exits 1.
 *
 * @param {string} message Reason.
 */
export function fail( message ) {
	process.stderr.write( `E2E RESULT: FAIL ${ message }\n` );
	process.exit( 1 );
}

/**
 * The pinned WordPress versions.
 *
 * @return {{latest: string, min: string}}
 */
export function wpVersions() {
	return JSON.parse( readFileSync( join( E2E_DIR, 'wp-versions.json' ), 'utf8' ) );
}

/**
 * Target definitions (tests/e2e/targets.json), with ~ expanded.
 *
 * @return {Record<string, Object>}
 */
export function targetDefs() {
	const defs = JSON.parse( readFileSync( join( E2E_DIR, 'targets.json' ), 'utf8' ) );
	delete defs._comment;
	for ( const def of Object.values( defs ) ) {
		if ( def.path ) {
			def.path = def.path.replace( /^~(?=\/)/, homedir() );
		}
	}
	return defs;
}

/**
 * The plugin version in this checkout (wordpress-zero-spam.php).
 *
 * @return {string}
 */
export function repoVersion() {
	const main = readFileSync( join( ROOT, 'wordpress-zero-spam.php' ), 'utf8' );
	const m = main.match( /define\( 'ZEROSPAM_VERSION', '([^']+)' \)/ );
	return m ? m[ 1 ] : '';
}

/**
 * Runs a command synchronously and returns its result without throwing.
 *
 * @param {string}   cmd  Executable.
 * @param {string[]} args Arguments.
 * @param {Object}   opts spawnSync options.
 * @return {{status: number, stdout: string, stderr: string}}
 */
export function run( cmd, args, opts = {} ) {
	const res = spawnSync( cmd, args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, ...opts } );
	return {
		status: null === res.status ? 1 : res.status,
		stdout: res.stdout || '',
		stderr: ( res.stderr || '' ) + ( res.error ? String( res.error ) : '' ),
	};
}

/**
 * Refuses WP-CLI invocations that would delete the plugin directory.
 *
 * Both Local and wp-env point at this checkout, so `wp plugin uninstall`
 * without --skip-delete (or `wp plugin delete`) would delete the repository.
 *
 * @param {string[]} args WP-CLI arguments (without the leading "wp").
 */
export function guardDestructive( args ) {
	const words = args.filter( ( a ) => ! a.startsWith( '--' ) );
	if ( 'plugin' === words[ 0 ] && 'delete' === words[ 1 ] ) {
		throw new Error( 'Refusing `wp plugin delete`: the plugin directory is this repository.' );
	}
	if ( 'plugin' === words[ 0 ] && 'uninstall' === words[ 1 ] && ! args.includes( '--skip-delete' ) ) {
		throw new Error( 'Refusing `wp plugin uninstall` without --skip-delete: the plugin directory is this repository.' );
	}
}

/**
 * The docker-compose file wp-env generated for a config file.
 *
 * @param {string} config Config file name relative to the repo root.
 * @return {string|null}
 */
export function wpEnvComposeFile( config ) {
	const res = run( WP_ENV_BIN, [ 'status', '--json', `--config=${ config }` ] );
	const line = res.stdout.split( '\n' ).find( ( l ) => l.trim().startsWith( '{' ) );
	if ( ! line ) {
		return null;
	}
	const status = JSON.parse( line );
	const install = status.installPath || status.install_path || ( status.runtime && status.runtime.installPath );
	if ( install && existsSync( join( install, 'docker-compose.yml' ) ) ) {
		return join( install, 'docker-compose.yml' );
	}
	return null;
}

/**
 * Runs WP-CLI in a wp-env cli container.
 *
 * @param {string}   composeFile docker-compose.yml of the environment.
 * @param {string[]} args        WP-CLI arguments without "wp".
 * @return {{status: number, stdout: string, stderr: string}}
 */
export function wpEnvCli( composeFile, args ) {
	guardDestructive( args );
	return run( 'docker', [ 'compose', '-f', composeFile, 'exec', '-T', '-w', '/var/www/html', 'cli', 'wp', ...args ] );
}

/**
 * Runs a shell script in a wp-env cli container.
 *
 * @param {string} composeFile docker-compose.yml of the environment.
 * @param {string} script      sh script.
 * @return {{status: number, stdout: string, stderr: string}}
 */
export function wpEnvSh( composeFile, script ) {
	return run( 'docker', [ 'compose', '-f', composeFile, 'exec', '-T', '-w', '/var/www/html', 'cli', 'sh', '-c', script ] );
}

/**
 * LocalWP paths for a site id.
 *
 * @param {string} siteId Local site id (from ~/Library/Application Support/Local/sites.json).
 * @return {{socket: string, php: string|null}}
 */
export function localPaths( siteId ) {
	const support = join( homedir(), 'Library/Application Support/Local' );
	const socket = join( support, 'run', siteId, 'mysql/mysqld.sock' );
	let php = null;
	const services = join( support, 'lightning-services' );
	if ( existsSync( services ) ) {
		const candidates = readdirSync( services )
			.filter( ( d ) => /^php-8\.2\./.test( d ) )
			.sort( ( a, b ) => a.localeCompare( b, undefined, { numeric: true } ) );
		for ( const dir of candidates.reverse() ) {
			const bin = join( services, dir, 'bin' );
			if ( ! existsSync( bin ) ) {
				continue;
			}
			const arch = readdirSync( bin ).find( ( d ) => d.startsWith( 'darwin' ) );
			if ( arch && existsSync( join( bin, arch, 'bin/php' ) ) ) {
				php = join( bin, arch, 'bin/php' );
				break;
			}
		}
	}
	return { socket, php };
}

/**
 * Runs WP-CLI against a LocalWP site.
 *
 * @param {{siteId: string, path: string, url?: string}} site Local site.
 * @param {string[]}                                     args WP-CLI arguments without "wp".
 * @return {{status: number, stdout: string, stderr: string}}
 */
export function localCli( site, args ) {
	guardDestructive( args );
	const { socket, php } = localPaths( site.siteId );
	if ( ! php ) {
		throw new Error( 'LocalWP PHP 8.2 binary not found under ~/Library/Application Support/Local/lightning-services/.' );
	}
	const wp = existsSync( '/opt/homebrew/bin/wp' ) ? '/opt/homebrew/bin/wp' : 'wp';
	return run( php, [ '-d', `mysqli.default_socket=${ socket }`, '-d', 'memory_limit=512M', wp, '--skip-packages', `--path=${ site.path }`, ...args ] );
}

/**
 * Parses a `KEY=value` file (tests/e2e/.env) without exporting it.
 *
 * @param {string} file Path.
 * @return {Record<string, string>}
 */
export function readEnvFile( file ) {
	const out = {};
	if ( ! existsSync( file ) ) {
		return out;
	}
	for ( const line of readFileSync( file, 'utf8' ).split( '\n' ) ) {
		const m = line.match( /^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/ );
		if ( m ) {
			out[ m[ 1 ] ] = m[ 2 ].replace( /^"(.*)"$/, '$1' );
		}
	}
	return out;
}
