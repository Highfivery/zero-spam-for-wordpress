/**
 * Maps `stats/timeseries` and the activity facets to what the Reports tab
 * draws (pure; unit tested). One period drives every card.
 */

/**
 * Internal dependencies
 */
import { NS } from '../data/api';
import { buildPath } from './query';

/** The periods, in days. The last is the default. */
export const PERIODS = [ 7, 14, 30 ];
export const DEFAULT_PERIOD = 30;

/** Countries listed in "By location". */
export const TOP_COUNTRIES = 9;

/**
 * The paths of a period. The 30-day series is the Overview's path (and the
 * one Admin_App preloads); the breakdown leaves out entries that are not
 * detections.
 *
 * @param {number} days Period.
 * @return {{series: string, facets: string}} Paths.
 */
export function reportPaths( days ) {
	const period = PERIODS.includes( days ) ? days : DEFAULT_PERIOD;
	return {
		series:
			30 === period
				? `${ NS }/stats/timeseries`
				: `${ NS }/stats/timeseries?range=${ period }`,
		facets: buildPath( 'activity', [
			[ 'facets', '1' ],
			[ 'detections_only', '1' ],
			[ 'days', String( period ) ],
		] ),
	};
}

/**
 * Detections in a series.
 *
 * @param {Object|undefined} series stats/timeseries answer.
 * @return {number} Total.
 */
export const seriesTotal = ( series ) =>
	( series?.days || [] ).reduce(
		( sum, day ) => sum + ( Number( day.total ) || 0 ),
		0
	);

/**
 * "By type": every type with detections, most first.
 *
 * @param {Object|undefined} series stats/timeseries answer.
 * @return {{rows: Array<{key: string, label: string, count: number}>, max: number, total: number}} Bars.
 */
export function typeBars( series ) {
	const rows = ( series?.types || [] )
		.map( ( t ) => ( {
			key: String( t.type ),
			label: String( t.label || t.type ),
			count: Math.max( 0, Number( t.count ) || 0 ),
		} ) )
		.filter( ( t ) => t.count > 0 )
		.sort(
			( a, b ) => b.count - a.count || a.label.localeCompare( b.label )
		);
	return {
		rows,
		max: Math.max( 1, ...rows.map( ( r ) => r.count ) ),
		total: rows.reduce( ( sum, r ) => sum + r.count, 0 ),
	};
}

/**
 * "By location": the countries with most detections.
 *
 * @param {Object|undefined} facets Activity facets.
 * @param {number}           limit  Countries listed.
 * @return {{rows: Array<{code: string, name: string, count: number}>, max: number, more: number}} Bars; more = countries not listed.
 */
export function countryBars( facets, limit = TOP_COUNTRIES ) {
	const all = ( facets?.country || [] )
		.map( ( c ) => ( {
			code: String( c.value ).toUpperCase(),
			name: String( c.label || c.value ),
			count: Math.max( 0, Number( c.count ) || 0 ),
		} ) )
		.filter( ( c ) => c.count > 0 );
	const rows = all.slice( 0, limit );
	return {
		rows,
		max: Math.max( 1, ...rows.map( ( r ) => r.count ) ),
		more: Math.max( 0, all.length - rows.length ),
	};
}

/** Sorts of "Most detections". */
export const TOP_SORTS = [ 'count', 'ip', 'country' ];

/**
 * "Most detections": the addresses with most detections.
 *
 * @param {Object|undefined}       facets Activity facets.
 * @param {string}                 sort   count, ip or country.
 * @param {Object<string, string>} names  Country names by code.
 * @return {Array<{ip: string, country: string|null, count: number, blocked: boolean}>} Rows.
 */
export function topIps( facets, sort = 'count', names = {} ) {
	const rows = ( facets?.ip || [] ).map( ( r ) => ( {
		ip: String( r.value ),
		country: r.country ? String( r.country ).toUpperCase() : null,
		count: Math.max( 0, Number( r.count ) || 0 ),
		blocked: true === r.blocked,
	} ) );
	const name = ( r ) => names[ r.country ] || r.country || '￿';
	const by = {
		count: ( a, b ) => b.count - a.count || a.ip.localeCompare( b.ip ),
		ip: ( a, b ) => a.ip.localeCompare( b.ip, 'en', { numeric: true } ),
		country: ( a, b ) =>
			name( a ).localeCompare( name( b ) ) || b.count - a.count,
	};
	return rows.sort( by[ sort ] || by.count );
}

