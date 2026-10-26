/**
 * The Enhanced Protection card's level panel.
 *
 * Basic: what Enhanced adds, one primary "Compare levels" (the Compare
 * drawer) and "I already have a license key", which opens the key form
 * (PUT license, with a "Checking…" state; a working key also turns
 * Enhanced Protection on).
 *
 * Enhanced: the license panel: verified, network status (the last
 * self-test's latency, when the license was last checked), key, plan,
 * renewal and sites when known, early access, and "Remove license key"
 * under Advanced. No prices or billing links.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { roadmap } from '../app/compare';
import { Badge, Button, Icon, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { NS, request } from '../data/api';
import {
	canRemoveKey,
	keyError,
	keySaved,
	licenseRows,
	networkLine,
} from './license-model';

/**
 * Focuses a card's heading.
 *
 * @param {string} key Card key.
 */
const focusCard = ( key ) =>
	window.requestAnimationFrame( () =>
		document.getElementById( `zs-ch-${ key }` )?.focus()
	);

/**
 * The license key form.
 *
 * @param {Object}                          props            Props.
 * @param {(...args: unknown[]) => unknown} props.onSaved    After the key was saved.
 * @param {(...args: unknown[]) => unknown} [props.onCancel] Closes the form.
 * @param {boolean}                         [props.replace]  Replacing a key that is not valid.
 * @param {boolean}                         [props.focus]    Move focus into the field when it opens.
 */
