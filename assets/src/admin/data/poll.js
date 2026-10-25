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
			.then( ( items ) => {
				failures = 0;
				const list = Array.isArray( items ) ? items : [];
				const fresh = list.filter(
					( item ) => Number( item.id ) > last
				);
				if ( fresh.length ) {
					last = highestId( fresh, last );
					onItems( fresh );
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
