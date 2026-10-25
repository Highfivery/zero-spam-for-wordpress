/**
 * The Overview's tiles below the chart.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import {
	Badge,
	Button,
	Chip,
	ExtLink,
	Icon,
	Tile,
	TileHead,
	cx,
} from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { Mark } from '../app/mark';
import { FOLLOW, SocialIcon } from '../app/shell/side-extra';
import { boot, nowLocal } from '../data/api';
import { ago, day, minutesAgo, num, parseDate, signed } from '../data/format';
import { areaUrl, utm } from '../data/routes';
import { milestones, weekBuckets } from './chart-data';

/**
 * The sprite icon for a detection type.
 *
 * @param {string} type Log type.
 * @return {string} Icon name.
 */
export function typeIcon( type ) {
	if ( 'comment' === type ) {
		return 'comment';
	}
	if ( 'registration' === type || 'wp_user_avatar' === type ) {
		return 'userplus';
	}
	if ( 'login' === type ) {
		return 'key';
	}
	if ( 'blocked' === type ) {
		return 'ban';
	}
	if ( String( type ).startsWith( 'woocommerce' ) ) {
		return 'cart';
	}
	if ( 'mailchimp4wp' === type ) {
		return 'mail';
	}
	return 'form';
}

/**
 * A country as a two-letter tag (no flag images or emoji).
 *
 * @param {Object} props      Props.
 * @param {string} props.code Country code.
 */
export function CountryCode( { code } ) {
	if ( ! code ) {
		return null;
	}
	return (
		<span className="zs-cc" aria-hidden="true">
			{ String( code ).toUpperCase() }
		</span>
	);
}

/**
 * Latest blocks (the live feed).
 *
 * @param {Object} props      Props.
 * @param {Object} props.feed useLiveFeed() state.
 */
export function LatestBlocks( { feed } ) {
	const { items, fresh, queued, showQueued, loaded } = feed;
	const now = nowLocal();

	return (
		<Tile span={ 4 } labelledBy="zs-ra-h" className="zs-feed-tile">
			<TileHead
				eyebrow={ __( 'Recent activity', 'zero-spam' ) }
				id="zs-ra-h"
				title={ __( 'Latest blocks', 'zero-spam' ) }
				aside={
					<Button
						variant="secondary"
						size="sm"
						href={ areaUrl( 'activity' ) }
					>
						{ __( 'View all', 'zero-spam' ) }
					</Button>
				}
			/>
			{ queued.length > 0 && (
				<div className="zs-newbar">
					<Button
						variant="secondary"
						size="sm"
						onClick={ showQueued }
					>
						{ sprintf(
							/* translators: %s: number of new detections */
							_n(
								'%s new · Show',
								'%s new · Show',
								queued.length,
								'zero-spam'
							),
							num( queued.length )
						) }
					</Button>
				</div>
			) }
			{ loaded && ! items.length && (
				<div className="zs-empty">
					<div
						className="zs-ghost-wall zs-ghost-sm"
						aria-hidden="true"
					>
						<span />
						<span />
						<span />
					</div>
					<b>{ __( 'Nothing blocked yet', 'zero-spam' ) }</b>
					<span className="zs-hint">
						{ __(
							'Spam and attacks Zero Spam stops show up here as they happen.',
							'zero-spam'
						) }
					</span>
				</div>
			) }
			{ items.length > 0 && (
				<ul className="zs-feed">
					{ items.map( ( item ) => {
						const when = ago( minutesAgo( item.date, now ) );
						const reason = item.failed_label || '';
						const place = item.country_name || item.country || '';
						return (
							<li
								key={ item.id }
								className={
									fresh.has( item.id )
										? 'zs-fresh'
										: undefined
								}
							>
								<a
									href={ areaUrl( 'activity', {
										item: item.id,
									} ) }
								>
									<span className="zs-ico zs-ico-danger">
										<Icon name={ typeIcon( item.type ) } />
									</span>
									<span className="zs-c-ip">
										<span className="zs-ip">
											{ item.ip }
										</span>
									</span>
									<span className="zs-when">{ when }</span>
									<span className="zs-c-rs">
										<CountryCode code={ item.country } />
										{ place && (
											<span className="zs-sr-only">
												{ place }
											</span>
										) }
										<span>
											{ [ item.type_label, reason ]
												.filter( Boolean )
												.join( ' · ' ) }
										</span>
									</span>
								</a>
							</li>
						);
					} ) }
				</ul>
			) }
		</Tile>
	);
}

/**
 * A module's state from protection/modules.
 *
 * @param {Array}  modules Modules.
 * @param {string} key     Section key.
 * @return {Object|undefined} Module.
 */
const findModule = ( modules, key ) =>
	( modules || [] ).find( ( m ) => m.key === key );

