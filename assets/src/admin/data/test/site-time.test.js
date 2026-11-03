/**
 * Absolute dates in the site's time zone. Each case runs with the process in
 * several time zones, so nothing depends on the browser's or the machine's.
 */

/**
 * External dependencies
 */
import { afterAll, beforeAll, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { offsetAt, siteWall } from '../site-time';

const UTC = { name: 'UTC', offset: 0 };
const NEW_YORK = { name: 'America/New_York', offset: -18000 };
const KOLKATA = { name: 'Asia/Kolkata', offset: 19800 };
const KIRITIMATI = { name: 'Pacific/Kiritimati', offset: 50400 };
const LORD_HOWE = { name: 'Australia/Lord_Howe', offset: 39600 };
const SANTIAGO = { name: 'America/Santiago', offset: -10800 };

/** WordPress set to a manual offset has no IANA name. */
const MANUAL_KOLKATA = { name: '', offset: 19800 };
const MANUAL_NEGATIVE = { name: '', offset: -12600 };

/** A name this browser does not know: the offset of the moment the page was made. */
const UNKNOWN = { name: 'Mars/Olympus_Mons', offset: -14400 };

const wall = ( iso, zone ) => {
	const date = siteWall( iso, zone );
	return date ? date.toISOString().slice( 0, 19 ) : date;
};

const BROWSERS = [
	[ 'UTC', 0 ],
	[ 'Asia/Tokyo', -540 ],
	[ 'America/Los_Angeles', 480 ],
];

describe.each( BROWSERS )( 'a browser in %s', ( browser, winterOffset ) => {
	const before = process.env.TZ;

	beforeAll( () => {
		process.env.TZ = browser;
	} );

	afterAll( () => {
		if ( undefined === before ) {
			delete process.env.TZ;
		} else {
			process.env.TZ = before;
		}
	} );

	it( 'really is in that zone (the test’s own check)', () => {
		expect( new Date( Date.UTC( 2026, 0, 15 ) ).getTimezoneOffset() ).toBe(
			winterOffset
		);
	} );

	it( 'keeps an exact midnight on its day', () => {
		expect( wall( '2025-11-26T00:00:00Z', UTC ) ).toBe(
			'2025-11-26T00:00:00'
		);
		expect( wall( '2025-11-25T18:30:00Z', KOLKATA ) ).toBe(
			'2025-11-26T00:00:00'
		);
	} );

	it( 'uses New York’s offset for that date, summer and winter', () => {
		expect( wall( '2026-07-15T04:00:00Z', NEW_YORK ) ).toBe(
			'2026-07-15T00:00:00'
		);
		expect( wall( '2026-01-15T05:00:00Z', NEW_YORK ) ).toBe(
			'2026-01-15T00:00:00'
		);
	} );

	it( 'reads a manual offset without a zone name', () => {
		expect( wall( '2025-11-25T18:30:00Z', MANUAL_KOLKATA ) ).toBe(
			'2025-11-26T00:00:00'
		);
		expect( wall( '2025-11-26T03:30:00Z', MANUAL_NEGATIVE ) ).toBe(
			'2025-11-26T00:00:00'
		);
	} );

	it( 'follows the clocks going forward in New York (the gap)', () => {
		expect( wall( '2026-03-08T06:59:59Z', NEW_YORK ) ).toBe(
			'2026-03-08T01:59:59'
		);
		// 02:00 to 02:59 never happen that night.
		expect( wall( '2026-03-08T07:00:00Z', NEW_YORK ) ).toBe(
			'2026-03-08T03:00:00'
		);
	} );

	it( 'follows the clocks going back in New York (both 01:30)', () => {
		expect( wall( '2026-11-01T05:30:00Z', NEW_YORK ) ).toBe(
			'2026-11-01T01:30:00'
		);
		expect( wall( '2026-11-01T06:30:00Z', NEW_YORK ) ).toBe(
			'2026-11-01T01:30:00'
		);
		expect( wall( '2026-11-01T07:00:00Z', NEW_YORK ) ).toBe(
			'2026-11-01T02:00:00'
		);
	} );

	it( 'handles a half-hour DST change and a gap at midnight', () => {
		// Lord Howe moves between +11:00 and +10:30.
		expect( wall( '2026-01-15T13:00:00Z', LORD_HOWE ) ).toBe(
			'2026-01-16T00:00:00'
		);
		expect( wall( '2026-07-15T13:30:00Z', LORD_HOWE ) ).toBe(
			'2026-07-16T00:00:00'
		);
		// Chile's clocks skip midnight: 00:00 on Sep 6, 2026 is 01:00.
		expect( wall( '2026-09-06T04:00:00Z', SANTIAGO ) ).toBe(
			'2026-09-06T01:00:00'
		);
	} );

	it( 'crosses year boundaries in the site’s zone', () => {
		expect( wall( '2026-01-01T04:59:59Z', NEW_YORK ) ).toBe(
			'2025-12-31T23:59:59'
		);
		expect( wall( '2026-01-01T05:00:00Z', NEW_YORK ) ).toBe(
			'2026-01-01T00:00:00'
		);
		expect( wall( '2025-12-31T10:00:00Z', KIRITIMATI ) ).toBe(
			'2026-01-01T00:00:00'
		);
	} );

	it( 'keeps seconds, so a date-only end stays 23:59:59', () => {
		expect( wall( '2026-08-01T03:59:59Z', NEW_YORK ) ).toBe(
			'2026-07-31T23:59:59'
		);
	} );

	it( 'falls back to the page’s offset for a name the browser does not know', () => {
		expect( offsetAt( Date.UTC( 2026, 6, 15 ), UNKNOWN ) ).toBe(
			-14400000
		);
		expect( wall( '2026-07-15T04:00:00Z', UNKNOWN ) ).toBe(
			'2026-07-15T00:00:00'
		);
	} );

	it( 'reads no zone at all as UTC rather than failing', () => {
		expect( wall( '2026-07-15T04:00:00Z', undefined ) ).toBe(
			'2026-07-15T04:00:00'
		);
	} );

	it( 'leaves a date without a zone as it is, in any zone', () => {
		expect( wall( '2026-10-22T00:00:00', KOLKATA ) ).toBe(
			'2026-10-22T00:00:00'
		);
		expect( wall( '2026-10-22', NEW_YORK ) ).toBe( '2026-10-22T00:00:00' );
	} );

	it( 'gives null for no date or an unreadable one', () => {
		expect( siteWall( null, UTC ) ).toBeNull();
		expect( siteWall( '', UTC ) ).toBeNull();
		expect( siteWall( 'nope', UTC ) ).toBeNull();
	} );

	it( 'reads a date with an explicit offset as that moment', () => {
		expect( wall( '2026-07-15T00:00:00-04:00', NEW_YORK ) ).toBe(
			'2026-07-15T00:00:00'
		);
	} );

	it( 'ignores the milliseconds of a moment when working out the offset', () => {
		expect( offsetAt( Date.UTC( 2026, 0, 15 ) + 999, NEW_YORK ) ).toBe(
			-18000000
		);
	} );
} );
