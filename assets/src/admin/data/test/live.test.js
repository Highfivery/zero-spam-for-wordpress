/**
 * The Live/Pause button and the feed's page-level pauses: a press that only
 * wakes a feed the page paused never writes live_paused, the visibility
 * listener is wired, rescue rows are left out, and new detections are
 * announced at most once a minute.
 */

/**
 * External dependencies
 */
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';

/**
 * Internal dependencies
 */
import {
	ANNOUNCE_GAP,
	IDLE_LIMIT,
	WAKE_GRACE,
	createAnnounceGate,
	createIdleTracker,
	createNewDetectionsAnnouncer,
	detectionsOnly,
	liveAction,
	pauseReason,
	watchVisibility,
} from '../poll';

describe( 'liveAction', () => {
	it( 'resumes a feed the user paused', () => {
		expect( liveAction( { userPaused: true, reason: 'paused' } ) ).toBe(
			'resume'
		);
	} );
	it( 'only wakes a feed the page paused', () => {
		expect( liveAction( { userPaused: false, reason: 'idle' } ) ).toBe(
			'wake'
		);
		expect( liveAction( { userPaused: false, reason: 'hidden' } ) ).toBe(
			'wake'
		);
		expect(
			liveAction( { userPaused: false, reason: null, justWoke: true } )
		).toBe( 'wake' );
	} );
	it( 'pauses a running feed', () => {
		expect( liveAction( { userPaused: false, reason: null } ) ).toBe(
			'pause'
		);
	} );
} );

/*
 * The reviewer's path: the feed went idle, focus is on the Live button, the
 * user presses Enter or Space. The key press is input, which wakes the feed;
 * the click it causes must still be read as that wake, not as "pause".
 */
describe( 'Live button after inactivity (fake clock)', () => {
	beforeEach( () => {
		vi.useFakeTimers();
	} );
	afterEach( () => {
		vi.useRealTimers();
	} );

	const setup = () => {
		const states = [];
		const tracker = createIdleTracker( {
			now: () => Date.now(),
			timers: {
				setTimeout: ( fn, ms ) => setTimeout( fn, ms ),
				clearTimeout: ( id ) => clearTimeout( id ),
			},
			onChange: ( idle ) => states.push( idle ),
		} );
		const savePrefs = vi.fn();
		const wake = vi.fn( () => tracker.wake() );
		// What overview.js does on click, reading the state of that moment.
		const press = () => {
			const action = liveAction( {
				userPaused: false,
				reason: pauseReason( {
					userPaused: false,
					hidden: false,
					idleFor: tracker.isIdle() ? IDLE_LIMIT : 0,
				} ),
				justWoke: tracker.justWoke(),
			} );
			if ( 'wake' === action ) {
				wake();
			} else {
				savePrefs( { live_paused: 'pause' === action } );
			}
			return action;
		};
		vi.advanceTimersByTime( IDLE_LIMIT );
		tracker.check();
		expect( tracker.isIdle() ).toBe( true );
		return { tracker, press, savePrefs, wake, states };
	};

	it( 'Enter: keydown and click in the same task only wake', () => {
		const { tracker, press, savePrefs, wake } = setup();
		tracker.input(); // keydown
		expect( press() ).toBe( 'wake' ); // click, same task
		expect( savePrefs ).not.toHaveBeenCalled();
		expect( wake ).toHaveBeenCalledTimes( 1 );
		vi.runOnlyPendingTimers();
		expect( tracker.isIdle() ).toBe( false );
	} );

	it( 'Space: keydown, then the click on key up, only wakes', () => {
		const { tracker, press, savePrefs } = setup();
		tracker.input(); // keydown
		vi.advanceTimersByTime( 0 ); // the idle state clears, React renders
		expect( tracker.isIdle() ).toBe( false );
		vi.advanceTimersByTime( 120 ); // key up
		expect( press() ).toBe( 'wake' );
		expect( savePrefs ).not.toHaveBeenCalled();
	} );

	it( 'a later press pauses and saves the preference', () => {
		const { tracker, press, savePrefs } = setup();
		tracker.input();
		vi.advanceTimersByTime( WAKE_GRACE + 1 );
		expect( press() ).toBe( 'pause' );
		expect( savePrefs ).toHaveBeenCalledWith( { live_paused: true } );
	} );

	it( 'input while active never counts as a wake', () => {
		const { tracker, press, savePrefs } = setup();
		tracker.wake();
		vi.advanceTimersByTime( WAKE_GRACE + 1 );
		tracker.input();
		expect( tracker.justWoke() ).toBe( false );
		expect( press() ).toBe( 'pause' );
		expect( savePrefs ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'watchVisibility', () => {
	it( 'reports the hidden state on visibilitychange and unbinds', () => {
		const doc = new EventTarget();
		doc.hidden = false;
		const onChange = vi.fn();
		const stop = watchVisibility( doc, onChange );

		doc.hidden = true;
		doc.dispatchEvent( new Event( 'visibilitychange' ) );
		doc.hidden = false;
		doc.dispatchEvent( new Event( 'visibilitychange' ) );
		expect( onChange.mock.calls ).toEqual( [ [ true ], [ false ] ] );

		stop();
		doc.hidden = true;
		doc.dispatchEvent( new Event( 'visibilitychange' ) );
		expect( onChange ).toHaveBeenCalledTimes( 2 );
	} );
} );

describe( 'detectionsOnly', () => {
	it( 'leaves rescue rows out of the feed', () => {
		const items = [
			{ id: 3, type: 'comment' },
			{ id: 2, type: 'rescue' },
			{ id: 1, type: 'login' },
		];
		expect(
			detectionsOnly( items, [ 'rescue' ] ).map( ( i ) => i.id )
		).toEqual( [ 3, 1 ] );
		expect( detectionsOnly( undefined, [] ) ).toEqual( [] );
	} );
} );

describe( 'createNewDetectionsAnnouncer', () => {
	it( 'speaks at most once a minute, with the burst total', () => {
		let clock = 0;
		const announce = vi.fn();
		const say = createNewDetectionsAnnouncer( {
			gate: createAnnounceGate( { now: () => clock } ),
			announce,
			message: ( items, total ) => `${ total } new`,
		} );

		expect( say( [ { id: 1 } ], 40 ) ).toBe( true );
		clock += 15000;
		expect( say( [ { id: 2 } ], 1 ) ).toBe( false );
		clock += ANNOUNCE_GAP;
		expect( say( [], 0 ) ).toBe( false );
		expect( say( [ { id: 3 } ], 2 ) ).toBe( true );
		expect( announce.mock.calls ).toEqual( [ [ '40 new' ], [ '2 new' ] ] );
	} );
} );
