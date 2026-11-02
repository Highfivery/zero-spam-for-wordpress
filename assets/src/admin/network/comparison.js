/**
 * The network Comparison (prototype §9): for each setting the network
 * sets, its value on a page of sites (five at a time, so a large network
 * is never read at once) and whether it is the network's, changed by the
 * site, or locked. Sorted by name or by the most site changes.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMedia } from '../app/context';
import { byKey } from '../app/fields/settings-model';
import { Badge, Button } from '../app/primitives';
import { useResource } from '../data/api';
import { num } from '../data/format';
import { EmptyState, Pager } from '../rules/parts';
import {
	COMPARISON_PER_PAGE,
	PATHS,
	comparisonCell,
	comparisonRows,
	pageInfo,
	valueText,
} from './model';
import { LoadState } from './parts';

/** Width from which the comparison is a table. */
const TABLE_QUERY = '(min-width: 1100px)';

/**
 * A badge for a cell or a row.
 *
 * @param {Object} props      Props.
 * @param {string} props.tone lock, warn or ''.
 * @param {string} props.text Label.
 */
const CellBadge = ( { tone, text } ) =>
	'lock' === tone ? (
		<Badge className="zs-badge-lock" icon="lock">
			{ text }
		</Badge>
	) : (
		<Badge tone={ tone || undefined }>{ text }</Badge>
	);

/**
 * @param {Object}                          props        Props.
 * @param {(...args: unknown[]) => unknown} props.setTab Opens another tab.
 */
