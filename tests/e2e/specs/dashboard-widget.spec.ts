import type { Page } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { DashboardWidget } from '../support/pages/network-pages';

/**
 * Clicks Refresh and waits for the REST answers (stats/timeseries and
 * activity; the 5.x admin-ajax.php action is deprecated and no longer used).
 *
 * @param page   Page.
 * @param widget Widget.
 */
async function refresh( page: Page, widget: DashboardWidget ): Promise< void > {
	const isRoute = ( route: string ) => ( r: { url(): string } ) => decodeURIComponent( r.url() ).includes( `zero-spam/v1/${ route }` );
	const answers = Promise.all( [ page.waitForResponse( isRoute( 'stats/timeseries' ) ), page.waitForResponse( isRoute( 'activity' ) ) ] );
	await widget.refresh.click();
	for ( const response of await answers ) {
		expect( response.status() ).toBe( 200 );
	}
}

test.describe( 'Dashboard widget', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await zs.seedLog( 5 );
	} );

	test( 'an administrator sees "Zero Spam overview", refreshes it and sees updated numbers', async ( { page, zs, gallery } ) => {
		const widget = new DashboardWidget( page );
		const ajax: string[] = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'admin-ajax.php' ) && ( request.postData() || '' ).includes( 'zerospam_refresh_dashboard' ) ) {
				ajax.push( request.url() );
			}
		} );

		await page.goto( 'wp-admin/index.php' );
		await expect( widget.root.getByRole( 'heading', { name: 'Zero Spam overview' } ) ).toBeVisible();
		await expect( widget.total ).toHaveText( '5' );
		await expect( widget.root.locator( 'canvas' ), 'no Chart.js canvas' ).toHaveCount( 0 );
		await gallery.capture( widget.root, 'widget before refresh' );

		await test.step( 'new detections arrive; Refresh shows them without reloading', async () => {
			await zs.seedLog( 7, { keepCache: true, ipPrefix: '198.51.100.' } );
			await expect( widget.total, 'the page still shows what it rendered' ).toHaveText( '5' );
			await refresh( page, widget );
			await expect( widget.total ).toHaveText( '12' );
			await expect( widget.root.locator( '[data-zs-field="latest"] li' ) ).toHaveCount( 3 );
			await gallery.capture( widget.root, 'widget after refresh' );
		} );

		expect( ajax, 'the deprecated AJAX refresh is not used' ).toEqual( [] );
	} );

	test( 'the refresh result is announced to screen readers', async ( { page } ) => {
		// AGENTS.md §8 (fixed in 6.0): 5.x only swapped an icon; "Data refreshed." now reaches a polite live region.
		const widget = new DashboardWidget( page );
		await page.goto( 'wp-admin/index.php' );
		await refresh( page, widget );
		const live = widget.root.locator( '[aria-live], [role="status"], [role="alert"]' );
		await expect( live.filter( { hasText: /refreshed/i } ) ).toHaveCount( 1, { timeout: 3000 } );
		await expect( widget.status ).toHaveText( 'Data refreshed.' );
	} );

	test( 'a failed refresh is announced and the numbers stay', async ( { page } ) => {
		const widget = new DashboardWidget( page );
		await page.goto( 'wp-admin/index.php' );
		await page.route( /zero-spam(\/|%2F)v1(\/|%2F)stats(\/|%2F)timeseries/, ( route ) => route.fulfill( { status: 500, contentType: 'application/json', body: '{"code":"e2e"}' } ) );
		await widget.refresh.click();
		await expect( widget.status ).toHaveText( /Couldn’t refresh the data/ );
		await expect( widget.total ).toHaveText( '5' );
	} );

	test( 'widget visibility follows the roles setting', async ( { page, asEditor, zs, target } ) => {
		const adminWidget = new DashboardWidget( page );
		const editorWidget = new DashboardWidget( asEditor );

		await test.step( 'by default only administrators see it', async () => {
			await asEditor.goto( 'wp-admin/index.php' );
			await expect( editorWidget.root ).toHaveCount( 0 );
		} );

		await test.step( 'allowed editors see it without the Refresh button', async () => {
			await zs.set( { widget_visibility: [ 'administrator', 'editor' ] } );
			await asEditor.goto( 'wp-admin/index.php' );
			await expect( editorWidget.root ).toBeVisible();
			await expect( editorWidget.refresh ).toHaveCount( 0 );
		} );

		await test.step( 'clearing every role hides it (super admins on a network still see it)', async () => {
			await zs.set( { widget_visibility: [] } );
			await page.goto( 'wp-admin/index.php' );
			await expect( adminWidget.root ).toHaveCount( target.multisite ? 1 : 0 );
			await asEditor.goto( 'wp-admin/index.php' );
			await expect( editorWidget.root ).toHaveCount( 0 );
		} );

		await test.step( 'disabling the widget hides it for everyone', async () => {
			await zs.set( { widget_visibility: [ 'administrator', 'editor' ], widget_enabled: false } );
			await page.goto( 'wp-admin/index.php' );
			await expect( adminWidget.root ).toHaveCount( 0 );
			await asEditor.goto( 'wp-admin/index.php' );
			await expect( editorWidget.root ).toHaveCount( 0 );
		} );
	} );
} );

test.describe( 'Network dashboard widget', { tag: '@multisite' }, () => {
	test( 'the network overview sums the network and has no Refresh button', async ( { page, zs } ) => {
		await zs.reset( 'baseline' );
		await zs.seedLog( 4 );
		const widget = new DashboardWidget( page );
		await page.goto( 'wp-admin/network/index.php' );
		await expect( widget.root.getByRole( 'heading', { name: 'Zero Spam network overview' } ) ).toBeVisible();
		await expect( widget.total ).toHaveText( '4' );
		await expect( widget.refresh ).toHaveCount( 0 );
		await expect( widget.root.getByRole( 'link', { name: 'View network activity' } ) ).toBeVisible();
	} );
} );
