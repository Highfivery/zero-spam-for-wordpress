// @vitest-environment happy-dom

/**
 * Confirm: the Yes button takes focus, or Cancel when Yes could lock the
 * administrator out (a held Enter must not confirm that).
 */

/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

/**
 * Internal dependencies
 */
import { Confirm } from '../parts';

let container;
let root;

beforeEach( () => {
	globalThis.IS_REACT_ACT_ENVIRONMENT = true;
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

const render = ( props ) =>
	act( () =>
		root.render(
			<Confirm
				id="q"
				question="203.0.113.5 is your own address."
				yes="Block it anyway"
				onYes={ () => {} }
				onCancel={ () => {} }
				{ ...props }
			/>
		)
	);

describe( 'Confirm', () => {
	it( 'focuses Yes by default', () => {
		render( {} );
		expect( document.activeElement?.textContent ).toBe( 'Block it anyway' );
	} );

	it( 'focuses Cancel, described by the question, when safe', () => {
		render( { safe: true } );
		expect( document.activeElement?.textContent ).toBe( 'Cancel' );
		expect(
			document.activeElement?.getAttribute( 'aria-describedby' )
		).toBe( 'q' );
	} );
} );
