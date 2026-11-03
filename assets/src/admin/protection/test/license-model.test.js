/**
 * The license on the Protection page: the panel's facts (no prices or
 * quotas; renewal and sites only when known), the network line, and the
 * key flow.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	canRemoveKey,
	keyError,
	keySaved,
	licenseRows,
	networkLine,
} from '../license-model';
import { enhancedLicense } from '../../app/fields/test/fixture';

describe( 'licenseRows', () => {
	it( 'shows the masked key and plan, never queries', () => {
		const rows = licenseRows( enhancedLicense() );
		expect( rows ).toEqual( [
			[ 'License key', '••••7Q4K', true ],
			[ 'Plan', 'Business' ],
		] );
		expect( JSON.stringify( rows ) ).not.toMatch( /31200|18800|50000/ );
	} );

	it( 'adds renewal and sites when the answer has them', () => {
		const rows = licenseRows( {
			...enhancedLicense(),
			renewal: { status: 'active', date: '2026-11-14' },
			sites: 3,
		} );
		expect( rows.map( ( r ) => r[ 0 ] ) ).toEqual( [
			'License key',
			'Plan',
			'Renews',
			'Sites',
		] );
		expect( rows[ 3 ][ 1 ] ).toBe( '3 activated' );
	} );

	it( 'says where a key from wp-config.php comes from', () => {
		const rows = licenseRows( {
			...enhancedLicense(),
			key: { set: true, source: 'constant' },
		} );
		expect( rows ).toContainEqual( [ 'License key', '••••', true ] );
		expect( rows ).toContainEqual( [ 'Set in', 'wp-config.php' ] );
	} );
} );

describe( 'networkLine', () => {
	const now = 1_800_000_000_000;
	const license = { ...enhancedLicense(), checked_at: now / 1000 - 300 };

	it( 'is connected, with the latency and when it was checked', () => {
		expect( networkLine( license, 38, now ) ).toEqual( {
			tone: 'ok',
			text: 'Network connected · 38 ms · last checked 5 min ago',
		} );
		expect( networkLine( license, null, now ).text ).toBe(
			'Network connected · last checked 5 min ago'
		);
	} );

	it( 'warns when the network cannot be reached or lookups ran out', () => {
		expect(
			networkLine( { ...license, status: 'unreachable' }, null, now ).tone
		).toBe( 'warn' );
		const quota = networkLine(
			{ ...license, status: 'quota_exceeded' },
			null,
			now
		);
		expect( quota.tone ).toBe( 'warn' );
		expect( quota.text ).not.toMatch( /\d/ );
	} );
} );

describe( 'key flow', () => {
	it( 'turns Enhanced on after a working key when it is off', () => {
		expect( keySaved( { ...enhancedLicense(), enabled: false } ) ).toEqual(
			{
				working: true,
				enable: true,
				message:
					'License verified · Business plan. Enhanced Protection is on.',
			}
		);
		expect( keySaved( enhancedLicense() ).enable ).toBe( false );
		expect(
			keySaved( { status: 'unreachable', enabled: false, plan: null } )
		).toMatchObject( { working: true, enable: true } );
		expect( keySaved( { status: 'unreachable' } ).message ).toMatch(
			/saved/
		);
	} );

	it( 'explains a refused key', () => {
		expect(
			keyError( {
				code: 'rest_invalid_param',
				data: { params: { license_key: 'The key is invalid.' } },
			} )
		).toBe( 'The key is invalid.' );
		expect( keyError( { code: 'rest_setting_locked' } ) ).toMatch(
			/network administrator/
		);
		expect( keyError( { message: 'Offline' } ) ).toBe( 'Offline' );
	} );

	it( 'lets the site remove only its own key', () => {
		expect( canRemoveKey( enhancedLicense() ) ).toBe( true );
		expect(
			canRemoveKey( { key: { set: true, source: 'constant' } } )
		).toBe( false );
		expect(
			canRemoveKey( { key: { set: true, source: 'network' } } )
		).toBe( false );
		expect(
			canRemoveKey( { key: { set: false, source: 'default' } } )
		).toBe( false );
	} );
} );