/**
 * What one attention item links to.
 *
 * @param {Object} item    Attention item.
 * @param {Array}  modules protection/modules modules.
 * @return {{label: string, href: string}|null} Action.
 */
function attentionAction( item, modules ) {
	if ( item.id.startsWith( 'integration_unprotected:' ) ) {
		const name =
			findModule( modules, item.module )?.plugin?.name || item.module;
		return {
			/* translators: %s: plugin name */
			label: sprintf( __( 'Review %s protection', 'zero-spam' ), name ),
			href: areaUrl( 'protection', {
				tab: 'integrations',
				card: item.module,
			} ),
		};
	}
	if ( 'log_fill' === item.id ) {
		return {
			label: __( 'Change log size', 'zero-spam' ),
			href: areaUrl( 'settings', { section: 'max_logs' } ),
		};
	}
	if ( 'shared_ip' === item.id || 'proxy_untrusted' === item.id ) {
		return {
			label: __( 'Add a trusted proxy', 'zero-spam' ),
			href: areaUrl( 'settings', { section: 'proxies' } ),
		};
	}
	return null;
}

/**
 * The icon of an attention item.
 *
 * @param {Object} item Attention item.
 * @return {string} Icon name.
 */
const attentionIcon = ( item ) => {
	if ( 'log_fill' === item.id ) {
		return 'db';
	}
	return item.module ? 'form' : 'warn';
};

/**
 * The ID a dismissed attention item is remembered by.
 *
 * @param {Object} item Attention item.
 * @return {string} ID.
 */
export const attentionKey = ( item ) =>
	( 'attention:' + item.id + ( item.ip ? ':' + item.ip : '' ) )
		.toLowerCase()
		.slice( 0, 64 );

/**
 * Needs attention.
 *
 * @param {Object}  props      Props.
 * @param {boolean} props.wide Full width (Enhanced).
 */
export function NeedsAttention( { wide } ) {
	const { modules, isDismissed, dismiss, undismiss } = useApp();
	const { toast } = useFeedback();
	const items = ( modules?.attention || [] ).filter(
		( item ) => ! isDismissed( attentionKey( item ) )
	);

	const title = items.length
		? sprintf(
				/* translators: %d: number of things */
				_n(
					'%d thing to look at',
					'%d things to look at',
					items.length,
					'zero-spam'
				),
				items.length
			)
		: __( 'Nothing to look at', 'zero-spam' );

	return (
		<Tile
			span={ wide ? 12 : 6 }
			labelledBy="zs-na-h"
			className={ cx( 'zs-na', wide && 'zs-na-wide' ) }
		>
			<TileHead
				eyebrow={ __( 'Needs attention', 'zero-spam' ) }
				id="zs-na-h"
				title={ title }
				focusable
			/>
			{ ! items.length && modules && (
				<div className="zs-na-ok">
					<span className="zs-ico zs-ico-ok">
						<Icon name="checkc" />
					</span>
					<span className="zs-li-body">
						<b>
							{ __(
								'Every check is on and working',
								'zero-spam'
							) }
						</b>
						<span className="zs-hint">
							{ __(
								"Zero Spam tells you here when a plugin isn't protected, the log is nearly full or visitors seem to share one address.",
								'zero-spam'
							) }
						</span>
					</span>
				</div>
			) }
			{ items.length > 0 && (
				<ul className="zs-list">
					{ items.map( ( item ) => {
						const action = attentionAction(
							item,
							modules?.modules
						);
						const key = attentionKey( item );
						return (
							<li key={ item.id }>
								<span
									className={ cx(
										'zs-ico',
										'warning' === item.severity
											? 'zs-ico-warn'
											: 'zs-ico-info'
									) }
								>
									<Icon name={ attentionIcon( item ) } />
								</span>
								<div className="zs-li-body">
									<b>{ item.title }</b>
									<span className="zs-hint">
										{ item.message }
									</span>
									{ 'log_fill' === item.id && (
										<div
											className="zs-bar"
											role="img"
											aria-label={ sprintf(
												/* translators: %d: percentage */
												__(
													'%d percent used',
													'zero-spam'
												),
												item.percent
											) }
										>
											<i
												style={ {
													width: `${ item.percent }%`,
												} }
											/>
										</div>
									) }
									<div className="zs-li-act">
										{ action && (
											<Button
												variant="secondary"
												size="sm"
												href={ action.href }
											>
												{ action.label }
											</Button>
										) }
										{ 'log_fill' !== item.id && (
											<Button
												variant="ghost"
												size="sm"
												onClick={ () => {
													dismiss( key ).then( () =>
														toast(
															__(
																'Dismissed. It comes back if something changes.',
																'zero-spam'
															),
															{
																undo: () =>
																	undismiss(
																		key
																	),
															}
														)
													);
													document
														.getElementById(
															'zs-na-h'
														)
														?.focus();
												} }
											>
												{ __( 'Dismiss', 'zero-spam' ) }
											</Button>
										) }
									</div>
								</div>
							</li>
						);
					} ) }
				</ul>
			) }
		</Tile>
	);
}

