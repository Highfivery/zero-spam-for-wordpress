/**
 * Rules (prototype v18): the page heading and six tabs (?tab=) — Blocked
 * IPs, Blocked locations, Allowed IPs, Email domains, Words and No-lockout
 * addresses — each with its count. The list editors' unsaved changes live
 * here, so they survive a change of tab, and one unsaved-changes bar saves
 * them all.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	createInterpolateElement,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ExtLink, cx } from '../app/primitives';
import { UnsavedBar, useFeedback } from '../app/primitives/feedback';
import { NS, PATHS, boot, request, useResource } from '../data/api';
import { num } from '../data/format';
import { useTab } from '../data/routes';
import { checkMinLength } from '../data/validate';
import { errorMessage } from '../activity/undo';
import { Blocks } from './blocks';
import { ConfirmAction, ListEditor, editState, runAction } from './list-editor';
import { changeCount, emptyDraft, requestBody } from './list-diff';
import { NoLockout } from './no-lockout';
import { LockBadge } from './parts';
import { KIND_OF_TAB, LIST_OF_TAB, TABS } from './query';

/** @return {Object<string, string>} Tab names. */
export const tabNames = () => ( {
	ips: __( 'Blocked IPs', 'zero-spam' ),
	locations: __( 'Blocked locations', 'zero-spam' ),
	allowlist: __( 'Allowed IPs', 'zero-spam' ),
	email: __( 'Email domains', 'zero-spam' ),
	words: __( 'Words', 'zero-spam' ),
	'no-lockout': __( 'No-lockout addresses', 'zero-spam' ),
} );

const LISTS = Object.values( LIST_OF_TAB );

/** @return {Object} No changes to any list. */
const noDrafts = () =>
	Object.fromEntries( LISTS.map( ( l ) => [ l, emptyDraft() ] ) );

/**
 * The minimum disallowed word length row (Words tab).
 *
 * @param {Object}                          props          Props.
 * @param {Object|undefined}                props.saved    min_length from the list route.
 * @param {boolean}                         props.custom   The list was customized for this site.
 * @param {string|null}                     props.value    Unsaved value.
 * @param {(...args: unknown[]) => unknown} props.onChange Receives the value.
 * @param {string}                          [props.error]  What is wrong.
 */
function MinLength( { saved, custom, value, onChange, error } ) {
	const state = editState( saved, custom );
	const id = 'zs-minlen';
	return (
		<div className="zs-setrow zs-rrow">
			<div className="zs-desc">
				<label className="zs-st-t" htmlFor={ id }>
					{ __( 'Minimum disallowed word length', 'zero-spam' ) }
				</label>
				<p id={ `${ id }-d` }>
					{ __(
						'Only check spam words that are at least this many characters long. Very short words can match normal text and cause false alarms. Set to 0 to check every word.',
						'zero-spam'
					) }
				</p>
				<p className="zs-rec">
					{ __( 'Recommended: 4', 'zero-spam' ) }
				</p>
			</div>
			<div className="zs-ctl">
				{ state.locked && <LockBadge /> }
				<input
					id={ id }
					className="zs-input zs-mono zs-num-in"
					type="number"
					min="0"
					max="100"
					step="1"
					inputMode="numeric"
					value={ value ?? String( saved?.value ?? 0 ) }
					disabled={ ! state.editable }
					aria-invalid={ error ? 'true' : 'false' }
					aria-describedby={ `${ id }-d ${ id }-e` }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
				<span className="zs-ferr" id={ `${ id }-e` } role="alert">
					{ error || '' }
				</span>
			</div>
		</div>
	);
}

/**
 * The Rules area.
 */
