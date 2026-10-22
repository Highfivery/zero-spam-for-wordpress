import { expect, test } from '../support/fixtures';
import { SettingsPage } from '../support/pages/settings-page';
import { SETTINGS_SUBVIEWS } from '../support/screens';

/** Sections that only appear when the matching third-party plugin is active. */
const THIRD_PARTY_SUBVIEWS = [ 'contactform7', 'wpforms', 'formidable', 'fluent_form', 'mailchimp4wp', 'woocommerce', 'gravityforms', 'wp-user-avatar', 'givewp', 'elementor' ];
const NO_FORM = [ 'documentation', 'export', 'errors' ];

/** One representative text or number field per subview, and the module option it is stored in. */
const REPRESENTATIVE: Record< string, { key: string; module: string; value: string } > = {
	settings: { key: 'blocked_message', module: 'settings', value: 'E2E: you have been blocked.' },
	comments: { key: 'comment_spam_message', module: 'comments', value: 'E2E: comment flagged.' },
	registration: { key: 'registration_spam_message', module: 'registration', value: 'E2E: registration flagged.' },
	login: { key: 'login_spam_message', module: 'login', value: 'E2E: login flagged.' },
	davidwalsh: { key: 'davidwalsh_form_selectors', module: 'davidwalsh', value: '.e2e-form' },
	zerospam: { key: 'zerospam_timeout', module: 'zerospam', value: '7' },
	stop_forum_spam: { key: 'stop_forum_spam_timeout', module: 'stop_forum_spam', value: '7' },
	project_honeypot: { key: 'project_honeypot_cache', module: 'project_honeypot', value: '9' },
	ipstack: { key: 'ipstack_timeout', module: 'ipstack', value: '7' },
	ipinfo: { key: 'ipinfo_cache', module: 'ipinfo', value: '9' },
	ipbase: { key: 'ipbase_api_timeout', module: 'ipbase', value: '7' },
	google: { key: 'google_api', module: 'google', value: 'e2e-google-key' },
	debug: { key: 'debug_ip', module: 'debug', value: '203.0.113.99' },
	'api-monitoring': { key: 'api_retention', module: 'api-monitoring', value: '45' },
	security: { key: 'disable_rss_feed', module: 'security', value: 'enabled' },
};

test.describe( 'Settings', () => {
	// These walk every settings subview.
	test.describe.configure( { timeout: 150_000 } );

	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'every settings subview shows its form and a "Save Settings" button', async ( { page, gallery } ) => {
		const settings = new SettingsPage( page );
		await settings.goto( 'settings' );
		const menu = await settings.menuSubviews();

		await test.step( 'the menu lists every known subview (new modules must be added to screens.ts)', async () => {
			expect( menu ).toEqual( expect.arrayContaining( [ ...SETTINGS_SUBVIEWS ] ) );
			const unknown = menu.filter( ( s ) => ! ( SETTINGS_SUBVIEWS as readonly string[] ).includes( s ) && ! THIRD_PARTY_SUBVIEWS.includes( s ) );
			expect( unknown, 'subviews missing from tests/e2e/support/screens.ts' ).toEqual( [] );
		} );

		for ( const subview of menu.filter( ( s ) => ! NO_FORM.includes( s ) ) ) {
			await test.step( `${ subview } has a form and a Save Settings button`, async () => {
				await settings.goto( subview );
				await expect( settings.form ).toBeVisible();
				await expect( settings.saveButton ).toBeVisible();
				await expect( page.locator( '.zerospam-dashboard__menu-link--active' ) ).toHaveCount( 1 );
			} );
		}
		await settings.goto( 'comments' );
		await gallery.capture( page, 'comments settings' );
	} );

	test( 'saving a setting on each subview shows "Settings saved." and the value persists after reload', async ( { page, zs } ) => {
		const settings = new SettingsPage( page );
		for ( const [ subview, field ] of Object.entries( REPRESENTATIVE ) ) {
			await test.step( `${ subview }: ${ field.key }`, async () => {
				await settings.goto( subview );
				if ( 'enabled' === field.value ) {
					await settings.toggle( field.key, 'enabled', true );
				} else {
					await settings.field( field.key ).fill( field.value );
				}
				await settings.save();

				await page.reload();
				if ( 'enabled' === field.value ) {
					await expect( settings.field( field.key, 'enabled' ) ).toBeChecked();
				} else {
					await expect( settings.field( field.key ) ).toHaveValue( field.value );
				}
				const options = await zs.options( `zero-spam-${ field.module }` );
				const stored = ( options[ `zero-spam-${ field.module }` ] || {} ) as Record< string, unknown >;
				expect( String( stored[ field.key ] ) ).toBe( field.value );
			} );
		}
	} );

	test( 'unchecking a protection stores "off" instead of falling back to the default', async ( { page, zs, gallery } ) => {
		const settings = new SettingsPage( page );
		await settings.goto( 'comments' );
		await expect( settings.field( 'verify_comments', 'enabled' ) ).toBeChecked();
		await settings.toggle( 'verify_comments', 'enabled', false );
		await settings.save();
		await gallery.capture( page, 'settings saved' );

		await page.reload();
		await expect( settings.field( 'verify_comments', 'enabled' ) ).not.toBeChecked();
		const stored = ( await zs.options( 'zero-spam-comments' ) )[ 'zero-spam-comments' ] as Record< string, unknown >;
		expect( stored ).toHaveProperty( 'verify_comments' );
		expect( stored.verify_comments ).toBeFalsy();
	} );

	test( 'exported settings can be imported back with a success message', async ( { page, zs, gallery } ) => {
		const settings = new SettingsPage( page );
		await settings.goto( 'export' );
		const exported = JSON.parse( await page.locator( 'textarea[readonly]' ).first().inputValue() );
		expect( exported ).toHaveProperty( 'comments' );

		await test.step( 'import a changed copy', async () => {
			exported.comments.comment_spam_message = 'E2E: imported message.';
			await page.locator( 'textarea[name="settings"]' ).fill( JSON.stringify( exported ) );
			await Promise.all( [ page.waitForURL( /zerospam-success=1/ ), page.getByRole( 'button', { name: /Import Settings/ } ).click() ] );
			await expect( page.getByText( 'The settings JSON has been successfully imported.' ) ).toBeVisible();
			await gallery.capture( page, 'import succeeded' );
			const stored = ( await zs.options( 'zero-spam-comments' ) )[ 'zero-spam-comments' ] as Record< string, unknown >;
			expect( stored.comment_spam_message ).toBe( 'E2E: imported message.' );
		} );

		await test.step( 'invalid JSON is rejected with an error', async () => {
			await page.locator( 'textarea[name="settings"]' ).fill( '{ not json' );
			await Promise.all( [ page.waitForURL( /zerospam-error=1/ ), page.getByRole( 'button', { name: /Import Settings/ } ).click() ] );
			await expect( page.getByText( 'There was a problem importing the settings JSON. Please try again.' ) ).toBeVisible();
		} );
	} );
} );
