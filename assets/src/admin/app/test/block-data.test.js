/**
 * The block form's values: site-time inputs and the request body.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	blockAll,
	blockBody,
	bulkBlockPlan,
	editBaseline,
	initialLocationValues,
	initialValues,
	toSiteInput,
} from '../block-data';

const UTC = { name: 'UTC', offset: 0 };
const NEW_YORK = { name: 'America/New_York', offset: -18000 };
const LONDON = { name: 'Europe/London', offset: 0 };
const KOLKATA = { name: 'Asia/Kolkata', offset: 19800 };
const MANUAL = { name: '', offset: 19800 };

describe( 'toSiteInput', () => {
	it( 'shows a UTC date in the site’s time, for that date', () => {
		expect( toSiteInput( '2026-10-29T14:00:00Z', KOLKATA ) ).toBe(
			'2026-10-29T19:30'
		);
		expect( toSiteInput( '2026-10-29T14:00:00Z', MANUAL ) ).toBe(
			'2026-10-29T19:30'
		);
		expect( toSiteInput( '2026-07-15T04:00:00Z', NEW_YORK ) ).toBe(
			'2026-07-15T00:00'
		);
		expect( toSiteInput( '2026-01-15T05:00:00Z', NEW_YORK ) ).toBe(
			'2026-01-15T00:00'
		);
		expect( toSiteInput( '2025-11-26T00:00:00Z', UTC ) ).toBe(
			'2025-11-26T00:00'
		);
		expect( toSiteInput( null, UTC ) ).toBe( '' );
		expect( toSiteInput( 'nope', UTC ) ).toBe( '' );
	} );
} );

describe( 'initialValues', () => {
	const now = Date.UTC( 2026, 9, 25, 9, 30 );

	it( 'blocks a new address for 7 days by default', () => {
		expect( initialValues( null, '203.0.113.9', now ) ).toEqual( {
			ip: '203.0.113.9',
			type: 'temporary',
			reason: '',
			start: '',
			end: '2026-11-01T09:30',
		} );
	} );

	it( 'starts from the block being changed', () => {
		expect(
			initialValues(
				{
					ip: '203.0.113.9',
					type: 'permanent',
					reason: 'Spam',
					start: '2026-10-01T00:00:00Z',
					end: null,
				},
				'',
				now,
				LONDON
			)
		).toEqual( {
			ip: '203.0.113.9',
			type: 'permanent',
			reason: 'Spam',
			start: '2026-10-01T01:00',
			end: '2026-11-01T09:30',
		} );
	} );
} );

describe( 'blockBody', () => {
	it( 'sends site times without an offset, and no end for a permanent block', () => {
		expect(
			blockBody( {
				ip: ' 203.0.113.9 ',
				type: 'temporary',
				reason: ' x ',
				start: '',
				end: '2026-11-01T09:30',
			} )
		).toEqual( {
			kind: 'ip',
			ip: '203.0.113.9',
			type: 'temporary',
			reason: 'x',
			end: '2026-11-01T09:30:00',
		} );
		expect(
			blockBody( {
				ip: '2001:db8::1',
				type: 'permanent',
				reason: '',
				start: '2026-10-25T10:00',
				end: '2026-11-01T09:30',
			} )
		).toEqual( {
			kind: 'ip',
			ip: '2001:db8::1',
			type: 'permanent',
			reason: '',
			start: '2026-10-25T10:00:00',
		} );
	} );
} );

describe( 'blockBody when a block is edited', () => {
	const block = {
		ip: '203.0.113.9',
		type: 'temporary',
		reason: 'Spam',
		// Midnight, and a date-only end (23:59:59 on the site), in New York.
		start: '2026-07-15T04:00:00Z',
		end: '2026-08-01T03:59:59Z',
	};
	const edit = ( changes = {}, from = block, zone = NEW_YORK ) => {
		const values = initialValues( from, '', 0, zone );
		return blockBody(
			{ ...values, ...changes },
			editBaseline( from, zone )
		);
	};

	it( 'sends neither date when neither was edited, so the server keeps them to the second', () => {
		const body = edit();
		expect( body ).not.toHaveProperty( 'start' );
		expect( body ).not.toHaveProperty( 'end' );
		expect( body ).toMatchObject( { ip: '203.0.113.9', reason: 'Spam' } );
	} );

	it( 'keeps a time repeated by the clocks going back (the second 01:30)', () => {
		const repeated = { ...block, start: '2026-11-01T06:30:00Z' };
		expect( initialValues( repeated, '', 0, NEW_YORK ).start ).toBe(
			'2026-11-01T01:30'
		);
		expect( edit( {}, repeated ) ).not.toHaveProperty( 'start' );
	} );

	it( 'sends only the date that was edited, as a wall-clock time', () => {
		const start = edit( { start: '2026-07-16T09:00' } );
		expect( start.start ).toBe( '2026-07-16T09:00:00' );
		expect( start ).not.toHaveProperty( 'end' );
		const end = edit( { end: '2026-08-02T09:00' } );
		expect( end.end ).toBe( '2026-08-02T09:00:00' );
		expect( end ).not.toHaveProperty( 'start' );
	} );

	it( 'treats a date changed and changed back as not edited', () => {
		const values = initialValues( block, '', 0, NEW_YORK );
		expect(
			blockBody(
				{ ...values, end: values.end },
				editBaseline( block, NEW_YORK )
			)
		).not.toHaveProperty( 'end' );
	} );

	it( 'sends the end too when the new start passes it, so the server can say so', () => {
		const body = edit( { start: '2026-08-05T09:00' } );
		expect( body.start ).toBe( '2026-08-05T09:00:00' );
		expect( body.end ).toBe( '2026-07-31T23:59:00' );
	} );

	it( 'sends the end of a permanent block made temporary (it has none to keep)', () => {
		const permanent = { ...block, type: 'permanent', end: null };
		const body = edit(
			{ type: 'temporary', end: '2026-08-02T09:00' },
			permanent
		);
		expect( body.end ).toBe( '2026-08-02T09:00:00' );
		const untouched = edit( { type: 'temporary' }, permanent );
		expect( untouched.end ).toMatch( /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00$/ );
	} );

	it( 'sends no end when a temporary block is made permanent', () => {
		expect( edit( { type: 'permanent' } ) ).not.toHaveProperty( 'end' );
	} );

	it( 'keeps a location block’s dates in the same way', () => {
		const location = {
			...block,
			ip: '',
			key_type: 'country_code',
			key: 'US',
		};
		const values = initialLocationValues( location, 0, NEW_YORK );
		const body = blockBody( values, editBaseline( location, NEW_YORK ) );
		expect( body ).toMatchObject( { kind: 'location', key: 'US' } );
		expect( body ).not.toHaveProperty( 'start' );
		expect( body ).not.toHaveProperty( 'end' );
	} );

	it( 'sends the dates of a new block (nothing to keep)', () => {
		const body = blockBody( {
			ip: '203.0.113.9',
			type: 'temporary',
			reason: '',
			start: '2026-07-16T09:00',
			end: '2026-08-02T09:00',
		} );
		expect( body.start ).toBe( '2026-07-16T09:00:00' );
		expect( body.end ).toBe( '2026-08-02T09:00:00' );
	} );
} );

describe( 'Block selected IPs', () => {
	const rows = [
		{ id: 1, ip: '203.0.113.9', blocked: false },
		{ id: 2, ip: '203.0.113.9', blocked: false },
		{ id: 3, ip: '198.51.100.4', blocked: true },
		{ id: 4, ip: '2001:DB8::1', blocked: false },
		{ id: 5, ip: '192.0.2.1', blocked: false },
	];

	it( 'blocks each selected address once and counts the ones already blocked', () => {
		expect( bulkBlockPlan( rows, new Set( [ 1, 2, 3, 4 ] ) ) ).toEqual( {
			ips: [ '203.0.113.9', '2001:DB8::1' ],
			already: 1,
		} );
		expect( bulkBlockPlan( rows, new Set( [ 3 ] ) ) ).toEqual( {
			ips: [],
			already: 1,
		} );
	} );

	it( 'sends one request and reports new, replaced, skipped and invalid addresses', async () => {
		const calls = [];
		const request = ( options ) => {
			calls.push( options );
			return Promise.resolve( {
				created: 1,
				replaced: 1,
				skipped: [ '198.51.100.4' ],
				invalid: { nope: 'Must be an IPv4 or IPv6 address.' },
				undo_token: 'a'.repeat( 32 ),
			} );
		};

		const result = await blockAll( {
			ips: [ '203.0.113.9', '2001:DB8::1', 'nope' ],
			end: '2026-11-01T09:30',
			request,
		} );

		expect( result ).toEqual( {
			created: 1,
			replaced: 1,
			skipped: [ '198.51.100.4' ],
			failed: 1,
			undo_token: 'a'.repeat( 32 ),
		} );
		expect( calls ).toEqual( [
			{
				path: '/zero-spam/v1/rules/blocks/bulk',
				method: 'POST',
				data: {
					type: 'temporary',
					reason: '',
					end: '2026-11-01T09:30:00',
					ips: [ '203.0.113.9', '2001:DB8::1', 'nope' ],
				},
			},
		] );
	} );
} );
