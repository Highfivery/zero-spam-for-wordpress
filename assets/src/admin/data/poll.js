/**
 * The live feed's scheduler (build plan §6), free of React and of the
 * browser so it can be unit tested with fake timers.
 *
 * - Polls every 15 s with ±20 % jitter, so sites opened at the same moment
 *   do not poll in step.
 * - After a failed request it waits 30 s, then 60 s, then 120 s (and stays
 *   at 120 s) until a request succeeds again.
 * - It stops while the tab is hidden, after 30 minutes without input, or
 *   when the user pauses it; resuming fetches at once.
 * - Under reduced motion it keeps polling but never inserts rows itself:
 *   the caller queues them behind an "N new · Show" button (see `reduced`).
 */

/** The global timers, called unbound (a method call on another object throws "Illegal invocation"). */
const defaultTimers = {
	setTimeout: ( fn, ms ) => setTimeout( fn, ms ),
	clearTimeout: ( id ) => clearTimeout( id ),
};

export const POLL_INTERVAL = 15000;
export const POLL_JITTER = 0.2;
export const BACKOFF = [ 30000, 60000, 120000 ];
export const IDLE_LIMIT = 30 * 60 * 1000;

/**
 * How long after input woke an idle feed a press of the Live button still
 * counts as that wake (a Space press clicks on key up, a mouse on release).
 */
export const WAKE_GRACE = 1500;
export const ANNOUNCE_GAP = 60000;

/**
 * Milliseconds until the next poll.
 *
 * @param {number}                          failures Failed requests in a row.
 * @param {(...args: unknown[]) => unknown} random   Returns a number in [0, 1).
 * @return {number} Delay in milliseconds.
 */
export function nextDelay( failures, random = Math.random ) {
	if ( failures > 0 ) {
		return BACKOFF[ Math.min( failures, BACKOFF.length ) - 1 ];
	}

	const factor = 1 - POLL_JITTER + 2 * POLL_JITTER * random();

	return Math.round( POLL_INTERVAL * factor );
}

/**
 * Why the feed is not polling, or null when it should.
 *
 * @param {Object}  state            Conditions.
 * @param {boolean} state.userPaused The user pressed Pause.
 * @param {boolean} state.hidden     The tab is hidden.
 * @param {number}  state.idleFor    Milliseconds since the last input.
 * @return {null|'paused'|'hidden'|'idle'} Reason.
 */
export function pauseReason( { userPaused, hidden, idleFor } ) {
	if ( userPaused ) {
		return 'paused';
	}
	if ( hidden ) {
		return 'hidden';
	}
	if ( idleFor >= IDLE_LIMIT ) {
		return 'idle';
	}
	return null;
}

/**
 * The highest ID in a list of entries, or the current one.
 *
 * @param {Array<{id: number}>} items   Entries.
 * @param {number}              current Current highest ID.
 * @return {number} Highest ID.
 */
export function highestId( items, current = 0 ) {
	return items.reduce(
		( max, item ) => ( Number( item.id ) > max ? Number( item.id ) : max ),
		current
	);
}

/**
 * Creates the poller.
 *
 * @param {Object}                          options           Options.
 * @param {(...args: unknown[]) => unknown} options.fetch     ( sinceId ) => Promise<Array> of newer entries, newest first.
 * @param {(...args: unknown[]) => unknown} options.onItems   Receives each non-empty batch.
 * @param {(...args: unknown[]) => unknown} [options.onError] Receives each failure.
 * @param {number}                          [options.sinceId] Highest ID already shown.
 * @param {Object}                          [options.timers]  { setTimeout, clearTimeout }.
 * @param {(...args: unknown[]) => unknown} [options.random]  Random source for the jitter.
 * @return {Object} { start, stop, pause, resume, isRunning, failures, sinceId }.
 */
export function createPoller( {
	fetch,
	onItems,
	onError = () => {},
	sinceId = 0,
	timers = defaultTimers,
	random = Math.random,
} ) {
	let timer = null;
	let running = false;
	let inFlight = false;
	let failures = 0;
	let last = sinceId;

	const clear = () => {
		if ( timer !== null ) {
			timers.clearTimeout( timer );
			timer = null;
		}
	};

	const schedule = () => {
		clear();
		if ( running ) {
			timer = timers.setTimeout( tick, nextDelay( failures, random ) );
		}
	};

	function tick() {
		timer = null;
		if ( ! running || inFlight ) {
			return Promise.resolve();
		}
		inFlight = true;

		return Promise.resolve()
			.then( () => fetch( last ) )
			.then( ( result ) => {
				failures = 0;
				// An array of entries, or { items, total } when the server
				// says how many are new in all (a burst bigger than a page).
				const list = Array.isArray( result )
					? result
					: result?.items || [];
				const fresh = list.filter(
					( item ) => Number( item.id ) > last
				);
				if ( fresh.length ) {
					last = highestId( fresh, last );
					onItems(
						fresh,
						Math.max( fresh.length, Number( result?.total ) || 0 )
					);
				}
			} )
			.catch( ( error ) => {
				failures++;
				onError( error, failures );
			} )
			.finally( () => {
				inFlight = false;
				schedule();
			} );
	}

	return {
		/** Starts polling (first request after one interval). */
		start() {
			if ( running ) {
				return;
			}
			running = true;
			schedule();
		},
		/** Stops polling. */
		stop() {
			running = false;
			clear();
		},
		/** Same as stop(); kept for readability at call sites. */
		pause() {
			running = false;
			clear();
		},
		/**
		 * Resumes and fetches at once.
		 *
		 * @return {Promise} The request.
		 */
		resume() {
			running = true;
			clear();
			return tick();
		},
		isRunning: () => running,
		failures: () => failures,
		sinceId: () => last,
		/** @param {number} id Highest ID now shown. */
		setSinceId( id ) {
			last = Math.max( last, Number( id ) || 0 );
		},
	};
}

