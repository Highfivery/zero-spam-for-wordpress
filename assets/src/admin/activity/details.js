/**
 * A detection's details in a drawer (prototype §4.3): why it was blocked,
 * where it came from (with an "Open in Google Maps" link, no embedded map),
 * everything the visitor sent as plain text, the Zero Spam reputation
 * (Enhanced), and Block IP, Edit block, Always allow and Delete entry.
 * Previous and Next (Alt+Up and Alt+Down) move through the rows shown.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { Badge, Button, ExtLink, Icon } from '../app/primitives';
import { Drawer } from '../app/primitives/drawer';
import { boot } from '../data/api';
import { day, dayTime, num } from '../data/format';
import { utm } from '../data/routes';
import { typeIcon } from '../overview/tiles';
import { Country, TypeIcon } from './parts';
import { confidenceOf, detailPairs } from './details-data';
import { itemPath } from './query';
import { useQuery } from './use-query';

/**
 * Whether a field has a value (0 is a value: a coordinate on the equator).
 *
 * @param {unknown} value Value.
 * @return {boolean} Has one.
 */
const hasValue = ( value ) =>
	null !== value && undefined !== value && '' !== value;

/**
 * One sentence on why an entry was blocked.
 *
 * @param {Object} row Entry.
 * @return {string} Sentence.
 */
function whySentence( row ) {
	if ( row.network ) {
		return __(
			'The Zero Spam network has seen this address send spam or attacks to other sites, so it was stopped before it reached yours.',
			'zero-spam'
		);
	}
	if ( row.failed_label ) {
		return sprintf(
			/* translators: 1: check, such as Honeypot, 2: what was checked, such as Comment */
			__(
				'It failed the %1$s check, so Zero Spam stopped it (%2$s).',
				'zero-spam'
			),
			row.failed_label,
			row.type_label
		);
	}
	return sprintf(
		/* translators: %s: what was checked, such as Comment */
		__( 'Zero Spam stopped this request (%s).', 'zero-spam' ),
		row.type_label
	);
}

/**
 * @param {Object}                          props             Props.
 * @param {number}                          props.id          Entry shown.
 * @param {Object}                          [props.row]       Its list row (shown while the details load).
 * @param {Object}                          [props.nav]       { index, total, prev, next }.
 * @param {(...args: unknown[]) => unknown} props.onStep      Receives the entry to show.
 * @param {(...args: unknown[]) => unknown} props.onClose     Closes.
 * @param {(...args: unknown[]) => unknown} props.onBlock     Receives ( ip, block|null ).
 * @param {(...args: unknown[]) => unknown} props.onAllow     Receives ( ip, setAllowed ).
 * @param {(...args: unknown[]) => unknown} props.onDelete    Receives the entry.
 * @param {(...args: unknown[]) => unknown} props.returnFocus Where focus goes on close.
 * @param {number}                          props.version     Changes when the entry may have changed (reloads it).
 */
