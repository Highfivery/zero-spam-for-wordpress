/**
 * The Settings area's own cards and controls: display preferences (applied
 * live), rescue mode, the no-lockout addresses, the alert tests, the next
 * quota reset and the system report.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp, useMedia } from '../app/context';
import { Badge, Button, Icon, Switch, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import {
	copyText,
	environmentLine,
	systemReport,
	systemRows,
} from '../app/system-report';
import { NS, boot, nowLocal, request, useResource } from '../data/api';
import { areaUrl } from '../data/routes';
import { themePref } from '../data/theme';
import {
	applyDisplay,
	formatDay,
	isDate,
	quotaPeriod,
	setRootAttributes,
	todayOf,
} from './models';

/**
 * The user's display preferences: theme and reduced animations. Each
 * change applies at once and is saved for this user (ui/prefs), with Undo;
 * they are not part of the site's unsaved changes.
 */
export function DisplayPrefs() {
	const { root, prefs, savePrefs, osReduce } = useApp();
	const { toast } = useFeedback();
	const theme = themePref( prefs.theme );
	const reduce = true === prefs.reduce_motion;
	const osDark = useMedia( '(prefers-color-scheme: dark)' );

	const names = {
		light: __( 'Light', 'zero-spam' ),
		dark: __( 'Dark', 'zero-spam' ),
		auto: __( 'Match my device', 'zero-spam' ),
	};

	/**
	 * Applies and saves a change.
	 *
	 * @param {Object} changes { theme?, reduce_motion? }.
	 * @param {string} message Toast text.
	 */
	const change = ( changes, message ) => {
		const before = { theme, reduce_motion: reduce };
		const next = applyDisplay( prefs, changes, osDark, osReduce );
		setRootAttributes( root, next.attributes );
		savePrefs( changes ).catch( () => {} );
		toast( message, {
			undo: () => {
				setRootAttributes(
					root,
					applyDisplay( prefs, before, osDark, osReduce ).attributes
				);
				savePrefs( before ).catch( () => {} );
			},
		} );
	};

	let motionHint = __( 'Off', 'zero-spam' );
	if ( osReduce ) {
		motionHint = __(
			'On, because your device asks for reduced motion.',
			'zero-spam'
		);
	} else if ( reduce ) {
		motionHint = __( 'On for you on every device.', 'zero-spam' );
	}

	return (
		<>
			<div className="zs-setrow">
				<div className="zs-desc">
					<b className="zs-st-t" id="zs-theme-l">
						{ __( 'Theme', 'zero-spam' ) }
					</b>
					<p id="zs-theme-d">
						{ __(
							'Choose light or dark, or follow your device. The top bar button switches it too.',
							'zero-spam'
						) }
					</p>
					<p className="zs-rec">
						{ __( 'Recommended: Match my device', 'zero-spam' ) }
					</p>
				</div>
				<div className="zs-ctl">
					<div
						className="zs-radios"
						role="radiogroup"
						aria-labelledby="zs-theme-l"
						aria-describedby="zs-theme-d"
					>
						{ [
							[
								'light',
								__( 'Light surfaces everywhere.', 'zero-spam' ),
							],
							[
								'dark',
								__(
									'Dark surfaces, easier at night.',
									'zero-spam'
								),
							],
							[
								'auto',
								__(
									'Follows your operating system.',
									'zero-spam'
								),
							],
						].map( ( [ key, hint ] ) => (
							<label
								className="zs-rcard"
								key={ key }
								htmlFor={ `zs-theme-${ key }` }
							>
								<input
									id={ `zs-theme-${ key }` }
									type="radio"
									name="zs-theme"
									value={ key }
									checked={ theme === key }
									onChange={ () =>
										change(
											{ theme: key },
											sprintf(
												/* translators: %s: theme name, such as Dark */
												__(
													'Theme: %s. Saved for you.',
													'zero-spam'
												),
												names[ key ]
											)
										)
									}
								/>
								<span>
									{ names[ key ] }
									<small>{ hint }</small>
								</span>
							</label>
						) ) }
					</div>
				</div>
			</div>
			<div className="zs-setrow">
				<div className="zs-desc">
					<b className="zs-st-t" id="zs-reduce-l">
						{ __( 'Reduce animations', 'zero-spam' ) }
					</b>
					<p id="zs-reduce-d">
						{ __(
							'Stops the live feed from playing on its own, the brick animations and the chart sweeps. Applies right away.',
							'zero-spam'
						) }
					</p>
				</div>
				<div className="zs-ctl">
					<span className="zs-sw-row">
						<Switch
							id="zs-reduce"
							checked={ osReduce || reduce }
							disabled={ osReduce }
							labelledBy="zs-reduce-l"
							describedBy="zs-reduce-s zs-reduce-d"
							onChange={ ( on ) =>
								change(
									{ reduce_motion: on },
									on
										? __(
												'Animations reduced. Saved for you.',
												'zero-spam'
											)
										: __(
												'Animations back on. Saved for you.',
												'zero-spam'
											)
								)
							}
						/>
						<span className="zs-hint zs-sw-status" id="zs-reduce-s">
							{ motionHint }
						</span>
					</span>
				</div>
			</div>
		</>
	);
}

