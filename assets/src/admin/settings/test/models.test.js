/**
 * The Settings area's own logic: the quota period of the date control
 * (as License_Status::quota_period() works it out), display preferences
 * applied live, and import results and files.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	applyDisplay,
	exportFileName,
	formatDay,
	importResult,
	isDate,
	quotaPeriod,
	readImport,
	setRootAttributes,
	todayOf,
} from '../models';

describe( 'the quota reset date control', () => {
	it( 'accepts real calendar dates only', () => {
		expect( isDate( '2026-11-15' ) ).toBe( true );
		expect( isDate( '2028-02-29' ) ).toBe( true );
		expect( isDate( '2026-02-29' ) ).toBe( false );
		expect( isDate( '2026-02-31' ) ).toBe( false );
		expect( isDate( '2026-13-01' ) ).toBe( false );
		expect( isDate( '' ) ).toBe( false );
		expect( isDate( undefined ) ).toBe( false );
		expect( isDate( '15/11/2026' ) ).toBe( false );
	} );

	it( 'keeps a date still to come as the next reset', () => {
		expect( quotaPeriod( '2026-11-15', '2026-10-25' ) ).toEqual( {
			start: '2026-10-15',
			next: '2026-11-15',
		} );
	} );

	it( 'rolls a past date forward a month at a time', () => {
		expect( quotaPeriod( '2026-03-05', '2026-10-25' ) ).toEqual( {
			start: '2026-10-05',
			next: '2026-11-05',
		} );
		// Across a year.
		expect( quotaPeriod( '2025-12-30', '2026-12-31' ) ).toEqual( {
			start: '2026-12-30',
			next: '2027-01-30',
		} );
	} );

	it( 'counts a reset due today as done', () => {
		expect( quotaPeriod( '2026-10-25', '2026-10-25' ) ).toEqual( {
			start: '2026-10-25',
			next: '2026-11-25',
		} );
	} );

	it( 'keeps the day of the month, or the last day of a shorter month', () => {
		expect( quotaPeriod( '2026-01-31', '2026-02-10' ) ).toEqual( {
			start: '2026-01-31',
			next: '2026-02-28',
		} );
		expect( quotaPeriod( '2026-01-31', '2026-03-01' ) ).toEqual( {
			start: '2026-02-28',
			next: '2026-03-31',
		} );
	} );

	it( 'has no period without a valid date', () => {
		expect( quotaPeriod( '', '2026-10-25' ) ).toBeNull();
		expect( quotaPeriod( '2026-02-30', '2026-10-25' ) ).toBeNull();
	} );

	it( 'writes days for people, whatever the time zone', () => {
		expect( formatDay( '2026-11-15', 'en-US' ) ).toBe( 'Nov 15, 2026' );
		expect( formatDay( 'nope', 'en-US' ) ).toBe( '' );
		expect( todayOf( Date.UTC( 2026, 9, 25, 23, 59 ) ) ).toBe(
			'2026-10-25'
		);
	} );
} );

describe( 'display preferences apply live', () => {
	/**
	 * A stand-in for the app root that records its attributes.
	 *
	 * @return {Object} Root.
	 */
	const fakeRoot = () => {
		const attrs = {};
		return {
			attrs,
			setAttribute: ( name, value ) => {
				attrs[ name ] = value;
			},
			removeAttribute: ( name ) => {
				delete attrs[ name ];
			},
		};
	};

	it( 'gives the root the new theme before anything is saved', () => {
		const root = fakeRoot();
		const next = applyDisplay(
			{ theme: 'auto', reduce_motion: false, dismissed: [ 'x' ] },
			{ theme: 'dark' },
			false,
			false
		);
		setRootAttributes( root, next.attributes );
		expect( root.attrs ).toEqual( { 'data-zs-theme': 'dark' } );
		expect( next.shown ).toBe( 'dark' );
		// Other preferences are kept.
		expect( next.prefs.dismissed ).toEqual( [ 'x' ] );
	} );

	it( 'follows the device for "Match my device"', () => {
		expect( applyDisplay( {}, { theme: 'auto' }, true, false ).shown ).toBe(
			'dark'
		);
		expect(
			applyDisplay( {}, { theme: 'auto' }, false, false ).shown
		).toBe( 'light' );
		expect( applyDisplay( {}, { theme: 'nope' }, true, false ).theme ).toBe(
			'auto'
		);
	} );

	it( 'turns animations off and on again at once', () => {
		const root = fakeRoot();
		setRootAttributes(
			root,
			applyDisplay( {}, { reduce_motion: true }, false, false ).attributes
		);
		expect( root.attrs[ 'data-zs-motion' ] ).toBe( 'reduce' );
		setRootAttributes(
			root,
			applyDisplay(
				{ reduce_motion: true },
				{ reduce_motion: false },
				false,
				false
			).attributes
		);
		expect( root.attrs[ 'data-zs-motion' ] ).toBeUndefined();
	} );

	it( 'keeps animations off while the device asks for reduced motion', () => {
		expect(
			applyDisplay( {}, { reduce_motion: false }, false, true ).reduced
		).toBe( true );
	} );
} );

