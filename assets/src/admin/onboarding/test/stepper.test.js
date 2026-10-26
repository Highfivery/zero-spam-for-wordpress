/**
 * First-run setup as a state machine: steps, validation, what each step
 * writes and how far the mark is built.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	DONE,
	STEPS,
	changesFor,
	clampStep,
	initialState,
	progress,
	protectItems,
	reducer,
	rowLaid,
	stepKeys,
	validateStep,
	writable,
} from '../stepper';

const run = ( state, ...actions ) => actions.reduce( reducer, state );

const MODULES = {
	modules: [
		{
			key: 'comments',
			title: 'Comments',
			group: 'detection',
			switches: [ 'verify_comments' ],
			locked_keys: [],
			enabled: false,
		},
		{
			key: 'registration',
			title: 'Registration',
			group: 'detection',
			switches: [ 'verify_registrations' ],
			locked_keys: [ 'verify_registrations' ],
			enabled: true,
		},
		{
			key: 'login',
			title: 'Login',
			group: 'detection',
			switches: [ 'verify_login' ],
			locked_keys: [],
		},
		{
			key: 'davidwalsh',
			title: 'David Walsh',
			group: 'detection',
			switches: [ 'davidwalsh' ],
			locked_keys: [],
		},
		{
			key: 'security',
			title: 'Security',
			group: 'detection',
			switches: [ 'disable_emojis' ],
			locked_keys: [],
		},
		{
			key: 'woocommerce',
			title: 'WooCommerce',
			group: 'integrations',
			available: true,
			plugin: { active: true },
			switches: [
				'verify_woocommerce_registrations',
				'verify_woocommerce_checkout',
			],
			locked_keys: [],
		},
		{
			key: 'wpforms',
			title: 'WPForms',
			group: 'integrations',
			available: false,
			plugin: { active: false },
			switches: [],
			locked_keys: [],
		},
	],
};

describe( 'the steps', () => {
	it( 'start at step 1, or where a saved setup stopped', () => {
		expect( initialState().step ).toBe( 1 );
		expect( initialState( { step: 2 } ).step ).toBe( 2 );
		expect( initialState( { step: 99 } ).step ).toBe( DONE );
		expect( clampStep( 0 ) ).toBe( 1 );
		expect( clampStep( 'x' ) ).toBe( 1 );
	} );

	it( 'move forward and back, never past the ends', () => {
		let s = initialState();
		s = run( s, { type: 'back' } );
		expect( s.step ).toBe( 1 );
		s = run( s, { type: 'next' }, { type: 'next' } );
		expect( s.step ).toBe( 3 );
		s = run( s, { type: 'next' }, { type: 'next' } );
		expect( s.step ).toBe( DONE );
		s = run( s, { type: 'restart' } );
		expect( s.step ).toBe( 1 );
	} );

	it( 'clear errors and the busy flag when the step changes', () => {
		const s = run(
			initialState(),
			{ type: 'busy', busy: true },
			{ type: 'error', errors: { key: 'x' } },
			{ type: 'busy', busy: true },
			{ type: 'next' }
		);
		expect( s.errors ).toEqual( {} );
		expect( s.busy ).toBe( false );
	} );

	it( 'mark exactly one step as current, the earlier ones done', () => {
		expect( progress( 2 ) ).toEqual( [
			{ n: 1, status: 'done' },
			{ n: 2, status: 'current' },
			{ n: 3, status: 'todo' },
		] );
		expect( progress( DONE ).every( ( p ) => 'done' === p.status ) ).toBe(
			true
		);
		expect( progress( 1 ) ).toHaveLength( STEPS );
	} );
} );

describe( 'step 1, the protection level', () => {
	it( 'needs a key for Enhanced only', () => {
		const basic = initialState();
		expect( validateStep( basic ) ).toEqual( {} );
		const enhanced = run( basic, { type: 'level', level: 'enhanced' } );
		expect( validateStep( enhanced ).key ).toBeTruthy();
		const keyed = run( enhanced, { type: 'key', key: '  abc  ' } );
		expect( validateStep( keyed ) ).toEqual( {} );
	} );

	it( 'saves the key and switches Enhanced on, or switches it off', () => {
		const enhanced = run(
			initialState(),
			{ type: 'level', level: 'enhanced' },
			{ type: 'key', key: ' key-1 ' }
		);
		expect( changesFor( enhanced ) ).toEqual( {
			license: 'key-1',
			settings: { zerospam: 'enabled' },
		} );
		expect( changesFor( initialState() ) ).toEqual( {
			license: null,
			settings: { zerospam: false },
		} );
	} );

	it( 'treats an unknown level as Basic', () => {
		expect(
			run( initialState(), { type: 'level', level: 'gold' } ).level
		).toBe( 'basic' );
	} );
} );

describe( 'step 2, what to protect', () => {
	const items = protectItems( MODULES );

	it( 'offers the core entry points, the browser check and active form plugins', () => {
		expect( items.map( ( i ) => [ i.key, i.group ] ) ).toEqual( [
			[ 'comments', 'core' ],
			[ 'registration', 'core' ],
			[ 'login', 'core' ],
			[ 'davidwalsh', 'core' ],
			[ 'woocommerce', 'forms' ],
		] );
		expect( items[ 1 ].locked ).toBe( true );
		expect( protectItems( undefined ) ).toEqual( [] );
	} );

	it( 'writes every switch of each item, ticked by default, never a locked one', () => {
		const s = run( initialState( { step: 2 } ), {
			type: 'protect',
			key: 'login',
			on: false,
		} );
		expect( changesFor( s, items ).settings ).toEqual( {
			verify_comments: true,
			verify_login: false,
			davidwalsh: true,
			verify_woocommerce_registrations: true,
			verify_woocommerce_checkout: true,
		} );
		expect( stepKeys( 2, items ) ).toContain( 'verify_registrations' );
	} );
} );

describe( 'step 3, how to block', () => {
	it( 'writes the method, sharing and logging', () => {
		const s = run(
			initialState( { step: 3 } ),
			{
				type: 'blocking',
				name: 'block_method',
				value: 'htaccess_modern',
			},
			{ type: 'blocking', name: 'share_data', value: false }
		);
		expect( changesFor( s ).settings ).toEqual( {
			block_method: 'htaccess_modern',
			share_data: false,
			log_blocked_ips: true,
		} );
		expect( stepKeys( 3 ) ).toEqual( [
			'block_method',
			'share_data',
			'log_blocked_ips',
		] );
	} );
} );

describe( 'writable()', () => {
	it( 'drops locked settings and settings the site does not have', () => {
		const schema = [
			{ key: 'verify_comments', locked: false },
			{ key: 'verify_login', locked: true },
		];
		expect(
			writable(
				{ verify_comments: true, verify_login: true, nope: true },
				schema
			)
		).toEqual( { verify_comments: true } );
		expect( writable( { a: 1 }, undefined ) ).toEqual( {} );
	} );
} );

describe( 'the mark', () => {
	it( 'lays the bottom row at step 1 and the top row when done', () => {
		const rows = ( step ) =>
			[ 0, 1, 2, 3 ].filter( ( row ) => rowLaid( row, step ) );
		expect( rows( 1 ) ).toEqual( [ 3 ] );
		expect( rows( 2 ) ).toEqual( [ 2, 3 ] );
		expect( rows( 3 ) ).toEqual( [ 1, 2, 3 ] );
		expect( rows( DONE ) ).toEqual( [ 0, 1, 2, 3 ] );
	} );
} );
