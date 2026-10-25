/**
 * Overview: the hero, the threats chart, the live feed and the tiles, laid
 * as courses of a running-bond wall (seams never line up row to row).
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { cx, Icon } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { land } from '../app/mark';
import { protectionStatus } from '../app/shell/shell';
import { boot, nowLocal, PATHS, useResource } from '../data/api';
import { ago, minutesAgo, num } from '../data/format';
import { createAnnounceGate } from '../data/poll';
import { useLiveFeed } from '../data/use-live-feed';
import { addToDays, milestones } from './chart-data';
import { ThreatsChart } from './chart';
import { HeroWall } from './hero-wall';
import {
	EnhancedTile,
	LatestBlocks,
	LevelTile,
	MilestoneTile,
	NeedsAttention,
	NetworkPanel,
	NewsTile,
	RenewTile,
	SystemTile,
	WeekTile,
} from './tiles';

const bucketOf = ( type ) =>
	( {
		comment: 'comments',
		login: 'login',
		registration: 'registration',
		blocked: 'blocked',
	} )[ type ] || 'forms';

/**
 * Adds live entries to a summary's totals.
 *
 * @param {Object|undefined} summary stats/summary answer.
 * @param {Array}            items   New entries.
 * @return {Object|undefined} Summary.
 */
function bump( summary, items ) {
	if ( ! summary ) {
		return summary;
	}
	const byType = [ ...( summary.by_type || [] ) ];
	items.forEach( ( item ) => {
		const i = byType.findIndex( ( t ) => t.type === item.type );
		if ( i >= 0 ) {
			byType[ i ] = { ...byType[ i ], count: byType[ i ].count + 1 };
		} else {
			byType.push( {
				type: item.type,
				label: item.type_label,
				count: 1,
			} );
		}
	} );
	return {
		...summary,
		total: summary.total + items.length,
		delta: summary.delta + items.length,
		by_type: byType,
	};
}

/**
 * The hero.
 *
 * @param {Object}                          props              Props.
 * @param {number}                          props.total        Threats stopped in 30 days.
 * @param {Object}                          props.feed         Live feed state.
 * @param {(...args: unknown[]) => unknown} props.onToggleLive Pause or resume.
 */
