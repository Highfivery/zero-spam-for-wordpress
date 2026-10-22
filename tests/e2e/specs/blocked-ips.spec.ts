import { expect, test, visitorIp } from '../support/fixtures';
import { BlockIpModal, DashboardPage } from '../support/pages/dashboard-page';

test.describe( 'Blocked IPs', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'an admin blocks an IP in the modal, that visitor gets the blocked page (403), others do not, and deleting the block restores access', async ( { page, visitor, newRoleContext, gallery }, testInfo ) => {
		const dashboard = new DashboardPage( page );
		const modal = new BlockIpModal( page );

		await test.step( 'the visitor can browse the site', async () => {
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 200 );
		} );

		await test.step( 'invalid input is explained', async () => {
			await dashboard.goto( 'blocked-ips' );
			await modal.openFromToolbar();
			await modal.fill( { ip: 'not-an-ip', type: 'permanent', reason: 'E2E' } );
			await modal.submit();
			await expect( dashboard.notice ).toContainText( 'Invalid IP address.' );

			await modal.openFromToolbar();
			await modal.fill( { ip: visitor.ip, type: 'temporary', reason: 'E2E', end: '' } );
			await modal.submit();
			await expect( dashboard.notice ).toContainText( 'Missing block end date.' );
		} );

		await test.step( 'block the visitor permanently', async () => {
			await modal.openFromToolbar();
			await modal.fill( { ip: visitor.ip, type: 'permanent', reason: 'E2E: blocked by the admin' } );
			await gallery.capture( modal.root, 'block IP modal' );
			await modal.submit();
			await expect( dashboard.notice ).toContainText( 'Record has been successfully added/updated.' );
			await expect( dashboard.rows.filter( { hasText: visitor.ip } ) ).toHaveCount( 1 );
			await gallery.capture( page, 'blocked IPs list' );
		} );

		await test.step( 'the blocked visitor sees the blocked page', async () => {
			const response = await visitor.page.goto( '' );
			expect( response?.status() ).toBe( 403 );
			await expect( visitor.page ).toHaveTitle( /Blocked/ );
			await gallery.capture( visitor.page, 'blocked visitor' );
		} );

		await test.step( 'another visitor is not affected', async () => {
			const other = await newRoleContext( null, { ip: visitorIp( testInfo, 'other' ) } );
			expect( ( await ( await other.newPage() ).goto( '' ) )?.status() ).toBe( 200 );
		} );

		await test.step( 'deleting the block lets the visitor back in', async () => {
			await dashboard.goto( 'blocked-ips' );
			const row = dashboard.rows.filter( { hasText: visitor.ip } );
			await Promise.all( [ page.waitForURL( /zerospam-msg/ ), row.getByRole( 'link', { name: /delete/i } ).click() ] );
			await expect( dashboard.notice ).toContainText( 'The record has been successfully deleted.' );
			expect( ( await visitor.page.goto( '' ) )?.status() ).toBe( 200 );
		} );
	} );
} );
