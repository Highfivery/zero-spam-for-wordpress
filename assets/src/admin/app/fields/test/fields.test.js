/**
 * The generic field renderer: every schema type gets its control, labelled
 * by the setting's title and described by its description, status, lock
 * and error.
 */

/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';
import { renderToStaticMarkup } from 'react-dom/server';

/**
 * Internal dependencies
 */
import { Field, sameTitle } from '../fields';
import { byKey } from '../settings-model';
import { schema } from './fixture';

const entries = byKey( schema() );

const render = ( entry, props = {} ) =>
	renderToStaticMarkup(
		<Field
			entry={ entry }
			value={ entry.secret ? undefined : entry.value }
			onChange={ () => {} }
			entries={ entries }
			{ ...props }
		/>
	);

/**
 * The attributes of the first element matching a tag and an attribute.
 *
 * @param {string} html Markup.
 * @param {string} tag  Tag name.
 * @return {string} The element's opening tag.
 */
const tagOf = ( html, tag ) => {
	const match = new RegExp( `<${ tag }\\b[^>]*>` ).exec( html );
	return match ? match[ 0 ] : '';
};

describe( 'Field', () => {
	it( 'renders a checkbox as a labelled switch with its status', () => {
		const html = render( entries.verify_comments );
		const sw = tagOf( html, 'button' );
		expect( sw ).toContain( 'role="switch"' );
		expect( sw ).toContain( 'aria-checked="true"' );
		expect( sw ).toContain( 'aria-labelledby="zs-t-verify_comments"' );
		expect( sw ).toContain( 'aria-describedby="zs-s-verify_comments"' );
		expect( html ).toContain(
			'id="zs-t-verify_comments">Protect comments<'
		);
		expect( html ).toContain( 'id="zs-s-verify_comments">On<' );
		expect( html ).toContain( 'Recommended: On' );
	} );

	it( 'shows a default-on switch stored as disabled as off', () => {
		const sw = tagOf( render( entries.hide_wp_version ), 'button' );
		expect( sw ).toContain( 'aria-checked="false"' );
	} );

	it( 'renders text with a label, its placeholder and description', () => {
		const html = render( {
			...entries.davidwalsh_form_selectors,
			description:
				'Separate selectors with commas, for example <code>.a, #b</code>.',
		} );
		expect( html ).toContain(
			'<label class="zs-st-t" id="zs-t-davidwalsh_form_selectors" for="zs-f-davidwalsh_form_selectors">'
		);
		const input = tagOf( html, 'input' );
		expect( input ).toContain( 'type="text"' );
		expect( input ).toContain( 'placeholder=".my-custom-form"' );
		expect( input ).toContain(
			'aria-describedby="zs-d-davidwalsh_form_selectors"'
		);
		expect( html ).toContain( '<code>.a, #b</code>' );
	} );

	it.each( [
		[ 'url', 'type="url"' ],
		[ 'date', 'type="date"' ],
	] )( 'renders %s as an input of its type', ( type, attr ) => {
		const html = render( {
			...entries.comment_spam_message,
			type,
			value: '',
		} );
		expect( tagOf( html, 'input' ) ).toContain( attr );
	} );

	it( 'renders numbers with their limits', () => {
		const input = tagOf( render( entries.zerospam_timeout ), 'input' );
		expect( input ).toContain( 'type="number"' );
		expect( input ).toContain( 'min="1"' );
		expect( input ).toContain( 'max="30"' );
		expect( input ).toContain( 'value="5"' );
	} );

	it( 'renders a textarea', () => {
		const html = render( {
			...entries.comment_spam_message,
			type: 'textarea',
			value: 'a\nb',
		} );
		expect( html ).toContain( '<textarea' );
		expect( html ).toContain( 'id="zs-f-comment_spam_message"' );
	} );

	it( 'renders a radio as a segmented radio group', () => {
		const html = render( entries.zs_widgets_mode );
		expect( html ).toContain( 'role="radiogroup"' );
		expect( html ).toContain( 'aria-labelledby="zs-t-zs_widgets_mode"' );
		expect( ( html.match( /type="radio"/g ) || [] ).length ).toBe( 2 );
		expect( html ).toMatch( /checked="" value="strict"/ );
		expect( html ).toContain( '>Relaxed<' );
	} );

	it( 'renders a single select and a multi-select tag picker', () => {
		const options = [
			{ value: 'administrator', label: 'Administrator' },
			{ value: 'editor', label: 'Editor' },
		];
		const single = render( {
			...entries.comment_spam_message,
			type: 'select',
			options,
			value: 'editor',
		} );
		expect( single ).toContain( '<select' );
		expect( single ).toMatch(
			/<option value="editor" selected="">Editor<\/option>/
		);

		const multi = render( {
			...entries.comment_spam_message,
			type: 'select',
			multiple: true,
			options,
			value: [ 'administrator' ],
		} );
		expect( multi ).toContain( 'role="group"' );
		expect( multi ).toContain( 'aria-label="Remove Administrator"' );
		expect( multi ).toContain( 'aria-label="Add to Message"' );
		expect( multi ).toContain( '<option value="editor">Editor</option>' );
		expect( multi ).not.toContain( '<option value="administrator">' );
	} );

	it( 'shows a saved secret masked, with Replace and Remove', () => {
		const html = render( entries.ipstack_api );
		expect( html ).toContain( '••••a1b2' );
		expect( html ).toContain( 'aria-label="Replace API Key"' );
		expect( html ).toContain( 'aria-label="Remove API Key"' );
		expect( html ).not.toContain( 'type="password"' );
	} );

	it( 'asks for a secret that is not set in a password field', () => {
		const html = render( entries.zerospam_license );
		const input = tagOf( html, 'input' );
		expect( input ).toContain( 'type="password"' );
		expect( input.toLowerCase() ).toContain( 'autocomplete="off"' );
		expect( html ).toContain( 'aria-pressed="false"' );
	} );

	it( 'shows a secret being removed', () => {
		const html = render( entries.ipstack_api, { value: '' } );
		expect( html ).toContain( 'Removed when you save.' );
		expect( html ).toContain( 'Keep it' );
	} );

	it( 'does not offer to change a key set in wp-config.php', () => {
		const html = render( {
			...entries.ipstack_api,
			value: { set: true, source: 'constant' },
		} );
		expect( html ).toContain( 'Set in wp-config.php' );
		expect( html ).not.toContain( 'Replace' );
	} );

	it( 'renders html help as a disclosure and actions as buttons', () => {
		const help = render( entries.davidwalsh_form_selectors_help );
		expect( help ).toMatch( /^<details class="zs-help"/ );
		expect( help ).toContain(
			'<ol><li>Right-click on your form.</li></ol>'
		);

		const action = render( entries.regenerate_honeypot );
		expect( action ).toContain( 'Regenerate honeypot ID' );
		expect( action ).toContain( 'role="status"' );
		expect( action ).toContain( '<code>abc12</code>' );
	} );

	it( 'disables a locked setting and says why', () => {
		const html = render( {
			...entries.verify_login,
			locked: true,
			source: 'locked',
		} );
		const sw = tagOf( html, 'button' );
		expect( sw ).toContain( 'disabled=""' );
		expect( sw ).toContain( 'zs-l-verify_login' );
		expect( html ).toContain( 'Locked by network' );
		expect( html ).toContain(
			'Locked by the network administrator. This setting cannot be changed on this site.'
		);
	} );

	it( 'shows the network default a site follows', () => {
		const html = render( {
			...entries.zerospam_timeout,
			source: 'network',
			value: 8,
		} );
		expect( html ).toContain( 'Using the network default: 8' );
		const empty = render( {
			...entries.comment_spam_message,
			source: 'network',
			value: '',
		} );
		expect( empty ).toContain( 'Using the network default.' );
	} );

	it( 'marks an error and describes the control with it', () => {
		const html = render( entries.zerospam_timeout, {
			error: 'Must be at most 30.',
		} );
		const input = tagOf( html, 'input' );
		expect( input ).toContain( 'aria-invalid="true"' );
		expect( input ).toContain( 'zs-e-zerospam_timeout' );
		expect( html ).toContain( 'id="zs-e-zerospam_timeout"' );
		expect( html ).toContain( 'Must be at most 30.' );
	} );

	it( 'says when a setting applies', () => {
		const html = render( {
			...entries.comment_spam_message,
			depends_on: { key: 'verify_comments', value: 'enabled' },
		} );
		expect( html ).toContain( 'Applies when “Protect comments” is on.' );
	} );

	it( 'clamps a long description behind More', () => {
		const html = render( {
			...entries.comment_spam_message,
			description: 'x '.repeat( 120 ),
		} );
		expect( html ).toContain( 'zs-clamp' );
		expect( html ).toContain( 'aria-expanded="false"' );
		expect( html ).toContain( 'aria-controls="zs-d-comment_spam_message"' );
	} );

	it( 'keeps a title that repeats its card title for screen readers only', () => {
		const entry = { ...entries.verify_comments, title: 'Dashboard Widget' };
		const html = render( entry, { cardTitle: 'Dashboard widget' } );
		expect( html ).toContain(
			'class="zs-st-t zs-sr-only" id="zs-t-verify_comments">Dashboard Widget<'
		);
		expect( tagOf( html, 'button' ) ).toContain(
			'aria-labelledby="zs-t-verify_comments"'
		);
		expect( render( entry, { cardTitle: 'Dashboard' } ) ).not.toContain(
			'zs-sr-only" id="zs-t-'
		);
		expect( sameTitle( '  Zero Spam News ', 'Zero Spam news' ) ).toBe(
			true
		);
		expect( sameTitle( 'Zero Spam News', '' ) ).toBe( false );
	} );
} );
