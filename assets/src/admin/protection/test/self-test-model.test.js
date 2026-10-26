/**
 * "Test my protection": pending → running → pass / warn / fail, the result
 * line, the rate limit and failures.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	STEPS,
	counts,
	heading,
	initialTest,
	isBusy,
	networkLatency,
	stepTime,
	summary,
	testReducer,
} from '../self-test-model';

const answer = ( statuses = {} ) => ( {
	status: 'pass',
	ran_at: '2026-10-25T10:00:00+00:00',
	steps: [
		// Out of order on purpose: the panel follows the route's order.
		{
			id: 'lists',
			status: statuses.lists || 'pass',
			message: 'Loaded.',
			ms: 30,
		},
		{
			id: 'honeypot',
			status: statuses.honeypot || 'pass',
			message: 'Stopped.',
			ms: 200,
		},
		{ id: 'browser_check', status: 'pass', message: 'Refused.', ms: 120 },
		{
			id: 'reputation',
			status: 'pass',
			message: 'Answered.',
			ms: 400,
			services: [
				{ id: 'stop_forum_spam', ok: true, code: 200, ms: 150 },
				{ id: 'zero_spam', ok: true, code: 200, ms: 38 },
			],
		},
		{ id: 'ip_source', status: 'pass', message: 'Direct.', ms: 50 },
	],
} );

const run = ( reducerAnswer, reveal = 'all' ) => {
	let state = testReducer( initialTest, { type: 'start' } );
	state = testReducer( state, { type: 'answer', data: reducerAnswer } );
	if ( 'all' === reveal ) {
		return testReducer( state, { type: 'reveal', all: true } );
	}
	for ( let i = 0; i < reveal; i++ ) {
		state = testReducer( state, { type: 'reveal' } );
	}
	return state;
};

describe( 'testReducer', () => {
	it( 'starts with every check pending and the first one running', () => {
		const state = testReducer( initialTest, { type: 'start' } );
		expect( state.phase ).toBe( 'running' );
		expect( state.steps.map( ( s ) => s.id ) ).toEqual( STEPS );
		expect( state.steps.map( ( s ) => s.status ) ).toEqual( [
			'running',
			'pending',
			'pending',
			'pending',
			'pending',
		] );
		expect( isBusy( state ) ).toBe( true );
		expect( summary( state ).text ).toBe( 'Running 5 checks…' );
		expect( heading( state ) ).toBe( 'Testing your protection…' );
	} );

	it( 'lays the answers down one at a time, in the route order', () => {
		const state = run( answer(), 2 );
		expect( state.phase ).toBe( 'revealing' );
		expect( state.steps.map( ( s ) => s.id ) ).toEqual( STEPS );
		expect( state.steps.map( ( s ) => s.status ) ).toEqual( [
			'pass',
			'pass',
			'running',
			'pending',
			'pending',
		] );
		expect( state.steps[ 0 ].message ).toBe( 'Stopped.' );
		expect( summary( state ).text ).toBe( 'Checking 3 of 5…' );
	} );

	it( 'is done when every answer is down', () => {
		const state = run( answer(), 5 );
		expect( state.phase ).toBe( 'done' );
		expect( isBusy( state ) ).toBe( false );
		expect( counts( state ) ).toMatchObject( {
			pass: 5,
			warn: 0,
			fail: 0,
			total: 5,
			ms: 800,
		} );
		expect( summary( state ) ).toEqual( {
			tone: 'ok',
			text: 'All 5 checks passed in 0.8 s. Your site is protected.',
		} );
		expect( heading( state ) ).toBe( 'All checks passed' );
		// Further reveals change nothing.
		expect( testReducer( state, { type: 'reveal' } ) ).toBe( state );
	} );

	it( 'reports checks that need a look, and failures first', () => {
		const warn = run( answer( { lists: 'warn' } ) );
		expect( summary( warn ) ).toEqual( {
			tone: 'warn',
			text: '4 of 5 checks passed; 1 needs a look.',
		} );
		expect( heading( warn ) ).toBe( 'Some checks need a look' );

		const fail = run( answer( { lists: 'warn', honeypot: 'fail' } ) );
		expect( summary( fail ).tone ).toBe( 'fail' );
		expect( summary( fail ).text ).toBe(
			'1 of 5 checks failed. The details are below.'
		);
		expect( heading( fail ) ).toBe( 'A check failed' );
	} );

	it( 'treats an unknown status as needing a look', () => {
		const state = run( answer( { lists: 'mystery' } ) );
		expect( state.steps.find( ( s ) => 'lists' === s.id ).status ).toBe(
			'warn'
		);
	} );

	it( 'keeps the last result when the route rate-limits a second run', () => {
		const done = run( answer() );
		const again = testReducer( testReducer( done, { type: 'start' } ), {
			type: 'fail',
			error: {
				code: 'zerospam_self_test_rate_limited',
				message: 'The self-test just ran. Try again in 42 seconds.',
				data: { status: 429, retry_after: 42 },
			},
		} );
		expect( again.phase ).toBe( 'limited' );
		expect( again.retryAfter ).toBe( 42 );
		expect( summary( again ) ).toEqual( {
			tone: 'warn',
			text: 'The self-test just ran. Try again in 42 seconds.',
		} );
	} );

	it( 'shows a failure to run', () => {
		const state = testReducer(
			testReducer( initialTest, { type: 'start' } ),
			{
				type: 'fail',
				error: { code: 'rest_forbidden', message: 'Sorry.' },
			}
		);
		expect( state.phase ).toBe( 'error' );
		expect( state.steps ).toEqual( [] );
		expect( summary( state ) ).toEqual( { tone: 'fail', text: 'Sorry.' } );
		expect( heading( state ) ).toBe( 'The self-test could not run' );
	} );
} );

describe( 'details', () => {
	it( 'labels each step by its state', () => {
		expect( stepTime( { status: 'pending' } ) ).toBe( 'Waiting' );
		expect( stepTime( { status: 'running' } ) ).toBe( 'Checking…' );
		expect( stepTime( { status: 'pass', ms: 420 } ) ).toBe(
			'Passed · 0.4 s'
		);
		expect( stepTime( { status: 'warn', ms: 1500 } ) ).toBe(
			'Needs a look · 1.5 s'
		);
		expect( stepTime( { status: 'fail', ms: 0 } ) ).toBe(
			'Failed · 0.0 s'
		);
	} );

	it( "gives the Zero Spam network's latency for the license panel", () => {
		expect( networkLatency( run( answer() ) ) ).toBe( 38 );
		expect( networkLatency( initialTest ) ).toBeNull();
	} );
} );
