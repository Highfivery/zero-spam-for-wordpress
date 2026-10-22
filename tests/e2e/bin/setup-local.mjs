/**
 * Prepares the two LocalWP sites for the e2e suite (`npm run e2e:setup:local`).
 * This changes the Local sites, so it asks first (or pass --yes).
 *
 * On both sites (http://wordpress-zero-spam.local/, http://zero-spam-network.local/):
 * 1. `wp config set ZEROSPAM_E2E_TOKEN <random>` (and ZEROSPAM_RESCUE_KEY with --rescue);
 * 2. symlinks tests/e2e/mu-plugins/zero-spam-e2e.php into wp-content/mu-plugins/;
 * 3. creates e2eadmin (administrator; super admin on the network) and
 *    e2eeditor (editor) with random passwords, and on the network e2esiteadmin,
 *    administrator of /test-site-1/ only;
 * 4. creates the "E2E comments" post (main site and /test-site-1/);
 * 5. single site: users_can_register=1 (the old value is kept for teardown).
 * Then writes the git-ignored tests/e2e/.env with the accounts and the token.
 *
 * It does not repoint the network site's plugin folder; see AGENTS.md section 6.
 * Undo everything with `npm run e2e:teardown:local`.
 */

import { randomBytes } from 'node:crypto';
import { existsSync, lstatSync, mkdirSync, symlinkSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createInterface } from 'node:readline/promises';

import { E2E_DIR, checkNode, fail, localCli, localPaths, readEnvFile, targetDefs } from './lib.mjs';

checkNode();

const yes = process.argv.includes( '--yes' );
const withRescue = process.argv.includes( '--rescue' );
const defs = targetDefs();
const sites = [ defs.local, defs.network ];
const envFile = join( E2E_DIR, '.env' );
const muPlugin = join( E2E_DIR, 'mu-plugins/zero-spam-e2e.php' );

for ( const site of sites ) {
	if ( ! existsSync( localPaths( site.siteId ).socket ) ) {
		fail( `"${ site.localName }" is not running in Local (no MySQL socket for ${ site.siteId }); start it first.` );
	}
}

if ( ! yes ) {
	const rl = createInterface( { input: process.stdin, output: process.stdout } );
	const answer = await rl.question(
		'This adds a wp-config constant, a mu-plugin symlink, three test users and a post to your Local sites\n' +
			`(${ sites.map( ( s ) => s.baseURL ).join( ', ' ) }) and enables registration on the single site.\nContinue? [y/N] `
	);
	rl.close();
	if ( ! /^y(es)?$/i.test( answer.trim() ) ) {
		process.stdout.write( 'Nothing changed.\n' );
		process.exit( 0 );
	}
}

const previous = readEnvFile( envFile );
const token = previous.E2E_TOKEN || randomBytes( 24 ).toString( 'hex' );
const rescue = withRescue ? previous.E2E_RESCUE_KEY || randomBytes( 16 ).toString( 'hex' ) : '';
const password = () => randomBytes( 18 ).toString( 'base64url' );

/**
 * Runs WP-CLI and fails with its output.
 *
 * @param {Object}   site Site definition.
 * @param {string[]} args WP-CLI args.
 * @return {string}
 */
function wp( site, args ) {
	const res = localCli( site, args );
	if ( 0 !== res.status ) {
		fail( `wp ${ args.join( ' ' ) } on ${ site.baseURL } failed: ${ ( res.stderr || res.stdout ).trim().split( '\n' ).pop() }` );
	}
	return res.stdout.trim();
}

/**
 * Creates a user or resets its password.
 *
 * @param {Object}   site  Site definition.
 * @param {string}   login Login.
 * @param {string}   role  Role.
 * @param {string}   pass  Password.
 * @param {string[]} extra Extra args (e.g. --url).
 */
function ensureUser( site, login, role, pass, extra = [] ) {
	if ( 0 === localCli( site, [ 'user', 'get', login, '--field=ID' ] ).status ) {
		wp( site, [ 'user', 'update', login, `--user_pass=${ pass }`, '--skip-email' ] );
		wp( site, [ 'user', 'set-role', login, role, ...extra ] );
	} else {
		wp( site, [ 'user', 'create', login, `${ login }@example.com`, `--role=${ role }`, `--user_pass=${ pass }`, '--porcelain', ...extra ] );
	}
}

