import { expect, test } from '../support/fixtures';
import { DashboardPage } from '../support/pages/dashboard-page';

test.describe( 'Log table', () => {
	test.beforeEach( async ( { zs } ) => {
		await zs.reset( 'baseline' );
	} );

	test( 'the log shows 50 rows per page with no gaps or overlaps (120 seeded rows: 50/50/20)', async ( { page, zs } ) => {
		test.fail( true, 'AGENTS.md §8: LogTable LIMIT has count and offset swapped and the offset is per_page * page, so page 2 shows rows 51-120 (70 rows)' );
		await zs.seedLog( 120 );
		const dashboard = new DashboardPage( page );
		const seen = new Set< string >();
		const counts: number[] = [];

		for ( const n of [ 1, 2, 3 ] ) {
			await dashboard.goto( 'log', `&paged=${ n }` );
			const ids = await page.locator( 'table.wp-list-table tbody input[name="ids[]"]' ).evaluateAll( ( inputs ) => inputs.map( ( i ) => ( i as HTMLInputElement ).value ) );
			counts.push( ids.length );
			ids.forEach( ( id ) => seen.add( id ) );
		}

		expect( counts ).toEqual( [ 50, 50, 20 ] );
		expect( seen.size ).toBe( 120 );
	} );

	test( 'searching the log by IP shows the matching count', async ( { page, zs, gallery } ) => {
		test.fail( true, 'AGENTS.md §8: the LogTable total count ignores the active filters, so a search reports every row' );
		await zs.seedLog( 60 );
		await zs.seedLog( 3, { ip: '198.51.100.9' } );
		const dashboard = new DashboardPage( page );
		await dashboard.goto( 'log' );

		await page.locator( '#search-ip-search-input' ).fill( '198.51.100.9' );
		await Promise.all( [ page.waitForLoadState(), page.getByRole( 'button', { name: 'Search IPs' } ).click() ] );
		await expect( dashboard.rows ).toHaveCount( 3 );
		await gallery.capture( page, 'search by IP' );
		await expect( page.locator( '.tablenav.top .displaying-num' ) ).toHaveText( '3 items' );
	} );

	test( 'sorting the log by region orders the rows by region', async ( { page, zs } ) => {
		test.fail( true, 'AGENTS.md §8: LogTable sorts the region column by country' );
		await zs.seedLog( 5 );
		const dashboard = new DashboardPage( page );
		await dashboard.goto( 'log', '&orderby=region&order=asc' );
		const regions = await page.locator( 'table.wp-list-table tbody td.region' ).allInnerTexts();
		expect( regions ).toHaveLength( 5 );
		expect( regions ).toEqual( [ ...regions ].sort() );
	} );
} );
