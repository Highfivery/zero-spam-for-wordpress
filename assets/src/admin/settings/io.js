/**
 * Import & errors: download the settings as JSON, import a file with a
 * result per setting (applied, skipped because the network locked it,
 * skipped because it is a key and keys were not asked for, invalid), and
 * the error log with "Clear error log".
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, Icon, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { NS, boot, nowLocal, request, useResource } from '../data/api';
import { exportFileName, importResult, readImport, todayOf } from './models';

/** The error log route (preloaded on the Import & errors tab). */
export const ERROR_LOG_PATH = `${ NS }/settings/error-log`;

/**
 * Moves focus once the element it waits for can take it: after a
 * confirmation closes, focus returns to the button that opened it (or to
 * what replaced the question), never to the top of the page.
 *
 * @param {Array<unknown>} deps Re-check when these change.
 * @return {(target: {current: HTMLElement|null}) => void} Asks for focus on a ref.
 */
function useFocusAfter( deps ) {
	const pending = useRef( null );
	useEffect( () => {
		const el = pending.current?.current;
		if ( el && ! el.disabled ) {
			el.focus();
			pending.current = null;
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- The callers pass what changes the targets.
	}, deps );
	return ( target ) => {
		pending.current = target;
	};
}

/**
 * One group of import results.
 *
 * @param {Object}   props        Props.
 * @param {string}   props.title  Heading.
 * @param {Object[]} props.items  { key, title, message? }.
 * @param {string}   props.tone   ok, warn or err.
 * @param {string}   [props.hint] A line under the heading.
 */
function ResultGroup( { title, items, tone, hint } ) {
	if ( ! items.length ) {
		return null;
	}
	return (
		<div className={ cx( 'zs-imp-g', `zs-imp-${ tone }` ) }>
			<b>
				{ title } <span className="zs-c">{ items.length }</span>
			</b>
			{ hint && <p className="zs-hint">{ hint }</p> }
			<ul>
				{ items.map( ( item ) => (
					<li key={ item.key }>
						<span>{ item.title }</span>
						{ item.message && (
							<span className="zs-hint"> — { item.message }</span>
						) }
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * Export and import.
 *
 * @param {Object}                          props           Props.
 * @param {Object<string, Object>}          props.entries   Schema entries by key.
 * @param {(...args: unknown[]) => unknown} props.onApplied After an import stored something.
 * @param {number}                          props.unsaved   Unsaved changes on the page.
 */
export function ImportExport( { entries, onApplied, unsaved } ) {
	const { toast, announce } = useFeedback();
	const file = useRef( null );
	const [ busy, setBusy ] = useState( '' );
	const [ secrets, setSecrets ] = useState( false );
	const [ chosen, setChosen ] = useState( null );
	const [ confirm, setConfirm ] = useState( false );
	const [ problem, setProblem ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const importBtn = useRef( null );
	const results = useRef( null );
	const focusAfter = useFocusAfter( [ confirm, busy, result, problem ] );

	const download = () => {
		setBusy( 'export' );
		request( { path: `${ NS }/settings/export` } )
			.then( ( data ) => {
				const blob = new window.Blob(
					[ JSON.stringify( data, null, 2 ) ],
					{ type: 'application/json' }
				);
				const url = window.URL.createObjectURL( blob );
				const link = document.createElement( 'a' );
				link.href = url;
				link.download = exportFileName(
					boot.site.url,
					todayOf( nowLocal() )
				);
				document.body.appendChild( link );
				link.click();
				link.remove();
				window.setTimeout( () => window.URL.revokeObjectURL( url ), 0 );
				toast( __( 'Settings downloaded', 'zero-spam' ) );
			} )
			.catch( ( error ) =>
				toast(
					error?.message ||
						__(
							'The settings could not be downloaded. Please try again.',
							'zero-spam'
						)
				)
			)
			.finally( () => setBusy( '' ) );
	};

	const pick = ( event ) => {
		const picked = event.target.files?.[ 0 ];
		setResult( null );
		setProblem( '' );
		setConfirm( false );
		if ( ! picked ) {
			setChosen( null );
			return;
		}
		picked.text().then(
			( text ) => {
				const read = readImport( text );
				if ( ! read.ok ) {
					setChosen( null );
					setProblem( read.message );
					return;
				}
				setChosen( { name: picked.name, body: read.body } );
			},
			() =>
				setProblem(
					__(
						'The file could not be read. Please try again.',
						'zero-spam'
					)
				)
		);
	};

	const run = () => {
		setConfirm( false );
		focusAfter( importBtn );
		setBusy( 'import' );
		setProblem( '' );
		request( {
			path: `${ NS }/settings/import${
				secrets ? '?include_secrets=1' : ''
			}`,
			method: 'POST',
			body: chosen.body,
			headers: { 'Content-Type': 'application/json' },
		} )
			.then( ( answer ) => {
				const mapped = importResult( answer, entries );
				setResult( mapped );
				const n = mapped.applied.length;
				announce(
					sprintf(
						/* translators: 1: settings imported, 2: settings skipped or invalid */
						__(
							'Import finished: %1$d applied, %2$d not applied.',
							'zero-spam'
						),
						n,
						mapped.locked.length +
							mapped.secrets.length +
							mapped.errors.length
					)
				);
				focusAfter( results );
				if ( mapped.changed > 0 ) {
					onApplied();
				}
				setChosen( null );
				if ( file.current ) {
					file.current.value = '';
				}
			} )
			.catch( ( error ) => {
				const message =
					error?.data?.params?.body ||
					error?.message ||
					__(
						'The settings could not be imported. Nothing was changed.',
						'zero-spam'
					);
				setProblem( message );
				focusAfter( importBtn );
				announce( message, 'assertive' );
			} )
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="zs-io">
			<div className="zs-field">
				<b className="zs-lbl-strong" id="zs-exp-l">
					{ __( 'Export settings', 'zero-spam' ) }
				</b>
				<p className="zs-hint" id="zs-exp-d">
					{ __(
						'Downloads every setting this site uses as a JSON file. License and API keys and webhook addresses are never exported.',
						'zero-spam'
					) }
				</p>
				<div>
					<Button
						variant="secondary"
						size="sm"
						icon="down"
						aria-describedby="zs-exp-d"
						disabled={ 'export' === busy }
						onClick={ download }
					>
						{ 'export' === busy
							? __( 'Preparing…', 'zero-spam' )
							: __( 'Download settings', 'zero-spam' ) }
					</Button>
				</div>
			</div>
			<div className="zs-field">
				<b className="zs-lbl-strong" id="zs-imp-l">
					{ __( 'Import settings', 'zero-spam' ) }
				</b>
				<p className="zs-hint" id="zs-imp-d">
					{ __(
						'Choose a settings file exported from Zero Spam on this or another site. The settings in the file replace yours; settings your network administrator locked are skipped.',
						'zero-spam'
					) }
				</p>
				<div className="zs-filepick">
					{ /* The real file input, visually hidden inside a button-styled label: keyboard, screen readers and the browser's picker work as usual. */ }
					<label
						className="zs-btn zs-btn-secondary zs-btn-sm zs-filebtn"
						htmlFor="zs-imp-file"
					>
						<input
							ref={ file }
							id="zs-imp-file"
							className="zs-file"
							type="file"
							accept="application/json,.json"
							aria-labelledby="zs-imp-l zs-imp-t"
							aria-describedby="zs-imp-n zs-imp-d"
							onChange={ pick }
						/>
						<Icon name="up" size="sm" />
						<span id="zs-imp-t">
							{ __( 'Choose a file…', 'zero-spam' ) }
						</span>
					</label>
					<span className="zs-hint zs-mono" id="zs-imp-n">
						{ chosen
							? chosen.name
							: __( 'No file chosen', 'zero-spam' ) }
					</span>
				</div>
				<label className="zs-check" htmlFor="zs-imp-secrets">
					<input
						id="zs-imp-secrets"
						type="checkbox"
						checked={ secrets }
						onChange={ ( event ) =>
							setSecrets( event.target.checked )
						}
					/>
					<span>
						{ __(
							'Also import license and API keys and webhook addresses found in the file',
							'zero-spam'
						) }
					</span>
				</label>
				{ confirm ? (
					<div
						className="zs-inline-row"
						role="group"
						aria-labelledby="zs-imp-q"
					>
						<span id="zs-imp-q">
							{ unsaved > 0
								? sprintf(
										/* translators: %d: number of unsaved changes */
										_n(
											'This replaces the settings in the file, and discards %d unsaved change. Continue?',
											'This replaces the settings in the file, and discards %d unsaved changes. Continue?',
											unsaved,
											'zero-spam'
										),
										unsaved
									)
								: __(
										'This replaces the settings in the file. Continue?',
										'zero-spam'
									) }
						</span>
						<Button
							variant="secondary"
							size="sm"
							onClick={ run }
							// eslint-disable-next-line jsx-a11y/no-autofocus -- The confirmation replaces the button that had focus.
							autoFocus
						>
							{ __( 'Import', 'zero-spam' ) }
						</Button>
						<Button
							variant="ghost"
							size="sm"
							onClick={ () => {
								setConfirm( false );
								focusAfter( importBtn );
							} }
						>
							{ __( 'Cancel', 'zero-spam' ) }
						</Button>
					</div>
				) : (
					<div>
						<Button
							variant="secondary"
							size="sm"
							ref={ importBtn }
							icon="up"
							disabled={ ! chosen || 'import' === busy }
							onClick={ () => setConfirm( true ) }
						>
							{ 'import' === busy
								? __( 'Importing…', 'zero-spam' )
								: __( 'Import settings', 'zero-spam' ) }
						</Button>
					</div>
				) }
				{ problem && (
					<p className="zs-field-err" role="alert">
						<Icon name="warn" size="sm" />
						<span>{ problem }</span>
					</p>
				) }
				<div
					ref={ results }
					tabIndex={ -1 }
					className="zs-imp-res"
					role="region"
					aria-label={ __( 'Import results', 'zero-spam' ) }
					hidden={ ! result }
				>
					{ result && (
						<>
							<p className="zs-imp-sum">
								{ sprintf(
									/* translators: 1: settings applied, 2: settings changed by the import */
									_n(
										'%1$d setting applied (%2$d changed).',
										'%1$d settings applied (%2$d changed).',
										result.applied.length,
										'zero-spam'
									),
									result.applied.length,
									result.changed
								) }
							</p>
							<ResultGroup
								title={ __( 'Applied', 'zero-spam' ) }
								items={ result.applied }
								tone="ok"
							/>
							<ResultGroup
								title={ __(
									'Skipped: locked by your network administrator',
									'zero-spam'
								) }
								items={ result.locked }
								tone="warn"
							/>
							<ResultGroup
								title={ __(
									'Skipped: keys and webhook addresses',
									'zero-spam'
								) }
								hint={ __(
									'Tick “Also import license and API keys” and import again to use them.',
									'zero-spam'
								) }
								items={ result.secrets }
								tone="warn"
							/>
							<ResultGroup
								title={ __(
									'Not applied: invalid',
									'zero-spam'
								) }
								items={ result.errors }
								tone="err"
							/>
							<ResultGroup
								title={ __(
									'Ignored: not settings',
									'zero-spam'
								) }
								items={ result.ignored }
								tone="info"
							/>
						</>
					) }
				</div>
			</div>
		</div>
	);
}

/**
 * The error log: its last 64 KB, Refresh and Clear error log.
 */
export function ErrorLog() {
	const { toast, announce } = useFeedback();
	const log = useResource( ERROR_LOG_PATH );
	const [ confirm, setConfirm ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const clearBtn = useRef( null );
	const refreshBtn = useRef( null );
	const text = log.data?.log || '';
	const focusAfter = useFocusAfter( [ confirm, busy, text ] );
	const kb = Math.ceil( ( log.data?.size || 0 ) / 1024 );

	const clear = () => {
		setConfirm( false );
		focusAfter( clearBtn );
		setBusy( true );
		request( {
			path: `${ NS }/settings/actions/clear-error-log`,
			method: 'POST',
		} )
			.then( () => {
				log.setData( { log: '', size: 0, truncated: false } );
				// "Clear error log" is off once the log is empty.
				focusAfter( refreshBtn );
				toast( __( 'Error log cleared', 'zero-spam' ) );
			} )
			.catch( ( error ) =>
				announce(
					error?.message ||
						__(
							'The error log could not be cleared. Please try again.',
							'zero-spam'
						),
					'assertive'
				)
			)
			.finally( () => setBusy( false ) );
	};

	let note = __(
		'No errors logged. Zero Spam writes here when a service it asks does not answer, and when debug is on.',
		'zero-spam'
	);
	if ( log.error && ! log.data ) {
		note = __( 'The error log could not be loaded.', 'zero-spam' );
	} else if ( ! log.data ) {
		note = __( 'Loading…', 'zero-spam' );
	} else if ( log.data.truncated ) {
		note = sprintf(
			/* translators: %d: size of the log in kilobytes */
			__( '%d KB in total; showing the latest 64 KB.', 'zero-spam' ),
			kb
		);
	} else if ( text ) {
		note = sprintf(
			/* translators: %d: size of the log in kilobytes */
			__( '%d KB.', 'zero-spam' ),
			kb
		);
	}

	return (
		<div className="zs-errlog">
			<div className="zs-field">
				{ text ? (
					<label className="zs-lbl-strong" htmlFor="zs-errlog-box">
						{ __( 'Recent errors', 'zero-spam' ) }
					</label>
				) : (
					<b className="zs-lbl-strong">
						{ __( 'Recent errors', 'zero-spam' ) }
					</b>
				) }
				{ text ? (
					<textarea
						id="zs-errlog-box"
						className="zs-textarea zs-mono"
						rows={ 8 }
						readOnly
						value={ text }
						aria-describedby="zs-errlog-n"
					/>
				) : (
					<p
						className="zs-empty-line"
						aria-busy={ log.loading ? 'true' : undefined }
					>
						{ __( 'The error log is empty.', 'zero-spam' ) }
					</p>
				) }
				<p className="zs-hint" id="zs-errlog-n" aria-live="polite">
					{ note }
				</p>
			</div>
			{ confirm ? (
				<div
					className="zs-inline-row"
					role="group"
					aria-labelledby="zs-errlog-q"
				>
					<span id="zs-errlog-q">
						{ __(
							'This deletes the error log for good. Continue?',
							'zero-spam'
						) }
					</span>
					<Button
						variant="secondary"
						size="sm"
						onClick={ clear }
						// eslint-disable-next-line jsx-a11y/no-autofocus -- The confirmation replaces the button that had focus.
						autoFocus
					>
						{ __( 'Clear', 'zero-spam' ) }
					</Button>
					<Button
						variant="ghost"
						size="sm"
						onClick={ () => {
							setConfirm( false );
							focusAfter( clearBtn );
						} }
					>
						{ __( 'Cancel', 'zero-spam' ) }
					</Button>
				</div>
			) : (
				<div className="zs-inline-row">
					<Button
						variant="secondary"
						size="sm"
						ref={ refreshBtn }
						icon="refresh"
						disabled={ log.loading }
						onClick={ () => log.reload() }
					>
						{ __( 'Refresh', 'zero-spam' ) }
					</Button>
					<Button
						variant="secondary"
						size="sm"
						ref={ clearBtn }
						icon="trash"
						disabled={ ! text || busy }
						onClick={ () => setConfirm( true ) }
					>
						{ busy
							? __( 'Clearing…', 'zero-spam' )
							: __( 'Clear error log', 'zero-spam' ) }
					</Button>
				</div>
			) }
		</div>
	);
}
