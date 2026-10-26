/**
 * The news on Help, as data: which state the tile is in, the category each
 * article is filed under and the chips to filter by. No JSX, so the unit
 * tests run in Node.
 */

/** The category zerospam.org files security notices under (News_Feed::SECURITY_CATEGORY). */
export const SECURITY_CATEGORY = 'incident response';

/**
 * The category an article is shown under: its first one that is not the
 * security-notice category (the badge says that), else its first one.
 *
 * @param {Object} item News item (`categories`).
 * @return {string} Category, or ''.
 */
export function displayCategory( item ) {
	const list = Array.isArray( item?.categories )
		? item.categories.filter( Boolean )
		: [];
	const own = list.find(
		( c ) => SECURITY_CATEGORY !== String( c ).toLowerCase()
	);
	return own || list[ 0 ] || '';
}

/**
 * The categories to filter by, in the order the articles first use them.
 *
 * @param {Object[]} items News items.
 * @return {string[]} Categories.
 */
export function newsCategories( items ) {
	return [ ...new Set( ( items || [] ).map( displayCategory ) ) ].filter(
		Boolean
	);
}

/**
 * The articles in a category ('' for all).
 *
 * @param {Object[]} items    News items.
 * @param {string}   category Category, or ''.
 * @return {Object[]} Articles.
 */
export function filterNews( items, category ) {
	const list = items || [];
	return category
		? list.filter( ( item ) => displayCategory( item ) === category )
		: list;
}

/**
 * What the news tile shows.
 *
 * - `disabled`: Zero Spam News is turned off (the setting, the filter, or
 *   the route answering 404 `zerospam_news_disabled`);
 * - `loading`: the first answer has not arrived;
 * - `unavailable`: no copy could be had (503, a network error);
 * - `empty`: the feed had no articles;
 * - `ready`: articles to show (`stale` when an older copy is served).
 *
 * @param {Object}  args         Arguments.
 * @param {boolean} args.enabled boot.newsEnabled.
 * @param {Object}  args.state   The news resource (`data`, `error`, `loading`, `headers`).
 * @return {{state: string, stale: boolean}} State.
 */
export function newsView( { enabled, state } ) {
	const data = state?.data;
	// A preloaded error arrives as the body of a 200 answer (the preloading
	// middleware); a fetched one as the error (or the Response itself).
	const error =
		state?.error ||
		( data && ! Array.isArray( data ) && data.code ? data : null );
	const status = error?.data?.status ?? error?.status;
	if (
		! enabled ||
		( error &&
			( 'zerospam_news_disabled' === error.code || 404 === status ) )
	) {
		return { state: 'disabled', stale: false };
	}

	const items = Array.isArray( data ) ? data : null;
	if ( items && items.length ) {
		return {
			state: 'ready',
			stale: '1' === state.headers?.[ 'x-zerospam-news-stale' ],
		};
	}
	if ( error ) {
		return { state: 'unavailable', stale: false };
	}
	if ( ! items ) {
		return { state: 'loading', stale: false };
	}
	return { state: 'empty', stale: false };
}
