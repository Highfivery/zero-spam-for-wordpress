/**
 * Protection (prototype v18): Detection, Integrations and Intelligence
 * tabs (?tab=), a card per settings section built from the settings schema
 * and protection/modules, "Test my protection", and the unsaved-changes
 * bar. ?card={section} opens the page on a card (the Compare drawer's
 * "I already have a license key", the Overview's attention items).
 *
 * Everything the page reads on load is preloaded (Admin_App::
 * PROTECTION_PRELOADS and the shell's): no request of its own on load.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useReducer, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { Button, Icon, cx } from '../app/primitives';
import { UnsavedBar, useFeedback } from '../app/primitives/feedback';
import { PATHS, useResource } from '../data/api';
import { useTab } from '../data/routes';
import { Card } from './card';
import { TABS, buildCards, tabNames } from './cards';
import { LevelPanel } from './license';
import { SelfTestPanel, TestButton, runSelfTest } from './self-test';
import { initialTest, networkLatency, testReducer } from './self-test-model';
import { isOn } from './settings-model';
import { useSettings } from './use-settings';

/**
 * The card a ?card= argument asks for, if any.
 *
 * @return {string} Card key.
 */
const cardArg = () => {
	const value = new URLSearchParams( window.location.search ).get( 'card' );
	return value && /^[a-z0-9_-]{1,64}$/i.test( value ) ? value : '';
};

/**
 * Scrolls to a card and focuses it.
 *
 * @param {string}  key     Card key.
 * @param {boolean} reduced Reduced motion.
 */
const goToCard = ( key, reduced ) => {
	const card = document.getElementById( `zs-card-${ key }` );
	if ( ! card ) {
		return;
	}
	const more = card.closest( 'details' );
	if ( more ) {
		more.open = true;
	}
	card.scrollIntoView( {
		block: 'start',
		behavior: reduced ? 'auto' : 'smooth',
	} );
	// A field inside already has focus (the license key form the link opened).
	if ( ! card.contains( card.ownerDocument.activeElement ) ) {
		card.focus( { preventScroll: true } );
	}
};

/**
 * The tab list: arrow keys, Home and End move between tabs (roving tabindex).
 *
 * @param {Object}                          props        Props.
 * @param {string}                          props.tab    Current tab.
 * @param {(...args: unknown[]) => unknown} props.setTab Changes the tab.
 * @param {Object}                          props.counts Unsaved changes per tab.
 */
function Tabs( { tab, setTab, counts } ) {
	const list = useRef( null );
	const names = tabNames();

	const onKey = ( event ) => {
		const i = TABS.indexOf( tab );
		const next = {
			ArrowRight: TABS[ ( i + 1 ) % TABS.length ],
			ArrowLeft: TABS[ ( i - 1 + TABS.length ) % TABS.length ],
			Home: TABS[ 0 ],
			End: TABS[ TABS.length - 1 ],
		}[ event.key ];
		if ( next ) {
			event.preventDefault();
			setTab( next );
			window.requestAnimationFrame( () =>
				list.current?.querySelector( `#zs-pt-${ next }` )?.focus()
			);
		}
	};

	return (
		// eslint-disable-next-line jsx-a11y/interactive-supports-focus -- The tabs take focus (roving tabindex), not the list.
		<div
			ref={ list }
			className="zs-seg zs-tabs"
			role="tablist"
			aria-label={ __( 'Protection areas', 'zero-spam' ) }
			onKeyDown={ onKey }
		>
			{ TABS.map( ( t ) => (
				<button
					key={ t }
					id={ `zs-pt-${ t }` }
					type="button"
					role="tab"
					aria-selected={ t === tab ? 'true' : 'false' }
					aria-controls="zs-prot-body"
					tabIndex={ t === tab ? 0 : -1 }
					onClick={ () => setTab( t ) }
				>
					{ names[ t ] }
					{ counts[ t ] > 0 && (
						<span className="zs-c">
							<span aria-hidden="true">•</span>
							<span className="zs-sr-only">
								{ sprintf(
									/* translators: %d: number of unsaved changes */
									_n(
										'%d unsaved change',
										'%d unsaved changes',
										counts[ t ],
										'zero-spam'
									),
									counts[ t ]
								) }
							</span>
						</span>
					) }
				</button>
			) ) }
		</div>
	);
}

/**
 * "On this page": chips that jump to a card.
 *
 * @param {Object}   props         Props.
 * @param {Object[]} props.cards   Cards.
 * @param {boolean}  props.reduced Reduced motion.
 * @param {boolean}  [props.more]  Add "More integrations".
 */
