/**
 * The block drawer's location form (Rules › Blocked locations): its first
 * values for a new and an existing block, and the body it sends.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { blockBody, initialLocationValues } from '../block-data';
import { locationExample } from '../block-form';

const NOW = Date.UTC( 2026, 9, 22, 12, 0 );

describe( 'initialLocationValues', () => {
	it( 'starts a new block as a temporary country block ending in a week', () => {
		expect( initialLocationValues( null, NOW, 0 ) ).toEqual( {
			key_type: 'country_code',
			key: '',
			type: 'temporary',
			reason: '',
			start: '',
			end: '2026-10-29T12:00',
		} );
	} );

	it( 'fills in an existing block in the site’s time', () => {
		expect(
			initialLocationValues(
				{
					kind: 'location',
					key_type: 'zip',
					key: '90210',
					type: 'temporary',
					reason: 'Card testing',
					start: '2026-10-20T08:00:00Z',
					end: '2026-10-30T08:00:00Z',
				},
				NOW,
				2 * 3600000
			)
		).toEqual( {
			key_type: 'zip',
			key: '90210',
			type: 'temporary',
			reason: 'Card testing',
			start: '2026-10-20T10:00',
			end: '2026-10-30T10:00',
		} );
	} );
} );

describe( 'blockBody', () => {
	it( 'sends a location block, without an end when permanent', () => {
		expect(
			blockBody( {
				key_type: 'country_code',
				key: ' DE ',
				type: 'permanent',
				reason: ' Comment spam ',
				start: '',
				end: '2026-10-29T12:00',
			} )
		).toEqual( {
			kind: 'location',
			key_type: 'country_code',
			key: 'DE',
			type: 'permanent',
			reason: 'Comment spam',
		} );
	} );

	it( 'still sends an IP block as before', () => {
		expect(
			blockBody( {
				ip: '203.0.113.9',
				type: 'temporary',
				reason: '',
				start: '2026-10-22T12:00',
				end: '2026-10-29T12:00',
			} )
		).toEqual( {
			kind: 'ip',
			ip: '203.0.113.9',
			type: 'temporary',
			reason: '',
			start: '2026-10-22T12:00:00',
			end: '2026-10-29T12:00:00',
		} );
	} );
} );

describe( 'locationExample', () => {
	it( 'gives an example for each location type', () => {
		expect( locationExample( 'country_code' ) ).toBe( 'e.g. US' );
		expect( locationExample( 'region_code' ) ).toBe( 'e.g. CA' );
		expect( locationExample( 'city' ) ).toBe( 'e.g. Springfield' );
		expect( locationExample( 'zip' ) ).toBe( 'e.g. 90210' );
	} );
} );