/**
 * The Enhanced Protection value panel (Basic sites).
 *
 * @param {Object} props       Props.
 * @param {number} props.total Threats stopped in 30 days.
 */
export function EnhancedTile( { total } ) {
	const { openCompare } = useApp();
	return (
		<Tile span={ 6 } labelledBy="zs-en-h" className="zs-enh zs-dark-block">
			<div className="zs-enh-copy">
				<div className="zs-eyebrow">
					{ __( 'Enhanced Protection', 'zero-spam' ) }
				</div>
				<h2 id="zs-en-h" className="zs-tile-title">
					{ total > 0
						? __( 'Enhanced would also have checked', 'zero-spam' )
						: __(
								'Enhanced checks every visitor',
								'zero-spam'
							) }{ ' ' }
					{ total > 0 && (
						<span>
							{ sprintf(
								/* translators: %s: number of submissions */
								_n(
									'%s submission',
									'%s submissions',
									total,
									'zero-spam'
								),
								num( total )
							) }
						</span>
					) }{ ' ' }
					{ __( "against Zero Spam's threat network.", 'zero-spam' ) }
				</h2>
				<ul className="zs-enh-ben">
					<li>
						<Icon name="hub" size="sm" />
						<span>
							{ __(
								'Real-time checks against one of the largest malicious IP and email databases',
								'zero-spam'
							) }
						</span>
					</li>
					<li>
						<Icon name="mail" size="sm" />
						<span>
							{ __(
								'Blocks email addresses reported for spam',
								'zero-spam'
							) }
						</span>
					</li>
					<li>
						<Icon name="shield" size="sm" />
						<span>
							{ __(
								'Helps stop DDoS attempts and stolen credit card testing',
								'zero-spam'
							) }
						</span>
					</li>
				</ul>
				<dl
					className="zs-glance"
					aria-label={ __(
						'Zero Spam network at a glance',
						'zero-spam'
					) }
				>
					<div>
						<dt>{ __( 'Average check', 'zero-spam' ) }</dt>
						<dd>{ __( '<10 ms', 'zero-spam' ) }</dd>
					</div>
					<div>
						<dt>{ __( 'Sites', 'zero-spam' ) }</dt>
						<dd>{ __( '30,000+', 'zero-spam' ) }</dd>
					</div>
				</dl>
				<div className="zs-enh-row">
					<Button onClick={ () => openCompare( 'overview' ) }>
						{ __( 'Compare levels', 'zero-spam' ) }
					</Button>
					<Button
						variant="darksec"
						href={ areaUrl( 'protection', {
							tab: 'intelligence',
							card: 'zerospam',
						} ) }
					>
						{ __( 'I already have a license key', 'zero-spam' ) }
					</Button>
				</div>
				<p className="zs-biglabel">
					{ __(
						'Enhanced subscriptions fund the free plugin.',
						'zero-spam'
					) }
				</p>
			</div>
		</Tile>
	);
}

/**
 * Network intelligence (Enhanced): what the license and the network report.
 */
