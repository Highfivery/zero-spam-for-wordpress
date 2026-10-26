/**
 * Settings (prototype v18): six tabs (?tab=) with a card per group of
 * settings, rendered from the settings schema by the shared field renderer
 * (app/fields), the user's display preferences, rescue mode, the
 * no-lockout addresses, the system report, import and export, the error
 * log, and the unsaved-changes bar. ?section= (a card or a setting key, as
 * the Overview links to) opens the page on that card.
 *
 * Everything the page reads on load is preloaded (Admin_App::
 * settings_preloads() and the shell's): no request of its own on load.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { useFocusAfterRender } from '../app/use-focus-after-render';
import { Field } from '../app/fields/fields';
import { byKey, holdsValue, idOf } from '../app/fields/settings-model';
import { useSettings } from '../app/fields/use-settings';
import { Badge, Button, Icon, cx } from '../app/primitives';
import { UnsavedBar, useFeedback } from '../app/primitives/feedback';
import { PATHS, boot, useResource } from '../data/api';
import { useTab } from '../data/routes';
import {
	TABS,
	buildCards,
	cardStatus,
	locate,
	lockCount,
	tabCounts,
	tabNames,
} from './cards';
import { ErrorLog, ImportExport } from './io';
import {
	DisplayPrefs,
	NoLockout,
	QuotaHint,
	RescuePanel,
	SystemReport,
	TestAlerts,
	rescueKeySet,
} from './parts';

/**
 * The ?section= (or ?card=) argument, if any.
 *
 * @return {string} Card or setting key.
 */
const sectionArg = () => {
	const args = new URLSearchParams( window.location.search );
	const value = args.get( 'section' ) || args.get( 'card' ) || '';
	return /^[a-z0-9_-]{1,64}$/i.test( value ) ? value : '';
};

/**
 * Before the tabs read the address: a ?section= without a ?tab= gets the
 * tab of its card, so the right tab paints first (and its data was
 * preloaded for it, see Admin_App::settings_preloads()).
 *
 * @param {Object|undefined} schema settings/schema answer.
 */
function useSectionTab( schema ) {
	useState( () => {
		const url = new URL( window.location.href );
		const section = sectionArg();
		if ( ! section || url.searchParams.get( 'tab' ) ) {
			return null;
		}
		const found = locate( buildCards( schema ), section );
		if ( found && 'general' !== found.tab ) {
			url.searchParams.set( 'tab', found.tab );
			window.history.replaceState(
				window.history.state,
				'',
				url.toString()
			);
		}
		return null;
	} );
}

/**
 * Scrolls to a card (or a setting's row in it) and focuses it.
 *
 * @param {string}  card    Card key.
 * @param {string}  key     Setting key ('' for the card).
 * @param {boolean} reduced Reduced motion.
 * @return {boolean} Whether something took focus (false: the setting's controls are disabled).
 */
const goTo = ( card, key, reduced ) => {
	const box = document.getElementById( `zs-card-${ card }` );
	if ( ! box ) {
		return false;
	}
	const row = key
		? document.getElementById( `zs-row-${ idOf( key ) }` )
		: null;
	( row || box ).scrollIntoView( {
		block: 'start',
		behavior: reduced ? 'auto' : 'smooth',
	} );
	// The setting's own control (never a disabled one), not the description's "More" button before it.
	const enabled =
		'input:not([disabled]),select:not([disabled]),textarea:not([disabled]),button:not([disabled])';
	const control =
		row?.querySelector(
			`.zs-ctl :is(${ enabled.replace( /,/g, ', ' ) })`
		) || row?.querySelector( enabled );
	if ( row && ! control ) {
		return false;
	}
	( control || box ).focus( { preventScroll: true } );
	return true;
};

/**
 * Focuses the unsaved-changes bar (it says what happened when the setting
 * cannot take focus: locked, or not on this page).
 */
const focusSaveBar = () => {
	const bar = document.querySelector( '.zs-savebar' );
	if ( bar ) {
		bar.setAttribute( 'tabindex', '-1' );
		bar.focus();
	}
};

/**
 * The tab list: arrow keys, Home and End move between tabs (roving
 * tabindex), with the unsaved changes of each tab.
 *
 * @param {Object}                          props        Props.
 * @param {string}                          props.tab    Current tab.
 * @param {(...args: unknown[]) => unknown} props.setTab Changes the tab.
 * @param {Object<string, number>}          props.counts Unsaved changes per tab.
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
				list.current?.querySelector( `#zs-st-${ next }` )?.focus()
			);
		}
	};

	return (
		<div className="zs-subnav">
			{ /* eslint-disable-next-line jsx-a11y/interactive-supports-focus -- The tabs take focus (roving tabindex), not the list. */ }
			<div
				ref={ list }
				className="zs-seg zs-tabs"
				role="tablist"
				aria-label={ __( 'Settings groups', 'zero-spam' ) }
				onKeyDown={ onKey }
			>
				{ TABS.map( ( t ) => (
					<button
						key={ t }
						id={ `zs-st-${ t }` }
						type="button"
						role="tab"
						aria-selected={ t === tab ? 'true' : 'false' }
						aria-controls="zs-set-body"
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
		</div>
	);
}

/**
 * "On this page": chips that jump to a card.
 *
 * @param {Object}   props         Props.
 * @param {Object[]} props.cards   Cards of the tab.
 * @param {boolean}  props.reduced Reduced motion.
 */
function Jump( { cards, reduced } ) {
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
					onClick={ () => goTo( card.key, '', reduced ) }
				>
					{ card.title }
				</button>
			) ) }
		</nav>
	);
}

