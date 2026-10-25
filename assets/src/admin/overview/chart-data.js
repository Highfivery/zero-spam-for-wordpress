/**
 * Maps `stats/timeseries` and `stats/summary` to what the Overview draws
 * (pure; unit tested): the brick chart's columns, its scale, the axis
 * labels, and the "This week" buckets.
 */

/** The chart's drawing box, from the prototype (viewBox units). */
export const CHART = {
	width: 720,
	height: 236,
	left: 30,
	right: 6,
	top: 30,
	bottom: 26,
	minRows: 14,
};

/** Brick values the scale may use, smallest first. */
const STEPS = [ 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000 ];

/**
 * How many detections one brick stands for: the smallest step that keeps
 * the busiest day within 13 bricks (the prototype's 1 brick = 5 at 63/day).
 *
 * @param {number} max Busiest day's count.
 * @return {number} Detections per brick.
 */
export function brickValue( max ) {
	const busiest = Math.max( 0, Number( max ) || 0 );
	for ( const step of STEPS ) {
		if ( Math.ceil( busiest / step ) <= 13 ) {
			return step;
		}
	}
	let step = STEPS[ STEPS.length - 1 ];
	while ( Math.ceil( busiest / step ) > 13 ) {
		step *= 10;
	}
	return step;
}

/**
 * The chart's columns and scale.
 *
 * @param {Array<{date: string, total: number}>} days Days, oldest first.
 * @return {Object} { per, rows, brickHeight, base, columnWidth, columns, grid, max, empty }.
 */
export function mapChart( days ) {
	const list = Array.isArray( days ) ? days : [];
	const totals = list.map( ( day ) =>
		Math.max( 0, Number( day.total ) || 0 )
	);
	const max = totals.length ? Math.max( ...totals ) : 0;
	const per = brickValue( max );
	const rows = Math.max( CHART.minRows, Math.ceil( max / per ) + 1 );
	const base = CHART.height - CHART.bottom;
	const brickHeight = ( base - CHART.top ) / rows;
	const n = Math.max( 1, list.length );
	const columnWidth = ( CHART.width - CHART.left - CHART.right ) / n;

	const columns = list.map( ( day, i ) => {
		const value = totals[ i ];
		const full = Math.floor( value / per );
		const rest = value % per;
		const x = CHART.left + i * columnWidth + 1.6;
		const width = columnWidth - 3.2;
		const bricks = [];

		for ( let k = 0; k < full; k++ ) {
			bricks.push( {
				shade: Math.min( 4, Math.floor( k / 3 ) ),
				y: base - ( k + 1 ) * brickHeight + 1,
				height: brickHeight - 2,
			} );
		}
		if ( rest ) {
			const height = ( ( brickHeight - 2 ) * rest ) / per;
			bricks.push( {
				shade: Math.min( 4, Math.floor( full / 3 ) ),
				y: base - full * brickHeight - height,
				height,
				partial: true,
			} );
		}

		return {
			index: i,
			date: day.date,
			value,
			x,
			width,
			center: CHART.left + i * columnWidth + columnWidth / 2,
			top: base - ( value / per ) * brickHeight,
			bricks,
		};
	} );

	// A grid line every four bricks.
	const grid = [];
	for ( let v = per * 4; v < rows * per; v += per * 4 ) {
		grid.push( { value: v, y: base - ( v / per ) * brickHeight } );
	}

	return {
		per,
		rows,
		brickHeight,
		base,
		columnWidth,
		columns,
		grid,
		max,
		empty: 0 === max,
	};
}

/**
 * The highlighted band behind the last `range` days.
 *
 * @param {number} range       Days (1, 7 or 30).
 * @param {number} n           Days in the chart.
 * @param {number} columnWidth Column width.
 * @return {{x: number, width: number}} Band.
 */
export function bandFor( range, n, columnWidth ) {
	const r = Math.min( Math.max( 1, range ), n );
	return {
		x: CHART.left + ( n - r ) * columnWidth - 3,
		width: r * columnWidth + 3,
	};
}

/**
 * Indexes of the days labelled under the chart: first, three between, last.
 *
 * @param {number} n Days.
 * @return {number[]} Indexes.
 */
export function axisTicks( n ) {
	if ( n <= 1 ) {
		return [ 0 ];
	}
	const last = n - 1;
	const ticks = new Set( [ 0, last ] );
	[ 0.27, 0.5, 0.75 ].forEach( ( f ) => {
		const i = Math.round( last * f );
		if ( i > 0 && i < last ) {
			ticks.add( i );
		}
	} );
	return [ ...ticks ].sort( ( a, b ) => a - b );
}

/**
 * The busiest of a list of days (the latest of equally busy ones).
 *
 * @param {Array<{date: string, total: number}>} days Days.
 * @return {{date: string, total: number}|null} Busiest day.
 */
export function busiestDay( days ) {
	let best = null;
	( days || [] ).forEach( ( day ) => {
		if ( day.total > 0 && ( ! best || day.total >= best.total ) ) {
			best = day;
		}
	} );
	return best;
}

/** "This week" buckets, in the prototype's order. */
export const WEEK_BUCKETS = [
	'comments',
	'login',
	'registration',
	'forms',
	'blocked',
];

