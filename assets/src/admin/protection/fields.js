/**
 * The generic field renderer: one settings row per schema entry, whatever
 * section it comes from (built-in or an extension's).
 *
 * checkbox → switch; text, url, number, date → input; textarea; radio →
 * segmented control (native radios, so arrow keys move the choice); select
 * → select, or a tag picker when it takes several values; secrets →
 * write-only key field ("•••• a1b2" with Replace and Remove); html → an
 * action button (settings/actions) or a help disclosure.
 *
 * Every control is labelled by the setting's title and described by its
 * description, status and error. A setting the network administrator locked
 * is disabled, with a "Locked by network" badge and the reason in its
 * description.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Badge, Button, Icon, Switch, cx } from '../app/primitives';
import { NS, request } from '../data/api';
import { RichText, plainText } from './rich-text';
import {
	ACTIONS,
	CONFIRM_ACTIONS,
	controlFor,
	dependency,
	formatValue,
	idOf,
	isOn,
	onValue,
	optionLabel,
	recommendedText,
} from './settings-model';

/** Descriptions longer than this (characters) are clamped behind "More". */
const CLAMP_AT = 170;

/**
 * Labels of the action buttons.
 *
 * @return {Object<string, string>} Labels by action.
 */
const actionLabels = () => ( {
	autoconfigure: __( 'Apply recommended settings', 'zero-spam' ),
	'regenerate-honeypot': __( 'Regenerate honeypot ID', 'zero-spam' ),
	'update-blocked-email-domains': __(
		'Replace with the recommended list',
		'zero-spam'
	),
	'update-disallowed-words': __(
		'Replace with the recommended words',
		'zero-spam'
	),
} );

/**
 * A description, clamped with More / Less when it is long.
 *
 * @param {Object} props      Props.
 * @param {string} props.id   ID.
 * @param {string} props.html Description (limited markup).
 */
function Description( { id, html } ) {
	const [ open, setOpen ] = useState( false );
	if ( ! html ) {
		return null;
	}
	const long = plainText( html ).length > CLAMP_AT;
	return (
		<>
			<RichText
				id={ id }
				html={ html }
				className={ cx( 'zs-fdesc', long && ! open && 'zs-clamp' ) }
			/>
			{ long && (
				<button
					type="button"
					className="zs-morebtn"
					aria-expanded={ open ? 'true' : 'false' }
					aria-controls={ id }
					onClick={ () => setOpen( ! open ) }
				>
					{ open
						? __( 'Less', 'zero-spam' )
						: __( 'More', 'zero-spam' ) }
				</button>
			) }
		</>
	);
}

/**
 * Where a value comes from on a network: the network default, or locked.
 *
 * @param {Object} props       Props.
 * @param {Object} props.entry Schema entry.
 */
function NetworkNote( { entry } ) {
	if ( entry.locked || 'network' !== entry.source ) {
		return null;
	}
	const value = entry.secret ? '' : formatValue( entry, entry.value );
	return (
		<p className="zs-netnote">
			<Icon name="network" size="sm" />
			<span>
				{ value
					? sprintf(
							/* translators: %s: the network's value of the setting */
							__( 'Using the network default: %s', 'zero-spam' ),
							value
						)
					: __( 'Using the network default.', 'zero-spam' ) }
			</span>
		</p>
	);
}

/**
 * The write-only field of a secret (license and API keys, webhook URLs).
 *
 * @param {Object}                          props             Props.
 * @param {Object}                          props.entry       Schema entry (value: { set, hint, source }).
 * @param {string|undefined}                props.draft       Typed value, '' to remove, undefined for no change.
 * @param {(...args: unknown[]) => unknown} props.onChange    Receives the draft (undefined = no change).
 * @param {string}                          props.id          Input ID.
 * @param {string}                          props.describedBy IDs describing the field.
 * @param {boolean}                         props.disabled    Disabled.
 * @param {boolean}                         props.invalid     Has an error.
 */
