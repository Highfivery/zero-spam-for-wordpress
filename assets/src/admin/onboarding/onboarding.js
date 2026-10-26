/**
 * First-run setup (fresh installs): a standalone view in place of the
 * Overview while `zerospam_onboarding` is pending (Admin_App::view_for()).
 *
 * Three steps and a done screen (prototype v18): the protection level
 * (Basic, or Enhanced with a license key), what to protect (the core entry
 * points, the browser check and every form plugin found), and how to block.
 * Each step saves its own settings when the administrator continues; the
 * first also applies the recommended settings underneath, so a setup left
 * half-way still protects the site. "Skip setup" applies the recommended
 * settings and ends the setup. The mark builds one course per step.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	createInterpolateElement,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { Button, Icon, Switch, cx } from '../app/primitives';
import { Mark } from '../app/mark';
import { boot, NS, PATHS, request, useResource } from '../data/api';
import { areaUrl, utm } from '../data/routes';
import { LINKS } from '../help/lists';
import { BuildMark } from './build-mark';
import {
	DONE,
	STEPS,
	blockingFrom,
	changesFor,
	initialState,
	progress,
	protectFrom,
	protectItems,
	reducer,
	stepKeys,
	validateStep,
	writable,
} from './stepper';

/**
 * Marks the setup's own writes, so the server does not take them for a
 * save from the Settings screens (which ends a pending setup).
 */
const SETUP_HEADERS = { 'X-ZeroSpam-Setup': '1' };

/** The message when a step could not be saved. */
const saveError = () =>
	__(
		'That did not save. Check your connection and try again.',
		'zero-spam'
	);

/**
 * The message of a failed request, when the server sent one.
 *
 * @param {Object} failure Rejection of apiFetch.
 * @param {string} param   Parameter whose message to prefer.
 * @return {string} Message.
 */
const failureMessage = ( failure, param ) =>
	failure?.data?.params?.[ param ] || failure?.message || saveError();

/**
 * "Settings this step changes" (progressive disclosure for technical users).
 *
 * @param {Object}   props      Props.
 * @param {string[]} props.keys Setting keys.
 */
function KeysNote( { keys } ) {
	if ( ! keys.length ) {
		return null;
	}
	return (
		<details className="zs-ob-keys">
			<summary>
				{ __( 'Settings this step changes', 'zero-spam' ) }
			</summary>
			<p className="zs-hint">
				{ keys.map( ( k ) => (
					<code key={ k }>{ k }</code>
				) ) }
			</p>
		</details>
	);
}

/**
 * Back / Continue.
 *
 * @param {Object}                          props          Props.
 * @param {number}                          props.step     Step.
 * @param {boolean}                         props.busy     Saving.
 * @param {(...args: unknown[]) => unknown} props.onBack   Back.
 * @param {unknown}                         props.children Submit label.
 */
function StepNav( { step, busy, onBack, children } ) {
	return (
		<div className="zs-onb-nav">
			{ step > 1 && (
				<Button
					variant="ghost"
					icon="chevl"
					onClick={ onBack }
					disabled={ busy }
				>
					{ __( 'Back', 'zero-spam' ) }
				</Button>
			) }
			<span className="zs-grow" />
			<Button
				type="submit"
				aria-disabled={ busy ? 'true' : undefined }
				iconAfter={ step < STEPS ? 'chev' : undefined }
			>
				{ busy ? __( 'Saving…', 'zero-spam' ) : children }
			</Button>
		</div>
	);
}

/**
 * The heading of a step (focused when the step changes).
 *
 * @param {Object}  props       Props.
 * @param {unknown} props.title First line.
 * @param {unknown} props.sub   Second line.
 */
function StepHeading( { title, sub } ) {
	return (
		<h1 className="zs-h1" id="zs-ob-h" tabIndex={ -1 }>
			{ title }
			<span>{ sub }</span>
		</h1>
	);
}

/**
 * Step 1: protection level.
 *
 * @param {Object}                          props            Props.
 * @param {Object}                          props.state      State.
 * @param {(...args: unknown[]) => unknown} props.dispatch   Dispatch.
 * @param {Object}                          props.keyInput   Ref of the key field.
 * @param {boolean}                         props.configured The site keeps its current settings.
 */
