/**
 * Resolves E2E_TARGET into everything a run needs: URLs, accounts, the WP-CLI
 * helper and where artifacts go.
 *
 * Targets:
 * - wp-env     disposable single site, http://localhost:8900 (.wp-env.json)
 * - wp-env-ms  disposable subdirectory network, http://localhost:8910 (.wp-env.multisite.json)
 * - local      LocalWP single site, http://wordpress-zero-spam.local/
 * - network    LocalWP subdirectory network, http://zero-spam-network.local/
 *
 * Local accounts and the token come from the git-ignored tests/e2e/.env that
 * `npm run e2e:setup:local` writes; wp-env accounts from wp-env-users.json.
 */

import { existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join, resolve } from 'node:path';

export type TargetName = 'wp-env' | 'wp-env-ms' | 'local' | 'network';
export type Role = 'admin' | 'editor' | 'author' | 'siteadmin';

export interface Account {
	username: string;
	password: string;
}

export type CliConfig =
	| { kind: 'wp-env'; config: string }
	| { kind: 'local'; siteId: string; path: string; url: string };

export interface Target {
	name: TargetName;
	kind: 'wp-env' | 'local';
	multisite: boolean;
	/** Site root with a trailing slash. */
	baseURL: string;
	/** Path of the subsite used by network tests (multisite only), e.g. /site2/. */
	subsitePath: string | null;
	/** WordPress line under test: latest or min (wp-env only; Local is whatever it runs). */
	wp: 'latest' | 'min';
	token: string;
	rescueKey: string | null;
	users: Partial< Record< Role, Account > >;
	cli: CliConfig;
	/** Artifacts directory of this target for this run. */
	artifacts: string;
	/** Where per-role storage states are written. */
	authDir: string;
	/** Destructive specs (uninstall) may run. Always on wp-env; Local needs E2E_ALLOW_DESTRUCTIVE=1. */
	allowDestructive: boolean;
	/** Visual baselines are compared only on wp-env with the latest WordPress. */
	compareSnapshots: boolean;
}

export const E2E_DIR = resolve( __dirname, '..' );
export const ROOT = resolve( E2E_DIR, '../..' );

interface TargetDefinition {
	kind: 'wp-env' | 'local';
	baseURL: string;
	multisite: boolean;
	subsitePath: string | null;
	config?: string;
	siteId?: string;
	path?: string;
	envPrefix?: string;
}

/** tests/e2e/targets.json, shared with the runner scripts. */
const DEFINITIONS = JSON.parse( readFileSync( join( E2E_DIR, 'targets.json' ), 'utf8' ) ) as Record< string, TargetDefinition >;

/**
 * Reads a value from the environment or fails with a pointer to the setup step.
 *
 * @param key Variable name.
 */
function required( key: string ): string {
	const value = process.env[ key ];
	if ( ! value ) {
		throw new Error( `${ key } is not set. Run \`npm run e2e:setup:local\` (writes tests/e2e/.env); see AGENTS.md section 6.` );
	}
	return value;
}

/**
 * Builds the target description for a target name.
 *
 * @param name Target name (defaults to E2E_TARGET or wp-env).
 */
export function resolveTarget( name: string | undefined = process.env.E2E_TARGET ): Target {
	const target = buildTarget( name );
	// E2E_BASE_URL points a target somewhere else (used to check the preflight's failure path).
	if ( process.env.E2E_BASE_URL ) {
		target.baseURL = process.env.E2E_BASE_URL.replace( /\/?$/, '/' );
	}
	return target;
}

/**
 * Builds the target description without overrides.
 *
 * @param name Target name.
 */
function buildTarget( name: string | undefined ): Target {
	const targetName = ( name || 'wp-env' ) as TargetName;
	const wp = 'min' === process.env.E2E_WP ? 'min' : 'latest';
	const artifacts = process.env.E2E_ARTIFACTS
		? resolve( process.env.E2E_ARTIFACTS )
		: join( ROOT, 'artifacts/e2e/adhoc', targetName );
	const authDir = join( E2E_DIR, '.auth' );

	const def = DEFINITIONS[ targetName ];
	if ( ! def || targetName.startsWith( '_' ) ) {
		throw new Error( `Unknown E2E_TARGET "${ targetName }" (expected wp-env, wp-env-ms, local or network).` );
	}

	if ( 'wp-env' === def.kind ) {
		const users = JSON.parse( readFileSync( join( E2E_DIR, 'wp-env-users.json' ), 'utf8' ) );
		const multisite = def.multisite;
		const account = ( role: Role ): Account => ( { username: users[ role ].user, password: users[ role ].pass } );

		return {
			name: targetName,
			kind: 'wp-env',
			multisite,
			baseURL: def.baseURL,
			subsitePath: def.subsitePath,
			wp,
			token: users.token,
			rescueKey: users.rescueKey,
			users: multisite
				? { admin: account( 'admin' ), editor: account( 'editor' ), author: account( 'author' ), siteadmin: account( 'siteadmin' ) }
				: { admin: account( 'admin' ), editor: account( 'editor' ), author: account( 'author' ) },
			cli: { kind: 'wp-env', config: def.config! },
			artifacts,
			authDir,
			allowDestructive: true,
			compareSnapshots: 'latest' === wp,
		};
	}

	{
		const envFile = join( E2E_DIR, '.env' );
		if ( existsSync( envFile ) ) {
			process.loadEnvFile( envFile );
		}
		const path = def.path!.replace( /^~(?=\/)/, homedir() );
		const prefix = def.envPrefix!;
		const users: Partial< Record< Role, Account > > = {
			admin: { username: required( `${ prefix }_ADMIN_USER` ), password: required( `${ prefix }_ADMIN_PASS` ) },
			editor: { username: required( `${ prefix }_EDITOR_USER` ), password: required( `${ prefix }_EDITOR_PASS` ) },
		};
		if ( def.multisite ) {
			users.siteadmin = { username: required( 'E2E_NETWORK_SITEADMIN_USER' ), password: required( 'E2E_NETWORK_SITEADMIN_PASS' ) };
		}

		return {
			name: targetName,
			kind: 'local',
			multisite: def.multisite,
			baseURL: def.baseURL,
			subsitePath: def.subsitePath,
			wp,
			token: required( 'E2E_TOKEN' ),
			rescueKey: process.env.E2E_RESCUE_KEY || null,
			users,
			cli: { kind: 'local', siteId: def.siteId!, path, url: def.baseURL },
			artifacts,
			authDir,
			allowDestructive: '1' === process.env.E2E_ALLOW_DESTRUCTIVE,
			compareSnapshots: false,
		};
	}
}

/**
 * Storage-state file of a role on a target.
 *
 * @param target Target.
 * @param role   Role.
 */
export function storageStatePath( target: Target, role: Role ): string {
	return join( target.authDir, `${ target.name }-${ role }.json` );
}

/**
 * URL of a path on the subsite used by network tests.
 *
 * @param target Target.
 * @param path   Path relative to the subsite root.
 */
export function subsiteURL( target: Target, path = '' ): string {
	if ( ! target.subsitePath ) {
		throw new Error( `${ target.name } has no subsite.` );
	}
	return new URL( target.subsitePath.replace( /^\//, '' ) + path.replace( /^\//, '' ), target.baseURL ).toString();
}