/**
 * Whether the rescue key is set (ZEROSPAM_RESCUE_KEY, Admin_App::bootstrap()).
 *
 * @return {boolean} Set.
 */
export const rescueKeySet = () => true === boot.settings?.rescueKey;

/**
 * Rescue mode: the emailed link, and the always-on key when it is set.
 */
export function RescuePanel() {
	const set = rescueKeySet();
	return (
		<div className="zs-rescue">
			<div className="zs-notice zs-notice-ok">
				<Icon name="buoy" />
				<div>
					<b>{ __( 'Emailed rescue links are on', 'zero-spam' ) }</b>
					<span>
						{ __(
							'The blocked page offers administrators a one-time link by email. The link expires in 15 minutes and lets that browser in for one hour.',
							'zero-spam'
						) }
					</span>
				</div>
			</div>
			{ set ? (
				<div className="zs-notice zs-notice-ok" id="zs-rescue-key">
					<Icon name="key" />
					<div>
						<b>{ __( 'Active', 'zero-spam' ) }</b>
						<span>
							{ __(
								'Key defined in wp-config.php. Visit any page with ?zerospam_rescue={KEY} appended to get back in for one hour.',
								'zero-spam'
							) }
						</span>
					</div>
				</div>
			) : (
				<div className="zs-notice zs-notice-warn" id="zs-rescue-key">
					<Icon name="warn" />
					<div>
						<b>{ __( 'Rescue key not set', 'zero-spam' ) }</b>
						<span>
							{ __(
								'Rescue key not set — the emailed rescue link still works. Define ZEROSPAM_RESCUE_KEY in wp-config.php for an always-on key.',
								'zero-spam'
							) }
						</span>
					</div>
				</div>
			) }
		</div>
	);
}

/**
 * The addresses Zero Spam never locks out (read-only here; managed in Rules).
 */
