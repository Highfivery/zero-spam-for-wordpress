/**
 * Unsaved changes to a list (the IP Allowlist, the blocked email domains,
 * the allowed words), kept as entries to add and entries to remove (pure;
 * unit tested).
 *
 * The lists can be long (the recommended email domains are about 3,000),
 * so the screen shows them a page at a time and never holds the whole
 * list: changes are staged against what the server has and sent as
 * `{ add, remove }` (PUT rules/lists/{list}), which the server applies to
 * the list it holds. Entries are compared without case, as the server
 * compares them.
 */

const key = ( entry ) => String( entry ).toLowerCase();

/** @return {{add: string[], remove: string[]}} No changes. */
export const emptyDraft = () => ( { add: [], remove: [] } );

/**
 * Stages entries to add. An entry staged for removal is kept instead; one
 * already staged, or known to be on the list, is left alone.
 *
 * @param {{add: string[], remove: string[]}} draft    Draft.
 * @param {string[]}                          entries  Entries in stored form.
 * @param {(entry: string) => boolean}        [onList] Whether an entry is known to be on the list already.
 * @return {{draft: {add: string[], remove: string[]}, skipped: string[]}} The new draft and the entries left alone.
 */
export function stageAdd( draft, entries, onList = () => false ) {
	const add = [ ...draft.add ];
	let remove = [ ...draft.remove ];
	const skipped = [];
	entries.forEach( ( entry ) => {
		const k = key( entry );
		if ( remove.some( ( r ) => key( r ) === k ) ) {
			remove = remove.filter( ( r ) => key( r ) !== k );
		} else if ( add.some( ( a ) => key( a ) === k ) || onList( entry ) ) {
			skipped.push( entry );
		} else {
			add.push( entry );
		}
	} );
	return { draft: { add, remove }, skipped };
}

/**
 * Stages an entry for removal; an entry only staged to be added is simply
 * dropped.
 *
 * @param {{add: string[], remove: string[]}} draft Draft.
 * @param {string}                            entry Entry.
 * @return {{add: string[], remove: string[]}} The new draft.
 */
export function stageRemove( draft, entry ) {
	const k = key( entry );
	if ( draft.add.some( ( a ) => key( a ) === k ) ) {
		return { ...draft, add: draft.add.filter( ( a ) => key( a ) !== k ) };
	}
	if ( draft.remove.some( ( r ) => key( r ) === k ) ) {
		return draft;
	}
	return { ...draft, remove: [ ...draft.remove, entry ] };
}

/**
 * Keeps an entry staged for removal.
 *
 * @param {{add: string[], remove: string[]}} draft Draft.
 * @param {string}                            entry Entry.
 * @return {{add: string[], remove: string[]}} The new draft.
 */
export const unstageRemove = ( draft, entry ) => ( {
	...draft,
	remove: draft.remove.filter( ( r ) => key( r ) !== key( entry ) ),
} );

/**
 * @param {{add: string[], remove: string[]}} draft Draft.
 * @return {number} Entries added or removed.
 */
export const changeCount = ( draft ) => draft.add.length + draft.remove.length;

/**
 * The body of PUT rules/lists/{list}.
 *
 * @param {{add: string[], remove: string[]}} draft       Draft.
 * @param {number|null}                       [minLength] New shortest disallowed word (allowed words only).
 * @return {Object} Body.
 */
export function requestBody( draft, minLength = null ) {
	const body = {};
	if ( draft.add.length ) {
		body.add = draft.add;
	}
	if ( draft.remove.length ) {
		body.remove = draft.remove;
	}
	if ( null !== minLength && undefined !== minLength ) {
		body.min_length = minLength;
	}
	return body;
}

/**
 * The rows a list shows: entries staged to add first (when they match the
 * search), then the page from the server, each marked when it is staged
 * for removal.
 *
 * @param {string[]}                          items  Entries of the page.
 * @param {{add: string[], remove: string[]}} draft  Draft.
 * @param {string}                            search Search text.
 * @return {Array<{entry: string, state: string}>} Rows (state: added, removed or saved).
 */
export function listRows( items, draft, search = '' ) {
	const q = key( search.trim() );
	const removed = new Set( draft.remove.map( key ) );
	const added = draft.add
		.filter( ( a ) => ! q || key( a ).includes( q ) )
		.map( ( entry ) => ( { entry, state: 'added' } ) );
	const addedKeys = new Set( draft.add.map( key ) );
	return [
		...added,
		...items
			.filter( ( i ) => ! addedKeys.has( key( i ) ) )
			.map( ( entry ) => ( {
				entry,
				state: removed.has( key( entry ) ) ? 'removed' : 'saved',
			} ) ),
	];
}

/**
 * What changes a list into another: entries to add and to remove.
 *
 * @param {string[]} saved Entries saved.
 * @param {string[]} next  Entries wanted.
 * @return {{add: string[], remove: string[]}} Differences.
 */
export function diffLists( saved, next ) {
	const have = new Set( saved.map( key ) );
	const want = new Set( next.map( key ) );
	const seen = new Set();
	return {
		add: next.filter( ( n ) => {
			const k = key( n );
			if ( have.has( k ) || seen.has( k ) ) {
				return false;
			}
			seen.add( k );
			return true;
		} ),
		remove: saved.filter( ( s ) => ! want.has( key( s ) ) ),
	};
}
