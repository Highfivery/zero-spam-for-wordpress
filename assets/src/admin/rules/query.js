/**
 * The Rules area's tabs and REST paths (pure; unit tested).
 *
 * The first view's paths are preloaded by Admin_App::rules_preloads()
 * exactly as written here (the page's preload store is keyed by the path),
 * so the screen paints with its data and makes no request of its own on
 * load. test/query.test.js checks the two stay in step.
 */

import { NS } from '../data/api';

/** Tabs, in order (?tab=). */
export const TABS = [
	'ips',
	'locations',
	'allowlist',
	'email',
	'words',
	'no-lockout',
];

/** Rows per page of the block lists and the list editors. */
export const PER_PAGE = 50;

/** The block lists' default order: newest first. */
export const DEFAULT_SORT = 'date_added:desc';

/**
 * Width from which the block lists are a table; below it, a list of cards.
 * Wider than the Activity log's: in the widest content (a year in all three
 * date columns) the table's fixed columns and floors need this viewport with
 * the WordPress menu and the app's sidebar open.
 */
export const TABLE_MIN_WIDTH = 1440;
export const TABLE_QUERY = `(min-width: ${ TABLE_MIN_WIDTH }px)`;

/** The list each list tab edits (rules/lists/{list}). */
export const LIST_OF_TAB = {
	allowlist: 'allowed-ips',
	email: 'email-domains',
	words: 'allowed-words',
};

/** The block kind of each block tab. */
export const KIND_OF_TAB = { ips: 'ip', locations: 'location' };

/** Columns the block lists sort by (Rules_Controller::BLOCK_ORDERBY). */
export const SORT_COLUMNS = [ 'date_added', 'type', 'key', 'start', 'end' ];

/**
 * A query string from arguments, in the given order, without empty ones.
 *
 * @param {Array<[string, unknown]>} args Arguments.
 * @return {string} Query string with its "?", or ''.
 */
const query = ( args ) => {
	const text = args
		.filter( ( [ , v ] ) => undefined !== v && null !== v && '' !== v )
		.map(
			( [ k, v ] ) =>
				`${ encodeURIComponent( k ) }=${ encodeURIComponent( v ) }`
		)
		.join( '&' );
	return text ? `?${ text }` : '';
};

/**
 * The card list's Sort menu: its own choices, plus the current order when only
 * the table offers it (a sort chosen before the window narrowed), so the menu
 * never names an order the list is not in.
 *
 * @param {Array<[string, string]>} options Values and names.
 * @param {string}                  sort    Current order.
 * @param {string}                  other   Name for an order of the table.
 * @return {Array<[string, string]>} Values and names.
 */
export function sortChoices( options, sort, other ) {
	return options.some( ( [ value ] ) => value === sort )
		? options
		: [ ...options, [ sort, other ] ];
}

/**
 * A sort value split into column and direction.
 *
 * @param {string} sort column:direction.
 * @return {[string, string]} Column and asc or desc.
 */
export function splitSort( sort ) {
	const [ col, dir ] = String( sort || '' ).split( ':' );
	return [
		SORT_COLUMNS.includes( col ) ? col : 'date_added',
		'asc' === dir ? 'asc' : 'desc',
	];
}

/**
 * The next sort when a column header is pressed: the other direction for
 * the current column; a new column starts newest or A–Z first.
 *
 * @param {string} sort   Current sort.
 * @param {string} column Column pressed.
 * @return {string} New sort.
 */
export function toggleSort( sort, column ) {
	const [ col, dir ] = splitSort( sort );
	if ( col === column ) {
		return `${ col }:${ 'asc' === dir ? 'desc' : 'asc' }`;
	}
	return `${ column }:${ 'date_added' === column ? 'desc' : 'asc' }`;
}

/**
 * aria-sort of a column header.
 *
 * @param {string} sort   Current sort.
 * @param {string} column Column.
 * @return {string} ascending, descending or none.
 */
export function ariaSort( sort, column ) {
	const parts = splitSort( sort );
	if ( parts[ 0 ] !== column ) {
		return 'none';
	}
	return 'asc' === parts[ 1 ] ? 'ascending' : 'descending';
}

/**
 * The path of a block list page.
 *
 * @param {Object} view          View.
 * @param {string} view.kind     ip or location.
 * @param {string} [view.search] Search text.
 * @param {number} [view.page]   Page.
 * @param {string} [view.sort]   column:direction.
 * @return {string} Path.
 */
export function blocksPath( {
	kind,
	search = '',
	page = 1,
	sort = DEFAULT_SORT,
} ) {
	const [ orderby, order ] = splitSort( sort );
	return `${ NS }/rules/blocks${ query( [
		[ 'kind', 'location' === kind ? 'location' : 'ip' ],
		[ 'per_page', PER_PAGE ],
		[ 'orderby', orderby ],
		[ 'order', order ],
		[ 'page', page > 1 ? page : '' ],
		[ 'search', search.trim() ],
	] ) }`;
}

/**
 * The path of a list page.
 *
 * @param {string} list          allowed-ips, email-domains or allowed-words.
 * @param {Object} [view]        View.
 * @param {string} [view.search] Search text.
 * @param {number} [view.page]   Page.
 * @return {string} Path.
 */
export function listPath( list, { search = '', page = 1 } = {} ) {
	return `${ NS }/rules/lists/${ list }${ query( [
		[ 'per_page', PER_PAGE ],
		[ 'page', page > 1 ? page : '' ],
		[ 'search', search.trim() ],
	] ) }`;
}

/** The never-locked-out addresses. */
export const NO_LOCKOUT_PATH = `${ NS }/rules/no-lockout`;

/**
 * The path a tab reads on load (what Admin_App preloads for it).
 *
 * @param {string} tab Tab.
 * @return {string} Path.
 */
export function firstPath( tab ) {
	if ( KIND_OF_TAB[ tab ] ) {
		return blocksPath( { kind: KIND_OF_TAB[ tab ] } );
	}
	if ( LIST_OF_TAB[ tab ] ) {
		return listPath( LIST_OF_TAB[ tab ] );
	}
	return NO_LOCKOUT_PATH;
}

/**
 * Pages of a list.
 *
 * @param {number} total   Entries.
 * @param {number} page    Page asked for.
 * @param {number} [shown] Entries on this page.
 * @return {{page: number, pages: number, from: number, to: number}} Paging.
 */
export function paging( total, page, shown = PER_PAGE ) {
	const pages = Math.max(
		1,
		Math.ceil( ( Number( total ) || 0 ) / PER_PAGE )
	);
	const current = Math.min( Math.max( 1, Number( page ) || 1 ), pages );
	const from = total > 0 ? ( current - 1 ) * PER_PAGE + 1 : 0;
	return {
		page: current,
		pages,
		from,
		to: total > 0 ? Math.min( total, from + Math.max( 0, shown ) - 1 ) : 0,
	};
}
