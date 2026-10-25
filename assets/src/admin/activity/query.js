/**
 * The Activity log's state as REST paths and addresses (pure; unit tested).
 *
 * Filters, sort and page map to `zero-spam/v1/activity` query arguments.
 * The default view's paths are the ones Admin_App::activity_preloads()
 * preloads, so the first load makes no request (the preloading middleware
 * matches query arguments in any order). The address keeps the view
 * (&type=, &country=, &ip=, &days=, &network=1, &paged=, &sort=, &item=),
 * so a link or the back button opens the same list.
 */

/**
 * Internal dependencies
 */
import { NS } from '../data/api';

/** Fields of a log row (Admin_App::ACTIVITY_FIELDS). */
export const LIST_FIELDS =
	'id,date,type,type_label,ip,blocked,network,failed,failed_label,country,country_name,region,region_name,city';

/** Rows per page (Admin_App::ACTIVITY_PER_PAGE). */
export const PER_PAGE = 25;

/** The date range chips, in days (0 = all time). */
export const DATE_RANGES = [ 0, 1, 7, 30 ];

/** The sorts: key => [ orderby, order ]. The first is the default. */
export const SORTS = {
	'date:desc': [ 'date_recorded', 'desc' ],
	'date:asc': [ 'date_recorded', 'asc' ],
	'type:asc': [ 'log_type', 'asc' ],
	'type:desc': [ 'log_type', 'desc' ],
	'ip:asc': [ 'user_ip', 'asc' ],
	'ip:desc': [ 'user_ip', 'desc' ],
	'country:asc': [ 'country', 'asc' ],
	'country:desc': [ 'country', 'desc' ],
	'region:asc': [ 'region', 'asc' ],
	'region:desc': [ 'region', 'desc' ],
};

export const DEFAULT_SORT = 'date:desc';

/** Width from which the log is a table; below it, a list of cards. */
export const TABLE_MIN_WIDTH = 1200;
export const TABLE_QUERY = `(min-width: ${ TABLE_MIN_WIDTH }px)`;

/**
 * Table or cards for a viewport width.
 *
 * @param {number} width Viewport width in CSS pixels.
 * @return {'table'|'cards'} Layout.
 */
export const listMode = ( width ) =>
	Number( width ) >= TABLE_MIN_WIDTH ? 'table' : 'cards';

/** @return {Object} Filters that show everything. */
export const emptyFilters = () => ( {
	search: '',
	type: '',
	country: '',
	days: 0,
	network: false,
} );

/**
 * Whether any filter is set.
 *
 * @param {Object} filters Filters.
 * @return {boolean} Filtered.
 */
export const isFiltered = ( filters ) =>
	Boolean(
		filters.search.trim() ||
		filters.type ||
		filters.country ||
		filters.days ||
		filters.network
	);

/**
 * The filters as query arguments, in a fixed order.
 *
 * @param {Object} filters Filters.
 * @return {Array<[string, string]>} Arguments.
 */
export function filterArgs( filters ) {
	const args = [];
	const search = String( filters.search || '' ).trim();
	if ( search ) {
		args.push( [ 'search', search ] );
	}
	if ( filters.type ) {
		args.push( [ 'type[]', filters.type ] );
	}
	if ( filters.country ) {
		args.push( [ 'country[]', filters.country ] );
	}
	if ( DATE_RANGES.includes( Number( filters.days ) ) && filters.days ) {
		args.push( [ 'days', String( filters.days ) ] );
	}
	if ( filters.network ) {
		args.push( [ 'network_only', '1' ] );
	}
	return args;
}

/**
 * A REST path from arguments (values encoded, brackets kept).
 *
 * @param {string}                  route Route below the namespace.
 * @param {Array<[string, string]>} args  Arguments.
 * @return {string} Path.
 */
export function buildPath( route, args ) {
	const query = args
		.map(
			( [ key, value ] ) => `${ key }=${ encodeURIComponent( value ) }`
		)
		.join( '&' );
	return `${ NS }/${ route }${ query ? `?${ query }` : '' }`;
}

/**
 * The list's path.
 *
 * @param {Object} state         View.
 * @param {Object} state.filters Filters.
 * @param {string} state.sort    Sort key.
 * @param {number} state.page    Page.
 * @return {string} Path.
 */
