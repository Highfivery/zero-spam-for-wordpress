/**
 * Activity: the page heading, the Log and Reports tabs (?tab=reports).
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PATHS, useResource } from '../data/api';
import { num } from '../data/format';
import { useTab } from '../data/routes';
import { Log } from './log';
import { Reports } from './reports';

const TABS = [ 'log', 'reports' ];

/**
 * The Activity area.
 */
export function Activity() {
	const [ tab, setTab ] = useTab( 'log', TABS );
	const summary = useResource( PATHS.summary( 30 ) );
	const tabs = useRef( null );
	const total = summary.data?.total ?? null;
	const names = {
		log: __( 'Log', 'zero-spam' ),
		reports: __( 'Reports', 'zero-spam' ),
	};

	const onKey = ( event ) => {
		const i = TABS.indexOf( tab );
		let next = null;
		if ( 'ArrowRight' === event.key ) {
			next = TABS[ ( i + 1 ) % TABS.length ];
		} else if ( 'ArrowLeft' === event.key ) {
			next = TABS[ ( i - 1 + TABS.length ) % TABS.length ];
		} else if ( 'Home' === event.key ) {
			next = TABS[ 0 ];
		} else if ( 'End' === event.key ) {
			next = TABS[ TABS.length - 1 ];
		}
		if ( next ) {
			event.preventDefault();
			setTab( next );
			window.requestAnimationFrame( () =>
				tabs.current?.querySelector( `#zs-at-${ next }` )?.focus()
			);
		}
	};

	return (
		<div className="zs-view zs-activity" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ __( 'Activity.', 'zero-spam' ) }
						<span>
							{ null === total && '\u00a0' }
							{ 0 === total &&
								__(
									'Nothing stopped in 30 days.',
									'zero-spam'
								) }
							{ total > 0 &&
								sprintf(
									/* translators: %s: number of threats */
									_n(
										'%s stopped in 30 days.',
										'%s stopped in 30 days.',
										total,
										'zero-spam'
									),
									num( total )
								) }
						</span>
					</h1>
					<p>
						{ __(
							'Every blocked comment, login, sign-up and form. Select a row to see why it was blocked and what you can do.',
							'zero-spam'
						) }
					</p>
				</div>
			</div>
			{ /* eslint-disable-next-line jsx-a11y/interactive-supports-focus -- The tabs take focus (roving tabindex), not the list. */ }
			<div
				ref={ tabs }
				className="zs-seg zs-tabs"
				role="tablist"
				aria-label={ __( 'Activity views', 'zero-spam' ) }
				onKeyDown={ onKey }
			>
				{ TABS.map( ( t ) => (
					<button
						key={ t }
						id={ `zs-at-${ t }` }
						type="button"
						role="tab"
						aria-selected={ t === tab ? 'true' : 'false' }
						aria-controls="zs-act-body"
						tabIndex={ t === tab ? 0 : -1 }
						onClick={ () => setTab( t ) }
					>
						{ names[ t ] }
					</button>
				) ) }
			</div>
			<div
				id="zs-act-body"
				role="tabpanel"
				aria-labelledby={ `zs-at-${ tab }` }
				className="zs-areabody"
			>
				{ 'reports' === tab ? (
					<Reports />
				) : (
					<Log
						onChanged={ summary.reload }
						summary={ summary.data }
					/>
				) }
			</div>
		</div>
	);
}
