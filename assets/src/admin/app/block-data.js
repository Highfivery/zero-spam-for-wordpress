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
 * The request body (times without an offset are the site's time).
 *
 * @param {Object} values Values.
 * @return {Object} Body.
 */
export function blockBody( values ) {
	const body = {
		kind: 'ip',
		ip: values.ip.trim(),
		type: values.type,
		reason: values.reason.trim(),
	};
	if ( values.start ) {
		body.start = values.start;
	}
	if ( 'temporary' === values.type ) {
		body.end = values.end;
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
 * Blocks addresses one by one with the block drawer's defaults (temporary,
 * ending at `end`, no reason).
 *
 * @param {Object}                          options         Options.
 * @param {string[]}                        options.ips     Addresses.
 * @param {string}                          options.end     End (site time, YYYY-MM-DDTHH:MM).
 * @param {(...args: unknown[]) => unknown} options.request apiFetch-like; called with parse: false.
 * @return {Promise<{created: number[], replaced: number, failed: number}>} IDs of new blocks (what Undo removes), blocks replaced, failures.
 */
export function blockAll( { ips, end, request } ) {
	return Promise.allSettled(
		ips.map( ( ip ) =>
			request( {
				path: '/zero-spam/v1/rules/blocks',
				method: 'POST',
				data: blockBody( {
					ip,
					type: 'temporary',
					reason: '',
					start: '',
					end,
				} ),
				parse: false,
			} ).then( ( response ) =>
				response.json().then( ( saved ) => ( {
					saved,
					created: 201 === response.status,
				} ) )
			)
		)
	).then( ( results ) => {
		const out = { created: [], replaced: 0, failed: 0 };
		results.forEach( ( r ) => {
			if ( 'fulfilled' !== r.status ) {
				out.failed++;
			} else if ( r.value.created ) {
				out.created.push( r.value.saved.id );
			} else {
				out.replaced++;
			}
		} );
		return out;
	} );
}
