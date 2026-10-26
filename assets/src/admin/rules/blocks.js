/**
 * Blocked IPs and blocked locations (prototype v18 §3.2, §3.3): search, a
 * bulk bar (Select all and Remove selected, with Undo), a table from
 * 1,200px and a list of cards below it (with a Sort menu), Edit block and
 * Remove (with Undo) on every row, and the shared block drawer to add or
 * change a block.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMedia } from '../app/context';
import { Badge, Button, Icon, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { BlockForm, locationTypes } from '../app/block-form';
import { NS, boot, request, useResource } from '../data/api';
import { num } from '../data/format';
import { areaUrl, utm } from '../data/routes';
import { errorMessage } from '../activity/undo';
import { blockName, removeBlock, removeBlocks } from './actions';
import { Country, EmptyState, Pager, SearchBox, siteDay } from './parts';
import {
	DEFAULT_SORT,
	ariaSort,
	blocksPath,
	paging,
	toggleSort,
} from './query';

/** Width from which the list is a table (the Activity log's breakpoint). */
export const TABLE_QUERY = '(min-width: 1200px)';

/**
 * Sort menu choices of the card list.
 *
 * @param {string} kind ip or location.
 * @return {Array<[string, string]>} Values and names.
 */
const sortOptions = ( kind ) => [
	[ 'date_added:desc', __( 'Newest first', 'zero-spam' ) ],
	[ 'date_added:asc', __( 'Oldest first', 'zero-spam' ) ],
	[
		'key:asc',
		'ip' === kind
			? __( 'IP address', 'zero-spam' )
			: __( 'Location A–Z', 'zero-spam' ),
	],
	[ 'end:asc', __( 'Ending soonest', 'zero-spam' ) ],
];

/**
 * "Temporary" or "Permanent", as a badge.
 *
 * @param {Object} props       Props.
 * @param {Object} props.block Block.
 */
const TypeBadge = ( { block } ) =>
	'permanent' === block.type ? (
		<Badge tone="danger">{ __( 'Permanent', 'zero-spam' ) }</Badge>
	) : (
		<Badge>{ __( 'Temporary', 'zero-spam' ) }</Badge>
	);

/**
 * When a block ends: its day, or "Never".
 *
 * @param {Object} block Block.
 * @return {string} End.
 */
const endOf = ( block ) =>
	'temporary' === block.type && block.end
		? siteDay( block.end )
		: __( 'Never', 'zero-spam' );

/**
 * The opener of a row's drawer (its Edit block button, or its card).
 *
 * @param {number} id Block.
 * @return {Element|null} Element.
 */
const editButton = ( id ) =>
	Array.from(
		document.querySelectorAll(
			`.zs-rules [data-zs-edit="${ Number( id ) }"]`
		)
	).find( ( el ) => null !== el.offsetParent ) || null;

/**
 * @param {Object}                          props         Props.
 * @param {string}                          props.kind    ip or location.
 * @param {Object|undefined}                props.schema  settings/schema (block method, location services).
 * @param {(...args: unknown[]) => unknown} props.onCount Receives the number of blocks.
 * @param {Object}                          [props.panel] Ref of the tab panel (focus after a removal).
 */
