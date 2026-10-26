/**
 * The Protection cards: one per section of a tab, built from the schema
 * and protection/modules, with built-in looks, generic third-party
 * sections, the honeypot card and inactive integrations.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { buildCards, cardStatus, detectionLine } from '../cards';
import { byKey } from '../../app/fields/settings-model';
import {
	basicLicense,
	enhancedLicense,
	modules,
	schema,
} from '../../app/fields/test/fixture';

const keys = ( list ) => list.map( ( c ) => c.key );
const saved = byKey( schema() );
const valueOf = ( key ) => saved[ key ]?.value;

describe( 'buildCards', () => {
	it( 'lists the detection cards in the prototype order, the honeypot included', () => {
		const cards = buildCards( schema(), modules(), 'detection' );
		expect( keys( cards ) ).toEqual( [
			'comments',
			'login',
			'davidwalsh',
			'honeypot',
			'security',
		] );
		const honeypot = cards.find( ( c ) => 'honeypot' === c.key );
		expect( honeypot.title ).toBe( 'Honeypot' );
		expect( keys( honeypot.visible ) ).toEqual( [ 'regenerate_honeypot' ] );
		expect( honeypot.fine ).toEqual( [] );
	} );

	it( 'shows the module switch and puts the rest behind Fine-tune', () => {
		const comments = buildCards( schema(), modules(), 'detection' )[ 0 ];
		expect( comments.title ).toBe( 'Comments' );
		expect( comments.main ).toEqual( [ 'verify_comments' ] );
		expect( keys( comments.visible ) ).toEqual( [ 'verify_comments' ] );
		expect( keys( comments.fine ) ).toEqual( [ 'comment_spam_message' ] );
		expect( comments.signals ).toEqual( [
			'honeypot',
			'email',
			'davidwalsh',
			'words',
		] );

		const dw = buildCards( schema(), modules(), 'detection' ).find(
			( c ) => 'davidwalsh' === c.key
		);
		expect( keys( dw.fine ) ).toEqual( [
			'davidwalsh_form_selectors',
			'davidwalsh_form_selectors_help',
		] );
	} );

	it( 'shows every Security switch, the two new ones first', () => {
		const security = buildCards( schema(), modules(), 'detection' ).find(
			( c ) => 'security' === c.key
		);
		expect( keys( security.visible ) ).toEqual( [
			'disable_xmlrpc',
			'hide_wp_version',
			'disable_emojis',
		] );
		expect( security.fine ).toEqual( [] );
	} );

	it( 'renders a third-party section like any other, after the built-in ones', () => {
		const cards = buildCards( schema(), modules(), 'integrations' );
		expect( keys( cards ) ).toEqual( [
			'wpforms',
			'zs_widgets',
			'contactform7',
		] );
		const widgets = cards[ 1 ];
		expect( widgets.title ).toBe( 'Widget Forms' );
		expect( widgets.icon ).toBe( 'form' );
		expect( keys( widgets.visible ) ).toEqual( [ 'verify_zs_widgets' ] );
		expect( keys( widgets.fine ) ).toEqual( [ 'zs_widgets_mode' ] );
		expect( widgets.inactive ).toBe( false );
	} );

	it( 'adds integrations whose plugin is not active, last', () => {
		const cf7 = buildCards( schema(), modules(), 'integrations' ).pop();
		expect( cf7 ).toMatchObject( {
			key: 'contactform7',
			title: 'Contact Form 7',
			inactive: true,
			notInstalled: true,
		} );
		expect( cf7.settings ).toEqual( [] );
	} );

	it( 'keeps the license key out of the Enhanced card rows and shows key fields', () => {
		const cards = buildCards( schema(), modules(), 'intelligence' );
		expect( keys( cards ) ).toEqual( [ 'zerospam', 'ipstack' ] );
		expect( keys( cards[ 0 ].settings ) ).not.toContain(
			'zerospam_license'
		);
		expect( keys( cards[ 0 ].visible ) ).toEqual( [ 'zerospam' ] );
		expect( keys( cards[ 1 ].visible ) ).toEqual( [ 'ipstack_api' ] );
		expect( keys( cards[ 1 ].fine ) ).toEqual( [ 'ipstack_cache' ] );
	} );

	it( 'copes with missing answers', () => {
		expect( buildCards( undefined, undefined, 'detection' ) ).toEqual( [] );
	} );
} );

describe( 'cardStatus', () => {
	const cards = ( tab ) => buildCards( schema(), modules(), tab );
	const card = ( tab, key ) => cards( tab ).find( ( c ) => key === c.key );

	it( 'says On, Off or how many switches are on', () => {
		expect(
			cardStatus( card( 'detection', 'comments' ), valueOf )
		).toEqual( {
			tone: 'ok',
			label: 'On',
		} );
		expect(
			cardStatus( card( 'detection', 'login' ), valueOf ).label
		).toBe( 'Off' );
		expect(
			cardStatus( card( 'detection', 'security' ), valueOf ).label
		).toBe( '1 of 3 on' );
	} );

	it( 'follows the drafts', () => {
		const draft = ( key ) =>
			'verify_login' === key ? 'enabled' : valueOf( key );
		expect( cardStatus( card( 'detection', 'login' ), draft ).label ).toBe(
			'On'
		);
	} );

	it( 'reports key services as connected or not set up', () => {
		expect(
			cardStatus( card( 'intelligence', 'ipstack' ), valueOf ).label
		).toBe( 'Connected' );
		const removed = ( key ) =>
			'ipstack_api' === key ? '' : valueOf( key );
		expect(
			cardStatus( card( 'intelligence', 'ipstack' ), removed ).label
		).toBe( 'Not set up' );
	} );

	it( 'reports the license on the Enhanced card', () => {
		const zerospam = card( 'intelligence', 'zerospam' );
		expect( cardStatus( zerospam, valueOf, basicLicense() ).label ).toBe(
			'No license key'
		);
		expect( cardStatus( zerospam, valueOf, enhancedLicense() ).label ).toBe(
			'Active'
		);
		expect(
			cardStatus( zerospam, valueOf, {
				...enhancedLicense(),
				status: 'invalid',
			} )
		).toEqual( { tone: 'danger', label: 'Key not valid' } );
	} );

	it( 'says when the plugin is not active, and nothing for the honeypot', () => {
		expect(
			cardStatus( card( 'integrations', 'contactform7' ), valueOf ).label
		).toBe( 'Plugin not active' );
		expect(
			cardStatus( card( 'detection', 'honeypot' ), valueOf )
		).toBeNull();
	} );
} );

describe( 'detectionLine', () => {
	const now = Date.parse( '2026-10-25T11:00:00Z' );
	const cards = buildCards( schema(), modules(), 'detection' );

	it( 'gives the last detection and the 7-day count', () => {
		expect( detectionLine( cards[ 0 ], now ) ).toBe(
			'Last detection 2 h ago · 14 in the last 7 days.'
		);
		expect( detectionLine( cards[ 1 ], now ) ).toBe(
			'No detections in the last 7 days.'
		);
	} );

	it( 'is empty for cards that do not detect', () => {
		const honeypot = cards.find( ( c ) => 'honeypot' === c.key );
		expect( detectionLine( honeypot, now ) ).toBe( '' );
		const intel = buildCards( schema(), modules(), 'intelligence' );
		expect( detectionLine( intel[ 1 ], now ) ).toBe( '' );
	} );
} );
