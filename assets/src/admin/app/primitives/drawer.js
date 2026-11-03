/**
 * The drawer: a modal panel from the right edge (full width on phones).
 *
 * Modal in every way (prototype v14): a scrim behind it, aria-modal, the
 * rest of the app inert, focus moved in on open and trapped inside, Escape
 * and the scrim close it, and focus returns to the control that opened it.
 * Optional previous/next controls (Alt+Up / Alt+Down) for lists.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createPortal, useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, cx } from './index';

const FOCUSABLE =
	'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),summary,[tabindex]:not([tabindex="-1"])';

/**
 * The elements Tab can reach inside a container, in order.
 *
 * @param {Element} root Container.
 * @return {Element[]} Focusable elements.
 */
export function focusables( root ) {
	return Array.from( root.querySelectorAll( FOCUSABLE ) ).filter(
		( el ) =>
			el.offsetParent !== null || el === root.ownerDocument.activeElement
	);
}

/**
 * @param {Object}                          props               Props.
 * @param {boolean}                         props.open          Shown.
 * @param {(...args: unknown[]) => unknown} props.onClose       Called by Escape, the scrim and the close button.
 * @param {string}                          props.labelledBy    ID of the drawer's heading.
 * @param {boolean}                         [props.wide]        760px instead of 440px.
 * @param {string}                          [props.className]   Extra classes.
 * @param {unknown}                         [props.header]      Header content (left of the close button); omit for a floating close button.
 * @param {unknown}                         [props.footer]      Footer content.
 * @param {Object}                          [props.nav]         { label, onPrev, onNext, prevLabel, nextLabel, groupLabel } for previous/next (an action is missing at either end).
 * @param {(...args: unknown[]) => unknown} [props.returnFocus] Returns the element focus goes back to on close (the row now shown, after previous/next); the control that opened the drawer when it returns nothing.
 * @param {string}                          [props.busy]        aria-busy while the body loads.
 * @param {unknown}                         props.children      Body.
 */
