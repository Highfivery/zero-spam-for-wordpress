/**
 * The list editors: the IP Allowlist (`ip_whitelist`), the blocked email
 * domains and the allowed words (with the shortest disallowed word
 * checked). Each has an add field (checked as typed entries are added),
 * the list a page at a time with a search, and Remove on every entry.
 * Changes wait in the unsaved-changes bar (owned by the Rules area) until
 * they are saved.
 *
 * Network awareness: a list the network administrator has locked is shown
 * read-only with the "Locked by network" badge; on a subsite that uses the
 * network's list, it is shown read-only until "Customize for this site".
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Badge, Button, Icon, Tile, cx } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { NS, request, useResource } from '../data/api';
import { num } from '../data/format';
import { checkEntries } from '../data/validate';
import { errorMessage } from '../activity/undo';
import { listRows, stageAdd, stageRemove, unstageRemove } from './list-diff';
import { EmptyState, LockBadge, Pager, SearchBox } from './parts';
import { listPath, paging } from './query';

/**
 * Copy of each list (approved 6.0 copy).
 *
 * @return {Object<string, Object>} By list.
 */
export const listCopy = () => ( {
	'allowed-ips': {
		icon: 'checkc',
		title: __( 'IP Allowlist', 'zero-spam' ),
		row: __( 'IP allowlist', 'zero-spam' ),
		locked: __( 'Allowlist locked by network', 'zero-spam' ),
		lockedSentence: __(
			'The IP allowlist is locked by the network administrator, so it cannot be changed on this site.',
			'zero-spam'
		),
		rowHidden: true,
		hint: __(
			'IP addresses that should never be blocked. Allowed addresses skip every check.',
			'zero-spam'
		),
		rec: __(
			"Recommended: your office's fixed address, if it has one.",
			'zero-spam'
		),
		add: __( 'Add IP addresses', 'zero-spam' ),
		addHint: __(
			'One or more addresses, separated by spaces or commas. Ranges (CIDR) are not supported.',
			'zero-spam'
		),
		placeholder: __( 'e.g. 203.0.113.5', 'zero-spam' ),
		search: __( 'Search allowed IPs', 'zero-spam' ),
		caption: __( 'Allowed IP addresses', 'zero-spam' ),
		empty: __( 'No allowed IPs yet.', 'zero-spam' ),
		emptyHint: __(
			'Add an address that must never be blocked, such as your office.',
			'zero-spam'
		),
		mono: true,
	},
	'email-domains': {
		icon: 'mail',
		title: __( 'Blocked email domains', 'zero-spam' ),
		row: __( 'Blocked email domains', 'zero-spam' ),
		rowHidden: true,
		hint: __(
			'Block email addresses from these domains (like "spam.com") in comments, registrations and forms.',
			'zero-spam'
		),
		rec: '',
		add: __( 'Add domains', 'zero-spam' ),
		addHint: __(
			'One or more domains, separated by spaces or commas.',
			'zero-spam'
		),
		placeholder: __( 'e.g. mailinator.com', 'zero-spam' ),
		search: __( 'Search domains', 'zero-spam' ),
		caption: __( 'Blocked email domains', 'zero-spam' ),
		empty: __( 'No blocked email domains.', 'zero-spam' ),
		emptyHint: __(
			'Add a domain, or start from the recommended list below.',
			'zero-spam'
		),
		mono: true,
	},
	'allowed-words': {
		icon: 'rules',
		title: __( 'Words', 'zero-spam' ),
		row: __( 'Allowed words', 'zero-spam' ),
		rowHidden: false,
		rec: '',
		hint: __(
			'Sometimes a word on the spam list matches part of a real name, email or website. Add those words here so they aren\'t treated as spam. For example, if your email contains "ugg" and keeps getting blocked, add "ugg".',
			'zero-spam'
		),
		add: __( 'Add allowed words', 'zero-spam' ),
		addHint: __( 'One or more words, separated by commas.', 'zero-spam' ),
		placeholder: __( 'e.g. ugg', 'zero-spam' ),
		search: __( 'Search allowed words', 'zero-spam' ),
		caption: __( 'Allowed words', 'zero-spam' ),
		empty: __( 'No allowed words.', 'zero-spam' ),
		emptyHint: __(
			'Add a word only when the spam list keeps matching part of a real name, email or website.',
			'zero-spam'
		),
		mono: false,
	},
} );

/**
 * Whether a list (or the minimum word length) can be changed here, and why
 * not.
 *
 * @param {Object|undefined} state  { locked, source } from the list route.
 * @param {boolean}          custom "Customize for this site" was chosen.
 * @return {{editable: boolean, locked: boolean, network: boolean}} State.
 */
