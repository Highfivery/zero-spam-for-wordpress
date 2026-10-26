/**
 * The settings model of the generic field renderer: controls per type,
 * dirty tracking, the PATCH /settings body and how its errors map back.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import {
	byKey,
	controlFor,
	dependency,
	dirtyPayload,
	errorsFrom,
	formatValue,
	idOf,
	isChange,
	isOn,
	onValue,
	recommendedText,
	undoPayload,
	withValues,
} from '../settings-model';
import { schema } from './fixture';

const entries = byKey( schema() );

describe( 'controlFor', () => {
	it.each( [
		[ { type: 'checkbox' }, 'switch' ],
		[ { type: 'text' }, 'text' ],
		[ { type: 'url' }, 'url' ],
		[ { type: 'number' }, 'number' ],
		[ { type: 'date' }, 'date' ],
		[ { type: 'textarea' }, 'textarea' ],
		[ { type: 'radio' }, 'segmented' ],
		[ { type: 'select', multiple: false }, 'select' ],
		[ { type: 'select', multiple: true }, 'tags' ],
		[ { type: 'text', secret: true }, 'secret' ],
		[ { type: 'url', secret: true }, 'secret' ],
		[ { type: 'html', key: 'regenerate_honeypot', html: '' }, 'action' ],
		[ { type: 'html', key: 'x_help', html: '<p>Hi</p>' }, 'help' ],
		[ { type: 'html', key: 'x_buttons', html: '' }, 'none' ],
		[ { type: 'mystery' }, 'text' ],
	] )( '%o renders as %s', ( entry, control ) => {
		expect( controlFor( entry ) ).toBe( control );
	} );
} );

describe( 'switch values', () => {
	it( 'is on with its first choice or true', () => {
		const entry = entries.verify_comments;
		expect( onValue( entry ) ).toBe( 'enabled' );
		expect( isOn( entry, 'enabled' ) ).toBe( true );
		expect( isOn( entry, true ) ).toBe( true );
		expect( isOn( entry, false ) ).toBe( false );
		expect( isOn( entry, 'disabled' ) ).toBe( false );
		expect( isOn( entry, '' ) ).toBe( false );
	} );
} );

describe( 'dirty tracking', () => {
	it( 'ignores a value equal to the saved one, however it is typed', () => {
		expect( isChange( entries.verify_comments, true ) ).toBe( false );
		expect( isChange( entries.verify_comments, false ) ).toBe( true );
		expect( isChange( entries.hide_wp_version, false ) ).toBe( false );
		expect( isChange( entries.zerospam_timeout, '5' ) ).toBe( false );
		expect( isChange( entries.zerospam_timeout, '6' ) ).toBe( true );
		expect( isChange( entries.comment_spam_message, 'No spam.' ) ).toBe(
			false
		);
	} );

	it( 'counts a secret only when typed, or removed while set', () => {
		expect( isChange( entries.zerospam_license, undefined ) ).toBe( false );
		expect( isChange( entries.zerospam_license, '' ) ).toBe( false );
		expect( isChange( entries.zerospam_license, 'KEY' ) ).toBe( true );
		expect( isChange( entries.ipstack_api, '' ) ).toBe( true );
	} );
} );

describe( 'dirtyPayload', () => {
	it( 'sends only changes, with switch and number values the route accepts', () => {
		const payload = dirtyPayload( entries, {
			verify_comments: false,
			disable_xmlrpc: false,
			hide_wp_version: 'enabled',
			zerospam_timeout: '12',
			comment_spam_message: 'No spam.',
			zs_widgets_mode: 'relaxed',
			ipstack_api: '',
			max_logs: 'abc',
		} );

		expect( payload ).toEqual( {
			verify_comments: false,
			// Off is sent as false; the server stores `disabled` for a default-on switch.
			disable_xmlrpc: false,
			hide_wp_version: 'enabled',
			zerospam_timeout: 12,
			zs_widgets_mode: 'relaxed',
			ipstack_api: '',
			// Not a number: sent as typed so the route explains what is wrong.
			max_logs: 'abc',
		} );
	} );

	it( 'never sends a locked setting or an html one', () => {
		const locked = byKey( {
			settings: [
				{ ...entries.verify_login, locked: true },
				entries.regenerate_honeypot,
			],
		} );
		expect(
			dirtyPayload( locked, {
				verify_login: true,
				regenerate_honeypot: 'x',
			} )
		).toEqual( {} );
	} );

	it( 'sends multi-select values as a list', () => {
		const tags = byKey( {
			settings: [
				{
					key: 'widget_visibility',
					type: 'select',
					multiple: true,
					options: [
						{ value: 'administrator', label: 'Administrator' },
						{ value: 'editor', label: 'Editor' },
					],
					value: [ 'administrator' ],
				},
			],
		} );
		expect(
			dirtyPayload( tags, { widget_visibility: [ 'administrator' ] } )
		).toEqual( {} );
		expect(
			dirtyPayload( tags, {
				widget_visibility: [ 'editor', 'administrator' ],
			} )
		).toEqual( { widget_visibility: [ 'editor', 'administrator' ] } );
	} );
} );

describe( 'undo and saved values', () => {
	it( 'keeps the saved values of what changed', () => {
		expect(
			undoPayload( entries, {
				verify_comments: false,
				hide_wp_version: 'enabled',
				zerospam_timeout: 9,
			} )
		).toEqual( {
			verify_comments: 'enabled',
			hide_wp_version: false,
			zerospam_timeout: 5,
		} );
	} );

	it( 'offers no undo when a secret changed', () => {
		expect( undoPayload( entries, { ipstack_api: 'new' } ) ).toBeNull();
	} );

	it( 'puts the values a save returned into the schema', () => {
		const next = withValues( schema(), {
			verify_comments: false,
			ipstack_api: { set: false, source: 'default' },
		} );
		const map = byKey( next );
		expect( map.verify_comments.value ).toBe( false );
		expect( map.ipstack_api.value ).toEqual( {
			set: false,
			source: 'default',
		} );
		expect( map.verify_login.value ).toBe( false );
	} );
} );

describe( 'errorsFrom', () => {
	it( 'maps invalid values to their fields, without the key prefix', () => {
		const result = errorsFrom( {
			code: 'rest_invalid_settings',
			message: 'One or more settings are invalid.',
			data: {
				status: 400,
				params: {
					zerospam_timeout:
						'Invalid value for "zerospam_timeout". Must be at most 30.',
				},
			},
		} );
		expect( result.fields ).toEqual( {
			zerospam_timeout: 'Must be at most 30.',
		} );
		expect( result.message ).toBe(
			'Nothing was saved. Check the 1 highlighted setting.'
		);
	} );

	it( 'maps locked settings', () => {
		const result = errorsFrom( {
			code: 'rest_setting_locked',
			data: { status: 403, locked_keys: [ 'verify_login' ] },
		} );
		expect( Object.keys( result.fields ) ).toEqual( [ 'verify_login' ] );
		expect( result.message ).toMatch(
			/locked by your network administrator/
		);
	} );

	it( 'falls back to the error message', () => {
		expect( errorsFrom( { message: 'Server down' } ) ).toEqual( {
			message: 'Server down',
			fields: {},
		} );
		expect( errorsFrom( undefined ).message ).toMatch(
			/could not be saved/
		);
	} );
} );

describe( 'texts', () => {
	it( 'formats values and recommendations', () => {
		expect( formatValue( entries.verify_comments, 'enabled' ) ).toBe(
			'On'
		);
		expect( formatValue( entries.zs_widgets_mode, 'relaxed' ) ).toBe(
			'Relaxed'
		);
		expect( formatValue( entries.comment_spam_message, '' ) ).toBe( '' );
		expect( recommendedText( entries.verify_comments ) ).toBe( 'On' );
		expect( recommendedText( entries.comment_spam_message ) ).toBe( '' );
		expect(
			recommendedText( {
				...entries.verify_comments,
				description: 'Recommended: keep it on.',
			} )
		).toBe( '' );
	} );

	it( 'describes a dependency', () => {
		const map = byKey( {
			settings: [
				entries.verify_comments,
				{
					...entries.comment_spam_message,
					depends_on: { key: 'verify_comments', value: 'enabled' },
				},
				{
					...entries.zs_widgets_mode,
					key: 'b',
					depends_on: { key: 'zs_widgets_mode', value: 'strict' },
				},
				entries.zs_widgets_mode,
			],
		} );
		expect( dependency( map.comment_spam_message, map ) ).toEqual( {
			title: 'Protect comments',
			value: '',
		} );
		expect( dependency( map.b, map ) ).toEqual( {
			title: 'Mode',
			value: 'Strict',
		} );
		expect( dependency( map.verify_comments, map ) ).toBeNull();
	} );

	it( 'makes IDs from any key', () => {
		expect( idOf( 'my plugin/key' ) ).toBe( 'my-plugin-key' );
	} );
} );
