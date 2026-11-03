/**
 * The setup's mark: the 6.0 mark built course by course, bottom row first,
 * one row per step (prototype v18). Rows not laid yet are dashed outlines;
 * newly laid bricks drop in (not under reduced motion; styles/help.css).
 */

/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Mark } from '../app/mark';
import { rowLaid } from './stepper';

/**
 * A brick's row, from its --zs-r custom property (0 = top).
 *
 * @param {Element} brick `.zs-b` group.
 * @return {number} Row.
 */
const rowOf = ( brick ) => {
	const match = /--zs-r:\s*(\d+)/.exec( brick.getAttribute( 'style' ) || '' );
	return match ? Number( match[ 1 ] ) : 0;
};

/**
 * @param {Object} props      Props.
 * @param {number} props.step The highest step reached (DONE lays the top row).
 */
export function BuildMark( { step } ) {
	const wrap = useRef( null );
	const before = useRef( 0 );

	useEffect( () => {
		const root = wrap.current;
		if ( ! root ) {
			return;
		}
		root.querySelectorAll( '.zs-b' ).forEach( ( brick ) => {
			const row = rowOf( brick );
			const laid = rowLaid( row, step );
			const fresh = laid && ! rowLaid( row, before.current );
			brick.toggleAttribute( 'data-ghost', ! laid );
			brick.toggleAttribute( 'data-lay', fresh );
		} );
		// The core glows once the row around it is up.
		root.querySelector( '.zs-coreglow' )?.toggleAttribute(
			'data-off',
			! rowLaid( 1, step )
		);
		before.current = step;
	}, [ step ] );

	return (
		<div className="zs-onb-mark" ref={ wrap }>
			<Mark size={ 420 } />
		</div>
	);
}
