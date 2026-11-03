// @vitest-environment happy-dom

/**
 * After a refused license key, focus returns to the key field once the
 * field is enabled again, even when a frame fires before React re-renders
 * (the race seen under CPU throttling: focus() on the still-disabled field
 * left focus on the body).
 */

/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

let reject;
vi.mock( '../../data/api', () => ( {
	NS: '/zero-spam/v1',
	request: () =>
		new Promise( ( resolve, rejectRequest ) => {
			reject = rejectRequest;
		} ),
} ) );
vi.mock( '../../app/context', () => ( {
	useApp: () => ( { setLicense() {}, modulesState: {} } ),
} ) );
vi.mock( '../../app/compare', () => ( { roadmap: () => [] } ) );

/**
 * Internal dependencies
 */
import { KeyForm } from '../license';
import { FeedbackProvider } from '../../app/primitives/feedback';

let container;
let root;

beforeEach( () => {
	globalThis.IS_REACT_ACT_ENVIRONMENT = true;
	// A frame that fires at once, before React commits the update the catch handler queued.
	vi.spyOn( window, 'requestAnimationFrame' ).mockImplementation( ( cb ) => {
		cb( 0 );
		return 1;
	} );
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	vi.restoreAllMocks();
} );

/**
 * Types into a React-controlled input.
 *
 * @param {HTMLInputElement} input Input.
 * @param {string}           value Value.
 */
const type = ( input, value ) => {
	Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set.call( input, value );
	input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
};

describe( 'KeyForm focus after a refused key', () => {
	it( 'is disabled while checking, then focuses the enabled field', async () => {
		await act( async () => {
			root.render(
				<FeedbackProvider>
					<KeyForm focus={ false } onSaved={ () => {} } />
				</FeedbackProvider>
			);
		} );
		const input = container.querySelector( '#zs-license-key' );
		const form = container.querySelector( 'form' );

		await act( async () => {
			type( input, 'e2e-invalid' );
		} );
		await act( async () => {
			form.dispatchEvent(
				new window.Event( 'submit', {
					bubbles: true,
					cancelable: true,
				} )
			);
		} );

		// busy: the field is disabled while the key is checked.
		expect( input.disabled ).toBe( true );

		await act( async () => {
			reject( {
				code: 'rest_invalid_param',
				data: { params: { license_key: 'The key is invalid.' } },
			} );
		} );

		// busy → false: enabled, focused, and described by the error.
		expect( input.disabled ).toBe( false );
		expect( document.activeElement ).toBe( input );
		expect( input.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect(
			container.querySelector( '#zs-license-e' ).textContent
		).toContain( 'The key is invalid.' );
	} );
} );
