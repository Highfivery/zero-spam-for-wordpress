/**
 * The hero's angled wall: courses of bricks laid in on load (top-right
 * first), then drifting slowly sideways, alternate courses in opposite
 * directions.
 *
 * Each course is an HTML element holding its own small SVG, so the drift
 * (a transform on the course) runs on the compositor; transforms on SVG
 * children would repaint on the main thread every frame. Only transform
 * and opacity animate. The drift pauses while the hero is off screen, the
 * tab is hidden, or the pointer or focus is on the hero; nothing moves
 * under reduced motion.
 */

/**
 * WordPress dependencies
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';

/** The prototype's drawing box: the wall fills it like an SVG with "slice". */
export const WALL_BOX = { width: 640, height: 420 };

/**
 * A seeded random source (the wall looks the same on every load).
 *
 * @param {number} seed Seed.
 * @return {() => number} Random numbers in [0, 1).
 */
export function seeded( seed ) {
	// eslint-disable-next-line no-bitwise -- An unsigned 32-bit seed for the generator.
	let s = seed >>> 0 || 1;
	return () => {
		// eslint-disable-next-line no-bitwise -- A linear congruential generator works on 32-bit integers.
		s = ( Math.imul( s, 1664525 ) + 1013904223 ) >>> 0;
		return s / 4294967296;
	};
}

/**
 * The wall's courses, in the wall's own coordinates (course height `bh`,
 * length `length`; each course holds its bricks twice for a seamless loop).
 *
 * @param {Object} o      Options.
 * @param {number} o.w    Drawing box width.
 * @param {number} o.h    Drawing box height.
 * @param {number} o.bh   Course height.
 * @param {number} o.seed Seed.
 * @param {number} o.gap  Mortar.
 * @return {{rows: Array, length: number, bh: number, gap: number}} Courses.
 */
export function driftWall( { w, h, bh, seed, gap } ) {
	const random = seeded( seed );
	const cx = w / 2;
	const cy = h / 2;
	const length = Math.ceil( Math.hypot( w, h ) + bh * 2 );
	const count = Math.ceil( length / bh ) + 1;
	const y0 = cy - ( count * bh ) / 2;
	const x0 = cx - length / 2;
	const rows = [];

	for ( let r = 0; r < count; r++ ) {
		const y = y0 + r * bh;
		const bricks = [];
		let x = 0;
		while ( x < length ) {
			let bw = bh * [ 1.1, 1.25, 1.6, 1.3 ][ Math.floor( random() * 4 ) ];
			if ( length - x - bw < bh * 0.9 ) {
				bw = length - x;
			}
			bricks.push( [ x, bw ] );
			x += bw;
		}

		const rects = [];
		[ 0, length ].forEach( ( offset ) =>
			bricks.forEach( ( [ bx, bw ] ) => {
				const ax = x0 + bx + offset;
				const fx = ( ax + bw / 2 ) / w;
				const fy = ( y + bh / 2 ) / h;
				const t =
					fx * 0.55 + fy * 0.5 - 0.05 + ( random() - 0.5 ) * 0.5;
				const shade =
					1 + Math.max( 0, Math.min( 5, Math.floor( t * 6 ) ) );
				const delay = Math.round(
					Math.max(
						0,
						Math.min(
							1,
							( 1 - fy ) * 0.55 +
								Math.min( 1, Math.max( 0, fx ) ) * 0.45
						)
					) * 480
				);
				rects.push( {
					x: +( bx + offset + gap / 2 ).toFixed( 1 ),
					width: +( bw - gap ).toFixed( 1 ),
					shade,
					delay,
				} );
			} )
		);

		rows.push( {
			rects,
			duration: 60 + ( ( r * 37 ) % 31 ),
			reverse: 1 === r % 2,
		} );
	}

	return { rows, length, bh, gap };
}

/**
 * @param {Object}  props         Props.
 * @param {boolean} props.reduced Animations are off.
 */
export function HeroWall( { reduced } ) {
	const wall = useMemo(
		() =>
			driftWall( {
				w: WALL_BOX.width,
				h: WALL_BOX.height,
				bh: 118,
				seed: 11,
				gap: 6,
			} ),
		[]
	);
	const box = useRef( null );
	const [ phase, setPhase ] = useState( reduced ? 'still' : 'lay' );
	const [ onScreen, setOnScreen ] = useState( true );
	const [ scale, setScale ] = useState( 1 );

	useEffect( () => {
		if ( reduced ) {
			setPhase( 'still' );
			return undefined;
		}
		const t = window.setTimeout( () => setPhase( 'drift' ), 1100 );
		return () => window.clearTimeout( t );
	}, [ reduced ] );

	// Fills the box like preserveAspectRatio="xMidYMid slice", and notes
	// when it is off screen (the drift pauses).
	useEffect( () => {
		const el = box.current;
		if ( ! el ) {
			return undefined;
		}
		const fit = () =>
			setScale(
				Math.max(
					el.clientWidth / WALL_BOX.width,
					el.clientHeight / WALL_BOX.height
				) || 1
			);
		fit();
		const resize =
			'undefined' !== typeof window.ResizeObserver
				? new window.ResizeObserver( fit )
				: null;
		resize?.observe( el );
		const visible =
			'undefined' !== typeof window.IntersectionObserver
				? new window.IntersectionObserver( ( [ entry ] ) =>
						setOnScreen( entry.isIntersecting )
					)
				: null;
		visible?.observe( el );
		return () => {
			resize?.disconnect();
			visible?.disconnect();
		};
	}, [] );

	const classes = [ 'zs-hero-art' ];
	if ( 'lay' === phase ) {
		classes.push( 'zs-lay-in' );
	}
	if ( 'drift' === phase ) {
		classes.push( 'zs-drifting' );
	}
	if ( ! onScreen ) {
		classes.push( 'zs-offscreen' );
	}

	const height = wall.bh - wall.gap;
	const rx = +( wall.bh * 0.1 ).toFixed( 1 );

	return (
		<div className={ classes.join( ' ' ) } ref={ box } aria-hidden="true">
			<div
				className="zs-wall-rot"
				style={ {
					width: `${ wall.length }px`,
					height: `${ wall.rows.length * wall.bh }px`,
					transform: `translate(-50%, -50%) scale(${ scale.toFixed(
						3
					) }) rotate(-6deg)`,
				} }
			>
				{ wall.rows.map( ( row, r ) => (
					<div
						key={ r }
						className={
							row.reverse ? 'zs-drow zs-drow-r' : 'zs-drow'
						}
						style={ {
							top: `${ r * wall.bh }px`,
							width: `${ wall.length * 2 }px`,
							height: `${ wall.bh }px`,
							'--zs-p': `${ wall.length }px`,
							'--zs-dur': `${ row.duration }s`,
						} }
					>
						<svg
							width={ wall.length * 2 }
							height={ wall.bh }
							viewBox={ `0 0 ${ wall.length * 2 } ${ wall.bh }` }
							focusable="false"
						>
							{ row.rects.map( ( b, i ) => (
								<rect
									key={ i }
									className={ `zs-hb zs-b${ b.shade }` }
									style={ { '--zs-d': `${ b.delay }ms` } }
									x={ b.x }
									y={ wall.gap / 2 }
									width={ b.width }
									height={ height }
									rx={ rx }
								/>
							) ) }
						</svg>
					</div>
				) ) }
			</div>
		</div>
	);
}