export function SecretField( {
	entry,
	draft,
	onChange,
	id,
	describedBy,
	disabled,
	invalid,
} ) {
	const masked =
		entry.value && 'object' === typeof entry.value ? entry.value : {};
	const [ editing, setEditing ] = useState( false );
	const [ show, setShow ] = useState( false );
	const input = useRef( null );

	// Replace moves focus into the new field.
	useEffect( () => {
		if ( editing ) {
			input.current?.focus();
		}
	}, [ editing ] );
	const fixed =
		[ 'constant', 'locked' ].includes( masked.source ) || disabled;
	const removing = '' === draft && masked.set;

	if ( masked.set && ! editing && undefined === draft ) {
		let where = '';
		if ( 'constant' === masked.source ) {
			where = __( 'Set in wp-config.php', 'zero-spam' );
		} else if (
			'network' === masked.source ||
			'locked' === masked.source
		) {
			where = __( 'Set by your network administrator', 'zero-spam' );
		}
		return (
			<div className="zs-secret">
				<span className="zs-secret-v" id={ id }>
					<span className="zs-mono">{ masked.hint || '••••' }</span>
					{ where && <span className="zs-hint">{ where }</span> }
				</span>
				{ ! fixed && (
					<span className="zs-inline-row">
						<Button
							variant="secondary"
							size="sm"
							onClick={ () => setEditing( true ) }
							aria-label={ sprintf(
								/* translators: %s: setting name, such as API Key */
								__( 'Replace %s', 'zero-spam' ),
								entry.title
							) }
						>
							{ __( 'Replace', 'zero-spam' ) }
						</Button>
						<Button
							variant="ghost"
							size="sm"
							onClick={ () => onChange( '' ) }
							aria-label={ sprintf(
								/* translators: %s: setting name, such as API Key */
								__( 'Remove %s', 'zero-spam' ),
								entry.title
							) }
						>
							{ __( 'Remove', 'zero-spam' ) }
						</Button>
					</span>
				) }
			</div>
		);
	}

	if ( removing ) {
		return (
			<div className="zs-secret">
				<span className="zs-secret-v" id={ id }>
					{ __( 'Removed when you save.', 'zero-spam' ) }
				</span>
				<Button
					variant="secondary"
					size="sm"
					onClick={ () => onChange( undefined ) }
				>
					{ __( 'Keep it', 'zero-spam' ) }
				</Button>
			</div>
		);
	}

	return (
		<span className="zs-inwrap">
			<input
				ref={ input }
				className="zs-input zs-mono"
				id={ id }
				type={ show ? 'text' : 'password' }
				value={ draft || '' }
				autoComplete="off"
				spellCheck="false"
				disabled={ disabled }
				aria-invalid={ invalid ? 'true' : undefined }
				aria-describedby={ describedBy }
				placeholder={
					masked.set
						? __( 'Enter the new key', 'zero-spam' )
						: entry.placeholder || ''
				}
				onChange={ ( event ) =>
					onChange( event.target.value || undefined )
				}
			/>
			<Button
				variant="ghost"
				size="sm"
				aria-pressed={ show ? 'true' : 'false' }
				aria-controls={ id }
				onClick={ () => setShow( ! show ) }
				disabled={ disabled }
			>
				{ show ? __( 'Hide', 'zero-spam' ) : __( 'Show', 'zero-spam' ) }
			</Button>
			{ masked.set && (
				<Button
					variant="ghost"
					size="sm"
					onClick={ () => {
						setEditing( false );
						onChange( undefined );
					} }
				>
					{ __( 'Cancel', 'zero-spam' ) }
				</Button>
			) }
		</span>
	);
}

/**
 * Chips for the values of a multi-select, with an "Add…" select.
 *
 * @param {Object}                          props             Props.
 * @param {Object}                          props.entry       Schema entry.
 * @param {string[]}                        props.value       Selected values.
 * @param {(...args: unknown[]) => unknown} props.onChange    Receives the new list.
 * @param {string}                          props.titleId     ID of the title.
 * @param {string}                          props.describedBy IDs describing the field.
 * @param {boolean}                         props.disabled    Disabled.
 */