function LevelStep( { state, dispatch, keyInput, configured } ) {
	const enhanced = 'enhanced' === state.level;
	const error = state.errors.key;
	return (
		<>
			<StepHeading
				title={ __( "Let's build your wall.", 'zero-spam' ) }
				sub={ __( 'Choose a protection level.', 'zero-spam' ) }
			/>
			<fieldset className="zs-ob-fs zs-ob-choice">
				<legend className="zs-sr-only">
					{ __( 'Protection level', 'zero-spam' ) }
				</legend>
				<div className="zs-ocard">
					<label className="zs-ocard-l" htmlFor="zs-ob-level-basic">
						<input
							id="zs-ob-level-basic"
							type="radio"
							name="zs-ob-level"
							value="basic"
							checked={ ! enhanced }
							aria-describedby="zs-ob-level-basic-d"
							onChange={ () =>
								dispatch( { type: 'level', level: 'basic' } )
							}
						/>
						{ __( 'Basic', 'zero-spam' ) }
						<span className="zs-badge">
							{ __( 'No key needed', 'zero-spam' ) }
						</span>
					</label>
					<div className="zs-ocard-d" id="zs-ob-level-basic-d">
						<p className="zs-ocard-p">
							{ __(
								'Strong protection that runs on your site.',
								'zero-spam'
							) }
						</p>
						<ul className="zs-ocard-ul">
							<li>
								{ __(
									'Hidden traps and browser checks on every form',
									'zero-spam'
								) }
							</li>
							<li>
								{ __(
									'Login and registration screening',
									'zero-spam'
								) }
							</li>
							<li>
								{ __(
									'Stop Forum Spam lookups and your own block lists',
									'zero-spam'
								) }
							</li>
						</ul>
					</div>
				</div>
				<div className="zs-ocard">
					<label
						className="zs-ocard-l"
						htmlFor="zs-ob-level-enhanced"
					>
						<input
							id="zs-ob-level-enhanced"
							type="radio"
							name="zs-ob-level"
							value="enhanced"
							checked={ enhanced }
							aria-describedby="zs-ob-level-enhanced-d"
							onChange={ () =>
								dispatch( { type: 'level', level: 'enhanced' } )
							}
						/>
						{ __( 'Enhanced', 'zero-spam' ) }
						<span className="zs-badge zs-badge-enh">
							{ __( 'License key', 'zero-spam' ) }
						</span>
					</label>
					<div className="zs-ocard-d" id="zs-ob-level-enhanced-d">
						<p className="zs-ocard-p">
							{ __(
								"Everything in Basic, plus real-time checks against Zero Spam's IP and email database from 30,000+ sites.",
								'zero-spam'
							) }
						</p>
					</div>
					{ enhanced && (
						<div className="zs-ob-field zs-ocard-field">
							<label htmlFor="zs-ob-key">
								{ __( 'License key', 'zero-spam' ) }
							</label>
							<input
								ref={ keyInput }
								id="zs-ob-key"
								className="zs-ob-input zs-mono"
								value={ state.key }
								autoComplete="off"
								spellCheck="false"
								aria-invalid={ error ? 'true' : undefined }
								aria-describedby="zs-ob-key-e zs-ob-key-h"
								onChange={ ( event ) =>
									dispatch( {
										type: 'key',
										key: event.target.value,
									} )
								}
							/>
							<span
								className="zs-ob-err"
								id="zs-ob-key-e"
								role={ error ? 'alert' : undefined }
							>
								{ error }
							</span>
							<span className="zs-hint" id="zs-ob-key-h">
								{ __( 'No key yet?', 'zero-spam' ) }{ ' ' }
								<a
									href={ utm( LINKS.plans, 'onboarding' ) }
									target="_blank"
									rel="noopener noreferrer"
								>
									{ __(
										'See plans on zerospam.org',
										'zero-spam'
									) }
									<Icon name="ext" size="sm" />
									<span className="zs-sr-only">
										{ __(
											'(opens in a new tab)',
											'zero-spam'
										) }
									</span>
								</a>
							</span>
						</div>
					) }
				</div>
			</fieldset>
			<p className="zs-hint">
				{ __(
					'You can change this any time in Protection › Intelligence.',
					'zero-spam'
				) }
			</p>
			{ configured && (
				<p className="zs-onb-kept">
					<Icon name="info" size="sm" />
					<span>
						{ __(
							'Your current settings are kept. Each step only changes what it shows.',
							'zero-spam'
						) }
					</span>
				</p>
			) }
		</>
	);
}

