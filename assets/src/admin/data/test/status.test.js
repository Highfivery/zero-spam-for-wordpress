/**
 * The protection status the shell and the Overview show: the core entry
 * points decide whether a site is called protected.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { ENTRY_POINTS, protectionStatus } from '../status';

const modules = ( on, attention = [] ) => ( {
	modules: [
		...ENTRY_POINTS.map( ( key ) => ( {
			key,
			enabled: on.includes( key ),
		} ) ),
		{ key: 'honeypot', enabled: true },
	],
	attention,
} );

describe( 'protectionStatus', () => {
	it( 'waits for the answer', () => {
		expect( protectionStatus( undefined ).state ).toBe( 'loading' );
	} );

	it( 'is "off" on a fresh install with every entry point off', () => {
		const status = protectionStatus( modules( [] ) );
		expect( status ).toMatchObject( {
			state: 'off',
			tone: 'warn',
			coreOn: 0,
			coreTotal: 3,
			label: 'No checks active',
		} );
	} );

	it( 'is "partial" while one entry point is off', () => {
		const status = protectionStatus(
			modules(
				[ 'comments', 'login' ],
				[ { id: 'entry_point_off:registration', severity: 'warning' } ]
			)
		);
		expect( status ).toMatchObject( {
			state: 'partial',
			tone: 'warn',
			coreOn: 2,
			label: 'Protection not fully on',
		} );
	} );

	it( 'counts warnings to review once every entry point is on', () => {
		const status = protectionStatus(
			modules( ENTRY_POINTS, [
				{ id: 'a', severity: 'warning' },
				{ id: 'b', severity: 'info' },
			] )
		);
		expect( status ).toMatchObject( {
			state: 'review',
			count: 1,
			label: '1 to review',
		} );
	} );

	it( 'is "ok" with every entry point on and nothing to review', () => {
		expect( protectionStatus( modules( ENTRY_POINTS ) ) ).toMatchObject( {
			state: 'ok',
			tone: 'ok',
			label: 'All checks active',
		} );
	} );
} );
