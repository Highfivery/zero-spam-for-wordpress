/**
 * Undoes setup-local.mjs on both LocalWP sites (`npm run e2e:teardown:local`):
 * deletes the e2e users and the "E2E comments" posts, removes the
 * ZEROSPAM_E2E_TOKEN / ZEROSPAM_RESCUE_KEY constants and the mu-plugin
 * symlink, restores users_can_register, and deletes tests/e2e/.env.
 * Asks first unless --yes is passed.
 */

import { existsSync, lstatSync, rmSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';
import { createInterface } from 'node:readline/promises';

import { E2E_DIR, checkNode, fail, localCli, localPaths, readEnvFile, targetDefs } from './lib.mjs';

checkNode();

const envFile = join( E2E_DIR, '.env' );
const env = readEnvFile( envFile );
const defs = targetDefs();
const sites = [ defs.local, defs.network ];

for ( const site of sites ) {
	if ( ! existsSync( localPaths( site.siteId ).socket ) ) {
		fail( `"${ site.localName }" is not running in Local; start it first.` );
	}
}

if ( ! process.argv.includes( '--yes' ) ) {
	const rl = createInterface( { input: process.stdin, output: process.stdout } );
	const answer = await rl.question( 'Remove the e2e users, posts, constants and mu-plugin from both Local sites? [y/N] ' );
	rl.close();
	if ( ! /^y(es)?$/i.test( answer.trim() ) ) {
		process.stdout.write( 'Nothing changed.\n' );
		process.exit( 0 );
	}
}

for ( const site of sites ) {
	process.stdout.write( `Cleaning ${ site.baseURL }…\n` );
	const urls = [ site.baseURL ];
	if ( site.multisite ) {
		urls.push( new URL( site.subsitePath.slice( 1 ), site.baseURL ).toString() );
	}
	for ( const url of urls ) {
		const ids = localCli( site, [ 'post', 'list', '--post_type=post', '--name=e2e-comments', '--format=ids', `--url=${ url }` ] ).stdout.trim();
		if ( ids ) {
			localCli( site, [ 'post', 'delete', ...ids.split( /\s+/ ), '--force', `--url=${ url }` ] );
		}
	}

	const logins = site.multisite ? [ 'e2eadmin', 'e2eeditor', 'e2esiteadmin' ] : [ 'e2eadmin', 'e2eeditor' ];
	if ( site.multisite ) {
		localCli( site, [ 'super-admin', 'remove', 'e2eadmin' ] );
	}
	for ( const login of logins ) {
		if ( 0 === localCli( site, [ 'user', 'get', login, '--field=ID' ] ).status ) {
			localCli( site, [ 'user', 'delete', login, '--yes', ...( site.multisite ? [ '--network' ] : [ '--reassign=1' ] ) ] );
		}
	}

	localCli( site, [ 'config', 'delete', 'ZEROSPAM_E2E_TOKEN', '--type=constant' ] );
	localCli( site, [ 'config', 'delete', 'ZEROSPAM_RESCUE_KEY', '--type=constant' ] );

	const link = join( site.path, 'wp-content/mu-plugins/zero-spam-e2e.php' );
	if ( lstatSync( link, { throwIfNoEntry: false } )?.isSymbolicLink() ) {
		unlinkSync( link );
	}

	if ( ! site.multisite && '' !== ( env.E2E_LOCAL_ORIG_USERS_CAN_REGISTER ?? '' ) ) {
		localCli( site, [ 'option', 'update', 'users_can_register', env.E2E_LOCAL_ORIG_USERS_CAN_REGISTER ] );
	}
}

rmSync( envFile, { force: true } );
rmSync( join( E2E_DIR, '.auth' ), { recursive: true, force: true } );
process.stdout.write( 'Local sites restored; tests/e2e/.env removed.\n' );
