/**
 * Network stats (prototype §9; no world map, no external assets): the
 * network's totals for a period, each site's detections (paged, with its
 * change from the period before and a protection level that says
 * "Enhanced" only when its license key was found valid), the addresses
 * seen on several sites with "Block network-wide", and the addresses
 * blocked on every site (with Undo).
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMedia } from '../app/context';
import { Badge, Button, Icon } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { NS, nowLocal, request, useResource } from '../data/api';
import { day, num } from '../data/format';
import { checkBlockIp } from '../data/validate';
import { Country, EmptyState, Pager } from '../rules/parts';
import {
	BLOCKS_PER_PAGE,
	PATHS,
	PERIODS,
	STATS_PER_PAGE,
	levelBadge,
	pageInfo,
	periodNames,
	statsCsv,
	trendBadge,
} from './model';
import { ActionResult, LoadState, NumberTile, errorText } from './parts';

/** Width from which the lists are tables. */
const TABLE_QUERY = '(min-width: 1100px)';

/**
 * The sort choices of the site comparison.
 *
 * @return {Array<[string, string]>} Values and names.
 */
const sortOptions = () => [
	[ 'blocked:desc', __( 'Most spam first', 'zero-spam' ) ],
	[ 'name:asc', __( 'Site A–Z', 'zero-spam' ) ],
	[ 'trend:desc', __( 'Biggest increase first', 'zero-spam' ) ],
];

/**
 * Downloads text as a file.
 *
 * @param {string} text Contents.
 * @param {string} name File name.
 * @param {string} type Media type.
 */
const saveFile = ( text, name, type ) => {
	const blob = new window.Blob( [ text ], { type } );
	const url = window.URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = name;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.URL.revokeObjectURL( url );
};

/**
 * The addresses blocked on every site: list, Remove (with Undo), and a form
 * to add one.
 *
 * @param {Object}                          props           Props.
 * @param {Object}                          props.blocks    useResource() of network/blocks.
 * @param {number}                          props.page      Page.
 * @param {(...args: unknown[]) => unknown} props.setPage   Changes the page.
 * @param {(...args: unknown[]) => unknown} props.onChanged After a block was added or removed.
 * @param {(...args: unknown[]) => unknown} props.remove    Removes a block (with Undo).
 */
