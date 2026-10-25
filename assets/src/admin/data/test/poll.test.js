/**
 * The live feed's scheduler: 15 s ±20 % jitter, back-off after failures,
 * pause and resume, and the one-announcement-a-minute gate.
 */

/**
 * External dependencies
 */
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';

/**
 * Internal dependencies
 */
import {
	BACKOFF,
	IDLE_LIMIT,
	POLL_INTERVAL,
	createAnnounceGate,
	createPoller,
	highestId,
	nextDelay,
	pauseReason,
} from '../poll';

describe( 'nextDelay', () => {
	it( 'is 15 s with ±20 % jitter while requests succeed', () => {
		expect( nextDelay( 0, () => 0 ) ).toBe( POLL_INTERVAL * 0.8 );
		expect( nextDelay( 0, () => 0.5 ) ).toBe( POLL_INTERVAL );
		expect( nextDelay( 0, () => 0.999999 ) ).toBeLessThanOrEqual(
			POLL_INTERVAL * 1.2
		);
		for ( let i = 0; i < 200; i++ ) {
			const d = nextDelay( 0 );
			expect( d ).toBeGreaterThanOrEqual( 12000 );
			expect( d ).toBeLessThanOrEqual( 18000 );
		}
	} );

	it( 'backs off 30, 60, then 120 s and stays at 120 s', () => {
		expect( BACKOFF ).toEqual( [ 30000, 60000, 120000 ] );
		expect( nextDelay( 1 ) ).toBe( 30000 );
		expect( nextDelay( 2 ) ).toBe( 60000 );
		expect( nextDelay( 3 ) ).toBe( 120000 );
		expect( nextDelay( 9 ) ).toBe( 120000 );
	} );
} );

describe( 'pauseReason', () => {
	it( 'names why the feed is paused, the user first', () => {
		expect(
			pauseReason( { userPaused: false, hidden: false, idleFor: 0 } )
		).toBeNull();
		expect(
			pauseReason( {
				userPaused: true,
				hidden: true,
				idleFor: IDLE_LIMIT,
			} )
		).toBe( 'paused' );
		expect(
			pauseReason( { userPaused: false, hidden: true, idleFor: 0 } )
		).toBe( 'hidden' );
		expect(
			pauseReason( {
				userPaused: false,
				hidden: false,
				idleFor: IDLE_LIMIT - 1,
			} )
		).toBeNull();
		expect(
			pauseReason( {
				userPaused: false,
				hidden: false,
				idleFor: IDLE_LIMIT,
			} )
		).toBe( 'idle' );
		expect( IDLE_LIMIT ).toBe( 30 * 60 * 1000 );
	} );
} );

describe( 'highestId', () => {
	it( 'returns the highest entry ID, or the current one', () => {
		expect( highestId( [ { id: 3 }, { id: 9 }, { id: '12' } ], 5 ) ).toBe(
			12
		);
		expect( highestId( [], 7 ) ).toBe( 7 );
		expect( highestId( [ { id: 2 } ], 7 ) ).toBe( 7 );
	} );
} );

