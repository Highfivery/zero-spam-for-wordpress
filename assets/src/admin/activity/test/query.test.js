/**
 * The Activity log's view as REST paths and addresses: filters, sorting,
 * paging, facet counts, the card/table switch and Previous/Next.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	LIST_FIELDS,
	TABLE_QUERY,
	allCountriesCount,
	allTypesCount,
	ariaSort,
	emptyFilters,
	facetOptions,
	facetsPath,
	filterArgs,
	isFiltered,
	keepSelected,
	listMode,
	listPath,
	navState,
	pageList,
	paging,
	readView,
	sincePath,
	toggleSort,
	withView,
} from '../query';

/**
 * A path's query as sorted pairs (as the preloading middleware compares them).
 *
 * @param {string} path Path.
 * @return {string[]} Pairs.
 */
const pairs = ( path ) =>
	path
		.split( '?' )[ 1 ]
		.split( '&' )
		.map( ( p ) => decodeURIComponent( p ) )
		.sort();

describe( 'listPath', () => {
	it( 'is the preloaded path for the default view (Admin_App::activity_preloads)', () => {
		const path = listPath( {
			filters: emptyFilters(),
			sort: 'date:desc',
			page: 1,
		} );
		expect( path.split( '?' )[ 0 ] ).toBe( '/zero-spam/v1/activity' );
		expect( pairs( path ) ).toEqual(
			pairs(
				`/zero-spam/v1/activity?per_page=25&_fields=${ LIST_FIELDS }`
			)
		);
	} );

	it( 'maps every filter, the sort and the page', () => {
		const path = listPath( {
			filters: {
				search: ' 203.0.113 ',
				type: 'comment',
				country: 'DE',
				after: '2026-10-01',
				before: '2026-10-20',
				network: true,
			},
			sort: 'region:desc',
			page: 3,
		} );
		expect( pairs( path ) ).toEqual(
			[
				'per_page=25',
				`_fields=${ LIST_FIELDS }`,
				'page=3',
				'orderby=region_name',
				'order=desc',
				'search=203.0.113',
				'type[]=comment',
				'country[]=DE',
				'after=2026-10-01',
				'before=2026-10-20',
				'network_only=1',
			].sort()
		);
	} );

	it( 'encodes the search text', () => {
		expect(
			listPath( {
				filters: { ...emptyFilters(), search: 'a&b=c' },
				sort: 'date:desc',
				page: 1,
			} )
		).toContain( 'search=a%26b%3Dc' );
	} );

	it( 'sorts locations by name', () => {
		expect(
			pairs(
				listPath( {
					filters: emptyFilters(),
					sort: 'country:asc',
					page: 1,
				} )
			)
		).toContain( 'orderby=country_name' );
	} );

	it( 'leaves out dates that are not YYYY-MM-DD', () => {
		expect(
			filterArgs( {
				...emptyFilters(),
				after: 'yesterday',
				before: '2026-1-2',
			} )
		).toEqual( [] );
	} );
} );

describe( 'facets and live feed paths', () => {
	it( 'facets carry the filters, not the page or sort', () => {
		expect( facetsPath( emptyFilters() ) ).toBe(
			'/zero-spam/v1/activity?facets=1'
		);
		expect(
			pairs(
				facetsPath( {
					...emptyFilters(),
					type: 'login',
					network: true,
				} )
			)
		).toEqual( [ 'facets=1', 'network_only=1', 'type[]=login' ] );
	} );

	it( 'the live feed asks for IDs only, newer than the newest shown', () => {
		expect(
			pairs( sincePath( { ...emptyFilters(), country: 'US' }, 42 ) )
		).toEqual( [
			'_fields=id',
			'country[]=US',
			'per_page=20',
			'since_id=42',
		] );
	} );
} );

describe( 'the address', () => {
	it( 'reads the view, including the 5.x log arguments the redirects map', () => {
		const view = readView(
			'?page=zero-spam-activity&type=comment&country=de&ip=203.0.113.5&after=2026-10-01&before=2026-10-20&network=1&sort=ip:asc&paged=4&item=17'
		);
		expect( view ).toEqual( {
			filters: {
				search: '203.0.113.5',
				type: 'comment',
				country: 'DE',
				after: '2026-10-01',
				before: '2026-10-20',
				network: true,
			},
			sort: 'ip:asc',
			page: 4,
			item: 17,
		} );
	} );

	it( 'ignores what it does not know', () => {
		expect(
			readView(
				'?type=<b>&country=USA&after=soon&sort=nope&paged=-2&item=x'
			)
		).toEqual( {
			filters: emptyFilters(),
			sort: 'date:desc',
			page: 1,
			item: 0,
		} );
	} );

	it( 'writes the view and keeps the page and tab arguments', () => {
		const href =
			'https://example.org/wp-admin/admin.php?page=zero-spam-activity&tab=log&paged=2';
		const out = new URL(
			withView( href, {
				filters: { ...emptyFilters(), type: 'login' },
				sort: 'date:desc',
				page: 1,
				item: 9,
			} )
		);
		expect( out.searchParams.get( 'page' ) ).toBe( 'zero-spam-activity' );
		expect( out.searchParams.get( 'tab' ) ).toBe( 'log' );
		expect( out.searchParams.get( 'type' ) ).toBe( 'login' );
		expect( out.searchParams.get( 'item' ) ).toBe( '9' );
		expect( out.searchParams.has( 'paged' ) ).toBe( false );
		expect( out.searchParams.has( 'sort' ) ).toBe( false );
	} );

	it( 'round-trips', () => {
		const view = {
			filters: {
				search: '198.51.100.1',
				type: 'login',
				country: 'US',
				after: '2026-10-01',
				before: '',
				network: false,
			},
			sort: 'country:desc',
			page: 2,
			item: 0,
		};
		const url = new URL(
			withView(
				'https://example.org/wp-admin/admin.php?page=zero-spam-activity',
				view
			)
		);
		expect( readView( url.search ) ).toEqual( view );
	} );
} );

