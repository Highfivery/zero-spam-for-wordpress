// @vitest-environment happy-dom

/**
 * useFocusAfterRender(): a focus request made with a state change runs once
 * that change is committed (a control enabled, a tab shown), and falls back
 * when the target cannot take focus.
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
import { useLayoutEffect, useState } from '@wordpress/element';
import { focusIfEnabled, useFocusAfterRender } from '../use-focus-after-render';

let container;
let root;
let api;

/**
 * A field disabled while busy, and a second "tab" shown on demand.
 */
function Probe() {
	const [ busy, setBusy ] = useState( true );
	const [ tab, setTab ] = useState( 'a' );
	const [ fell, setFell ] = useState( false );
	const focusAfterRender = useFocusAfterRender();
	api = {
		// Outside any event handler, as in a promise callback.
		finish: () => {
			setBusy( false );
			focusAfterRender( () =>
				focusIfEnabled( document.getElementById( 'field' ) )
			);
		},
		showB: () => {
			setTab( 'b' );
			focusAfterRender( () =>
				focusIfEnabled( document.getElementById( 'in-b' ) )
			);
		},
		missing: () =>
			focusAfterRender(
				() => focusIfEnabled( document.getElementById( 'nope' ) ),
				() => setFell( true )
			),
	};
	return (
		<div>
			<input id="field" disabled={ busy } />
			{ 'b' === tab && <input id="in-b" /> }
			{ fell && <span id="fell" /> }
		</div>
	);
}

beforeEach( () => {
	globalThis.IS_REACT_ACT_ENVIRONMENT = true;
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	act( () => root.render( <Probe /> ) );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

describe( 'useFocusAfterRender', () => {
	it( 'focuses a control once it is enabled', async () => {
		await act( async () => {
			await Promise.resolve();
			api.finish();
		} );
		expect( document.activeElement?.id ).toBe( 'field' );
	} );

	it( 'focuses a control that only exists after the change', async () => {
		await act( async () => {
			await Promise.resolve();
			api.showB();
		} );
		expect( document.activeElement?.id ).toBe( 'in-b' );
	} );

	it( 'falls back when the target cannot take focus', async () => {
		await act( async () => {
			api.missing();
		} );
		expect( container.querySelector( '#fell' ) ).not.toBeNull();
	} );

	it( 'waits for its own change when asked between a commit and that commit’s effects', async () => {
		// A server answer can arrive after React committed an earlier update
		// (the busy flag cleared) but before that commit's effects ran. The
		// earlier commit must not take the request: its target is not on
		// screen yet, so the fallback would move focus elsewhere.
		let fellBack = false;
		let request = null;
		function Late() {
			const [ phase, setPhase ] = useState( 0 );
			const [ shown, setShown ] = useState( false );
			const focusAfterRender = useFocusAfterRender();
			request = () => setPhase( 1 );
			useLayoutEffect( () => {
				if ( 1 === phase ) {
					setShown( true );
					focusAfterRender(
						() =>
							focusIfEnabled(
								document.getElementById( 'late-target' )
							),
						() => {
							fellBack = true;
						}
					);
				}
			}, [ phase, focusAfterRender ] );
			return shown ? <button id="late-target">Go</button> : null;
		}
		const host = document.createElement( 'div' );
		document.body.appendChild( host );
		const lateRoot = createRoot( host );
		act( () => lateRoot.render( <Late /> ) );
		await act( async () => {
			await Promise.resolve();
			request();
		} );
		expect( fellBack ).toBe( false );
		expect( document.activeElement?.id ).toBe( 'late-target' );
		act( () => lateRoot.unmount() );
		host.remove();
	} );

	it( 'focusIfEnabled refuses a disabled control', () => {
		const input = document.getElementById( 'field' );
		expect( focusIfEnabled( input ) ).toBe( false );
		expect( focusIfEnabled( null ) ).toBe( false );
	} );
} );
