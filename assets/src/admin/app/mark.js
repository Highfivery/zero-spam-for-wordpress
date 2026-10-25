/**
 * The live mark (treatment B "Live core") and its controller.
 *
 * States (styles/logo.css): idle (halo breathes; one brick glints every
 * 20–40 s while the tab is visible), thinking (a request has been in
 * flight for more than 150 ms) and blocking (one brick lands when a
 * detection arrives; at most one landing every 2 s, bursts coalesce).
 * Under reduced motion nothing moves; a landing only dips one brick.
 */

/**
 * WordPress dependencies
 */
import { useEffect, useMemo, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { onBusy } from '../data/api';

const marks = new Set();
const BRICKS = 13;
const CORE = 3;
const LAND_GAP = 2000;
const THINK_DELAY = 150;

let state = 'idle';
let busyTimer = null;
let lastLanding = -Infinity;
let pendingLanding = null;
let glintTimer = null;
let lastBrick = -1;

const reduced = () =>
	window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ||
	Boolean( document.querySelector( '[data-zs-motion="reduce"]' ) );

const apply = () =>
	marks.forEach( ( m ) => m.setAttribute( 'data-state', state ) );

onBusy( ( count ) => {
	window.clearTimeout( busyTimer );
	if ( count > 0 && 'thinking' !== state ) {
		busyTimer = window.setTimeout( () => {
			state = 'thinking';
			apply();
		}, THINK_DELAY );
	} else if ( 0 === count && 'thinking' === state ) {
		state = 'idle';
		apply();
	}
} );

const pick = () => {
	let i;
	do {
		i = Math.floor( Math.random() * BRICKS );
	} while ( i === lastBrick || i === CORE );
	lastBrick = i;
	return i;
};

/**
 * Lands one brick on every live mark.
 */
export function land() {
	if ( document.hidden || ! marks.size ) {
		return;
	}
	const now = performance.now();
	const gap = now - lastLanding;
	if ( gap < LAND_GAP ) {
		if ( ! pendingLanding ) {
			pendingLanding = window.setTimeout( () => {
				pendingLanding = null;
				land();
			}, LAND_GAP - gap );
		}
		return;
	}
	lastLanding = now;
	const index = pick();

	marks.forEach( ( mark ) => {
		const brick = mark.querySelector( `.zs-b[data-i="${ index }"]` );
		if ( ! brick ) {
			return;
		}
		const x = Number( brick.dataset.x );
		const y = Number( brick.dataset.y );
		const w = Number( brick.dataset.w );
		const h = Number( brick.dataset.h );
		const pulse = mark.querySelector( '.zs-pulse' );
		const ring = mark.querySelector( '.zs-ring' );
		pulse?.setAttribute( 'cx', String( x + w / 2 ) );
		pulse?.setAttribute( 'cy', String( y + h / 2 ) );
		pulse?.setAttribute( 'rx', String( w * 0.95 ) );
		pulse?.setAttribute( 'ry', String( h * 1.35 ) );
		ring?.setAttribute( 'cx', String( x + w / 2 ) );
		ring?.setAttribute( 'cy', String( y + h / 2 ) );
		ring?.setAttribute( 'r', String( Math.max( w, h ) * 0.62 ) );

		mark.querySelectorAll( '[data-landing]' ).forEach( ( b ) =>
			b.removeAttribute( 'data-landing' )
		);
		mark.setAttribute( 'data-state', 'blocking' );
		brick.setAttribute( 'data-landing', '' );
		window.setTimeout( () => {
			brick.removeAttribute( 'data-landing' );
			mark.setAttribute( 'data-state', state );
		}, 520 );
	} );
}

const scheduleGlint = () => {
	window.clearTimeout( glintTimer );
	glintTimer = window.setTimeout(
		() => {
			if ( 'idle' === state && ! document.hidden && ! reduced() ) {
				const index = Math.floor( Math.random() * BRICKS );
				marks.forEach( ( mark ) => {
					const brick = mark.querySelector(
						`.zs-b[data-i="${ index }"]`
					);
					brick?.setAttribute( 'data-glint', '' );
					window.setTimeout(
						() => brick?.removeAttribute( 'data-glint' ),
						760
					);
				} );
			}
			scheduleGlint();
		},
		20000 + Math.random() * 20000
	);
};

/**
 * The mark, drawn inline from the sprite's #zs-mark symbol so the
 * controller (and logo.css) can reach each brick.
 *
 * @param {Object}  props             Props.
 * @param {number}  props.size        Width in pixels.
 * @param {boolean} [props.live]      Follows the app's state (idle/thinking/blocking).
 * @param {string}  [props.className] Extra classes.
 */
export function Mark( { size = 32, live = false, className } ) {
	const ref = useRef( null );
	const content = useMemo( () => {
		const symbol = document.getElementById( 'zs-mark' );
		return symbol ? symbol.innerHTML : '';
	}, [] );

	useEffect( () => {
		if ( ! live || ! ref.current ) {
			return undefined;
		}
		const el = ref.current;
		marks.add( el );
		el.setAttribute( 'data-state', state );
		if ( 1 === marks.size ) {
			scheduleGlint();
		}
		return () => {
			marks.delete( el );
			if ( ! marks.size ) {
				window.clearTimeout( glintTimer );
			}
		};
	}, [ live ] );

	return (
		<span
			ref={ ref }
			className={ [ 'zs-mark', className ].filter( Boolean ).join( ' ' ) }
			style={ { width: `${ size }px` } }
			aria-hidden="true"
		>
			<svg
				viewBox="0 0 256 242"
				focusable="false"
				// The sprite is the plugin's own static file (assets/dist/sprite.svg).
				dangerouslySetInnerHTML={ { __html: content } }
			/>
		</span>
	);
}
