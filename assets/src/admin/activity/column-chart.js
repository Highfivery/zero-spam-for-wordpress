/**
 * A column chart read with the keyboard (the Overview's chart pattern):
 * a focusable group whose columns the left and right arrow keys, Home and
 * End move through, each spoken in a polite live region and shown in a
 * tooltip, with the same numbers in a table behind "Show the numbers as
 * a table".
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { cx } from '../app/primitives';
import { keyStep } from './reports-data';

/**
 * @param {Object}                          props              Props.
 * @param {string}                          props.id           ID prefix.
 * @param {string}                          props.label        Accessible name of the chart.
 * @param {string}                          props.summary      One sentence about the whole chart.
 * @param {Array<{key: string|number}>}     props.columns      Columns.
 * @param {(...args: unknown[]) => unknown} props.describe     ( column ) => text for the reader and the tooltip.
 * @param {(...args: unknown[]) => unknown} props.renderColumn ( column ) => the column's bars.
 * @param {(...args: unknown[]) => unknown} props.axis         ( column, index ) => axis label or ''.
 * @param {string}                          props.caption      Table caption.
 * @param {string[]}                        props.head         Table column names (the first is the row header).
 * @param {(...args: unknown[]) => unknown} props.cells        ( column ) => [ row header, …numbers ].
 * @param {string}                          [props.className]  Extra classes.
 */
export function ColumnChart( {
	id,
	label,
	summary,
	columns,
	describe,
	renderColumn,
	axis,
	caption,
	head,
	cells,
	className,
} ) {
	const [ hot, setHot ] = useState( null );
	const [ spoken, setSpoken ] = useState( '' );
	const n = columns.length;

	const show = ( i ) => {
		setHot( i );
		setSpoken( null === i ? '' : describe( columns[ i ] ) );
	};

	const onKey = ( event ) => {
		if ( 'Escape' === event.key ) {
			setHot( null );
			return;
		}
		const next = keyStep( event.key, hot, n );
		if ( null !== next ) {
			event.preventDefault();
			show( next );
		}
	};

	const left =
		null !== hot
			? Math.min( 88, Math.max( 12, ( ( hot + 0.5 ) / n ) * 100 ) )
			: 0;

	return (
		<div className="zs-cchart-wrap">
			<p className="zs-sr-only" id={ `${ id }-sum` }>
				{ summary }
			</p>
			{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions -- A focusable group read with the arrow keys (WAI-ARIA data chart pattern); the table below is the alternative. */ }
			<div
				className={ cx( 'zs-cchart', className ) }
				role="group"

				tabIndex={ 0 }
				aria-label={ `${ label }. ${ __( 'Use the left and right arrow keys to read each column.', 'zero-spam' ) }` }
				aria-describedby={ `${ id }-sum` }
				style={ {
					gridTemplateColumns: `repeat(${ n }, minmax(0, 1fr))`,
				} }
				onKeyDown={ onKey }
				onFocus={ () => show( null === hot ? n - 1 : hot ) }
				onBlur={ () => setHot( null ) }
				onMouseLeave={ () => setHot( null ) }
			>
				{ columns.map( ( col, i ) => (
					<div
						key={ col.key }
						className={ cx( 'zs-ccol', hot === i && 'zs-hot' ) }
						onMouseEnter={ () => setHot( i ) }
					>
						{ renderColumn( col ) }
						<span className="zs-clbl" aria-hidden="true">
							{ axis( col, i ) }
						</span>
					</div>
				) ) }
				{ null !== hot && columns[ hot ] && (
					<div
						className="zs-tip zs-ctip"
						style={ { left: `${ left }%` } }
						aria-hidden="true"
					>
						{ describe( columns[ hot ] ) }
					</div>
				) }
			</div>
			<p className="zs-sr-only" aria-live="polite">
				{ spoken }
			</p>
			<details className="zs-data">
				<summary>
					{ __( 'Show the numbers as a table', 'zero-spam' ) }
				</summary>
				<div className="zs-tbl-wrap">
					<table className="zs-t">
						<caption className="zs-sr-only">{ caption }</caption>
						<thead>
							<tr>
								{ head.map( ( h, i ) => (
									<th
										key={ h }
										scope="col"
										className={ i ? 'zs-num' : undefined }
									>
										{ h }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ columns.map( ( col ) => {
								const [ name, ...values ] = cells( col );
								return (
									<tr key={ col.key }>
										<th scope="row">{ name }</th>
										{ values.map( ( v, i ) => (
											<td
												key={ i }
												className="zs-mono zs-num"
											>
												{ v }
											</td>
										) ) }
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
			</details>
		</div>
	);
}
