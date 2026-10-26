/**
 * Reports (prototype §5): one period (7, 14 or 30 days) for every card.
 * Enhanced vs Basic (Enhanced only), By type and By location side by side
 * (a country list replaces the 5.x heat map), Most detections, and By the
 * hour. Bars are bricks; the charts are read with the keyboard and also
 * shown as tables.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	createInterpolateElement,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp, useMedia } from '../app/context';
import { Badge, Button, ExtLink, Tile, TileHead } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { BlockForm } from '../app/block-form';
import { NS, boot, request } from '../data/api';
import { day, num, shortDay } from '../data/format';
import { utm } from '../data/routes';
import { ColumnChart } from './column-chart';
import { Country } from './parts';
import { TABLE_QUERY } from './query';
import {
	DEFAULT_PERIOD,
	PERIODS,
	TOP_SORTS,
	barWidth,
	countryBars,
	countryNames,
	enhancedColumns,
	hourData,
	hourLabel,
	reportPaths,
	seriesTotal,
	topIps,
	typeBars,
} from './reports-data';
import { runUndoable } from './undo';
import { useQuery } from './use-query';

const locale = () =>
	( typeof document !== 'undefined' && document.documentElement.lang ) ||
	'en-US';

/**
 * @param {number} count Detections.
 * @return {string} "12 detections".
 */
const detections = ( count ) =>
	sprintf(
		/* translators: %s: number of detections */
		_n( '%s detection', '%s detections', count, 'zero-spam' ),
		num( count )
	);

/**
 * Brick bars with a label, a track and the number.
 *
 * @param {Object}                          props       Props.
 * @param {Array}                           props.rows  Rows ({ key, count }).
 * @param {number}                          props.max   Longest bar's value.
 * @param {(...args: unknown[]) => unknown} props.label ( row ) => label.
 */
function Bars( { rows, max, label } ) {
	return (
		<ul className="zs-wk zs-rep-bars">
			{ rows.map( ( row ) => (
				<li key={ row.key || row.code } className="zs-wk-row">
					<span className="zs-wk-k">{ label( row ) }</span>
					<span className="zs-wk-track" aria-hidden="true">
						<span
							className="zs-wk-bar"
							style={ { width: barWidth( row.count, max ) } }
						/>
					</span>
					<span className="zs-mono zs-num">{ num( row.count ) }</span>
				</li>
			) ) }
		</ul>
	);
}

/**
 * An empty card.
 *
 * @param {Object} props      Props.
 * @param {string} props.text What is empty.
 */
const Empty = ( { text } ) => (
	<div className="zs-empty">
		<div className="zs-ghost-wall zs-ghost-sm" aria-hidden="true">
			<span />
			<span />
			<span />
		</div>
		<span className="zs-hint">{ text }</span>
	</div>
);

/**
 * The Reports tab.
 */
