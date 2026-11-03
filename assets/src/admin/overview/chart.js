/**
 * "Threats stopped": the 24 h / 7 d / 30 d totals and the brick chart of
 * the last 30 days. Each column is a day built from bricks; the chosen
 * period is highlighted. Readable with the keyboard (arrow keys, Home,
 * End), with the numbers also available as a table.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Tile, TileHead, cx } from '../app/primitives';
import { day as fmtDay, num, shortDay, signed } from '../data/format';
import { CHART, axisTicks, bandFor, mapChart } from './chart-data';

const RANGES = [ 1, 7, 30 ];

/**
 * Text anchor of an axis label: the first starts, the last ends, the rest centre.
 *
 * @param {number} i Index.
 * @param {number} n Days.
 * @return {string} SVG text-anchor.
 */
const anchorFor = ( i, n ) => {
	if ( i === n - 1 ) {
		return 'end';
	}
	return 0 === i ? 'start' : 'middle';
};

/** @return {Object<number, string>} Period names. */
const rangeLabels = () => ( {
	1: __( 'Last 24 hours', 'zero-spam' ),
	7: __( 'Last 7 days', 'zero-spam' ),
	30: __( 'Last 30 days', 'zero-spam' ),
} );

/** @return {Object<number, string>} "Vs previous …" labels. */
const previousLabels = () => ( {
	1: __( 'Vs previous 24 hours', 'zero-spam' ),
	7: __( 'Vs previous week', 'zero-spam' ),
	30: __( 'Vs previous 30 days', 'zero-spam' ),
} );

/**
 * @param {number} count Threats.
 * @return {string} "63 threats".
 */
const threats = ( count ) =>
	sprintf(
		/* translators: %s: number of threats */
		_n( '%s threat', '%s threats', count, 'zero-spam' ),
		num( count )
	);

/**
 * @param {Object}  props           Props.
 * @param {Object}  props.summaries stats/summary answers by range.
 * @param {Array}   props.days      stats/timeseries days.
 * @param {boolean} props.reduced   Animations are off.
 * @param {number}  props.freshDay  Index of a day that just grew (drop-in), or -1.
 */
