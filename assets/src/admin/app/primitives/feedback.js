/**
 * Feedback: toasts (with Undo), the screen reader live regions and the
 * unsaved-changes bar.
 *
 * - Toasts stack bottom centre, above the unsaved-changes bar while it is
 *   shown and beside an open drawer (prototype v17). Their timer pauses
 *   while the pointer is over them or focus is inside (WCAG 2.2.1). They
 *   sit in a polite live region that exists from the first render.
 * - announce() speaks a message without showing anything.
 * - The unsaved-changes bar holds Save and Discard for edits not saved yet.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button } from './index';
import {
	createToastTimer,
	TOAST_DURATION,
	UNDO_DURATION,
} from '../toast-timer';

const FeedbackContext = createContext( {
	toast: () => {},
	announce: () => {},
	setUnsaved: () => {},
} );

/** @return {{toast: (message: string, options?: Object) => number, announce: (message: string, level?: string) => void}} Feedback API. */
export const useFeedback = () => useContext( FeedbackContext );

let nextId = 1;

/**
 * One toast.
 *
 * @param {Object}                          props         Props.
 * @param {Object}                          props.toast   { id, message, undo, undoLabel }.
 * @param {(...args: unknown[]) => unknown} props.onClose Removes it.
 */
function Toast( { toast, onClose } ) {
	const timer = useRef( null );

	useEffect( () => {
		timer.current = createToastTimer( {
			duration: toast.undo ? UNDO_DURATION : TOAST_DURATION,
			onExpire: onClose,
		} );
		timer.current.start();
		return () => timer.current.cancel();
		// eslint-disable-next-line react-hooks/exhaustive-deps -- One timer per toast.
	}, [] );

	const hold = () => timer.current?.pause();
	const release = () => timer.current?.resume();

	return (
		<div
			className="zs-toast"
			onMouseEnter={ hold }
			onMouseLeave={ release }
			onFocus={ hold }
			onBlur={ release }
		>
			<span>{ toast.message }</span>
			{ toast.undo && (
				<button
					type="button"
					className="zs-toast-btn"
					onClick={ () => {
						timer.current?.cancel();
						onClose();
						toast.undo();
					} }
				>
					{ toast.undoLabel || __( 'Undo', 'zero-spam' ) }
				</button>
			) }
			<button
				type="button"
				className="zs-toast-x"
				aria-label={ __( 'Dismiss message', 'zero-spam' ) }
				onClick={ () => {
					timer.current?.cancel();
					onClose();
				} }
			>
				<svg
					className="zs-icon zs-icon-sm"
					aria-hidden="true"
					focusable="false"
				>
					<use href="#zs-i-close" />
				</svg>
			</button>
		</div>
	);
}

/**
 * The unsaved-changes bar.
 *
 * @param {Object}                          props           Props.
 * @param {string}                          [props.message] Text.
 * @param {(...args: unknown[]) => unknown} props.onSave    Save.
 * @param {(...args: unknown[]) => unknown} props.onDiscard Discard.
 * @param {boolean}                         [props.saving]  Saving now.
 */
export function UnsavedBar( { message, onSave, onDiscard, saving = false } ) {
	const bar = useRef( null );

	// Toasts sit 12px above the bar wherever it is (sticky at the bottom,
	// or higher up at the end of a short page).
	useEffect( () => {
		const app = document.querySelector( '.zs-app' );
		let frame = 0;
		const sync = () => {
			frame = 0;
			const top =
				bar.current?.getBoundingClientRect().top ?? window.innerHeight;
			app?.style.setProperty(
				'--zs-savebar-offset',
				`${ Math.max( 24, window.innerHeight - top + 12 ) }px`
			);
		};
		const queue = () => {
			if ( ! frame ) {
				frame = window.requestAnimationFrame( sync );
			}
		};
		app?.classList.add( 'zs-has-savebar' );
		sync();
		window.addEventListener( 'resize', queue );
		window.addEventListener( 'scroll', queue, { passive: true } );
		return () => {
			window.removeEventListener( 'resize', queue );
			window.removeEventListener( 'scroll', queue );
			window.cancelAnimationFrame( frame );
			app?.classList.remove( 'zs-has-savebar' );
		};
	}, [] );

	return (
		<form
			ref={ bar }
			className="zs-savebar"
			aria-label={ __( 'Unsaved changes', 'zero-spam' ) }
			onSubmit={ ( event ) => {
				event.preventDefault();
				onSave();
			} }
		>
			<span aria-live="polite">
				{ message || __( 'You have unsaved changes.', 'zero-spam' ) }
			</span>
			<span className="zs-inline-row">
				<Button
					variant="secondary"
					size="sm"
					onClick={ onDiscard }
					disabled={ saving }
				>
					{ __( 'Discard', 'zero-spam' ) }
				</Button>
				<Button size="sm" type="submit" disabled={ saving }>
					{ saving
						? __( 'Saving…', 'zero-spam' )
						: __( 'Save changes', 'zero-spam' ) }
				</Button>
			</span>
		</form>
	);
}

/**
 * Provides toast(), announce() and the live regions.
 *
 * @param {Object}  props          Props.
 * @param {unknown} props.children App.
 */
export function FeedbackProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );
	const [ polite, setPolite ] = useState( '' );
	const [ assertive, setAssertive ] = useState( '' );

	const remove = useCallback(
		( id ) => setToasts( ( list ) => list.filter( ( t ) => t.id !== id ) ),
		[]
	);

	/**
	 * Shows a toast.
	 *
	 * @param {string}                          message             Message.
	 * @param {Object}                          [options]           Options.
	 * @param {(...args: unknown[]) => unknown} [options.undo]      Undo callback (adds an Undo button).
	 * @param {string}                          [options.undoLabel] Undo button label.
	 */
	const toast = useCallback( ( message, options = {} ) => {
		const id = nextId++;
		setToasts( ( list ) => [
			...list.slice( -2 ),
			{ id, message, ...options },
		] );
		return id;
	}, [] );

	const announce = useCallback( ( message, level = 'polite' ) => {
		const set = 'assertive' === level ? setAssertive : setPolite;
		// Clear first, so the same message is spoken again.
		set( '' );
		window.setTimeout( () => set( message ), 50 );
	}, [] );

	const api = useMemo( () => ( { toast, announce } ), [ toast, announce ] );

	return (
		<FeedbackContext.Provider value={ api }>
			{ children }
			<div className="zs-toasts" role="status" aria-live="polite">
				{ toasts.map( ( t ) => (
					<Toast
						key={ t.id }
						toast={ t }
						onClose={ () => remove( t.id ) }
					/>
				) ) }
			</div>
			<div
				className="zs-sr-only"
				aria-live="polite"
				data-zs-live="polite"
			>
				{ polite }
			</div>
			<div
				className="zs-sr-only"
				aria-live="assertive"
				data-zs-live="assertive"
			>
				{ assertive }
			</div>
		</FeedbackContext.Provider>
	);
}