/**
 * Step 2: what to protect.
 *
 * @param {Object}                          props          Props.
 * @param {Object}                          props.state    State.
 * @param {(...args: unknown[]) => unknown} props.dispatch Dispatch.
 * @param {Array}                           props.items    protectItems().
 */
function ProtectStep( { state, dispatch, items } ) {
	const groups = [
		[ 'core', __( 'WordPress', 'zero-spam' ) ],
		[ 'forms', __( 'Detected form plugins', 'zero-spam' ) ],
	];
	const ticked = ( item ) =>
		item.locked ? item.enabled : false !== state.protect[ item.key ];

	return (
		<>
			<StepHeading
				title={ sprintf(
					/* translators: %s: number of places visitors can send something (forms, comments, logins) */
					_n(
						'We found %s door.',
						'We found %s doors.',
						items.length,
						'zero-spam'
					),
					items.length
				) }
				sub={ __( 'Confirm what to protect.', 'zero-spam' ) }
			/>
			<p className="zs-hint zs-onb-lede">
				{ __(
					"Everything we detected is ticked. Untick anything you'd rather check yourself.",
					'zero-spam'
				) }
			</p>
			{ groups.map( ( [ group, legend ] ) => {
				const list = items.filter( ( i ) => i.group === group );
				if ( ! list.length ) {
					return null;
				}
				return (
					<fieldset className="zs-ob-fs" key={ group }>
						<legend className="zs-eyebrow">{ legend }</legend>
						<div className="zs-ob-checks">
							{ list.map( ( item ) => (
								<label
									key={ item.key }
									htmlFor={ `zs-ob-c-${ item.key }` }
									className={ cx(
										item.locked && 'zs-is-locked'
									) }
								>
									<input
										id={ `zs-ob-c-${ item.key }` }
										type="checkbox"
										checked={ ticked( item ) }
										disabled={ item.locked }
										aria-describedby={ `zs-ob-p-${ item.key }` }
										onChange={ ( event ) =>
											dispatch( {
												type: 'protect',
												key: item.key,
												on: event.target.checked,
											} )
										}
									/>
									<span>
										{ item.title }
										<small id={ `zs-ob-p-${ item.key }` }>
											{ item.locked
												? __(
														'Set by your network administrator',
														'zero-spam'
													)
												: item.hint }
										</small>
									</span>
								</label>
							) ) }
						</div>
					</fieldset>
				);
			} ) }
		</>
	);
}

/**
 * Step 3: how to block.
 *
 * @param {Object}                          props          Props.
 * @param {Object}                          props.state    State.
 * @param {(...args: unknown[]) => unknown} props.dispatch Dispatch.
 * @param {Object[]}                        props.schema   settings/schema `settings`.
 */
