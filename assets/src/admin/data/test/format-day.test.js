/**
 * Days: a year is only shown when it is not this year; the compact form the
 * Rules lists use drops the weekday next to a year, which keeps their date
 * columns from widening the table.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { day } from '../format';

const NOW = new Date( '2026-11-02T12:00:00Z' );

describe( 'day', () => {
	it( 'shows the weekday, and the year when it is not this year', () => {
		expect( day( '2026-10-22T12:00:00Z', NOW ) ).toMatch( /Thu.*Oct.*22/ );
		expect( day( '2099-01-15T12:00:00Z', NOW ) ).toMatch(
			/Thu.*Jan.*2099/
		);
	} );

	it( 'compact: this year is unchanged', () => {
		expect( day( '2026-10-22T12:00:00Z', NOW, { compact: true } ) ).toBe(
			day( '2026-10-22T12:00:00Z', NOW )
		);
	} );

	it( 'compact: no weekday next to a year', () => {
		const text = day( '2099-01-15T12:00:00Z', NOW, { compact: true } );
		expect( text ).toContain( '2099' );
		expect( text ).not.toMatch( /Thu/ );
	} );
} );
