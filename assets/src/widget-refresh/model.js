/**
 * The dashboard widget's numbers, sentences and sparkline, without the DOM.
 *
 * Each function mirrors its PHP twin in ZeroSpam\Core\Admin, so a refreshed
 * widget reads like the one the server rendered (sparkline coordinates agree
 * to 0.1 unit; toFixed() and number_format() may round a rare halfway value
 * differently):
 *
 * - sparkPaths()  Sparkline::paths() (the approved prototype's spark());
 * - figures()     Dashboard_Widget::figures();
 * - deltaText()   Dashboard_Widget::delta_text();
 * - sparkText()   Dashboard_Widget::spark_text().
 *
 * The tests (test/model.test.js and tests/phpunit/unit/test-sparkline.php)
 * use the same values on both sides.
 */

export const SPARK_WIDTH = 240;
export const SPARK_HEIGHT = 48;

/**
 * A coordinate with one decimal.
 *
 * @param {number} value Coordinate.
 * @return {string} Text.
 */
const coordinate = ( value ) => {
	const text = value.toFixed( 1 );
	return text === '-0.0' ? '0.0' : text;
};

/**
 * The line and the area under it, for an <svg viewBox="0 0 width height"
 * preserveAspectRatio="none">.
 *
 * @param {number[]} values Values, oldest first; negatives count as zero.
 * @param {number}   width  Width of the viewBox.
 * @param {number}   height Height of the viewBox.
 * @return {{line: string, area: string}} Path data.
 */
export function sparkPaths(
	values,
	width = SPARK_WIDTH,
	height = SPARK_HEIGHT
) {
	let points = ( values || [] ).map( ( value ) =>
		Math.max( 0, Number( value ) || 0 )
	);
	if ( ! points.length ) {
		points = [ 0 ];
	}
	// One value is a flat line across the whole width.
	if ( points.length === 1 ) {
		points.push( points[ 0 ] );
	}
	// An empty series stays a flat line on the bottom instead of dividing by zero.
	const max = Math.max( 1, ...points );
	const last = points.length - 1;
	const line = points
		.map(
			( value, i ) =>
				( i ? 'L' : 'M' ) +
				coordinate( ( i / last ) * width ) +
				' ' +
				coordinate( height - 2 - ( value / max ) * ( height - 4 ) )
		)
		.join( '' );

	return { line, area: `${ line }L${ width } ${ height }L0 ${ height }Z` };
}

/**
 * The headline figures from daily totals (oldest first).
 *
 * @param {number[]} days Detections per day, oldest first.
 * @return {{total: number, week: number, previous: number, delta: number, percent: number|null}} Figures.
 */
export function figures( days ) {
	const values = ( days || [] ).map(
		( day ) => Math.trunc( Number( day ) ) || 0
	);
	const sum = ( list ) => list.reduce( ( a, b ) => a + b, 0 );
	const n = values.length;
	const week = sum( values.slice( -7 ) );
	const previous =
		n > 7 ? sum( values.slice( Math.max( 0, n - 14 ), n - 7 ) ) : 0;
	const delta = week - previous;

	return {
		total: sum( values ),
		week,
		previous,
		delta,
		percent:
			previous > 0
				? Math.round( ( Math.abs( delta ) / previous ) * 100 )
				: null,
	};
}

/**
 * Fills a sprintf-style template (%s, %1$s, %2$s and %%).
 *
 * @param {string}    template Template.
 * @param {...string} values   Values.
 * @return {string} Text.
 */
export function format( template, ...values ) {
	let next = 0;
	return String( template ).replace(
		/%(?:(\d+)\$)?([s%])/g,
		( match, position, kind ) => {
			if ( kind === '%' ) {
				return '%';
			}
			const index = position ? Number( position ) - 1 : next++;
			return index < values.length ? String( values[ index ] ) : '';
		}
	);
}

/**
 * Formats whole numbers for a locale.
 *
 * @param {string} locale BCP 47 locale, for example en-US.
 * @return {(value: number) => string} Formatter.
 */
export function numberFormat( locale ) {
	let formatter;
	try {
		formatter = new Intl.NumberFormat( locale || undefined );
	} catch {
		formatter = new Intl.NumberFormat();
	}
	return ( value ) => formatter.format( value );
}

