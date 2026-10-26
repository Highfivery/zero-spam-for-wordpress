/**
 * Overview: the hero, the threats chart, the live feed and the tiles, laid
 * as courses of a running-bond wall (seams never line up row to row).
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	createInterpolateElement,
	useCallback,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { cx, Icon } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { land } from '../app/mark';
import { protectionStatus } from '../data/status';
import { boot, nowLocal, PATHS, useResource } from '../data/api';
import { ago, minutesAgo, num } from '../data/format';
import {
	createAnnounceGate,
	createNewDetectionsAnnouncer,
	liveAction,
} from '../data/poll';
import { useLiveFeed } from '../data/use-live-feed';
import {
	addToDays,
	bumpSummary,
	milestones,
	networkFigures,
	networkSeries,
} from './chart-data';
import { Spark } from './spark';
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
 * The screen reader summary of new detections.
 *
 * @param {Array}  items New entries, newest first.
 * @param {number} count New detections in all.
 * @return {string} Message.
 */
export function newBlocksMessage( items, count ) {
	const latest = items[ 0 ];
	return sprintf(
		/* translators: 1: number of new blocks, 2: detection type, 3: IP address */
		_n(
			'%1$s new block. Latest: %2$s from %3$s.',
			'%1$s new blocks. Latest: %2$s from %3$s.',
			count,
			'zero-spam'
		),
		num( count ),
		latest.type_label,
		latest.ip
	);
}

/**
 * The hero's first line, honest about what is switched on.
 *
 * @param {string} state protectionStatus().state.
 * @param {string} site  Site name ('' when it has none).
 * @return {string} Headline.
 */
function headline( state, site ) {
	if ( 'off' === state ) {
		return site
			? sprintf(
					/* translators: %s: site name */
					__( '%s is not protected yet.', 'zero-spam' ),
					site
				)
			: __( 'Your site is not protected yet.', 'zero-spam' );
	}
	if ( 'partial' === state ) {
		return site
			? sprintf(
					/* translators: %s: site name */
					__( '%s is partly protected.', 'zero-spam' ),
					site
				)
			: __( 'Your site is partly protected.', 'zero-spam' );
	}
	return site
		? sprintf(
				/* translators: %s: site name */
				__( '%s is protected.', 'zero-spam' ),
				site
			)
		: __( 'Your site is protected.', 'zero-spam' );
}

/**
 * The hero's lede: one translatable sentence per case.
 *
 * @param {string}  state    protectionStatus().state.
 * @param {boolean} enhanced Enhanced Protection is on.
 * @return {import('react').ReactNode} Lede.
 */
function lede( state, enhanced ) {
	if ( 'off' === state ) {
		return __(
			'Comment, login and registration checks are off, so nothing is checked for spam yet. Turn them on under Needs attention below.',
			'zero-spam'
		);
	}
	if ( 'partial' === state ) {
		return __(
			'Some of the core checks are off. The ones that are on were applied to every submission in the last 30 days; turn the rest on under Needs attention below.',
			'zero-spam'
		);
	}
	return enhanced
		? createInterpolateElement(
				__(
					"Every comment, login, registration and form was checked in the last 30 days, and every visitor was checked against Zero Spam's network of 30,000+ sites. <b>Enhanced Protection is active.</b>",
					'zero-spam'
				),
				{ b: <b /> }
			)
		: createInterpolateElement(
				__(
					"Every comment, login, registration and form Zero Spam protects was checked in the last 30 days. You're on <b>Basic protection</b>, which is free.",
					'zero-spam'
				),
				{ b: <b /> }
			);
}

/**
 * The hero.
 *
 * @param {Object}                          props              Props.
 * @param {number}                          props.total        Threats stopped in 30 days.
 * @param {Object}                          props.feed         Live feed state.
 * @param {(...args: unknown[]) => unknown} props.onToggleLive Pause or resume.
 * @param {Object}                          props.summary      stats/summary?range=30.
 * @param {Array}                           props.days         stats/timeseries days.
 */
