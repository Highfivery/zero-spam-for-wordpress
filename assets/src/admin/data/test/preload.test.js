/**
 * The first render reads the page's preloaded answers, once, so the screen
 * paints with its data (no layout shift when it arrives).
 */

/**
 * External dependencies
 */
import { afterEach, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { PATHS, takePreloaded } from '../api';

describe( 'takePreloaded', () => {
	afterEach( () => {
		delete globalThis.window;
	} );

	it( 'returns a preloaded answer by its exact path, once', () => {
		const answer = { body: { total: 3 }, headers: { 'X-WP-Total': '3' } };
		globalThis.window = {
			zeroSpamPreload: { [ PATHS.summary( 7 ) ]: answer },
		};
		expect( takePreloaded( PATHS.summary( 7 ) ) ).toBe( answer );
		expect( takePreloaded( PATHS.summary( 7 ) ) ).toBeUndefined();
	} );

	it( 'is undefined without a preload, a path or a match', () => {
		expect( takePreloaded( PATHS.prefs ) ).toBeUndefined();
		globalThis.window = { zeroSpamPreload: [] };
		expect( takePreloaded( null ) ).toBeUndefined();
		expect( takePreloaded( PATHS.prefs ) ).toBeUndefined();
	} );
} );
