/**
 * Absolute times (dates ending in Z or an offset, as block starts and ends)
 * in the site's time zone, for the date itself (pure; unit tested).
 *
 * Never derive a time zone from `nowLocal() - Date.now()`: that difference
 * also holds the page's latency and the browser clock's error, and it is
 * today's offset, not the offset of the date being shown. It is only right
 * for "now", which is what nowLocal() is for (relative times).
 *
 * `zone` is the bootstrap's `timezone` (Admin_App::site_time_zone()):
 * - `name`: the IANA name (Intl gives the offset of any date, with its DST
 *   rules), or '' when WordPress is set to a manual offset such as UTC+5:30;
 * - `offset`: the site's offset right now, in seconds: a manual offset, and
 *   the fallback when the browser does not know the name (right for dates on
 *   the same side of a DST change as today, an hour off on the other side).
 */

/**
 * Internal dependencies
 */
import { parseDate } from './format';

const HAS_ZONE = /(Z|[+-]\d{2}:?\d{2})$/;

/** Formatters by zone name (null: the browser does not know the name). */
const formatters = new Map();

/**
 * The formatter of a zone name.
 *
 * @param {string} name IANA name.
 * @return {Intl.DateTimeFormat|null} Formatter, or null.
 */
function formatterOf( name ) {
	if ( ! name ) {
		return null;
	}
	if ( ! formatters.has( name ) ) {
		let formatter = null;
		try {
			// A fixed calendar and digits, whatever the language of the page.
			formatter = new Intl.DateTimeFormat( 'en-US-u-ca-gregory-nu-latn', {
				timeZone: name,
				hourCycle: 'h23',
				year: 'numeric',
				month: 'numeric',
				day: 'numeric',
				hour: 'numeric',
				minute: 'numeric',
				second: 'numeric',
			} );
		} catch {
			formatter = null;
		}
		formatters.set( name, formatter );
	}
	return formatters.get( name );
}

/**
 * The site's offset from UTC at a moment.
 *
 * @param {number} ms   Moment, in milliseconds since the epoch.
 * @param {Object} zone The bootstrap's time zone.
 * @return {number} Offset in milliseconds.
 */
export function offsetAt( ms, zone ) {
	const formatter = formatterOf( zone?.name );
	if ( formatter ) {
		const part = {};
		formatter.formatToParts( ms ).forEach( ( { type, value } ) => {
			part[ type ] = Number( value );
		} );
		const wall = Date.UTC(
			part.year,
			part.month - 1,
			part.day,
			part.hour % 24,
			part.minute,
			part.second
		);
		if ( ! Number.isNaN( wall ) ) {
			return wall - Math.floor( ms / 1000 ) * 1000;
		}
	}
	return ( Number( zone?.offset ) || 0 ) * 1000;
}

/**
 * A REST date as the site's wall clock, in a Date read as UTC (as day(),
 * time() and dayTime() of format.js format it). A date without a zone is
 * already site time (date_added, the log) and stays as it is.
 *
 * @param {string|null} value Date.
 * @param {Object}      zone  The bootstrap's time zone.
 * @return {Date|null} The wall clock, or null.
 */
export function siteWall( value, zone ) {
	const date = parseDate( value );
	if ( ! date ) {
		return null;
	}
	return HAS_ZONE.test( String( value ) )
		? new Date( date.getTime() + offsetAt( date.getTime(), zone ) )
		: date;
}
