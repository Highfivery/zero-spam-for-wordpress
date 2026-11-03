/**
 * The block form's values (pure; unit tested): dates in the site's time
 * for the inputs, and the body `zero-spam/v1/rules/blocks` takes.
 */

/**
 * Internal dependencies
 */
import { siteWall } from '../data/site-time';

const DAY = 86400000;

/**
 * A UTC date from the API as the value of a datetime-local input, in the
 * site's time for that date (seconds are not shown: see editBaseline()).
 *
 * @param {string|null} iso  UTC date (ends in Z).
 * @param {Object}      zone The bootstrap's time zone (data/site-time.js).
 * @return {string} YYYY-MM-DDTHH:MM, or '' without a date.
 */
export function toSiteInput( iso, zone ) {
	const wall = siteWall( iso, zone );
	return wall ? wall.toISOString().slice( 0, 16 ) : '';
}

/**
 * The form's first values.
 *
 * @param {Object|null} block Block being changed (rules/blocks shape), or null.
 * @param {string}      ip    Address to block.
 * @param {number}      now   Site-local now in milliseconds (read as UTC).
 * @param {Object}      zone  The bootstrap's time zone (for a block's dates).
 * @return {{ip: string, type: string, reason: string, start: string, end: string}} Values.
 */
export function initialValues( block, ip, now, zone ) {
	if ( block ) {
		return {
			ip: block.ip || block.key || '',
			type: 'temporary' === block.type ? 'temporary' : 'permanent',
			reason: block.reason || '',
			start: toSiteInput( block.start, zone ),
			end:
				toSiteInput( block.end, zone ) ||
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
 * @param {Object}      zone  The bootstrap's time zone (for a block's dates).
 * @return {{key_type: string, key: string, type: string, reason: string, start: string, end: string}} Values.
 */
export function initialLocationValues( block, now, zone ) {
	const { ip, ...rest } = initialValues( block, '', now, zone );
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
 * The dates a block being edited has in the form, to tell which were edited.
 *
 * The inputs show minutes in the site's wall clock, which cannot hold a
 * stored date exactly: the seconds of a date-only end (23:59:59), or which of
 * the two 01:30 of the night the clocks go back. So a date the form did not
 * change is not sent, and the server keeps the stored one to the second.
 *
 * @param {Object|null} block Block being changed (rules/blocks shape), or null.
 * @param {Object}      zone  The bootstrap's time zone.
 * @return {{start: string, end: string}|null} Form values of its dates, or null.
 */
export function editBaseline( block, zone ) {
	if ( ! block ) {
		return null;
	}
	return {
		start: toSiteInput( block.start, zone ),
		end: toSiteInput( block.end, zone ),
	};
}

/**
 * The request body. Times without an offset are the site's wall clock; the
 * server reads them (Rules_Controller::parse_utc(): a time the clocks skip
 * moves forward by the skip, a time they repeat is its first occurrence).
 * Values with a `key_type` are a location block (initialLocationValues()).
 *
 * @param {Object}                            values     Values.
 * @param {{start: string, end: string}|null} [baseline] editBaseline() of the block being edited: dates still at these values are not sent.
 * @return {Object} Body.
 */
export function blockBody( values, baseline = null ) {
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
	const startEdited = values.start && values.start !== baseline?.start;
	if ( startEdited ) {
		body.start = withSeconds( values.start );
	} else if ( ! values.start && baseline?.start ) {
		// An emptied start: the server reads '' as now, which is what the
		// field's hint promises. Not sending it would keep the old start.
		body.start = '';
	}
	if ( 'temporary' === values.type ) {
		// A new start past the end is sent with the end, so the server
		// answers on the end field that it must come after the start.
		const endKept =
			baseline?.end &&
			values.end === baseline.end &&
			! ( startEdited && values.start >= baseline.end );
		if ( ! endKept ) {
			body.end = withSeconds( values.end );
		}
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