function TagPicker( {
	entry,
	value,
	onChange,
	titleId,
	describedBy,
	disabled,
} ) {
	const selected = Array.isArray( value ) ? value.map( String ) : [];
	const rest = ( entry.options || [] ).filter(
		( o ) => ! selected.includes( String( o.value ) )
	);
	return (
		<div
			className="zs-tagpick"
			role="group"
			aria-labelledby={ titleId }
			aria-describedby={ describedBy }
		>
			{ selected.length ? (
				<ul className="zs-tchips">
					{ selected.map( ( v ) => {
						const label = optionLabel( entry, v );
						return (
							<li className="zs-tchip" key={ v }>
								<span>{ label }</span>
								<button
									className="zs-tchip-x"
									type="button"
									disabled={ disabled }
									aria-label={ sprintf(
										/* translators: %s: name of the value, such as Editor */
										__( 'Remove %s', 'zero-spam' ),
										label
									) }
									onClick={ () =>
										onChange(
											selected.filter( ( x ) => x !== v )
										)
									}
								>
									<Icon name="close" size="sm" />
								</button>
							</li>
						);
					} ) }
				</ul>
			) : (
				<p className="zs-hint">
					{ __( 'None selected.', 'zero-spam' ) }
				</p>
			) }
			{ rest.length > 0 && (
				<select
					className="zs-select"
					value=""
					disabled={ disabled }
					aria-label={ sprintf(
						/* translators: %s: setting name */
						__( 'Add to %s', 'zero-spam' ),
						entry.title
					) }
					onChange={ ( event ) => {
						if ( event.target.value ) {
							onChange( [ ...selected, event.target.value ] );
						}
					} }
				>
					<option value="">{ __( 'Add…', 'zero-spam' ) }</option>
					{ rest.map( ( o ) => (
						<option key={ o.value } value={ o.value }>
							{ o.label || o.value }
						</option>
					) ) }
				</select>
			) }
		</div>
	);
}

/**
 * A button that runs one of settings/actions, with its result.
 *
 * @param {Object}                          props          Props.
 * @param {Object}                          props.entry    Schema entry.
 * @param {boolean}                         props.disabled Disabled.
 * @param {(...args: unknown[]) => unknown} [props.onDone] Called with the answer.
 */
function ActionField( { entry, disabled, onDone } ) {
	const action = ACTIONS[ entry.key ];
	const id = idOf( entry.key );
	const [ state, setState ] = useState( {
		busy: false,
		ask: false,
		message: '',
		ok: true,
	} );
	const label = actionLabels()[ action ] || entry.title;

	const run = () => {
		setState( { busy: true, ask: false, message: '', ok: true } );
		request( {
			path: `${ NS }/settings/actions/${ action }`,
			method: 'POST',
		} )
			.then( ( answer ) => {
				let message = answer?.message || __( 'Done.', 'zero-spam' );
				if ( answer?.honeypot ) {
					message = sprintf(
						/* translators: %s: the new honeypot ID */
						__(
							'New honeypot ID: %s. If you use a page cache, clear it so forms use the new ID.',
							'zero-spam'
						),
						answer.honeypot
					);
				}
				setState( { busy: false, ask: false, message, ok: true } );
				onDone?.( answer );
			} )
			.catch( ( error ) =>
				setState( {
					busy: false,
					ask: false,
					message:
						error?.message ||
						__(
							'That did not work. Please try again.',
							'zero-spam'
						),
					ok: false,
				} )
			);
	};

	return (
		<div className="zs-setrow" id={ `zs-row-${ id }` }>
			<div className="zs-desc">
				<b className="zs-st-t" id={ `zs-t-${ id }` }>
					{ entry.title }
				</b>
				<Description id={ `zs-d-${ id }` } html={ entry.description } />
			</div>
			<div className="zs-ctl">
				{ state.ask ? (
					<div
						className="zs-inline-row"
						role="group"
						aria-labelledby={ `zs-q-${ id }` }
					>
						<span id={ `zs-q-${ id }` }>
							{ __(
								'This replaces what is saved now. Continue?',
								'zero-spam'
							) }
						</span>
						<Button
							variant="secondary"
							size="sm"
							onClick={ run }
							data-autofocus
						>
							{ __( 'Replace', 'zero-spam' ) }
						</Button>
						<Button
							variant="ghost"
							size="sm"
							onClick={ () =>
								setState( { ...state, ask: false } )
							}
						>
							{ __( 'Cancel', 'zero-spam' ) }
						</Button>
					</div>
				) : (
					<div className="zs-inline-row">
						<Button
							variant="secondary"
							icon="refresh"
							disabled={ disabled || state.busy }
							aria-describedby={ `zs-d-${ id }` }
							onClick={ () =>
								CONFIRM_ACTIONS.includes( action )
									? setState( { ...state, ask: true } )
									: run()
							}
						>
							{ state.busy
								? __( 'Working…', 'zero-spam' )
								: label }
						</Button>
					</div>
				) }
				<p
					className={ cx(
						'zs-actres',
						state.message &&
							( state.ok ? 'zs-actres-ok' : 'zs-actres-err' )
					) }
					role="status"
					aria-live="polite"
				>
					{ state.message }
				</p>
			</div>
		</div>
	);
}