function BlockingStep( { state, dispatch, schema } ) {
	const entry = ( schema || [] ).find( ( s ) => 'block_method' === s.key );
	const options = entry?.options?.length
		? entry.options
		: [ { value: 'php', label: __( 'PHP (Recommended)', 'zero-spam' ) } ];
	const set = ( name, value ) =>
		dispatch( { type: 'blocking', name, value } );

	return (
		<>
			<StepHeading
				title={ __( 'Last course of bricks.', 'zero-spam' ) }
				sub={ __( 'How should blocking work?', 'zero-spam' ) }
			/>
			<fieldset className="zs-ob-fs">
				<legend className="zs-ob-legend">
					{ __( 'How the plugin blocks bad visitors', 'zero-spam' ) }
				</legend>
				<div className="zs-radios zs-radios-one">
					{ options.map( ( o ) => (
						<label
							className="zs-rcard"
							key={ o.value }
							htmlFor={ `zs-ob-m-${ o.value }` }
						>
							<input
								id={ `zs-ob-m-${ o.value }` }
								type="radio"
								name="zs-ob-method"
								value={ o.value }
								checked={
									state.blocking.block_method === o.value
								}
								disabled={ Boolean( entry?.locked ) }
								onChange={ () =>
									set( 'block_method', o.value )
								}
							/>
							<span>
								{ o.label }
								<small>
									{ 'php' === o.value
										? __(
												'Works on every host.',
												'zero-spam'
											)
										: __(
												'Blocks earlier, Apache only.',
												'zero-spam'
											) }
								</small>
							</span>
						</label>
					) ) }
				</div>
			</fieldset>
			<div className="zs-ob-sw">
				<span>
					<b id="zs-obs1">
						{ __( 'Share anonymous spam data', 'zero-spam' ) }
					</b>
					<span className="zs-hint" id="zs-obs1-h">
						{ __(
							'Helps every Zero Spam site block the same spammers sooner. Never personal information.',
							'zero-spam'
						) }
					</span>
				</span>
				<Switch
					checked={ Boolean( state.blocking.share_data ) }
					labelledBy="zs-obs1"
					describedBy="zs-obs1-h"
					onChange={ ( on ) => set( 'share_data', on ) }
				/>
			</div>
			<div className="zs-ob-sw">
				<span>
					<b id="zs-obs2">
						{ __( 'Keep an activity log', 'zero-spam' ) }
					</b>
					<span className="zs-hint" id="zs-obs2-h">
						{ __(
							'Needed for Activity and the charts. Up to 10,000 entries.',
							'zero-spam'
						) }
					</span>
				</span>
				<Switch
					checked={ Boolean( state.blocking.log_blocked_ips ) }
					labelledBy="zs-obs2"
					describedBy="zs-obs2-h"
					onChange={ ( on ) => set( 'log_blocked_ips', on ) }
				/>
			</div>
		</>
	);
}

/**
 * The done screen.
 *
 * @param {Object}                          props           Props.
 * @param {Object}                          props.state     State.
 * @param {number}                          props.places    Places protected.
 * @param {string}                          props.method    Block method label.
 * @param {(...args: unknown[]) => unknown} props.onRestart Start again.
 */