function Hero( { total, feed, onToggleLive, summary, days } ) {
	const { enhanced, license, modules, reduced } = useApp();
	const status = protectionStatus( modules );
	const site = boot.site.name;
	const last = feed.items[ 0 ];
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

	const live = ! feed.reason;
	let liveState = __( 'Live', 'zero-spam' );
	if ( 'idle' === feed.reason ) {
		liveState = __( 'Paused while you were away', 'zero-spam' );
	} else if ( feed.reason ) {
		liveState = __( 'Paused', 'zero-spam' );
	}

	const net = networkFigures( summary );

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
						{ createInterpolateElement(
							sprintf(
								/* translators: %s: protection status, for example "All checks active" */
								__( 'System status: <s>%s</s>', 'zero-spam' ),
								status.label
							),
							{
								s: (
									<span
										className={
											'ok' === status.tone
												? 'zs-ok'
												: 'zs-warn-t'
										}
									/>
								),
							}
						) }
					</span>
					{ /* The name stays "Live feed"; the state is the pressed state and the description. */ }
					<button
						className={ cx(
							'zs-pill',
							'zs-live',
							live && 'zs-on'
						) }
						type="button"
						aria-pressed={ live ? 'true' : 'false' }
						aria-describedby="zs-live-state"
						onClick={ onToggleLive }
					>
						<span className="zs-sr-only">
							{ __( 'Live feed', 'zero-spam' ) }
						</span>
						<span className="zs-ldot" aria-hidden="true" />
						<span className="zs-live-t" aria-hidden="true">
							{ liveState }
						</span>
						<span aria-hidden="true">·</span>
						<span aria-hidden="true">
							{ live
								? __( 'Pause', 'zero-spam' )
								: __( 'Resume', 'zero-spam' ) }
						</span>
					</button>
					<span id="zs-live-state" className="zs-sr-only">
						{ liveState }
					</span>
				</div>
				<h1 className="zs-h1" id="zs-ov-h" tabIndex={ -1 }>
					{ headline( status.state, site ) }
					{ ! enhanced && (
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
					) }
				</h1>
				{ enhanced && (
					<p className="zs-hero-sub">
						{ net.network > 0
							? createInterpolateElement(
									sprintf(
										/* translators: %s: number of threats */
										__(
											'The network caught <b>%s</b>.',
											'zero-spam'
										),
										num( net.network )
									),
									{ b: <b /> }
								)
							: sprintf(
									/* translators: %s: number of threats */
									_n(
										'%s threat stopped.',
										'%s threats stopped.',
										total,
										'zero-spam'
									),
									num( total )
								) }
					</p>
				) }
				<p className="zs-lede">{ lede( status.state, enhanced ) }</p>
				<div className="zs-hero-meta">
					<span>
						{ createInterpolateElement(
							sprintf(
								/* translators: %s: when, such as 2 min ago */
								__( 'Last detection <b>%s</b>', 'zero-spam' ),
								last
									? ago( minutesAgo( last.date, nowLocal() ) )
									: __( 'none yet', 'zero-spam' )
							),
							{ b: <b /> }
						) }
					</span>
					<span>
						{ createInterpolateElement(
							sprintf(
								/* translators: %s: where visitor addresses come from, such as Cloudflare */
								__(
									'Visitor IPs read from <b>%s</b>',
									'zero-spam'
								),
								ipNames[ boot.environment.ipSource ] ||
									ipNames.direct
							),
							{ b: <b /> }
						) }
					</span>
					{ modules && (
						<span>
							{ createInterpolateElement(
								sprintf(
									/* translators: %d: number of entry points */
									_n(
										'Checks run on <b>%d entry point</b>',
										'Checks run on <b>%d entry points</b>',
										entry,
										'zero-spam'
									),
									entry
								),
								{ b: <b /> }
							) }
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
						<b className="zs-mono">{ num( net.network ) }</b>
						<span>
							{ __(
								'threats caught by the Zero Spam network · last 30 days',
								'zero-spam'
							) }
						</span>
					</p>
					<div className="zs-hn-spark">
						<Spark
							values={ networkSeries( days ) }
							width={ 240 }
							height={ 48 }
							className="zs-spark-big"
							label={ sprintf(
								/* translators: %s: number of threats */
								__(
									'Threats the Zero Spam network caught, per day over the last 30 days: %s in all',
									'zero-spam'
								),
								num( net.network )
							) }
						/>
					</div>
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
	const announcer = useRef( null );
	if ( ! announcer.current ) {
		announcer.current = createNewDetectionsAnnouncer( {
			gate: createAnnounceGate(),
			announce: ( message ) => announce( message ),
			message: newBlocksMessage,
		} );
	}

	const onNew = useCallback(
		( items, count = items.length ) => {
			setDay( ( d ) => bumpSummary( d, items, count ) );
			s7.setData( ( d ) => bumpSummary( d, items, count ) );
			s30.setData( ( d ) => bumpSummary( d, items, count ) );
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
			announcer.current( items, count );
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

	// Decided from the state the button showed when it was pressed.
	const toggleLive = () => {
		const action = liveAction( {
			userPaused: true === prefs.live_paused,
			reason: feed.reason,
			justWoke: feed.justWoke(),
		} );
		if ( 'wake' === action ) {
			feed.wake();
			toast( __( 'Live feed resumed', 'zero-spam' ) );
			return;
		}
		const pause = 'pause' === action;
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
					summary={ s30.data }
					days={ days }
				/>
				{ enhanced && (
					<NetworkPanel summary={ s30.data } days={ days } />
				) }
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