export function NoLockout() {
	const list = useResource( `${ NS }/rules/no-lockout` );
	const items = Array.isArray( list.data?.items ) ? list.data.items : [];

	let body;
	if ( list.error && ! list.data ) {
		body = (
			<p className="zs-hint" role="alert">
				{ __( 'The list could not be loaded.', 'zero-spam' ) }{ ' ' }
				<button
					type="button"
					className="zs-linkbtn"
					onClick={ () => list.reload() }
				>
					{ __( 'Try again', 'zero-spam' ) }
				</button>
			</p>
		);
	} else if ( ! list.data ) {
		body = (
			<p className="zs-hint" aria-busy="true">
				{ __( 'Loading…', 'zero-spam' ) }
			</p>
		);
	} else if ( ! items.length ) {
		body = (
			<p className="zs-hint">
				{ __(
					'No addresses saved yet. Zero Spam saves the address of the administrator who activates or updates it, so they can never be locked out.',
					'zero-spam'
				) }
			</p>
		);
	} else {
		body = (
			<ul className="zs-iplist">
				{ items.map( ( item ) => (
					<li key={ item.ip }>
						<span className="zs-mono">{ item.ip }</span>
						{ item.current && (
							<Badge tone="info">
								{ __( 'Your address', 'zero-spam' ) }
							</Badge>
						) }
					</li>
				) ) }
			</ul>
		);
	}

	return (
		<div className="zs-nolock">
			<p>
				{ list.data
					? sprintf(
							/* translators: 1: addresses saved, 2: most addresses kept */
							_n(
								'Administrator addresses that can never be locked out are kept with your rules. %1$d saved (up to %2$d).',
								'Administrator addresses that can never be locked out are kept with your rules. %1$d saved (up to %2$d).',
								items.length,
								'zero-spam'
							),
							items.length,
							list.data.max || 0
						)
					: __(
							'Administrator addresses that can never be locked out are kept with your rules.',
							'zero-spam'
						) }
			</p>
			{ body }
			<div>
				<Button
					variant="secondary"
					size="sm"
					href={ areaUrl( 'rules', { tab: 'no-lockout' } ) }
				>
					{ __( 'Manage in Rules', 'zero-spam' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * The three alert tests (email, admin notice, webhook).
 *
 * @param {Object}  props          Props.
 * @param {Object}  props.entry    The test_alerts schema entry.
 * @param {boolean} props.disabled Disabled.
 */
export function TestAlerts( { entry, disabled } ) {
	const [ state, setState ] = useState( {
		busy: '',
		message: '',
		ok: true,
	} );
	const tests = [
		[ 'email', __( 'Send test email', 'zero-spam' ) ],
		[ 'notice', __( 'Trigger admin notice', 'zero-spam' ) ],
		[ 'webhook', __( 'Send test webhook', 'zero-spam' ) ],
	];

	const run = ( kind ) => {
		setState( { busy: kind, message: '', ok: true } );
		request( {
			path: `${ NS }/settings/actions/test-alert-${ kind }`,
			method: 'POST',
		} )
			.then( ( answer ) =>
				setState( {
					busy: '',
					message: answer?.message || __( 'Done.', 'zero-spam' ),
					ok: true,
				} )
			)
			.catch( ( error ) =>
				setState( {
					busy: '',
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
		<div className="zs-setrow" id="zs-row-test_alerts">
			<div className="zs-desc">
				<b className="zs-st-t" id="zs-t-test_alerts">
					{ entry?.title || __( 'Test Alerts', 'zero-spam' ) }
				</b>
				<p id="zs-d-test_alerts">
					{ __(
						'Send a test alert to verify your configuration is working correctly.',
						'zero-spam'
					) }
				</p>
			</div>
			<div className="zs-ctl">
				<div
					className="zs-inline-row"
					role="group"
					aria-labelledby="zs-t-test_alerts"
				>
					{ tests.map( ( [ kind, label ] ) => (
						<Button
							key={ kind }
							variant="secondary"
							size="sm"
							disabled={ disabled || Boolean( state.busy ) }
							aria-describedby="zs-d-test_alerts"
							onClick={ () => run( kind ) }
						>
							{ state.busy === kind
								? __( 'Sending…', 'zero-spam' )
								: label }
						</Button>
					) ) }
				</div>
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
 * Under the Quota Reset Date control: the next reset it gives, and a
 * button that empties it.
 *
 * @param {Object}                          props          Props.
 * @param {unknown}                         props.value    Current value.
 * @param {(...args: unknown[]) => unknown} props.onClear  Empties the date.
 * @param {boolean}                         props.disabled Disabled (locked).
 */
export function QuotaHint( { value, onClear, disabled } ) {
	const period = quotaPeriod( String( value || '' ), todayOf( nowLocal() ) );
	const set = isDate( value );
	return (
		<div className="zs-subnote" id="zs-quota-next">
			<div className="zs-subnote-b">
				<p className="zs-hint" aria-live="polite">
					{ period
						? sprintf(
								/* translators: 1: date of the next quota reset, 2: date of the last one */
								__(
									'Next reset: %1$s. Quota alerts count from %2$s.',
									'zero-spam'
								),
								formatDay( period.next ),
								formatDay( period.start )
							)
						: __(
								'No reset date set. Quota alerts count today’s use only.',
								'zero-spam'
							) }
				</p>
				{ set && ! disabled && (
					<Button variant="ghost" size="sm" onClick={ onClear }>
						{ __( 'Clear date', 'zero-spam' ) }
					</Button>
				) }
			</div>
		</div>
	);
}

/**
 * The system report, with Copy.
 *
 * @param {Object} props        Props.
 * @param {Object} props.schema settings/schema answer.
 */
export function SystemReport( { schema } ) {
	const { modules, enhanced } = useApp();
	const { toast } = useFeedback();
	const rows = systemRows( { modules, enhanced, schema } );
	const report = systemReport( rows, enhanced );
	return (
		<div className="zs-sysrep">
			<p className="zs-hint">{ environmentLine() }</p>
			<dl className="zs-sysrep-l">
				{ rows.map( ( [ k, v, ok ] ) => (
					<div className="zs-sysrep-r" key={ k }>
						<dt>{ k }</dt>
						<dd>
							<span
								className={ ok ? 'zs-d' : 'zs-d zs-d-off' }
								aria-hidden="true"
							/>
							{ v }
						</dd>
					</div>
				) ) }
			</dl>
			<div className="zs-inline-row">
				<Button
					variant="secondary"
					size="sm"
					icon="copy"
					onClick={ () => copyText( report, toast ) }
				>
					{ __( 'Copy system report', 'zero-spam' ) }
				</Button>
			</div>
		</div>
	);
}