describe( 'import results', () => {
	const entries = {
		share_data: { title: 'Usage Data Sharing' },
		max_logs: { title: 'Maximum Log Entries' },
		zerospam_license: { title: 'License Key' },
	};

	it( 'groups what was applied, skipped and refused, with titles', () => {
		const result = importResult(
			{
				imported: [ 'share_data', 'max_logs' ],
				changed: [ 'max_logs' ],
				skipped_locked: [ 'block_method' ],
				skipped_secrets: [ 'zerospam_license' ],
				errors: {
					debug_ip: 'Invalid value for "debug_ip". Must be text.',
				},
				ignored: [ 'rescue_mode' ],
			},
			entries
		);
		expect( result.applied ).toEqual( [
			{ key: 'share_data', title: 'Usage Data Sharing' },
			{ key: 'max_logs', title: 'Maximum Log Entries' },
		] );
		expect( result.changed ).toBe( 1 );
		expect( result.locked ).toEqual( [
			{ key: 'block_method', title: 'block_method' },
		] );
		expect( result.secrets ).toEqual( [
			{ key: 'zerospam_license', title: 'License Key' },
		] );
		expect( result.errors ).toEqual( [
			{ key: 'debug_ip', title: 'debug_ip', message: 'Must be text.' },
		] );
		expect( result.ignored ).toHaveLength( 1 );
		expect( result.ok ).toBe( false );
	} );

	it( 'reads an empty answer as nothing applied', () => {
		expect( importResult( {} ) ).toEqual( {
			applied: [],
			changed: 0,
			locked: [],
			secrets: [],
			errors: [],
			ignored: [],
			ok: true,
		} );
	} );

	it( 'sends only a JSON object, and says why not', () => {
		expect( readImport( '{"settings":{"share_data":"enabled"}}' ) ).toEqual(
			{ ok: true, body: '{"settings":{"share_data":"enabled"}}' }
		);
		// A byte order mark is fine.
		expect( readImport( '﻿{"a":{}}' ).ok ).toBe( true );
		expect( readImport( 'not json' ).ok ).toBe( false );
		expect( readImport( '[1,2]' ).ok ).toBe( false );
		expect( readImport( 'null' ).ok ).toBe( false );
	} );

	it( 'names an export after the site and the day', () => {
		expect(
			exportFileName( 'https://Lakeside.example/blog/', '2026-10-25' )
		).toBe( 'zero-spam-settings-lakeside-example-2026-10-25.json' );
		expect( exportFileName( 'http://localhost:8900/', '2026-10-25' ) ).toBe(
			'zero-spam-settings-localhost-8900-2026-10-25.json'
		);
		expect( exportFileName( '', '2026-10-25' ) ).toBe(
			'zero-spam-settings-2026-10-25.json'
		);
	} );
} );
