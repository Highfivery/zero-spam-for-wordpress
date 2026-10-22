/**
 * Prepares the two LocalWP sites for the e2e suite (`npm run e2e:setup:local`).
 * This changes the Local sites, so it asks first (or pass --yes).
 *
 * On both sites (http://wordpress-zero-spam.local/, http://zero-spam-network.local/):
 * 1. `wp config set ZEROSPAM_E2E_TOKEN <random>` (and ZEROSPAM_RESCUE_KEY with
 *    --rescue). A constant that already exists and was not created by an
 *    earlier run of this script is never overwritten (its value is used
 *    instead); the constants this script created are recorded in .env
 *    (E2E_LOCAL_CREATED_CONSTANTS, E2E_NETWORK_CREATED_CONSTANTS) and only
 *    those are removed by the teardown;
 * 2. symlinks tests/e2e/mu-plugins/zero-spam-e2e.php into wp-content/mu-plugins/;
 * 3. creates e2eadmin (administrator; super admin on the network) and
 *    e2eeditor (editor) with random passwords (passed on stdin with
 *    --prompt=user_pass, never on the command line), and on the network
 *    e2esiteadmin, administrator of /test-site-1/ only;
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
const password = () => randomBytes( 18 ).toString( 'base64url' );

/**
 * Runs WP-CLI and fails with its output.
 *
 * @param {Object}   site   Site definition.
 * @param {string[]} args   WP-CLI args.
 * @param {string}   secret Value passed on stdin for --prompt (redacted from any message).
 * @return {string}
 */
function wp( site, args, secret = undefined ) {
	const res = localCli( site, args, undefined === secret ? undefined : `${ secret }\n` );
	if ( 0 !== res.status ) {
		const last = ( res.stderr || res.stdout ).trim().split( '\n' ).pop();
		fail( `wp ${ args.join( ' ' ) } on ${ site.baseURL } failed: ${ secret ? last.split( secret ).join( '***' ) : last }` );
	}
	return res.stdout.trim();
}

/**
 * Creates a user or resets its password. The password goes to WP-CLI on
 * stdin (--prompt=user_pass), so it never appears in the process list.
 *
 * @param {Object}   site  Site definition.
 * @param {string}   login Login.
 * @param {string}   role  Role.
 * @param {string}   pass  Password.
 * @param {string[]} extra Extra args (e.g. --url).
 */
function ensureUser( site, login, role, pass, extra = [] ) {
	if ( 0 === localCli( site, [ 'user', 'get', login, '--field=ID' ] ).status ) {
		wp( site, [ 'user', 'update', login, '--skip-email', '--prompt=user_pass' ], pass );
		wp( site, [ 'user', 'set-role', login, role, ...extra ] );
	} else {
		wp( site, [ 'user', 'create', login, `${ login }@example.com`, `--role=${ role }`, '--porcelain', '--prompt=user_pass', ...extra ], pass );
	}
}

/**
 * A wp-config.php constant of a site: its value, or null when it is not defined.
 *
 * @param {Object} site Site definition.
 * @param {string} name Constant.
 * @return {string|null}
 */
function configConstant( site, name ) {
	if ( 0 !== localCli( site, [ 'config', 'has', name, '--type=constant' ] ).status ) {
		return null;
	}
	return wp( site, [ 'config', 'get', name, '--type=constant' ] );
}

// Constants each site already had before this script ever touched it are
// "foreign": they are read, never overwritten, and never removed by teardown.
const created = {};
const foreign = {};
for ( const site of sites ) {
	created[ site.envPrefix ] = ( previous[ `${ site.envPrefix }_CREATED_CONSTANTS` ] || '' ).split( ',' ).filter( Boolean );
	foreign[ site.envPrefix ] = {};
	for ( const name of [ 'ZEROSPAM_E2E_TOKEN', 'ZEROSPAM_RESCUE_KEY' ] ) {
		const value = configConstant( site, name );
		if ( null !== value && ! created[ site.envPrefix ].includes( name ) ) {
			foreign[ site.envPrefix ][ name ] = value;
		}
	}
}

const foreignTokens = [ ...new Set( sites.map( ( s ) => foreign[ s.envPrefix ].ZEROSPAM_E2E_TOKEN ).filter( ( v ) => undefined !== v ) ) ];
if ( foreignTokens.length > 1 ) {
	fail( 'The two Local sites already define different ZEROSPAM_E2E_TOKEN values that this script did not create; remove one of them (wp config delete ZEROSPAM_E2E_TOKEN) and rerun.' );
}
const token = foreignTokens[ 0 ] || previous.E2E_TOKEN || randomBytes( 24 ).toString( 'hex' );

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
	E2E_LOCAL_RESCUE_KEY: '',
	E2E_NETWORK_RESCUE_KEY: '',
	E2E_LOCAL_CREATED_CONSTANTS: '',
	E2E_NETWORK_CREATED_CONSTANTS: '',
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
	const prefix = site.envPrefix;
	const ours = new Set( created[ prefix ] );

	if ( undefined === foreign[ prefix ].ZEROSPAM_E2E_TOKEN ) {
		wp( site, [ 'config', 'set', 'ZEROSPAM_E2E_TOKEN', token, '--type=constant' ] );
		ours.add( 'ZEROSPAM_E2E_TOKEN' );
	}

	// Rescue key: the site's own one if it already has it (never replaced);
	// otherwise, with --rescue or when an earlier run created it, ours.
	if ( undefined !== foreign[ prefix ].ZEROSPAM_RESCUE_KEY ) {
		env[ `${ prefix }_RESCUE_KEY` ] = withRescue ? foreign[ prefix ].ZEROSPAM_RESCUE_KEY : '';
	} else if ( withRescue || ours.has( 'ZEROSPAM_RESCUE_KEY' ) ) {
		const rescue = previous[ `${ prefix }_RESCUE_KEY` ] || previous.E2E_RESCUE_KEY || randomBytes( 16 ).toString( 'hex' );
		wp( site, [ 'config', 'set', 'ZEROSPAM_RESCUE_KEY', rescue, '--type=constant' ] );
		ours.add( 'ZEROSPAM_RESCUE_KEY' );
		env[ `${ prefix }_RESCUE_KEY` ] = rescue;
	}
	env[ `${ prefix }_CREATED_CONSTANTS` ] = [ ...ours ].join( ',' );

	const muDir = join( site.path, 'wp-content/mu-plugins' );
	mkdirSync( muDir, { recursive: true } );
	const link = join( muDir, 'zero-spam-e2e.php' );
	if ( ! existsSync( link ) && ! lstatSync( link, { throwIfNoEntry: false } ) ) {
		symlinkSync( muPlugin, link );
	}

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
