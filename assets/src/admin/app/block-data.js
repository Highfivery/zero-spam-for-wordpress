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