function Hero( { total, feed, onToggleLive } ) {
	const { enhanced, license, modules, reduced, prefs } = useApp();
	const status = protectionStatus( modules );
	const site = boot.site.name;
	const last = feed.items[ 0 ];
	const paused = Boolean( feed.reason );
	const list = modules?.modules || [];
	const entry = list.filter(
		( m ) =>
			true === m.enabled &&
			( [ 'comments', 'registration', 'login' ].includes( m.key ) ||
				( 'integrations' === m.group && m.plugin?.active ) )
	).length;
	const ipNames = {
		cloudflare: __( 'Cloudflare', 'zero-spam' ),
		trusted_proxy: __( 'your trusted proxy', 'zero-spam' ),
		untrusted_proxy: __( 'an untrusted proxy', 'zero-spam' ),
		direct: __( 'the direct connection', 'zero-spam' ),
		none: __( 'nowhere (not available)', 'zero-spam' ),
	};

	let liveText = __( 'Live', 'zero-spam' );
	if ( 'idle' === feed.reason ) {
		liveText = __( 'Paused while you were away', 'zero-spam' );
	} else if ( paused ) {
		liveText = __( 'Paused', 'zero-spam' );
	}

	const q = license?.queries;

	return (
		<div
			className={ cx(
				'zs-hero',
				'zs-s12',
				'zs-dark-block',
				enhanced && 'zs-has-net'
			) }
		>
			<HeroWall reduced={ reduced } />
			<div className="zs-hero-copy">
				<div className="zs-inline-row">
					<span className="zs-pill">
						<span
							className={ cx(
								'zs-dot',
								'warn' === status.tone && 'zs-dot-warn'
							) }
							aria-hidden="true"
						/>
						{ __( 'System status:', 'zero-spam' ) }{ ' ' }
						<span
							className={
								'ok' === status.tone ? 'zs-ok' : 'zs-warn-t'
							}
						>
							{ status.label }
						</span>
					</span>
					<button
						className={ cx(
							'zs-pill',
							'zs-live',
							! paused && 'zs-on'
						) }
						type="button"
						aria-pressed={ prefs.live_paused ? 'false' : 'true' }
						onClick={ onToggleLive }
					>
						<span className="zs-ldot" aria-hidden="true" />
						<span className="zs-live-t">{ liveText }</span>
						<span aria-hidden="true">·</span>
						<span>
							{ prefs.live_paused || paused
								? __( 'Resume', 'zero-spam' )
								: __( 'Pause', 'zero-spam' ) }
						</span>
						<span className="zs-sr-only">
							{ __( 'live feed', 'zero-spam' ) }
						</span>
					</button>
				</div>
				<h1 className="zs-h1" id="zs-ov-h" tabIndex={ -1 }>
					{ site
						? sprintf(
								/* translators: %s: site name */
								__( '%s is protected.', 'zero-spam' ),
								site
							)
						: __( 'Your site is protected.', 'zero-spam' ) }
					<span>
						{ total > 0
							? sprintf(
									/* translators: %s: number of threats */
									_n(
										'%s threat stopped.',
										'%s threats stopped.',
										total,
										'zero-spam'
									),
									num( total )
								)
							: __( 'Watching for threats.', 'zero-spam' ) }
					</span>
				</h1>
				<p className="zs-lede">
					{ enhanced
						? __(
								"Every comment, login, registration and form was checked in the last 30 days, and every visitor was checked against Zero Spam's network of 30,000+ sites.",
								'zero-spam'
							)
						: __(
								'Every comment, login, registration and form Zero Spam protects was checked in the last 30 days.',
								'zero-spam'
							) }{ ' ' }
					{ enhanced ? (
						<b>
							{ __(
								'Enhanced Protection is active.',
								'zero-spam'
							) }
						</b>
					) : (
						<>
							{ __( "You're on", 'zero-spam' ) }{ ' ' }
							<b>{ __( 'Basic protection', 'zero-spam' ) }</b>
							{ __( ', which is free.', 'zero-spam' ) }
						</>
					) }
				</p>
				<div className="zs-hero-meta">
					<span>
						{ __( 'Last detection', 'zero-spam' ) }{ ' ' }
						<b>
							{ last
								? ago( minutesAgo( last.date, nowLocal() ) )
								: __( 'none yet', 'zero-spam' ) }
						</b>
					</span>
					<span>
						{ __( 'Visitor IPs read from', 'zero-spam' ) }{ ' ' }
						<b>
							{ ipNames[ boot.environment.ipSource ] ||
								ipNames.direct }
						</b>
					</span>
					{ modules && (
						<span>
							{ __( 'Checks run on', 'zero-spam' ) }{ ' ' }
							<b>
								{ sprintf(
									/* translators: %d: number of entry points */
									_n(
										'%d entry point',
										'%d entry points',
										entry,
										'zero-spam'
									),
									entry
								) }
							</b>
						</span>
					) }
				</div>
			</div>
			{ enhanced && (
				<aside className="zs-hero-net" aria-labelledby="zs-hn-l">
					<span className="zs-pill zs-nshield">
						<Icon name="shield" size="sm" />
						{ 'unreachable' === license?.status
							? __( 'Network shield · retrying', 'zero-spam' )
							: __( 'Network shield · on', 'zero-spam' ) }
					</span>
					<p className="zs-hn-count" id="zs-hn-l">
						<b className="zs-mono">{ q ? num( q.used ) : '—' }</b>
						<span>
							{ __(
								'visitors checked against the network this month',
								'zero-spam'
							) }
						</span>
					</p>
					{ q && q.limit > 0 && (
						<div className="zs-zc-bar" aria-hidden="true">
							<i
								style={ {
									width: `${ Math.min( 100, Math.round( ( q.used / q.limit ) * 100 ) ) }%`,
								} }
							/>
						</div>
					) }
				</aside>
			) }
		</div>
	);
}

