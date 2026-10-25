/**
 * Theme and motion resolution for the app root.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	motionReduced,
	resolveTheme,
	rootAttributes,
	themePref,
	toggledTheme,
} from '../theme';

describe( 'theme', () => {
	it( 'accepts light, dark and auto, and falls back to auto', () => {
		expect( themePref( 'light' ) ).toBe( 'light' );
		expect( themePref( 'dark' ) ).toBe( 'dark' );
		expect( themePref( 'auto' ) ).toBe( 'auto' );
		expect( themePref( 'purple' ) ).toBe( 'auto' );
		expect( themePref( undefined ) ).toBe( 'auto' );
	} );

	it( 'follows the operating system only for auto', () => {
		expect( resolveTheme( 'auto', true ) ).toBe( 'dark' );
		expect( resolveTheme( 'auto', false ) ).toBe( 'light' );
		expect( resolveTheme( 'light', true ) ).toBe( 'light' );
		expect( resolveTheme( 'dark', false ) ).toBe( 'dark' );
	} );

	it( 'switches to the opposite of what is shown', () => {
		expect( toggledTheme( 'auto', true ) ).toBe( 'light' );
		expect( toggledTheme( 'auto', false ) ).toBe( 'dark' );
		expect( toggledTheme( 'dark', false ) ).toBe( 'light' );
		expect( toggledTheme( 'light', true ) ).toBe( 'dark' );
	} );
} );

describe( 'motion', () => {
	it( 'is reduced by the user preference or the operating system', () => {
		expect( motionReduced( false, false ) ).toBe( false );
		expect( motionReduced( true, false ) ).toBe( true );
		expect( motionReduced( false, true ) ).toBe( true );
		expect( motionReduced( 'yes', false ) ).toBe( false );
	} );
} );

describe( 'rootAttributes', () => {
	it( 'maps preferences to data-zs-theme and data-zs-motion', () => {
		expect(
			rootAttributes( { theme: 'dark', reduce_motion: true } )
		).toEqual( {
			'data-zs-theme': 'dark',
			'data-zs-motion': 'reduce',
		} );
		expect( rootAttributes( {} ) ).toEqual( {
			'data-zs-theme': 'auto',
			'data-zs-motion': undefined,
		} );
	} );
} );
