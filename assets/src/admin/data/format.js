/**
 * Numbers, dates and relative times in the site's language.
 *
 * One date pattern everywhere (prototype v14): "Thu, Oct 22", with the time
 * added when it matters and the year only when it is not the current one.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

const locale = () =>
	( typeof document !== 'undefined' && document.documentElement.lang ) ||
	'en-US';

/**
 * A whole number with grouping ("1,284").
 *
 * @param {number} value Number.
 * @return {string} Formatted number.
 */
export function num( value ) {
	try {
		return new Intl.NumberFormat( locale() ).format( Number( value ) || 0 );
	} catch {
		return String( Number( value ) || 0 );
	}
}

/**
 * A signed difference ("+96", "−4").
 *
 * @param {number} value Difference.
 * @return {string} Formatted difference.
 */
export function signed( value ) {
	const v = Number( value ) || 0;
	if ( v > 0 ) {
		return '+' + num( v );
	}
	if ( v < 0 ) {
		return '−' + num( -v );
	}
	return '0';
}

/**
 * Parses a REST date. Dates without a time zone are site-local times; they
 * are shown as they are (no conversion), so they are read as UTC and
 * formatted in UTC.
 *
 * @param {string} value Date (Y-m-d or ISO 8601).
 * @return {Date|null} Date.
 */
export function parseDate( value ) {
	if ( ! value ) {
		return null;
	}
	const text = String( value );
	const iso = /^\d{4}-\d{2}-\d{2}$/.test( text ) ? text + 'T00:00:00Z' : text;
	const withZone = /(Z|[+-]\d{2}:?\d{2})$/.test( iso ) ? iso : iso + 'Z';
	const date = new Date( withZone );
	return Number.isNaN( date.getTime() ) ? null : date;
}

/**
 * "Thu, Oct 22" (year added when it is not this year).
 *
 * @param {string|Date} value Date.
 * @param {Date}        now   Today.
 * @return {string} Formatted day.
 */
export function day( value, now = new Date() ) {
	const date = value instanceof Date ? value : parseDate( value );
	if ( ! date ) {
		return '';
	}
	const options = {
		weekday: 'short',
		month: 'short',
		day: 'numeric',
		timeZone: 'UTC',
	};
	if ( date.getUTCFullYear() !== now.getUTCFullYear() ) {
		options.year = 'numeric';
	}
	return date.toLocaleDateString( locale(), options );
}

/**
 * "2:12 PM" (the time of a site-local date).
 *
 * @param {string|Date} value Date.
 * @return {string} Formatted time.
 */
export function time( value ) {
	const date = value instanceof Date ? value : parseDate( value );
	if ( ! date ) {
		return '';
	}
	return date.toLocaleTimeString( locale(), {
		hour: 'numeric',
		minute: '2-digit',
		timeZone: 'UTC',
	} );
}

/**
 * "Thu, Oct 22 · 2:12 PM": the day pattern with the time, for when the
 * time matters.
 *
 * @param {string|Date} value Date.
 * @param {Date}        now   Today.
 * @return {string} Formatted date and time.
 */
export function dayTime( value, now = new Date() ) {
	const d = day( value, now );
	return d ? `${ d } · ${ time( value ) }` : '';
}

/**
 * "Oct 22" for chart axes.
 *
 * @param {string} value Date.
 * @return {string} Formatted date.
 */
export function shortDay( value ) {
	const date = parseDate( value );
	return date
		? date.toLocaleDateString( locale(), {
				month: 'short',
				day: 'numeric',
				timeZone: 'UTC',
			} )
		: '';
}

/**
 * Minutes between a site-local REST date and now (site-local).
 *
 * @param {string} value    Date.
 * @param {number} nowLocal Site-local "now" in milliseconds, read as UTC.
 * @return {number} Minutes, never negative.
 */
export function minutesAgo( value, nowLocal ) {
	const date = parseDate( value );
	if ( ! date ) {
		return 0;
	}
	return Math.max( 0, Math.floor( ( nowLocal - date.getTime() ) / 60000 ) );
}

/**
 * "Just now", "2 min ago", "3 h ago", "Yesterday", "4 days ago".
 *
 * @param {number} minutes Minutes ago.
 * @return {string} Relative time.
 */
export function ago( minutes ) {
	if ( minutes < 1 ) {
		return __( 'Just now', 'zero-spam' );
	}
	if ( minutes < 60 ) {
		/* translators: %d: number of minutes */
		return sprintf( __( '%d min ago', 'zero-spam' ), minutes );
	}
	if ( minutes < 1440 ) {
		return sprintf(
			/* translators: %d: number of hours */
			__( '%d h ago', 'zero-spam' ),
			Math.round( minutes / 60 )
		);
	}
	if ( minutes < 2880 ) {
		return __( 'Yesterday', 'zero-spam' );
	}
	const days = Math.floor( minutes / 1440 );
	return sprintf(
		/* translators: %s: number of days */
		_n( '%s day ago', '%s days ago', days, 'zero-spam' ),
		num( days )
	);
}
