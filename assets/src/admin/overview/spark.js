/**
 * A sparkline (prototype v11/v13: one 1.75px stroke, a soft area under it).
 */

/**
 * Internal dependencies
 */
import { sparkPaths } from './chart-data';

/**
 * @param {Object}   props             Props.
 * @param {number[]} props.values      Values, oldest first.
 * @param {number}   props.width       viewBox width.
 * @param {number}   props.height      viewBox height.
 * @param {string}   props.label       Accessible description of the trend.
 * @param {string}   [props.className] Extra classes (zs-spark-big, zs-spark-mini).
 */
export function Spark( { values, width, height, label, className } ) {
	const { line, area } = sparkPaths( values, width, height );
	if ( ! line ) {
		return null;
	}
	return (
		<svg
			className={ [ 'zs-spark', className ]
				.filter( Boolean )
				.join( ' ' ) }
			viewBox={ `0 0 ${ width } ${ height }` }
			preserveAspectRatio="none"
			role="img"
			aria-label={ label }
			focusable="false"
		>
			<path className="zs-sp-area" d={ area } />
			<path className="zs-sp-line" d={ line } pathLength="1" />
		</svg>
	);
}
