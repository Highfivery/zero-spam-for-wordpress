/**
 * The block form's values (pure; unit tested): dates in the site's time
 * for the inputs, and the body `zero-spam/v1/rules/blocks` takes.
 */

const DAY = 86400000;

/**
 * A UTC date from the API as the value of a datetime-local input, in the
 * site's time.
 *
 * @param {string|null} iso   UTC date (ends in Z).
 * @param {number}      shift Site time minus browser time, in milliseconds.
 * @return {string} YYYY-MM-DDTHH:MM, or '' without a date.
 */
export function toSiteInput( iso, shift ) {
	const t = iso ? Date.parse( iso ) : NaN;
	if ( Number.isNaN( t ) ) {
		return '';
	}
	return new Date( t + shift ).toISOString().slice( 0, 16 );
}

/**
 * The form's first values.
 *
 * @param {Object|null} block Block being changed (rules/blocks shape), or null.
 * @param {string}      ip    Address to block.
 * @param {number}      now   Site-local now in milliseconds (read as UTC).
 * @param {number}      shift Site time minus browser time, in milliseconds.
 * @return {{ip: string, type: string, reason: string, start: string, end: string}} Values.
 */
export function initialValues( block, ip, now, shift ) {
	if ( block ) {
		return {
			ip: block.ip || block.key || '',
			type: 'temporary' === block.type ? 'temporary' : 'permanent',
			reason: block.reason || '',
			start: toSiteInput( block.start, shift ),
			end:
				toSiteInput( block.end, shift ) ||
				new Date( now + 7 * DAY ).toISOString().slice( 0, 16 ),
		};
	}
	return {
		ip: ip || '',
		type: 'temporary',
		reason: '',
		start: '',
		end: new Date( now + 7 * DAY ).toISOString().slice( 0, 16 ),
	};
}

/**
 * The first values of the location form: as initialValues(), with the
 * location type (a country by default) and the location instead of an
 * address.
 *
 * @param {Object|null} block Location block being changed, or null.
 * @param {number}      now   Site-local now in milliseconds (read as UTC).
 * @param {number}      shift Site time minus browser time, in milliseconds.
 * @return {{key_type: string, key: string, type: string, reason: string, start: string, end: string}} Values.
 */
export function initialLocationValues( block, now, shift ) {
	const { ip, ...rest } = initialValues( block, '', now, shift );
	return {
		key_type: block?.key_type || 'country_code',
		key: block ? block.key || '' : ip,
		...rest,
	};
}

/**
 * A datetime-local value (YYYY-MM-DDTHH:MM) as the API reads it: with
 * seconds (rest_parse_date() needs them), still without an offset.
 *
 * @param {string} value Input value.
 * @return {string} Date and time.
 */
export const withSeconds = ( value ) =>
	/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test( value ) ? `${ value }:00` : value;

/**
 * The request body (times without an offset are the site's time). Values
 * with a `key_type` are a location block (initialLocationValues()).
 *
 * @param {Object} values Values.
 * @return {Object} Body.
 */
export function blockBody( values ) {
	const body = values.key_type
		? {
				kind: 'location',
				key_type: values.key_type,
				key: values.key.trim(),
				type: values.type,
				reason: values.reason.trim(),
			}
		: {
				kind: 'ip',
				ip: values.ip.trim(),
				type: values.type,
				reason: values.reason.trim(),
			};
	if ( values.start ) {
		body.start = withSeconds( values.start );
	}
	if ( 'temporary' === values.type ) {
		body.end = withSeconds( values.end );
	}
	return body;
}

/**
 * What "Block selected IPs" does with the selected rows: each address once,
 * the ones already blocked left out (and counted).
 *
 * @param {Array<{id: number, ip: string, blocked: boolean}>} rows     Rows shown.
 * @param {Set<number>}                                       selected Selected IDs.
 * @return {{ips: string[], already: number}} Addresses to block, and how many were already blocked.
 */
export function bulkBlockPlan( rows, selected ) {
	const block = new Set();
	const already = new Set();
	rows.filter( ( r ) => selected.has( r.id ) ).forEach( ( r ) => {
		const ip = String( r.ip || '' ).trim();
		if ( ! ip ) {
			return;
		}
		( r.blocked ? already : block ).add( ip.toLowerCase() );
	} );
	already.forEach( ( ip ) => block.delete( ip ) );
	return {
		ips: rows
			.map( ( r ) => String( r.ip || '' ).trim() )
			.filter(
				( ip, i, list ) =>
					block.has( ip.toLowerCase() ) &&
					list.findIndex(
						( x ) => x.toLowerCase() === ip.toLowerCase()
					) === i
			),
		already: already.size,
	};
}

/**
 * Blocks addresses in one request (`rules/blocks/bulk`) with the block
 * drawer's defaults: temporary, ending at `end`, no reason.
 *
 * @param {Object}                          options         Options.
 * @param {string[]}                        options.ips     Addresses.
 * @param {string}                          options.end     End (site time, YYYY-MM-DDTHH:MM).
 * @param {(...args: unknown[]) => unknown} options.request apiFetch-like.
 * @return {Promise<{created: number, replaced: number, skipped: string[], failed: number, undo_token: string|null}>} What happened; undo_token removes the new blocks and puts replaced ones back.
 */
export function blockAll( { ips, end, request } ) {
	const body = blockBody( {
		ip: '',
		type: 'temporary',
		reason: '',
		start: '',
		end,
	} );
	delete body.ip;
	delete body.kind;
	return request( {
		path: '/zero-spam/v1/rules/blocks/bulk',
		method: 'POST',
		data: { ...body, ips },
	} ).then( ( answer ) => ( {
		created: Number( answer?.created ) || 0,
		replaced: Number( answer?.replaced ) || 0,
		skipped: Array.isArray( answer?.skipped ) ? answer.skipped : [],
		failed: Object.keys( answer?.invalid || {} ).length,
		undo_token: answer?.undo_token || null,
	} ) );
}
