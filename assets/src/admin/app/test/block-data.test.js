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
	initialValues,
	toSiteInput,
} from '../block-data';

const HOUR = 3600000;

describe( 'toSiteInput', () => {
	it( 'shows a UTC date in the site’s time', () => {
		expect( toSiteInput( '2026-10-29T14:00:00Z', 2 * HOUR ) ).toBe(
			'2026-10-29T16:00'
		);
		expect( toSiteInput( null, 0 ) ).toBe( '' );
		expect( toSiteInput( 'nope', 0 ) ).toBe( '' );
	} );
} );

describe( 'initialValues', () => {
	const now = Date.UTC( 2026, 9, 25, 9, 30 );

	it( 'blocks a new address for 7 days by default', () => {
		expect( initialValues( null, '203.0.113.9', now, 0 ) ).toEqual( {
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
				HOUR
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

	it( 'sends temporary blocks and reports new, replaced and failed ones', async () => {
		const calls = [];
		const answers = {
			'203.0.113.9': { status: 201, body: { id: 11 } },
			'2001:DB8::1': { status: 200, body: { id: 12 } },
		};
		const request = ( options ) => {
			calls.push( options );
			const a = answers[ options.data.ip ];
			return a
				? Promise.resolve( {
						status: a.status,
						json: () => Promise.resolve( a.body ),
					} )
				: Promise.reject( new Error( 'nope' ) );
		};

		const result = await blockAll( {
			ips: [ '203.0.113.9', '2001:DB8::1', '192.0.2.1' ],
			end: '2026-11-01T09:30',
			request,
		} );

		expect( result ).toEqual( { created: [ 11 ], replaced: 1, failed: 1 } );
		expect( calls[ 0 ] ).toEqual( {
			path: '/zero-spam/v1/rules/blocks',
			method: 'POST',
			data: {
				kind: 'ip',
				ip: '203.0.113.9',
				type: 'temporary',
				reason: '',
				end: '2026-11-01T09:30:00',
			},
			parse: false,
		} );
	} );
} );