export function NetworkPanel() {
	const { license } = useApp();
	const q = license?.queries;
	const used = q ? q.used : null;
	const pct =
		q && q.limit > 0
			? Math.min( 100, Math.round( ( q.used / q.limit ) * 100 ) )
			: null;
	const status = license?.status;

	const tiles = [
		[
			'hub',
			__( 'Network checks', 'zero-spam' ),
			null !== used ? num( used ) : '—',
			__( 'this month', 'zero-spam' ),
		],
		[
			'activity',
			__( 'Checks left', 'zero-spam' ),
			q ? num( Math.max( 0, q.remaining ) ) : '—',
			q
				? sprintf(
						/* translators: %s: queries in the plan */
						__( 'of %s', 'zero-spam' ),
						num( q.limit )
					)
				: '',
		],
		[
			'clock',
			__( 'Quota resets', 'zero-spam' ),
			license?.reset_date ? day( license.reset_date ) : '—',
			license?.reset_date
				? ''
				: __( 'Set in API Monitoring', 'zero-spam' ),
		],
	];

	return (
		<Tile span={ 12 } labelledBy="zs-ni-h" className="zs-ni">
			<div className="zs-ni-head">
				<div className="zs-ni-title">
					<p className="zs-eyebrow zs-ni-eyebrow">
						<Icon name="hub" size="sm" />
						{ __( 'Network intelligence', 'zero-spam' ) }
					</p>
					<h2 id="zs-ni-h" className="zs-tile-title">
						{ __(
							'The Zero Spam network is checking your visitors',
							'zero-spam'
						) }
					</h2>
					<p className="zs-ni-sub">
						{ __(
							"Every visitor's IP address is checked against reputation data reported by sites across the Zero Spam network.",
							'zero-spam'
						) }
					</p>
				</div>
			</div>
			<ul className="zs-ni-tiles">
				{ tiles.map( ( [ icon, label, value, note ] ) => (
					<li key={ label }>
						<div className="zs-ni-tk">
							<span className="zs-ni-ico">
								<Icon name={ icon } size="sm" />
							</span>
							<span>{ label }</span>
						</div>
						<b className="zs-mono">{ value }</b>
						{ note && (
							<span className="zs-ni-delta">{ note }</span>
						) }
					</li>
				) ) }
			</ul>
			{ null !== pct && (
				<div className="zs-ni-since">
					<div className="zs-ni-since-t">
						<span className="zs-eyebrow">
							{ __( 'Queries this month', 'zero-spam' ) }
						</span>
						<span className="zs-mono">{ `${ pct }%` }</span>
					</div>
					<div
						className="zs-ni-prog"
						role="img"
						aria-label={ sprintf(
							/* translators: 1: queries used, 2: queries in the plan */
							__( '%1$s of %2$s queries used', 'zero-spam' ),
							num( q.used ),
							num( q.limit )
						) }
					>
						<i style={ { width: `${ pct }%` } } />
					</div>
				</div>
			) }
			<p className="zs-ni-pulse">
				<span
					className={ cx(
						'zs-pdot',
						'unreachable' === status && 'zs-pdot-off'
					) }
					aria-hidden="true"
				/>
				<span>
					{ 'unreachable' === status
						? __(
								'The network could not be reached at the last check. Protection stays on.',
								'zero-spam'
							)
						: sprintf(
								/* translators: %s: relative time, such as 2 min ago */
								__( 'License checked %s', 'zero-spam' ),
								license?.checked_at
									? ago(
											Math.max(
												0,
												Math.floor(
													( Date.now() -
														license.checked_at *
															1000 ) /
														60000
												)
											)
										).toLowerCase()
									: __( 'recently', 'zero-spam' )
							) }
				</span>
			</p>
			<p className="zs-ni-foot">
				{ __(
					'Addresses the network has already seen misbehave are stopped before they reach your forms.',
					'zero-spam'
				) }
			</p>
		</Tile>
	);
}

/**
 * Protection level (Basic) or the membership (Enhanced).
 */
export function LevelTile() {
	const { enhanced, license, openCompare } = useApp();

	if ( enhanced ) {
		const q = license?.queries;
		const pct =
			q && q.limit > 0
				? Math.min( 100, Math.round( ( q.used / q.limit ) * 100 ) )
				: null;
		return (
			<Tile span={ 4 } labelledBy="zs-np-h" className="zs-membership">
				<TileHead
					eyebrow={ __( 'Your membership', 'zero-spam' ) }
					id="zs-np-h"
					title={
						license?.plan?.name
							? sprintf(
									/* translators: %s: plan name */
									__( '%s plan', 'zero-spam' ),
									license.plan.name
								)
							: __( 'Enhanced Protection', 'zero-spam' )
					}
				/>
				{ null !== pct && (
					<div className="zs-sect">
						<h3>{ __( 'Queries this month', 'zero-spam' ) }</h3>
						<div
							className="zs-bar zs-bar-enh"
							role="img"
							aria-label={ sprintf(
								/* translators: %d: percentage */
								__( '%d percent used', 'zero-spam' ),
								pct
							) }
						>
							<i style={ { width: `${ pct }%` } } />
						</div>
						<p className="zs-hint">
							{ sprintf(
								/* translators: 1: queries used, 2: queries left */
								__( '%1$s used · %2$s left', 'zero-spam' ),
								num( q.used ),
								num( Math.max( 0, q.remaining ) )
							) }
						</p>
					</div>
				) }
				<dl className="zs-kv">
					{ license?.reset_date && (
						<div className="zs-kv-row">
							<dt>{ __( 'Quota resets', 'zero-spam' ) }</dt>
							<dd>{ day( license.reset_date ) }</dd>
						</div>
					) }
					<div className="zs-kv-row">
						<dt>{ __( 'Early access', 'zero-spam' ) }</dt>
						<dd>{ __( '4 features', 'zero-spam' ) }</dd>
					</div>
				</dl>
				<ul className="zs-levels">
					<li className="zs-yes">
						<Icon name="buoy" size="sm" />
						<span>
							{ __(
								'Support from the team that builds Zero Spam.',
								'zero-spam'
							) }{ ' ' }
							<ExtLink
								href={ utm(
									'https://www.zerospam.org/contact/',
									'membership'
								) }
								newTab={ __(
									'(opens in a new tab)',
									'zero-spam'
								) }
							>
								{ __( 'Contact support', 'zero-spam' ) }
							</ExtLink>
						</span>
					</li>
				</ul>
			</Tile>
		);
	}

	const layers = [
		[
			true,
			__( 'Hidden traps and browser checks on every form', 'zero-spam' ),
		],
		[ true, __( 'Login and registration screening', 'zero-spam' ) ],
		[
			true,
			__( 'Stop Forum Spam and Project Honeypot lookups', 'zero-spam' ),
		],
		[
			true,
			__( 'Your own IP, location, email and word rules', 'zero-spam' ),
		],
		[ false, __( 'Zero Spam IP and email reputation', 'zero-spam' ) ],
		[ false, __( 'Reported spam email blocking', 'zero-spam' ) ],
	];

	return (
		<Tile span={ 4 } labelledBy="zs-lv-h">
			<TileHead
				eyebrow={ __( 'Protection level', 'zero-spam' ) }
				id="zs-lv-h"
				title={ __( 'Basic covers 4 of 6 layers', 'zero-spam' ) }
			/>
			<ul className="zs-levels">
				{ layers.map( ( [ yes, text ] ) => (
					<li key={ text } className={ yes ? 'zs-yes' : 'zs-no' }>
						<Icon name={ yes ? 'checkc' : 'lock' } size="sm" />
						<span>
							{ text }
							{ ! yes && (
								<span className="zs-sr-only">
									{ ' ' }
									{ __( '(Enhanced only)', 'zero-spam' ) }
								</span>
							) }
						</span>
					</li>
				) ) }
			</ul>
			<button
				className="zs-linkbtn zs-tile-foot"
				type="button"
				onClick={ () => openCompare( 'level' ) }
			>
				{ __( 'Compare levels', 'zero-spam' ) }
			</button>
		</Tile>
	);
}

