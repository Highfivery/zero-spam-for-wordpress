/**
 * Copy to the clipboard, on every site.
 *
 * The Clipboard API only exists on secure origins (https:// or localhost),
 * and many sites run their admin over plain http://. So a copy tries the
 * Clipboard API, then a hidden textarea and `document.execCommand( 'copy' )`
 * and, when both fail, shows the text in a visible, labelled, read-only
 * field with the text selected and focused, so "press Ctrl+C or Cmd+C" is
 * something the person can do.
 *
 * Used by every Copy button of the admin app (Copy system report on the
 * Overview, Settings › Debug and Help; Help's code and reference lists).
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button } from './primitives';
import { useFeedback } from './primitives/feedback';
import { focusIfEnabled, useFocusAfterRender } from './use-focus-after-render';

/**
 * Copies with a hidden, selectable textarea and `execCommand( 'copy' )`.
 *
 * Focus and the page's selection are put back afterwards.
 *
 * @param {string}   text Text.
 * @param {Document} doc  Document.
 * @return {boolean} Whether the browser says it copied.
 */
export function execCopy( text, doc = window.document ) {
	if ( ! doc?.body || 'function' !== typeof doc.execCommand ) {
		return false;
	}

	const area = doc.createElement( 'textarea' );
	area.value = text;
	area.setAttribute( 'readonly', '' );
	area.setAttribute( 'aria-hidden', 'true' );
	area.tabIndex = -1;
	// Off screen but rendered: a hidden (display: none) field cannot be selected.
	Object.assign( area.style, {
		position: 'fixed',
		top: '0',
		left: '-9999px',
		width: '1px',
		height: '1px',
	} );

	const active = doc.activeElement;
	const selection = doc.getSelection?.();
	const range =
		selection && selection.rangeCount > 0
			? selection.getRangeAt( 0 )
			: null;

	doc.body.appendChild( area );
	let copied = false;
	try {
		area.focus( { preventScroll: true } );
		area.select();
		area.setSelectionRange( 0, text.length );
		copied = true === doc.execCommand( 'copy' );
	} catch {
		copied = false;
	} finally {
		area.remove();
		if ( active && 'function' === typeof active.focus ) {
			active.focus( { preventScroll: true } );
		}
		if ( range && selection ) {
			selection.removeAllRanges();
			selection.addRange( range );
		}
	}
	return copied;
}

/**
 * Writes text to the clipboard: the Clipboard API, else execCopy().
 *
 * @param {string} text Text.
 * @param {Window} win  Window.
 * @return {Promise<boolean>} Whether it was copied.
 */
export async function writeClipboard( text, win = window ) {
	const clipboard = win.navigator?.clipboard;
	if ( clipboard && 'function' === typeof clipboard.writeText ) {
		try {
			await clipboard.writeText( text );
			return true;
		} catch {
			// Refused (no permission, document not focused): try the old way.
		}
	}
	return execCopy( text, win.document );
}

let fallbackIds = 0;

/**
 * The text a copy could not put on the clipboard, selected for Ctrl+C.
 *
 * @param {Object}     props        Props.
 * @param {Object}     props.field  Ref to the textarea.
 * @param {string}     props.text   Text.
 * @param {string}     props.label  What the text is.
 * @param {() => void} props.onHide Hides it.
 */
function CopyFallback( { field, text, label, onHide } ) {
	const [ id ] = useState( () => `zs-copyfb-${ ++fallbackIds }` );
	return (
		<div className="zs-copyfb">
			<label className="zs-copyfb-l" htmlFor={ id }>
				{ label }
			</label>
			<p className="zs-hint" id={ `${ id }-d` }>
				{ __(
					'The text is selected. Press Ctrl+C or Cmd+C to copy it.',
					'zero-spam'
				) }
			</p>
			<textarea
				ref={ field }
				id={ id }
				className="zs-input zs-mono zs-copyfb-t"
				aria-describedby={ `${ id }-d` }
				readOnly
				rows={ 4 }
				value={ text }
				onFocus={ ( event ) => event.target.select() }
			/>
			<div>
				<Button variant="ghost" size="sm" onClick={ onHide }>
					{ __( 'Hide', 'zero-spam' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * A copy action for one place on the page, with its fallback field.
 *
 * Render `fallback` next to the Copy button: it is null until a copy fails.
 *
 * @return {{copy: (text: string, label?: string) => Promise<boolean>, fallback: Element|null}} Copy and its fallback.
 */
export function useCopy() {
	const { toast } = useFeedback();
	const [ shown, setShown ] = useState( null );
	const field = useRef( null );
	const opener = useRef( null );
	const focusAfterRender = useFocusAfterRender();

	const copy = useCallback(
		( text, label ) => {
			const from = window.document.activeElement;
			return writeClipboard( text ).then( ( copied ) => {
				if ( copied ) {
					setShown( null );
					toast( __( 'Copied to clipboard', 'zero-spam' ) );
					return true;
				}
				opener.current = from;
				setShown( {
					text,
					label: label || __( 'Text to copy', 'zero-spam' ),
				} );
				focusAfterRender( () => {
					const element = field.current;
					if ( ! focusIfEnabled( element ) ) {
						return false;
					}
					element.select();
					return true;
				} );
				toast(
					__(
						'Copy did not work. The text is selected below: press Ctrl+C or Cmd+C.',
						'zero-spam'
					)
				);
				return false;
			} );
		},
		[ toast, focusAfterRender ]
	);

	const hide = useCallback( () => {
		setShown( null );
		const back = opener.current;
		opener.current = null;
		if ( back?.isConnected && 'function' === typeof back.focus ) {
			back.focus();
		}
	}, [] );

	const fallback = shown ? (
		<CopyFallback
			field={ field }
			text={ shown.text }
			label={ shown.label }
			onHide={ hide }
		/>
	) : null;

	return { copy, fallback };
}
