/**
 * The network Settings tab (prototype §9): every setting a network can set,
 * as cards built from the settings schema (Protection by section, Settings
 * and Rules by group) and rendered by the shared field renderer, with the
 * network value, how many sites use it, and a lock switch per setting.
 * `&group=` shows one group (Detection, Integrations, Intelligence,
 * Settings, Rules).
 *
 * Values are saved together from the unsaved-changes bar
 * (`PATCH settings?scope=network`, which keeps each lock and writes one
 * audit entry per change); the lock switch saves at once
 * (`network-settings/{key}/lock|unlock`) with Undo.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Field } from '../app/fields/fields';
import {
	byKey,
	dirtyPayload,
	errorsFrom,
	isChange,
	idOf,
	toPayloadValue,
} from '../app/fields/settings-model';
import { Badge, Button, Chip, Icon, cx } from '../app/primitives';
import { UnsavedBar, useFeedback } from '../app/primitives/feedback';
import { NS, request, useResource } from '../data/api';
import {
	PATHS,
	lockToast,
	networkCards,
	settingGroups,
	sitesState,
	valueText,
} from './model';
import { LoadState, LockButton, errorText } from './parts';

/**
 * The ?group= argument, if it names a group.
 *
 * @return {string} Group key, or ''.
 */
const groupArg = () => {
	const value = new URLSearchParams( window.location.search ).get( 'group' );
	return settingGroups().some( ( [ k ] ) => k === value ) ? value : '';
};

/**
 * Keeps ?group= in the address (the back button and bookmarks).
 *
 * @param {string} group Group key, or '' for all.
 */
const writeGroup = ( group ) => {
	const url = new URL( window.location.href );
	if ( group ) {
		url.searchParams.set( 'group', group );
	} else {
		url.searchParams.delete( 'group' );
	}
	window.history.replaceState( window.history.state, '', url.toString() );
};

/**
 * Under a setting's control: how many sites use the network value, the
 * network value when it is empty, and the lock switch.
 *
 * @param {Object}                          props          Props.
 * @param {Object}                          props.entry    Network entry.
 * @param {Object|undefined}                props.overview network/overview answer.
 * @param {boolean}                         props.busy     The lock is being saved.
 * @param {(...args: unknown[]) => unknown} props.onLock   Toggles the lock.
 */
function SitesAside( { entry, overview, busy, onLock } ) {
	const state = sitesState( entry, overview );
	const id = `zs-ns-${ idOf( entry.key ) }`;
	let badge = null;
	if ( 'lock' === state.tone ) {
		badge = (
			<Badge className="zs-badge-lock" icon="lock">
				{ state.label }
			</Badge>
		);
	} else if ( state.custom > 0 ) {
		badge = (
			<Badge tone="warn">
				{ sprintf(
					/* translators: %s: number of sites */
					_n(
						'%s site changed it',
						'%s sites changed it',
						state.custom,
						'zero-spam'
					),
					state.custom.toLocaleString()
				) }
			</Badge>
		);
	}
	return (
		<div className="zs-netaside">
			<div className="zs-netaside-t" id={ id }>
				{ 'lock' !== state.tone && (
					<span className="zs-hint">{ state.label }</span>
				) }
				{ badge }
				{ entry.has && ! entry.secret && (
					<span className="zs-sr-only">
						{ sprintf(
							/* translators: %s: the network value */
							__( 'Network value: %s.', 'zero-spam' ),
							valueText( entry, entry.value )
						) }
					</span>
				) }
				<span className="zs-sr-only">{ state.sentence }</span>
			</div>
			<LockButton
				entry={ entry }
				locked={ entry.netLocked }
				busy={ busy }
				describedBy={ id }
				onToggle={ onLock }
			/>
		</div>
	);
}

/**
 * One card of network settings, opened and closed from its header.
 *
 * @param {Object}                          props          Props.
 * @param {Object}                          props.card     Card.
 * @param {boolean}                         props.open     Open.
 * @param {(...args: unknown[]) => unknown} props.onToggle Opens or closes it.
 * @param {number}                          props.changed  Unsaved changes in it.
 * @param {unknown}                         props.children Rows.
 */
function NetCard( { card, open, onToggle, changed, children } ) {
	const locked = card.entries.filter( ( e ) => e.netLocked ).length;
	const n = card.entries.length;
	const bodyId = `zs-ncb-${ card.key }`;
	return (
		<section
			className="zs-tile zs-ncard"
			id={ `zs-ncard-${ card.key }` }
			aria-labelledby={ `zs-nch-${ card.key }` }
		>
			<button
				type="button"
				className="zs-ngh"
				aria-expanded={ open ? 'true' : 'false' }
				aria-controls={ bodyId }
				onClick={ onToggle }
			>
				<span className="zs-grow">
					<b id={ `zs-nch-${ card.key }` }>{ card.title }</b>
					<span className="zs-hint">
						{ [
							card.blurb,
							sprintf(
								/* translators: %s: number of settings */
								_n(
									'%s setting',
									'%s settings',
									n,
									'zero-spam'
								),
								n.toLocaleString()
							),
							locked > 0 &&
								sprintf(
									/* translators: %s: number of settings */
									__( '%s locked', 'zero-spam' ),
									locked.toLocaleString()
								),
						]
							.filter( Boolean )
							.join( ' · ' ) }
					</span>
				</span>
				{ changed > 0 && (
					<Badge tone="info">
						{ sprintf(
							/* translators: %s: number of unsaved changes */
							_n(
								'%s unsaved',
								'%s unsaved',
								changed,
								'zero-spam'
							),
							changed.toLocaleString()
						) }
					</Badge>
				) }
				<Icon name="chev" size="sm" className="zs-chev" />
			</button>
			<div id={ bodyId } className="zs-ncard-b" hidden={ ! open }>
				{ open && children }
			</div>
		</section>
	);
}

