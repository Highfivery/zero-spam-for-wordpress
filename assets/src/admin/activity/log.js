/**
 * The Activity log (prototype §3.1): filters with live counts, a bulk bar,
 * a table from 1,200px (a list of cards below), pages, the details drawer
 * and the "N new · Show" bar of the live feed.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp, useMedia } from '../app/context';
import { Badge, Button, Chip, Icon, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { BlockForm } from '../app/block-form';
import { blockAll, bulkBlockPlan, initialValues } from '../app/block-data';
import { NS, boot, nowLocal, request } from '../data/api';
import { ago, day, dayTime, minutesAgo, num } from '../data/format';
import { createAnnounceGate } from '../data/poll';
import { useLiveFeed } from '../data/use-live-feed';
import { typeIcon } from '../overview/tiles';
import { DetailsDrawer } from './details';
import { Country, LocationSort, SortButton, TypeIcon } from './parts';
import {
	DATE_RANGES,
	DEFAULT_SORT,
	TABLE_QUERY,
	allCountriesCount,
	allTypesCount,
	ariaSort,
	emptyFilters,
	facetOptions,
	facetsPath,
	isFiltered,
	keepSelected,
	listPath,
	navState,
	pageList,
	paging,
	readView,
	sincePath,
	toggleSort,
	withView,
} from './query';
import { errorMessage, runUndoable } from './undo';
import { useQuery } from './use-query';

/** @return {Array<[string, string]>} Sort keys and names (the Sort menu). */
const sortOptions = () => [
	[ 'date:desc', __( 'Newest first', 'zero-spam' ) ],
	[ 'date:asc', __( 'Oldest first', 'zero-spam' ) ],
	[ 'type:asc', __( 'Type', 'zero-spam' ) ],
	[ 'ip:asc', __( 'IP address', 'zero-spam' ) ],
	[ 'country:asc', __( 'Country', 'zero-spam' ) ],
	[ 'region:asc', __( 'Region', 'zero-spam' ) ],
];

/** @return {Object<number, string>} Date range chip names. */
const rangeNames = () => ( {
	0: __( 'All time', 'zero-spam' ),
	1: __( 'Today', 'zero-spam' ),
	7: __( '7 days', 'zero-spam' ),
	30: __( '30 days', 'zero-spam' ),
} );

/**
 * "Region (code)" of a row.
 *
 * @param {Object} row Row.
 * @return {string} Region.
 */
const regionOf = ( row ) =>
	[ row.region_name, row.region ? `(${ row.region })` : '' ]
		.filter( Boolean )
		.join( ' ' );

/**
 * The element that opens an entry (the Details button in the table, the
 * card itself in the list).
 *
 * @param {number} id Entry.
 * @return {Element|null} Element.
 */
const opener = ( id ) =>
	document.querySelector( `.zs-log [data-zs-open="${ Number( id ) }"]` );

/**
 * @param {Object}                          props           Props.
 * @param {(...args: unknown[]) => unknown} props.onChanged Called after entries are deleted or restored (the heading's count).
 * @param {Object|undefined}                props.summary   stats/summary?range=30 (the log's size).
 */
