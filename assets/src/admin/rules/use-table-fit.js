/**
 * Whether the block lists show their table: from 1,440px, and only while the
 * table fits its space (translations and long values can make it wider).
 *
 * Cards cannot report how wide the table would be, so the width the table
 * needed is kept when it overflows, and the table is only tried again once the
 * list has grown past that. A change of rows, or fonts finishing loading, tries
 * it again too. Every measure and retry happens in a layout effect, before the
 * browser paints, so a try that fails is never seen. Nothing here runs on a
 * timer; below 1,440px nothing is measured.
 *
 * A switch replaces the row controls, so the focused one is noted first and the
 * same row's matching control takes focus once the layout has settled (a
 * failed try is two commits in a row, and focus must survive both).
 */

/**
 * WordPress dependencies
 */
import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useMedia } from '../app/context';
import {
	focusIfEnabled,
	useFocusAfterRender,
} from '../app/use-focus-after-render';
import {
	TABLE_QUERY,
	findControl,
	neededWidth,
	rowControlOf,
	shouldRetryTable,
} from './query';

/**
 * @param {{current: HTMLElement|null}} listRef The list's root (it holds .zs-tbl-wrap in both layouts).
 * @param {Array}                       rows    The rows shown.
 * @return {boolean} Whether to show the table.
 */
export function useTableFit( listRef, rows ) {
	const focusAfter = useFocusAfterRender();
	const noted = useRef( null );
	const settling = useRef( false );

	// Keeps what is focused before a switch takes it away.
	const noteFocus = useRef( () => {
		if ( ! noted.current ) {
			noted.current = rowControlOf(
				listRef.current,
				listRef.current?.ownerDocument.activeElement
			);
		}
	} ).current;

	const wide = useMedia( TABLE_QUERY, noteFocus );
	const [ needs, setNeeds ] = useState( null );
	const [ fontsLoaded, setFontsLoaded ] = useState( 0 );

	/**
	 * Keeps the width the table needs, if it overflows now.
	 *
	 * @return {boolean} Whether the table overflows.
	 */
	const measure = useRef( () => {
		const list = listRef.current;
		const wrap = list?.querySelector( ':scope > .zs-tbl-wrap' );
		if ( ! wrap || ! list.clientWidth ) {
			return false;
		}
		const width = neededWidth(
			list.clientWidth,
			wrap.scrollWidth,
			wrap.clientWidth
		);
		if ( null === width ) {
			return false;
		}
		noteFocus();
		setNeeds( width );
		return true;
	} ).current;

	const latest = useRef();
	latest.current = { wide, needs };
	const seen = useRef( { rows, fontsLoaded } );

	useLayoutEffect( () => {
		const changed =
			seen.current.rows !== rows ||
			seen.current.fontsLoaded !== fontsLoaded;
		seen.current = { rows, fontsLoaded };
		settling.current = false;
		if ( ! wide ) {
			return;
		}
		if ( null === needs ) {
			settling.current = measure();
		} else if (
			changed ||
			shouldRetryTable( listRef.current?.clientWidth, needs )
		) {
			noteFocus();
			setNeeds( null );
			settling.current = true;
		}
	}, [ wide, needs, rows, fontsLoaded, listRef, measure, noteFocus ] );

	// After every commit, but it only acts once when something was noted.
	useLayoutEffect( () => {
		if ( settling.current || ! noted.current ) {
			return;
		}
		const control = noted.current;
		noted.current = null;
		focusAfter( () =>
			focusIfEnabled( findControl( listRef.current, control ) )
		);
	} );

	// One check per change of the list's width.
	useEffect( () => {
		const list = listRef.current;
		if ( ! list || ! window.ResizeObserver ) {
			return undefined;
		}
		let last = list.clientWidth;
		const observer = new window.ResizeObserver( () => {
			const width = list.clientWidth;
			if ( width === last || ! width ) {
				return;
			}
			last = width;
			if ( ! latest.current.wide ) {
				return;
			}
			if ( null === latest.current.needs ) {
				measure();
			} else if ( shouldRetryTable( width, latest.current.needs ) ) {
				noteFocus();
				setNeeds( null );
			}
		} );
		observer.observe( list );
		return () => observer.disconnect();
	}, [ listRef, measure, noteFocus ] );

	// The first measure can come before the fonts, which change every width.
	useEffect( () => {
		const fonts = document.fonts;
		if ( ! fonts || 'loaded' === fonts.status ) {
			return undefined;
		}
		let active = true;
		fonts.ready.then( () => {
			if ( active ) {
				setFontsLoaded( ( n ) => n + 1 );
			}
		} );
		return () => {
			active = false;
		};
	}, [] );

	return wide && null === needs;
}