describe( 'createPoller', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );
	afterEach( () => {
		vi.useRealTimers();
	} );

	const flush = async () => {
		// Let the fetch promise chain settle.
		for ( let i = 0; i < 5; i++ ) {
			await Promise.resolve();
		}
	};

	it( 'waits one interval, asks for entries newer than the last ID and passes new ones on', async () => {
		const fetch = vi
			.fn()
			.mockResolvedValueOnce( [ { id: 11 }, { id: 10 } ] )
			.mockResolvedValue( [] );
		const onItems = vi.fn();
		const poller = createPoller( {
			fetch,
			onItems,
			sinceId: 9,
			random: () => 0.5,
		} );

		poller.start();
		expect( fetch ).not.toHaveBeenCalled();
		await vi.advanceTimersByTimeAsync( POLL_INTERVAL - 1 );
		expect( fetch ).not.toHaveBeenCalled();
		await vi.advanceTimersByTimeAsync( 1 );
		await flush();
		expect( fetch ).toHaveBeenCalledWith( 9 );
		expect( onItems ).toHaveBeenCalledWith( [ { id: 11 }, { id: 10 } ] );
		expect( poller.sinceId() ).toBe( 11 );

		await vi.advanceTimersByTimeAsync( POLL_INTERVAL );
		await flush();
		expect( fetch ).toHaveBeenLastCalledWith( 11 );
		expect( onItems ).toHaveBeenCalledTimes( 1 );
		poller.stop();
	} );

	it( 'drops entries it has already seen', async () => {
		const fetch = vi.fn().mockResolvedValue( [ { id: 5 }, { id: 4 } ] );
		const onItems = vi.fn();
		const poller = createPoller( {
			fetch,
			onItems,
			sinceId: 5,
			random: () => 0.5,
		} );
		poller.start();
		await vi.advanceTimersByTimeAsync( POLL_INTERVAL );
		await flush();
		expect( onItems ).not.toHaveBeenCalled();
		poller.stop();
	} );

	it( 'backs off after failures and returns to the normal interval after a success', async () => {
		const fetch = vi
			.fn()
			.mockRejectedValueOnce( new Error( 'offline' ) )
			.mockRejectedValueOnce( new Error( 'offline' ) )
			.mockResolvedValue( [] );
		const onError = vi.fn();
		const poller = createPoller( {
			fetch,
			onItems: vi.fn(),
			onError,
			random: () => 0.5,
		} );

		poller.start();
		await vi.advanceTimersByTimeAsync( POLL_INTERVAL );
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( poller.failures() ).toBe( 1 );

		await vi.advanceTimersByTimeAsync( 29999 );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		await vi.advanceTimersByTimeAsync( 1 );
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( poller.failures() ).toBe( 2 );

		await vi.advanceTimersByTimeAsync( 59999 );
		expect( fetch ).toHaveBeenCalledTimes( 2 );
		await vi.advanceTimersByTimeAsync( 1 );
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 3 );
		expect( poller.failures() ).toBe( 0 );
		expect( onError ).toHaveBeenCalledTimes( 2 );

		await vi.advanceTimersByTimeAsync( POLL_INTERVAL );
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 4 );
		poller.stop();
	} );

	it( 'does nothing while paused and fetches at once on resume', async () => {
		const fetch = vi.fn().mockResolvedValue( [] );
		const poller = createPoller( {
			fetch,
			onItems: vi.fn(),
			random: () => 0.5,
		} );

		poller.start();
		poller.pause();
		expect( poller.isRunning() ).toBe( false );
		await vi.advanceTimersByTimeAsync( POLL_INTERVAL * 10 );
		expect( fetch ).not.toHaveBeenCalled();

		poller.resume();
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( poller.isRunning() ).toBe( true );

		await vi.advanceTimersByTimeAsync( POLL_INTERVAL );
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 2 );
		poller.stop();
		await vi.advanceTimersByTimeAsync( POLL_INTERVAL * 3 );
		expect( fetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'never runs two requests at once', async () => {
		let release;
		const fetch = vi.fn( () => new Promise( ( r ) => ( release = r ) ) );
		const poller = createPoller( {
			fetch,
			onItems: vi.fn(),
			random: () => 0.5,
		} );
		poller.resume();
		poller.resume();
		await flush();
		poller.resume();
		await flush();
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		release( [] );
		await flush();
		poller.stop();
	} );
} );

describe( 'createAnnounceGate', () => {
	it( 'allows one screen reader summary per minute', () => {
		let t = 0;
		const gate = createAnnounceGate( { now: () => t } );
		expect( gate() ).toBe( true );
		t = 59999;
		expect( gate() ).toBe( false );
		t = 60000;
		expect( gate() ).toBe( true );
		t = 61000;
		expect( gate() ).toBe( false );
	} );
} );