export function Drawer( {
	open,
	onClose,
	labelledBy,
	wide = false,
	className,
	header,
	footer,
	nav,
	returnFocus,
	busy,
	children,
} ) {
	const panel = useRef( null );
	const opener = useRef( null );
	const onCloseRef = useRef( onClose );
	const returnRef = useRef( returnFocus );
	onCloseRef.current = onClose;
	returnRef.current = returnFocus;

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		opener.current = panel.current?.ownerDocument.activeElement || null;

		const app = document.querySelector( '.zs-app' );
		const frame = app?.querySelector( '.zs-frame' );
		frame?.setAttribute( 'inert', '' );
		app?.classList.add( 'zs-drawer-open' );
		app?.style.setProperty( '--zs-drawer-w', wide ? '760px' : '440px' );

		const first =
			panel.current?.querySelector( '[data-autofocus]' ) ||
			panel.current?.querySelector( '[data-close]' );
		first?.focus();

		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				event.stopPropagation();
				onCloseRef.current();
				return;
			}
			if ( 'Tab' !== event.key || ! panel.current ) {
				return;
			}
			const list = focusables( panel.current );
			if ( ! list.length ) {
				event.preventDefault();
				return;
			}
			const firstEl = list[ 0 ];
			const lastEl = list[ list.length - 1 ];
			const active = panel.current.ownerDocument.activeElement;
			if (
				event.shiftKey &&
				( active === firstEl || ! panel.current.contains( active ) )
			) {
				event.preventDefault();
				lastEl.focus();
			} else if (
				! event.shiftKey &&
				( active === lastEl || ! panel.current.contains( active ) )
			) {
				event.preventDefault();
				firstEl.focus();
			}
		};
		const doc = panel.current?.ownerDocument || document;
		doc.addEventListener( 'keydown', onKey, true );

		return () => {
			doc.removeEventListener( 'keydown', onKey, true );
			frame?.removeAttribute( 'inert' );
			app?.classList.remove( 'zs-drawer-open' );
			const wanted = returnRef.current ? returnRef.current() : null;
			const back = wanted && wanted.isConnected ? wanted : opener.current;
			if ( back && back.isConnected ) {
				back.focus();
			}
		};
	}, [ open, wide ] );

	if ( ! open ) {
		return null;
	}

	/**
	 * Moves through the list, then keeps focus in the drawer: on the same
	 * button while it can still be used, else on the other one or Close.
	 *
	 * @param {(...args: unknown[]) => unknown} action Previous or next.
	 * @param {string}                          dir    prev or next.
	 */
	const step = ( action, dir ) => {
		action();
		window.requestAnimationFrame( () => {
			const p = panel.current;
			if ( ! p ) {
				return;
			}
			const active = p.ownerDocument.activeElement;
			if ( active && p.contains( active ) && ! active.disabled ) {
				return;
			}
			(
				p.querySelector( `[data-zs-nav="${ dir }"]:not([disabled])` ) ||
				p.querySelector( '[data-zs-nav]:not([disabled])' ) ||
				p.querySelector( '[data-close]' )
			)?.focus();
		} );
	};

	const onNavKey = ( event ) => {
		if ( ! nav || ! event.altKey ) {
			return;
		}
		if ( 'ArrowUp' === event.key && nav.onPrev ) {
			event.preventDefault();
			step( nav.onPrev, 'prev' );
		} else if ( 'ArrowDown' === event.key && nav.onNext ) {
			event.preventDefault();
			step( nav.onNext, 'next' );
		}
	};

	const close = (
		<Button
			variant="ghost"
			iconOnly
			icon="close"
			data-close
			aria-label={ __( 'Close', 'zero-spam' ) }
			onClick={ onClose }
		/>
	);

	const layer = document.getElementById( 'zs-layer' );
	const tree = (
		<>
			<div
				className="zs-scrim zs-scrim-drawer"
				onClick={ onClose }
				aria-hidden="true"
			/>
			{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions -- Alt+Up/Down move through the list while focus is anywhere in the drawer. */ }
			<div
				ref={ panel }
				className={ cx(
					'zs-drawer',
					wide && 'zs-drawer-wide',
					className
				) }
				role="dialog"
				aria-modal="true"
				aria-labelledby={ labelledBy }
				onKeyDown={ onNavKey }
			>
				{ header ? (
					<div className="zs-drawer-h">
						<div className="zs-grow">{ header }</div>
						{ close }
						{ nav && (
							<div
								className="zs-detnav"
								role="group"
								aria-label={
									nav.groupLabel ||
									__( 'Move through the list', 'zero-spam' )
								}
							>
								<Button
									variant="ghost"
									iconOnly
									icon="chev"
									className="zs-chev-up"
									data-zs-nav="prev"
									aria-label={
										nav.prevLabel ||
										__( 'Previous (Alt+Up)', 'zero-spam' )
									}
									aria-keyshortcuts="Alt+ArrowUp"
									disabled={ ! nav.onPrev }
									onClick={ () =>
										nav.onPrev && step( nav.onPrev, 'prev' )
									}
								/>
								<span className="zs-detpos" aria-live="polite">
									{ nav.label }
								</span>
								<Button
									variant="ghost"
									iconOnly
									icon="chev"
									className="zs-chev-down"
									data-zs-nav="next"
									aria-label={
										nav.nextLabel ||
										__( 'Next (Alt+Down)', 'zero-spam' )
									}
									aria-keyshortcuts="Alt+ArrowDown"
									disabled={ ! nav.onNext }
									onClick={ () =>
										nav.onNext && step( nav.onNext, 'next' )
									}
								/>
							</div>
						) }
					</div>
				) : (
					<div className="zs-drawer-close">{ close }</div>
				) }
				<div
					className="zs-drawer-b"
					aria-busy={ busy ? 'true' : undefined }
				>
					{ children }
				</div>
				{ footer && <div className="zs-drawer-f">{ footer }</div> }
			</div>
		</>
	);

	// Rendered next to the frame (which turns inert while a drawer is open).
	return layer ? createPortal( tree, layer ) : tree;
}