export function ThreatsChart( { summaries, days, reduced, freshDay = -1 } ) {
	const [ range, setRange ] = useState( 7 );
	const [ hot, setHot ] = useState( null );
	const [ spoken, setSpoken ] = useState( '' );
	const [ scanning, setScanning ] = useState( false );
	const [ popping, setPopping ] = useState( false );
	const box = useRef( null );
	const svg = useRef( null );
	const labels = rangeLabels();

	const chart = useMemo( () => mapChart( days || [] ), [ days ] );
	const n = chart.columns.length;
	const band = bandFor( range, n || 30, chart.columnWidth );
	const summary = summaries[ range ];
	const total = summary?.total ?? 0;
	const ticks = axisTicks( n );

	// One 1.2 s scan sweep about every 30 s, only while it is on screen.
	useEffect( () => {
		if ( reduced || chart.empty ) {
			return undefined;
		}
		let visible = true;
		const observer =
			'undefined' !== typeof window.IntersectionObserver
				? new window.IntersectionObserver( ( [ entry ] ) => {
						visible = entry.isIntersecting;
					} )
				: null;
		if ( observer && box.current ) {
			observer.observe( box.current );
		}
		const timer = window.setInterval( () => {
			if ( visible && ! document.hidden ) {
				setScanning( true );
				window.setTimeout( () => setScanning( false ), 1300 );
			}
		}, 30000 );
		return () => {
			window.clearInterval( timer );
			observer?.disconnect();
		};
	}, [ reduced, chart.empty ] );

	const choose = ( r ) => {
		setRange( r );
		if ( ! reduced ) {
			setPopping( false );
			window.requestAnimationFrame( () => setPopping( true ) );
			window.setTimeout( () => setPopping( false ), 900 );
		}
	};

	const describe = ( i ) => {
		const col = chart.columns[ i ];
		return col ? `${ fmtDay( col.date ) } · ${ threats( col.value ) }` : '';
	};

	const show = ( i ) => {
		setHot( i );
		setSpoken( describe( i ) );
	};

	const onKey = ( event ) => {
		if ( ! n ) {
			return;
		}
		let i = null === hot ? n - 1 : hot;
		if ( 'ArrowLeft' === event.key ) {
			i = Math.max( 0, i - 1 );
		} else if ( 'ArrowRight' === event.key ) {
			i = Math.min( n - 1, i + 1 );
		} else if ( 'Home' === event.key ) {
			i = 0;
		} else if ( 'End' === event.key ) {
			i = n - 1;
		} else if ( 'Escape' === event.key ) {
			setHot( null );
			return;
		} else {
			return;
		}
		event.preventDefault();
		show( i );
	};

	const onMove = ( event ) => {
		if ( ! svg.current || ! n ) {
			return;
		}
		const r = svg.current.getBoundingClientRect();
		const ux = ( ( event.clientX - r.left ) / r.width ) * CHART.width;
		const i = Math.floor( ( ux - CHART.left ) / chart.columnWidth );
		if ( i >= 0 && i < n ) {
			if ( i !== hot ) {
				setHot( i );
			}
		} else {
			setHot( null );
		}
	};

	// Tooltip position, in the box's pixels.
	let tip = null;
	if ( null !== hot && chart.columns[ hot ] && svg.current && box.current ) {
		const r = svg.current.getBoundingClientRect();
		const b = box.current.getBoundingClientRect();
		const scale = r.width / CHART.width;
		const col = chart.columns[ hot ];
		const left = Math.max(
			70,
			Math.min( b.width - 70, col.center * scale + ( r.left - b.left ) )
		);
		const top = Math.max( 18, col.top * scale + ( r.top - b.top ) - 8 );
		tip = { left, top, text: describe( hot ) };
	}

	const busiest = summary?.busiest_day;
	const perBrick =
		1 === chart.per
			? __( '1 brick = 1 threat', 'zero-spam' )
			: sprintf(
					/* translators: %s: number of threats one brick stands for */
					__( '1 brick = %s threats', 'zero-spam' ),
					num( chart.per )
				);

	const first = chart.columns[ 0 ];
	const last = chart.columns[ n - 1 ];
	const description = chart.empty
		? __( 'No threats were stopped in the last 30 days.', 'zero-spam' )
		: sprintf(
				/* translators: 1: threats per brick, 2: first day, 3: threats on it, 4: threats today, 5: period, 6: threats in the period */
				__(
					'Each column is a day built from bricks of %1$s. %2$s: %3$s. Today: %4$s. %5$s: %6$s stopped.',
					'zero-spam'
				),
				threats( chart.per ),
				fmtDay( first?.date ),
				num( first?.value ),
				num( last?.value ),
				labels[ range ],
				num( total )
			);

	return (
		<Tile span={ 8 } labelledBy="zs-ch-h" className="zs-chart-tile">
			<TileHead
				eyebrow={ __( 'Threats stopped', 'zero-spam' ) }
				id="zs-ch-h"
				title={ __( 'Your wall, last 30 days', 'zero-spam' ) }
				aside={
					! chart.empty && (
						<div className="zs-legend">
							<span>
								<span className="zs-bk" aria-hidden="true" />
								{ perBrick }
							</span>
						</div>
					)
				}
			/>
			<div
				className="zs-stats"
				role="group"
				aria-label={ __( 'Choose a time range', 'zero-spam' ) }
			>
				{ RANGES.map( ( r ) => (
					<button
						key={ r }
						type="button"
						className="zs-stat"
						aria-pressed={ r === range ? 'true' : 'false' }
						onClick={ () => choose( r ) }
					>
						<span className="zs-stat-k">{ labels[ r ] }</span>
						<span className="zs-stat-v">
							{ num( summaries[ r ]?.total ?? 0 ) }
						</span>
					</button>
				) ) }
			</div>

			{ chart.empty ? (
				<div className="zs-chart-empty">
					<div className="zs-ghost-wall" aria-hidden="true">
						<span />
						<span />
						<span />
						<span />
						<span />
					</div>
					<p>
						<b>
							{ __(
								'No threats yet. Your wall starts here.',
								'zero-spam'
							) }
						</b>
						<span className="zs-hint">
							{ __(
								'Each blocked comment, login, sign-up or form adds a brick. Nothing was blocked in the last 30 days.',
								'zero-spam'
							) }
						</span>
					</p>
				</div>
			) : (
				<>
					{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions -- A focusable group read with the arrow keys (WAI-ARIA data chart pattern); the table below is the alternative. */ }
					<div
						className="zs-chartbox"
						ref={ box }
						tabIndex={ 0 }
						role="group"
						aria-label={ __(
							'Daily threats chart. Use the left and right arrow keys to read each day.',
							'zero-spam'
						) }
						onKeyDown={ onKey }
						onFocus={ () => show( null === hot ? n - 1 : hot ) }
						onBlur={ () => setHot( null ) }
						onMouseMove={ onMove }
						onMouseLeave={ () => {
							if (
								box.current &&
								box.current !==
									box.current.ownerDocument.activeElement
							) {
								setHot( null );
							}
						} }
					>
						<svg
							ref={ svg }
							className={ cx(
								'zs-chart',
								scanning && 'zs-scanning'
							) }
							viewBox={ `0 0 ${ CHART.width } ${ CHART.height }` }
							role="img"
							aria-labelledby="zs-ch-t zs-ch-d"
						>
							<title id="zs-ch-t">
								{ __(
									'Threats stopped per day, last 30 days',
									'zero-spam'
								) }
							</title>
							<desc id="zs-ch-d">{ description }</desc>
							<defs>
								<linearGradient
									id="zs-scan-g"
									x1="0"
									x2="1"
									y1="0"
									y2="0"
								>
									<stop offset="0" className="zs-sc0" />
									<stop offset=".55" className="zs-sc1" />
									<stop offset="1" className="zs-sc0" />
								</linearGradient>
							</defs>
							<rect
								className="zs-band"
								x={ band.x }
								width={ band.width }
								y={ CHART.top - 24 }
								height={ chart.base - CHART.top + 26 }
								rx="8"
							/>
							<text
								className="zs-bandlbl"
								x={ CHART.width - CHART.right }
								y={ CHART.top - 11 }
								textAnchor="end"
							>
								{ `${ labels[ range ] } · ${ num( total ) }` }
							</text>
							{ chart.grid.map( ( g ) => (
								<g key={ g.value }>
									<line
										className="zs-grid"
										x1={ CHART.left }
										x2={ CHART.width - CHART.right }
										y1={ g.y }
										y2={ g.y }
									/>
									<text
										x={ CHART.left - 6 }
										y={ g.y + 4 }
										textAnchor="end"
									>
										{ num( g.value ) }
									</text>
								</g>
							) ) }
							<line
								className="zs-grid"
								x1={ CHART.left }
								x2={ CHART.width - CHART.right }
								y1={ chart.base }
								y2={ chart.base }
							/>
							{ chart.columns.map( ( col ) => (
								<g
									key={ col.date }
									className={ cx(
										'zs-col',
										col.index >= n - range && 'zs-inr',
										hot === col.index && 'zs-hot',
										popping &&
											col.index >= n - range &&
											'zs-pop'
									) }
									style={ {
										'--zs-d': `${ ( col.index - ( n - range ) ) * 18 }ms`,
									} }
								>
									{ col.bricks.map( ( b, k ) => (
										<rect
											key={ k }
											className={ cx(
												`zs-k${ b.shade }`,
												col.index === freshDay &&
													k ===
														col.bricks.length - 1 &&
													'zs-fresh'
											) }
											x={ col.x }
											y={ b.y }
											width={ col.width }
											height={ b.height }
											rx="2"
										/>
									) ) }
								</g>
							) ) }
							{ last && last.value > 0 && (
								<text
									className="zs-endlbl"
									x={ last.center }
									y={ last.top - 7 }
									textAnchor="middle"
								>
									{ num( last.value ) }
								</text>
							) }
							{ ticks.map( ( i ) => (
								<text
									key={ i }
									x={ chart.columns[ i ]?.center }
									y={ CHART.height - 8 }
									textAnchor={ anchorFor( i, n ) }
								>
									{ i === n - 1
										? __( 'Today', 'zero-spam' )
										: shortDay( chart.columns[ i ]?.date ) }
								</text>
							) ) }
							<rect
								className="zs-scan"
								x={ CHART.left - 56 }
								y={ CHART.top - 6 }
								width="56"
								height={ chart.base - CHART.top + 6 }
								fill="url(#zs-scan-g)"
								style={ {
									'--zs-sweep': `${ CHART.width - CHART.right - CHART.left + 56 }px`,
								} }
							/>
						</svg>
						{ tip && (
							<div
								className="zs-tip"
								style={ { left: tip.left, top: tip.top } }
								aria-hidden="true"
							>
								{ tip.text }
							</div>
						) }
					</div>
					<div className="zs-axis-m" aria-hidden="true">
						<span>{ shortDay( first?.date ) }</span>
						<span>
							{ shortDay(
								chart.columns[ Math.floor( n / 2 ) ]?.date
							) }
						</span>
						<span>{ __( 'Today', 'zero-spam' ) }</span>
					</div>
				</>
			) }
			<p className="zs-sr-only" aria-live="polite">
				{ spoken }
			</p>

			<div className="zs-chart-foot">
				<div>
					<span>{ __( 'Daily average', 'zero-spam' ) }</span>
					<b>{ num( Math.round( summary?.daily_average ?? 0 ) ) }</b>
				</div>
				<div>
					<span>{ __( 'Busiest day', 'zero-spam' ) }</span>
					<b>
						{ busiest
							? `${ fmtDay( busiest.date ) } · ${ num( busiest.count ) }`
							: '—' }
					</b>
				</div>
				<div>
					<span>{ previousLabels()[ range ] }</span>
					<b>{ summary ? signed( summary.delta ) : '—' }</b>
				</div>
			</div>
			{ ! chart.empty && (
				<details className="zs-data">
					<summary>
						{ __( 'Show the numbers as a table', 'zero-spam' ) }
					</summary>
					<div className="zs-tbl-wrap">
						<table className="zs-t">
							<caption className="zs-sr-only">
								{ __( 'Threats stopped per day', 'zero-spam' ) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Day', 'zero-spam' ) }
									</th>
									<th scope="col" className="zs-num">
										{ __( 'Threats stopped', 'zero-spam' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ chart.columns.map( ( col ) => (
									<tr key={ col.date }>
										<td>{ fmtDay( col.date ) }</td>
										<td className="zs-mono zs-num">
											{ num( col.value ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</details>
			) }
		</Tile>
	);
}