describe( 'filters', () => {
	it( 'knows when anything is filtered', () => {
		expect( isFiltered( emptyFilters() ) ).toBe( false );
		expect( isFiltered( { ...emptyFilters(), search: '   ' } ) ).toBe(
			false
		);
		expect( isFiltered( { ...emptyFilters(), after: '2026-10-01' } ) ).toBe(
			true
		);
		expect( isFiltered( { ...emptyFilters(), network: true } ) ).toBe(
			true
		);
	} );
} );

describe( 'facet counts', () => {
	const facets = {
		total: 7,
		type: [
			{ value: 'comment', label: 'Comment', count: 5 },
			{ value: 'login', label: 'Login', count: 3 },
		],
		country: [ { value: 'DE', label: 'Germany', count: 4 } ],
	};

	it( '"All types" counts every entry the other filters match', () => {
		expect( allTypesCount( facets ) ).toBe( 8 );
		expect( allTypesCount( undefined ) ).toBeNull();
	} );

	it( '"All countries" shows a count only when it is exact', () => {
		expect( allCountriesCount( facets, emptyFilters() ) ).toBe( 7 );
		expect(
			allCountriesCount( facets, { ...emptyFilters(), country: 'DE' } )
		).toBeNull();
	} );

	it( 'keeps the chosen option listed when it has no entries left', () => {
		expect( facetOptions( facets.type, 'registration' ) ).toEqual( [
			{ value: 'comment', label: 'Comment', count: 5 },
			{ value: 'login', label: 'Login', count: 3 },
			{ value: 'registration', label: 'registration', count: 0 },
		] );
		expect( facetOptions( undefined, '' ) ).toEqual( [] );
	} );
} );

describe( 'table or cards', () => {
	it( 'is a table from 1,200px and cards below', () => {
		expect( listMode( 1280 ) ).toBe( 'table' );
		expect( listMode( 1200 ) ).toBe( 'table' );
		expect( listMode( 1199 ) ).toBe( 'cards' );
		expect( listMode( 1024 ) ).toBe( 'cards' );
		expect( listMode( 375 ) ).toBe( 'cards' );
		expect( TABLE_QUERY ).toBe( '(min-width: 1200px)' );
	} );
} );

describe( 'paging', () => {
	it( 'says which rows are shown', () => {
		expect( paging( 120, 2 ) ).toEqual( {
			from: 26,
			to: 50,
			pages: 5,
			page: 2,
		} );
		expect( paging( 120, 9 ) ).toEqual( {
			from: 101,
			to: 120,
			pages: 5,
			page: 5,
		} );
		expect( paging( 0, 1 ) ).toEqual( {
			from: 0,
			to: 0,
			pages: 1,
			page: 1,
		} );
	} );

	it( 'lists the first, last and neighbouring pages with gaps', () => {
		expect( pageList( 1, 3 ) ).toEqual( [ 1, 2, 3 ] );
		expect( pageList( 6, 20 ) ).toEqual( [ 1, null, 5, 6, 7, null, 20 ] );
		expect( pageList( 2, 20 ) ).toEqual( [ 1, 2, 3, null, 20 ] );
	} );
} );

describe( 'sorting', () => {
	it( 'toggles a column, dates newest first', () => {
		expect( toggleSort( 'date:desc', 'date' ) ).toBe( 'date:asc' );
		expect( toggleSort( 'date:desc', 'ip' ) ).toBe( 'ip:asc' );
		expect( toggleSort( 'ip:asc', 'ip' ) ).toBe( 'ip:desc' );
		expect( toggleSort( 'ip:asc', 'date' ) ).toBe( 'date:desc' );
	} );

	it( 'sets aria-sort on the header that sorts', () => {
		expect( ariaSort( 'date:desc', [ 'date' ] ) ).toBe( 'descending' );
		expect( ariaSort( 'region:asc', [ 'country', 'region' ] ) ).toBe(
			'ascending'
		);
		expect( ariaSort( 'type:asc', [ 'ip' ] ) ).toBe( 'none' );
	} );
} );

describe( 'the drawer', () => {
	it( 'moves through the rows shown', () => {
		expect( navState( [ 5, 4, 3 ], 5 ) ).toEqual( {
			index: 0,
			total: 3,
			prev: null,
			next: 4,
		} );
		expect( navState( [ 5, 4, 3 ], 4 ) ).toEqual( {
			index: 1,
			total: 3,
			prev: 5,
			next: 3,
		} );
		expect( navState( [ 5, 4, 3 ], 3 ) ).toEqual( {
			index: 2,
			total: 3,
			prev: 4,
			next: null,
		} );
	} );

	it( 'has no neighbours for an entry not on the page (a link from the Overview)', () => {
		expect( navState( [ 5, 4 ], 99 ) ).toEqual( {
			index: -1,
			total: 2,
			prev: null,
			next: null,
		} );
	} );

	it( 'keeps only selected entries still shown', () => {
		expect( [
			...keepSelected( new Set( [ 1, 2, 3 ] ), [ 2, 3, 4 ] ),
		] ).toEqual( [ 2, 3 ] );
	} );
} );
