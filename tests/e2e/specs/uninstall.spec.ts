import { expect, test } from '../support/fixtures';

test.describe( 'Uninstall', { tag: '@destructive' }, () => {
	test.afterEach( async ( { wpcli, target, zs } ) => {
		// Put the plugin back for whatever runs next (and for a Local site's restore).
		wpcli.run( [ 'plugin', 'activate', 'zero-spam', ...( target.multisite ? [ '--network' ] : [] ) ] );
		await zs.reset( 'baseline' ).catch( () => undefined );
	} );

	test( 'wp plugin uninstall zero-spam --skip-delete removes every zero-spam-*/zerospam_*/wpzerospam* option, table, transient and cron event', async ( { wpcli, zs, target } ) => {
		test.skip( ! target.allowDestructive, 'Uninstall on a Local site needs E2E_ALLOW_DESTRUCTIVE=1 (the run snapshots and restores the plugin data).' );
		test.fail( true, 'AGENTS.md §8: uninstall.php leaves module options (zero-spam-settings and others), zerospam_blocked_email_domains, the network_* tables and the network site options behind' );
		const network = target.multisite ? [ '--network' ] : [];

		await test.step( 'create data in every store', async () => {
			await zs.reset( 'baseline' );
			await zs.seedLog( 10 );
			await zs.block( '198.51.100.200' );
			if ( target.multisite ) {
				await zs.call( 'POST', 'network-setting', { key: 'verify_comments', value: 'enabled', locked: false } );
			}
			const before = await zs.inventory();
			expect( before.tables.length ).toBeGreaterThan( 0 );
		} );

		await test.step( 'deactivate and uninstall, keeping the files (the plugin directory is this repository)', async () => {
			wpcli.exec( [ 'plugin', 'deactivate', 'zero-spam', ...network ] );
			wpcli.exec( [ 'plugin', 'uninstall', 'zero-spam', '--skip-delete' ] );
		} );

		await test.step( 'nothing of the plugin is left', async () => {
			const after = await zs.inventory();
			await test.info().attach( 'inventory-after-uninstall', { body: JSON.stringify( after, null, 2 ), contentType: 'application/json' } );
			expect.soft( after.tables, 'tables' ).toEqual( [] );
			expect.soft( after.cron, 'cron events' ).toEqual( [] );
			expect.soft( after.sitemeta, 'network options' ).toEqual( [] );
			for ( const [ blog, options ] of Object.entries( after.options ) ) {
				expect.soft( options, `options on site ${ blog }` ).toEqual( [] );
			}
		} );
	} );
} );
