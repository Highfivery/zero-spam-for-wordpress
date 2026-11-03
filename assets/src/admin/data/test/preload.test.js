/**
 * The first render reads the page's preloaded answers, once, so the screen
 * paints with its data (no layout shift when it arrives).
 */

/**
 * External dependencies
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { PATHS, request, takePreloaded } from '../api';

vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn( () => Promise.resolve( {} ) ),
} ) );

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

describe( 'the preloading middleware’s copy', () => {
	beforeEach( () => {
		apiFetch.mockClear();
	} );

	afterEach( () => {
		delete globalThis.window;
	} );

	it( 'is used up with one apiFetch of the same path, once (answered from memory)', () => {
		globalThis.window = {
			wp: { apiFetch },
			zeroSpamPreload: { [ PATHS.prefs ]: { body: {}, headers: {} } },
		};
		takePreloaded( PATHS.prefs );
		takePreloaded( PATHS.prefs );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: PATHS.prefs,
			parse: false,
		} );
	} );

	it( 'is left alone outside WordPress (no wp.apiFetch, no middleware)', () => {
		globalThis.window = {
			zeroSpamPreload: { [ PATHS.prefs ]: { body: {}, headers: {} } },
		};
		expect( takePreloaded( PATHS.prefs ) ).toBeDefined();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'is never drained after request() read the path (that would be a real GET)', async () => {
		globalThis.window = {
			wp: { apiFetch },
			zeroSpamPreload: { [ PATHS.license ]: { body: {}, headers: {} } },
		};
		await request( { path: PATHS.license } );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( takePreloaded( PATHS.license ) ).toBeUndefined();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );
} );
