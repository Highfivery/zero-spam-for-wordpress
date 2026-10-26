/**
 * The Settings area's own logic (pure; unit tested): the next quota reset
 * shown under the Quota Reset Date control, display preferences applied
 * live, and the import results and export file of Import & errors.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { motionReduced, resolveTheme, themePref } from '../data/theme';

/**
 * A day of a month as Y-m-d; a day the month does not have becomes its
 * last day (License_Status::monthly_date()).
 *
 * @param {number} year  Year.
 * @param {number} month Month (1–12; others move into the next or previous years).
 * @param {number} day   Day.
 * @return {string} Date.
 */
function monthlyDate( year, month, day ) {
	const index = year * 12 + month - 1;
	const y = Math.floor( index / 12 );
	const m = ( ( index % 12 ) + 12 ) % 12;
	const length = new Date( Date.UTC( y, m + 1, 0 ) ).getUTCDate();
	return `${ String( y ).padStart( 4, '0' ) }-${ String( m + 1 ).padStart(
		2,
		'0'
	) }-${ String( Math.min( day, length ) ).padStart( 2, '0' ) }`;
}

/**
 * Whether a string is a real calendar date (Y-m-d).
 *
 * @param {unknown} value Value.
 * @return {boolean} Valid.
 */
export function isDate( value ) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( String( value ?? '' ) );
	if ( ! match ) {
		return false;
	}
	const [ y, m, d ] = match.slice( 1 ).map( Number );
	return m >= 1 && m <= 12 && d >= 1 && monthlyDate( y, m, d ) === match[ 0 ];
}

/**
 * The quota period of a Quota Reset Date, as License_Status::quota_period()
 * works it out: the next reset after today (the date repeats monthly,
 * keeping its day of the month) and the reset before it.
 *
 * @param {string} value Quota Reset Date (Y-m-d).
 * @param {string} today Today (Y-m-d, site time).
 * @return {{start: string, next: string}|null} Period, or null for no valid date.
 */
export function quotaPeriod( value, today ) {
	if ( ! isDate( value ) || ! isDate( today ) ) {
		return null;
	}
	const [ y, m, d ] = value.split( '-' ).map( Number );
	let next = value;
	for ( let add = 0; next <= today && add < 1000; add++ ) {
		next = monthlyDate( y, m + add + 1, d );
	}
	const [ ny, nm ] = next.split( '-' ).map( Number );
	return { start: monthlyDate( ny, nm - 1, d ), next };
}

/**
 * A Y-m-d date for people (Nov 15, 2026), read as a calendar day.
 *
 * @param {string} date   Date.
 * @param {string} [lang] Locale.
 * @return {string} Text.
 */
export function formatDay( date, lang ) {
	if ( ! isDate( date ) ) {
		return '';
	}
	const [ y, m, d ] = date.split( '-' ).map( Number );
	return new Date( Date.UTC( y, m - 1, d ) ).toLocaleDateString( lang, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		timeZone: 'UTC',
	} );
}

/**
 * Today (Y-m-d) from the site's local "now" in milliseconds (read as UTC).
 *
 * @param {number} nowLocal Site-local now.
 * @return {string} Date.
 */
export const todayOf = ( nowLocal ) =>
	new Date( nowLocal ).toISOString().slice( 0, 10 );

/**
 * Display preferences after a change, and the attributes the app root
 * takes right away (before the save answers), so the change shows at once.
 *
 * @param {Object}  prefs    ui/prefs now.
 * @param {Object}  changes  { theme?, reduce_motion? }.
 * @param {boolean} osDark   The operating system prefers dark.
 * @param {boolean} osReduce The operating system asks for reduced motion.
 * @return {{prefs: Object, theme: string, shown: string, reduced: boolean, attributes: Object}} Result.
 */