function DoneStep( { state, places, method, onRestart } ) {
	return (
		<div className="zs-onb-step">
			<StepHeading
				title={ __( 'Your wall is up.', 'zero-spam' ) }
				sub={ __( "You're protected.", 'zero-spam' ) }
			/>
			<p className="zs-onb-lede">
				{ sprintf(
					/* translators: %s: number of places (forms, comments, logins) */
					_n(
						'Zero Spam is now checking %s place visitors can send you something. Blocks show on your Overview as they happen.',
						'Zero Spam is now checking %s places visitors can send you something. Blocks show on your Overview as they happen.',
						places,
						'zero-spam'
					),
					places
				) }
			</p>
			<ul className="zs-levels">
				<li className="zs-yes">
					<Icon name="checkc" size="sm" />
					<span>
						{ 'enhanced' === state.level
							? __( 'Enhanced protection is on', 'zero-spam' )
							: __( 'Basic protection is on', 'zero-spam' ) }
					</span>
				</li>
				<li className="zs-yes">
					<Icon name="checkc" size="sm" />
					<span>
						{ sprintf(
							/* translators: %s: block method, such as PHP */
							__( 'Blocking with %s', 'zero-spam' ),
							method
						) }
					</span>
				</li>
				{ boot.safeIp && (
					<li className="zs-yes">
						<Icon name="checkc" size="sm" />
						<span>
							{ createInterpolateElement(
								/* translators: <ip />: the administrator's IP address */
								__(
									'Your address <ip /> can never be locked out',
									'zero-spam'
								),
								{
									ip: (
										<span className="zs-mono">
											{ boot.safeIp }
										</span>
									),
								}
							) }
						</span>
					</li>
				) }
			</ul>
			<div className="zs-onb-note">
				<span className="zs-ico zs-ico-info">
					<Icon name="star" />
				</span>
				<div className="zs-li-body">
					<b>{ __( "One thing we'll ask, once", 'zero-spam' ) }</b>
					<span>
						{ __(
							'When Zero Spam reaches its first milestone of threats stopped, the Overview shows a small thank-you tile with a link to rate Zero Spam on WordPress.org. Dismiss it and that milestone never asks again. No banners, no reminders.',
							'zero-spam'
						) }
					</span>
				</div>
			</div>
			<div className="zs-onb-nav">
				<Button href={ areaUrl( 'overview' ) } iconAfter="chev">
					{ __( 'Go to Overview', 'zero-spam' ) }
				</Button>
				<Button variant="ghost" onClick={ onRestart }>
					{ __( 'Start again', 'zero-spam' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * The setup.
 */
export function Onboarding() {
	const { prefsLoaded, modules, enhanced } = useApp();
	const schema = useResource( PATHS.schema );
	const settings = Array.isArray( schema.data?.settings )
		? schema.data.settings
		: undefined;
	const items = useMemo( () => protectItems( modules ), [ modules ] );
	// The site decides, not the user: a site with settings of its own keeps
	// them (Skip only ends the setup, step 1 applies nothing underneath, and
	// steps 2 and 3 start from its values); the step is this site's.
	const configured = true === boot.configured;
	const saved = Number( boot.onboardingStep ) || 0;
	const [ state, dispatch ] = useReducer(
		reducer,
		saved >= 1 && saved <= STEPS ? saved : 1,
		( step ) =>
			initialState( {
				step,
				...( configured
					? {
							protect: protectFrom( items ),
							blocking: blockingFrom( settings ),
						}
					: {} ),
			} )
	);
	const [ failure, setFailure ] = useState( '' );
	const [ skipping, setSkipping ] = useState( false );
	const baseline = useRef( configured );
	const keyInput = useRef( null );
	const first = useRef( true );

	// A resumed setup shows the level the site has.
	useEffect( () => {
		if ( enhanced ) {
			dispatch( { type: 'level', level: 'enhanced' } );
		}
	}, [ enhanced ] );

	// Focus the step's heading when the step changes (not on load).
	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			return;
		}
		document.getElementById( 'zs-ob-h' )?.focus();
	}, [ state.step ] );

	const applyBaseline = () =>
		baseline.current
			? Promise.resolve()
			: request( {
					path: `${ NS }/settings/actions/autoconfigure`,
					method: 'POST',
					headers: SETUP_HEADERS,
				} ).then( () => {
					baseline.current = true;
				} );

	// This administrator's step on this site (kept for a reload).
	const saveStep = ( step ) =>
		request( {
			path: PATHS.onboarding,
			method: 'POST',
			data: { step },
		} ).catch( () => {} );

	const submit = ( event ) => {
		event.preventDefault();
		if ( state.busy ) {
			return;
		}
		const errors = validateStep( state );
		if ( Object.keys( errors ).length ) {
			dispatch( { type: 'error', errors } );
			keyInput.current?.focus();
			return;
		}
		setFailure( '' );
		dispatch( { type: 'busy', busy: true } );
		const changes = changesFor( state, items );
		const writes = writable( changes.settings, settings );
		const step = state.step;

		applyBaseline()
			.then( () =>
				changes.license
					? request( {
							path: PATHS.license,
							method: 'PUT',
							data: { license_key: changes.license },
							headers: SETUP_HEADERS,
						} ).catch( ( error ) => {
							const keyFailure = new Error( 'license' );
							keyFailure.field = 'key';
							keyFailure.error = error;
							throw keyFailure;
						} )
					: null
			)
			.then( () =>
				Object.keys( writes ).length
					? request( {
							path: `${ NS }/settings`,
							method: 'PATCH',
							data: writes,
							headers: SETUP_HEADERS,
						} )
					: null
			)
			.then( () =>
				STEPS === step
					? request( {
							path: PATHS.onboarding,
							method: 'POST',
							data: { status: 'done' },
						} )
					: null
			)
			.then( () => {
				if ( STEPS !== step ) {
					saveStep( step + 1 );
				}
				dispatch( { type: 'next' } );
			} )
			.catch( ( problem ) => {
				if ( problem?.field ) {
					dispatch( {
						type: 'error',
						errors: {
							key: failureMessage( problem.error, 'license_key' ),
						},
					} );
					keyInput.current?.focus();
					return;
				}
				dispatch( { type: 'busy', busy: false } );
				setFailure( saveError() );
			} );
	};

	const skip = () => {
		if ( skipping ) {
			return;
		}
		setSkipping( true );
		setFailure( '' );
		applyBaseline()
			.then( () =>
				request( {
					path: PATHS.onboarding,
					method: 'POST',
					data: { status: 'done' },
				} )
			)
			.then( () => window.location.assign( areaUrl( 'overview' ) ) )
			.catch( () => {
				setSkipping( false );
				setFailure( saveError() );
			} );
	};

	const method =
		( settings || [] )
			.find( ( s ) => 'block_method' === s.key )
			?.options?.find( ( o ) => o.value === state.blocking.block_method )
			?.label || state.blocking.block_method;
	const places = items.filter( ( i ) =>
		i.locked ? i.enabled : false !== state.protect[ i.key ]
	).length;

	const captions = [
		'',
		__( 'Step 1 of 3 · foundation laid', 'zero-spam' ),
		__( 'Step 2 of 3 · walls going up', 'zero-spam' ),
		__( 'Step 3 of 3 · almost there', 'zero-spam' ),
		__( 'Done · your wall is complete', 'zero-spam' ),
	];
	const labels = [
		__( 'Protection level', 'zero-spam' ),
		__( 'What to protect', 'zero-spam' ),
		__( 'How to block', 'zero-spam' ),
	];

	let body;
	if ( DONE === state.step ) {
		body = (
			<DoneStep
				state={ state }
				places={ places }
				method={ method }
				onRestart={ () => dispatch( { type: 'restart' } ) }
			/>
		);
	} else {
		body = (
			<form
				className="zs-onb-step"
				aria-labelledby="zs-ob-h"
				onSubmit={ submit }
				noValidate
			>
				{ 1 === state.step && (
					<LevelStep
						state={ state }
						dispatch={ dispatch }
						keyInput={ keyInput }
						configured={ configured }
					/>
				) }
				{ 2 === state.step && (
					<ProtectStep
						state={ state }
						dispatch={ dispatch }
						items={ items }
					/>
				) }
				{ 3 === state.step && (
					<BlockingStep
						state={ state }
						dispatch={ dispatch }
						schema={ settings }
					/>
				) }
				<KeysNote keys={ stepKeys( state.step, items ) } />
				{ failure && (
					<p className="zs-ob-err zs-onb-fail" role="alert">
						{ failure }
					</p>
				) }
				<StepNav
					step={ state.step }
					busy={ state.busy }
					onBack={ () => dispatch( { type: 'back' } ) }
				>
					{ STEPS === state.step
						? __( 'Turn on protection', 'zero-spam' )
						: __( 'Continue', 'zero-spam' ) }
				</StepNav>
			</form>
		);
	}

	return (
		<div className="zs-onb" aria-busy={ prefsLoaded ? undefined : 'true' }>
			<div className="zs-onb-l">
				<div className="zs-onb-top">
					<span className="zs-onb-brand">
						<Mark size={ 32 } live />
						<span className="zs-onb-brand-t">
							<b>{ __( 'Zero Spam', 'zero-spam' ) }</b>
							<small>
								{ sprintf(
									/* translators: %s: site name */
									__( 'Setup for %s', 'zero-spam' ),
									boot.site.name ||
										__( 'your site', 'zero-spam' )
								) }
							</small>
						</span>
					</span>
					{ DONE !== state.step && (
						<button
							type="button"
							className="zs-linkbtn zs-onb-skip"
							onClick={ skip }
							aria-disabled={ skipping ? 'true' : undefined }
						>
							{ skipping
								? __(
										'Applying the recommended settings…',
										'zero-spam'
									)
								: __( 'Skip setup', 'zero-spam' ) }
						</button>
					) }
				</div>
				<ol
					className="zs-ob-steps"
					aria-label={ __( 'Setup progress', 'zero-spam' ) }
				>
					{ progress( state.step ).map( ( { n, status } ) => (
						<li
							key={ n }
							className={ cx(
								'done' === status && 'zs-done',
								'current' === status && 'zs-cur'
							) }
							aria-current={
								'current' === status ? 'step' : undefined
							}
						>
							<span className="zs-ob-steps-b" aria-hidden="true">
								{ 'done' === status && (
									<Icon
										name="check"
										size="sm"
										className="zs-ob-check"
									/>
								) }
							</span>
							{ labels[ n - 1 ] }
							{ 'done' === status && (
								<span className="zs-sr-only">
									{ __( '(done)', 'zero-spam' ) }
								</span>
							) }
						</li>
					) ) }
				</ol>
				{ body }
			</div>
			<div className="zs-onb-r zs-dark-block" aria-hidden="true">
				<BuildMark step={ state.step } />
				<div className="zs-onb-cap">{ captions[ state.step ] }</div>
			</div>
		</div>
	);
}