export function listPath( { filters, sort, page } ) {
	const args = [
		[ 'per_page', String( PER_PAGE ) ],
		[ '_fields', LIST_FIELDS ],
	];
	if ( page > 1 ) {
		args.push( [ 'page', String( page ) ] );
	}
	if ( sort !== DEFAULT_SORT && SORTS[ sort ] ) {
		args.push( [ 'orderby', SORTS[ sort ][ 0 ] ] );
		args.push( [ 'order', SORTS[ sort ][ 1 ] ] );
	}
	return buildPath( 'activity', [ ...args, ...filterArgs( filters ) ] );
}

/**
 * The filter counts' path.
 *
 * @param {Object} filters Filters.
 * @return {string} Path.
 */
export const facetsPath = ( filters ) =>
	buildPath( 'activity', [ [ 'facets', '1' ], ...filterArgs( filters ) ] );

/**
 * The live feed's path: IDs of entries newer than `since` that match the filters.
 *
 * @param {Object} filters Filters.
 * @param {number} since   Highest ID shown.
 * @return {string} Path.
 */
export const sincePath = ( filters, since ) =>
	buildPath( 'activity', [
		[ 'since_id', String( Number( since ) || 0 ) ],
		[ 'per_page', '20' ],
		[ '_fields', 'id' ],
		...filterArgs( filters ),
	] );

/**
 * One entry's path.
 *
 * @param {number} id Entry ID.
 * @return {string} Path.
 */
export const itemPath = ( id ) => `${ NS }/activity/${ Number( id ) }`;

/**
 * Reads the view from an address's query string.
 *
 * @param {string} search location.search.
 * @return {{filters: Object, sort: string, page: number, item: number}} View.
 */
export function readView( search ) {
	const q = new URLSearchParams( search || '' );
	const filters = emptyFilters();
	const type = q.get( 'type' ) || '';
	const country = ( q.get( 'country' ) || '' ).toUpperCase();
	const days = Number( q.get( 'days' ) );
	const sort = q.get( 'sort' ) || DEFAULT_SORT;
	const page = parseInt( q.get( 'paged' ) || '1', 10 );
	const item = parseInt( q.get( 'item' ) || '0', 10 );

	if ( /^[A-Za-z0-9_-]{1,64}$/.test( type ) ) {
		filters.type = type;
	}
	if ( /^[A-Z]{2}$/.test( country ) ) {
		filters.country = country;
	}
	filters.search = ( q.get( 'ip' ) || '' ).slice( 0, 100 );
	filters.days = DATE_RANGES.includes( days ) ? days : 0;
	filters.network = '1' === q.get( 'network' );

	return {
		filters,
		sort: SORTS[ sort ] ? sort : DEFAULT_SORT,
		page: page > 0 ? page : 1,
		item: item > 0 ? item : 0,
	};
}

/**
 * The view as address arguments (empty values are removed).
 *
 * @param {Object} view         View.
 * @param {Object} view.filters Filters.
 * @param {string} view.sort    Sort key.
 * @param {number} view.page    Page.
 * @param {number} view.item    Entry open in the drawer (0: none).
 * @return {Object<string, string>} Arguments.
 */
export function viewArgs( { filters, sort, page, item } ) {
	return {
		type: filters.type,
		country: filters.country,
		ip: filters.search.trim(),
		days: filters.days ? String( filters.days ) : '',
		network: filters.network ? '1' : '',
		sort: sort !== DEFAULT_SORT ? sort : '',
		paged: page > 1 ? String( page ) : '',
		item: item ? String( item ) : '',
	};
}

/**
 * Writes the view into an address, keeping its other arguments.
 *
 * @param {string} href Address.
 * @param {Object} view View.
 * @return {string} Address.
 */
export function withView( href, view ) {
	const url = new URL( href );
	Object.entries( viewArgs( view ) ).forEach( ( [ key, value ] ) => {
		if ( value ) {
			url.searchParams.set( key, value );
		} else {
			url.searchParams.delete( key );
		}
	} );
	return url.toString();
}

/**
 * Options of a filter select with their counts. The selected value stays
 * listed (with 0) when the other filters leave it no entries.
 *
 * @param {Array<{value: string, label: string, count: number}>|undefined} facet    Facet.
 * @param {string}                                                         selected Selected value.
 * @return {Array<{value: string, label: string, count: number}>} Options.
 */
export function facetOptions( facet, selected ) {
	const list = Array.isArray( facet ) ? [ ...facet ] : [];
	if ( selected && ! list.some( ( o ) => o.value === selected ) ) {
		list.push( { value: selected, label: selected, count: 0 } );
	}
	return list.map( ( o ) => ( {
		value: String( o.value ),
		label: String( o.label || o.value ),
		count: Math.max( 0, Number( o.count ) || 0 ),
	} ) );
}