export function Rules() {
	const { toast } = useFeedback();
	const [ tab, setTab ] = useTab( 'ips', TABS );
	const schema = useResource( PATHS.schema );
	const [ counts, setCounts ] = useState( () => ( {
		...( boot.rules?.counts || {} ),
	} ) );
	const [ drafts, setDrafts ] = useState( noDrafts );
	const [ minDraft, setMinDraft ] = useState( null );
	const [ errors, setErrors ] = useState( {} );
	const [ saving, setSaving ] = useState( false );
	const [ wordState, setWordState ] = useState( null );
	const reloaders = useRef( {} );
	const tabs = useRef( null );
	const panel = useRef( null );
	const names = tabNames();

	const count = useCallback(
		( key ) => ( n ) =>
			setCounts( ( c ) => ( c[ key ] === n ? c : { ...c, [ key ]: n } ) ),
		[]
	);

	const minChanged =
		null !== minDraft &&
		undefined !== wordState?.min_length &&
		String( wordState.min_length.value ) !== String( minDraft ).trim();
	const changes =
		LISTS.reduce( ( n, l ) => n + changeCount( drafts[ l ] ), 0 ) +
		( minChanged ? 1 : 0 );

	// Unsaved changes are not lost by leaving the page by accident.
	useEffect( () => {
		if ( ! changes ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ changes ] );

	const discard = () => {
		setDrafts( noDrafts() );
		setMinDraft( null );
		setErrors( {} );
		toast( __( 'Changes discarded', 'zero-spam' ) );
	};

	const save = async () => {
		const failed = {};
		let min = null;
		if ( minChanged ) {
			const checked = checkMinLength( minDraft );
			if ( checked.error ) {
				setErrors( { min: checked.error } );
				if ( 'words' !== tab ) {
					setTab( 'words' );
				}
				window.requestAnimationFrame( () =>
					document.getElementById( 'zs-minlen' )?.focus()
				);
				return;
			}
			min = checked.value;
		}

		setSaving( true );
		let saved = 0;
		for ( const list of LISTS ) {
			const withMin = 'allowed-words' === list ? min : null;
			if ( ! changeCount( drafts[ list ] ) && null === withMin ) {
				continue;
			}
			try {
				await request( {
					path: `${ NS }/rules/lists/${ list }`,
					method: 'PUT',
					data: requestBody( drafts[ list ], withMin ),
				} );
				saved++;
				setDrafts( ( d ) => ( { ...d, [ list ]: emptyDraft() } ) );
				if ( null !== withMin ) {
					setMinDraft( null );
				}
				reloaders.current[ list ]?.();
			} catch ( error ) {
				const params = error?.data?.params;
				failed[ list ] =
					( params && Object.values( params ).join( ' ' ) ) ||
					errorMessage(
						error,
						__(
							'The changes could not be saved. Please try again.',
							'zero-spam'
						)
					);
				if ( params?.min_length ) {
					failed.min = params.min_length;
				}
			}
		}
		setSaving( false );
		setErrors( failed );

		if ( Object.keys( failed ).length ) {
			toast(
				__(
					'Some changes could not be saved. The lists with problems show what is wrong.',
					'zero-spam'
				)
			);
		} else if ( saved ) {
			toast( __( 'Changes saved', 'zero-spam' ) );
		}
	};

	const onKey = ( event ) => {
		const i = TABS.indexOf( tab );
		let next = null;
		if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
			next = TABS[ ( i + 1 ) % TABS.length ];
		} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
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
				tabs.current?.querySelector( `#zs-rt-${ next }` )?.focus()
			);
		}
	};

	const list = LIST_OF_TAB[ tab ];
	const editor = list && (
		<ListEditor
			key={ list }
			list={ list }
			draft={ drafts[ list ] }
			setDraft={ ( d ) =>
				setDrafts( ( all ) => ( { ...all, [ list ]: d } ) )
			}
			saveError={ errors[ list ] }
			onCount={ count( tab ) }
			onReload={ ( fn ) => {
				reloaders.current[ list ] = fn;
			} }
			onState={ 'allowed-words' === list ? setWordState : undefined }
		>
			{ ( { state, data, reload } ) => (
				<>
					{ 'email-domains' === list && (
						<div className="zs-setrow zs-rrow">
							<div className="zs-desc">
								<b className="zs-st-t" id="zs-rec-domains-t">
									{ __(
										'Use blocked email domains recommendation',
										'zero-spam'
									) }
								</b>
								<p>
									{ __(
										"Replaces your blocked email domains with Zero Spam's recommended list.",
										'zero-spam'
									) }{ ' ' }
									<strong>
										{ __(
											'Any domains you added yourself are removed.',
											'zero-spam'
										) }
									</strong>
								</p>
							</div>
							<div className="zs-ctl">
								<ConfirmAction
									id="zs-rec-domains"
									label={ __(
										'Replace with recommended domains',
										'zero-spam'
									) }
									question={ sprintf(
										/* translators: %s: number of domains */
										_n(
											'Replace your %s blocked email domain with the recommended list?',
											'Replace your %s blocked email domains with the recommended list?',
											Number( data?.total ) || 0,
											'zero-spam'
										),
										num( Number( data?.total ) || 0 )
									) }
									confirm={ __(
										'Replace domains',
										'zero-spam'
									) }
									disabled={
										! state.editable ||
										changeCount( drafts[ list ] ) > 0
									}
									why={
										state.editable &&
										changeCount( drafts[ list ] ) > 0
											? __(
													'Save or discard your changes to this list first.',
													'zero-spam'
												)
											: ''
									}
									onRun={ () =>
										runAction(
											'update-blocked-email-domains'
										).then( ( answer ) => {
											reload();
											return sprintf(
												/* translators: %s: number of domains */
												_n(
													'Your blocked email domains now match the recommended list (%s domain).',
													'Your blocked email domains now match the recommended list (%s domains).',
													Number( answer?.count ) ||
														0,
													'zero-spam'
												),
												num(
													Number( answer?.count ) || 0
												)
											);
										} )
									}
								/>
							</div>
						</div>
					) }
					{ 'allowed-words' === list && (
						<>
							<MinLength
								saved={ data?.min_length }
								custom={ state.editable && ! state.locked }
								value={ minDraft }
								onChange={ ( v ) => {
									setMinDraft( v );
									setErrors( ( e ) => ( { ...e, min: '' } ) );
								} }
								error={ errors.min }
							/>
							<div className="zs-setrow zs-rrow">
								<div className="zs-desc">
									<b className="zs-st-t">
										{ __(
											'Update core disallowed words',
											'zero-spam'
										) }
									</b>
									<p>
										{ createInterpolateElement(
											__(
												"Replaces WordPress's disallowed words list with <a>splorp's Comment Blacklist for WordPress</a>.",
												'zero-spam'
											),
											{
												a: (
													<ExtLink
														href="https://github.com/splorp/wordpress-comment-blacklist/"
														newTab={ __(
															'(opens in a new tab)',
															'zero-spam'
														) }
													/>
												),
											}
										) }{ ' ' }
										<strong>
											{ __(
												'Any words you added yourself are removed.',
												'zero-spam'
											) }
										</strong>
									</p>
								</div>
								<div className="zs-ctl">
									<ConfirmAction
										id="zs-rec-words"
										label={ __(
											'Replace with recommended words',
											'zero-spam'
										) }
										question={ __(
											"Replace WordPress's disallowed words list with the recommended one?",
											'zero-spam'
										) }
										confirm={ __(
											'Replace words',
											'zero-spam'
										) }
										disabled={ false }
										onRun={ () =>
											runAction(
												'update-disallowed-words'
											).then( () =>
												__(
													"WordPress's disallowed words list now matches the recommended list.",
													'zero-spam'
												)
											)
										}
									/>
								</div>
							</div>
						</>
					) }
				</>
			) }
		</ListEditor>
	);

	return (
		<div className="zs-view zs-rules" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ __( 'Rules.', 'zero-spam' ) }
						<span>
							{ __( 'Who gets in, who doesn’t.', 'zero-spam' ) }
						</span>
					</h1>
					<p>
						{ __(
							'Your own block and allow lists. Allowed addresses skip every check, so you can’t lock yourself out with them.',
							'zero-spam'
						) }
					</p>
				</div>
			</div>

			{ /* eslint-disable-next-line jsx-a11y/interactive-supports-focus -- The tabs take focus (roving tabindex), not the list. */ }
			<div
				ref={ tabs }
				className="zs-seg zs-tabs zs-rtabs"
				role="tablist"
				aria-label={ __( 'Rule lists', 'zero-spam' ) }
				onKeyDown={ onKey }
			>
				{ TABS.map( ( t ) => (
					<button
						key={ t }
						id={ `zs-rt-${ t }` }
						type="button"
						role="tab"
						aria-selected={ t === tab ? 'true' : 'false' }
						aria-controls="zs-rules-body"
						tabIndex={ t === tab ? 0 : -1 }
						onClick={ () => setTab( t ) }
					>
						{ names[ t ] }
						{ undefined !== counts[ t ] && (
							<span className="zs-c">{ num( counts[ t ] ) }</span>
						) }
					</button>
				) ) }
			</div>

			<div
				ref={ panel }
				id="zs-rules-body"
				role="tabpanel"
				aria-labelledby={ `zs-rt-${ tab }` }
				tabIndex={ -1 }
				className={ cx( 'zs-areabody', `zs-rules-${ tab }` ) }
			>
				{ KIND_OF_TAB[ tab ] && (
					<Blocks
						key={ tab }
						kind={ KIND_OF_TAB[ tab ] }
						schema={ schema.data }
						onCount={ count( tab ) }
						panel={ panel }
					/>
				) }
				{ editor }
				{ 'no-lockout' === tab && (
					<NoLockout onCount={ count( tab ) } panel={ panel } />
				) }
			</div>

			{ changes > 0 && (
				<UnsavedBar
					message={ sprintf(
						/* translators: %s: number of unsaved changes */
						_n(
							'%s unsaved change',
							'%s unsaved changes',
							changes,
							'zero-spam'
						),
						num( changes )
					) }
					saving={ saving }
					onSave={ save }
					onDiscard={ discard }
				/>
			) }
		</div>
	);
}