/**
 * Limits screen reader summaries to one per minute.
 *
 * @param {Object}                          options     Options.
 * @param {number}                          options.gap Milliseconds between two announcements.
 * @param {(...args: unknown[]) => unknown} options.now Clock.
 * @return {(...args: unknown[]) => unknown} Returns true when an announcement may be made now (and records it).
 */
export function createAnnounceGate( {
	gap = ANNOUNCE_GAP,
	now = Date.now,
} = {} ) {
	let lastAt = -Infinity;

	return () => {
		const t = now();
		if ( t - lastAt < gap ) {
			return false;
		}
		lastAt = t;
		return true;
	};
}

/**
 * What the Live/Pause button does in a state: "pause" saves live_paused,
 * "resume" clears it, and "wake" only restarts a feed the page paused (a
 * hidden tab, or 30 minutes without input) without writing the preference.
 *
 * @param {Object}      state            State.
 * @param {boolean}     state.userPaused The user's live_paused preference.
 * @param {string|null} state.reason     pauseReason() at the time of the press.
 * @param {boolean}     [state.justWoke] The press's own input just woke an idle feed.
 * @return {'pause'|'resume'|'wake'} Action.
 */
export function liveAction( { userPaused, reason, justWoke = false } ) {
	if ( userPaused ) {
		return 'resume';
	}
	return reason || justWoke ? 'wake' : 'pause';
}

/**
 * Tracks inactivity. Input clears the idle state on the next tick, not at
 * once, so a key press or click that lands on the Resume button still sees
 * the feed as paused (and only wakes it).
 *
 * @param {Object}                  options          Options.
 * @param {number}                  [options.limit]  Milliseconds without input.
 * @param {() => number}            [options.now]    Clock.
 * @param {Object}                  [options.timers] { setTimeout, clearTimeout }.
 * @param {(idle: boolean) => void} options.onChange Receives each change.
 * @return {{input: () => void, check: () => void, wake: () => void, isIdle: () => boolean, justWoke: () => boolean}} Tracker.
 */
export function createIdleTracker( {
	limit = IDLE_LIMIT,
	now = Date.now,
	timers = defaultTimers,
	onChange,
} ) {
	let last = now();
	let idle = false;
	let pending = null;
	let wokeAt = -Infinity;

	const set = ( value ) => {
		if ( value !== idle ) {
			idle = value;
			onChange( value );
		}
	};

	return {
		input() {
			last = now();
			if ( idle && null === pending ) {
				wokeAt = last;
				pending = timers.setTimeout( () => {
					pending = null;
					set( false );
				}, 0 );
			}
		},
		check() {
			if ( now() - last >= limit ) {
				set( true );
			}
		},
		wake() {
			last = now();
			set( false );
		},
		isIdle: () => idle,
		justWoke: () => now() - wokeAt < WAKE_GRACE,
	};
}

/**
 * Follows the tab's visibility.
 *
 * @param {EventTarget & {hidden: boolean}} doc      The document.
 * @param {(hidden: boolean) => void}       onChange Receives the hidden state.
 * @return {() => void} Stops listening.
 */
export function watchVisibility( doc, onChange ) {
	const handler = () => onChange( Boolean( doc.hidden ) );
	doc.addEventListener( 'visibilitychange', handler );
	return () => doc.removeEventListener( 'visibilitychange', handler );
}

/**
 * Leaves out log rows that are not detections (rescue links).
 *
 * @param {Array<{type: string}>} items Entries.
 * @param {string[]}              types Non-detection types.
 * @return {Array} Detections.
 */
export const detectionsOnly = ( items, types = [] ) =>
	( items || [] ).filter( ( item ) => ! types.includes( item.type ) );

/**
 * One polite screen-reader summary of new detections, at most once a minute.
 *
 * @param {Object}                                  options          Options.
 * @param {() => boolean}                           options.gate     createAnnounceGate().
 * @param {(message: string) => void}               options.announce Speaks a message.
 * @param {(items: Array, total: number) => string} options.message  Builds the message.
 * @return {(items: Array, total: number) => boolean} Announces when allowed; true when it did.
 */
export function createNewDetectionsAnnouncer( { gate, announce, message } ) {
	return ( items, total ) => {
		if ( ! items.length || ! gate() ) {
			return false;
		}
		announce( message( items, total ) );
		return true;
	};
}