export function Log( { onChanged, summary } ) {
	const { enhanced, prefs } = useApp();
	const { toast, announce } = useFeedback();
	const wide = useMedia( TABLE_QUERY );
	const first = useMemo( () => readView( window.location.search ), [] );
	const [ view, setView ] = useState( () => ( {
		filters: first.filters,
		sort: first.sort,
		page: first.page,
	} ) );
	const [ searchText, setSearchText ] = useState( first.filters.search );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ drawer, setDrawer ] = useState(
		first.item ? { kind: 'details', id: first.item } : null
	);
	const [ version, setVersion ] = useState( 0 );
	const [ bulk, setBulk ] = useState( '' );
	const returnTo = useRef( null );
	const searchRef = useRef( null );
	const bulkRef = useRef( null );
	const gate = useRef( createAnnounceGate() );

	const list = useQuery( listPath( view ), { total: true } );
	const facets = useQuery( facetsPath( view.filters ) );
	const rows = useMemo(
		() => ( Array.isArray( list.data ) ? list.data : [] ),
		[ list.data ]
	);
	const ids = useMemo( () => rows.map( ( r ) => r.id ), [ rows ] );
	const loaded = undefined !== list.data;
	const pages = paging( list.total ?? rows.length, list.page || view.page );
	const filtered = isFiltered( view.filters );
	const now = nowLocal();

	// The address keeps the view (and the entry open in the drawer).
	useEffect( () => {
		const item = drawer && 'details' === drawer.kind ? drawer.id : 0;
		const next = withView( window.location.href, { ...view, item } );
		if ( next !== window.location.href ) {
			window.history.replaceState( window.history.state, '', next );
		}
	}, [ view, drawer ] );

	// Typing in the search waits for a pause.
	useEffect( () => {
		if ( searchText === view.filters.search ) {
			return undefined;
		}
		const timer = window.setTimeout( () => {
			setView( ( v ) => ( {
				...v,
				page: 1,
				filters: { ...v.filters, search: searchText },
			} ) );
		}, 350 );
		return () => window.clearTimeout( timer );
	}, [ searchText, view.filters.search ] );

	// Selected entries no longer shown are let go.
	useEffect( () => {
		setSelected( ( s ) => keepSelected( s, ids ) );
	}, [ ids ] );

	const refresh = useCallback( () => {
		list.reload();
		facets.reload();
		setVersion( ( v ) => v + 1 );
		onChanged();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- reload functions follow their paths.
	}, [ list.reload, facets.reload, onChanged ] );

	const setFilter = ( changes ) => {
		setSelected( new Set() );
		setView( ( v ) => ( {
			...v,
			page: 1,
			filters: { ...v.filters, ...changes },
		} ) );
	};

	const clearFilters = () => {
		setSearchText( '' );
		setSelected( new Set() );
		setView( ( v ) => ( { ...v, page: 1, filters: emptyFilters() } ) );
		searchRef.current?.focus();
		toast( __( 'Filters cleared', 'zero-spam' ) );
	};

	const setSort = ( sort ) => setView( ( v ) => ( { ...v, sort, page: 1 } ) );

	const goPage = ( page ) => {
		setView( ( v ) => ( { ...v, page } ) );
		document
			.getElementById( 'zs-log-top' )
			?.scrollIntoView( { block: 'nearest' } );
	};

	// The live feed: entries newer than the newest these filters match.
	const facetsReady = undefined !== facets.data && ! facets.loading;
	const filtersKey = JSON.stringify( view.filters );
	const feed = useLiveFeed( {
		initial: facetsReady ? [] : undefined,
		sinceId: facets.data?.max_id ?? 0,
		resetKey: `${ filtersKey }|${ facets.data?.max_id ?? '' }`,
		userPaused: true === prefs.live_paused,
		// The table never moves under the pointer: new entries always wait behind the bar.
		reduced: true,
		keep: 0,
		fetch: ( since ) =>
			request( { path: sincePath( view.filters, since ) } ),
		onNew: ( items ) => {
			if ( gate.current() ) {
				announce(
					sprintf(
						/* translators: %s: number of new detections */
						_n(
							'%s new detection. Use Show to see it.',
							'%s new detections. Use Show to see them.',
							items.length,
							'zero-spam'
						),
						num( items.length )
					)
				);
			}
		},
	} );

	const showNew = () => {
		feed.showQueued();
		if ( 1 !== view.page || DEFAULT_SORT !== view.sort ) {
			setView( ( v ) => ( { ...v, page: 1, sort: DEFAULT_SORT } ) );
		} else {
			list.reload();
		}
		facets.reload();
		searchRef.current?.focus();
	};

	/* Drawer */

	const openDetails = ( id ) => {
		returnTo.current = id;
		setDrawer( { kind: 'details', id } );
	};

	const openBlock = ( row, block = null ) => {
		returnTo.current = row.id;
		setDrawer( {
			kind: 'block',
			ip: row.ip,
			block,
			lookup: ! block && Boolean( row.blocked ),
			from: row.id,
		} );
	};

	const focusBack = () => {
		const id = returnTo.current;
		if ( null === id ) {
			return searchRef.current;
		}
		if ( drawer && 'block' === drawer.kind ) {
			return (
				document.querySelector( `.zs-log [data-zs-block="${ id }"]` ) ||
				opener( id )
			);
		}
		return opener( id );
	};

	const onBlockSaved = ( saved, created ) => {
		const ip = saved.ip || saved.key;
		setDrawer( null );
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
			refresh,
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
						day(
							new Date(
								Date.parse( saved.end ) +
									( boot.nowLocal - Date.now() )
							)
						)
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
				undoFailed: ( error ) =>
					errorMessage(
						error,
						__(
							'The block could not be removed. Please try again.',
							'zero-spam'
						)
					),
			},
		} );
	};

	const onAllow = ( ip, setAllowed ) =>
		runUndoable( {
			run: () =>
				request( {
					path: `${ NS }/rules/lists/allowed-ips`,
					method: 'POST',
					data: { add: [ ip ] },
				} ).then( ( answer ) => {
					setAllowed( true );
					return answer;
				} ),
			undo: () =>
				request( {
					path: `${ NS }/rules/lists/allowed-ips`,
					method: 'POST',
					data: { remove: [ ip ] },
				} ).then( ( answer ) => {
					setAllowed( false );
					return answer;
				} ),
			request,
			toast,
			refresh: () => {},
			messages: {
				done: () =>
					sprintf(
						/* translators: %s: IP address */ __(
							'%s added to your IP allowlist',
							'zero-spam'
						),
						ip
					),
				undone: () =>
					__( 'Removed from your IP allowlist', 'zero-spam' ),
				failed: ( error ) =>
					errorMessage(
						error,
						__(
							'The address could not be added to your IP allowlist. Please try again.',
							'zero-spam'
						)
					),
				undoFailed: ( error ) =>
					errorMessage(
						error,
						__(
							'The address could not be removed. Please try again.',
							'zero-spam'
						)
					),
			},
		} );

	const onDelete = ( item ) => {
		const nav = navState( ids, item.id );
		returnTo.current = nav.next ?? nav.prev ?? null;
		setDrawer( null );
		runUndoable( {
			run: () =>
				request( {
					path: `${ NS }/activity/${ item.id }`,
					method: 'DELETE',
				} ),
			request,
			toast,
			refresh,
			messages: {
				done: () => __( 'Entry deleted', 'zero-spam' ),
				undone: () => __( 'Entry restored', 'zero-spam' ),
				failed: ( error ) =>
					errorMessage(
						error,
						__(
							'The entry could not be deleted. Please try again.',
							'zero-spam'
						)
					),
				undoFailed: ( error ) =>
					errorMessage(
						error,
						__( 'The entry could not be restored.', 'zero-spam' )
					),
			},
		} );
	};

	const bulkBlock = ( chosen ) => {
		const plan = bulkBlockPlan( rows, new Set( chosen ) );
		const alreadyText = plan.already
			? sprintf(
					/* translators: %s: number of IP addresses */
					_n(
						'%s already blocked',
						'%s already blocked',
						plan.already,
						'zero-spam'
					),
					num( plan.already )
				)
			: '';
		if ( ! plan.ips.length ) {
			toast( alreadyText );
			return;
		}
		const end = initialValues( null, '', nowLocal(), 0 ).end;
		runUndoable( {
			run: () =>
				blockAll( { ips: plan.ips, end, request } ).then(
					( result ) => {
						if ( ! result.created.length && ! result.replaced ) {
							return Promise.reject( {} );
						}
						return result;
					}
				),
			// Undo removes the new blocks (a replaced block keeps its new dates).
			canUndo: ( result ) => result.created.length > 0,
			undo: ( result ) =>
				request( {
					path: `${ NS }/rules/blocks/bulk-delete`,
					method: 'POST',
					data: { ids: result.created },
				} ),
			request,
			toast,
			refresh,
			messages: {
				done: ( result ) => {
					const blocked = result.created.length + result.replaced;
					return [
						sprintf(
							/* translators: %s: number of IP addresses */
							_n(
								'Blocked %s IP address for 7 days',
								'Blocked %s IP addresses for 7 days',
								blocked,
								'zero-spam'
							),
							num( blocked )
						),
						alreadyText,
						result.failed
							? sprintf(
									/* translators: %s: number of IP addresses */
									_n(
										'%s could not be blocked',
										'%s could not be blocked',
										result.failed,
										'zero-spam'
									),
									num( result.failed )
								)
							: '',
					]
						.filter( Boolean )
						.join( ' · ' );
				},
				undone: ( answer ) =>
					sprintf(
						/* translators: %s: number of IP addresses */
						_n(
							'%s block removed',
							'%s blocks removed',
							answer?.deleted ?? 0,
							'zero-spam'
						),
						num( answer?.deleted ?? 0 )
					),
				failed: ( error ) =>
					errorMessage(
						error,
						__(
							'The addresses could not be blocked. Please try again.',
							'zero-spam'
						)
					),
				undoFailed: ( error ) =>
					errorMessage(
						error,
						__(
							'The blocks could not be removed. Please try again.',
							'zero-spam'
						)
					),
			},
		} );
	};

	const applyBulk = () => {
		if ( ! bulk ) {
			toast( __( 'Choose a bulk action first', 'zero-spam' ) );
			bulkRef.current?.focus();
			return;
		}
		if ( ! selected.size ) {
			toast( __( 'Select at least one entry first', 'zero-spam' ) );
			return;
		}
		const chosen = [ ...selected ];
		setSelected( new Set() );
		if ( 'block' === bulk ) {
			bulkBlock( chosen );
			return;
		}
		runUndoable( {
			run: () =>
				request( {
					path: `${ NS }/activity/bulk-delete`,
					method: 'POST',
					data: { ids: chosen },
				} ),
			request,
			toast,
			refresh,
			messages: {
				done: ( answer ) =>
					sprintf(
						/* translators: %s: number of entries */
						_n(
							'Deleted %s entry',
							'Deleted %s entries',
							answer?.deleted ?? chosen.length,
							'zero-spam'
						),
						num( answer?.deleted ?? chosen.length )
					),
				undone: ( answer ) =>
					sprintf(
						/* translators: %s: number of entries */
						_n(
							'%s entry restored',
							'%s entries restored',
							answer?.restored ?? chosen.length,
							'zero-spam'
						),
						num( answer?.restored ?? chosen.length )
					),
				failed: ( error ) =>
					errorMessage(
						error,
						__(
							'The entries could not be deleted. Please try again.',
							'zero-spam'
						)
					),
				undoFailed: ( error ) =>
					errorMessage(
						error,
						__( 'The entries could not be restored.', 'zero-spam' )
					),
			},
		} );
	};

	const toggle = ( id, on ) =>
		setSelected( ( s ) => {
			const next = new Set( s );
			if ( on ) {
				next.add( id );
			} else {
				next.delete( id );
			}
			return next;
		} );

	const allShown =
		rows.length > 0 && rows.every( ( r ) => selected.has( r.id ) );
	const toggleAll = ( on ) => setSelected( on ? new Set( ids ) : new Set() );

	/* Filters */

	const typeCount = allTypesCount( facets.data );
	const countryCount = allCountriesCount( facets.data, view.filters );
	const withCount = ( label, count ) =>
		null === count || undefined === count
			? label
			: `${ label } (${ num( count ) })`;
	const ranges = rangeNames();

	const details = drawer && 'details' === drawer.kind ? drawer : null;
	const detailsRow = details
		? rows.find( ( r ) => r.id === details.id )
		: null;
	const openId = details ? details.id : null;

	const when = ( row ) => ago( minutesAgo( row.date, now ) );
	const rowLabel = ( row ) =>
		[ row.ip, row.type_label, row.country_name || row.country, when( row ) ]
			.filter( Boolean )
			.join( ', ' );

	const blockButton = ( row ) => (
		<Button
			variant="ghost"
			size="sm"
			data-zs-block={ row.id }
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
								'Block %s',
								'zero-spam'
							),
							row.ip
						)
			}
			onClick={ ( event ) => {
				event.stopPropagation();
				openBlock( row );
			} }
		>
			{ row.blocked
				? __( 'Edit block', 'zero-spam' )
				: __( 'Block IP', 'zero-spam' ) }
		</Button>
	);

	const networkBadge = ( row ) =>
		enhanced &&
		row.network && (
			<Badge tone="enh" icon="hub" className="zs-netbadge">
				{ __( 'Network', 'zero-spam' ) }
			</Badge>
		);

	const emptyState = loaded && ! rows.length && (
		<div className="zs-empty">
			<div className="zs-ghost-wall zs-ghost-sm" aria-hidden="true">
				<span />
				<span />
				<span />
			</div>
			{ filtered ? (
				<>
					<b>
						{ __(
							'No detections match these filters.',
							'zero-spam'
						) }
					</b>
					<span className="zs-hint">
						{ __(
							'Clear the search, type, country and date filters to see every detection.',
							'zero-spam'
						) }
					</span>
					<Button
						variant="secondary"
						size="sm"
						onClick={ clearFilters }
					>
						{ __( 'Clear filters', 'zero-spam' ) }
					</Button>
				</>
			) : (
				<>
					<b>{ __( 'Nothing blocked yet', 'zero-spam' ) }</b>
					<span className="zs-hint">
						{ __(
							'Spam and attacks Zero Spam stops show up here as they happen.',
							'zero-spam'
						) }
					</span>
				</>
			) }
		</div>
	);

	const sortName = ( sortOptions().find( ( [ k ] ) => k === view.sort ) ||
		[] )[ 1 ];
	const caption = sprintf(
		/* translators: 1: sort order, such as Newest first, 2: page number, 3: number of pages */
		__( 'Detection log, %1$s, page %2$s of %3$s', 'zero-spam' ),
		sortName ||
			( view.sort.startsWith( 'region' )
				? __( 'by region', 'zero-spam' )
				: __( 'by country', 'zero-spam' ) ),
		num( pages.page ),
		num( pages.pages )
	);

	const table = (
		<div className="zs-tbl-wrap">
			<table
				className="zs-t zs-log-t"
				aria-busy={ list.loading ? 'true' : undefined }
			>
				<caption className="zs-sr-only">{ caption }</caption>
				<colgroup>
					<col className="zs-w-cb" />
					<col className="zs-w-date" />
					<col className="zs-w-type" />
					<col className="zs-w-ip" />
					<col className="zs-w-loc" />
					<col className="zs-w-act" />
				</colgroup>
				<thead>
					<tr>
						<th scope="col" className="zs-c-cb">
							<input
								type="checkbox"
								aria-label={ __(
									'Select all shown',
									'zero-spam'
								) }
								checked={ allShown }
								disabled={ ! rows.length }
								onChange={ ( e ) =>
									toggleAll( e.target.checked )
								}
							/>
						</th>
						<th
							scope="col"
							aria-sort={ ariaSort( view.sort, [ 'date' ] ) }
						>
							<SortButton
								sort={ view.sort }
								column="date"
								onSort={ ( c ) =>
									setSort( toggleSort( view.sort, c ) )
								}
							>
								{ __( 'Date', 'zero-spam' ) }
							</SortButton>
						</th>
						<th
							scope="col"
							aria-sort={ ariaSort( view.sort, [ 'type' ] ) }
						>
							<SortButton
								sort={ view.sort }
								column="type"
								onSort={ ( c ) =>
									setSort( toggleSort( view.sort, c ) )
								}
							>
								{ __( 'Type', 'zero-spam' ) }
							</SortButton>
						</th>
						<th
							scope="col"
							aria-sort={ ariaSort( view.sort, [ 'ip' ] ) }
						>
							<SortButton
								sort={ view.sort }
								column="ip"
								onSort={ ( c ) =>
									setSort( toggleSort( view.sort, c ) )
								}
							>
								{ __( 'IP address', 'zero-spam' ) }
							</SortButton>
						</th>
						<th
							scope="col"
							aria-sort={ ariaSort( view.sort, [
								'country',
								'region',
							] ) }
						>
							<LocationSort
								sort={ view.sort }
								onSort={ setSort }
							/>
						</th>
						<th scope="col" className="zs-act">
							<span className="zs-sr-only">
								{ __( 'Actions', 'zero-spam' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<tr
							key={ row.id }
							className={ cx(
								( selected.has( row.id ) ||
									openId === row.id ) &&
									'zs-sel'
							) }
							onClick={ ( event ) => {
								if (
									! event.target.closest(
										'button, a, input, label'
									)
								) {
									openDetails( row.id );
								}
							} }
						>
							<td className="zs-c-cb">
								{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control -- The checkbox has its own accessible name (aria-label); the label only makes the whole cell a 32px target. */ }
								<label className="zs-cbhit">
									<input
										type="checkbox"
										checked={ selected.has( row.id ) }
										onChange={ ( e ) =>
											toggle( row.id, e.target.checked )
										}
										aria-label={ sprintf(
											/* translators: 1: IP address, 2: relative time */ __(
												'Select %1$s, %2$s',
												'zero-spam'
											),
											row.ip,
											when( row )
										) }
									/>
								</label>
							</td>
							<td className="zs-c-w">
								<span>{ when( row ) }</span>
								<span className="zs-sub2">
									{ dayTime( row.date ) }
								</span>
							</td>
							<td className="zs-c-ty">
								<span className="zs-typec">
									<TypeIcon icon={ typeIcon( row.type ) } />
									<span className="zs-typet">
										<span
											className="zs-typel"
											title={ row.type_label }
										>
											{ row.type_label }
										</span>
										{ networkBadge( row ) }
									</span>
								</span>
							</td>
							<td className="zs-c-ip zs-mono" title={ row.ip }>
								{ row.ip }
							</td>
							<td className="zs-c-ct">
								<span className="zs-loc">
									{ row.country ? (
										<Country
											code={ row.country }
											name={ row.country_name }
										/>
									) : (
										<span className="zs-hint">
											{ __( 'Unknown', 'zero-spam' ) }
										</span>
									) }
									{ regionOf( row ) && (
										<span className="zs-sub2">
											{ regionOf( row ) }
										</span>
									) }
								</span>
							</td>
							<td className="zs-act">
								<div className="zs-actw">
									<Button
										variant="ghost"
										size="sm"
										data-zs-open={ row.id }
										aria-label={ sprintf(
											/* translators: 1: IP address, 2: relative time */ __(
												'Details for %1$s, %2$s',
												'zero-spam'
											),
											row.ip,
											when( row )
										) }
										onClick={ () => openDetails( row.id ) }
									>
										{ __( 'Details', 'zero-spam' ) }
									</Button>
									{ blockButton( row ) }
								</div>
							</td>
						</tr>
					) ) }
					{ emptyState && (
						<tr className="zs-empty-row">
							<td colSpan={ 6 }>{ emptyState }</td>
						</tr>
					) }
				</tbody>
			</table>
		</div>
	);

	const cards = (
		<div
			className="zs-tbl-wrap zs-cards-wrap"
			aria-busy={ list.loading ? 'true' : undefined }
		>
			{ rows.length > 0 && (
				<ul className="zs-cards" aria-label={ caption }>
					{ rows.map( ( row ) => (
						<li
							key={ row.id }
							className={ cx(
								( selected.has( row.id ) ||
									openId === row.id ) &&
									'zs-sel'
							) }
						>
							{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control -- The checkbox has its own accessible name (aria-label); the label only makes the whole cell a 32px target. */ }
							<label className="zs-cbhit">
								<input
									type="checkbox"
									checked={ selected.has( row.id ) }
									onChange={ ( e ) =>
										toggle( row.id, e.target.checked )
									}
									aria-label={ sprintf(
										/* translators: 1: IP address, 2: relative time */ __(
											'Select %1$s, %2$s',
											'zero-spam'
										),
										row.ip,
										when( row )
									) }
								/>
							</label>
							<button
								type="button"
								className="zs-cardmain"
								data-zs-open={ row.id }
								aria-label={ rowLabel( row ) }
								onClick={ () => openDetails( row.id ) }
							>
								<span className="zs-cm-t zs-mono">
									{ row.ip }
								</span>
								<span className="zs-cm-m">
									{ [
										when( row ),
										row.country_name || row.country,
										row.region_name,
										row.failed_label,
									]
										.filter( Boolean )
										.join( ' · ' ) }
								</span>
							</button>
							<span className="zs-card-side">
								<span className="zs-cm-b">
									<Badge>{ row.type_label }</Badge>
									{ networkBadge( row ) }
								</span>
								<span className="zs-cm-a">
									{ blockButton( row ) }
								</span>
							</span>
						</li>
					) ) }
				</ul>
			) }
			{ emptyState }
		</div>
	);

	const facetType = facetOptions( facets.data?.type, view.filters.type );
	const facetCountry = facetOptions(
		facets.data?.country,
		view.filters.country
	);

	return (
		<div className="zs-log" id="zs-log-top">
			{ feed.queued.length > 0 && (
				<div className="zs-newbar">
					<Button variant="secondary" size="sm" onClick={ showNew }>
						{ sprintf(
							/* translators: %s: number of new detections */
							_n(
								'%s new · Show',
								'%s new · Show',
								feed.queued.length,
								'zero-spam'
							),
							num( feed.queued.length )
						) }
					</Button>
				</div>
			) }

			<div
				className="zs-tbar zs-logbar"
				role="search"
				aria-label={ __( 'Filter detections', 'zero-spam' ) }
			>
				<div className="zs-field zs-search">
					<label htmlFor="zs-lq" className="zs-sr-only">
						{ __( 'Search IPs', 'zero-spam' ) }
					</label>
					<Icon name="search" size="sm" />
					<input
						ref={ searchRef }
						id="zs-lq"
						className="zs-input"
						type="search"
						placeholder={ __( 'Search IPs', 'zero-spam' ) }
						value={ searchText }
						maxLength={ 100 }
						onChange={ ( e ) => setSearchText( e.target.value ) }
						onKeyDown={ ( e ) => {
							if ( 'Enter' === e.key ) {
								setFilter( { search: searchText } );
							}
						} }
					/>
				</div>
				<div className="zs-field">
					<label className="zs-sr-only" htmlFor="zs-lt">
						{ __( 'Filter by type', 'zero-spam' ) }
					</label>
					<select
						id="zs-lt"
						className="zs-select"
						value={ view.filters.type }
						onChange={ ( e ) =>
							setFilter( { type: e.target.value } )
						}
					>
						<option value="">
							{ withCount(
								__( 'All types', 'zero-spam' ),
								typeCount
							) }
						</option>
						{ facetType.map( ( o ) => (
							<option key={ o.value } value={ o.value }>
								{ withCount( o.label, o.count ) }
							</option>
						) ) }
					</select>
				</div>
				<div className="zs-field">
					<label className="zs-sr-only" htmlFor="zs-lc">
						{ __( 'Filter by country', 'zero-spam' ) }
					</label>
					<select
						id="zs-lc"
						className="zs-select"
						value={ view.filters.country }
						onChange={ ( e ) =>
							setFilter( { country: e.target.value } )
						}
					>
						<option value="">
							{ withCount(
								__( 'All countries', 'zero-spam' ),
								countryCount
							) }
						</option>
						{ facetCountry.map( ( o ) => (
							<option key={ o.value } value={ o.value }>
								{ withCount( o.label, o.count ) }
							</option>
						) ) }
					</select>
				</div>
				<div
					className="zs-chips zs-ranges"
					role="group"
					aria-label={ __( 'Date range', 'zero-spam' ) }
				>
					{ DATE_RANGES.map( ( d ) => (
						<Chip
							key={ d }
							pressed={ view.filters.days === d }
							onClick={ () => setFilter( { days: d } ) }
						>
							{ ranges[ d ] }
						</Chip>
					) ) }
				</div>
				{ enhanced && (
					<button
						type="button"
						className="zs-netchip"
						aria-pressed={ view.filters.network ? 'true' : 'false' }
						onClick={ () =>
							setFilter( { network: ! view.filters.network } )
						}
					>
						<Icon name="hub" size="sm" />
						{ __( 'Network-only', 'zero-spam' ) }
						{ undefined !== facets.data?.network_only && (
							<span className="zs-c">
								{ num( facets.data.network_only ) }
							</span>
						) }
					</button>
				) }
			</div>

			<div className="zs-bulk">
				{ ! wide && (
					<label className="zs-selall" htmlFor="zs-lallm">
						<input
							id="zs-lallm"
							type="checkbox"
							checked={ allShown }
							disabled={ ! rows.length }
							onChange={ ( e ) => toggleAll( e.target.checked ) }
						/>
						<span>{ __( 'Select all', 'zero-spam' ) }</span>
					</label>
				) }
				<span
					className={ cx(
						'zs-selcount',
						! selected.size && 'zs-zero'
					) }
					aria-live="polite"
				>
					{ sprintf(
						/* translators: %s: number of selected entries */
						__( '%s selected', 'zero-spam' ),
						num( selected.size )
					) }
				</span>
				<label className="zs-sr-only" htmlFor="zs-lbulk">
					{ __( 'Bulk action', 'zero-spam' ) }
				</label>
				<select
					ref={ bulkRef }
					id="zs-lbulk"
					className="zs-select zs-select-auto"
					value={ bulk }
					onChange={ ( e ) => setBulk( e.target.value ) }
				>
					<option value="">
						{ __( 'Bulk actions', 'zero-spam' ) }
					</option>
					<option value="delete">
						{ __( 'Delete selected', 'zero-spam' ) }
					</option>
					<option value="block">
						{ __( 'Block selected IPs', 'zero-spam' ) }
					</option>
				</select>
				<Button variant="secondary" size="sm" onClick={ applyBulk }>
					{ __( 'Apply', 'zero-spam' ) }
				</Button>
				<span className="zs-grow" />
				{ ! wide && (
					<span className="zs-cardsort">
						<label htmlFor="zs-lsort" className="zs-sr-only">
							{ __( 'Sort detections', 'zero-spam' ) }
						</label>
						<select
							id="zs-lsort"
							className="zs-select zs-select-auto"
							value={
								sortOptions().some(
									( [ k ] ) => k === view.sort
								)
									? view.sort
									: DEFAULT_SORT
							}
							onChange={ ( e ) => setSort( e.target.value ) }
						>
							{ sortOptions().map( ( [ value, label ] ) => (
								<option key={ value } value={ value }>
									{ sprintf(
										/* translators: %s: sort order, such as Newest first */
										__( 'Sort: %s', 'zero-spam' ),
										label
									) }
								</option>
							) ) }
						</select>
					</span>
				) }
				<span className="zs-badge" aria-live="polite">
					{ loaded &&
						sprintf(
							/* translators: %s: number of entries */
							_n(
								'%s entry',
								'%s entries',
								list.total ?? rows.length,
								'zero-spam'
							),
							num( list.total ?? rows.length )
						) }
				</span>
			</div>

			{ wide ? table : cards }

			{ pages.pages > 1 && (
				<nav
					className="zs-pager"
					aria-label={ __( 'Log pages', 'zero-spam' ) }
				>
					<span>
						{ sprintf(
							/* translators: 1: first entry shown, 2: last entry shown, 3: number of entries */
							__( 'Showing %1$s–%2$s of %3$s', 'zero-spam' ),
							num( pages.from ),
							num( pages.to ),
							num( list.total ?? 0 )
						) }
					</span>
					<span className="zs-pages">
						<Button
							variant="secondary"
							size="sm"
							disabled={ pages.page <= 1 }
							onClick={ () => goPage( pages.page - 1 ) }
						>
							{ __( 'Previous', 'zero-spam' ) }
						</Button>
						{ pageList( pages.page, pages.pages ).map( ( n, i ) =>
							null === n ? (
								<span
									key={ `gap-${ i }` }
									className="zs-gap"
									aria-hidden="true"
								>
									…
								</span>
							) : (
								<Button
									key={ n }
									variant="secondary"
									size="sm"
									aria-current={
										n === pages.page ? 'page' : undefined
									}
									aria-label={ sprintf(
										/* translators: %s: page number */ __(
											'Page %s',
											'zero-spam'
										),
										num( n )
									) }
									onClick={ () => goPage( n ) }
								>
									{ num( n ) }
								</Button>
							)
						) }
						<Button
							variant="secondary"
							size="sm"
							disabled={ pages.page >= pages.pages }
							onClick={ () => goPage( pages.page + 1 ) }
						>
							{ __( 'Next', 'zero-spam' ) }
						</Button>
					</span>
				</nav>
			) }

			{ list.error && (
				<p className="zs-notice zs-notice-err" role="alert">
					{ __(
						'The log could not be loaded. Reload the page to try again.',
						'zero-spam'
					) }
				</p>
			) }

			{ summary?.log?.max && (
				<p className="zs-hint zs-log-note">
					{ sprintf(
						/* translators: %s: number of entries the log keeps */
						__(
							'The log keeps the latest %s entries; older ones are removed as new ones arrive (Settings › Activity log).',
							'zero-spam'
						),
						num( summary.log.max )
					) }
				</p>
			) }

			{ details && (
				<DetailsDrawer
					key="details"
					id={ details.id }
					row={ detailsRow }
					nav={ navState( ids, details.id ) }
					version={ version }
					onStep={ ( id ) => {
						returnTo.current = id;
						setDrawer( { kind: 'details', id } );
						opener( id )
							?.closest( 'tr, li' )
							?.scrollIntoView( { block: 'nearest' } );
					} }
					onClose={ () => setDrawer( null ) }
					onBlock={ ( ip, block ) =>
						openBlock(
							detailsRow || {
								id: details.id,
								ip,
								blocked: Boolean( block ),
							},
							block
						)
					}
					onAllow={ onAllow }
					onDelete={ onDelete }
					returnFocus={ focusBack }
				/>
			) }
			{ drawer && 'block' === drawer.kind && (
				<BlockForm
					key={ `block-${ drawer.from }` }
					open
					ip={ drawer.ip }
					block={ drawer.block }
					lookup={ drawer.lookup }
					onClose={ () => setDrawer( null ) }
					onSaved={ onBlockSaved }
					returnFocus={ focusBack }
				/>
			) }
		</div>
	);
}