/**
 * One settings card.
 *
 * @param {Object}  props          Props.
 * @param {Object}  props.card     Card (buildCards()).
 * @param {Object}  [props.status] { label, tone } badge.
 * @param {boolean} [props.mine]   Preferences of this user only.
 * @param {unknown} props.children Content.
 */
function SettingsCard( { card, status, mine = false, children } ) {
	const id = `zs-card-${ card.key }`;
	const headingId = `zs-ch-${ card.key }`;
	const locks = lockCount( card );
	const allLocked = locks.total > 0 && locks.locked === locks.total;
	return (
		<section
			className={ cx(
				'zs-tile',
				'zs-pcard',
				'zs-scard',
				allLocked && 'zs-pcard-locked'
			) }
			id={ id }
			aria-labelledby={ headingId }
			tabIndex={ -1 }
		>
			<div className="zs-pcard-h">
				<div className="zs-pcard-t">
					<span className="zs-ico">
						<Icon name={ card.icon } />
					</span>
					<div>
						<h2 id={ headingId } className="zs-pcard-title">
							{ card.title }
						</h2>
						{ card.blurb && (
							<p className="zs-hint">{ card.blurb }</p>
						) }
					</div>
				</div>
				<span className="zs-pcard-badges">
					{ mine && (
						<Badge>{ __( 'Just for you', 'zero-spam' ) }</Badge>
					) }
					{ allLocked && (
						<Badge className="zs-badge-lock" icon="lock">
							{ __( 'Locked by network', 'zero-spam' ) }
						</Badge>
					) }
					{ status && (
						<Badge tone={ status.tone || undefined }>
							<span className="zs-sr-only">
								{ __( 'Status:', 'zero-spam' ) }{ ' ' }
							</span>
							{ status.label }
						</Badge>
					) }
				</span>
			</div>
			<div className="zs-pcard-b">{ children }</div>
		</section>
	);
}

/**
 * The Settings area.
 */