/**
 * Help (an html setting): a disclosure with its text.
 *
 * @param {Object} props       Props.
 * @param {Object} props.entry Schema entry.
 */
function HelpField( { entry } ) {
	return (
		<details className="zs-help" id={ `zs-row-${ idOf( entry.key ) }` }>
			<summary>
				<Icon name="help" size="sm" />
				<span>{ entry.title || __( 'Help', 'zero-spam' ) }</span>
			</summary>
			<RichText className="zs-help-b" html={ entry.html } as="div" />
		</details>
	);
}

/**
 * One setting.
 *
 * @param {Object}                          props            Props.
 * @param {Object}                          props.entry      Schema entry.
 * @param {unknown}                         props.value      Current value (draft or saved; for secrets the draft).
 * @param {(...args: unknown[]) => unknown} props.onChange   Receives (key, value).
 * @param {Object<string, Object>}          props.entries    Every entry by key (dependencies).
 * @param {string}                          [props.error]    Error from the last save.
 * @param {boolean}                         [props.disabled] Disabled for another reason (the card).
 * @param {(...args: unknown[]) => unknown} [props.onAction] Called after an action ran.
 */
export function Field( {
	entry,
	value,
	onChange,
	entries,
	error,
	disabled = false,
	onAction,
} ) {
	const control = controlFor( entry );
	const id = idOf( entry.key );
	const inputId = `zs-f-${ id }`;
	const titleId = `zs-t-${ id }`;
	const descId = `zs-d-${ id }`;
	const errId = `zs-e-${ id }`;
	const lockId = `zs-l-${ id }`;
	const statusId = `zs-s-${ id }`;
	const locked = Boolean( entry.locked );
	const off = locked || disabled;

	if ( 'none' === control ) {
		return null;
	}
	if ( 'help' === control ) {
		return <HelpField entry={ entry } />;
	}
	if ( 'action' === control ) {
		return (
			<ActionField entry={ entry } disabled={ off } onDone={ onAction } />
		);
	}

	const set = ( next ) => onChange( entry.key, next );
	const labelled = [
		'text',
		'url',
		'number',
		'date',
		'textarea',
		'select',
		'secret',
	].includes( control );
	const describedBy =
		[
			'switch' === control && statusId,
			entry.description && descId,
			locked && lockId,
			error && errId,
		]
			.filter( Boolean )
			.join( ' ' ) || undefined;
	const invalid = error ? 'true' : undefined;
	const rec = recommendedText( entry );
	const dep = dependency( entry, entries );

	let input = null;
	switch ( control ) {
		case 'switch': {
			const on = isOn( entry, value );
			input = (
				<span className="zs-sw-row">
					<Switch
						id={ inputId }
						checked={ on }
						disabled={ off }
						labelledBy={ titleId }
						describedBy={ describedBy }
						onChange={ ( next ) =>
							set( next ? onValue( entry ) : false )
						}
					/>
					<span className="zs-hint zs-sw-status" id={ statusId }>
						{ on
							? __( 'On', 'zero-spam' )
							: __( 'Off', 'zero-spam' ) }
					</span>
				</span>
			);
			break;
		}
		case 'segmented':
			input = (
				<div
					className="zs-seg zs-segf"
					role="radiogroup"
					aria-labelledby={ titleId }
					aria-describedby={ describedBy }
				>
					{ ( entry.options || [] ).map( ( o ) => (
						// eslint-disable-next-line jsx-a11y/label-has-associated-control -- The radio is inside the label, its text after it.
						<label className="zs-segr" key={ o.value }>
							<input
								type="radio"
								name={ inputId }
								value={ o.value }
								checked={
									String( value ) === String( o.value )
								}
								disabled={ off }
								onChange={ () => set( o.value ) }
							/>
							<span>{ o.label || o.value }</span>
						</label>
					) ) }
				</div>
			);
			break;
		case 'select': {
			const known = ( entry.options || [] ).some(
				( o ) => String( o.value ) === String( value )
			);
			input = (
				<select
					className="zs-select"
					id={ inputId }
					value={
						null === value || undefined === value || false === value
							? ''
							: String( value )
					}
					disabled={ off }
					aria-invalid={ invalid }
					aria-describedby={ describedBy }
					onChange={ ( event ) => set( event.target.value ) }
				>
					{ ! known && (
						<option value="">
							{ __( 'Choose…', 'zero-spam' ) }
						</option>
					) }
					{ ( entry.options || [] ).map( ( o ) => (
						<option key={ o.value } value={ o.value }>
							{ o.label || o.value }
						</option>
					) ) }
				</select>
			);
			break;
		}
		case 'tags':
			input = (
				<TagPicker
					entry={ entry }
					value={ value }
					onChange={ set }
					titleId={ titleId }
					describedBy={ describedBy }
					disabled={ off }
				/>
			);
			break;
		case 'textarea':
			input = (
				<textarea
					className="zs-textarea"
					id={ inputId }
					rows={ 5 }
					value={
						null === value || undefined === value || false === value
							? ''
							: String( value )
					}
					placeholder={ entry.placeholder || undefined }
					disabled={ off }
					spellCheck="false"
					aria-invalid={ invalid }
					aria-describedby={ describedBy }
					onChange={ ( event ) => set( event.target.value ) }
				/>
			);
			break;
		case 'secret':
			input = (
				<SecretField
					entry={ entry }
					draft={ value }
					onChange={ set }
					id={ inputId }
					describedBy={ describedBy }
					disabled={ off }
					invalid={ Boolean( error ) }
				/>
			);
			break;
		default:
			input = (
				<input
					className={ cx(
						'zs-input',
						'number' === control && 'zs-mono zs-num-in',
						'date' === control && 'zs-mono'
					) }
					id={ inputId }
					type={ control }
					value={
						null === value || undefined === value || false === value
							? ''
							: String( value )
					}
					placeholder={ entry.placeholder || undefined }
					min={ entry.min ?? undefined }
					max={ entry.max ?? undefined }
					step={ entry.step ?? undefined }
					inputMode={ 'number' === control ? 'decimal' : undefined }
					autoComplete="off"
					spellCheck="false"
					disabled={ off }
					aria-invalid={ invalid }
					aria-describedby={ describedBy }
					onChange={ ( event ) => set( event.target.value ) }
				/>
			);
	}

	return (
		<div
			className={ cx( 'zs-setrow', error && 'zs-setrow-err' ) }
			id={ `zs-row-${ id }` }
		>
			<div className="zs-desc">
				{ labelled ? (
					<label
						className="zs-st-t"
						id={ titleId }
						htmlFor={ inputId }
					>
						{ entry.title }
					</label>
				) : (
					<b className="zs-st-t" id={ titleId }>
						{ entry.title }
					</b>
				) }
				{ locked && (
					<span className="zs-tags">
						<Badge
							className="zs-badge-lock zs-lockbadge"
							icon="lock"
						>
							<span aria-hidden="true">
								{ __( 'Locked by network', 'zero-spam' ) }
							</span>
							<span className="zs-sr-only" id={ lockId }>
								{ sprintf(
									/* translators: %s: setting name */
									__(
										'“%s” is locked by the network administrator, so it cannot be changed on this site.',
										'zero-spam'
									),
									entry.title
								) }
							</span>
						</Badge>
					</span>
				) }
				<Description id={ descId } html={ entry.description } />
				{ rec && (
					<p className="zs-rec">
						{ sprintf(
							/* translators: %s: recommended value, such as On */
							__( 'Recommended: %s', 'zero-spam' ),
							rec
						) }
					</p>
				) }
				{ dep && (
					<p className="zs-dep">
						{ dep.value
							? sprintf(
									/* translators: 1: setting name, 2: one of its choices */
									__(
										'Applies when “%1$s” is “%2$s”.',
										'zero-spam'
									),
									dep.title,
									dep.value
								)
							: sprintf(
									/* translators: %s: setting name */
									__(
										'Applies when “%s” is on.',
										'zero-spam'
									),
									dep.title
								) }
					</p>
				) }
			</div>
			<div className="zs-ctl">
				<NetworkNote entry={ entry } />
				{ input }
				{ error && (
					<p className="zs-field-err" id={ errId }>
						<Icon name="warn" size="sm" />
						<span>{ error }</span>
					</p>
				) }
			</div>
		</div>
	);
}