/**
 * Groups detection types into the "This week" buckets.
 *
 * @param {Array<{type: string, count: number}>} byType summary.by_type.
 * @return {Array<{bucket: string, count: number}>} Buckets in order (empty ones included).
 */
export function weekBuckets( byType ) {
	const counts = Object.fromEntries( WEEK_BUCKETS.map( ( b ) => [ b, 0 ] ) );
	( byType || [] ).forEach( ( { type, count } ) => {
		let bucket = 'forms';
		if ( 'comment' === type ) {
			bucket = 'comments';
		} else if ( 'login' === type ) {
			bucket = 'login';
		} else if ( 'registration' === type ) {
			bucket = 'registration';
		} else if ( 'blocked' === type ) {
			bucket = 'blocked';
		}
		counts[ bucket ] += Number( count ) || 0;
	} );
	return WEEK_BUCKETS.map( ( bucket ) => ( {
		bucket,
		count: counts[ bucket ],
	} ) );
}

/**
 * Adds live detections to the time series' last days (so the chart grows
 * without asking the server again).
 *
 * @param {Array<{date: string, total: number}>} days  Days.
 * @param {Array<{date: string}>}                items New entries (site-local ISO dates).
 * @return {Array} New days.
 */
export function addToDays( days, items ) {
	if ( ! Array.isArray( days ) || ! days.length ) {
		return days;
	}
	const add = {};
	const network = {};
	( items || [] ).forEach( ( item ) => {
		const day = String( item.date || '' ).slice( 0, 10 );
		add[ day ] = ( add[ day ] || 0 ) + 1;
		if ( isNetworkOnly( item ) ) {
			network[ day ] = ( network[ day ] || 0 ) + 1;
		}
	} );
	return days.map( ( day ) =>
		add[ day.date ]
			? {
					...day,
					total: day.total + add[ day.date ],
					network_only:
						( day.network_only || 0 ) +
						( network[ day.date ] || 0 ),
				}
			: day
	);
}

/**
 * The next milestone above a total: 100, 250, 500, 1,000, 2,500, 5,000 …
 *
 * @param {number} total Threats stopped.
 * @return {{reached: number, next: number}} Last milestone reached (0 if none) and the next one.
 */
export function milestones( total ) {
	const marks = [];
	for ( let m = 100; m <= 1e9; m *= 10 ) {
		marks.push( m, m * 2.5, m * 5 );
	}
	let reached = 0;
	let next = marks[ 0 ];
	for ( const m of marks ) {
		if ( total >= m ) {
			reached = m;
		} else {
			next = m;
			break;
		}
	}
	return { reached, next };
}

/** Failed-check key of the Zero Spam network (Stats_Controller::NETWORK_FAILED). */
export const NETWORK_FAILED = 'zero_spam';

/**
 * Whether a live entry is a detection only the network made.
 *
 * @param {{type: string, failed: ?string}} item Activity entry.
 * @return {boolean} Network-only.
 */
export const isNetworkOnly = ( item ) =>
	'blocked' === item?.type && NETWORK_FAILED === item?.failed;

/**
 * What Enhanced added in a period, from stats/summary.
 *
 * @param {Object|undefined} summary stats/summary answer.
 * @return {{network: number, basic: number, total: number, previous: number, delta: number, share: number, plus: number}} Figures; share and plus are whole percents.
 */
export function networkFigures( summary ) {
	const total = Math.max( 0, Number( summary?.total ) || 0 );
	const network = Math.min(
		total,
		Math.max( 0, Number( summary?.network_only?.total ) || 0 )
	);
	const basic = total - network;
	const previous = Math.max(
		0,
		Number( summary?.network_only?.previous_total ) || 0
	);
	return {
		network,
		basic,
		total,
		previous,
		delta: network - previous,
		share: total > 0 ? Math.round( ( network / total ) * 100 ) : 0,
		plus: basic > 0 ? Math.round( ( network / basic ) * 100 ) : 0,
	};
}

/**
 * The days' network-only counts, oldest first.
 *
 * @param {Array<{network_only?: number}>} days stats/timeseries days.
 * @return {number[]} Counts.
 */
export const networkSeries = ( days ) =>
	( days || [] ).map( ( day ) =>
		Math.max( 0, Number( day.network_only ) || 0 )
	);

/**
 * A sparkline's line and area paths in a w × h box (2px inset top and bottom).
 *
 * @param {number[]} values Values, oldest first.
 * @param {number}   w      Width.
 * @param {number}   h      Height.
 * @return {{line: string, area: string}} SVG path data ('' for fewer than two values).
 */
export function sparkPaths( values, w, h ) {
	if ( ! Array.isArray( values ) || values.length < 2 ) {
		return { line: '', area: '' };
	}
	const max = Math.max( 1, ...values );
	const n = values.length;
	const points = values.map( ( v, i ) => [
		( i / ( n - 1 ) ) * w,
		h - 2 - ( v / max ) * ( h - 4 ),
	] );
	const line = points
		.map(
			( [ x, y ], i ) =>
				`${ i ? 'L' : 'M' }${ x.toFixed( 1 ) } ${ y.toFixed( 1 ) }`
		)
		.join( '' );
	return { line, area: `${ line }L${ w } ${ h }L0 ${ h }Z` };
}
