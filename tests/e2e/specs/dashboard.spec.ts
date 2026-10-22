import { expect, test } from '../support/fixtures';
import { DashboardPage } from '../support/pages/dashboard-page';

test.describe( 'Dashboard', () => {
	test( 'dashboard, log, blocked IPs and blocked locations show clear empty states with no data', async ( { page, zs, gallery } ) => {
		await zs.reset( 'baseline' );
		const dashboard = new DashboardPage( page );

		await test.step( 'reports: every block says there is nothing to report yet', async () => {
			await dashboard.goto( 'reports' );
			await expect( page.getByText( 'Nothing to report.' ).first() ).toBeVisible();
			await expect( page.getByText( 'Nothing to report yet.' ) ).toBeVisible();
			await expect( page.getByText( /There haven't been any detections of malicious or spammy IPs yet/ ) ).toBeVisible();
			await gallery.capture( page, 'empty dashboard' );
		} );

		for ( const subview of [ 'log', 'blocked-ips', 'blocked-locations' ] as const ) {
			await test.step( `${ subview }: the table says no items were found`, async () => {
				await dashboard.goto( subview );
				await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
				await expect( page.locator( 'table.wp-list-table tbody' ) ).toContainText( 'No items found.' );
			} );
		}

		await test.step( 'blocked locations explain why location blocking is off', async () => {
			await expect( page.getByText( 'Blocking locations is currently disabled.' ) ).toBeVisible();
		} );
	} );

	test( 'with seeded detections the dashboard shows charts, map and top IPs, and the log lists them', async ( { page, zs, gallery } ) => {
		await zs.reset( 'baseline' );
		await zs.seedLog( 40, { spacingMinutes: 30 } );
		const dashboard = new DashboardPage( page );

		await test.step( 'reports: charts, heat map and most-detected IPs', async () => {
			await dashboard.goto( 'reports' );
			await expect( page.locator( '#zerospam-bar-chart' ) ).toBeVisible();
			await expect( page.locator( '#zerospam-line-chart' ) ).toBeVisible();
			await expect( page.locator( '#zerospam-pie-countries' ) ).toBeVisible();
			await expect( page.locator( '#world-map svg, #world-map .jvectormap-container' ).first() ).toBeVisible();
			await expect( page.locator( '.zerospam-block--list' ) ).toContainText( '203.0.113.' );
			await expect( page.getByText( 'Nothing to report.' ) ).toHaveCount( 0 );
			await gallery.capture( page, 'dashboard with detections' );
		} );

		await test.step( 'log: the seeded detections are listed', async () => {
			await dashboard.goto( 'log' );
			await expect( dashboard.rows ).toHaveCount( 40 );
			await expect( dashboard.rows.first() ).toContainText( 'Comment' );
		} );
	} );
} );
