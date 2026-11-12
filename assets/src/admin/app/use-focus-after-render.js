/**
 * Moves focus after the state change that makes the target usable has been
 * rendered.
 *
 * Focusing in a requestAnimationFrame after a state update races React: an
 * update made outside an event handler (in a promise callback, an effect)
 * may be committed after the frame, so focus() hits a control that is still
 * disabled, or on a tab that is not shown yet, and focus falls to the body.
 * A request made here is kept until the first commit that includes the
 * state changes made with it (not an earlier commit whose effects had not
 * run yet), then `run` is called from an effect; when it returns false (the
 * target is missing, disabled or locked), `fallback` is called instead. A
 * later request replaces one still waiting. The changes must be made in the
 * same update as the request (not in `startTransition`), or the request can
 * run before them.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * @return {(run: () => boolean, fallback?: () => void) => void} Requests focus after the render that carries the caller's change.
 */
export function useFocusAfterRender() {
	const pending = useRef( null );
	const requests = useRef( 0 );
	const [ tick, setTick ] = useState( 0 );

	// After every commit: a request made with a state change runs once that change is on screen.
	useEffect( () => {
		const request = pending.current;
		// A request made after this commit (before its effects ran) waits for
		// the commit that carries its own change.
		if ( ! request || request.tick > tick ) {
			return;
		}
		pending.current = null;
		if ( ! request.run() && request.fallback ) {
			request.fallback();
		}
	} );

	return useCallback( ( run, fallback ) => {
		requests.current += 1;
		pending.current = { run, fallback, tick: requests.current };
		setTick( requests.current );
	}, [] );
}

/**
 * Focuses an element if it can take focus now (present, not disabled).
 *
 * @param {Element|null|undefined} element Element.
 * @return {boolean} Whether it has focus.
 */
export function focusIfEnabled( element ) {
	if ( ! element || element.disabled ) {
		return false;
	}
	element.focus( { preventScroll: false } );
	return element.ownerDocument.activeElement === element;
}