function Jump( { cards, reduced, more = false } ) {
	if ( cards.length < 2 ) {
		return null;
	}
	return (
		<nav
			className="zs-jump"
			aria-label={ __( 'On this page', 'zero-spam' ) }
		>
			{ cards.map( ( card ) => (
				<button
					type="button"
					className="zs-chip"
					key={ card.key }
					onClick={ () => goToCard( card.key, reduced ) }
				>
					{ card.title }
				</button>
			) ) }
			{ more && (
				<button
					type="button"
					className="zs-chip"
					onClick={ () => {
						const box =
							document.getElementById( 'zs-card-moreint' );
						if ( box ) {
							box.open = true;
							box.scrollIntoView( {
								block: 'start',
								behavior: reduced ? 'auto' : 'smooth',
							} );
							box.querySelector( 'summary' )?.focus();
						}
					} }
				>
					{ __( 'More integrations', 'zero-spam' ) }
				</button>
			) }
		</nav>
	);
}

/**
 * The Protection area.
 */
export function Protection() {
	const app = useApp();
	const { toast, announce } = useFeedback();
	const schema = useResource( PATHS.schema );
	const settings = useSettings( schema );
	const [ tab, setTab ] = useTab( 'detection', TABS );
	const [ test, dispatch ] = useReducer( testReducer, initialTest );
	const opened = useRef( false );

	const cards = useMemo(
		() => buildCards( schema.data, app.modules, tab ),
		[ schema.data, app.modules, tab ]
	);

	// Unsaved changes per tab, shown on the tabs.
	const counts = useMemo( () => {
		const out = {};
		TABS.forEach( ( t ) => {
			out[ t ] = Object.keys( settings.payload ).filter(
				( key ) => settings.entries[ key ]?.group === t
			).length;
		} );
		return out;
	}, [ settings.payload, settings.entries ] );

	// ?card= opens the page on that card, once the cards exist.
	useEffect( () => {
		if ( opened.current || ! schema.data ) {
			return;
		}
		opened.current = true;
		const key = cardArg();
		if ( key ) {
			window.requestAnimationFrame( () => goToCard( key, app.reduced ) );
		}
	}, [ schema.data, app.reduced ] );

	const save = () => {
		settings
			.save()
			.then( ( { undo, saved } ) => {
				const n = Object.keys( saved ).length;
				const message = sprintf(
					/* translators: %d: number of settings */
					_n(
						'%d setting saved',
						'%d settings saved',
						n,
						'zero-spam'
					),
					n
				);
				app.modulesState?.reload?.();
				if (
					Object.keys( saved ).some( ( key ) =>
						key.startsWith( 'zerospam' )
					)
				) {
					app.reloadLicense?.();
				}
				toast(
					message,
					undo
						? {
								undo: () =>
									settings
										.restore( undo )
										.then( () => {
											app.modulesState?.reload?.();
											toast(
												__(
													'Changes undone',
													'zero-spam'
												)
											);
										} )
										.catch( () =>
											toast(
												__(
													'The change could not be undone. Please try again.',
													'zero-spam'
												)
											)
										),
							}
						: {}
				);
			} )
			.catch( ( mapped ) => {
				announce( mapped.message, 'assertive' );
				// Focus the first setting with an error, on any tab.
				const first = Object.keys( mapped.fields )[ 0 ];
				const group = first && settings.entries[ first ]?.group;
				if ( group && group !== tab ) {
					setTab( group );
				}
				window.setTimeout( () => {
					const row =
						first &&
						document.getElementById(
							`zs-row-${ first.replace( /[^A-Za-z0-9_-]/g, '-' ) }`
						);
					const control = row?.querySelector(
						'input:not([disabled]),select:not([disabled]),textarea:not([disabled]),button:not([disabled])'
					);
					if ( control ) {
						control.focus();
						return;
					}
					// Locked (disabled) settings cannot take focus: the bar says what happened.
					const bar = document.querySelector( '.zs-savebar' );
					if ( bar ) {
						bar.setAttribute( 'tabindex', '-1' );
						bar.focus();
					}
				}, 60 );
			} );
	};

	const zerospamKey = settings.entries.zerospam_license;
	const keyLocked = Boolean( zerospamKey?.locked );
	const basicOnIntel =
		'intelligence' === tab && app.licenseLoaded && ! app.enhanced;
	const latency = networkLatency( test );

	/**
	 * Settings shown on the Enhanced Protection card: its switch only once a
	 * key is saved (or the switch is already on).
	 *
	 * @param {Object} entry Schema entry.
	 * @return {boolean} Shown.
	 */
	const zerospamFilter = ( entry ) =>
		'zerospam' !== entry.key ||
		Boolean( app.license?.key?.set ) ||
		isOn( entry, settings.valueOf( 'zerospam' ) );

	const renderCard = ( card ) => (
		<Card
			key={ card.key }
			card={ card }
			settings={ settings }
			license={ app.license }
			filter={ 'zerospam' === card.key ? zerospamFilter : undefined }
			onAction={ () => schema.reload() }
		>
			{ 'zerospam' === card.key && (
				<LevelPanel
					latency={ latency }
					locked={ keyLocked }
					onChanged={ () => schema.reload() }
				/>
			) }
		</Card>
	);

	let body;
	if ( ! schema.data && schema.error ) {
		body = (
			<div className="zs-tile zs-empty" role="alert">
				<b>
					{ __(
						'Your protection settings could not be loaded.',
						'zero-spam'
					) }
				</b>
				<span>
					{ __(
						'Your site is still protected. Check your connection and try again.',
						'zero-spam'
					) }
				</span>
				<div>
					<Button
						variant="secondary"
						icon="refresh"
						onClick={ () => schema.reload() }
					>
						{ __( 'Try again', 'zero-spam' ) }
					</Button>
				</div>
			</div>
		);
	} else if ( ! schema.data ) {
		body = (
			<div className="zs-cardcol" aria-hidden="true">
				<div className="zs-tile zs-pcard zs-pcard-skel" />
				<div className="zs-tile zs-pcard zs-pcard-skel" />
			</div>
		);
	} else if ( 'integrations' === tab ) {
		const active = cards.filter( ( c ) => ! c.inactive );
		const inactive = cards.filter( ( c ) => c.inactive );
		body = (
			<>
				<Jump
					cards={ active }
					reduced={ app.reduced }
					more={ inactive.length > 0 }
				/>
				<p className="zs-hint zs-sub-lead">
					{ sprintf(
						/* translators: 1: integrations whose plugin is active, 2: integrations whose plugin is not active */
						__(
							'Cards appear for form plugins that are active on this site. %1$d active, %2$d not active.',
							'zero-spam'
						),
						active.length,
						inactive.length
					) }
				</p>
				{ active.length ? (
					<div className="zs-cardcol">
						{ active.map( renderCard ) }
					</div>
				) : (
					<div className="zs-tile zs-empty">
						<b>
							{ __(
								'No form plugin Zero Spam works with is active.',
								'zero-spam'
							) }
						</b>
						<span>
							{ __(
								'Comments, registrations and logins are still protected on the Detection tab.',
								'zero-spam'
							) }
						</span>
					</div>
				) }
				{ inactive.length > 0 && (
					<details className="zs-moreint" id="zs-card-moreint">
						<summary>
							{ __(
								'More integrations, shown when their plugin is active',
								'zero-spam'
							) }
							<span className="zs-c">{ inactive.length }</span>
						</summary>
						<div className="zs-cardcol">
							{ inactive.map( renderCard ) }
						</div>
					</details>
				) }
			</>
		);
	} else {
		body = (
			<>
				<Jump cards={ cards } reduced={ app.reduced } />
				<div className="zs-cardcol">{ cards.map( renderCard ) }</div>
			</>
		);
	}

	return (
		<div className="zs-view zs-protection" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ __( 'Protection.', 'zero-spam' ) }
						<span>
							{ __( 'Every door, checked.', 'zero-spam' ) }
						</span>
					</h1>
					<p>
						{ __(
							'Turn checks on or off for each place visitors can send you something, and choose which reputation services Zero Spam asks.',
							'zero-spam'
						) }
					</p>
				</div>
				<TestButton
					state={ test }
					primary={ ! basicOnIntel }
					onRun={ () => runSelfTest( dispatch ) }
				/>
			</div>
			<SelfTestPanel state={ test } dispatch={ dispatch } />
			<Tabs tab={ tab } setTab={ setTab } counts={ counts } />
			<div
				id="zs-prot-body"
				role="tabpanel"
				aria-labelledby={ `zs-pt-${ tab }` }
				className={ cx( 'zs-areabody', `zs-prot-${ tab }` ) }
			>
				{ body }
			</div>
			{ settings.count > 0 && (
				<UnsavedBar
					saving={ settings.saving }
					message={
						settings.message ||
						sprintf(
							/* translators: %d: number of unsaved changes */
							_n(
								'You have %d unsaved change.',
								'You have %d unsaved changes.',
								settings.count,
								'zero-spam'
							),
							settings.count
						)
					}
					onSave={ save }
					onDiscard={ () => {
						settings.discard();
						toast( __( 'Changes discarded', 'zero-spam' ) );
					} }
				/>
			) }
			{ settings.count === 0 && settings.message && (
				<p className="zs-notice zs-notice-err" role="alert">
					<Icon name="warn" size="sm" />
					<span>{ settings.message }</span>
				</p>
			) }
		</div>
	);
}
