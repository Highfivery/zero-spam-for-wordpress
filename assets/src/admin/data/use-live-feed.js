/**
 * The Overview's live feed (build plan §6): polls `activity?since_id=` with
 * the scheduler in poll.js while the tab is visible, the user has not
 * paused it and they were active in the last 30 minutes. Under reduced
 * motion new entries wait behind an "N new · Show" button instead of
 * sliding in.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { boot, PATHS, request } from './api';
import {
	createIdleTracker,
	createPoller,
	detectionsOnly,
	highestId,
	IDLE_LIMIT,
	pauseReason,
	watchVisibility,
} from './poll';

const INPUT_EVENTS = [
	'pointerdown',
	'keydown',
	'wheel',
	'touchstart',
	'mousemove',
];

/**
 * @param {Object}                          options            Options.
 * @param {Array}                           options.initial    Entries already shown (newest first), or undefined while loading.
 * @param {boolean}                         options.userPaused The user's live_paused preference.
 * @param {boolean}                         options.reduced    Animations are off (new entries always wait behind the button).
 * @param {(...args: unknown[]) => unknown} options.onNew      Receives every batch of new entries and their count (counts, mark, announcements).
 * @param {number}                          [options.keep]     Entries kept.
 * @param {(...args: unknown[]) => unknown} [options.path]     ( sinceId ) => path: another request than the Overview's (filters, fewer fields); its X-ZeroSpam-New-Detections header is read the same way.
 * @param {number}                          [options.sinceId]  Highest ID already shown, when it is not the highest of `initial` (a filtered or sorted list).
 * @param {string}                          [options.resetKey] Changing it starts over from `sinceId` with nothing queued (new filters).
 * @return {Object} { items, fresh, queued, queuedCount, showQueued, reason, loaded, wake, justWoke }.
 */
export function useLiveFeed( {
	initial,
	userPaused,
	reduced,
	onNew,
	keep = 6,
	path,
	sinceId,
	resetKey = '',
} ) {
	const [ items, setItems ] = useState( [] );
	const [ fresh, setFresh ] = useState( () => new Set() );
	const [ queued, setQueued ] = useState( [] );
	// New detections waiting, counted by the server after a burst bigger than a page.
	const [ queuedCount, setQueuedCount ] = useState( 0 );
	const [ hidden, setHidden ] = useState(
		() => 'undefined' !== typeof document && document.hidden
	);
	const [ idle, setIdle ] = useState( false );
	const poller = useRef( null );
	const tracker = useRef( null );
	const onNewRef = useRef( onNew );
	const reducedRef = useRef( reduced );
	const pathRef = useRef( path );
	const started = useRef( false );
	onNewRef.current = onNew;
	reducedRef.current = reduced;
	pathRef.current = path;

	const loaded = Array.isArray( initial );

	useEffect( () => {
		if ( loaded ) {
			setItems(
				detectionsOnly( initial, boot.nonDetectionTypes ).slice(
					0,
					keep
				)
			);
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Seed once, when the first list arrives.
	}, [ loaded ] );

	const insert = useCallback(
		( entries ) => {
			setItems( ( list ) => [ ...entries, ...list ].slice( 0, keep ) );
			setFresh( new Set( entries.map( ( e ) => e.id ) ) );
		},
		[ keep ]
	);

	// One poller for the page (a new one when resetKey changes).
	useEffect( () => {
		if ( ! loaded ) {
			return undefined;
		}
		started.current = false;
		setQueued( [] );
		setQueuedCount( 0 );
		poller.current = createPoller( {
			sinceId:
				undefined !== sinceId
					? Number( sinceId ) || 0
					: highestId( initial, 0 ),
			fetch: ( since ) =>
				request( {
					path: pathRef.current
						? pathRef.current( since )
						: PATHS.since( since ),
					parse: false,
				} ).then( ( response ) =>
					response.json().then( ( list ) => ( {
						items: list,
						total: Number(
							response.headers.get( 'X-ZeroSpam-New-Detections' )
						),
					} ) )
				),
			onItems: ( all, total ) => {
				const entries = detectionsOnly( all, boot.nonDetectionTypes );
				// The server's count leaves non-detections out already.
				const count = Math.max(
					entries.length,
					total - ( all.length - entries.length )
				);
				if ( ! entries.length && count <= 0 ) {
					return;
				}
				onNewRef.current( entries, count );
				if ( reducedRef.current ) {
					setQueued( ( q ) => [ ...entries, ...q ] );
					setQueuedCount( ( n ) => n + count );
				} else {
					insert( entries );
				}
			},
		} );
		return () => poller.current?.stop();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Created once the first list is in, again on a reset.
	}, [ loaded, resetKey ] );

	// Tab visibility and inactivity.
	useEffect( () => {
		tracker.current = createIdleTracker( { onChange: setIdle } );
		const onInput = () => tracker.current.input();
		const stopVisibility = watchVisibility( document, setHidden );
		INPUT_EVENTS.forEach( ( e ) =>
			window.addEventListener( e, onInput, { passive: true } )
		);
		const check = window.setInterval(
			() => tracker.current.check(),
			60000
		);
		return () => {
			stopVisibility();
			INPUT_EVENTS.forEach( ( e ) =>
				window.removeEventListener( e, onInput )
			);
			window.clearInterval( check );
		};
	}, [] );

	/** Restarts a feed the page paused (hidden tab, inactivity). */
	const wake = useCallback( () => tracker.current?.wake(), [] );

	/** True while a press still belongs to the input that woke the feed. */
	const justWoke = useCallback(
		() => Boolean( tracker.current?.justWoke() ),
		[]
	);

	const reason = pauseReason( {
		userPaused,
		hidden,
		idleFor: idle ? IDLE_LIMIT : 0,
	} );

	// Start, stop and resume as the conditions change. The first start waits
	// one interval (the list was just loaded); a resume fetches at once.
	useEffect( () => {
		const p = poller.current;
		if ( ! p ) {
			return;
		}
		if ( null !== reason ) {
			p.pause();
		} else if ( started.current ) {
			p.resume();
		} else {
			started.current = true;
			p.start();
		}
	}, [ reason, loaded, resetKey ] );

	const showQueued = useCallback( () => {
		setQueuedCount( 0 );
		setQueued( ( q ) => {
			if ( q.length ) {
				insert( q );
			}
			return [];
		} );
	}, [ insert ] );

	return {
		items,
		fresh,
		queued,
		queuedCount,
		showQueued,
		reason,
		loaded,
		wake,
		justWoke,
	};
}