/**
 * This week.
 *
 * @param {Object}  props         Props.
 * @param {Object}  props.summary stats/summary?range=7.
 * @param {boolean} props.wide    Spans 8 columns (no milestone beside it).
 * @param {string}  props.flash   Bucket that just grew.
 */
export function WeekTile( { summary, wide, flash } ) {
	const buckets = weekBuckets( summary?.by_type );
	const max = Math.max( 1, ...buckets.map( ( b ) => b.count ) );
	const total = summary?.total ?? 0;
	const names = {
		comments: __( 'Comments', 'zero-spam' ),
		login: __( 'Login', 'zero-spam' ),
		registration: __( 'Registration', 'zero-spam' ),
		forms: __( 'Forms', 'zero-spam' ),
		blocked: __( 'Blocked visits', 'zero-spam' ),
	};
	const top = summary?.top_country;
	const busiest = summary?.busiest_day;

	return (
		<Tile span={ wide ? 8 : 4 } labelledBy="zs-wk-h" className="zs-week">
			<TileHead
				eyebrow={ __( 'This week', 'zero-spam' ) }
				id="zs-wk-h"
				focusable
				title={ sprintf(
					/* translators: %s: number of threats */
					_n(
						'%s threat in 7 days',
						'%s threats in 7 days',
						total,
						'zero-spam'
					),
					num( total )
				) }
				aside={
					summary &&
					( summary.previous_total > 0 || total > 0 ) && (
						<Badge>
							{ sprintf(
								/* translators: %s: difference, such as +96 */
								__( '%s vs previous week', 'zero-spam' ),
								signed( summary.delta )
							) }
						</Badge>
					)
				}
			/>
			<div className="zs-wk">
				{ buckets.map( ( b ) => (
					<div
						key={ b.bucket }
						className={ cx(
							'zs-wk-row',
							flash === b.bucket && 'zs-fresh'
						) }
					>
						<span>{ names[ b.bucket ] }</span>
						<span className="zs-wk-track">
							<span
								className="zs-wk-bar"
								style={ {
									width: `${ ( ( b.count / max ) * 100 ).toFixed( 1 ) }%`,
								} }
							/>
						</span>
						<span className="zs-mono zs-num">
							{ num( b.count ) }
						</span>
					</div>
				) ) }
			</div>
			<dl className="zs-kv zs-tile-foot">
				<div className="zs-kv-row">
					<dt>{ __( 'Top country', 'zero-spam' ) }</dt>
					<dd>
						{ top ? (
							<span className="zs-cty">
								<CountryCode code={ top.code } />
								<span>
									{ sprintf(
										/* translators: 1: country name, 2: number of blocks */
										_n(
											'%1$s · %2$s block',
											'%1$s · %2$s blocks',
											top.count,
											'zero-spam'
										),
										top.name,
										num( top.count )
									) }
								</span>
							</span>
						) : (
							'—'
						) }
					</dd>
				</div>
				<div className="zs-kv-row">
					<dt>{ __( 'Busiest day', 'zero-spam' ) }</dt>
					<dd className="zs-nowrap">
						{ busiest
							? `${ day( busiest.date ) } · ${ num( busiest.count ) }`
							: '—' }
					</dd>
				</div>
			</dl>
		</Tile>
	);
}

