/**
 * "What's new in 6.0": when the tile shows, its dismissal, and the map of
 * where the 5.x screens moved.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	compareVersions,
	dismissChanges,
	menuAreas,
	movedRows,
	newSwitches,
	shouldShowWhatsNew,
} from '../model';

describe( 'compareVersions()', () => {
	it( 'compares part by part, numerically', () => {
		expect( compareVersions( '6.0.0', '6.0.0' ) ).toBe( 0 );
		expect( compareVersions( '5.7.14', '6.0.0' ) ).toBeLessThan( 0 );
		expect( compareVersions( '6.10.0', '6.9.9' ) ).toBeGreaterThan( 0 );
		expect( compareVersions( '6.0.0-beta.1', '6.0.0' ) ).toBe( 0 );
		expect( compareVersions( '', '6.0.0' ) ).toBeLessThan( 0 );
	} );
} );

describe( 'shouldShowWhatsNew()', () => {
	const base = { release: '6.0.0', seen: '', prefsLoaded: true };

	it( 'shows on an upgraded site until this administrator dismisses it', () => {
		expect( shouldShowWhatsNew( base ) ).toBe( true );
		expect( shouldShowWhatsNew( { ...base, seen: '6.0.0' } ) ).toBe(
			false
		);
		expect( shouldShowWhatsNew( { ...base, seen: '5.7.14' } ) ).toBe(
			true
		);
	} );

	it( 'never shows on a fresh install, during setup, or before the preferences arrive', () => {
		expect( shouldShowWhatsNew( { ...base, release: '' } ) ).toBe( false );
		expect( shouldShowWhatsNew( { ...base, onboarding: true } ) ).toBe(
			false
		);
		expect( shouldShowWhatsNew( { ...base, prefsLoaded: false } ) ).toBe(
			false
		);
	} );

	it( 'remembers the dismissed release per user (ui/prefs)', () => {
		expect( dismissChanges( '6.0.0' ) ).toEqual( {
			whats_new_seen: '6.0.0',
		} );
		const after = shouldShowWhatsNew( {
			...base,
			seen: dismissChanges( '6.0.0' ).whats_new_seen,
		} );
		expect( after ).toBe( false );
	} );
} );

describe( 'the panel', () => {
	it( 'lists the six areas of the menu', () => {
		expect( menuAreas().map( ( a ) => a[ 0 ] ) ).toEqual( [
			'overview',
			'activity',
			'rules',
			'protection',
			'settings',
			'help',
		] );
	} );

	it( 'maps every old screen to a real area and tab', () => {
		const rows = movedRows();
		const tabs = {
			activity: [ undefined, 'reports' ],
			rules: [ 'ips', 'locations' ],
			protection: [ 'detection', 'integrations', 'intelligence' ],
			settings: [ 'general', 'api', 'debug', 'io' ],
			help: [ undefined ],
		};
		rows.forEach( ( row ) => {
			expect( tabs[ row.area ] ).toBeDefined();
			expect( tabs[ row.area ] ).toContain( row.args.tab );
		} );
		expect( rows.some( ( r ) => 'network' === r.area ) ).toBe( false );
		expect( movedRows( true ).at( -1 ).area ).toBe( 'network' );
	} );

	it( 'links each new switch to where it lives', () => {
		newSwitches().forEach( ( item ) => {
			expect( [ 'protection', 'settings' ] ).toContain( item.area );
			expect( item.title ).toBeTruthy();
		} );
	} );
} );