export function Blocks( { kind, schema, onCount, panel } ) {
	const isIp = 'ip' === kind;
	const { toast } = useFeedback();
	const wide = useMedia( TABLE_QUERY );
	const [ view, setView ] = useState( {
		search: '',
		page: 1,
		sort: DEFAULT_SORT,
	} );
	const [ selected, setSelected ] = useState( () => new Set() );
	const [ drawer, setDrawer ] = useState( null );
	const searchRef = useRef( null );
	const addRef = useRef( null );

	const list = useResource( blocksPath( { kind, ...view } ), {
		parse: false,
	} );
	const rows = useMemo(
		() => ( Array.isArray( list.data ) ? list.data : [] ),
		[ list.data ]
	);
	const loaded = undefined !== list.data;
	const total = Number( list.headers[ 'x-wp-total' ] ?? rows.length ) || 0;
	const pages = paging(
		total,
		Number( list.headers[ 'x-zerospam-page' ] ) || view.page,
		rows.length
	);

	// The tab's count follows the whole list (not a search).
	useEffect( () => {
		if ( loaded && ! view.search ) {
			onCount( total );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- onCount is a setter.
	}, [ loaded, total, view.search ] );

	// Selections belong to the rows shown.
	useEffect( () => {
		setSelected( ( current ) => {
			const shown = new Set( rows.map( ( r ) => r.id ) );
			const kept = new Set(
				[ ...current ].filter( ( id ) => shown.has( id ) )
			);
			return kept.size === current.size ? current : kept;
		} );
	}, [ rows ] );

	const change = ( next ) => {
		setSelected( new Set() );
		setView( ( v ) => ( { ...v, page: 1, ...next } ) );
	};

	const allShown =
		rows.length > 0 && rows.every( ( r ) => selected.has( r.id ) );
	const toggle = ( id, on ) =>
		setSelected( ( current ) => {
			const next = new Set( current );
			if ( on ) {
				next.add( id );
			} else {
				next.delete( id );
			}
			return next;
		} );
	const toggleAll = ( on ) =>
		setSelected( on ? new Set( rows.map( ( r ) => r.id ) ) : new Set() );

	const clearSearch = () => {
		change( { search: '' } );
		window.requestAnimationFrame( () => searchRef.current?.focus() );
		toast( __( 'Search cleared', 'zero-spam' ) );
	};

	const afterRemoval = () => {
		setSelected( new Set() );
		window.requestAnimationFrame( () => panel?.current?.focus() );
	};

	const removeOne = ( block ) => {
		afterRemoval();
		removeBlock( { block, request, toast, refresh: list.reload } );
	};

	const removeSelected = () => {
		const ids = rows
			.filter( ( r ) => selected.has( r.id ) )
			.map( ( r ) => r.id );
		if ( ! ids.length ) {
			toast( __( 'Select at least one block first.', 'zero-spam' ) );
			return;
		}
		afterRemoval();
		removeBlocks( { ids, request, toast, refresh: list.reload } );
	};

	const onSaved = ( saved, created ) => {
		const name = blockName( saved );
		setDrawer( null );
		list.reload();
		if ( ! created ) {
			toast( __( 'Block saved', 'zero-spam' ) );
		} else if ( 'permanent' === saved.type ) {
			toast(
				sprintf(
					/* translators: %s: IP address or location */
					__( '%s blocked permanently', 'zero-spam' ),
					name
				)
			);
		} else {
			toast(
				sprintf(
					/* translators: 1: IP address or location, 2: end day, such as Thu, Oct 22 */
					__( '%1$s blocked until %2$s', 'zero-spam' ),
					name,
					siteDay( saved.end )
				)
			);
		}
	};

	const settings = schema?.settings || [];
	const valueOf = ( key ) => settings.find( ( s ) => s.key === key )?.value;
	const blockMethod = valueOf( 'block_method' );
	const htaccess = Boolean( blockMethod ) && 'php' !== blockMethod;
	const geo = [ 'ipinfo_access_token', 'ipstack_api' ].find(
		( k ) => valueOf( k )?.set
	);

	const refreshHtaccess = () =>
		request( {
			path: `${ NS }/settings/actions/refresh-htaccess`,
			method: 'POST',
		} ).then(
			() => toast( __( '.htaccess refreshed', 'zero-spam' ) ),
			( error ) =>
				toast(
					errorMessage(
						error,
						__( 'The .htaccess file was not updated.', 'zero-spam' )
					)
				)
		);

	const note = isIp ? (
		htaccess && (
			<div className="zs-notice zs-notice-warn zs-rnotice">
				<Icon name="warn" />
				<div className="zs-li-body">
					<b>{ __( '.htaccess blocking is on', 'zero-spam' ) }</b>
					<span>
						{ __(
							'Because of .htaccess character limits, keep this list under 170 addresses.',
							'zero-spam'
						) }
					</span>
					<div>
						<Button
							variant="secondary"
							size="sm"
							icon="refresh"
							onClick={ refreshHtaccess }
						>
							{ __( 'Refresh .htaccess', 'zero-spam' ) }
						</Button>
					</div>
				</div>
			</div>
		)
	) : (
		<div
			className={ cx(
				'zs-notice',
				'zs-rnotice',
				geo ? 'zs-notice-info' : 'zs-notice-warn'
			) }
		>
			<Icon name={ geo ? 'info' : 'warn' } />
			<div className="zs-li-body">
				{ geo ? (
					<span>
						{ sprintf(
							/* translators: %s: location service, IPinfo or ipstack */
							__(
								'Location lookups use %s. Shared networks can place visitors in the wrong place, so block a location only when you never expect visitors from there.',
								'zero-spam'
							),
							'ipinfo_access_token' === geo ? 'IPinfo' : 'ipstack'
						) }
					</span>
				) : (
					<>
						<b>
							{ __(
								'Blocking locations is currently off.',
								'zero-spam'
							) }
						</b>
						<span>
							{ __(
								'Add an ipstack API key or an IPinfo access token to turn it on. (ipbase does not support location blocking.)',
								'zero-spam'
							) }
						</span>
						<div>
							<Button
								variant="secondary"
								size="sm"
								href={ areaUrl( 'protection', {
									tab: 'intelligence',
									card: 'ipinfo',
								} ) }
							>
								{ __( 'Set up IPinfo', 'zero-spam' ) }
							</Button>
						</div>
					</>
				) }
			</div>
		</div>
	);

	const noun = ( n ) =>
		isIp
			? sprintf(
					/* translators: %s: number of blocked IP addresses */
					_n( '%s block', '%s blocks', n, 'zero-spam' ),
					num( n )
				)
			: sprintf(
					/* translators: %s: number of blocked locations */
					_n( '%s location', '%s locations', n, 'zero-spam' ),
					num( n )
				);

	const lookupUrl = ( ip ) =>
		boot.urls.ipLookup
			? utm(
					`${ boot.urls.ipLookup }${ encodeURIComponent( ip ) }/`,
					'rules'
				)
			: '';

	const what = ( block ) =>
		isIp ? (
			<span className="zs-mono">
				{ lookupUrl( block.ip ) ? (
					<a
						href={ lookupUrl( block.ip ) }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ block.ip }
						<span className="zs-sr-only">
							{ __(
								'(look it up, opens in a new tab)',
								'zero-spam'
							) }
						</span>
					</a>
				) : (
					block.ip
				) }
			</span>
		) : (
			<span className="zs-rloc">
				{ 'country_code' === block.key_type ? (
					<Country code={ block.key } name={ block.label } />
				) : (
					<b>{ block.key }</b>
				) }
				<span className="zs-hint">
					{ ' · ' }
					{ locationTypes()[ block.key_type ] || block.key_type }
				</span>
			</span>
		);

	const actions = ( block, compact = false ) => (
		<>
			<Button
				variant="ghost"
				size="sm"
				icon={ compact ? undefined : 'edit' }
				data-zs-edit={ block.id }
				aria-label={ sprintf(
					/* translators: %s: IP address or location */
					__( 'Edit block for %s', 'zero-spam' ),
					blockName( block )
				) }
				onClick={ () => setDrawer( { block } ) }
			>
				{ __( 'Edit block', 'zero-spam' ) }
			</Button>
			<Button
				variant="ghost"
				size="sm"
				icon={ compact ? undefined : 'trash' }
				aria-label={ sprintf(
					/* translators: %s: IP address or location */
					__( 'Remove block for %s', 'zero-spam' ),
					blockName( block )
				) }
				onClick={ () => removeOne( block ) }
			>
				{ __( 'Remove', 'zero-spam' ) }
			</Button>
		</>
	);

	const filtered = Boolean( view.search );
	let emptyTitle = isIp
		? __( 'No blocked IPs yet.', 'zero-spam' )
		: __( 'No blocked locations.', 'zero-spam' );
	let emptyHint = isIp
		? __( 'Block an address from Activity, or add one here.', 'zero-spam' )
		: __(
				'Block a country, region, city or postal code when spam keeps coming from one place.',
				'zero-spam'
			);
	if ( filtered ) {
		emptyTitle = isIp
			? __( 'No blocked IPs match your search.', 'zero-spam' )
			: __( 'No blocked locations match your search.', 'zero-spam' );
		emptyHint = isIp
			? __( 'Check the address, or clear the search.', 'zero-spam' )
			: __( 'Search by country name, code or region.', 'zero-spam' );
	}
	const empty = loaded && ! rows.length && (
		<EmptyState
			title={ emptyTitle }
			hint={ emptyHint }
			onClear={ filtered ? clearSearch : undefined }
		/>
	);

	const caption = isIp
		? __( 'Blocked IP addresses', 'zero-spam' )
		: __( 'Blocked locations', 'zero-spam' );

	const header = ( label, column ) => (
		<th scope="col" aria-sort={ ariaSort( view.sort, column ) }>
			<button
				type="button"
				className="zs-thsort"
				data-zs-sort={ column }
				onClick={ () =>
					change( { sort: toggleSort( view.sort, column ) } )
				}
			>
				{ label }
				<span aria-hidden="true">
					{ { ascending: ' ↑', descending: ' ↓' }[
						ariaSort( view.sort, column )
					] || '' }
				</span>
			</button>
		</th>
	);

	const table = (
		<div className="zs-tbl-wrap">
			<table
				className="zs-t zs-rules-t"
				aria-busy={ list.loading ? 'true' : undefined }
			>
				<caption className="zs-sr-only">{ caption }</caption>
				<thead>
					<tr>
						<th scope="col" className="zs-c-cb">
							<input
								type="checkbox"
								aria-label={
									isIp
										? __(
												'Select all blocked IPs',
												'zero-spam'
											)
										: __(
												'Select all blocked locations',
												'zero-spam'
											)
								}
								checked={ allShown }
								disabled={ ! rows.length }
								onChange={ ( e ) =>
									toggleAll( e.target.checked )
								}
							/>
						</th>
						{ header( __( 'Date', 'zero-spam' ), 'date_added' ) }
						{ isIp && header( __( 'Type', 'zero-spam' ), 'type' ) }
						{ header(
							isIp
								? __( 'IP address', 'zero-spam' )
								: __( 'Location', 'zero-spam' ),
							'key'
						) }
						{ ! isIp &&
							header( __( 'Type', 'zero-spam' ), 'type' ) }
						{ header( __( 'Starts', 'zero-spam' ), 'start' ) }
						{ header( __( 'Ends', 'zero-spam' ), 'end' ) }
						<th scope="col">{ __( 'Reason', 'zero-spam' ) }</th>
						<th scope="col" className="zs-act">
							<span className="zs-sr-only">
								{ __( 'Actions', 'zero-spam' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( block ) => (
						<tr
							key={ block.id }
							className={ cx(
								selected.has( block.id ) && 'zs-sel'
							) }
						>
							<td className="zs-c-cb">
								<input
									type="checkbox"
									checked={ selected.has( block.id ) }
									onChange={ ( e ) =>
										toggle( block.id, e.target.checked )
									}
									aria-label={ sprintf(
										/* translators: %s: IP address or location */
										__( 'Select %s', 'zero-spam' ),
										blockName( block )
									) }
								/>
							</td>
							<td className="zs-nowrap zs-c-date">
								{ siteDay( block.date_added ) }
							</td>
							{ isIp && (
								<td>
									<TypeBadge block={ block } />
								</td>
							) }
							<td className="zs-c-what">{ what( block ) }</td>
							{ ! isIp && (
								<td>
									<TypeBadge block={ block } />
								</td>
							) }
							<td className="zs-nowrap zs-c-date">
								{ siteDay( block.start ) }
							</td>
							<td className="zs-nowrap zs-c-date">
								{ endOf( block ) }
							</td>
							<td className="zs-c-reason">
								<span
									className="zs-clamp2"
									title={ block.reason || '' }
								>
									{ block.reason || '—' }
								</span>
							</td>
							<td className="zs-act">
								<div className="zs-actw">
									{ actions( block ) }
								</div>
							</td>
						</tr>
					) ) }
					{ empty && (
						<tr className="zs-empty-row">
							<td colSpan={ 8 }>{ empty }</td>
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
					{ rows.map( ( block ) => {
						const name = blockName( block );
						const meta = [
							! isIp &&
								`${ locationTypes()[ block.key_type ] || '' } ${ block.key }`,
							`${ siteDay( block.start ) } → ${
								'temporary' === block.type && block.end
									? siteDay( block.end )
									: __( 'never ends', 'zero-spam' )
							}`,
							block.reason ||
								__( 'No reason given', 'zero-spam' ),
						]
							.filter( Boolean )
							.join( ' · ' );
						return (
							<li
								key={ block.id }
								className={ cx(
									selected.has( block.id ) && 'zs-sel'
								) }
							>
								{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control -- The checkbox has its own accessible name (aria-label); the label only makes the whole cell a target. */ }
								<label className="zs-cbhit">
									<input
										type="checkbox"
										checked={ selected.has( block.id ) }
										onChange={ ( e ) =>
											toggle( block.id, e.target.checked )
										}
										aria-label={ sprintf(
											/* translators: %s: IP address or location */
											__( 'Select %s', 'zero-spam' ),
											name
										) }
									/>
								</label>
								<button
									type="button"
									className="zs-cardmain"
									aria-label={ sprintf(
										/* translators: 1: IP address or location, 2: Temporary or Permanent, 3: when it ends */
										__(
											'Edit block for %1$s, %2$s, ends %3$s',
											'zero-spam'
										),
										name,
										'permanent' === block.type
											? __( 'permanent', 'zero-spam' )
											: __( 'temporary', 'zero-spam' ),
										endOf( block )
									) }
									onClick={ () => setDrawer( { block } ) }
								>
									<span
										className={ cx(
											'zs-cm-t',
											isIp && 'zs-mono'
										) }
									>
										{ name }
									</span>
									<span className="zs-cm-m" title={ meta }>
										{ meta }
									</span>
								</button>
								<span className="zs-card-side">
									<span className="zs-cm-b">
										<TypeBadge block={ block } />
									</span>
									<span className="zs-cm-a">
										{ actions( block, true ) }
									</span>
								</span>
							</li>
						);
					} ) }
				</ul>
			) }
			{ empty }
		</div>
	);

	const sortValue = sortOptions( kind ).some( ( [ k ] ) => k === view.sort )
		? view.sort
		: DEFAULT_SORT;

	return (
		<div className="zs-rules-list">
			{ note }
			<div className="zs-tbar">
				<SearchBox
					id={ `zs-rq-${ kind }` }
					label={
						isIp
							? __( 'Search IPs', 'zero-spam' )
							: __( 'Search by country or region', 'zero-spam' )
					}
					value={ view.search }
					inputRef={ searchRef }
					onSearch={ ( search ) => change( { search } ) }
				/>
				<span className="zs-grow" />
				<Button
					ref={ addRef }
					icon="plus"
					onClick={ () => setDrawer( { block: null } ) }
				>
					{ isIp
						? __( 'Block an IP', 'zero-spam' )
						: __( 'Block a location', 'zero-spam' ) }
				</Button>
			</div>

			<div className="zs-bulk">
				{ ! wide && (
					<label
						className="zs-selall"
						htmlFor={ `zs-rall-${ kind }` }
					>
						<input
							id={ `zs-rall-${ kind }` }
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
						/* translators: %s: number of selected blocks */
						__( '%s selected', 'zero-spam' ),
						num( selected.size )
					) }
				</span>
				<Button
					variant="secondary"
					size="sm"
					icon="trash"
					aria-disabled={ selected.size ? undefined : 'true' }
					onClick={ selected.size ? removeSelected : undefined }
				>
					{ __( 'Remove selected', 'zero-spam' ) }
				</Button>
				<span className="zs-grow" />
				{ ! wide && (
					<span className="zs-cardsort">
						<label
							htmlFor={ `zs-rsort-${ kind }` }
							className="zs-sr-only"
						>
							{ __( 'Sort', 'zero-spam' ) }
						</label>
						<select
							id={ `zs-rsort-${ kind }` }
							className="zs-select zs-select-auto"
							value={ sortValue }
							onChange={ ( e ) =>
								change( { sort: e.target.value } )
							}
						>
							{ sortOptions( kind ).map( ( [ value, label ] ) => (
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
					{ loaded && noun( total ) }
				</span>
			</div>

			{ list.error && ! loaded && (
				<p className="zs-notice zs-notice-err" role="alert">
					{ errorMessage(
						list.error,
						__(
							'The list could not be loaded. Reload the page to try again.',
							'zero-spam'
						)
					) }
				</p>
			) }

			{ wide ? table : cards }

			<Pager
				pages={ pages }
				total={ total }
				label={ caption }
				onPage={ ( page ) => {
					setSelected( new Set() );
					setView( ( v ) => ( { ...v, page } ) );
				} }
			/>

			{ drawer && (
				<BlockForm
					open
					kind={ kind }
					block={ drawer.block }
					onClose={ () => setDrawer( null ) }
					onSaved={ onSaved }
					returnFocus={ () =>
						drawer.block
							? editButton( drawer.block.id )
							: addRef.current
					}
				/>
			) }
		</div>
	);
}