export function DetailsDrawer( {
	id,
	row,
	nav,
	onStep,
	onClose,
	onBlock,
	onAllow,
	onDelete,
	returnFocus,
	version,
} ) {
	const { enhanced, openCompare } = useApp();
	const detail = useQuery( itemPath( id ) );

	// After a change elsewhere (a block saved), read the entry again.
	useEffect( () => {
		if ( version ) {
			detail.reload();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Only when the version changes.
	}, [ version ] );
	const item = detail.data && detail.data.id === id ? detail.data : null;
	const shown = item || row || null;
	const ip = shown?.ip || '';
	const missing = detail.error && 404 === detail.error?.data?.status;

	const position =
		nav && nav.index >= 0
			? {
					label: sprintf(
						/* translators: 1: position of the entry, 2: number of entries shown */
						__( '%1$s of %2$s', 'zero-spam' ),
						num( nav.index + 1 ),
						num( nav.total )
					),
					onPrev: null !== nav.prev ? () => onStep( nav.prev ) : null,
					onNext: null !== nav.next ? () => onStep( nav.next ) : null,
					groupLabel: __( 'Move through detections', 'zero-spam' ),
					prevLabel: __( 'Previous detection (Alt+Up)', 'zero-spam' ),
					nextLabel: __( 'Next detection (Alt+Down)', 'zero-spam' ),
				}
			: undefined;

	const lookup =
		ip && boot.urls.ipLookup
			? utm(
					`${ boot.urls.ipLookup }${ encodeURIComponent( ip ) }/`,
					'activity-details'
				)
			: '';
	const pairs = detailPairs( item?.details );
	const confidence = confidenceOf( item?.details );
	const block = item?.block || null;
	const blockedNow = Boolean( item ? item.blocked : row?.blocked );

	let footer = null;
	if ( item ) {
		footer = block ? (
			<>
				<div className="zs-dnotice">
					<span className="zs-dnotice-i" aria-hidden="true">
						<Icon name="ban" />
					</span>
					<div className="zs-li-body">
						<b>
							{ blockedNow
								? __( 'On your block list', 'zero-spam' )
								: __( 'Block ended', 'zero-spam' ) }
						</b>
						<span className="zs-hint">
							{ 'permanent' === block.type
								? __( 'Permanent', 'zero-spam' )
								: sprintf(
										/* translators: %s: date the block ends */
										__(
											'Temporary · ends %s',
											'zero-spam'
										),
										day(
											new Date(
												Date.parse( block.end ) +
													( boot.nowLocal -
														Date.now() )
											)
										)
									) }
						</span>
					</div>
				</div>
				<div className="zs-inline-row">
					<Button
						variant="secondary"
						icon="edit"
						onClick={ () => onBlock( ip, block ) }
					>
						{ __( 'Edit block', 'zero-spam' ) }
					</Button>
					<Button
						variant="ghost"
						icon="trash"
						onClick={ () => onDelete( item ) }
					>
						{ __( 'Delete entry', 'zero-spam' ) }
					</Button>
				</div>
			</>
		) : (
			<div className="zs-inline-row">
				<Button icon="ban" onClick={ () => onBlock( ip, null ) }>
					{ __( 'Block IP', 'zero-spam' ) }
				</Button>
				{ item.allowed && (
					<Badge tone="ok" icon="checkc">
						{ __( 'On your IP allowlist', 'zero-spam' ) }
					</Badge>
				) }
				{ ! item.allowed && item.allowlist_locked && (
					<Badge icon="lock" className="zs-lockbadge">
						<span aria-hidden="true">
							{ __( 'Allowlist locked by network', 'zero-spam' ) }
						</span>
						<span className="zs-sr-only">
							{ __(
								'The IP allowlist is locked by the network administrator, so this address cannot be added here.',
								'zero-spam'
							) }
						</span>
					</Badge>
				) }
				{ ! item.allowed && ! item.allowlist_locked && (
					<Button
						variant="ghost"
						icon="checkc"
						onClick={ () =>
							onAllow( ip, ( allowed ) =>
								detail.setData( ( d ) =>
									d ? { ...d, allowed } : d
								)
							)
						}
					>
						{ __( 'Always allow', 'zero-spam' ) }
					</Button>
				) }
				<Button
					variant="ghost"
					icon="trash"
					onClick={ () => onDelete( item ) }
				>
					{ __( 'Delete entry', 'zero-spam' ) }
				</Button>
			</div>
		);
	}

	const place = [
		shown?.region_name,
		shown?.region ? `(${ shown.region })` : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<Drawer
			open
			onClose={ onClose }
			labelledBy="zs-dr-title"
			className="zs-details"
			nav={ position }
			returnFocus={ returnFocus }
			busy={ ! item && ! missing }
			header={
				<div className="zs-dh">
					<TypeIcon icon={ typeIcon( shown?.type ) } />
					<div className="zs-grow">
						<div className="zs-eyebrow">
							{ sprintf(
								/* translators: %s: detection ID, such as 48213 */
								__( 'Detection #%s', 'zero-spam' ),
								String( id )
							) }
						</div>
						<h2 id="zs-dr-title" className="zs-bigip">
							{ ip || '—' }
						</h2>
						{ shown?.country && (
							<Country
								code={ shown.country }
								name={ shown.country_name }
							/>
						) }
					</div>
				</div>
			}
			footer={ footer }
		>
			{ missing && (
				<p className="zs-notice zs-notice-err" role="alert">
					{ __(
						'This entry is no longer in the log. It may have been deleted or pruned.',
						'zero-spam'
					) }
				</p>
			) }
			{ detail.error && ! missing && (
				<p className="zs-notice zs-notice-err" role="alert">
					{ __(
						'The details could not be loaded. Close and try again.',
						'zero-spam'
					) }
				</p>
			) }
			{ shown && (
				<>
					<section className="zs-sect" aria-labelledby="zs-dr-why">
						<h3 id="zs-dr-why">
							{ __( 'Why it was blocked', 'zero-spam' ) }
						</h3>
						<p className="zs-why">
							<b>{ shown.failed_label || shown.type_label }</b>
						</p>
						<p className="zs-hint">{ whySentence( shown ) }</p>
						<dl className="zs-kv zs-dkv">
							<div className="zs-kv-row">
								<dt>{ __( 'Date', 'zero-spam' ) }</dt>
								<dd>{ dayTime( shown.date ) }</dd>
							</div>
							<div className="zs-kv-row">
								<dt>{ __( 'IP address', 'zero-spam' ) }</dt>
								<dd>
									<span className="zs-mono">{ ip }</span>
									{ lookup && (
										<>
											{ ' · ' }
											<ExtLink
												href={ lookup }
												newTab={ __(
													'(opens in a new tab)',
													'zero-spam'
												) }
											>
												{ __(
													'IP lookup',
													'zero-spam'
												) }
											</ExtLink>
										</>
									) }
								</dd>
							</div>
							<div className="zs-kv-row">
								<dt>{ __( 'Type', 'zero-spam' ) }</dt>
								<dd>
									{ shown.type_label }{ ' ' }
									<code>{ shown.type }</code>
									{ enhanced && shown.network && (
										<Badge
											tone="enh"
											icon="hub"
											className="zs-netbadge"
										>
											{ __( 'Network', 'zero-spam' ) }
										</Badge>
									) }
								</dd>
							</div>
							{ shown.failed && (
								<div className="zs-kv-row">
									<dt>
										{ __( 'Failed check', 'zero-spam' ) }
									</dt>
									<dd>
										{ shown.failed_label }{ ' ' }
										<code>{ shown.failed }</code>
									</dd>
								</div>
							) }
							{ item?.page_url && (
								<div className="zs-kv-row">
									<dt>{ __( 'Page', 'zero-spam' ) }</dt>
									<dd className="zs-mono zs-break">
										{ item.page_url }
									</dd>
								</div>
							) }
						</dl>
					</section>

					<section className="zs-sect" aria-labelledby="zs-dr-loc">
						<h3 id="zs-dr-loc">
							{ __( 'Location', 'zero-spam' ) }
						</h3>
						{ shown.country || shown.city || shown.region_name ? (
							<dl className="zs-kv zs-dkv">
								{ shown.country && (
									<div className="zs-kv-row">
										<dt>
											{ __( 'Country', 'zero-spam' ) }
										</dt>
										<dd>
											<Country
												code={ shown.country }
												name={ shown.country_name }
												code2
											/>
										</dd>
									</div>
								) }
								{ place && (
									<div className="zs-kv-row">
										<dt>{ __( 'Region', 'zero-spam' ) }</dt>
										<dd>{ place }</dd>
									</div>
								) }
								{ shown.city && (
									<div className="zs-kv-row">
										<dt>{ __( 'City', 'zero-spam' ) }</dt>
										<dd>{ shown.city }</dd>
									</div>
								) }
								{ item?.zip && (
									<div className="zs-kv-row">
										<dt>
											{ __(
												'Zip/Postal code',
												'zero-spam'
											) }
										</dt>
										<dd className="zs-mono">
											{ item.zip }
										</dd>
									</div>
								) }
								{ hasValue( item?.latitude ) &&
									hasValue( item?.longitude ) && (
										<div className="zs-kv-row">
											<dt>
												{ __(
													'Coordinates',
													'zero-spam'
												) }
											</dt>
											<dd className="zs-mono">
												{ `${ item.latitude }°, ${ item.longitude }°` }
											</dd>
										</div>
									) }
							</dl>
						) : (
							<p className="zs-hint">
								{ __(
									'No location was recorded for this address.',
									'zero-spam'
								) }
							</p>
						) }
						{ item?.map_url && (
							<p>
								<ExtLink
									href={ item.map_url }
									newTab={ __(
										'(opens in a new tab)',
										'zero-spam'
									) }
								>
									{ __( 'Open in Google Maps', 'zero-spam' ) }
								</ExtLink>
							</p>
						) }
					</section>

					{ item && (
						<section
							className="zs-sect"
							aria-labelledby="zs-dr-add"
						>
							<h3 id="zs-dr-add">
								{ __( 'Additional details', 'zero-spam' ) }
							</h3>
							{ pairs.length ? (
								<>
									<dl className="zs-code">
										{ pairs.map( ( [ key, text ] ) => (
											<div
												className="zs-code-row"
												key={ key }
											>
												<dt>{ key }</dt>
												<dd>{ text }</dd>
											</div>
										) ) }
									</dl>
									<p className="zs-hint">
										{ __(
											'Shown as plain text. Links and code in submissions never run here.',
											'zero-spam'
										) }
									</p>
								</>
							) : (
								<p className="zs-hint">
									{ __(
										'Nothing else was sent with this request.',
										'zero-spam'
									) }
								</p>
							) }
						</section>
					) }

					{ item && enhanced && (
						<section
							className="zs-sect"
							aria-labelledby="zs-dr-rep"
						>
							<h3 id="zs-dr-rep">
								{ __( 'Zero Spam reputation', 'zero-spam' ) }{ ' ' }
								<Badge tone="enh">
									{ __( 'Enhanced', 'zero-spam' ) }
								</Badge>
							</h3>
							{ null !== confidence ? (
								<dl className="zs-kv zs-dkv">
									<div className="zs-kv-row">
										<dt>{ __( 'Risk', 'zero-spam' ) }</dt>
										<dd>
											<Badge
												tone={
													confidence >= 50
														? 'danger'
														: 'warn'
												}
											>
												{ confidence >= 50
													? __( 'High', 'zero-spam' )
													: __(
															'Medium',
															'zero-spam'
														) }
											</Badge>{ ' ' }
											{ sprintf(
												/* translators: %s: confidence in percent */
												__(
													'%s%% confidence',
													'zero-spam'
												),
												num( confidence )
											) }
										</dd>
									</div>
									<div className="zs-kv-row">
										<dt>
											{ __( 'Caught by', 'zero-spam' ) }
										</dt>
										<dd>
											{ __(
												'The Zero Spam network',
												'zero-spam'
											) }
										</dd>
									</div>
								</dl>
							) : (
								<p className="zs-hint">
									{ __(
										'Another check stopped this visitor first, so no network score was recorded with it.',
										'zero-spam'
									) }
								</p>
							) }
							{ lookup && (
								<p>
									<ExtLink
										href={ lookup }
										newTab={ __(
											'(opens in a new tab)',
											'zero-spam'
										) }
									>
										{ __( 'Full IP report', 'zero-spam' ) }
									</ExtLink>
								</p>
							) }
						</section>
					) }
					{ item && ! enhanced && (
						<div className="zs-locked">
							<span className="zs-ico" aria-hidden="true">
								<Icon name="lock" />
							</span>
							<div className="zs-li-body">
								<b>
									{ __(
										'Zero Spam reputation needs Enhanced',
										'zero-spam'
									) }
								</b>
								<p>
									{ __(
										"See this address's reputation across the 30,000+ sites in the Zero Spam network.",
										'zero-spam'
									) }
								</p>
								<button
									type="button"
									className="zs-linkbtn"
									onClick={ () =>
										openCompare( 'activity-details' )
									}
								>
									{ __( 'Compare levels', 'zero-spam' ) }
								</button>
							</div>
						</div>
					) }
				</>
			) }
		</Drawer>
	);
}