/**
 * The Overview.
 */
export function Overview() {
	const app = useApp();
	const {
		enhanced,
		reduced,
		prefs,
		savePrefs,
		isDismissed,
		setDay,
		newsState,
	} = app;
	const { toast, announce } = useFeedback();
	const s7 = useResource( PATHS.summary( 7 ) );
	const s30 = useResource( PATHS.summary( 30 ) );
	const series = useResource( PATHS.timeseries );
	const schema = useResource( PATHS.schema );
	const activity = useResource( PATHS.activity );
	const [ freshDay, setFreshDay ] = useState( -1 );
	const [ flash, setFlash ] = useState( '' );
	const gate = useRef( createAnnounceGate() );

	const onNew = useCallback(
		( items ) => {
			setDay( ( d ) => bump( d, items ) );
			s7.setData( ( d ) => bump( d, items ) );
			s30.setData( ( d ) => bump( d, items ) );
			series.setData( ( d ) =>
				d ? { ...d, days: addToDays( d.days, items ) } : d
			);
			setFreshDay( ( series.data?.days?.length || 30 ) - 1 );
			setFlash( bucketOf( items[ 0 ].type ) );
			window.setTimeout( () => {
				setFreshDay( -1 );
				setFlash( '' );
			}, 2500 );
			land();
			if ( gate.current() ) {
				const latest = items[ 0 ];
				announce(
					sprintf(
						/* translators: 1: number of new blocks, 2: detection type, 3: IP address */
						_n(
							'%1$s new block. Latest: %2$s from %3$s.',
							'%1$s new blocks. Latest: %2$s from %3$s.',
							items.length,
							'zero-spam'
						),
						num( items.length ),
						latest.type_label,
						latest.ip
					)
				);
			}
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps -- setData functions are stable.
		[ announce, series.data ]
	);

	const feed = useLiveFeed( {
		initial: activity.data,
		userPaused: true === prefs.live_paused,
		reduced,
		onNew,
	} );

	const toggleLive = () => {
		const pause = ! prefs.live_paused;
		savePrefs( { live_paused: pause } ).then( () =>
			toast(
				pause
					? __( 'Live feed paused', 'zero-spam' )
					: __( 'Live feed resumed', 'zero-spam' )
			)
		);
	};

	const summaries = { 1: app.day, 7: s7.data, 30: s30.data };
	const total = s30.data?.total ?? 0;
	const { reached } = milestones( total );
	const showMilestone =
		reached > 0 && ! isDismissed( `milestone-${ reached }` );
	const showNews =
		boot.newsEnabled &&
		! ( newsState.error && 404 === newsState.error?.data?.status );

	const days = useMemo( () => series.data?.days || [], [ series.data ] );

	return (
		<div className="zs-view zs-overview" aria-labelledby="zs-ov-h">
			<div className="zs-wall">
				<Hero
					total={ total }
					feed={ feed }
					onToggleLive={ toggleLive }
				/>
				{ enhanced && <NetworkPanel /> }
				<ThreatsChart
					summaries={ summaries }
					days={ days }
					reduced={ reduced }
					freshDay={ freshDay }
				/>
				<LatestBlocks feed={ feed } />
				<NeedsAttention wide={ enhanced } />
				{ ! enhanced && <EnhancedTile total={ total } /> }
				<LevelTile />
				<WeekTile
					summary={ s7.data }
					wide={ ! showMilestone }
					flash={ flash }
				/>
				{ showMilestone && (
					<MilestoneTile
						total={ total }
						onDismiss={ () =>
							document.getElementById( 'zs-wk-h' )?.focus()
						}
					/>
				) }
				{ showNews && <NewsTile /> }
				<SystemTile schema={ schema.data } wide={ ! showNews } />
				<RenewTile />
			</div>
		</div>
	);
}