export function applyDisplay( prefs, changes, osDark, osReduce ) {
	const next = { ...( prefs || {} ), ...changes };
	const theme = themePref( next.theme );
	const reduced = true === next.reduce_motion;
	return {
		prefs: next,
		theme,
		shown: resolveTheme( theme, osDark ),
		reduced: motionReduced( reduced, osReduce ),
		attributes: {
			'data-zs-theme': theme,
			'data-zs-motion': reduced ? 'reduce' : null,
		},
	};
}

/**
 * Sets the app root's attributes (null removes one).
 *
 * @param {Element}                     root       Root element.
 * @param {Object<string, string|null>} attributes Attributes.
 */
export function setRootAttributes( root, attributes ) {
	Object.entries( attributes ).forEach( ( [ name, value ] ) => {
		if ( null === value || undefined === value ) {
			root.removeAttribute( name );
		} else {
			root.setAttribute( name, value );
		}
	} );
}

/**
 * Titles of setting keys (the key itself when the schema does not know it).
 *
 * @param {unknown}                list    Keys.
 * @param {Object<string, Object>} entries Schema entries by key.
 * @return {{key: string, title: string}[]} Items.
 */
const titled = ( list, entries ) =>
	( Array.isArray( list ) ? list : [] ).map( ( key ) => ( {
		key: String( key ),
		title: entries[ key ]?.title || String( key ),
	} ) );

/**
 * The answer of settings/import as groups to list.
 *
 * `imported` are the values that were valid (`changed` the ones that
 * differed from before); `skipped_locked` the network administrator's
 * locked settings; `skipped_secrets` the keys and webhook URLs left out
 * without include_secrets; `errors` key → reason; `ignored` entries that
 * hold no value or are not a module's settings.
 *
 * @param {Object}                 answer  settings/import answer.
 * @param {Object<string, Object>} entries Schema entries by key.
 * @return {{applied: Object[], changed: number, locked: Object[], secrets: Object[], errors: Object[], ignored: Object[], ok: boolean}} Groups.
 */
export function importResult( answer, entries = {} ) {
	const errors = Object.entries( answer?.errors || {} ).map(
		( [ key, message ] ) => ( {
			key,
			title: entries[ key ]?.title || key,
			message: String( message || '' ).replace(
				/^[^"]*"[^"]+"\.\s*/,
				''
			),
		} )
	);
	const applied = titled( answer?.imported, entries );
	return {
		applied,
		changed: Array.isArray( answer?.changed ) ? answer.changed.length : 0,
		locked: titled( answer?.skipped_locked, entries ),
		secrets: titled( answer?.skipped_secrets, entries ),
		errors,
		ignored: titled( answer?.ignored, entries ),
		ok: 0 === errors.length,
	};
}

/**
 * Reads an import file's text: a settings export or the 5.x JSON.
 *
 * @param {string} text File contents.
 * @return {{ok: true, body: string}|{ok: false, message: string}} The JSON to send, or why not.
 */
export function readImport( text ) {
	let data;
	try {
		data = JSON.parse( String( text ).replace( /^\uFEFF/, '' ) );
	} catch {
		return {
			ok: false,
			message: __(
				'This file is not valid JSON. Choose a file exported from Zero Spam.',
				'zero-spam'
			),
		};
	}
	if ( ! data || 'object' !== typeof data || Array.isArray( data ) ) {
		return {
			ok: false,
			message: __(
				'This file holds no settings. Choose a file exported from Zero Spam.',
				'zero-spam'
			),
		};
	}
	return { ok: true, body: JSON.stringify( data ) };
}

/**
 * The name of an export file: zero-spam-settings-{site}-{date}.json.
 *
 * @param {string} siteUrl Site address.
 * @param {string} date    Y-m-d.
 * @return {string} File name.
 */
export function exportFileName( siteUrl, date ) {
	let host = '';
	try {
		host = new URL( siteUrl ).host;
	} catch {
		host = '';
	}
	const slug = host
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' );
	return [ 'zero-spam-settings', slug, date ]
		.filter( Boolean )
		.join( '-' )
		.concat( '.json' );
}
