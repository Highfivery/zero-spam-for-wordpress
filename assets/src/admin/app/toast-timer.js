/**
 * The timer behind a toast (pure; unit tested).
 *
 * A toast closes on its own after a while, but the timer pauses while the
 * pointer is over it or focus is inside it, so its Undo button can always
 * be reached (WCAG 2.2.1 Timing Adjustable).
 */

/** The global timers, called unbound (a method call on another object throws "Illegal invocation"). */
const defaultTimers = {
	setTimeout: ( fn, ms ) => setTimeout( fn, ms ),
	clearTimeout: ( id ) => clearTimeout( id ),
};

export const TOAST_DURATION = 6000;
export const UNDO_DURATION = 10000;

/**
 * Creates a pausable countdown.
 *
 * @param {Object}   options          Options.
 * @param {number}   options.duration Milliseconds before it expires.
 * @param {Function} options.onExpire Called once when it expires.
 * @param {Function} [options.now]    Clock.
 * @param {Object}   [options.timers] { setTimeout, clearTimeout }.
 * @return {Object} { start, pause, resume, cancel, remaining, isPaused }.
 */
export function createToastTimer( {
	duration,
	onExpire,
	now = Date.now,
	timers = defaultTimers,
} ) {
	let remaining = duration;
	let startedAt = null;
	let timer = null;
	let done = false;
	let holds = 0;

	const fire = () => {
		timer = null;
		startedAt = null;
		remaining = 0;
		if ( ! done ) {
			done = true;
			onExpire();
		}
	};

	const run = () => {
		if ( done || timer !== null ) {
			return;
		}
		startedAt = now();
		timer = timers.setTimeout( fire, remaining );
	};

	const halt = () => {
		if ( timer === null ) {
			return;
		}
		timers.clearTimeout( timer );
		timer = null;
		remaining = Math.max( 0, remaining - ( now() - startedAt ) );
		startedAt = null;
	};

	return {
		start: run,
		/**
		 * Pauses the countdown. Pauses nest: hover and focus each hold it,
		 * and it runs again only when both are released.
		 */
		pause() {
			holds++;
			halt();
		},
		/** Releases one pause. */
		resume() {
			holds = Math.max( 0, holds - 1 );
			if ( 0 === holds ) {
				run();
			}
		},
		/** Stops it for good without calling onExpire. */
		cancel() {
			done = true;
			halt();
		},
		remaining() {
			if ( timer !== null ) {
				return Math.max( 0, remaining - ( now() - startedAt ) );
			}
			return remaining;
		},
		isPaused: () => holds > 0,
	};
}
