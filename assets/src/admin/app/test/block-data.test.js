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
import { blockBody, initialValues, toSiteInput } from '../block-data';

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
			end: '2026-11-01T09:30',
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
			start: '2026-10-25T10:00',
		} );
	} );
} );