/** The review link (the milestone tile's ask). */
export const REVIEW_URL =
	'https://wordpress.org/support/plugin/zero-spam/reviews/#new-post';

/**
 * The milestone (and the review ask).
 *
 * @param {Object}   props           Props.
 * @param {number}   props.total     Threats stopped in 30 days.
 * @param {Function} props.onDismiss Called after dismissing (focus moves on).
 */
export function MilestoneTile( { total, onDismiss } ) {
	const { dismiss, undismiss } = useApp();
	const { toast } = useFeedback();
	const { reached, next } = milestones( total );
	const site = boot.site.name || __( 'your site', 'zero-spam' );
	const pct = Math.min( 100, Math.round( ( total / next ) * 100 ) );
	const key = `milestone-${ reached }`;

	return (
		<Tile span={ 4 } labelledBy="zs-ms-h" className="zs-milestone">
			<div className="zs-plaque">
				<span className="zs-eyebrow">
					{ __( 'Milestone', 'zero-spam' ) }
				</span>
				<span className="zs-mono zs-plaque-n">{ num( reached ) }</span>
				<span>{ __( 'threats stopped', 'zero-spam' ) }</span>
				<Mark size={ 84 } className="zs-plaque-mk" />
			</div>
			<h2 className="zs-sr-only" id="zs-ms-h">
				{ sprintf(
					/* translators: %s: number of threats */
					__( 'Milestone: %s threats stopped', 'zero-spam' ),
					num( reached )
				) }
			</h2>
			<p className="zs-hint zs-ms-thanks">
				{ sprintf(
					/* translators: %s: site name */
					__(
						'Thank you for keeping %s safe with Zero Spam.',
						'zero-spam'
					),
					site
				) }
			</p>
			<div className="zs-nextms">
				<span className="zs-eyebrow">
					{ sprintf(
						/* translators: %s: next milestone */
						__( 'Next milestone · %s', 'zero-spam' ),
						num( next )
					) }
				</span>
				<div
					className="zs-bar zs-bar-bricks"
					role="img"
					aria-label={ sprintf(
						/* translators: 1: threats stopped, 2: next milestone */
						__( '%1$s of %2$s', 'zero-spam' ),
						num( total ),
						num( next )
					) }
				>
					<i style={ { width: `${ pct }%` } } />
				</div>
				<span className="zs-hint">
					{ sprintf(
						/* translators: 1: threats stopped, 2: next milestone */
						__( '%1$s of %2$s in the last 30 days', 'zero-spam' ),
						num( total ),
						num( next )
					) }
				</span>
			</div>
			<div className="zs-inline-row zs-tile-foot">
				<Button
					variant="secondary"
					size="sm"
					href={ REVIEW_URL }
					external
				>
					{ __( 'Rate Zero Spam', 'zero-spam' ) }{ ' ' }
					<span aria-hidden="true">★</span>
					<span className="zs-sr-only">
						{ __( '(opens in a new tab)', 'zero-spam' ) }
					</span>
				</Button>
				<Button
					variant="ghost"
					size="sm"
					onClick={ () => {
						dismiss( key ).then( () =>
							toast(
								__(
									"Milestone dismissed. It won't show again.",
									'zero-spam'
								),
								{
									undo: () => undismiss( key ),
								}
							)
						);
						onDismiss();
					} }
				>
					{ __( 'Dismiss', 'zero-spam' ) }
				</Button>
			</div>
		</Tile>
	);
}

/**
 * Latest from Zero Spam (the Threat Intelligence Log).
 */