/**
 * The count shown on "All types": every entry the other filters match
 * (the type facet ignores the type filter, and every entry has a type).
 *
 * @param {Object|undefined} facets Facets.
 * @return {number|null} Count, or null while loading.
 */
export function allTypesCount( facets ) {
	if ( ! facets || ! Array.isArray( facets.type ) ) {
		return null;
	}
	return facets.type.reduce(
		( sum, t ) => sum + ( Number( t.count ) || 0 ),
		0
	);
}

/**
 * The count shown on "All countries": exact only when no country is chosen
 * (entries without a location are not in the country facet).
 *
 * @param {Object|undefined} facets  Facets.
 * @param {Object}           filters Filters.
 * @return {number|null} Count, or null when it is not known.
 */
export function allCountriesCount( facets, filters ) {
	if ( ! facets || filters.country ) {
		return null;
	}
	return Math.max( 0, Number( facets.total ) || 0 );
}

/**
 * The rows shown and the pages.
 *
 * @param {number} total   Entries the filters match.
 * @param {number} page    Page.
 * @param {number} perPage Rows per page.
 * @return {{from: number, to: number, pages: number, page: number}} Paging.
 */
export function paging( total, page, perPage = PER_PAGE ) {
	const count = Math.max( 0, Number( total ) || 0 );
	const pages = Math.max( 1, Math.ceil( count / perPage ) );
	const current = Math.min( Math.max( 1, page ), pages );
	return {
		from: count ? ( current - 1 ) * perPage + 1 : 0,
		to: Math.min( count, current * perPage ),
		pages,
		page: current,
	};
}

/**
 * Page numbers to show: the first, the last, the current one and its
 * neighbours, with null for a gap.
 *
 * @param {number} page  Current page.
 * @param {number} pages Pages.
 * @return {Array<number|null>} Page numbers.
 */
export function pageList( page, pages ) {
	const keep = new Set( [ 1, pages, page - 1, page, page + 1 ] );
	const list = [];
	for ( let n = 1; n <= pages; n++ ) {
		if ( keep.has( n ) ) {
			list.push( n );
		} else if ( null !== list[ list.length - 1 ] ) {
			list.push( null );
		}
	}
	return list;
}

/**
 * Where an entry sits in the rows shown, for Previous and Next.
 *
 * @param {number[]} ids IDs in the order shown.
 * @param {number}   id  Entry shown in the drawer.
 * @return {{index: number, total: number, prev: number|null, next: number|null}} Position (index -1 when the entry is not in the list).
 */
export function navState( ids, id ) {
	const index = ids.indexOf( id );
	return {
		index,
		total: ids.length,
		prev: index > 0 ? ids[ index - 1 ] : null,
		next: index >= 0 && index < ids.length - 1 ? ids[ index + 1 ] : null,
	};
}

/**
 * The next sort when a column header is pressed: the other direction of the
 * same column, or the column's first direction (newest first for dates).
 *
 * @param {string} current Current sort key.
 * @param {string} column  date, type or ip.
 * @return {string} Sort key.
 */
export function toggleSort( current, column ) {
	const [ col, dir ] = current.split( ':' );
	if ( col === column ) {
		return `${ column }:${ 'asc' === dir ? 'desc' : 'asc' }`;
	}
	return 'date' === column ? 'date:desc' : `${ column }:asc`;
}

/**
 * aria-sort of a column.
 *
 * @param {string}   sort    Sort key.
 * @param {string[]} columns Columns the header sorts by.
 * @return {'ascending'|'descending'|'none'} Value.
 */
export function ariaSort( sort, columns ) {
	const [ col ] = sort.split( ':' );
	if ( ! columns.includes( col ) ) {
		return 'none';
	}
	const dir = sort.split( ':' )[ 1 ];
	return 'asc' === dir ? 'ascending' : 'descending';
}

/**
 * IDs of the entries selected on this page, after a reload: entries no
 * longer shown are dropped.
 *
 * @param {Set<number>} selected Selected IDs.
 * @param {number[]}    ids      IDs shown.
 * @return {Set<number>} Selected IDs still shown.
 */
export function keepSelected( selected, ids ) {
	const shown = new Set( ids );
	return new Set( [ ...selected ].filter( ( id ) => shown.has( id ) ) );
}
