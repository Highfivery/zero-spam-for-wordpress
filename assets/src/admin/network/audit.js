/**
 * The network audit log (prototype §9): every change to the network's
 * settings, templates and network-wide blocks, newest first, in plain
 * language, 25 per page. Filters: the action, the setting and the dates;
 * a user's or a site's name in a row shows only their entries.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMedia } from '../app/context';
import { byKey } from '../app/fields/settings-model';
import { Badge, Button } from '../app/primitives';
import { nowLocal, useResource } from '../data/api';
import { dayTime, num } from '../data/format';
import { EmptyState, Pager } from '../rules/parts';
import {
	AUDIT_PER_PAGE,
	PATHS,
	auditActions,
	auditFiltered,
	networkSettable,
	pageInfo,
} from './model';
import { LoadState } from './parts';

/** Width from which the log is a table. */
const TABLE_QUERY = '(min-width: 1100px)';

/**
 * The Audit log tab.
 */
export function Audit() {
	const wide = useMedia( TABLE_QUERY );
	const schema = useResource( PATHS.schema );
	const [ filters, setFilters ] = useState( {} );
	const [ page, setPage ] = useState( 1 );
	const log = useResource( PATHS.audit( filters, page ), { parse: false } );
	const entries = useMemo( () => byKey( schema.data ), [ schema.data ] );
	const keys = useMemo(
		() =>
			( schema.data?.settings || [] )
				.filter( networkSettable )
				.slice()
				.sort( ( a, b ) =>
					String( a.title ).localeCompare( String( b.title ) )
				),
		[ schema.data ]
	);

	const rows = Array.isArray( log.data ) ? log.data : [];
	const total = Number( log.headers[ 'x-wp-total' ] ?? rows.length ) || 0;
	const pages = pageInfo(
		total,
		Number( log.headers[ 'x-zerospam-page' ] ) || page,
		AUDIT_PER_PAGE,
		rows.length
	);
	const filtered = auditFiltered( filters );
	const now = new Date( nowLocal() );

	const change = ( next ) => {
		setPage( 1 );
		setFilters( ( current ) => ( { ...current, ...next } ) );
	};

	if ( undefined === log.data && ! filtered && 1 === page ) {
		return (
			<LoadState
				res={ log }
				what={ __( 'The audit log could not be loaded.', 'zero-spam' ) }
			/>
		);
	}

	const keyName = ( row ) =>
		row.key ? row.key_title || entries[ row.key ]?.title || row.key : '';

	const changes = ( row ) => {
		if ( 'set' === row.action && null !== row.old && null !== row.new ) {
			return (
				<>
					<code>{ row.old }</code> <span aria-hidden="true">→</span>
					<span className="zs-sr-only">
						{ __( 'to', 'zero-spam' ) }
					</span>{ ' ' }
					<code>{ row.new }</code>
				</>
			);
		}
		return row.summary;
	};

	const who = ( row ) =>
		row.user_id ? (
			<button
				type="button"
				className="zs-linkbtn"
				aria-label={ sprintf(
					/* translators: %s: user name */
					__( 'Show only changes by %s', 'zero-spam' ),
					row.user
				) }
				onClick={ () =>
					change( { user: row.user_id, userName: row.user } )
				}
			>
				{ row.user }
			</button>
		) : (
			row.user
		);

	const where = ( row ) =>
		row.site_name ? (
			<button
				type="button"
				className="zs-linkbtn"
				aria-label={ sprintf(
					/* translators: %s: site name */
					__( 'Show only changes on %s', 'zero-spam' ),
					row.site_name
				) }
				onClick={ () =>
					change( { site: row.site_id, siteName: row.site_name } )
				}
			>
				{ row.site_name }
			</button>
		) : (
			__( 'Network', 'zero-spam' )
		);

	const empty = rows.length ? null : (
		<EmptyState
			title={
				filtered
					? __( 'No audit entries match these filters.', 'zero-spam' )
					: __( 'No network changes yet.', 'zero-spam' )
			}
			hint={
				filtered
					? __(
							'Choose "All actions" or clear the filters to see everything.',
							'zero-spam'
						)
					: __(
							'Changes to network settings, templates and network-wide blocks are listed here.',
							'zero-spam'
						)
			}
		/>
	);

	const caption = __( 'Network audit log', 'zero-spam' );

	return (
		<>
			<p className="zs-hint">
				{ __(
					'Every change made to network settings, templates and network-wide blocks.',
					'zero-spam'
				) }
			</p>
			<div className="zs-tbar zs-auditbar">
				<div className="zs-field">
					<label htmlFor="zs-au-action" className="zs-lbl-strong">
						{ __( 'Action', 'zero-spam' ) }
					</label>
					<select
						id="zs-au-action"
						className="zs-select"
						value={ filters.action || '' }
						onChange={ ( e ) =>
							change( { action: e.target.value } )
						}
					>
						<option value="">
							{ __( 'All actions', 'zero-spam' ) }
						</option>
						{ auditActions().map( ( [ value, label ] ) => (
							<option key={ value } value={ value }>
								{ label }
							</option>
						) ) }
					</select>
				</div>
				<div className="zs-field">
					<label htmlFor="zs-au-key" className="zs-lbl-strong">
						{ __( 'Setting', 'zero-spam' ) }
					</label>
					<select
						id="zs-au-key"
						className="zs-select"
						value={ filters.key || '' }
						onChange={ ( e ) => change( { key: e.target.value } ) }
					>
						<option value="">
							{ __( 'All settings', 'zero-spam' ) }
						</option>
						{ keys.map( ( e ) => (
							<option key={ e.key } value={ e.key }>
								{ e.title || e.key }
							</option>
						) ) }
					</select>
				</div>
				<div className="zs-field">
					<label htmlFor="zs-au-after" className="zs-lbl-strong">
						{ __( 'From', 'zero-spam' ) }
					</label>
					<input
						id="zs-au-after"
						type="date"
						className="zs-input zs-mono"
						value={ filters.after || '' }
						max={ filters.before || undefined }
						onChange={ ( e ) =>
							change( { after: e.target.value } )
						}
					/>
				</div>
				<div className="zs-field">
					<label htmlFor="zs-au-before" className="zs-lbl-strong">
						{ __( 'To', 'zero-spam' ) }
					</label>
					<input
						id="zs-au-before"
						type="date"
						className="zs-input zs-mono"
						value={ filters.before || '' }
						min={ filters.after || undefined }
						onChange={ ( e ) =>
							change( { before: e.target.value } )
						}
					/>
				</div>
			</div>
			{ ( filters.user || filters.site || filtered ) && (
				<div
					className="zs-chips zs-aufilters"
					role="group"
					aria-label={ __( 'Filters', 'zero-spam' ) }
				>
					{ filters.user && (
						<Button
							variant="secondary"
							size="sm"
							iconAfter="close"
							aria-label={ sprintf(
								/* translators: %s: user name */
								__(
									'Remove the filter: changes by %s',
									'zero-spam'
								),
								filters.userName
							) }
							onClick={ () =>
								change( { user: '', userName: '' } )
							}
						>
							{ sprintf(
								/* translators: %s: user name */
								__( 'By %s', 'zero-spam' ),
								filters.userName
							) }
						</Button>
					) }
					{ filters.site && (
						<Button
							variant="secondary"
							size="sm"
							iconAfter="close"
							aria-label={ sprintf(
								/* translators: %s: site name */
								__(
									'Remove the filter: changes on %s',
									'zero-spam'
								),
								filters.siteName
							) }
							onClick={ () =>
								change( { site: '', siteName: '' } )
							}
						>
							{ sprintf(
								/* translators: %s: site name */
								__( 'On %s', 'zero-spam' ),
								filters.siteName
							) }
						</Button>
					) }
					{ filtered && (
						<Button
							variant="ghost"
							size="sm"
							onClick={ () => {
								setPage( 1 );
								setFilters( {} );
							} }
						>
							{ __( 'Clear filters', 'zero-spam' ) }
						</Button>
					) }
				</div>
			) }
			<div className="zs-bulk">
				<span className="zs-grow" />
				<span className="zs-badge" aria-live="polite">
					{ sprintf(
						/* translators: %s: number of entries */
						__( '%s entries', 'zero-spam' ),
						num( total )
					) }
				</span>
			</div>
			{ log.error && undefined === log.data && (
				<p className="zs-notice zs-notice-err" role="alert">
					{ __(
						'The audit log could not be loaded. Try again.',
						'zero-spam'
					) }
				</p>
			) }
			{ wide ? (
				<div className="zs-tbl-wrap">
					<table
						className="zs-t zs-audit-t"
						aria-busy={ log.loading ? 'true' : undefined }
					>
						<caption className="zs-sr-only">{ caption }</caption>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Date', 'zero-spam' ) }
								</th>
								<th scope="col">
									{ __( 'User', 'zero-spam' ) }
								</th>
								<th scope="col">
									{ __( 'Action', 'zero-spam' ) }
								</th>
								<th scope="col">
									{ __( 'Where', 'zero-spam' ) }
								</th>
								<th scope="col">
									{ __( 'Setting', 'zero-spam' ) }
								</th>
								<th scope="col">
									{ __( 'Changes', 'zero-spam' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( row ) => (
								<tr key={ row.id }>
									<td className="zs-nowrap zs-c-date">
										{ dayTime( row.date, now ) }
									</td>
									<td>{ who( row ) }</td>
									<td>
										<Badge>{ row.action_label }</Badge>
									</td>
									<td>{ where( row ) }</td>
									<td>
										{ row.key ? (
											<>
												<b>{ keyName( row ) }</b>
												<span className="zs-hint zs-mono zs-block">
													{ row.key }
												</span>
											</>
										) : (
											'—'
										) }
									</td>
									<td className="zs-c-changes">
										{ changes( row ) }
									</td>
								</tr>
							) ) }
							{ empty && (
								<tr className="zs-empty-row">
									<td colSpan={ 6 }>{ empty }</td>
								</tr>
							) }
						</tbody>
					</table>
				</div>
			) : (
				<div
					className="zs-tbl-wrap zs-cards-wrap"
					aria-busy={ log.loading ? 'true' : undefined }
				>
					{ rows.length > 0 && (
						<ul
							className="zs-cards zs-cards-plain"
							aria-label={ caption }
						>
							{ rows.map( ( row ) => (
								<li key={ row.id }>
									<div className="zs-cardmain">
										<span className="zs-cm-t">
											{ row.key
												? keyName( row )
												: row.action_label }
										</span>
										<span className="zs-cm-m">
											{ changes( row ) }
										</span>
										<span className="zs-cm-m">
											{ dayTime( row.date, now ) } ·{ ' ' }
											{ who( row ) } · { where( row ) }
										</span>
									</div>
									<span className="zs-card-side">
										<span className="zs-cm-b">
											<Badge>{ row.action_label }</Badge>
										</span>
									</span>
								</li>
							) ) }
						</ul>
					) }
					{ empty }
				</div>
			) }
			<Pager
				pages={ pages }
				total={ total }
				label={ caption }
				onPage={ ( next ) => {
					setPage( next );
					document.getElementById( 'zs-net-body' )?.focus();
				} }
			/>
		</>
	);
}