export function Reports() {
	const { enhanced } = useApp();
	const { toast } = useFeedback();
	const wide = useMedia( TABLE_QUERY );
	const [ period, setPeriod ] = useState( DEFAULT_PERIOD );
	const [ topSort, setTopSort ] = useState( 'count' );
	const [ blockFor, setBlockFor ] = useState( null );
	const returnTo = useRef( null );
	const paths = reportPaths( period );
	const series = useQuery( paths.series );
	const facets = useQuery( paths.facets );

	const total = seriesTotal( series.data );
	const loaded = undefined !== series.data && undefined !== facets.data;
	const per = sprintf(
		/* translators: %s: number of days */
		__( 'Last %s days', 'zero-spam' ),
		num( period )
	);

	const types = useMemo( () => typeBars( series.data ), [ series.data ] );
	const countries = useMemo(
		() => countryBars( facets.data ),
		[ facets.data ]
	);
	const names = useMemo( () => countryNames( facets.data ), [ facets.data ] );
	const top = useMemo(
		() => topIps( facets.data, topSort, names ),
		[ facets.data, topSort, names ]
	);
	const hours = useMemo( () => hourData( series.data ), [ series.data ] );
	const ev = useMemo( () => enhancedColumns( series.data ), [ series.data ] );

	const lab = ( hour ) => hourLabel( hour, locale() );
	const shift = boot.nowLocal - Date.now();

	const onSaved = ( saved, created ) => {
		const ip = saved.ip || saved.key;
		setBlockFor( null );
		runUndoable( {
			run: () => Promise.resolve( saved ),
			undo: created
				? () =>
						request( {
							path: `${ NS }/rules/blocks/${ saved.id }`,
							method: 'DELETE',
						} )
				: undefined,
			request,
			toast,
			refresh: () => facets.reload(),
			messages: {
				done: () => {
					if ( ! created ) {
						return __( 'Block saved', 'zero-spam' );
					}
					if ( 'permanent' === saved.type ) {
						return sprintf(
							/* translators: %s: IP address */ __(
								'%s blocked permanently',
								'zero-spam'
							),
							ip
						);
					}
					return sprintf(
						/* translators: 1: IP address, 2: date the block ends */
						__( '%1$s blocked until %2$s', 'zero-spam' ),
						ip,
						day( new Date( Date.parse( saved.end ) + shift ) )
					);
				},
				undone: () =>
					sprintf(
						/* translators: %s: IP address */ __(
							'%s is no longer blocked',
							'zero-spam'
						),
						ip
					),
				failed: () => '',
				undoFailed: () =>
					__(
						'The block could not be removed. Please try again.',
						'zero-spam'
					),
			},
		} );
	};

	const blockButton = ( row ) => (
		<Button
			variant="ghost"
			size="sm"
			data-zs-block={ row.ip }
			aria-label={
				row.blocked
					? sprintf(
							/* translators: %s: IP address */ __(
								'Edit block for %s',
								'zero-spam'
							),
							row.ip
						)
					: sprintf(
							/* translators: %s: IP address */ __(
								'Block IP %s',
								'zero-spam'
							),
							row.ip
						)
			}
			onClick={ () => {
				returnTo.current = row.ip;
				setBlockFor( row );
			} }
		>
			{ row.blocked
				? __( 'Edit block', 'zero-spam' )
				: __( 'Block IP', 'zero-spam' ) }
		</Button>
	);

	const blockedBadge = ( row ) =>
		row.blocked && (
			<Badge tone="danger">{ __( 'Blocked', 'zero-spam' ) }</Badge>
		);

	const topNames = {
		count: __( 'Most detections first', 'zero-spam' ),
		ip: __( 'IP address', 'zero-spam' ),
		country: __( 'Country A–Z', 'zero-spam' ),
	};

	const busiest = hours.busiest;

	return (
		<div className="zs-reports">
			<div className="zs-rep-bar">
				<span className="zs-lbl-strong" id="zs-rp-l">
					{ __( 'Period', 'zero-spam' ) }
				</span>
				<div className="zs-seg" role="group" aria-labelledby="zs-rp-l">
					{ PERIODS.map( ( d ) => (
						<button
							key={ d }
							type="button"
							aria-pressed={ d === period ? 'true' : 'false' }
							onClick={ () => setPeriod( d ) }
						>
							{ sprintf(
								/* translators: %s: number of days */
								__( '%s days', 'zero-spam' ),
								num( d )
							) }
						</button>
					) ) }
				</div>
				<span className="zs-hint" aria-live="polite">
					{ loaded &&
						sprintf(
							/* translators: 1: number of days, 2: number of detections */
							_n(
								'Every card shows the last %1$s days · %2$s detection',
								'Every card shows the last %1$s days · %2$s detections',
								total,
								'zero-spam'
							),
							num( period ),
							num( total )
						) }
				</span>
			</div>

			{ ( series.error || facets.error ) && (
				<p className="zs-notice zs-notice-err" role="alert">
					{ __(
						'The reports could not be loaded. Reload the page to try again.',
						'zero-spam'
					) }
				</p>
			) }

			<div
				className="zs-wall zs-rep"
				aria-busy={
					series.loading || facets.loading ? 'true' : undefined
				}
			>
				{ enhanced && (
					<Tile span={ 12 } labelledBy="zs-rp-ev">
						<TileHead
							eyebrow={ per }
							id="zs-rp-ev"
							title={ __( 'Enhanced vs Basic', 'zero-spam' ) }
							aside={
								<span className="zs-legend">
									<span>
										<span
											className="zs-bk"
											aria-hidden="true"
										/>
										{ __( 'Basic checks', 'zero-spam' ) }
									</span>
									<span>
										<span
											className="zs-bk zs-netbk"
											aria-hidden="true"
										/>
										{ __(
											'Caught only by the network',
											'zero-spam'
										) }
									</span>
								</span>
							}
						/>
						{ ev.columns.length && total > 0 ? (
							<ColumnChart
								id="zs-evc"
								className="zs-evchart"
								label={ __(
									'Enhanced vs Basic, per day',
									'zero-spam'
								) }
								summary={ sprintf(
									/* translators: 1: period, 2: detections the Basic checks made, 3: detections only the network made */
									__(
										'%1$s: the Basic checks stopped %2$s and the Zero Spam network caught %3$s more.',
										'zero-spam'
									),
									per,
									num( ev.basic ),
									num( ev.network )
								) }
								columns={ ev.columns }
								describe={ ( c ) =>
									sprintf(
										/* translators: 1: day, 2: detections by the Basic checks, 3: detections only the network made */
										__(
											'%1$s: %2$s Basic + %3$s network',
											'zero-spam'
										),
										day( c.key ),
										num( c.basic ),
										num( c.network )
									)
								}
								renderColumn={ ( c ) => (
									<>
										<span
											className="zs-evn"
											style={ {
												height: barWidth(
													c.network,
													ev.max
												),
											} }
										/>
										<span
											className="zs-evb"
											style={ {
												height: barWidth(
													c.basic,
													ev.max
												),
											} }
										/>
									</>
								) }
								axis={ ( c, i ) =>
									i % ( period > 14 ? 5 : 2 )
										? ''
										: shortDay( c.key )
								}
								caption={ sprintf(
									/* translators: %s: period, such as Last 30 days */
									__(
										'Detections per day by the Basic checks and only by the network, %s',
										'zero-spam'
									),
									per.toLowerCase()
								) }
								head={ [
									__( 'Day', 'zero-spam' ),
									__( 'Basic checks', 'zero-spam' ),
									__( 'Only the network', 'zero-spam' ),
								] }
								cells={ ( c ) => [
									day( c.key ),
									num( c.basic ),
									num( c.network ),
								] }
							/>
						) : (
							<Empty
								text={ __(
									'No detections in this period.',
									'zero-spam'
								) }
							/>
						) }
					</Tile>
				) }

				<Tile span={ 6 } labelledBy="zs-rp-type">
					<TileHead
						eyebrow={ per }
						id="zs-rp-type"
						title={ __( 'By type', 'zero-spam' ) }
					/>
					{ types.rows.length ? (
						<>
							<Bars
								rows={ types.rows }
								max={ types.max }
								label={ ( r ) => r.label }
							/>
							<p className="zs-hint zs-rep-f">
								{ sprintf(
									/* translators: 1: number of types, 2: number of detections */
									_n(
										'%1$s type · %2$s',
										'%1$s types · %2$s',
										types.rows.length,
										'zero-spam'
									),
									num( types.rows.length ),
									detections( types.total )
								) }
							</p>
						</>
					) : (
						<Empty
							text={ __(
								'No detections in this period.',
								'zero-spam'
							) }
						/>
					) }
				</Tile>

				<Tile span={ 6 } labelledBy="zs-rp-loc">
					<TileHead
						eyebrow={ per }
						id="zs-rp-loc"
						title={ __( 'By location', 'zero-spam' ) }
					/>
					{ countries.rows.length ? (
						<>
							<Bars
								rows={ countries.rows }
								max={ countries.max }
								label={ ( r ) => (
									<Country code={ r.code } name={ r.name } />
								) }
							/>
							<p className="zs-hint zs-rep-f">
								{ countries.more > 0
									? sprintf(
											/* translators: %s: number of countries listed */
											__(
												'Top %s countries · the rest are in the log',
												'zero-spam'
											),
											num( countries.rows.length )
										)
									: __(
											'Every country with detections',
											'zero-spam'
										) }
							</p>
						</>
					) : (
						<Empty
							text={ __(
								'No locations were recorded in this period.',
								'zero-spam'
							) }
						/>
					) }
				</Tile>

				<Tile span={ 12 } labelledBy="zs-rp-top">
					<TileHead
						eyebrow={ per }
						id="zs-rp-top"
						title={ __( 'Most detections', 'zero-spam' ) }
					/>
					{ top.length ? (
						<>
							{ ! wide && (
								<div className="zs-cardbar">
									<span className="zs-grow" />
									<label
										className="zs-sr-only"
										htmlFor="zs-top-sort"
									>
										{ __( 'Sort addresses', 'zero-spam' ) }
									</label>
									<select
										id="zs-top-sort"
										className="zs-select zs-select-auto"
										value={ topSort }
										onChange={ ( e ) =>
											setTopSort( e.target.value )
										}
									>
										{ TOP_SORTS.map( ( s ) => (
											<option key={ s } value={ s }>
												{ sprintf(
													/* translators: %s: sort order */
													__(
														'Sort: %s',
														'zero-spam'
													),
													topNames[ s ]
												) }
											</option>
										) ) }
									</select>
									<Badge>
										{ sprintf(
											/* translators: %s: number of addresses */
											_n(
												'%s address',
												'%s addresses',
												top.length,
												'zero-spam'
											),
											num( top.length )
										) }
									</Badge>
								</div>
							) }
							{ wide ? (
								<div className="zs-tbl-wrap">
									<table className="zs-t zs-toptbl">
										<caption className="zs-sr-only">
											{ sprintf(
												/* translators: %s: period, such as last 30 days */
												__(
													'IP addresses with the most detections, %s',
													'zero-spam'
												),
												per.toLowerCase()
											) }
										</caption>
										<thead>
											<tr>
												<th scope="col">
													{ __(
														'IP address',
														'zero-spam'
													) }
												</th>
												<th scope="col">
													{ __(
														'Country',
														'zero-spam'
													) }
												</th>
												<th
													scope="col"
													className="zs-num"
												>
													{ __(
														'Detections',
														'zero-spam'
													) }
												</th>
												<th
													scope="col"
													className="zs-act"
												>
													<span className="zs-sr-only">
														{ __(
															'Action',
															'zero-spam'
														) }
													</span>
												</th>
											</tr>
										</thead>
										<tbody>
											{ top.map( ( row ) => (
												<tr key={ row.ip }>
													<td className="zs-c-ip">
														<span className="zs-ipw">
															<span className="zs-mono">
																{ row.ip }
															</span>
															{ blockedBadge(
																row
															) }
														</span>
													</td>
													<td>
														{ row.country ? (
															<Country
																code={
																	row.country
																}
																name={
																	names[
																		row
																			.country
																	]
																}
															/>
														) : (
															<span className="zs-hint">
																{ __(
																	'Unknown',
																	'zero-spam'
																) }
															</span>
														) }
													</td>
													<td className="zs-mono zs-num">
														{ num( row.count ) }
													</td>
													<td className="zs-act">
														{ blockButton( row ) }
													</td>
												</tr>
											) ) }
										</tbody>
									</table>
								</div>
							) : (
								<ul className="zs-cards zs-cards-plain">
									{ top.map( ( row ) => (
										<li key={ row.ip }>
											<div className="zs-cardmain">
												<span className="zs-cm-t zs-mono">
													{ row.ip }
												</span>
												<span className="zs-cm-m">
													{ [
														names[ row.country ] ||
															row.country,
														detections( row.count ),
													]
														.filter( Boolean )
														.join( ' · ' ) }
												</span>
											</div>
											<span className="zs-card-side">
												<span className="zs-cm-b">
													{ blockedBadge( row ) }
												</span>
												<span className="zs-cm-a">
													{ blockButton( row ) }
												</span>
											</span>
										</li>
									) ) }
								</ul>
							) }
						</>
					) : (
						<Empty
							text={ __(
								'No detections in this period.',
								'zero-spam'
							) }
						/>
					) }
					<p className="zs-hint">
						{ createInterpolateElement(
							__(
								'Still seeing spam? Nothing is 100%. <a>Contact us</a> if you see a large amount of malicious activity.',
								'zero-spam'
							),
							{
								a: (
									<ExtLink
										href={ utm(
											'https://www.zerospam.org/contact/',
											'activity-reports'
										) }
										newTab={ __(
											'(opens in a new tab)',
											'zero-spam'
										) }
									/>
								),
							}
						) }
					</p>
				</Tile>

				<Tile span={ 12 } labelledBy="zs-rp-hour">
					<TileHead
						eyebrow={ per }
						id="zs-rp-hour"
						title={ __( 'Detections by the hour', 'zero-spam' ) }
					/>
					{ hours.total > 0 ? (
						<>
							<ColumnChart
								id="zs-hrc"
								className="zs-hours"
								label={ __(
									'Detections by hour of the day',
									'zero-spam'
								) }
								summary={ sprintf(
									/* translators: 1: period, 2: busiest hour, 3: detections in it */
									__(
										'%1$s. Busiest at %2$s with %3$s.',
										'zero-spam'
									),
									per,
									lab( busiest[ 0 ].hour ),
									detections( busiest[ 0 ].count )
								) }
								columns={ hours.columns }
								describe={ ( c ) =>
									`${ lab( c.key ) } · ${ detections( c.value ) }`
								}
								renderColumn={ ( c ) => (
									<span
										className="zs-hbar"
										style={ {
											height: barWidth(
												c.value,
												hours.max
											),
										} }
									/>
								) }
								axis={ ( c ) =>
									0 === c.key % 6 ? lab( c.key ) : ''
								}
								caption={ sprintf(
									/* translators: %s: period, such as last 30 days */
									__(
										'Detections by hour of the day, %s',
										'zero-spam'
									),
									per.toLowerCase()
								) }
								head={ [
									__( 'Hour', 'zero-spam' ),
									__( 'Detections', 'zero-spam' ),
								] }
								cells={ ( c ) => [
									lab( c.key ),
									num( c.value ),
								] }
							/>
							<p className="zs-hint">
								{ sprintf(
									/* translators: %s: list of the busiest hours with their counts */
									__(
										'Busiest hours: %s. Times are in the site’s time zone.',
										'zero-spam'
									),
									busiest
										.map(
											( h ) =>
												`${ lab( h.hour ) } (${ num( h.count ) })`
										)
										.join( ', ' )
								) }
							</p>
						</>
					) : (
						<Empty
							text={ __(
								'No detections in this period.',
								'zero-spam'
							) }
						/>
					) }
				</Tile>
			</div>

			{ blockFor && (
				<BlockForm
					key={ blockFor.ip }
					open
					ip={ blockFor.ip }
					lookup={ blockFor.blocked }
					onClose={ () => setBlockFor( null ) }
					onSaved={ onSaved }
					returnFocus={ () =>
						Array.from(
							document.querySelectorAll(
								'.zs-reports [data-zs-block]'
							)
						).find(
							( el ) =>
								el.getAttribute( 'data-zs-block' ) ===
								returnTo.current
						) || null
					}
				/>
			) }
		</div>
	);
}