/**
 * Country names by code, from the facets.
 *
 * @param {Object|undefined} facets Activity facets.
 * @return {Object<string, string>} Names.
 */
export const countryNames = ( facets ) =>
	Object.fromEntries(
		( facets?.country || [] ).map( ( c ) => [
			String( c.value ).toUpperCase(),
			String( c.label || c.value ),
		] )
	);

/**
 * An hour of the day in the site's language ("3 PM", "15").
 *
 * @param {number} hour   0 to 23.
 * @param {string} locale Locale.
 * @return {string} Label.
 */
export function hourLabel( hour, locale = 'en-US' ) {
	const date = new Date( Date.UTC( 2020, 0, 1, hour ) );
	try {
		return date.toLocaleTimeString( locale, {
			hour: 'numeric',
			timeZone: 'UTC',
		} );
	} catch {
		return String( hour );
	}
}

/**
 * "By the hour": 24 columns and the busiest hours.
 *
 * @param {Object|undefined} series stats/timeseries answer.
 * @param {number}           top    Busiest hours listed.
 * @return {{columns: Array<{key: number, value: number}>, max: number, busiest: Array<{hour: number, count: number}>, total: number}} Data.
 */
export function hourData( series, top = 5 ) {
	const hours = Array.from( { length: 24 }, ( _, i ) =>
		Math.max( 0, Number( series?.hours?.[ i ] ) || 0 )
	);
	const busiest = hours
		.map( ( count, hour ) => ( { hour, count } ) )
		.filter( ( h ) => h.count > 0 )
		.sort( ( a, b ) => b.count - a.count || a.hour - b.hour )
		.slice( 0, top );
	return {
		columns: hours.map( ( value, key ) => ( { key, value } ) ),
		max: Math.max( 1, ...hours ),
		busiest,
		total: hours.reduce( ( a, b ) => a + b, 0 ),
	};
}

/**
 * "Enhanced vs Basic": each day's detections split into what the Basic
 * checks stopped and what only the Zero Spam network caught.
 *
 * @param {Object|undefined} series stats/timeseries answer.
 * @return {{columns: Array<{key: string, basic: number, network: number, value: number}>, max: number, basic: number, network: number}} Data.
 */
export function enhancedColumns( series ) {
	const columns = ( series?.days || [] ).map( ( day ) => {
		const value = Math.max( 0, Number( day.total ) || 0 );
		const network = Math.min(
			value,
			Math.max( 0, Number( day.network_only ) || 0 )
		);
		return { key: day.date, basic: value - network, network, value };
	} );
	return {
		columns,
		max: Math.max( 1, ...columns.map( ( c ) => c.value ) ),
		basic: columns.reduce( ( sum, c ) => sum + c.basic, 0 ),
		network: columns.reduce( ( sum, c ) => sum + c.network, 0 ),
	};
}

/**
 * Which column labels to print on an axis: about five, the last one always.
 *
 * @param {number} n    Columns.
 * @param {number} step Columns between two labels.
 * @return {Set<number>} Indexes.
 */
export function axisLabels( n, step ) {
	const out = new Set();
	for ( let i = 0; i < n; i += step ) {
		out.add( i );
	}
	return out;
}

/**
 * A bar's width in percent of the longest one.
 *
 * @param {number} value Value.
 * @param {number} max   Longest bar's value.
 * @return {string} CSS width.
 */
export const barWidth = ( value, max ) =>
	`${ Math.max( 0, Math.min( 100, ( value / Math.max( 1, max ) ) * 100 ) ).toFixed( 1 ) }%`;

/**
 * The column a key moves to.
 *
 * @param {string}      key     Key.
 * @param {number|null} current Column shown (null: none yet).
 * @param {number}      n       Columns.
 * @return {number|null} Column, or null when the key does not move.
 */
export function keyStep( key, current, n ) {
	const i = null === current ? n - 1 : current;
	switch ( key ) {
		case 'ArrowLeft':
			return Math.max( 0, i - 1 );
		case 'ArrowRight':
			return Math.min( n - 1, i + 1 );
		case 'Home':
			return 0;
		case 'End':
			return n - 1;
		default:
			return null;
	}
}