/**
 * The Settings tab.
 */
export function NetworkSettings() {
	const { toast, announce } = useFeedback();
	const schema = useResource( PATHS.schema );
	const layer = useResource( PATHS.values );
	const overview = useResource( PATHS.overview );
	const [ group, setGroupState ] = useState( groupArg );
	// null until a card is opened or closed: the first card of the view starts open.
	const [ openState, setOpen ] = useState( null );
	const [ draft, setDraft ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ message, setMessage ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ locking, setLocking ] = useState( '' );

	const cards = useMemo(
		() => networkCards( schema.data, layer.data ),
		[ schema.data, layer.data ]
	);
	const entries = useMemo( () => {
		const map = {};
		cards.forEach( ( c ) =>
			c.entries.forEach( ( e ) => {
				map[ e.key ] = e;
			} )
		);
		return map;
	}, [ cards ] );
	const schemaEntries = useMemo(
		() => byKey( schema.data ),
		[ schema.data ]
	);
	const payload = useMemo(
		() => dirtyPayload( entries, draft ),
		[ entries, draft ]
	);
	const count = Object.keys( payload ).length;
	const shown = cards.filter( ( c ) => ! group || c.group === group );
	const open = openState || new Set( shown.length ? [ shown[ 0 ].key ] : [] );

	// Leaving the page with unsaved changes asks first.
	useEffect( () => {
		if ( ! count ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ count ] );

	if ( ! schema.data || ! layer.data ) {
		return (
			<LoadState
				res={ schema.data ? layer : schema }
				what={ __(
					'The network settings could not be loaded.',
					'zero-spam'
				) }
				retry={ () => {
					schema.reload();
					layer.reload();
				} }
			/>
		);
	}

	const setGroup = ( next ) => {
		setGroupState( next );
		writeGroup( next );
		setOpen( null );
	};

	const set = ( key, value ) => {
		setDraft( ( current ) => {
			const next = { ...current };
			if ( isChange( entries[ key ], value ) ) {
				next[ key ] = value;
			} else {
				delete next[ key ];
			}
			return next;
		} );
		setErrors( ( current ) => {
			if ( ! current[ key ] ) {
				return current;
			}
			const next = { ...current };
			delete next[ key ];
			return next;
		} );
	};

	const has = ( key ) => Object.prototype.hasOwnProperty.call( draft, key );
	// A secret's field holds only what was typed (undefined: no change).
	const fieldValue = ( entry ) => {
		if ( entry.secret ) {
			return has( entry.key ) ? draft[ entry.key ] : undefined;
		}
		return valueOf( entry.key );
	};
	const valueOf = ( key ) =>
		has( key ) ? draft[ key ] : entries[ key ]?.value;

	const reloadAll = () => {
		layer.reload();
		overview.reload();
	};

	const patch = ( body ) =>
		request( {
			path: PATHS.values,
			method: 'PATCH',
			data: body,
		} );

	const save = () => {
		const body = payload;
		const keys = Object.keys( body );
		// Undo puts the earlier network values back (not when one was not set before, or for a key).
		const undo = keys.every(
			( k ) => entries[ k ]?.has && ! entries[ k ]?.secret
		)
			? Object.fromEntries(
					keys.map( ( k ) => [
						k,
						toPayloadValue( entries[ k ], entries[ k ].value ),
					] )
				)
			: null;
		setSaving( true );
		setMessage( '' );
		patch( body )
			.then( () => {
				setDraft( {} );
				setErrors( {} );
				reloadAll();
				toast(
					sprintf(
						/* translators: %s: number of settings */
						_n(
							'%s network setting saved',
							'%s network settings saved',
							keys.length,
							'zero-spam'
						),
						keys.length.toLocaleString()
					),
					undo
						? {
								undo: () =>
									patch( undo )
										.then( () => {
											reloadAll();
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
			.catch( ( error ) => {
				const mapped = errorsFrom( error );
				setErrors( mapped.fields );
				setMessage( mapped.message );
				announce( mapped.message, 'assertive' );
				const first = Object.keys( mapped.fields )[ 0 ];
				const card = first
					? cards.find( ( c ) =>
							c.entries.some( ( e ) => e.key === first )
						)
					: null;
				if ( card ) {
					if ( group && card.group !== group ) {
						setGroupState( '' );
						writeGroup( '' );
					}
					setOpen( new Set( [ ...open, card.key ] ) );
					window.setTimeout(
						() =>
							document
								.querySelector(
									`#zs-row-${ idOf( first ) } .zs-ctl :is(input, select, textarea, button)`
								)
								?.focus(),
						60
					);
				}
			} )
			.finally( () => setSaving( false ) );
	};

	const toggleLock = ( entry ) => {
		const next = ! entry.netLocked;
		const lockPath = ( on ) =>
			`${ NS }/network-settings/${ encodeURIComponent( entry.key ) }/${
				on ? 'lock' : 'unlock'
			}`;
		const run = ( on ) =>
			// Locking a setting the network does not set yet locks the value shown (its default).
			( on && ! entry.has && ! entry.secret
				? patch( {
						[ entry.key ]: toPayloadValue( entry, entry.value ),
					} )
				: Promise.resolve()
			).then( () => request( { path: lockPath( on ), method: 'POST' } ) );

		setLocking( entry.key );
		run( next )
			.then( () => {
				reloadAll();
				toast( lockToast( entry, next ), {
					undo: () =>
						request( { path: lockPath( ! next ), method: 'POST' } )
							.then( () => {
								reloadAll();
								toast( __( 'Undone', 'zero-spam' ) );
							} )
							.catch( () =>
								toast(
									__(
										'The change could not be undone. Please try again.',
										'zero-spam'
									)
								)
							),
				} );
			} )
			.catch( ( error ) =>
				toast(
					errorText(
						error,
						__(
							'The lock could not be changed. Please try again.',
							'zero-spam'
						)
					)
				)
			)
			.finally( () => setLocking( '' ) );
	};

	const locked = Object.values( entries ).filter(
		( e ) => e.netLocked
	).length;
	const total = Object.keys( entries ).length;

	return (
		<>
			<div className="zs-tbar">
				<p className="zs-hint zs-grow">
					{ sprintf(
						/* translators: 1: number of settings, 2: number of locked settings */
						__(
							'Set default values for every site. Lock a setting to stop site administrators changing it. %1$s settings can be set here (%2$s locked); help and buttons stay on each site.',
							'zero-spam'
						),
						total.toLocaleString(),
						locked.toLocaleString()
					) }
				</p>
				<Button
					variant="secondary"
					size="sm"
					onClick={ () =>
						setOpen( new Set( shown.map( ( c ) => c.key ) ) )
					}
				>
					{ __( 'Expand all', 'zero-spam' ) }
				</Button>
				<Button
					variant="secondary"
					size="sm"
					onClick={ () => setOpen( new Set() ) }
				>
					{ __( 'Collapse all', 'zero-spam' ) }
				</Button>
			</div>
			<div
				className="zs-chips zs-ngroups"
				role="group"
				aria-label={ __( 'Show settings of', 'zero-spam' ) }
			>
				<Chip pressed={ ! group } onClick={ () => setGroup( '' ) }>
					{ __( 'All', 'zero-spam' ) }
				</Chip>
				{ settingGroups()
					.filter( ( [ k ] ) => cards.some( ( c ) => c.group === k ) )
					.map( ( [ key, label ] ) => (
						<Chip
							key={ key }
							pressed={ key === group }
							onClick={ () => setGroup( key ) }
						>
							{ label }
						</Chip>
					) ) }
			</div>
			<div className="zs-cardcol">
				{ shown.map( ( card ) => (
					<NetCard
						key={ card.key }
						card={ card }
						open={ open.has( card.key ) }
						changed={
							card.entries.filter(
								( e ) => payload[ e.key ] !== undefined
							).length
						}
						onToggle={ () => {
							const next = new Set( open );
							if ( next.has( card.key ) ) {
								next.delete( card.key );
							} else {
								next.add( card.key );
							}
							setOpen( next );
						} }
					>
						{ card.entries.map( ( entry ) => (
							<div
								key={ entry.key }
								className={ cx(
									'zs-netrow',
									entry.netLocked && 'zs-netrow-locked'
								) }
							>
								<Field
									entry={ entry }
									value={ fieldValue( entry ) }
									onChange={ set }
									entries={ schemaEntries }
									error={ errors[ entry.key ] }
									cardTitle={ card.title }
									aside={
										<SitesAside
											entry={ entry }
											overview={ overview.data }
											busy={ locking === entry.key }
											onLock={ () => toggleLock( entry ) }
										/>
									}
								/>
							</div>
						) ) }
					</NetCard>
				) ) }
			</div>
			{ count > 0 && (
				<UnsavedBar
					saving={ saving }
					message={
						message ||
						sprintf(
							/* translators: %s: number of unsaved changes */
							_n(
								'You have %s unsaved network change.',
								'You have %s unsaved network changes.',
								count,
								'zero-spam'
							),
							count.toLocaleString()
						)
					}
					onSave={ save }
					onDiscard={ () => {
						setDraft( {} );
						setErrors( {} );
						setMessage( '' );
						toast( __( 'Changes discarded', 'zero-spam' ) );
					} }
				/>
			) }
		</>
	);
}
