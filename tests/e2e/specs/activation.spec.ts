import { expect, test } from '../support/fixtures';

test.describe( 'Activation', () => {
	test.afterEach( async ( { wpcli, target } ) => {
		// Whatever happened, leave the plugin active.
		wpcli.run( [ 'plugin', 'activate', 'zero-spam', ...( target.multisite ? [ '--network' ] : [] ) ] );
	} );

	test( 'activating Zero Spam from the Plugins screen produces no PHP notices, warnings or deprecations', async ( { page, wpcli, target, gallery } ) => {
		const network = target.multisite ? [ '--network' ] : [];

		await test.step( 'deactivate the plugin with WP-CLI', async () => {
			wpcli.exec( [ 'plugin', 'deactivate', 'zero-spam', ...network ] );
		} );

		await test.step( 'open the Plugins screen and click Activate', async () => {
			await page.goto( target.multisite ? 'wp-admin/network/plugins.php' : 'wp-admin/plugins.php' );
			const row = page.locator( 'tr[data-plugin="zero-spam/wordpress-zero-spam.php"]' );
			await expect( row ).toBeVisible();
			await Promise.all( [ page.waitForEvent( 'load' ), row.locator( '.activate a' ).click() ] );
		} );

		await test.step( 'WordPress confirms the activation', async () => {
			await expect( page.locator( '#message, .notice-success' ).first() ).toContainText( /activated/i );
			await expect( page.locator( 'tr[data-plugin="zero-spam/wordpress-zero-spam.php"]' ) ).toHaveClass( /\bactive\b/ );
			await gallery.capture( page, 'plugin activated' );
		} );
		// The automatic PHP-error check runs after the test.
	} );
} );
