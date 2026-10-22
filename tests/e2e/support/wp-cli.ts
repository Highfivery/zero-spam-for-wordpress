/**
 * WP-CLI for the target under test.
 *
 * - wp-env: `docker compose exec cli wp …` against the compose file wp-env
 *   generated for the config (the same thing `wp-env run` does, minus its
 *   spinner on stdout).
 * - Local: Local's own PHP 8.2 binary with the site's MySQL socket and
 *   Homebrew's wp, with --skip-packages so global packages can't print
 *   deprecation noise into JSON output.
 *
 * Refuses `wp plugin delete` and `wp plugin uninstall` without --skip-delete
 * (support/cli-guard.cjs, shared with the runner scripts), because the plugin
 * directory on every target is this repository.
 */

import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

import { guardDestructive } from './cli-guard.cjs';
import { ROOT, type Target } from './targets';

export interface CliResult {
	status: number;
	stdout: string;
	stderr: string;
}

const composeFiles = new Map< string, string >();

/**
 * docker-compose.yml that wp-env generated for a config file.
 *
 * @param config Config file name.
 */
function composeFile( config: string ): string {
	const cached = composeFiles.get( config );
	if ( cached ) {
		return cached;
	}
	const res = spawnSync( join( ROOT, 'node_modules/.bin/wp-env' ), [ 'status', '--json', `--config=${ config }` ], { cwd: ROOT, encoding: 'utf8' } );
	const line = ( res.stdout || '' ).split( '\n' ).find( ( l ) => l.trim().startsWith( '{' ) );
	const status = line ? JSON.parse( line ) : {};
	const file = status.installPath ? join( status.installPath, 'docker-compose.yml' ) : '';
	if ( ! file || ! existsSync( file ) ) {
		throw new Error( `wp-env (${ config }) is not running; start it with npm run env:start${ config.includes( 'multisite' ) ? ':ms' : '' }.` );
	}
	composeFiles.set( config, file );
	return file;
}

/**
 * Newest LocalWP PHP 8.2 binary.
 */
function localPhp(): string {
	const services = join( homedir(), 'Library/Application Support/Local/lightning-services' );
	const dirs = existsSync( services )
		? readdirSync( services )
			.filter( ( d ) => /^php-8\.2\./.test( d ) )
			.sort( ( a, b ) => b.localeCompare( a, undefined, { numeric: true } ) )
		: [];
	for ( const dir of dirs ) {
		const bin = join( services, dir, 'bin' );
		const arch = existsSync( bin ) ? readdirSync( bin ).find( ( d ) => d.startsWith( 'darwin' ) ) : undefined;
		if ( arch && existsSync( join( bin, arch, 'bin/php' ) ) ) {
			return join( bin, arch, 'bin/php' );
		}
	}
	throw new Error( 'LocalWP PHP 8.2 binary not found under ~/Library/Application Support/Local/lightning-services/.' );
}

export class WpCli {
	constructor( private readonly target: Target ) {}

	/**
	 * Runs WP-CLI and returns the raw result.
	 *
	 * @param args WP-CLI arguments without "wp".
	 */
	run( args: string[] ): CliResult {
		guardDestructive( args );
		const cli = this.target.cli;
		const res =
			'wp-env' === cli.kind
				? spawnSync( 'docker', [ 'compose', '-f', composeFile( cli.config ), 'exec', '-T', '-w', '/var/www/html', 'cli', 'wp', ...args ], { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 } )
				: spawnSync(
					localPhp(),
					[
						'-d',
						`mysqli.default_socket=${ join( homedir(), 'Library/Application Support/Local/run', cli.siteId, 'mysql/mysqld.sock' ) }`,
						'-d',
						'memory_limit=512M',
						existsSync( '/opt/homebrew/bin/wp' ) ? '/opt/homebrew/bin/wp' : 'wp',
						'--skip-packages',
						`--path=${ cli.path }`,
						...args,
					],
					{ cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 }
				);
		return {
			status: null === res.status ? 1 : res.status,
			stdout: res.stdout || '',
			stderr: ( res.stderr || '' ) + ( res.error ? String( res.error ) : '' ),
		};
	}

	/**
	 * Runs WP-CLI and throws on a non-zero exit.
	 *
	 * @param args WP-CLI arguments without "wp".
	 */
	exec( args: string[] ): string {
		const res = this.run( args );
		if ( 0 !== res.status ) {
			throw new Error( `wp ${ args.join( ' ' ) } failed (${ res.status }): ${ res.stderr.trim() || res.stdout.trim() }` );
		}
		return res.stdout.trim();
	}

	/**
	 * Runs WP-CLI with --format=json and parses stdout only.
	 *
	 * @param args WP-CLI arguments without "wp" and without --format.
	 */
	json< T = unknown >( args: string[] ): T {
		const out = this.exec( [ ...args, '--format=json' ] );
		const start = out.search( /[[{]/ );
		return JSON.parse( start >= 0 ? out.slice( start ) : out ) as T;
	}
}
