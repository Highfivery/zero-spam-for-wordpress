import type { Page } from '@playwright/test';

import { expect, test } from '../support/fixtures';
import { DashboardWidget } from '../support/pages/network-pages';

/**
 * Clicks Refresh and waits for the AJAX answer.
 *
 * @param page   Page.
 * @param widget Widget.
 */
async function refresh( page: Page, widget: DashboardWidget ): Promise< void > {
	const response = page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() || '' ).includes( 'zerospam_refresh_dashboard' ) );
	await widget.refresh.click();
	expect( ( await ( await response ).json() ).success ).toBe( true );
}

test.describe( 'Dashboard widget', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
		await zs.seedLog( 5 );
	} );

	test( 'an administrator sees "Zero Spam Overview", refreshes it and sees updated numbers', async ( { page, zs, gallery } ) => {
		const widget = new DashboardWidget( page );
		await page.goto( 'wp-admin/index.php' );
		await expect( widget.root.getByRole( 'heading', { name: 'Zero Spam Overview' } ) ).toBeVisible();
		const total = widget.root.locator( '[data-stat="total_blocked"]' );
		await expect( total ).toHaveText( '5' );
		await gallery.capture( widget.root, 'widget before refresh' );

		await test.step( 'new detections arrive; Refresh shows them without reloading', async () => {
			await zs.seedLog( 7, { keepCache: true, ipPrefix: '198.51.100.' } );
			await page.reload();
			await expect( total, 'the widget still shows its cached numbers' ).toHaveText( '5' );
			await refresh( page, widget );
			await expect( total ).toHaveText( '12' );
			await gallery.capture( widget.root, 'widget after refresh' );
		} );
	} );

	test( 'the refresh result is announced to screen readers', async ( { page } ) => {
		test.fail( true, 'AGENTS.md §8: the widget refresh only swaps an icon; the "Data refreshed." string is passed to the script but never put in a live region (WCAG 4.1.3)' );
		const widget = new DashboardWidget( page );
		await page.goto( 'wp-admin/index.php' );
		await refresh( page, widget );
		const live = widget.root.locator( '[aria-live], [role="status"], [role="alert"]' );
		await expect( live.filter( { hasText: /refreshed/i } ) ).toHaveCount( 1, { timeout: 3000 } );
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