export function NewsTile() {
	const { news, newsState } = useApp();
	const { toast } = useFeedback();
	const [ cat, setCat ] = useState( '' );
	const categories = [
		...new Set(
			news.map( ( n ) => n.categories?.[ 0 ] ).filter( Boolean )
		),
	].slice( 0, 3 );
	const list = news
		.filter( ( n ) => ! cat || ( n.categories || [] ).includes( cat ) )
		.slice( 0, 3 );
	const fetched = parseDate(
		newsState.headers?.[ 'x-zerospam-news-fetched' ]
	);
	const updated = fetched
		? ago(
				Math.max(
					0,
					Math.floor( ( Date.now() - fetched.getTime() ) / 60000 )
				)
			)
		: '';

	return (
		<Tile span={ 7 } labelledBy="zs-nw-t" className="zs-news-tile">
			<TileHead
				eyebrow={ __( 'Latest from Zero Spam', 'zero-spam' ) }
				id="zs-nw-t"
				title={ __( 'Threat Intelligence Log', 'zero-spam' ) }
				aside={
					<span className="zs-news-upd">
						{ updated && (
							<span className="zs-hint" aria-live="polite">
								{ sprintf(
									/* translators: %s: relative time */
									__( 'Updated %s', 'zero-spam' ),
									updated.toLowerCase()
								) }
							</span>
						) }
						<Button
							variant="ghost"
							size="sm"
							icon="refresh"
							disabled={ newsState.loading }
							onClick={ () =>
								newsState
									.reload()
									.then( () =>
										toast(
											__(
												'News is up to date',
												'zero-spam'
											)
										)
									)
							}
						>
							{ __( 'Refresh', 'zero-spam' ) }
						</Button>
					</span>
				}
			/>
			{ newsState.error && ! news.length && (
				<div className="zs-empty">
					<b>
						{ __(
							"The news isn't available right now",
							'zero-spam'
						) }
					</b>
					<span className="zs-hint">
						{ __(
							'zerospam.org could not be reached. Try Refresh in a little while.',
							'zero-spam'
						) }
					</span>
				</div>
			) }
			{ categories.length > 1 && (
				<div
					className="zs-chips zs-newschips"
					role="group"
					aria-label={ __( 'Filter news by category', 'zero-spam' ) }
				>
					<Chip pressed={ '' === cat } onClick={ () => setCat( '' ) }>
						{ __( 'All', 'zero-spam' ) }
					</Chip>
					{ categories.map( ( c ) => (
						<Chip
							key={ c }
							pressed={ c === cat }
							onClick={ () => setCat( c ) }
						>
							{ c }
						</Chip>
					) ) }
				</div>
			) }
			{ list.length > 0 && (
				<ul className="zs-news">
					{ list.map( ( item ) => (
						<li key={ item.link } className="zs-news-i">
							<div className="zs-news-m">
								{ item.categories?.[ 0 ] && (
									<Badge>{ item.categories[ 0 ] }</Badge>
								) }
								{ item.security_notice && (
									<Badge tone="warn" icon="warn">
										{ __( 'Security notice', 'zero-spam' ) }
									</Badge>
								) }
								{ item.date && (
									<time
										className="zs-hint"
										dateTime={ item.date }
									>
										{ day( item.date ) }
									</time>
								) }
							</div>
							<a
								className="zs-news-t"
								href={ utm( item.link, 'overview-news' ) }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ item.title }
								<span className="zs-sr-only">
									{ __(
										'(opens zerospam.org)',
										'zero-spam'
									) }
								</span>
							</a>
							{ item.excerpt && (
								<p className="zs-hint zs-news-x">
									{ item.excerpt }
								</p>
							) }
						</li>
					) ) }
				</ul>
			) }
			<div className="zs-news-f zs-tile-foot">
				<div className="zs-follow-c">
					<span className="zs-hint">
						{ __( 'Follow Zero Spam', 'zero-spam' ) }
					</span>
					{ FOLLOW.slice( 0, 2 ).map( ( f ) => (
						<a
							key={ f.key }
							className="zs-soc"
							href={ f.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							<SocialIcon name={ f.key } />
							<span className="zs-sr-only">
								{ sprintf(
									/* translators: %s: network name, such as Facebook */
									__( 'Zero Spam on %s:', 'zero-spam' ),
									f.name
								) }
							</span>
							<span>{ f.handle }</span>
							<span className="zs-sr-only">
								{ __( '(opens in a new tab)', 'zero-spam' ) }
							</span>
						</a>
					) ) }
				</div>
				<ExtLink
					href={ utm(
						'https://www.zerospam.org/articles/',
						'overview-news'
					) }
					newTab={ __( '(opens in a new tab)', 'zero-spam' ) }
				>
					{ __( 'All articles', 'zero-spam' ) }
				</ExtLink>
			</div>
		</Tile>
	);
}

/**
 * What Zero Spam checks (System).
 *
 * @param {Object}  props        Props.
 * @param {Object}  props.schema settings/schema.
 * @param {boolean} props.wide   Full width (no news tile beside it).
 */