/**
 * The "Last 7 days" sentence and its direction.
 *
 * @param {ReturnType<typeof figures>} fig    Figures.
 * @param {Object<string, string>}     i18n   Strings (Dashboard_Widget::strings()).
 * @param {(value: number) => string}  number Number formatter.
 * @return {{text: string, direction: string}} Sentence and up, down or same.
 */
export function deltaText( fig, i18n, number ) {
	const week = number( fig.week );
	if ( fig.delta === 0 ) {
		return { text: format( i18n.deltaSame, week ), direction: 'same' };
	}
	const amount =
		fig.percent === null
			? number( Math.abs( fig.delta ) )
			: format( i18n.percent, number( fig.percent ) );
	const up = fig.delta > 0;
	return {
		text: format( up ? i18n.deltaUp : i18n.deltaDown, week, amount ),
		direction: up ? 'up' : 'down',
	};
}

/**
 * The sparkline's text alternative.
 *
 * @param {number[]}                  days   Detections per day, oldest first.
 * @param {Object<string, string>}    i18n   Strings.
 * @param {(value: number) => string} number Number formatter.
 * @return {string} Sentence.
 */
export function sparkText( days, i18n, number ) {
	const values = ( days || [] ).map( ( day ) => Number( day ) || 0 );
	return format(
		i18n.spark,
		number( values.length ? Math.max( ...values ) : 0 ),
		number( values.length ? values[ values.length - 1 ] : 0 )
	);
}

/**
 * A REST URL under the plugin's namespace root, which may already carry a
 * query string (sites without pretty permalinks use ?rest_route=).
 *
 * @param {string}                 root   Namespace root, ending in "/".
 * @param {string}                 path   Route below the root.
 * @param {Object<string, string>} params Query parameters.
 * @return {string} URL.
 */
export function endpoint( root, path, params = {} ) {
	const url = String( root ) + path;
	const query = new URLSearchParams( params ).toString();
	if ( ! query ) {
		return url;
	}
	return url + ( url.includes( '?' ) ? '&' : '?' ) + query;
}

/**
 * The latest detections from an activity page: entries whose type is not a
 * detection (rescue links) are left out, as the server's list does.
 *
 * @param {Array<Object>} entries Entries (GET activity), newest first.
 * @param {string[]}      skip    Log types that are not detections.
 * @param {number}        limit   How many to keep.
 * @return {Array<{label: string, ip: string, date: string}>} Items.
 */
export function latestItems( entries, skip, limit ) {
	return ( Array.isArray( entries ) ? entries : [] )
		.filter( ( entry ) => entry && ! ( skip || [] ).includes( entry.type ) )
		.slice( 0, limit )
		.map( ( entry ) => ( {
			label: String( entry.type_label || entry.type || '' ),
			ip: String( entry.ip || '' ),
			date: String( entry.date_gmt || '' ),
		} ) );
}

/**
 * "5 minutes ago" in the user's language.
 *
 * @param {string} iso    UTC time (ISO 8601; without an offset it is taken as UTC).
 * @param {number} now    Current time in milliseconds.
 * @param {string} locale BCP 47 locale.
 * @return {string} Relative time, or '' for an unreadable date.
 */
export function relativeTime( iso, now, locale ) {
	const text = String( iso || '' );
	// A UTC time without an offset (5.x-style RFC 3339 from mysql_to_rfc3339())
	// would be read as local time: say it is UTC.
	const then = Date.parse(
		/T\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/.test( text ) ? text + 'Z' : text
	);
	if ( Number.isNaN( then ) ) {
		return '';
	}
	const seconds = Math.max( 0, Math.round( ( now - then ) / 1000 ) );
	const steps = [
		[ 60, 'second', 1 ],
		[ 3600, 'minute', 60 ],
		[ 86400, 'hour', 3600 ],
		[ Infinity, 'day', 86400 ],
	];
	const [ , unit, size ] = steps.find( ( [ limit ] ) => seconds < limit );
	let rtf;
	try {
		rtf = new Intl.RelativeTimeFormat( locale || undefined, {
			numeric: 'auto',
		} );
	} catch {
		rtf = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
	}
	return rtf.format( -Math.round( seconds / size ), unit );
}