function NetworkBlocks( { blocks, page, setPage, onChanged, remove } ) {
	const [ ip, setIp ] = useState( '' );
	const [ reason, setReason ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	const input = useRef( null );
	const rows = Array.isArray( blocks.data ) ? blocks.data : [];
	const total = Number( blocks.headers[ 'x-wp-total' ] ?? rows.length ) || 0;
	const pages = pageInfo(
		total,
		Number( blocks.headers[ 'x-zerospam-page' ] ) || page,
		BLOCKS_PER_PAGE,
		rows.length
	);
	const now = new Date( nowLocal() );

	const add = ( event ) => {
		event.preventDefault();
		const checked = checkBlockIp( ip );
		if ( checked.error ) {
			setError( checked.error );
			input.current?.focus();
			return;
		}
		setError( '' );
		setBusy( true );
		request( {
			path: `${ NS }/network/blocks`,
			method: 'POST',
			data: { ip: checked.value, type: 'permanent', reason },
		} )
			.then( ( saved ) => {
				setIp( '' );
				setReason( '' );
				setResult( {
					ok: true,
					text: sprintf(
						/* translators: %s: IP address */
						__( '%s is blocked on every site.', 'zero-spam' ),
						saved?.ip || checked.value
					),
				} );
				onChanged();
			} )
			.catch( ( err ) => {
				const params = err?.data?.params;
				if ( params?.ip ) {
					setError( params.ip );
					input.current?.focus();
				} else {
					setResult( {
						ok: false,
						text: errorText(
							err,
							__(
								'The block could not be saved. Please try again.',
								'zero-spam'
							)
						),
					} );
				}
			} )
			.finally( () => setBusy( false ) );
	};

	return (
		<section className="zs-tile zs-s12" aria-labelledby="zs-nbl-h">
			<h2 className="zs-tile-title" id="zs-nbl-h">
				{ __( 'Blocked on every site', 'zero-spam' ) }
			</h2>
			<p className="zs-hint">
				{ __(
					'These addresses are blocked on every site in your network. Site administrators see them as “Blocked by your network” and cannot remove them; an address a site allows is still let in there. Zero Spam checks these blocks itself on each visit: they are not written to .htaccess, even on sites that block with .htaccess.',
					'zero-spam'
				) }
			</p>
			<form
				className="zs-tbar zs-nblock-form"
				onSubmit={ add }
				noValidate
			>
				<div className="zs-field">
					<label htmlFor="zs-nbl-ip">
						{ __( 'IP address', 'zero-spam' ) }
					</label>
					<input
						ref={ input }
						id="zs-nbl-ip"
						className="zs-input zs-mono"
						value={ ip }
						placeholder={ __( 'e.g. 203.0.113.9', 'zero-spam' ) }
						autoComplete="off"
						spellCheck="false"
						aria-invalid={ error ? 'true' : 'false' }
						aria-describedby="zs-nbl-ip-e"
						onChange={ ( e ) => setIp( e.target.value ) }
					/>
					<span className="zs-ferr" id="zs-nbl-ip-e">
						{ error }
					</span>
				</div>
				<div className="zs-field zs-grow">
					<label htmlFor="zs-nbl-reason">
						{ __( 'Reason (optional)', 'zero-spam' ) }
					</label>
					<input
						id="zs-nbl-reason"
						className="zs-input"
						value={ reason }
						maxLength={ 255 }
						placeholder={ __(
							'e.g. Spammed several sites',
							'zero-spam'
						) }
						onChange={ ( e ) => setReason( e.target.value ) }
					/>
				</div>
				<Button
					type="submit"
					variant="secondary"
					icon="ban"
					disabled={ busy }
				>
					{ __( 'Block on every site', 'zero-spam' ) }
				</Button>
			</form>
			<ActionResult result={ result } />
			{ rows.length ? (
				<ul
					className="zs-cards zs-cards-plain zs-nblocks"
					aria-label={ __(
						'Addresses blocked on every site',
						'zero-spam'
					) }
				>
					{ rows.map( ( block ) => (
						<li key={ block.id }>
							<div className="zs-cardmain">
								<span className="zs-cm-t zs-mono">
									{ block.ip }
								</span>
								<span className="zs-cm-m">
									{ [
										block.reason ||
											__(
												'No reason given',
												'zero-spam'
											),
										block.date_added &&
											sprintf(
												/* translators: %s: date */
												__( 'Added %s', 'zero-spam' ),
												day( block.date_added, now )
											),
										block.added_by &&
											sprintf(
												/* translators: %s: user name */
												__( 'by %s', 'zero-spam' ),
												block.added_by
											),
									]
										.filter( Boolean )
										.join( ' · ' ) }
								</span>
							</div>
							<span className="zs-card-side">
								<span className="zs-cm-b">
									{ block.active ? (
										<Badge tone="danger">
											{ __(
												'Blocked on all sites',
												'zero-spam'
											) }
										</Badge>
									) : (
										<Badge>
											{ __( 'Ended', 'zero-spam' ) }
										</Badge>
									) }
								</span>
								<span className="zs-cm-a">
									<Button
										variant="ghost"
										size="sm"
										icon="trash"
										aria-label={ sprintf(
											/* translators: %s: IP address */
											__(
												'Unblock %s on every site',
												'zero-spam'
											),
											block.ip
										) }
										onClick={ () => remove( block ) }
									>
										{ __( 'Unblock', 'zero-spam' ) }
									</Button>
								</span>
							</span>
						</li>
					) ) }
				</ul>
			) : (
				undefined !== blocks.data && (
					<EmptyState
						title={ __(
							'No address is blocked on every site.',
							'zero-spam'
						) }
						hint={ __(
							'Block one here, or from the multi-site attackers above.',
							'zero-spam'
						) }
					/>
				)
			) }
			<Pager
				pages={ pages }
				total={ total }
				label={ __( 'Addresses blocked on every site', 'zero-spam' ) }
				onPage={ setPage }
			/>
		</section>
	);
}

/**
 * The Network stats tab.
 */
export function NetworkStats() {
	const { toast } = useFeedback();
	const wide = useMedia( TABLE_QUERY );
	const [ period, setPeriod ] = useState( '30' );
	const [ page, setPage ] = useState( 1 );
	const [ sort, setSort ] = useState( 'blocked:desc' );
	const [ blockPage, setBlockPage ] = useState( 1 );
	const stats = useResource( PATHS.stats( period, page, sort ), {
		parse: false,
	} );
	const blocks = useResource( PATHS.blocks( blockPage ), { parse: false } );
	const names = periodNames();

	if ( undefined === stats.data ) {
		return (
			<LoadState
				res={ stats }
				what={ __(
					'The network statistics could not be loaded.',
					'zero-spam'
				) }
			/>
		);
	}

	const data = stats.data;
	const sites = Array.isArray( data.sites ) ? data.sites : [];
	const total = Number( stats.headers[ 'x-wp-total' ] ?? sites.length ) || 0;
	const pages = pageInfo(
		total,
		Number( stats.headers[ 'x-zerospam-page' ] ) || page,
		STATS_PER_PAGE,
		sites.length
	);
	const attackers = Array.isArray( data.attackers ) ? data.attackers : [];

	const blockIp = ( ip ) =>
		request( {
			path: `${ NS }/network/blocks`,
			method: 'POST',
			data: {
				ip,
				type: 'permanent',
				reason: __( 'Seen on several sites', 'zero-spam' ),
			},
		} )
			.then( ( saved ) => {
				stats.reload();
				blocks.reload();
				toast(
					sprintf(
						/* translators: %s: IP address */
						__( '%s is blocked on all sites', 'zero-spam' ),
						ip
					),
					{
						undo: () =>
							request( {
								path: `${ NS }/network/blocks/${ saved.id }`,
								method: 'DELETE',
							} ).then( () => {
								stats.reload();
								blocks.reload();
								toast( __( 'Undone', 'zero-spam' ) );
							} ),
					}
				);
			} )
			.catch( ( error ) =>
				toast(
					errorText(
						error,
						__(
							'The block could not be saved. Please try again.',
							'zero-spam'
						)
					)
				)
			);

	const unblock = ( block ) =>
		request( {
			path: `${ NS }/network/blocks/${ block.id }`,
			method: 'DELETE',
		} )
			.then( ( answer ) => {
				stats.reload();
				blocks.reload();
				toast(
					sprintf(
						/* translators: %s: IP address */
						__( '%s unblocked on all sites', 'zero-spam' ),
						block.ip
					),
					answer?.undo_token
						? {
								undo: () =>
									request( {
										path: `${ NS }/undo/${ answer.undo_token }`,
										method: 'POST',
									} )
										.then( () => {
											stats.reload();
											blocks.reload();
											toast(
												__(
													'Block restored',
													'zero-spam'
												)
											);
										} )
										.catch( ( error ) =>
											toast(
												errorText(
													error,
													__(
														'The block could not be restored.',
														'zero-spam'
													)
												)
											)
										),
							}
						: {}
				);
			} )
			.catch( ( error ) =>
				toast(
					errorText(
						error,
						__(
							'The block could not be removed. Please try again.',
							'zero-spam'
						)
					)
				)
			);

	const exportCsv = () => {
		saveFile(
			statsCsv( sites ),
			`zero-spam-network-stats-${ period }.csv`,
			'text/csv;charset=utf-8'
		);
		toast( __( 'Network statistics exported', 'zero-spam' ) );
	};

	const siteCell = ( site ) => (
		<>
			<b className="zs-block">{ site.name || site.url }</b>
			<span className="zs-dom zs-mono" title={ site.url }>
				{ site.url }
			</span>
		</>
	);

	const trend = ( site ) => {
		const t = trendBadge( site.trend );
		return (
			<Badge tone={ t.tone || undefined }>
				{ t.text }
				<span className="zs-sr-only"> { t.sr }</span>
			</Badge>
		);
	};

	const level = ( site ) => {
		const l = levelBadge( site.level );
		return <Badge tone={ l.tone || undefined }>{ l.text }</Badge>;
	};

	const attackerAction = ( a ) =>
		a.blocked ? (
			<>
				<Badge tone="danger">
					{ __( 'Blocked on all sites', 'zero-spam' ) }
				</Badge>{ ' ' }
				<Button
					variant="ghost"
					size="sm"
					aria-label={ sprintf(
						/* translators: %s: IP address */
						__( 'Unblock %s on every site', 'zero-spam' ),
						a.ip
					) }
					onClick={ () => unblock( { id: a.block_id, ip: a.ip } ) }
				>
					{ __( 'Unblock', 'zero-spam' ) }
				</Button>
			</>
		) : (
			<Button
				variant="secondary"
				size="sm"
				icon="ban"
				data-zs-netblock={ a.ip }
				aria-label={ sprintf(
					/* translators: %s: IP address */
					__( 'Block %s on every site', 'zero-spam' ),
					a.ip
				) }
				onClick={ () => blockIp( a.ip ) }
			>
				{ __( 'Block network-wide', 'zero-spam' ) }
			</Button>
		);

	return (
		<>
			<div className="zs-tbar">
				<label htmlFor="zs-ns-period" className="zs-lbl-strong">
					{ __( 'Time period', 'zero-spam' ) }
				</label>
				<select
					id="zs-ns-period"
					className="zs-select zs-select-auto"
					value={ period }
					onChange={ ( e ) => {
						setPeriod( e.target.value );
						setPage( 1 );
					} }
				>
					{ PERIODS.map( ( p ) => (
						<option key={ p } value={ p }>
							{ names[ p ] }
						</option>
					) ) }
				</select>
				<span className="zs-grow" />
				<Button
					variant="secondary"
					size="sm"
					icon="down"
					onClick={ exportCsv }
					disabled={ ! sites.length }
				>
					{ __( 'Export CSV', 'zero-spam' ) }
				</Button>
			</div>
			<div className="zs-wall">
				<NumberTile
					label={ __( 'Total spam blocked', 'zero-spam' ) }
					value={ data.totals?.blocked }
				/>
				<NumberTile
					label={ __( 'Unique IP addresses', 'zero-spam' ) }
					value={ data.totals?.unique_ips }
				/>
				<NumberTile
					label={ __( 'Sites', 'zero-spam' ) }
					value={ data.totals?.sites }
				/>
				<NumberTile
					label={ __( 'Spam types', 'zero-spam' ) }
					value={ data.totals?.types }
				/>

				<section className="zs-tile zs-s12" aria-labelledby="zs-nsc-h">
					<h2 className="zs-tile-title" id="zs-nsc-h">
						{ __( 'Site comparison', 'zero-spam' ) }
					</h2>
					<div className="zs-bulk">
						<label htmlFor="zs-nsc-sort" className="zs-sr-only">
							{ __( 'Sort', 'zero-spam' ) }
						</label>
						<select
							id="zs-nsc-sort"
							className="zs-select zs-select-auto"
							value={ sort }
							onChange={ ( e ) => {
								setSort( e.target.value );
								setPage( 1 );
							} }
						>
							{ sortOptions().map( ( [ value, label ] ) => (
								<option key={ value } value={ value }>
									{ sprintf(
										/* translators: %s: sort order */
										__( 'Sort: %s', 'zero-spam' ),
										label
									) }
								</option>
							) ) }
						</select>
						<span className="zs-grow" />
						<span className="zs-badge">
							{ sprintf(
								/* translators: %s: number of sites */
								_n( '%s site', '%s sites', total, 'zero-spam' ),
								num( total )
							) }
						</span>
					</div>
					{ wide ? (
						<div className="zs-tbl-wrap">
							<table
								className="zs-t zs-nstats-t"
								aria-busy={ stats.loading ? 'true' : undefined }
							>
								<caption className="zs-sr-only">
									{ sprintf(
										/* translators: %s: time period, such as Last 30 days */
										__( 'Spam by site, %s', 'zero-spam' ),
										names[ period ]
									) }
								</caption>
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Site', 'zero-spam' ) }
										</th>
										<th scope="col" className="zs-num">
											{ __(
												'Spam blocked',
												'zero-spam'
											) }
										</th>
										<th scope="col" className="zs-num">
											{ __( 'Unique IPs', 'zero-spam' ) }
										</th>
										<th scope="col" className="zs-col-tt">
											{ __( 'Top type', 'zero-spam' ) }
										</th>
										<th scope="col">
											{ __( 'Top country', 'zero-spam' ) }
										</th>
										<th scope="col">
											{ __( 'Trend', 'zero-spam' ) }
										</th>
										<th scope="col">
											{ __( 'Level', 'zero-spam' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ sites.map( ( site ) => (
										<tr key={ site.id }>
											<td className="zs-site">
												{ siteCell( site ) }
											</td>
											<td className="zs-mono zs-num">
												{ num( site.blocked ) }
											</td>
											<td className="zs-mono zs-num">
												{ num( site.unique_ips ) }
											</td>
											<td className="zs-col-tt">
												{ site.top_type_label || '—' }
											</td>
											<td>
												{ site.top_country ? (
													<Country
														code={
															site.top_country
														}
														name={
															site.country_name
														}
													/>
												) : (
													'—'
												) }
											</td>
											<td>{ trend( site ) }</td>
											<td>{ level( site ) }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) : (
						<ul
							className="zs-cards zs-cards-plain"
							aria-label={ __( 'Spam by site', 'zero-spam' ) }
						>
							{ sites.map( ( site ) => (
								<li key={ site.id }>
									<div className="zs-cardmain">
										<span className="zs-cm-t">
											{ site.name || site.url }
										</span>
										<span className="zs-cm-m">
											{ [
												sprintf(
													/* translators: %s: number of detections */
													__(
														'%s blocked',
														'zero-spam'
													),
													num( site.blocked )
												),
												sprintf(
													/* translators: %s: number of addresses */
													__( '%s IPs', 'zero-spam' ),
													num( site.unique_ips )
												),
												site.country_name,
											]
												.filter( Boolean )
												.join( ' · ' ) }
										</span>
									</div>
									<span className="zs-card-side">
										<span className="zs-cm-b">
											{ trend( site ) } { level( site ) }
										</span>
									</span>
								</li>
							) ) }
						</ul>
					) }
					{ ! sites.length && (
						<EmptyState
							title={ __(
								'No site logs to compare yet.',
								'zero-spam'
							) }
							hint={ __(
								'Detections appear here once sites block spam.',
								'zero-spam'
							) }
						/>
					) }
					<Pager
						pages={ pages }
						total={ total }
						label={ __( 'Sites', 'zero-spam' ) }
						onPage={ setPage }
					/>
					{ data.capped && (
						<p className="zs-hint">
							{ sprintf(
								/* translators: %s: number of sites */
								__(
									'Statistics cover the first %s sites.',
									'zero-spam'
								),
								num( data.checked )
							) }
						</p>
					) }
				</section>

				<section className="zs-tile zs-s12" aria-labelledby="zs-nma-h">
					<h2 className="zs-tile-title" id="zs-nma-h">
						{ __( 'Multi-site attackers', 'zero-spam' ) }
					</h2>
					<p className="zs-hint">
						{ sprintf(
							/* translators: %s: number of days */
							__(
								'IP addresses that tried to spam more than one site in your network in the last %s days.',
								'zero-spam'
							),
							num( data.attacker_days || 7 )
						) }
					</p>
					{ attackers.length > 0 && wide && (
						<div className="zs-tbl-wrap">
							<table className="zs-t">
								<caption className="zs-sr-only">
									{ __(
										'Multi-site attackers',
										'zero-spam'
									) }
								</caption>
								<thead>
									<tr>
										<th scope="col">
											{ __( 'IP address', 'zero-spam' ) }
										</th>
										<th scope="col">
											{ __( 'Country', 'zero-spam' ) }
										</th>
										<th scope="col" className="zs-num">
											{ __(
												'Sites attacked',
												'zero-spam'
											) }
										</th>
										<th scope="col" className="zs-act">
											<span className="zs-sr-only">
												{ __( 'Action', 'zero-spam' ) }
											</span>
										</th>
									</tr>
								</thead>
								<tbody>
									{ attackers.map( ( a ) => (
										<tr key={ a.ip }>
											<td className="zs-mono">
												{ a.ip }
											</td>
											<td>
												{ a.country ? (
													<Country
														code={ a.country }
														name={ a.country_name }
													/>
												) : (
													'—'
												) }
											</td>
											<td className="zs-mono zs-num">
												{ num( a.sites ) }
											</td>
											<td className="zs-act">
												<div className="zs-actw">
													{ attackerAction( a ) }
												</div>
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
					{ attackers.length > 0 && ! wide && (
						<ul
							className="zs-cards zs-cards-plain"
							aria-label={ __(
								'Multi-site attackers',
								'zero-spam'
							) }
						>
							{ attackers.map( ( a ) => (
								<li key={ a.ip }>
									<div className="zs-cardmain">
										<span className="zs-cm-t zs-mono">
											{ a.ip }
										</span>
										<span className="zs-cm-m">
											{ [
												a.country_name,
												sprintf(
													/* translators: %s: number of sites */
													_n(
														'%s site attacked',
														'%s sites attacked',
														a.sites,
														'zero-spam'
													),
													num( a.sites )
												),
											]
												.filter( Boolean )
												.join( ' · ' ) }
										</span>
									</div>
									<span className="zs-card-side">
										<span className="zs-cm-a">
											{ attackerAction( a ) }
										</span>
									</span>
								</li>
							) ) }
						</ul>
					) }
					{ ! attackers.length && (
						<p className="zs-empty-inline">
							<Icon name="check" size="sm" />{ ' ' }
							{ __(
								'No address tried more than one site this week.',
								'zero-spam'
							) }
						</p>
					) }
				</section>

				<NetworkBlocks
					blocks={ blocks }
					page={ blockPage }
					setPage={ setBlockPage }
					remove={ unblock }
					onChanged={ () => {
						blocks.reload();
						stats.reload();
					} }
				/>
			</div>
		</>
	);
}
