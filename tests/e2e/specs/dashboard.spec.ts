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

	test( 'the log details modal covers the rows behind it, the admin bar and the menu', async ( { page, zs } ) => {
		await zs.reset( 'baseline' );
		await zs.seedLog( 5, { spacingMinutes: 30 } );
		const dashboard = new DashboardPage( page );

		await dashboard.goto( 'log' );
		await expect( dashboard.rows ).toHaveCount( 5 );

		await page.locator( '.zerospam-details-trigger' ).first().click();
		const modal = page.locator( '.zerospam-modal.is-active' );
		await expect( modal ).toHaveCSS( 'opacity', '1' );

		// Row checkboxes are position:relative; z-index:1 (core list-tables.css),
		// so a modal with a lower stacking order lets them paint and take clicks
		// on top of it. Every point checked must hit the modal or its overlay.
		const targets = [
			...( await Promise.all( [ 1, 2, 3, 4 ].map( ( i ) => dashboard.rows.nth( i ).locator( '.check-column input' ).boundingBox() ) ) ),
			await page.locator( 'tfoot .check-column input' ).boundingBox(),
			await page.locator( '#wpadminbar' ).boundingBox(),
			await page.locator( '#adminmenu' ).boundingBox(),
		];
		const viewport = page.viewportSize() ?? { width: 1280, height: 720 };
		const points = targets
			.filter( ( box ): box is NonNullable< typeof box > => null !== box )
			.map( ( box ) => ( { x: box.x + Math.min( box.width, 20 ) / 2, y: box.y + Math.min( box.height, 20 ) / 2 } ) )
			.filter( ( p ) => p.x >= 0 && p.y >= 0 && p.x < viewport.width && p.y < viewport.height );
		expect( points.length ).toBeGreaterThanOrEqual( 4 );

		for ( const point of points ) {
			const hit = await page.evaluate( ( { x, y } ) => {
				const el = document.elementFromPoint( x, y );
				return {
					tag: el?.tagName ?? '',
					covered: !! el?.closest( '.zerospam-modal.is-active, .zerospam-modal-overlay' ),
				};
			}, point );
			expect( hit, `point ${ point.x },${ point.y } hits ${ hit.tag }` ).toEqual( { tag: expect.not.stringMatching( /^INPUT$/ ), covered: true } );
		}

		// A click on a row checkbox behind the modal must not toggle that row.
		const behind = dashboard.rows.nth( 2 ).locator( '.check-column input' );
		const box = await behind.boundingBox();
		if ( box ) {
			await page.mouse.click( box.x + box.width / 2, box.y + box.height / 2 );
		}
		await expect( behind ).not.toBeChecked();
	} );
} );
