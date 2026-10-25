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
import { PATHS, request } from './api';
import { createPoller, highestId, IDLE_LIMIT, pauseReason } from './poll';

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
 * @param {boolean}                         options.reduced    Animations are off.
 * @param {(...args: unknown[]) => unknown} options.onNew      Receives every batch of new entries (counts, mark, announcements).
 * @param {number}                          [options.keep]     Entries kept.
 * @return {Object} { items, fresh, queued, showQueued, reason }.
 */
export function useLiveFeed( {
	initial,
	userPaused,
	reduced,
	onNew,
	keep = 6,
} ) {
	const [ items, setItems ] = useState( [] );
	const [ fresh, setFresh ] = useState( () => new Set() );
	const [ queued, setQueued ] = useState( [] );
	const [ hidden, setHidden ] = useState(
		() => 'undefined' !== typeof document && document.hidden
	);
	const [ idle, setIdle ] = useState( false );
	const poller = useRef( null );
	const lastInput = useRef( Date.now() );
	const onNewRef = useRef( onNew );
	const reducedRef = useRef( reduced );
	onNewRef.current = onNew;
	reducedRef.current = reduced;

	const loaded = Array.isArray( initial );

	useEffect( () => {
		if ( loaded ) {
			setItems( initial.slice( 0, keep ) );
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

	// One poller for the page.
	useEffect( () => {
		if ( ! loaded ) {
			return undefined;
		}
		poller.current = createPoller( {
			sinceId: highestId( initial, 0 ),
			fetch: ( since ) => request( { path: PATHS.since( since ) } ),
			onItems: ( entries ) => {
				onNewRef.current( entries );
				if ( reducedRef.current ) {
					setQueued( ( q ) => [ ...entries, ...q ] );
				} else {
					insert( entries );
				}
			},
		} );
		return () => poller.current?.stop();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Created once the first list is in.
	}, [ loaded ] );

	// Tab visibility and inactivity.
	useEffect( () => {
		const onVisibility = () => setHidden( document.hidden );
		const onInput = () => {
			lastInput.current = Date.now();
			setIdle( false );
		};
		document.addEventListener( 'visibilitychange', onVisibility );
		INPUT_EVENTS.forEach( ( e ) =>
			window.addEventListener( e, onInput, { passive: true } )
		);
		const check = window.setInterval( () => {
			if ( Date.now() - lastInput.current >= IDLE_LIMIT ) {
				setIdle( true );
			}
		}, 60000 );
		return () => {
			document.removeEventListener( 'visibilitychange', onVisibility );
			INPUT_EVENTS.forEach( ( e ) =>
				window.removeEventListener( e, onInput )
			);
			window.clearInterval( check );
		};
	}, [] );

	const reason = pauseReason( {
		userPaused,
		hidden,
		idleFor: idle ? IDLE_LIMIT : 0,
	} );

	// Start, stop and resume as the conditions change. The first start waits
	// one interval (the list was just loaded); a resume fetches at once.
	const started = useRef( false );
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
	}, [ reason, loaded ] );

	const showQueued = useCallback( () => {
		setQueued( ( q ) => {
			if ( q.length ) {
				insert( q );
			}
			return [];
		} );
	}, [ insert ] );

	return { items, fresh, queued, showQueued, reason, loaded };
}
