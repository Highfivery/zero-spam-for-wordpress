import { expect, test } from '../support/fixtures';
import { screensFor } from '../support/screens';

const LOG_SCREEN = 'wp-admin/index.php?page=wordpress-zero-spam-dashboard&subview=log';

test.describe( 'Smoke', () => {
	test.beforeEach( async ( { zs, target } ) => {
		await zs.reset( 'baseline', { network: target.multisite } );
		await zs.seedLog( 30 );
	} );

	test( 'every Zero Spam admin screen loads without PHP notices', async ( { page, target, zs } ) => {
		// About 30 screens in one test.
		test.setTimeout( 180_000 );
		// Screens that need a set-up (a role, data, a protection level) are covered by a11y.spec and visual.spec.
		const screens = screensFor( target ).filter( ( s ) => 'front' !== s.area && ! s.as && ! s.before && ! s.level );
		const problems: string[] = [];

		for ( const screen of screens ) {
			await test.step( `open ${ screen.title }`, async () => {
				const response = await page.goto( screen.path( target ) );
				expect.soft( response?.status(), `${ screen.title } answers 200` ).toBe( 200 );
				await expect.soft( page.locator( '#wpbody-content' ), `${ screen.title } renders` ).toBeVisible();
				await expect.soft( page.getByText( 'Sorry, you are not allowed to access this page.' ) ).toHaveCount( 0 );
				if ( screen.prepare ) {
					await screen.prepare( page );
				}
			} );
		}

		for ( const e of await zs.errors() ) {
			problems.push( `${ e.kind }: ${ e.message } in ${ e.file }:${ e.line } (${ e.uri })` );
		}
		expect( problems ).toEqual( [] );
	} );

	test( 'the unfiltered Log screen loads without a wpdb::prepare() notice', async ( { page, zs } ) => {
		await page.goto( LOG_SCREEN );
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
		const errors = await zs.errors();
		expect( errors.map( ( e ) => `${ e.kind }: ${ e.message } in ${ e.file }` ) ).toEqual( [] );
	} );
} );
