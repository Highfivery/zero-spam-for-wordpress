/**
 * Every WP-CLI command Help lists must be one the plugin registers.
 */

/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { cliCommands } from '../lists';

const CLI = readFileSync(
	resolve( __dirname, '../../../../../includes/class-cli.php' ),
	'utf8'
);

/**
 * The subcommands of `wp zerospam`: those added by name, and the public
 * methods of ZeroSpamCLI (WP-CLI keeps a method's name as it is).
 *
 * @return {Set<string>} Subcommands.
 */
function registered() {
	const names = new Set();
	for ( const [ , name ] of CLI.matchAll(
		/add_command\(\s*'zerospam ([a-z_-]+)/g
	) ) {
		names.add( name );
	}
	const body = CLI.slice( CLI.indexOf( 'class ZeroSpamCLI' ) );
	for ( const [ , name ] of body.matchAll(
		/public function ([a-z][a-z_]*)\(/g
	) ) {
		names.add( name );
	}
	return names;
}

describe( 'Help › WP-CLI commands', () => {
	it( 'reads the registered commands', () => {
		expect( registered() ).toContain( 'api_usage' );
		expect( registered() ).toContain( 'network-settings' );
	} );

	it.each( cliCommands().map( ( [ command ] ) => [ command ] ) )(
		'%s is registered',
		( command ) => {
			const [ wp, group, sub ] = command.split( ' ' );
			expect( `${ wp } ${ group }` ).toBe( 'wp zerospam' );
			expect( registered() ).toContain( sub );
		}
	);
} );
