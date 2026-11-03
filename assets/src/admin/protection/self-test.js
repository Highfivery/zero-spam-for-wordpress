/**
 * "Test my protection": the button and the step-by-step panel (prototype
 * v18). The start of a run and its outcome are spoken through the app's
 * live region (it exists from the first render, so nothing is missed);
 * under reduced motion the answers appear at once.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { Button, Icon, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { NS, request } from '../data/api';
import {
	heading,
	isBusy,
	stepNames,
	stepTime,
	summary,
} from './self-test-model';

/** Pause between two answers being laid down, in milliseconds. */
const REVEAL_MS = 320;

/**
 * Starts a run.
 *
 * @param {(...args: unknown[]) => unknown} dispatch Reducer dispatch.
 */
export function runSelfTest( dispatch ) {
	dispatch( { type: 'start' } );
	request( { path: `${ NS }/self-test`, method: 'POST' } ).then(
		( data ) => dispatch( { type: 'answer', data } ),
		( error ) => dispatch( { type: 'fail', error } )
	);
}

/**
 * The button in the page head.
 *
 * @param {Object}                          props           Props.
 * @param {Object}                          props.state     Self-test state.
 * @param {(...args: unknown[]) => unknown} props.onRun     Starts a run.
 * @param {boolean}                         [props.primary] The page's primary action.
 */
export function TestButton( { state, onRun, primary = true } ) {
	const busy = isBusy( state );
	return (
		<Button
			variant={ primary ? 'primary' : 'secondary' }
			className="zs-test-btn"
			icon={ busy || 'idle' === state.phase ? undefined : 'refresh' }
			aria-disabled={ busy ? 'true' : undefined }
			aria-controls="zs-selftest"
			onClick={ () => {
				if ( ! busy ) {
					onRun();
				}
			} }
		>
			{ busy && <span className="zs-spin" aria-hidden="true" /> }
			{ 'idle' === state.phase && <ShieldIcon /> }
			{ busy && __( 'Testing…', 'zero-spam' ) }
			{ 'idle' === state.phase &&
				__( 'Test my protection', 'zero-spam' ) }
			{ ! busy &&
				'idle' !== state.phase &&
				__( 'Run the test again', 'zero-spam' ) }
		</Button>
	);
}

/** The shield icon of the button before the first run. */
const ShieldIcon = () => <Icon name="shield" size="sm" />;

/**
 * What a finished step's icon means, for screen readers.
 *
 * @return {Object<string, string>} Text by status.
 */
const STATUS_TEXT = () => ( {
	pass: __( 'Passed:', 'zero-spam' ),
	warn: __( 'Warning:', 'zero-spam' ),
	fail: __( 'Failed:', 'zero-spam' ),
} );

/**
 * The step icon.
 *
 * @param {Object} props        Props.
 * @param {string} props.status Step status.
 */
function StepIcon( { status } ) {
	const icon = { pass: 'check', warn: 'warn', fail: 'close' }[ status ];
	return (
		<span className="zs-st-s" aria-hidden="true">
			{ icon && <Icon name={ icon } size="sm" /> }
		</span>
	);
}

/**
 * The panel.
 *
 * @param {Object}                          props          Props.
 * @param {Object}                          props.state    Self-test state.
 * @param {(...args: unknown[]) => unknown} props.dispatch Reducer dispatch.
 */
export function SelfTestPanel( { state, dispatch } ) {
	const { reduced } = useApp();
	const { announce } = useFeedback();
	const panel = useRef( null );
	const names = stepNames();
	const line = summary( state );
	const started = useRef( false );

	// Lay the answers down one by one (at once with reduced motion).
	useEffect( () => {
		if ( 'revealing' !== state.phase ) {
			return undefined;
		}
		if ( reduced ) {
			dispatch( { type: 'reveal', all: true } );
			return undefined;
		}
		const timer = window.setTimeout(
			() => dispatch( { type: 'reveal' } ),
			REVEAL_MS
		);
		return () => window.clearTimeout( timer );
	}, [ state.phase, state.shown, reduced, dispatch ] );

	// Progress for screen readers: the start and the outcome.
	useEffect( () => {
		if (
			[ 'running', 'done', 'error', 'limited' ].includes( state.phase )
		) {
			announce( line.text );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Once per phase.
	}, [ state.phase ] );

	// The panel scrolls into view when a run starts.
	useEffect( () => {
		if ( 'running' === state.phase && ! started.current ) {
			started.current = true;
			panel.current?.scrollIntoView( {
				block: 'nearest',
				behavior: reduced ? 'auto' : 'smooth',
			} );
		}
		if ( 'running' !== state.phase ) {
			started.current = false;
		}
	}, [ state.phase, reduced ] );

	if ( 'idle' === state.phase ) {
		return <div id="zs-selftest" hidden />;
	}

	const laid = state.steps.filter( ( s ) =>
		[ 'pass', 'warn', 'fail' ].includes( s.status )
	);

	return (
		<section
			ref={ panel }
			id="zs-selftest"
			className="zs-tile zs-selftest"
			aria-labelledby="zs-stt-h"
			aria-busy={ isBusy( state ) ? 'true' : undefined }
		>
			<div className="zs-st-wall" aria-hidden="true">
				{ state.steps.map( ( step, i ) => (
					<span
						key={ step.id }
						className={ cx(
							i < laid.length && 'zs-laid',
							i < laid.length && `zs-laid-${ laid[ i ].status }`
						) }
					/>
				) ) }
			</div>
			<div className="zs-st-body">
				<div className="zs-eyebrow">
					{ __( 'Self-test', 'zero-spam' ) }
				</div>
				<h2 id="zs-stt-h" className="zs-tile-title">
					{ heading( state ) }
				</h2>
				{ state.steps.length > 0 && (
					<ol className="zs-st-steps">
						{ state.steps.map( ( step ) => {
							const [ name, waiting ] = names[ step.id ] || [
								step.id,
								'',
							];
							return (
								<li
									key={ step.id }
									className={ `zs-step-${ step.status }` }
								>
									<StepIcon status={ step.status } />
									<span className="zs-li-body">
										<b>
											{ STATUS_TEXT()[ step.status ] && (
												<span className="zs-sr-only">
													{
														STATUS_TEXT()[
															step.status
														]
													}{ ' ' }
												</span>
											) }
											{ name }
										</b>
										<span className="zs-hint">
											{ step.message || waiting }
										</span>
									</span>
									<span className="zs-st-time">
										{ stepTime( step ) }
									</span>
								</li>
							);
						} ) }
					</ol>
				) }
				<p
					className={ cx(
						'zs-st-result',
						line.tone && `zs-st-result-${ line.tone }`
					) }
				>
					{ line.text }
				</p>
			</div>
		</section>
	);
}