export function KeyForm( {
	onSaved,
	onCancel,
	replace = false,
	focus = true,
} ) {
	const { setLicense, modulesState } = useApp();
	const { toast } = useFeedback();
	const [ value, setValue ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ show, setShow ] = useState( false );
	const input = useRef( null );

	useEffect( () => {
		if ( focus ) {
			input.current?.focus();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Only when the form opens.
	}, [] );

	const submit = ( event ) => {
		event.preventDefault();
		const key = value.trim();
		if ( ! key ) {
			setError( __( 'Enter your license key.', 'zero-spam' ) );
			input.current?.focus();
			return;
		}
		setBusy( true );
		setError( '' );
		request( {
			path: `${ NS }/license`,
			method: 'PUT',
			data: { license_key: key },
		} )
			.then( ( answer ) => {
				const result = keySaved( answer );
				const turnOn = result.enable
					? request( {
							path: `${ NS }/settings`,
							method: 'PATCH',
							data: { zerospam: 'enabled' },
						} ).then(
							() => ( { ...answer, enabled: true } ),
							// Locked by the network, for example: the key is saved, the switch stays as it is.
							( failure ) => ( {
								...answer,
								enableError:
									failure?.message ||
									__(
										'It could not be switched on.',
										'zero-spam'
									),
							} )
						)
					: Promise.resolve( answer );
				return turnOn.then( ( { enableError, ...final } ) => {
					setLicense( final );
					modulesState?.reload?.();
					toast(
						enableError
							? sprintf(
									/* translators: %s: why Enhanced Protection could not be switched on */
									__(
										'Your license key is saved, but Enhanced Protection is still off: %s',
										'zero-spam'
									),
									enableError
								)
							: result.message
					);
					setBusy( false );
					onSaved?.( final );
					focusCard( 'zerospam' );
				} );
			} )
			.catch( ( failure ) => {
				setBusy( false );
				setError( keyError( failure ) );
				// After the field is enabled again.
				window.requestAnimationFrame( () => input.current?.focus() );
			} );
	};

	return (
		<form className="zs-keyform" onSubmit={ submit } noValidate>
			<label className="zs-st-t" htmlFor="zs-license-key">
				{ replace
					? __( 'New license key', 'zero-spam' )
					: __( 'License key', 'zero-spam' ) }
			</label>
			<p className="zs-hint" id="zs-license-h">
				{ __(
					'It is in the email you received with your plan, and in your zerospam.org account.',
					'zero-spam'
				) }
			</p>
			<span className="zs-inwrap">
				<input
					ref={ input }
					id="zs-license-key"
					className="zs-input zs-mono"
					type={ show ? 'text' : 'password' }
					value={ value }
					autoComplete="off"
					spellCheck="false"
					disabled={ busy }
					aria-invalid={ error ? 'true' : undefined }
					aria-describedby={ cx(
						'zs-license-h',
						error && 'zs-license-e'
					) }
					onChange={ ( event ) => setValue( event.target.value ) }
				/>
				<Button
					variant="ghost"
					size="sm"
					aria-pressed={ show ? 'true' : 'false' }
					aria-controls="zs-license-key"
					disabled={ busy }
					onClick={ () => setShow( ! show ) }
				>
					{ show
						? __( 'Hide', 'zero-spam' )
						: __( 'Show', 'zero-spam' ) }
				</Button>
				<Button variant="secondary" type="submit" disabled={ busy }>
					{ busy && <span className="zs-spin" aria-hidden="true" /> }
					{ busy
						? __( 'Checking…', 'zero-spam' )
						: __( 'Verify and save', 'zero-spam' ) }
				</Button>
				{ onCancel && ! busy && (
					<Button variant="ghost" onClick={ onCancel }>
						{ __( 'Cancel', 'zero-spam' ) }
					</Button>
				) }
			</span>
			<p className="zs-actres" role="status" aria-live="polite">
				{ busy ? __( 'Checking your license key…', 'zero-spam' ) : '' }
			</p>
			{ error && (
				<p className="zs-field-err" id="zs-license-e">
					<Icon name="warn" size="sm" />
					<span>{ error }</span>
				</p>
			) }
		</form>
	);
}

/**
 * Basic: what Enhanced adds, with Compare levels and the key form.
 *
 * @param {Object}                          props             Props.
 * @param {boolean}                         props.locked      The license key is set by the network.
 * @param {(...args: unknown[]) => unknown} [props.onChanged] After the key changed.
 */
function BasicPanel( { locked, onChanged } ) {
	const { license, openCompare } = useApp();
	const invalid = 'invalid' === license?.status;
	// "I already have a license key" in the Compare drawer links here with ?card=zerospam.
	const asked =
		'zerospam' ===
		new URLSearchParams( window.location.search ).get( 'card' );
	const [ form, setForm ] = useState( invalid || asked );
	const [ opened, setOpened ] = useState( asked );
	const opener = useRef( null );

	return (
		<>
			<div className="zs-enh-mini zs-dark-block">
				<div>
					<b>{ __( 'What Enhanced adds here', 'zero-spam' ) }</b>
					<ul>
						<li>
							{ __(
								'Real-time checks against one of the largest malicious IP and email databases, built from 30,000+ sites',
								'zero-spam'
							) }
						</li>
						<li>
							{ __(
								'Blocks email addresses reported for spam',
								'zero-spam'
							) }
						</li>
						<li>
							{ __(
								'Helps stop DDoS attempts and stolen credit card testing',
								'zero-spam'
							) }
						</li>
						<li>
							{ __(
								'Detailed IP and email reports',
								'zero-spam'
							) }
						</li>
					</ul>
				</div>
				<div className="zs-inline-row">
					<Button onClick={ () => openCompare( 'protection' ) }>
						{ __( 'Compare levels', 'zero-spam' ) }
					</Button>
					{ ! locked && (
						<Button
							ref={ opener }
							variant="darksec"
							aria-expanded={ form ? 'true' : 'false' }
							aria-controls="zs-keyform-wrap"
							onClick={ () => {
								setOpened( true );
								setForm( ! form );
							} }
						>
							{ __(
								'I already have a license key',
								'zero-spam'
							) }
						</Button>
					) }
				</div>
			</div>
			{ invalid && (
				<p className="zs-notice zs-notice-warn" role="note">
					<Icon name="warn" size="sm" />
					<span>
						{ __(
							'The saved license key is not valid, so Enhanced Protection is not checking anything. Enter a valid key.',
							'zero-spam'
						) }
					</span>
				</p>
			) }
			<div id="zs-keyform-wrap" hidden={ ! form }>
				{ form && (
					<KeyForm
						replace={ invalid }
						focus={ opened }
						onCancel={
							invalid
								? undefined
								: () => {
										setForm( false );
										opener.current?.focus();
									}
						}
						onSaved={ () => {
							setForm( false );
							onChanged?.();
						} }
					/>
				) }
			</div>
		</>
	);
}

/**
 * Enhanced: the license panel.
 *
 * @param {Object}                          props             Props.
 * @param {number|null}                     props.latency     Milliseconds of the last self-test lookup.
 * @param {(...args: unknown[]) => unknown} [props.onChanged] After the key changed.
 */
function LicensePanel( { latency, onChanged } ) {
	const { license, setLicense, modulesState, openCompare } = useApp();
	const { toast } = useFeedback();
	const [ asking, setAsking ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const line = networkLine( license, latency );
	const removeBtn = useRef( null );

	const remove = () => {
		setBusy( true );
		setError( '' );
		request( { path: `${ NS }/license`, method: 'DELETE' } )
			.then( ( answer ) => {
				setLicense( answer );
				modulesState?.reload?.();
				onChanged?.();
				toast(
					__(
						"License key removed. You're on Basic protection.",
						'zero-spam'
					)
				);
				focusCard( 'zerospam' );
			} )
			.catch( ( failure ) => {
				setBusy( false );
				setError( keyError( failure ) );
			} );
	};

	const openEarly = () => {
		openCompare( 'early' );
		window.setTimeout( () => {
			const heading = document.getElementById( 'zs-rm-h' );
			if ( heading ) {
				heading.setAttribute( 'tabindex', '-1' );
				heading.scrollIntoView( { block: 'start' } );
				heading.focus( { preventScroll: true } );
			}
		}, 80 );
	};

	return (
		<div className="zs-licpanel">
			<div className="zs-licpanel-h">
				<b>
					<Icon name="checkc" size="sm" />
					{ __( 'License verified', 'zero-spam' ) }
				</b>
				<Badge tone="enh" icon="star">
					{ __( 'Enhanced member', 'zero-spam' ) }
				</Badge>
			</div>
			<p
				className={ cx(
					'zs-lic-live',
					'warn' === line.tone && 'zs-lic-live-warn'
				) }
			>
				<span
					className={ cx(
						'zs-pdot',
						'warn' === line.tone && 'zs-pdot-off'
					) }
					aria-hidden="true"
				/>
				<span>{ line.text }</span>
			</p>
			<dl className="zs-kv zs-lic-kv">
				{ licenseRows( license ).map( ( [ label, value, mono ] ) => (
					<div className="zs-kv-row" key={ label }>
						<dt>{ label }</dt>
						<dd className={ mono ? 'zs-mono' : undefined }>
							{ value }
						</dd>
					</div>
				) ) }
			</dl>
			<div className="zs-lic-rm">
				<b id="zs-lic-early">{ __( 'Early access', 'zero-spam' ) }</b>
				<ul aria-labelledby="zs-lic-early">
					{ roadmap().map( ( [ icon, title ] ) => (
						<li key={ title }>
							<Icon name={ icon } size="sm" />
							<span>{ title }</span>
						</li>
					) ) }
				</ul>
				<button
					type="button"
					className="zs-linkbtn"
					onClick={ openEarly }
				>
					{ __( "What's next for members", 'zero-spam' ) }{ ' ' }
					<span aria-hidden="true">→</span>
				</button>
			</div>
			<details className="zs-lic-adv">
				<summary>{ __( 'Advanced', 'zero-spam' ) }</summary>
				{ canRemoveKey( license ) ? (
					<>
						<p className="zs-hint">
							{ __(
								'Removing the key switches this site back to Basic protection.',
								'zero-spam'
							) }
						</p>
						{ asking ? (
							<div
								className="zs-inline-row"
								role="group"
								aria-labelledby="zs-lic-q"
							>
								<span id="zs-lic-q">
									{ __(
										'Remove the license key from this site?',
										'zero-spam'
									) }
								</span>
								<Button
									variant="secondary"
									size="sm"
									onClick={ remove }
									disabled={ busy }
								>
									{ busy
										? __( 'Removing…', 'zero-spam' )
										: __( 'Remove key', 'zero-spam' ) }
								</Button>
								<Button
									variant="ghost"
									size="sm"
									disabled={ busy }
									onClick={ () => {
										setAsking( false );
										window.requestAnimationFrame( () =>
											removeBtn.current?.focus()
										);
									} }
								>
									{ __( 'Cancel', 'zero-spam' ) }
								</Button>
							</div>
						) : (
							<button
								ref={ removeBtn }
								type="button"
								className="zs-linkbtn zs-linkbtn-quiet"
								onClick={ () => setAsking( true ) }
							>
								{ __( 'Remove license key', 'zero-spam' ) }
							</button>
						) }
						{ error && (
							<p className="zs-field-err" role="alert">
								<Icon name="warn" size="sm" />
								<span>{ error }</span>
							</p>
						) }
					</>
				) : (
					<p className="zs-hint">
						{ 'constant' === license?.key?.source
							? __(
									'The key is set in wp-config.php (ZEROSPAM_LICENSE_KEY) and can only be removed there.',
									'zero-spam'
								)
							: __(
									'The key is set by your network administrator and can only be removed in the network settings.',
									'zero-spam'
								) }
					</p>
				) }
			</details>
		</div>
	);
}

/**
 * The level panel of the Enhanced Protection card.
 *
 * @param {Object}                          props             Props.
 * @param {number|null}                     props.latency     Milliseconds of the last self-test lookup.
 * @param {boolean}                         props.locked      The license key is locked by the network.
 * @param {(...args: unknown[]) => unknown} [props.onChanged] After the key changed (the schema reloads).
 */
export function LevelPanel( { latency, locked, onChanged } ) {
	const { enhanced, license, licenseLoaded } = useApp();
	if ( ! licenseLoaded ) {
		return null;
	}
	if ( enhanced ) {
		return <LicensePanel latency={ latency } onChanged={ onChanged } />;
	}
	// A working key with Enhanced switched off: the switch on the card turns it on.
	if (
		license?.key?.set &&
		'invalid' !== license.status &&
		'none' !== license.status
	) {
		return (
			<p className="zs-notice" role="note">
				<Icon name="info" size="sm" />
				<span>
					{ __(
						'Your license key is saved, but Enhanced Protection is switched off. Turn it on below.',
						'zero-spam'
					) }
				</span>
			</p>
		);
	}
	return <BasicPanel locked={ locked } onChanged={ onChanged } />;
}