export function Settings() {
	const app = useApp();
	const { toast, announce } = useFeedback();
	const schema = useResource( PATHS.schema );
	useSectionTab( schema.data );
	const settings = useSettings( schema );
	const [ tab, setTab ] = useTab( 'general', TABS );
	const opened = useRef( false );
	const focusAfterRender = useFocusAfterRender();

	const cards = useMemo( () => buildCards( schema.data ), [ schema.data ] );
	const counts = useMemo(
		() => tabCounts( cards, settings.payload ),
		[ cards, settings.payload ]
	);
	const entries = useMemo( () => byKey( schema.data ), [ schema.data ] );
	const lockedHere = useMemo(
		() =>
			( schema.data?.settings || [] ).filter(
				( e ) => 'settings' === e.area && holdsValue( e ) && e.locked
			).length,
		[ schema.data ]
	);

	// ?section= opens the page on that card (or setting), once it exists.
	useEffect( () => {
		if ( opened.current || ! schema.data ) {
			return;
		}
		opened.current = true;
		const found = locate( cards, sectionArg() );
		if ( ! found ) {
			return;
		}
		if ( found.tab !== tab ) {
			setTab( found.tab );
		}
		// Once that tab is shown.
		focusAfterRender( () => goTo( found.card, found.key, app.reduced ) );
	}, [ schema.data, cards, tab, setTab, app.reduced, focusAfterRender ] );

	const reloadAll = () => {
		schema.reload();
		app.modulesState?.reload?.();
	};

	const save = () => {
		settings
			.save()
			.then( ( { undo, saved } ) => {
				const n = Object.keys( saved ).length;
				app.modulesState?.reload?.();
				toast(
					sprintf(
						/* translators: %d: number of settings */
						_n(
							'%d setting saved',
							'%d settings saved',
							n,
							'zero-spam'
						),
						n
					),
					undo
						? {
								undo: () =>
									settings
										.restore( undo )
										.then( () =>
											toast(
												__(
													'Changes undone',
													'zero-spam'
												)
											)
										)
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
				// Open the tab of the first setting with an error and focus it.
				const first = Object.keys( mapped.fields )[ 0 ];
				const found = first ? locate( cards, first ) : null;
				if ( found && found.tab !== tab ) {
					setTab( found.tab );
				}
				// Once that tab is shown (the save ran outside an event, so the change may render late).
				// A locked setting (disabled), or one shown in another area: the bar explains.
				focusAfterRender(
					() =>
						Boolean( found ) &&
						goTo( found.card, first, app.reduced ),
					focusSaveBar
				);
			} );
	};

	/**
	 * The rows of a card's settings.
	 *
	 * @param {Object} card Card.
	 * @return {unknown} Rows.
	 */
	const rows = ( card ) =>
		card.entries.map( ( entry ) => {
			if ( 'test_alerts' === entry.key ) {
				return (
					<TestAlerts
						key={ entry.key }
						entry={ entry }
						disabled={ false }
					/>
				);
			}
			const field = (
				<Field
					key={ entry.key }
					entry={ entry }
					value={
						entry.secret
							? settings.draftOf( entry.key )
							: settings.valueOf( entry.key )
					}
					onChange={ settings.set }
					entries={ settings.entries }
					error={ settings.errors[ entry.key ] }
					onAction={ reloadAll }
					cardTitle={ card.title }
				/>
			);
			if ( 'quota_reset_date' === entry.key ) {
				return (
					<div key={ entry.key } className="zs-withnote">
						{ field }
						<QuotaHint
							value={ settings.valueOf( entry.key ) }
							disabled={ Boolean( entry.locked ) }
							onClear={ () => {
								settings.set( entry.key, '' );
								document
									.getElementById(
										`zs-f-${ idOf( entry.key ) }`
									)
									?.focus();
							} }
						/>
					</div>
				);
			}
			return field;
		} );

	/**
	 * A card's content.
	 *
	 * @param {Object} card Card.
	 * @return {unknown} Content.
	 */
	const content = ( card ) => {
		switch ( card.custom ) {
			case 'display':
				return <DisplayPrefs />;
			case 'rescue':
				return <RescuePanel />;
			case 'nolockout':
				return <NoLockout />;
			case 'system':
				return <SystemReport schema={ schema.data } />;
			case 'io':
				return (
					<ImportExport
						entries={ entries }
						unsaved={ settings.count }
						onApplied={ () => {
							settings.discard();
							reloadAll();
						} }
					/>
				);
			case 'errlog':
				return <ErrorLog />;
			default:
				return rows( card );
		}
	};

	/**
	 * A card's badge.
	 *
	 * @param {Object} card Card.
	 * @return {{label: string, tone: string}|null} Badge.
	 */
	const statusOf = ( card ) => {
		if ( 'rescue' === card.custom ) {
			return rescueKeySet()
				? { label: __( 'Active', 'zero-spam' ), tone: 'ok' }
				: { label: __( 'Key not set', 'zero-spam' ), tone: 'warn' };
		}
		return cardStatus( card, settings.valueOf, settings.entries );
	};

	const here = cards.filter( ( card ) => card.tab === tab );
	let body;
	if ( ! schema.data && schema.error ) {
		body = (
			<div className="zs-tile zs-empty" role="alert">
				<b>
					{ __( 'Your settings could not be loaded.', 'zero-spam' ) }
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
	} else {
		body = (
			<>
				<Jump cards={ here } reduced={ app.reduced } />
				<div className="zs-cardcol">
					{ here.map( ( card ) => (
						<SettingsCard
							key={ card.key }
							card={ card }
							status={ statusOf( card ) }
							mine={ card.mine }
						>
							{ content( card ) }
						</SettingsCard>
					) ) }
				</div>
			</>
		);
	}

	return (
		<div className="zs-view zs-settings" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ __( 'Settings.', 'zero-spam' ) }
						<span>
							{ boot.site.name
								? sprintf(
										/* translators: %s: site name */
										__( 'Tuned for %s.', 'zero-spam' ),
										boot.site.name
									)
								: __( 'Tuned for your site.', 'zero-spam' ) }
						</span>
					</h1>
					<p>
						{ __(
							'Recommended values are already set. Change only what you need.',
							'zero-spam'
						) }
					</p>
				</div>
			</div>
			{ lockedHere > 0 && (
				<div className="zs-notice zs-notice-info zs-netact">
					<Icon name="network" />
					<div>
						<b>{ __( 'Network settings active', 'zero-spam' ) }</b>
						<span>
							{ sprintf(
								/* translators: %d: number of settings */
								_n(
									'%d setting is locked by your network administrator and cannot be changed here.',
									'%d settings are locked by your network administrator and cannot be changed here.',
									lockedHere,
									'zero-spam'
								),
								lockedHere
							) }
						</span>
					</div>
				</div>
			) }
			<Tabs tab={ tab } setTab={ setTab } counts={ counts } />
			<div
				id="zs-set-body"
				role="tabpanel"
				aria-labelledby={ `zs-st-${ tab }` }
				className={ cx( 'zs-areabody', `zs-set-${ tab }` ) }
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
			{ 0 === settings.count && settings.message && (
				<p className="zs-notice zs-notice-err" role="alert">
					<Icon name="warn" size="sm" />
					<span>{ settings.message }</span>
				</p>
			) }
		</div>
	);
}