export function Comparison( { setTab } ) {
	const wide = useMedia( TABLE_QUERY );
	const schema = useResource( PATHS.schema );
	const [ page, setPage ] = useState( 1 );
	const [ sort, setSort ] = useState( 'name' );
	const data = useResource( PATHS.comparison( page ), { parse: false } );
	const entries = useMemo( () => byKey( schema.data ), [ schema.data ] );
	const rows = useMemo(
		() => comparisonRows( data.data, sort ),
		[ data.data, sort ]
	);

	if ( undefined === data.data ) {
		return (
			<LoadState
				res={ data }
				what={ __(
					'The comparison could not be loaded.',
					'zero-spam'
				) }
			/>
		);
	}

	const sites = Array.isArray( data.data.sites ) ? data.data.sites : [];
	const total = Number( data.headers[ 'x-wp-total' ] ?? sites.length ) || 0;
	const pages = pageInfo( total, page, COMPARISON_PER_PAGE, sites.length );
	const caption = __( 'Settings by site', 'zero-spam' );

	if ( ! rows.length ) {
		return (
			<div className="zs-tile">
				<EmptyState
					title={ __(
						'The network sets no values yet.',
						'zero-spam'
					) }
					hint={ __(
						'Set network values on the Settings tab; then compare them with each site here.',
						'zero-spam'
					) }
				/>
				<div>
					<Button
						variant="secondary"
						onClick={ () => setTab( 'settings' ) }
					>
						{ __( 'Open network settings', 'zero-spam' ) }
					</Button>
				</div>
			</div>
		);
	}

	const rowMeta = ( row ) => {
		const entry = entries[ row.key ];
		const net = sprintf(
			/* translators: %s: the network value */
			__( 'Network: %s', 'zero-spam' ),
			row.has_value
				? valueText( entry || row, row.network )
				: __( 'not set', 'zero-spam' )
		);
		return row.locked ? `${ net } · ${ __( 'locked', 'zero-spam' ) }` : net;
	};

	const rowBadge = ( row ) => {
		if ( row.locked ) {
			return (
				<CellBadge tone="lock" text={ __( 'Locked', 'zero-spam' ) } />
			);
		}
		if ( row.custom > 0 ) {
			return (
				<CellBadge
					tone="warn"
					text={ sprintf(
						/* translators: %s: number of sites */
						_n(
							'%s site changed it',
							'%s sites changed it',
							row.custom,
							'zero-spam'
						),
						num( row.custom )
					) }
				/>
			);
		}
		return (
			<CellBadge
				tone=""
				text={ __( 'Every site uses it', 'zero-spam' ) }
			/>
		);
	};

	return (
		<>
			<p className="zs-hint">
				{ sprintf(
					/* translators: 1: first site shown, 2: last site shown, 3: number of sites */
					__(
						'Compare the network’s settings with each site. Showing sites %1$s–%2$s of %3$s.',
						'zero-spam'
					),
					num( pages.from ),
					num( pages.to ),
					num( total )
				) }
			</p>
			<div className="zs-bulk">
				<label htmlFor="zs-cmp-sort" className="zs-sr-only">
					{ __( 'Sort', 'zero-spam' ) }
				</label>
				<select
					id="zs-cmp-sort"
					className="zs-select zs-select-auto"
					value={ sort }
					onChange={ ( e ) => setSort( e.target.value ) }
				>
					<option value="name">
						{ sprintf(
							/* translators: %s: sort order */
							__( 'Sort: %s', 'zero-spam' ),
							__( 'Setting A–Z', 'zero-spam' )
						) }
					</option>
					<option value="overrides">
						{ sprintf(
							/* translators: %s: sort order */
							__( 'Sort: %s', 'zero-spam' ),
							__( 'Most site changes first', 'zero-spam' )
						) }
					</option>
				</select>
				<span className="zs-grow" />
				<span className="zs-badge">
					{ sprintf(
						/* translators: %s: number of settings */
						_n(
							'%s setting',
							'%s settings',
							rows.length,
							'zero-spam'
						),
						num( rows.length )
					) }
				</span>
			</div>
			{ wide ? (
				<div className="zs-tbl-wrap">
					<table
						className="zs-t zs-cmp-t"
						aria-busy={ data.loading ? 'true' : undefined }
					>
						<caption className="zs-sr-only">{ caption }</caption>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Setting', 'zero-spam' ) }
								</th>
								{ sites.map( ( site ) => (
									<th scope="col" key={ site.id }>
										{ site.name || site.url }
										<span className="zs-hint zs-mono zs-block">
											{ site.url }
										</span>
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( row ) => (
								<tr key={ row.key }>
									<th scope="row">
										<b>{ row.title }</b>
										<span className="zs-hint zs-block">
											{ rowMeta( row ) }
										</span>
									</th>
									{ sites.map( ( site ) => {
										const cell = comparisonCell(
											row,
											row.sites?.[ site.id ],
											entries[ row.key ]
										);
										return (
											<td key={ site.id }>
												<span className="zs-cmp-v">
													{ cell.text }
												</span>
												<CellBadge
													tone={ cell.tone }
													text={ cell.badge }
												/>
											</td>
										);
									} ) }
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) : (
				<div className="zs-tbl-wrap zs-cards-wrap">
					<ul
						className="zs-cards zs-cards-plain"
						aria-label={ caption }
					>
						{ rows.map( ( row ) => (
							<li key={ row.key }>
								<div className="zs-cardmain">
									<span className="zs-cm-t">
										{ row.title }
									</span>
									<span className="zs-cm-m">
										{ rowMeta( row ) }
									</span>
									<ul className="zs-cmp-sites">
										{ sites.map( ( site ) => {
											const cell = comparisonCell(
												row,
												row.sites?.[ site.id ],
												entries[ row.key ]
											);
											return (
												<li key={ site.id }>
													<span className="zs-cmp-site">
														{ site.name ||
															site.url }
													</span>
													<span className="zs-cmp-v">
														{ cell.text }
													</span>
													<CellBadge
														tone={ cell.tone }
														text={ cell.badge }
													/>
												</li>
											);
										} ) }
									</ul>
								</div>
								<span className="zs-card-side">
									<span className="zs-cm-b">
										{ rowBadge( row ) }
									</span>
								</span>
							</li>
						) ) }
					</ul>
				</div>
			) }
			<Pager
				pages={ pages }
				total={ total }
				label={ __( 'Sites compared', 'zero-spam' ) }
				onPage={ ( next ) => {
					setPage( next );
					document.getElementById( 'zs-net-body' )?.focus();
				} }
			/>
			{ data.data.capped && (
				<p className="zs-hint">
					{ sprintf(
						/* translators: %s: number of sites */
						__(
							'Site changes are counted on the first %s sites.',
							'zero-spam'
						),
						num( data.data.checked )
					) }
				</p>
			) }
		</>
	);
}
