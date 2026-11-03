/**
 * Small parts of the Activity screens: a country with its flag, the
 * Location sort menu and the sortable column header.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Icon, cx } from '../app/primitives';
import { boot } from '../data/api';
import { ariaSort } from './query';

/**
 * A country: its flag (a decorative image shipped with the plugin) and its
 * name. The name is always text, so nothing depends on the flag.
 *
 * @param {Object}  props         Props.
 * @param {string}  props.code    Two-letter code.
 * @param {string}  [props.name]  Country name (the code when missing).
 * @param {boolean} [props.code2] Show the code after the name.
 */
export function Country( { code, name, code2 = false } ) {
	const [ broken, setBroken ] = useState( false );
	const cc = /^[A-Za-z]{2}$/.test( String( code || '' ) )
		? String( code ).toLowerCase()
		: '';
	if ( ! cc && ! name ) {
		return null;
	}
	return (
		<span className="zs-cty">
			{ cc && boot.urls.flags && ! broken && (
				<img
					className="zs-flag"
					src={ `${ boot.urls.flags }${ cc }.svg` }
					alt=""
					width="20"
					height="14"
					loading="lazy"
					decoding="async"
					onError={ () => setBroken( true ) }
				/>
			) }
			<span>
				{ name || cc.toUpperCase() }
				{ code2 && cc && name && (
					<span className="zs-hint"> ({ cc.toUpperCase() })</span>
				) }
			</span>
		</span>
	);
}

/**
 * A sortable column header's button (the th carries aria-sort).
 *
 * @param {Object}                          props          Props.
 * @param {string}                          props.sort     Current sort key.
 * @param {string}                          props.column   Column (date, type, ip).
 * @param {(...args: unknown[]) => unknown} props.onSort   Receives the column.
 * @param {unknown}                         props.children Label.
 */
export function SortButton( { sort, column, onSort, children } ) {
	const state = ariaSort( sort, [ column ] );
	let arrow = '';
	if ( 'ascending' === state ) {
		arrow = ' ↑';
	} else if ( 'descending' === state ) {
		arrow = ' ↓';
	}
	return (
		<button
			type="button"
			className="zs-thsort"
			data-zs-sort={ column }
			onClick={ () => onSort( column ) }
		>
			{ children }
			<span aria-hidden="true">{ arrow }</span>
		</button>
	);
}

/**
 * The Location header: one button with a menu (Country or Region, A–Z or
 * Z–A), a menu button with radio items (arrow keys, Escape, Tab closes).
 *
 * @param {Object}                          props        Props.
 * @param {string}                          props.sort   Current sort key.
 * @param {(...args: unknown[]) => unknown} props.onSort Receives a sort key.
 */
export function LocationSort( { sort, onSort } ) {
	const [ open, setOpen ] = useState( false );
	const button = useRef( null );
	const menu = useRef( null );
	const [ col, dir ] = sort.split( ':' );
	const on = 'country' === col || 'region' === col;
	const options = [
		[ 'country:asc', __( 'Country A–Z', 'zero-spam' ) ],
		[ 'country:desc', __( 'Country Z–A', 'zero-spam' ) ],
		[ 'region:asc', __( 'Region A–Z', 'zero-spam' ) ],
		[ 'region:desc', __( 'Region Z–A', 'zero-spam' ) ],
	];

	const items = () =>
		Array.from(
			menu.current?.querySelectorAll( '[role="menuitemradio"]' ) || []
		);

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const list = items();
		(
			list.find(
				( el ) => 'true' === el.getAttribute( 'aria-checked' )
			) || list[ 0 ]
		)?.focus();
		const onDown = ( event ) => {
			if (
				! menu.current?.contains( event.target ) &&
				! button.current?.contains( event.target )
			) {
				setOpen( false );
			}
		};
		document.addEventListener( 'pointerdown', onDown );
		return () => document.removeEventListener( 'pointerdown', onDown );
	}, [ open ] );

	const onMenuKey = ( event ) => {
		const list = items();
		const i = list.indexOf( event.target.ownerDocument.activeElement );
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			event.stopPropagation();
			setOpen( false );
			button.current?.focus();
		} else if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			const step = 'ArrowDown' === event.key ? 1 : -1;
			list[ ( i + step + list.length ) % list.length ]?.focus();
		} else if ( 'Home' === event.key || 'End' === event.key ) {
			event.preventDefault();
			list[ 'Home' === event.key ? 0 : list.length - 1 ]?.focus();
		} else if ( 'Tab' === event.key ) {
			setOpen( false );
		}
	};

	const by =
		'country' === col
			? __( 'country', 'zero-spam' )
			: __( 'region', 'zero-spam' );

	return (
		<div className="zs-sortmenu">
			<button
				ref={ button }
				type="button"
				className="zs-thsort"
				aria-haspopup="menu"
				aria-expanded={ open ? 'true' : 'false' }
				aria-controls="zs-loc-menu"
				onClick={ () => setOpen( ! open ) }
				onKeyDown={ ( event ) => {
					if ( 'ArrowDown' === event.key && ! open ) {
						event.preventDefault();
						setOpen( true );
					}
				} }
			>
				{ __( 'Location', 'zero-spam' ) }
				{ on && (
					<>
						<span className="zs-sm-by">{ by }</span>
						<span aria-hidden="true">
							{ 'asc' === dir ? ' ↑' : ' ↓' }
						</span>
					</>
				) }
				<span className="zs-caret" aria-hidden="true">
					▾
				</span>
			</button>
			{ /* eslint-disable-next-line jsx-a11y/interactive-supports-focus -- The menu items take focus, not the menu. */ }
			<div
				ref={ menu }
				id="zs-loc-menu"
				className="zs-menu"
				role="menu"
				aria-label={ __( 'Sort by location', 'zero-spam' ) }
				hidden={ ! open }
				onKeyDown={ onMenuKey }
			>
				{ options.map( ( [ value, label ] ) => (
					<button
						key={ value }
						type="button"
						role="menuitemradio"
						aria-checked={ value === sort ? 'true' : 'false' }
						tabIndex={ -1 }
						onClick={ () => {
							onSort( value );
							setOpen( false );
							button.current?.focus();
						} }
					>
						{ label }
						{ value === sort && <Icon name="check" size="sm" /> }
					</button>
				) ) }
			</div>
		</div>
	);
}

/**
 * The icon for a detection type, in its tinted square.
 *
 * @param {Object} props             Props.
 * @param {string} props.icon        Icon name.
 * @param {string} [props.className] Extra classes.
 */
export const TypeIcon = ( { icon, className } ) => (
	<span className={ cx( 'zs-ico', 'zs-ico-danger', className ) }>
		<Icon name={ icon } />
	</span>
);
