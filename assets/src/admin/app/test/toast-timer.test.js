/**
 * The toast timer: it expires once, and hover and focus pause it (nested),
 * so an Undo button can always be reached (WCAG 2.2.1).
 */

/**
 * External dependencies
 */
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';

/**
 * Internal dependencies
 */
import {
	TOAST_DURATION,
	UNDO_DURATION,
	bindToastHold,
	createToastTimer,
} from '../toast-timer';

describe( 'createToastTimer', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );
	afterEach( () => {
		vi.useRealTimers();
	} );

	const make = ( duration = 1000 ) => {
		const onExpire = vi.fn();
		const timer = createToastTimer( {
			duration,
			onExpire,
			now: () => Date.now(),
		} );
		return { timer, onExpire };
	};

	it( 'gives toasts with Undo longer', () => {
		expect( UNDO_DURATION ).toBeGreaterThan( TOAST_DURATION );
	} );

	it( 'expires after its duration, once', () => {
		const { timer, onExpire } = make();
		timer.start();
		vi.advanceTimersByTime( 999 );
		expect( onExpire ).not.toHaveBeenCalled();
		vi.advanceTimersByTime( 1 );
		expect( onExpire ).toHaveBeenCalledTimes( 1 );
		timer.start();
		vi.advanceTimersByTime( 5000 );
		expect( onExpire ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'pauses on hover and keeps the time that was left', () => {
		const { timer, onExpire } = make();
		timer.start();
		vi.advanceTimersByTime( 400 );
		timer.pause();
		expect( timer.isPaused() ).toBe( true );
		expect( timer.remaining() ).toBe( 600 );
		vi.advanceTimersByTime( 60000 );
		expect( onExpire ).not.toHaveBeenCalled();
		timer.resume();
		vi.advanceTimersByTime( 599 );
		expect( onExpire ).not.toHaveBeenCalled();
		vi.advanceTimersByTime( 1 );
		expect( onExpire ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'stays paused until both hover and focus are released', () => {
		const { timer, onExpire } = make();
		timer.start();
		timer.pause(); // Pointer over the toast.
		timer.pause(); // Focus inside it.
		timer.resume(); // Pointer leaves.
		vi.advanceTimersByTime( 5000 );
		expect( onExpire ).not.toHaveBeenCalled();
		timer.resume(); // Focus leaves.
		vi.advanceTimersByTime( 1000 );
		expect( onExpire ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'never expires once cancelled (Undo pressed, toast closed)', () => {
		const { timer, onExpire } = make();
		timer.start();
		timer.cancel();
		timer.resume();
		vi.advanceTimersByTime( 5000 );
		expect( onExpire ).not.toHaveBeenCalled();
	} );
} );

describe( 'bindToastHold', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );
	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'pauses on hover and focus, resumes on leave and blur, and unbinds', () => {
		const onExpire = vi.fn();
		const timer = createToastTimer( {
			duration: 1000,
			onExpire,
			now: () => Date.now(),
		} );
		const box = new EventTarget();
		const unbind = bindToastHold( box, timer );
		timer.start();

		vi.advanceTimersByTime( 500 );
		box.dispatchEvent( new Event( 'mouseenter' ) );
		box.dispatchEvent( new Event( 'focusin' ) );
		vi.advanceTimersByTime( 5000 );
		expect( onExpire ).not.toHaveBeenCalled();

		box.dispatchEvent( new Event( 'mouseleave' ) );
		vi.advanceTimersByTime( 5000 );
		expect( onExpire ).not.toHaveBeenCalled(); // still focused

		box.dispatchEvent( new Event( 'focusout' ) );
		vi.advanceTimersByTime( 499 );
		expect( onExpire ).not.toHaveBeenCalled();
		vi.advanceTimersByTime( 1 );
		expect( onExpire ).toHaveBeenCalledTimes( 1 );

		unbind();
		const spy = vi.spyOn( timer, 'pause' );
		box.dispatchEvent( new Event( 'mouseenter' ) );
		box.dispatchEvent( new Event( 'focusin' ) );
		expect( spy ).not.toHaveBeenCalled();
	} );
} );