export function SystemTile( { schema, wide } ) {
	const { modules, enhanced } = useApp();
	const { toast } = useFeedback();
	const list = modules?.modules || [];
	const on = ( key ) => true === findModule( list, key )?.enabled;
	const forms = list.filter(
		( m ) =>
			'integrations' === m.group && m.plugin?.active && true === m.enabled
	).length;
	const method = ( schema?.settings || [] ).find(
		( s ) => 'block_method' === s.key
	)?.value;
	const methodLabel = String( method || 'php' ).startsWith( 'htaccess' )
		? '.htaccess'
		: 'PHP';
	const ipLabels = {
		cloudflare: __( 'Cloudflare', 'zero-spam' ),
		trusted_proxy: __( 'Trusted proxy', 'zero-spam' ),
		untrusted_proxy: __( 'Untrusted proxy', 'zero-spam' ),
		direct: __( 'Direct connection', 'zero-spam' ),
		none: __( 'Not available', 'zero-spam' ),
	};
	const ip = ipLabels[ boot.environment.ipSource ] || ipLabels.direct;
	const checking = __( 'Checking', 'zero-spam' );
	const off = __( 'Off', 'zero-spam' );

	const rows = [
		[
			__( 'Comments', 'zero-spam' ),
			on( 'comments' ) ? checking : off,
			on( 'comments' ),
		],
		[
			__( 'Login', 'zero-spam' ),
			on( 'login' ) ? checking : off,
			on( 'login' ),
		],
		[
			__( 'Registration', 'zero-spam' ),
			on( 'registration' ) ? checking : off,
			on( 'registration' ),
		],
		[
			__( 'Forms', 'zero-spam' ),
			forms
				? sprintf(
						/* translators: %d: number of plugins */
						_n( '%d plugin', '%d plugins', forms, 'zero-spam' ),
						forms
					)
				: __( 'None active', 'zero-spam' ),
			forms > 0,
		],
		[ __( 'Block method', 'zero-spam' ), methodLabel, true ],
		[
			__( 'IP source', 'zero-spam' ),
			ip,
			'untrusted_proxy' !== boot.environment.ipSource,
		],
		[
			__( 'Stop Forum Spam', 'zero-spam' ),
			on( 'stop_forum_spam' ) ? checking : off,
			on( 'stop_forum_spam' ),
		],
		[
			__( 'Zero Spam network', 'zero-spam' ),
			enhanced ? checking : __( 'Not used (Basic)', 'zero-spam' ),
			enhanced,
		],
	];

	const env = sprintf(
		/* translators: 1: Zero Spam version, 2: WordPress version, 3: PHP version */
		__( 'Zero Spam %1$s · WordPress %2$s · PHP %3$s', 'zero-spam' ),
		boot.version,
		boot.environment.wordpress,
		boot.environment.php
	);
	const report = [
		env,
		enhanced ? __( 'Enhanced', 'zero-spam' ) : __( 'Basic', 'zero-spam' ),
		...rows.map( ( [ k, v ] ) => `${ k }: ${ v }` ),
	].join( ' · ' );

	const copy = () => {
		const done = () => toast( __( 'Copied to clipboard', 'zero-spam' ) );
		const fail = () =>
			toast(
				__(
					'Copy did not work. Select the text and press Ctrl+C or Cmd+C.',
					'zero-spam'
				)
			);
		try {
			window.navigator.clipboard.writeText( report ).then( done, fail );
		} catch {
			fail();
		}
	};

	return (
		<Tile span={ wide ? 12 : 5 } labelledBy="zs-sy-h" className="zs-system">
			<TileHead
				eyebrow={ __( 'System', 'zero-spam' ) }
				id="zs-sy-h"
				title={ __( 'What Zero Spam checks', 'zero-spam' ) }
			/>
			<ul className="zs-checks">
				{ rows.map( ( [ k, v, ok ] ) => (
					<li key={ k }>
						<span
							className={ ok ? 'zs-d' : 'zs-d zs-d-off' }
							aria-hidden="true"
						/>
						{ k } <b>{ v }</b>
					</li>
				) ) }
			</ul>
			<div className="zs-sysfoot zs-tile-foot">
				<span className="zs-hint">{ env }</span>
				<Button
					variant="secondary"
					size="sm"
					icon="copy"
					onClick={ copy }
				>
					{ __( 'Copy system report', 'zero-spam' ) }
				</Button>
			</div>
		</Tile>
	);
}

/**
 * Renewal reminder (Enhanced, within 30 days of the renewal date).
 */
export function RenewTile() {
	const { license } = useApp();
	const date = license?.renewal?.date;
	if ( ! date ) {
		return null;
	}
	const when = parseDate( date );
	const days = when ? ( when.getTime() - Date.now() ) / 864e5 : Infinity;
	if ( days < 0 || days > 30 ) {
		return null;
	}
	const auto = 'active' === license?.renewal?.status;
	return (
		<Tile span={ 12 } labelledBy="zs-rn-h" className="zs-renew">
			<div className="zs-renew-in">
				<span className="zs-ico zs-ico-ok">
					<Icon name="clock" />
				</span>
				<div className="zs-li-body">
					<h2 id="zs-rn-h" className="zs-nowrap">
						{ auto
							? sprintf(
									/* translators: %s: date */
									__(
										'Renews %s · auto-renew on',
										'zero-spam'
									),
									day( date )
								)
							: sprintf(
									/* translators: %s: date */
									__( 'Renews %s', 'zero-spam' ),
									day( date )
								) }
					</h2>
					<span className="zs-hint">
						{ auto
							? __(
									"Your membership continues automatically. There's nothing you need to do.",
									'zero-spam'
								)
							: __(
									'Renew on zerospam.org to keep Enhanced Protection.',
									'zero-spam'
								) }
					</span>
				</div>
			</div>
		</Tile>
	);
}
