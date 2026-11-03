/**
 * Block dates on the page: the site's real time zone for that date, whatever
 * the latency, the browser's clock and the browser's time zone.
 */

/**
 * External dependencies
 */
import { afterEach, describe, expect, it, vi } from 'vitest';

const NEW_YORK = { name: 'America/New_York', offset: -14400 };
const KOLKATA = { name: 'Asia/Kolkata', offset: 19800 };
const UTC = { name: 'UTC', offset: 0 };

// The page's clocks: PHP's whole second at `serverNow`, seen by a browser
// whose clock is `skew` ahead, `latency` after PHP read it.
const SERVER_NOW = Date.UTC( 2026, 10, 2, 15, 0, 0 );

/**
 * Loads the helpers as a page with these clocks would.
 *
 * @param {Object} zone      The bootstrap's time zone.
 * @param {number} [skew]    Browser clock minus the real time, in milliseconds.
 * @param {number} [latency] Milliseconds between PHP and the script.
 * @return {Promise<Object>} siteDay, the Rules lists' compact siteDay and nowLocal.
 */
async function page( zone, skew = 0, latency = 899 ) {
	vi.resetModules();
	vi.useFakeTimers();
	vi.setSystemTime( SERVER_NOW + latency + skew );
	const offsetNow = zone.offset * 1000;
	globalThis.window = {
		zeroSpamAdmin: {
			nowLocal: SERVER_NOW + offsetNow,
			timezone: zone,
		},
	};
	const { siteDay } = await import( '../site-day' );
	const { siteDay: rulesDay } = await import( '../../rules/parts' );
	const { nowLocal } = await import( '../api' );
	return { siteDay, rulesDay, nowLocal };
}

describe( 'siteDay', () => {
	afterEach( () => {
		vi.useRealTimers();
		delete globalThis.window;
	} );

	it( 'keeps an exact midnight on its own day despite latency (UTC)', async () => {
		const { siteDay } = await page( UTC );
		expect( siteDay( '2025-11-26T00:00:00Z' ) ).toBe( 'Wed, Nov 26, 2025' );
	} );

	it( 'keeps an exact midnight on its own day (Kolkata)', async () => {
		const { siteDay } = await page( KOLKATA );
		expect( siteDay( '2025-11-25T18:30:00Z' ) ).toBe( 'Wed, Nov 26, 2025' );
	} );

	it( 'uses that date’s offset, not today’s (New York in summer, seen in winter)', async () => {
		const { siteDay } = await page( { ...NEW_YORK, offset: -18000 } );
		expect( siteDay( '2026-07-15T04:00:00Z' ) ).toBe( 'Wed, Jul 15' );
		expect( siteDay( '2026-01-15T05:00:00Z' ) ).toBe( 'Thu, Jan 15' );
	} );

	it( 'does not move a date with a fast or a slow browser clock', async () => {
		const fast = await page( UTC, 10 * 60000 );
		expect( fast.siteDay( '2026-07-15T00:05:00Z' ) ).toBe( 'Wed, Jul 15' );
		const slow = await page( UTC, -10 * 60000 );
		expect( slow.siteDay( '2026-07-15T23:55:00Z' ) ).toBe( 'Wed, Jul 15' );
	} );

	it( 'hides this year but shows another one, in the site’s year', async () => {
		const { siteDay } = await page( { ...NEW_YORK, offset: -18000 } );
		// Still New Year's Eve 2025 in New York.
		expect( siteDay( '2026-01-01T04:59:59Z' ) ).toBe( 'Wed, Dec 31, 2025' );
		expect( siteDay( '2026-01-01T05:00:00Z' ) ).toBe( 'Thu, Jan 1' );
	} );

	it( 'shows a date without a zone as it is (date_added is site time)', async () => {
		const { siteDay } = await page( KOLKATA );
		expect( siteDay( '2026-10-22T00:00:00' ) ).toBe( 'Thu, Oct 22' );
		expect( siteDay( null ) ).toBe( '' );
		expect( siteDay( 'nope' ) ).toBe( '' );
	} );
} );

describe( 'nowLocal (relative times)', () => {
	afterEach( () => {
		vi.useRealTimers();
		delete globalThis.window;
	} );

	it( 'still corrects a wrong browser clock', async () => {
		for ( const skew of [ 10 * 60000, -10 * 60000, 0 ] ) {
			const { nowLocal } = await page( NEW_YORK, skew, 0 );
			expect( nowLocal() ).toBe( SERVER_NOW + NEW_YORK.offset * 1000 );
		}
	} );

	it( 'the Rules lists drop the weekday next to a year, and only there', async () => {
		const { siteDay, rulesDay } = await page( UTC );
		expect( rulesDay( '2025-11-26T00:00:00Z' ) ).toBe( 'Nov 26, 2025' );
		expect( siteDay( '2025-11-26T00:00:00Z' ) ).toBe( 'Wed, Nov 26, 2025' );
		expect( rulesDay( '2026-10-22T00:00:00' ) ).toBe(
			siteDay( '2026-10-22T00:00:00' )
		);
	} );
} );