export function editState( state, custom ) {
	const locked = Boolean( state?.locked );
	const network = ! locked && 'network' === state?.source && ! custom;
	return {
		editable: Boolean( state ) && ! locked && ! network,
		locked,
		network,
	};
}

/**
 * An inline confirmation for an action that replaces a list.
 *
 * @param {Object}                          props          Props.
 * @param {string}                          props.label    Button.
 * @param {string}                          props.question Question.
 * @param {string}                          props.confirm  Confirm button.
 * @param {boolean}                         props.disabled Disabled.
 * @param {string}                          [props.why]    Why it is disabled.
 * @param {(...args: unknown[]) => unknown} props.onRun    Runs the action; resolves to a message.
 * @param {string}                          props.id       ID prefix.
 */
export function ConfirmAction( {
	label,
	question,
	confirm,
	disabled,
	why,
	onRun,
	id,
} ) {
	const [ asking, setAsking ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( { tone: '', text: '' } );
	const opener = useRef( null );
	const yes = useRef( null );

	useEffect( () => {
		if ( asking ) {
			yes.current?.focus();
		}
	}, [ asking ] );

	const close = () => {
		setAsking( false );
		window.requestAnimationFrame( () => opener.current?.focus() );
	};

	const run = () => {
		setBusy( true );
		Promise.resolve()
			.then( onRun )
			.then(
				( text ) => setResult( { tone: 'ok', text } ),
				( error ) =>
					setResult( {
						tone: 'err',
						text: errorMessage(
							error,
							__(
								'That did not work. Please try again.',
								'zero-spam'
							)
						),
					} )
			)
			.finally( () => {
				setBusy( false );
				close();
			} );
	};

	return (
		<div className="zs-actrow">
			{ asking ? (
				<div
					className="zs-confirm-q"
					role="group"
					aria-labelledby={ `${ id }-q` }
				>
					<span id={ `${ id }-q` }>{ question }</span>
					<span className="zs-inline-row">
						<Button
							ref={ yes }
							variant="secondary"
							size="sm"
							disabled={ busy }
							onClick={ run }
						>
							{ busy ? __( 'Working…', 'zero-spam' ) : confirm }
						</Button>
						<Button variant="ghost" size="sm" onClick={ close }>
							{ __( 'Cancel', 'zero-spam' ) }
						</Button>
					</span>
				</div>
			) : (
				<Button
					ref={ opener }
					variant="secondary"
					disabled={ disabled }
					aria-describedby={ why ? `${ id }-why` : undefined }
					onClick={ () => {
						setResult( { tone: '', text: '' } );
						setAsking( true );
					} }
				>
					{ label }
				</Button>
			) }
			{ why && (
				<p className="zs-hint" id={ `${ id }-why` }>
					{ why }
				</p>
			) }
			<p
				className={ cx(
					'zs-actres',
					result.tone && `zs-actres-${ result.tone }`
				) }
				role="status"
				aria-live="polite"
			>
				{ result.text }
			</p>
		</div>
	);
}

/**
 * @param {Object}                            props             Props.
 * @param {string}                            props.list        allowed-ips, email-domains or allowed-words.
 * @param {{add: string[], remove: string[]}} props.draft       Unsaved changes.
 * @param {(...args: unknown[]) => unknown}   props.setDraft    Receives the new draft.
 * @param {string}                            [props.saveError] Error of the last save.
 * @param {(...args: unknown[]) => unknown}   props.onCount     Receives the number of entries.
 * @param {(...args: unknown[]) => unknown}   props.onReload    Receives the reload function (after a save).
 * @param {(...args: unknown[]) => unknown}   [props.onState]   Receives the list's { locked, source, min_length }.
 * @param {unknown}                           [props.children]  Extra rows (minimum length, recommended lists).
 */
export function ListEditor( {
	list,
	draft,
	setDraft,
	saveError = '',
	onCount,
	onReload,
	onState,
	children,
} ) {
	const copy = listCopy()[ list ];
	const { announce } = useFeedback();
	const [ view, setView ] = useState( { search: '', page: 1 } );
	const [ text, setText ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ custom, setCustom ] = useState( false );
	const searchRef = useRef( null );
	const addRef = useRef( null );
	const ids = `zs-le-${ list }`;

	const res = useResource( listPath( list, view ) );
	const data = res.data;
	const loaded = undefined !== data;
	const items = Array.isArray( data?.items ) ? data.items : [];
	const total = Number( data?.total ) || 0;
	const pages = paging( total, view.page, items.length );
	const state = editState( data, custom );

	useEffect( () => {
		onReload( res.reload );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Registered once per list.
	}, [ res.reload ] );

	useEffect( () => {
		if ( loaded && ! view.search ) {
			onCount( total );
		}
		if ( loaded && onState ) {
			onState( {
				locked: Boolean( data.locked ),
				source: data.source,
				min_length: data.min_length,
				custom,
			} );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- onCount and onState are setters.
	}, [ loaded, total, view.search, data, custom ] );

	// A full list is known when it fits on one page: then a repeat is caught here.
	const complete = loaded && ! view.search && total <= items.length;
	const known = new Set(
		complete ? items.map( ( i ) => i.toLowerCase() ) : []
	);

	const add = ( event ) => {
		event.preventDefault();
		const checked = checkEntries( list, text );
		if ( checked.error ) {
			setError( checked.error );
			addRef.current?.focus();
			return;
		}
		const { draft: next, skipped } = stageAdd(
			draft,
			checked.entries,
			( e ) => known.has( e.toLowerCase() )
		);
		setDraft( next );
		setText( '' );
		setError(
			skipped.length
				? sprintf(
						/* translators: %s: comma-separated list of entries */
						__( 'Already on the list: %s', 'zero-spam' ),
						skipped.join( ', ' )
					)
				: ''
		);
		const added = checked.entries.length - skipped.length;
		if ( added > 0 ) {
			announce(
				sprintf(
					/* translators: %s: number of entries */
					_n(
						'%s entry added. Save your changes to keep it.',
						'%s entries added. Save your changes to keep them.',
						added,
						'zero-spam'
					),
					num( added )
				)
			);
		}
	};

	const rows = listRows( items, draft, view.search );
	const filtered = Boolean( view.search );
	const shownTotal = total + draft.add.length - draft.remove.length;

	const clearSearch = () => {
		setView( { search: '', page: 1 } );
		window.requestAnimationFrame( () => searchRef.current?.focus() );
	};

	return (
		<Tile
			className={ cx(
				'zs-scard',
				'zs-listed',
				! state.editable && 'zs-readonly'
			) }
			labelledBy={ `${ ids }-h` }
		>
			<div className="zs-tile-h">
				<div className="zs-scard-t">
					<span className="zs-ico">
						<Icon name={ copy.icon } />
					</span>
					<h2 id={ `${ ids }-h` } className="zs-tile-title">
						{ copy.title }
					</h2>
				</div>
				<span className="zs-scard-s">
					{ state.locked && (
						<LockBadge
							label={ copy.locked }
							sentence={ copy.lockedSentence }
						/>
					) }
				</span>
			</div>

			{ state.locked && (
				<p className="zs-netnote">
					<Icon name="lock" size="sm" />
					<span>
						{ __(
							'The network administrator manages this list for every site. You can see it, but not change it here.',
							'zero-spam'
						) }
					</span>
				</p>
			) }
			{ state.network && (
				<div className="zs-netnote zs-netnote-row">
					<Icon name="info" size="sm" />
					<span>
						{ __(
							"This site uses the network's list. Customize it to keep a list of its own; the network's list stays as it is.",
							'zero-spam'
						) }
					</span>
					<Button
						variant="secondary"
						size="sm"
						onClick={ () => {
							setCustom( true );
							window.requestAnimationFrame( () =>
								addRef.current?.focus()
							);
						} }
					>
						{ __( 'Customize for this site', 'zero-spam' ) }
					</Button>
				</div>
			) }

			<div className="zs-setrow zs-rrow zs-lrow">
				<div className="zs-desc">
					<b
						className={ cx(
							'zs-st-t',
							copy.rowHidden && 'zs-sr-only'
						) }
					>
						{ copy.row }
					</b>
					<p id={ `${ ids }-d` }>{ copy.hint }</p>
					{ copy.rec && <p className="zs-rec">{ copy.rec }</p> }
				</div>
				<div className="zs-ctl">
					{ state.editable && (
						<form className="zs-addrow" noValidate onSubmit={ add }>
							<div className="zs-field">
								<label htmlFor={ `${ ids }-add` }>
									{ copy.add }
								</label>
								<div className="zs-addrow-in">
									<input
										ref={ addRef }
										id={ `${ ids }-add` }
										className={ cx(
											'zs-input',
											copy.mono && 'zs-mono'
										) }
										value={ text }
										placeholder={ copy.placeholder }
										autoComplete="off"
										spellCheck="false"
										aria-invalid={
											error ? 'true' : 'false'
										}
										aria-describedby={ `${ ids }-add-h ${ ids }-add-e` }
										onChange={ ( e ) => {
											setText( e.target.value );
											if ( error ) {
												setError( '' );
											}
										} }
									/>
									<Button
										type="submit"
										variant="secondary"
										icon="plus"
									>
										{ __( 'Add', 'zero-spam' ) }
									</Button>
								</div>
								<span
									className="zs-hint"
									id={ `${ ids }-add-h` }
								>
									{ copy.addHint }
								</span>
								<span
									className="zs-ferr"
									id={ `${ ids }-add-e` }
									role="alert"
								>
									{ error || saveError }
								</span>
							</div>
						</form>
					) }

					<div className="zs-tbar zs-le-bar">
						<SearchBox
							id={ `${ ids }-q` }
							label={ copy.search }
							value={ view.search }
							inputRef={ searchRef }
							onSearch={ ( search ) =>
								setView( { search, page: 1 } )
							}
						/>
						<span className="zs-grow" />
						{ loaded &&
							( draft.add.length > 0 ||
								draft.remove.length > 0 ) && (
								<Badge tone="warn">
									{ sprintf(
										/* translators: %s: number of entries after saving */
										__( '%s after saving', 'zero-spam' ),
										num( Math.max( 0, shownTotal ) )
									) }
								</Badge>
							) }
					</div>

					<div
						className="zs-tbl-wrap zs-cards-wrap"
						aria-busy={ res.loading ? 'true' : undefined }
					>
						{ rows.length > 0 && (
							<ul
								className="zs-cards zs-cards-plain zs-elist"
								aria-label={ copy.caption }
							>
								{ rows.map( ( { entry, state: rowState } ) => (
									<li
										key={ `${ rowState }-${ entry }` }
										className={ cx( `zs-e-${ rowState }` ) }
									>
										<span className="zs-cardmain">
											<span
												className={ cx(
													'zs-cm-t',
													copy.mono && 'zs-mono'
												) }
											>
												{ entry }
											</span>
										</span>
										<span className="zs-card-side">
											{ 'added' === rowState && (
												<Badge tone="ok">
													{ __(
														'New, not saved',
														'zero-spam'
													) }
												</Badge>
											) }
											{ 'removed' === rowState && (
												<Badge tone="warn">
													{ __(
														'Removed, not saved',
														'zero-spam'
													) }
												</Badge>
											) }
											{ state.editable &&
												'removed' === rowState && (
													<Button
														variant="ghost"
														size="sm"
														aria-label={ sprintf(
															/* translators: %s: list entry */
															__(
																'Keep %s',
																'zero-spam'
															),
															entry
														) }
														onClick={ () =>
															setDraft(
																unstageRemove(
																	draft,
																	entry
																)
															)
														}
													>
														{ __(
															'Keep',
															'zero-spam'
														) }
													</Button>
												) }
											{ state.editable &&
												'removed' !== rowState && (
													<Button
														variant="ghost"
														size="sm"
														aria-label={ sprintf(
															/* translators: %s: list entry */
															__(
																'Remove %s',
																'zero-spam'
															),
															entry
														) }
														onClick={ () => {
															setDraft(
																stageRemove(
																	draft,
																	entry
																)
															);
															announce(
																sprintf(
																	/* translators: %s: list entry */
																	__(
																		'%s removed. Save your changes to keep this.',
																		'zero-spam'
																	),
																	entry
																)
															);
														} }
													>
														{ __(
															'Remove',
															'zero-spam'
														) }
													</Button>
												) }
										</span>
									</li>
								) ) }
							</ul>
						) }
						{ loaded && ! rows.length && (
							<EmptyState
								title={
									filtered
										? __(
												'Nothing on this list matches your search.',
												'zero-spam'
											)
										: copy.empty
								}
								hint={
									filtered
										? __(
												'Check the spelling, or clear the search.',
												'zero-spam'
											)
										: copy.emptyHint
								}
								onClear={ filtered ? clearSearch : undefined }
							/>
						) }
						{ res.error && ! loaded && (
							<p className="zs-notice zs-notice-err" role="alert">
								{ errorMessage(
									res.error,
									__(
										'The list could not be loaded. Reload the page to try again.',
										'zero-spam'
									)
								) }
							</p>
						) }
					</div>

					<Pager
						pages={ pages }
						total={ total }
						label={ copy.caption }
						onPage={ ( page ) =>
							setView( ( v ) => ( { ...v, page } ) )
						}
					/>
				</div>
			</div>

			{ children && children( { state, data, reload: res.reload } ) }
		</Tile>
	);
}

/**
 * Runs a settings action (settings/actions/{action}).
 *
 * @param {string} action Action.
 * @return {Promise<Object>} Answer.
 */
export const runAction = ( action ) =>
	request( { path: `${ NS }/settings/actions/${ action }`, method: 'POST' } );
