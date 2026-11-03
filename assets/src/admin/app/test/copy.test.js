// @vitest-environment happy-dom

/**
 * Copy on every site: the Clipboard API, then a hidden textarea with
 * execCommand( 'copy' ) (plain http:// admin, where navigator.clipboard does
 * not exist), then the text shown, selected and focused for Ctrl+C.
 */

/**
 * External dependencies
 */
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Internal dependencies
 */
import { execCopy, useCopy, writeClipboard } from '../copy';
import { FeedbackProvider } from '../primitives/feedback';

const REPORT = 'Zero Spam 6.0.0 · WordPress 6.9 · PHP 8.2 · Basic';

let container;
let root;
let originalExec;

/**
 * Replaces navigator.clipboard (undefined: no Clipboard API).
 *
 * @param {Object|undefined} clipboard Clipboard.
 */
const setClipboard = ( clipboard ) =>
	Object.defineProperty( window.navigator, 'clipboard', {
		value: clipboard,
		configurable: true,
	} );

/**
 * Replaces document.execCommand (undefined: not supported).
 *
 * @param {((command: string) => boolean)|undefined} exec execCommand.
 */
const setExec = ( exec ) =>
	Object.defineProperty( document, 'execCommand', {
		value: exec,
		configurable: true,
		writable: true,
	} );

/**
 * A Copy button with its fallback, as the system report renders it.
 */
function Harness() {
	const { copy, fallback } = useCopy();
	return (
		<div>
			<button
				type="button"
				id="copy"
				onClick={ () => copy( REPORT, 'System report' ) }
			>
				Copy system report
			</button>
			{ fallback }
		</div>
	);
}

/**
 * Renders the harness and clicks Copy, with the button focused as a click would.
 *
 * @return {Promise<HTMLButtonElement>} The button.
 */
async function clickCopy() {
	await act( async () => {
		root.render(
			<FeedbackProvider>
				<Harness />
			</FeedbackProvider>
		);
	} );
	const button = container.querySelector( '#copy' );
	button.focus();
	await act( async () => {
		button.click();
	} );
	return button;
}

beforeEach( () => {
	globalThis.IS_REACT_ACT_ENVIRONMENT = true;
	originalExec = Object.getOwnPropertyDescriptor( document, 'execCommand' );
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	setClipboard( undefined );
	if ( originalExec ) {
		Object.defineProperty( document, 'execCommand', originalExec );
	} else {
		delete document.execCommand;
	}
	vi.restoreAllMocks();
} );

describe( 'writeClipboard', () => {
	it( 'uses the Clipboard API when it works', async () => {
		const writeText = vi.fn( () => Promise.resolve() );
		const exec = vi.fn( () => true );
		setClipboard( { writeText } );
		setExec( exec );

		await expect( writeClipboard( REPORT ) ).resolves.toBe( true );
		expect( writeText ).toHaveBeenCalledWith( REPORT );
		expect( exec ).not.toHaveBeenCalled();
	} );

	it( 'copies from a selected hidden textarea when there is no Clipboard API (http://)', async () => {
		setClipboard( undefined );
		const outside = document.createElement( 'button' );
		document.body.appendChild( outside );
		outside.focus();

		let seen = null;
		setExec(
			vi.fn( ( command ) => {
				const active = document.activeElement;
				seen = {
					command,
					tag: active.tagName,
					value: active.value,
					selected: active.value.slice(
						active.selectionStart,
						active.selectionEnd
					),
					hidden: active.getAttribute( 'aria-hidden' ),
				};
				return true;
			} )
		);

		await expect( writeClipboard( REPORT ) ).resolves.toBe( true );
		expect( seen ).toEqual( {
			command: 'copy',
			tag: 'TEXTAREA',
			value: REPORT,
			selected: REPORT,
			hidden: 'true',
		} );
		// The helper field is gone and focus is back where it was.
		expect( document.querySelectorAll( 'textarea' ) ).toHaveLength( 0 );
		expect( document.activeElement ).toBe( outside );
		outside.remove();
	} );

	it( 'falls back to execCommand when the Clipboard API rejects', async () => {
		const exec = vi.fn( () => true );
		setClipboard( {
			writeText: () => Promise.reject( new Error( 'NotAllowedError' ) ),
		} );
		setExec( exec );

		await expect( writeClipboard( REPORT ) ).resolves.toBe( true );
		expect( exec ).toHaveBeenCalledWith( 'copy' );
	} );

	it( 'reports a failure when execCommand fails, throws or is missing', async () => {
		setClipboard( undefined );

		setExec( () => false );
		await expect( writeClipboard( REPORT ) ).resolves.toBe( false );

		setExec( () => {
			throw new Error( 'SecurityError' );
		} );
		expect( execCopy( REPORT ) ).toBe( false );

		setExec( undefined );
		await expect( writeClipboard( REPORT ) ).resolves.toBe( false );
		expect( document.querySelectorAll( 'textarea' ) ).toHaveLength( 0 );
	} );
} );

describe( 'useCopy', () => {
	it( 'says it copied and shows no field when the copy works', async () => {
		setClipboard( undefined );
		setExec( () => true );

		await clickCopy();

		expect( container.querySelector( '.zs-toasts' ).textContent ).toContain(
			'Copied to clipboard'
		);
		expect( container.querySelector( '.zs-copyfb' ) ).toBeNull();
	} );

	it.each( [
		[ 'no Clipboard API and execCommand fails', undefined, () => false ],
		[
			'the Clipboard API rejects and execCommand throws',
			{ writeText: () => Promise.reject( new Error( 'denied' ) ) },
			() => {
				throw new Error( 'denied' );
			},
		],
		[ 'neither exists', undefined, undefined ],
	] )(
		'shows the text selected and focused when %s',
		async ( _name, clipboard, exec ) => {
			setClipboard( clipboard );
			setExec( exec );

			const button = await clickCopy();

			const field = container.querySelector( '.zs-copyfb textarea' );
			expect( field ).not.toBeNull();
			expect( field.value ).toBe( REPORT );
			expect( field.readOnly ).toBe( true );
			// Labelled, and described by the instruction.
			const label = container.querySelector(
				`label[for="${ field.id }"]`
			);
			expect( label.textContent ).toBe( 'System report' );
			expect(
				document.getElementById(
					field.getAttribute( 'aria-describedby' )
				).textContent
			).toContain( 'Ctrl+C or Cmd+C' );
			// Focused, with all of the text selected, so Ctrl+C copies it.
			expect( document.activeElement ).toBe( field );
			expect( field.selectionStart ).toBe( 0 );
			expect( field.selectionEnd ).toBe( REPORT.length );
			expect(
				container.querySelector( '.zs-toasts' ).textContent
			).toContain( 'The text is selected below' );

			// Hide removes it and returns focus to the Copy button.
			const hide = [ ...container.querySelectorAll( 'button' ) ].find(
				( b ) => 'Hide' === b.textContent
			);
			await act( async () => {
				hide.click();
			} );
			expect( container.querySelector( '.zs-copyfb' ) ).toBeNull();
			expect( document.activeElement ).toBe( button );
		}
	);
} );
