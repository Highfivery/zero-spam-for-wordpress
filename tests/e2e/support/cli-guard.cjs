/**
 * Refuses WP-CLI commands and container shell scripts that would delete the
 * plugin directory. On every target that directory is this repository (wp-env
 * maps the checkout; Local symlinks it), so `wp plugin delete` or
 * `wp plugin uninstall` without --skip-delete would delete the checkout.
 *
 * Shared by the runner scripts (tests/e2e/bin/lib.mjs) and the Playwright
 * helpers (support/wp-cli.ts). CommonJS so both ES modules and the
 * TypeScript compiled by Playwright can load it; types in cli-guard.d.cts.
 */

'use strict';

/**
 * Throws for WP-CLI arguments that would delete the plugin files.
 *
 * Refused: `plugin delete` (any plugin, --all included) and `plugin uninstall`
 * (any plugin, --all included) without a bare --skip-delete.
 *
 * @param {string[]} args WP-CLI arguments without the leading "wp".
 */
function guardDestructive( args ) {
	const words = args.filter( ( a ) => ! a.startsWith( '-' ) );
	if ( 'plugin' !== words[ 0 ] ) {
		return;
	}
	if ( 'delete' === words[ 1 ] ) {
		throw new Error( 'Refusing `wp plugin delete`: the plugin directory is this repository.' );
	}
	if ( 'uninstall' === words[ 1 ] && ! args.includes( '--skip-delete' ) ) {
		throw new Error( 'Refusing `wp plugin uninstall` without --skip-delete: the plugin directory is this repository.' );
	}
}

/**
 * Throws when a shell script run in a wp-env container contains a WP-CLI
 * command guardDestructive() refuses, or removes anything under
 * wp-content/plugins (the checkout is mounted there).
 *
 * @param {string} script sh script.
 */
function guardShellScript( script ) {
	for ( const command of script.split( /[\n;&|()]+|\$\(/ ) ) {
		const words = command.trim().split( /\s+/ ).map( ( w ) => w.replace( /^['"]|['"]$/g, '' ) );
		const wp = words.indexOf( 'wp' );
		if ( wp >= 0 ) {
			guardDestructive( words.slice( wp + 1 ) );
		}
		if ( 'rm' === words[ 0 ] && words.some( ( w ) => /(^|\/)(wp-content\/)?plugins(\/|$)/.test( w ) ) ) {
			throw new Error( 'Refusing to remove files under wp-content/plugins: the plugin directory is this repository.' );
		}
	}
}

module.exports = { guardDestructive, guardShellScript };
