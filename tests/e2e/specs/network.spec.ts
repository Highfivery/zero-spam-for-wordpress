import { expect, test } from '../support/fixtures';
import { NetworkSettingsPage } from '../support/pages/network-pages';
import { SETTINGS_PATH } from '../support/pages/settings-page';
import { subsiteURL } from '../support/targets';

const KEY = 'comment_spam_message';

test.describe( 'Network settings', { tag: '@multisite' }, () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { zs } ) => {
		// No site overrides anywhere, so network defaults can apply.
		await zs.reset( 'defaults', { network: true } );
	} );

	test( 'a network default saved by the super admin is inherited by a subsite with no override', async ( { page, asSiteAdmin, target, gallery } ) => {
		const network = new NetworkSettingsPage( page );
		await network.goto( 'settings' );
		const row = network.row( KEY );
		await row.reveal();
		await row.root.locator( 'input[type="text"]' ).fill( 'E2E: network default message.' );
		await row.save();
		await gallery.capture( row.root, 'network default saved' );

		await asSiteAdmin.goto( subsiteURL( target, `${ SETTINGS_PATH }&subview=comments` ) );
		await expect( asSiteAdmin.locator( `#${ KEY }` ) ).toHaveValue( 'E2E: network default message.' );
	} );

	test( 'a locked value is enforced on the subsite even when a save is tampered with', async ( { page, asSiteAdmin, target, gallery } ) => {
		const url = subsiteURL( target, `${ SETTINGS_PATH }&subview=comments` );
		const save = () => Promise.all( [ asSiteAdmin.waitForURL( /settings-updated=true/ ), asSiteAdmin.getByRole( 'button', { name: /Save Settings/ } ).click() ] );

		await test.step( 'the site admin overrides the unlocked network default', async () => {
			await asSiteAdmin.goto( url );
			await asSiteAdmin.locator( `#${ KEY }` ).fill( 'E2E: site message.' );
			await save();
		} );

		const network = new NetworkSettingsPage( page );
		await network.goto( 'settings' );
		const row = network.row( KEY );
		await row.reveal();
		await row.root.locator( 'input[type="text"]' ).fill( 'E2E: locked network message.' );
		await row.toggleLock();
		await row.save();
		await expect( row.root.locator( '.locked-badge' ) ).toBeVisible();
		await gallery.capture( row.root, 'network value locked' );

		await test.step( 'a save with the disabled field forced open does not change it', async () => {
			await asSiteAdmin.goto( url );
			const field = asSiteAdmin.locator( `#${ KEY }` );
			await field.evaluate( ( input ) => input.removeAttribute( 'disabled' ) );
			await field.fill( 'E2E: tampered message.' );
			await save();
			await asSiteAdmin.goto( url );
			await expect( asSiteAdmin.locator( `#${ KEY }` ) ).toHaveValue( 'E2E: locked network message.' );
		} );
	} );

	test( 'the subsite shows a locked field as locked and disabled', async ( { asSiteAdmin, target, gallery } ) => {
		await asSiteAdmin.goto( subsiteURL( target, `${ SETTINGS_PATH }&subview=comments` ) );
		await gallery.capture( asSiteAdmin, 'subsite settings while locked' );
		const field = asSiteAdmin.locator( `#${ KEY }` );
		await expect( field ).toBeDisabled();
		await expect( field ).toHaveAccessibleDescription( /Locked by the network administrator/ );
		await expect( asSiteAdmin.locator( 'tr', { has: field } ).locator( '.zerospam-locked-badge' ) ).toHaveText( /Locked by Network Admin/ );
		await expect( asSiteAdmin.locator( '.zerospam-network-notice' ) ).toContainText( /locked by your Network Administrator/ );
	} );

	test( "unlocking lets the site's override from before the lock win again", async ( { page, asSiteAdmin, target } ) => {
		const network = new NetworkSettingsPage( page );
		await network.goto( 'settings' );
		const row = network.row( KEY );
		await row.toggleLock();

		await asSiteAdmin.goto( subsiteURL( target, `${ SETTINGS_PATH }&subview=comments` ) );
		await expect( asSiteAdmin.locator( `#${ KEY }` ) ).toHaveValue( 'E2E: site message.' );
	} );

	test( 'a plain site admin gets a permission error on the network page; the Network Stats page and the network dashboard widget render', async ( { page, asSiteAdmin, zs, gallery } ) => {
		await zs.reset( 'baseline', { network: true } );

		await test.step( 'site admin is refused', async () => {
			await asSiteAdmin.goto( 'wp-admin/network/settings.php?page=zerospam-network-settings' );
			await expect( asSiteAdmin.locator( 'body' ) ).toContainText( /not allowed|do not have permission/i );
		} );

		await test.step( 'super admin: Network Stats', async () => {
			await page.goto( 'wp-admin/network/settings.php?page=wordpress-zero-spam-network-stats' );
			await expect( page.locator( '#wpbody-content h1' ).first() ).toContainText( /Zero Spam/ );
			await gallery.capture( page, 'network stats' );
		} );

		await test.step( 'super admin: network dashboard widget', async () => {
			await page.goto( 'wp-admin/network/index.php' );
			await expect( page.locator( '#zerospam_unified_widget' ).getByRole( 'heading', { name: 'Zero Spam Network Overview' } ) ).toBeVisible();
		} );
	} );
} );
