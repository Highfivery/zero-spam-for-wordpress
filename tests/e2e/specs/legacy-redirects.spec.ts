/**
 * The 5.x admin addresses redirect to the 6.0 pages (Admin\Legacy_Redirects,
 * build plan §3). Every row of the map and its arguments are covered by
 * PHPUnit (test-legacy-redirects.php); this checks, in a real browser, a
 * row of each kind, the fragment of a 5.x settings link, the network
 * redirects and that a user who may not open a page still gets
 * WordPress's permission error.
 */
import { expect, test } from '../support/fixtures';
import { subsiteURL } from '../support/targets';

/** Path and query of the page the browser ended on, below wp-admin/. */
const landed = ( url: string ) => {
	const u = new URL( url );
	return u.pathname.replace( /^.*\/wp-admin\//, '' ) + u.search;
};

test.describe( 'Redirects from the 5.x screens', () => {
	test.beforeEach( async ( { zs, target } ) => {
		await zs.reset( 'baseline', { network: target.multisite } );
	} );

	const SITE: Array< [ string, string ] > = [
		[ 'index.php?page=wordpress-zero-spam-dashboard', 'admin.php?page=zero-spam-activity&tab=reports' ],
		[ 'index.php?page=wordpress-zero-spam-dashboard&subview=log&type=comment&s=198.51.100.7&orderby=user_ip&order=asc&zerospam-msg=x', 'admin.php?page=zero-spam-activity&type=comment&ip=198.51.100.7&sort=ip%3Aasc' ],
		[ 'index.php?page=wordpress-zero-spam-dashboard&subview=blocked-locations', 'admin.php?page=zero-spam-rules&tab=locations' ],
		[ 'options-general.php?page=wordpress-zero-spam-settings&subview=comments', 'admin.php?page=zero-spam-protection&tab=detection&card=comments' ],
		[ 'options-general.php?page=wordpress-zero-spam-settings&subview=documentation', 'admin.php?page=zero-spam-help' ],
		[ 'admin.php?page=wordpress-zero-spam-settings&subview=api-monitoring', 'admin.php?page=zero-spam-settings&tab=api' ],
	];

	for ( const [ from, to ] of SITE ) {
		test( `${ from } goes to ${ to }`, async ( { page } ) => {
			await page.goto( `wp-admin/${ from }` );
			expect( landed( page.url() ) ).toBe( to );
			await expect( page.locator( '#zerospam-app' ) ).toBeVisible();
		} );
	}

	test( 'a 5.x settings link to a setting opens that setting (its #fragment survives the redirect)', async ( { page, gallery } ) => {
		await page.goto( 'wp-admin/options-general.php?page=wordpress-zero-spam-settings&subview=settings#trusted_proxies' );
		await expect( page.getByRole( 'tab', { selected: true } ) ).toContainText( 'Advanced' );
		await expect( page.locator( '#zs-card-proxies' ) ).toBeVisible();
		await gallery.capture( page, 'trusted proxies from a 5.x link' );
	} );

	test( 'an editor still gets a permission error, not a redirect', async ( { asEditor } ) => {
		const response = await asEditor.goto( 'wp-admin/options-general.php?page=wordpress-zero-spam-settings' );
		expect( landed( asEditor.url() ) ).toContain( 'page=wordpress-zero-spam-settings' );
		expect( response?.status() ).toBe( 403 );
	} );

	test( 'the network screens go to the network page', { tag: '@multisite' }, async ( { page } ) => {
		await page.goto( 'wp-admin/network/settings.php?page=zerospam-network-settings&tab=import-export' );
		expect( landed( page.url() ) ).toBe( 'network/admin.php?page=zero-spam-network&tab=io' );
		await page.goto( 'wp-admin/network/settings.php?page=wordpress-zero-spam-network-stats&period=week&start_date=2026-01-01' );
		expect( landed( page.url() ) ).toBe( 'network/admin.php?page=zero-spam-network&tab=stats&period=7' );
		await expect( page.locator( '#zerospam-app' ) ).toBeVisible();
	} );

	test( 'a site administrator opening a network screen gets a permission error', { tag: '@multisite' }, async ( { asSiteAdmin, target } ) => {
		const network = await asSiteAdmin.goto( subsiteURL( target, 'wp-admin/network/settings.php?page=zerospam-network-settings' ) );
		expect( landed( asSiteAdmin.url() ) ).not.toContain( 'zero-spam-network' );
		expect( network?.status() ).toBe( 403 );
		await expect( asSiteAdmin.getByText( /not allowed to access this page/ ) ).toBeVisible();

		const site = await asSiteAdmin.goto( subsiteURL( target, 'wp-admin/options-general.php?page=zerospam-network-settings' ) );
		expect( site?.status() ).toBe( 403 );
		await expect( asSiteAdmin.getByText( /not allowed to access this page/ ) ).toBeVisible();
	} );
} );