/**
 * Creates the comment-test post if it is missing.
 *
 * @param {Object}   site  Site definition.
 * @param {string[]} extra Extra args (e.g. --url).
 */
function ensurePost( site, extra = [] ) {
	if ( ! wp( site, [ 'post', 'list', '--post_type=post', '--name=e2e-comments', '--format=ids', ...extra ] ) ) {
		wp( site, [ 'post', 'create', '--post_type=post', '--post_status=publish', '--post_title=E2E comments', '--post_name=e2e-comments', '--comment_status=open', '--post_content=A post for the Zero Spam comment tests.', '--porcelain', ...extra ] );
	}
}

const env = {
	E2E_TOKEN: token,
	E2E_RESCUE_KEY: rescue,
	E2E_LOCAL_ADMIN_USER: 'e2eadmin',
	E2E_LOCAL_ADMIN_PASS: password(),
	E2E_LOCAL_EDITOR_USER: 'e2eeditor',
	E2E_LOCAL_EDITOR_PASS: password(),
	E2E_NETWORK_ADMIN_USER: 'e2eadmin',
	E2E_NETWORK_ADMIN_PASS: password(),
	E2E_NETWORK_EDITOR_USER: 'e2eeditor',
	E2E_NETWORK_EDITOR_PASS: password(),
	E2E_NETWORK_SITEADMIN_USER: 'e2esiteadmin',
	E2E_NETWORK_SITEADMIN_PASS: password(),
	E2E_LOCAL_ORIG_USERS_CAN_REGISTER: previous.E2E_LOCAL_ORIG_USERS_CAN_REGISTER ?? '',
};

for ( const site of sites ) {
	process.stdout.write( `Preparing ${ site.baseURL }…\n` );
	wp( site, [ 'config', 'set', 'ZEROSPAM_E2E_TOKEN', token, '--type=constant' ] );
	if ( rescue ) {
		wp( site, [ 'config', 'set', 'ZEROSPAM_RESCUE_KEY', rescue, '--type=constant' ] );
	}

	const muDir = join( site.path, 'wp-content/mu-plugins' );
	mkdirSync( muDir, { recursive: true } );
	const link = join( muDir, 'zero-spam-e2e.php' );
	if ( ! existsSync( link ) && ! lstatSync( link, { throwIfNoEntry: false } ) ) {
		symlinkSync( muPlugin, link );
	}

	const prefix = site.envPrefix;
	ensureUser( site, env[ `${ prefix }_ADMIN_USER` ], 'administrator', env[ `${ prefix }_ADMIN_PASS` ] );
	ensureUser( site, env[ `${ prefix }_EDITOR_USER` ], 'editor', env[ `${ prefix }_EDITOR_PASS` ] );
	ensurePost( site );

	if ( site.multisite ) {
		const subsite = `--url=${ new URL( site.subsitePath.slice( 1 ), site.baseURL ) }`;
		wp( site, [ 'super-admin', 'add', env.E2E_NETWORK_ADMIN_USER ] );
		ensureUser( site, env.E2E_NETWORK_SITEADMIN_USER, 'administrator', env.E2E_NETWORK_SITEADMIN_PASS, [ subsite ] );
		localCli( site, [ 'user', 'remove-role', env.E2E_NETWORK_SITEADMIN_USER, `--url=${ site.baseURL }` ] );
		ensurePost( site, [ subsite ] );
	} else {
		if ( '' === env.E2E_LOCAL_ORIG_USERS_CAN_REGISTER ) {
			env.E2E_LOCAL_ORIG_USERS_CAN_REGISTER = wp( site, [ 'option', 'get', 'users_can_register' ] ) || '0';
		}
		wp( site, [ 'option', 'update', 'users_can_register', '1' ] );
	}
}

writeFileSync(
	envFile,
	'# Written by tests/e2e/bin/setup-local.mjs for the Local e2e targets. Git-ignored; never commit.\n' +
		Object.entries( env )
			.map( ( [ k, v ] ) => `${ k }=${ v }` )
			.join( '\n' ) +
		'\n',
	{ mode: 0o600 }
);

process.stdout.write( `Wrote ${ envFile }. Run npm run test:e2e to test both Local sites.\n` );
