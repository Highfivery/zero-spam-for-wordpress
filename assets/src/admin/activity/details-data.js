/**
 * What the details drawer shows from an entry's stored details (pure;
 * unit tested). Every value becomes text: nothing a visitor sent is ever
 * treated as markup.
 */

/** Keys of the stored details that are shown elsewhere in the drawer. */
const SHOWN_ELSEWHERE = [ 'type', 'failed' ];

/**
 * The stored details as label/text pairs (nested values as JSON text).
 *
 * @param {Object|null} details Stored details.
 * @return {Array<[string, string]>} Pairs.
 */
export function detailPairs( details ) {
	if ( ! details || 'object' !== typeof details ) {
		return [];
	}
	return Object.entries( details )
		.filter( ( [ key ] ) => ! SHOWN_ELSEWHERE.includes( key ) )
		.map( ( [ key, value ] ) => {
			let text = '';
			if ( null === value || undefined === value ) {
				text = '';
			} else if ( 'object' === typeof value ) {
				text = JSON.stringify( value );
			} else {
				text = String( value );
			}
			return [ String( key ), text ];
		} );
}

/**
 * The confidence the Zero Spam network gave the address, when the
 * detection recorded one (0 to 100).
 *
 * @param {Object|null} details Stored details.
 * @return {number|null} Confidence.
 */
export function confidenceOf( details ) {
	if ( ! details ) {
		return null;
	}
	const score = Number( details.confidence_score );
	if ( Number.isFinite( score ) && score > 0 ) {
		return Math.min( 100, Math.round( score ) );
	}
	const raw = Number( details.confidence );
	if ( Number.isFinite( raw ) && raw > 0 ) {
		return Math.min( 100, Math.round( raw <= 1 ? raw * 100 : raw ) );
	}
	return null;
}
