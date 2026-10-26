/**
 * Network templates (prototype §9, "rebuilt on the real registry"): saved
 * sets of settings a network can lock. Three are built in; custom ones can
 * be made, changed and deleted (with Undo). A template is applied to the
 * network defaults (with Undo) or to the sites chosen in its drawer,
 * keeping (or replacing) the values sites changed themselves; locked
 * settings are never written to sites.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useMemo, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Field } from '../app/fields/fields';
import {
	byKey,
	idOf,
	isOn,
	onValue,
	toPayloadValue,
} from '../app/fields/settings-model';
import { Badge, Button, Icon, cx } from '../app/primitives';
import { Drawer } from '../app/primitives/drawer';
import { useFeedback } from '../app/primitives/feedback';
import { NS, boot, nowLocal, request, useResource } from '../data/api';
import { day, num } from '../data/format';
import {
	PATHS,
	networkCards,
	readLayer,
	slugify,
	templateChips,
	validSlug,
} from './model';
import { ActionResult, Confirm, LoadState, errorText } from './parts';

/**
 * A template value as the drawer edits it (a switch's value as on/off).
 *
 * @param {Object}  entry Schema entry.
 * @param {unknown} value Stored value.
 * @return {unknown} Draft value.
 */
const draftValue = ( entry, value ) => {
	if ( 'checkbox' === entry?.type ) {
		return isOn( entry, value ) ? onValue( entry ) : false;
	}
	return value;
};

/**
 * The template drawer: details, the settings it holds (search, include,
 * value) and the sites to apply it to.
 *
 * @param {Object}                          props          Props.
 * @param {Object|null}                     props.template Template to change (null for a new one).
 * @param {boolean}                         props.apply    Opened to apply it (focus on the sites).
 * @param {Array<Object>}                   props.cards    Settings cards (networkCards()).
 * @param {Object<string, Object>}          props.entries  Schema entries by key.
 * @param {Object}                          props.network  Network values (readLayer()).
 * @param {(...args: unknown[]) => unknown} props.onClose  Closes.
 * @param {(...args: unknown[]) => unknown} props.onSaved  After a save (message).
 */
function TemplateDrawer( {
	template,
	apply,
	cards,
	entries,
	network,
	onClose,
	onSaved,
} ) {
	const builtIn = 'built_in' === template?.type;
	const [ name, setName ] = useState( () => {
		if ( ! template ) {
			return '';
		}
		return builtIn
			? sprintf(
					/* translators: %s: template name */
					__( '%s (copy)', 'zero-spam' ),
					template.name
				)
			: template.name;
	} );
	const [ slug, setSlug ] = useState( () =>
		template && ! builtIn ? template.slug : ''
	);
	const [ slugTouched, setSlugTouched ] = useState(
		Boolean( template && ! builtIn )
	);
	const [ desc, setDesc ] = useState( template?.description || '' );
	const [ values, setValues ] = useState( () => {
		const start = {};
		Object.entries( template?.settings || {} ).forEach( ( [ k, v ] ) => {
			start[ k ] = draftValue( entries[ k ], v );
		} );
		return start;
	} );
	const [ search, setSearch ] = useState( '' );
	const [ target, setTarget ] = useState( apply ? 'all' : 'none' );
	const [ chosen, setChosen ] = useState( () => new Set() );
	const [ siteSearch, setSiteSearch ] = useState( '' );
	const [ mode, setMode ] = useState( 'fill' );
	const [ errors, setErrors ] = useState( {} );
	const [ failure, setFailure ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const sites = useResource(
		'choose' === target ? PATHS.sites( siteSearch ) : null,
		{ parse: false }
	);
	const sitesTotal = Number( sites.headers[ 'x-wp-total' ] ) || 0;
	const allSites = Number( network.sites ) || 0;

	const keys = Object.keys( values );
	const q = search.trim().toLowerCase();

	const include = ( entry, on ) =>
		setValues( ( current ) => {
			const next = { ...current };
			if ( on ) {
				next[ entry.key ] = draftValue(
					entry,
					Object.prototype.hasOwnProperty.call(
						network.values,
						entry.key
					)
						? network.values[ entry.key ]
						: entry.default
				);
			} else {
				delete next[ entry.key ];
			}
			return next;
		} );

	const fieldError = ( key ) => errors[ key ] || '';

	const save = ( andApply ) => {
		const local = {};
		const finalSlug = builtIn || ! template ? slug : template.slug;
		if ( ! name.trim() ) {
			local.name = __( 'Enter a template name.', 'zero-spam' );
		}
		if ( ! validSlug( finalSlug ) ) {
			local.slug = __(
				'Use lowercase letters, numbers and hyphens, like clinic-sites.',
				'zero-spam'
			);
		}
		if ( ! keys.length ) {
			local.settings = __( 'Choose at least one setting.', 'zero-spam' );
		}
		if ( andApply && 'choose' === target && ! chosen.size ) {
			local.sites = __(
				'Choose at least one site, or save without applying.',
				'zero-spam'
			);
		}
		setErrors( local );
		setFailure( '' );
		if ( Object.keys( local ).length ) {
			const first = Object.keys( local )[ 0 ];
			window.requestAnimationFrame( () =>
				document.getElementById( `zs-tp-${ first }` )?.focus()
			);
			return;
		}

		const settings = {};
		keys.forEach( ( k ) => {
			settings[ k ] = entries[ k ]
				? toPayloadValue( entries[ k ], values[ k ] )
				: values[ k ];
		} );

		const editing = template && ! builtIn;
		setSaving( true );
		request( {
			path: editing
				? `${ NS }/network-settings/templates/${ encodeURIComponent( template.slug ) }`
				: `${ NS }/network-settings/templates`,
			method: editing ? 'PUT' : 'POST',
			data: editing
				? { name: name.trim(), description: desc, settings }
				: {
						name: name.trim(),
						slug: finalSlug,
						description: desc,
						settings,
					},
		} )
			.then( () => {
				if ( ! andApply || 'none' === target ) {
					return null;
				}
				return request( {
					path: `${ NS }/network-settings/templates/${ encodeURIComponent( finalSlug ) }/apply`,
					method: 'POST',
					data: {
						scope: 'sites',
						force: 'force' === mode,
						sites: 'choose' === target ? [ ...chosen ] : [],
					},
				} );
			} )
			.then( ( applied ) => {
				setSaving( false );
				const updated = Number( applied?.updated_count ) || 0;
				onSaved(
					applied
						? sprintf(
								/* translators: 1: template name, 2: number of sites */
								_n(
									'%1$s saved and applied: %2$s site updated',
									'%1$s saved and applied: %2$s sites updated',
									updated,
									'zero-spam'
								),
								name.trim(),
								num( updated )
							)
						: sprintf(
								/* translators: %s: template name */
								__( 'Template “%s” saved', 'zero-spam' ),
								name.trim()
							)
				);
			} )
			.catch( ( error ) => {
				setSaving( false );
				const params = error?.data?.params;
				if ( params && 'object' === typeof params ) {
					setErrors( params );
				}
				setFailure(
					errorText(
						error,
						__(
							'The template could not be saved. Please try again.',
							'zero-spam'
						)
					)
				);
			} );
	};

	let eyebrow = __( 'New template', 'zero-spam' );
	if ( builtIn ) {
		eyebrow = __( 'Edit a copy of a built-in template', 'zero-spam' );
	} else if ( template ) {
		eyebrow = __( 'Edit template', 'zero-spam' );
	}
	const siteCount = 'choose' === target ? chosen.size : allSites;

	return (
		<Drawer
			open
			wide
			onClose={ onClose }
			labelledBy="zs-tp-title"
			className="zs-tpldrawer"
			header={
				<div className="zs-dh">
					<span className="zs-ico">
						<svg
							className="zs-icon"
							aria-hidden="true"
							focusable="false"
						>
							<use href="#zs-i-network" />
						</svg>
					</span>
					<div className="zs-grow">
						<div className="zs-eyebrow">{ eyebrow }</div>
						<h2 id="zs-tp-title" className="zs-tile-title">
							{ template
								? template.name
								: __( 'New template', 'zero-spam' ) }
						</h2>
					</div>
				</div>
			}
			footer={
				<>
					{ failure && (
						<p className="zs-actres zs-actres-err" role="alert">
							{ failure }
						</p>
					) }
					<div className="zs-inline-row">
						<Button
							disabled={ saving }
							onClick={ () => save( true ) }
						>
							{ 'none' === target
								? __( 'Save template', 'zero-spam' )
								: sprintf(
										/* translators: %s: number of sites */
										_n(
											'Save and apply to %s site',
											'Save and apply to %s sites',
											siteCount,
											'zero-spam'
										),
										num( siteCount )
									) }
						</Button>
						{ 'none' !== target && (
							<Button
								variant="secondary"
								disabled={ saving }
								onClick={ () => save( false ) }
							>
								{ __( 'Save template', 'zero-spam' ) }
							</Button>
						) }
						<Button variant="ghost" onClick={ onClose }>
							{ __( 'Cancel', 'zero-spam' ) }
						</Button>
					</div>
				</>
			}
		>
			<form
				className="zs-form zs-tplform"
				noValidate
				onSubmit={ ( e ) => e.preventDefault() }
			>
				<div className="zs-sect">
					<h3>{ __( '1 · Details', 'zero-spam' ) }</h3>
					{ builtIn && (
						<p className="zs-hint">
							{ __(
								'Built-in templates stay as they are; saving creates your own copy.',
								'zero-spam'
							) }
						</p>
					) }
					<div className="zs-field">
						<label htmlFor="zs-tp-name">
							{ __( 'Template name', 'zero-spam' ) }
						</label>
						<input
							id="zs-tp-name"
							className="zs-input"
							value={ name }
							maxLength={ 255 }
							data-autofocus={ apply ? undefined : true }
							aria-invalid={ errors.name ? 'true' : 'false' }
							aria-describedby="zs-tp-name-e"
							placeholder={ __(
								'My custom template',
								'zero-spam'
							) }
							onChange={ ( e ) => {
								setName( e.target.value );
								if ( ! slugTouched ) {
									setSlug( slugify( e.target.value ) );
								}
							} }
						/>
						<span className="zs-ferr" id="zs-tp-name-e">
							{ fieldError( 'name' ) }
						</span>
					</div>
					<div className="zs-field">
						<label htmlFor="zs-tp-slug">
							{ __( 'Template slug', 'zero-spam' ) }
						</label>
						<input
							id="zs-tp-slug"
							className="zs-input zs-mono"
							value={
								template && ! builtIn ? template.slug : slug
							}
							readOnly={ Boolean( template && ! builtIn ) }
							maxLength={ 63 }
							spellCheck="false"
							autoComplete="off"
							aria-invalid={ errors.slug ? 'true' : 'false' }
							aria-describedby="zs-tp-slug-h zs-tp-slug-e"
							placeholder="my-custom-template"
							onChange={ ( e ) => {
								setSlugTouched( true );
								setSlug( e.target.value );
							} }
						/>
						<span className="zs-hint" id="zs-tp-slug-h">
							{ template && ! builtIn
								? __(
										'The slug of a saved template does not change.',
										'zero-spam'
									)
								: __(
										'Lowercase letters, numbers and hyphens only.',
										'zero-spam'
									) }
						</span>
						<span className="zs-ferr" id="zs-tp-slug-e">
							{ fieldError( 'slug' ) }
						</span>
					</div>
					<div className="zs-field">
						<label htmlFor="zs-tp-desc">
							{ __( 'Description', 'zero-spam' ) }
						</label>
						<textarea
							id="zs-tp-desc"
							className="zs-textarea zs-textarea-ui"
							rows={ 2 }
							value={ desc }
							onChange={ ( e ) => setDesc( e.target.value ) }
						/>
					</div>
				</div>

				<div className="zs-sect">
					<h3 id="zs-tp-settings-h">
						{ __( '2 · Settings', 'zero-spam' ) }{ ' ' }
						<Badge>
							{ sprintf(
								/* translators: %s: number of settings */
								__( '%s chosen', 'zero-spam' ),
								num( keys.length )
							) }
						</Badge>
					</h3>
					<span
						className="zs-ferr"
						id="zs-tp-settings-e"
						role="alert"
					>
						{ fieldError( 'settings' ) }
					</span>
					<div className="zs-field zs-search">
						<label htmlFor="zs-tp-settings" className="zs-sr-only">
							{ __( 'Find a setting', 'zero-spam' ) }
						</label>
						<Icon name="search" size="sm" />
						<input
							id="zs-tp-settings"
							className="zs-input"
							type="search"
							placeholder={ __( 'Find a setting', 'zero-spam' ) }
							value={ search }
							aria-describedby="zs-tp-settings-e"
							onChange={ ( e ) => setSearch( e.target.value ) }
						/>
					</div>
					{ cards.map( ( card ) => {
						const list = card.entries.filter(
							( e ) =>
								! q ||
								String( e.title ).toLowerCase().includes( q )
						);
						if ( ! list.length ) {
							return null;
						}
						const picked = card.entries.filter(
							( e ) => e.key in values
						).length;
						return (
							<details
								key={ card.key }
								className="zs-fine zs-tpg"
								open={ Boolean( q ) || picked > 0 || undefined }
							>
								<summary>
									{ card.title }{ ' ' }
									<span className="zs-c">
										{ picked }/{ card.entries.length }
									</span>
								</summary>
								{ list.map( ( entry ) => {
									const included = entry.key in values;
									return (
										<div
											key={ entry.key }
											className="zs-tprow"
										>
											<label
												className="zs-tpinc"
												htmlFor={ `zs-tp-i-${ idOf(
													entry.key
												) }` }
											>
												<input
													id={ `zs-tp-i-${ idOf(
														entry.key
													) }` }
													type="checkbox"
													checked={ included }
													onChange={ ( e ) =>
														include(
															entry,
															e.target.checked
														)
													}
												/>
												<span>{ entry.title }</span>
											</label>
											{ included && (
												<div className="zs-tpval">
													<Field
														entry={ {
															...entry,
															value: values[
																entry.key
															],
															description: '',
														} }
														value={
															values[ entry.key ]
														}
														onChange={ (
															key,
															value
														) =>
															setValues(
																(
																	current
																) => ( {
																	...current,
																	[ key ]:
																		value,
																} )
															)
														}
														entries={ entries }
														cardTitle={
															entry.title
														}
													/>
												</div>
											) }
										</div>
									);
								} ) }
							</details>
						);
					} ) }
					{ keys
						.filter( ( k ) => ! entries[ k ] )
						.map( ( k ) => (
							<p key={ k } className="zs-hint">
								{ sprintf(
									/* translators: %s: setting key */
									__(
										'%s is kept as it is: its plugin is not active on the main site.',
										'zero-spam'
									),
									k
								) }
							</p>
						) ) }
				</div>

				<div className="zs-sect" id="zs-tp-apply">
					<h3>{ __( '3 · Apply to sites', 'zero-spam' ) }</h3>
					<fieldset className="zs-plain">
						<legend className="zs-sr-only">
							{ __( 'Sites', 'zero-spam' ) }
						</legend>
						<div className="zs-radios zs-radios-one">
							{ [
								[
									'none',
									__( 'Don’t apply now', 'zero-spam' ),
									__(
										'Save the template only.',
										'zero-spam'
									),
								],
								[
									'all',
									__( 'Every site', 'zero-spam' ),
									sprintf(
										/* translators: %s: number of sites */
										_n(
											'All %s site in the network.',
											'All %s sites in the network.',
											allSites,
											'zero-spam'
										),
										num( allSites )
									),
								],
								[
									'choose',
									__( 'Sites I choose', 'zero-spam' ),
									__(
										'Pick sites from a list.',
										'zero-spam'
									),
								],
							].map( ( [ value, label, hint ] ) => (
								<label
									key={ value }
									className="zs-rcard"
									htmlFor={ `zs-tp-t-${ value }` }
								>
									<input
										id={ `zs-tp-t-${ value }` }
										type="radio"
										name="zs-tp-target"
										value={ value }
										checked={ target === value }
										data-autofocus={
											apply && 'all' === value
												? true
												: undefined
										}
										onChange={ () => setTarget( value ) }
									/>
									<span>
										{ label }
										<small>{ hint }</small>
									</span>
								</label>
							) ) }
						</div>
					</fieldset>
					{ 'choose' === target && (
						<fieldset className="zs-plain">
							<legend className="zs-lbl-strong">
								{ __( 'Sites', 'zero-spam' ) }
							</legend>
							<div className="zs-field zs-search">
								<label
									htmlFor="zs-tp-sites"
									className="zs-sr-only"
								>
									{ __( 'Find a site', 'zero-spam' ) }
								</label>
								<Icon name="search" size="sm" />
								<input
									id="zs-tp-sites"
									className="zs-input"
									type="search"
									placeholder={ __(
										'Find a site by its address',
										'zero-spam'
									) }
									value={ siteSearch }
									aria-describedby="zs-tp-sites-e"
									onChange={ ( e ) =>
										setSiteSearch( e.target.value )
									}
								/>
							</div>
							<span
								className="zs-ferr"
								id="zs-tp-sites-e"
								role="alert"
							>
								{ fieldError( 'sites' ) }
							</span>
							<div
								className="zs-checklist"
								aria-busy={ sites.loading ? 'true' : undefined }
							>
								{ ( Array.isArray( sites.data )
									? sites.data
									: []
								).map( ( site ) => (
									<label
										key={ site.id }
										htmlFor={ `zs-tp-s-${ site.id }` }
									>
										<input
											id={ `zs-tp-s-${ site.id }` }
											type="checkbox"
											checked={ chosen.has( site.id ) }
											onChange={ ( e ) =>
												setChosen( ( current ) => {
													const next = new Set(
														current
													);
													if ( e.target.checked ) {
														next.add( site.id );
													} else {
														next.delete( site.id );
													}
													return next;
												} )
											}
										/>
										<span>
											{ site.name || site.url }
											<small className="zs-mono">
												{ site.url }
											</small>
										</span>
									</label>
								) ) }
							</div>
							{ sitesTotal > 50 && (
								<p className="zs-hint">
									{ sprintf(
										/* translators: %s: number of sites */
										__(
											'Showing 50 of %s sites. Search to find others.',
											'zero-spam'
										),
										num( sitesTotal )
									) }
								</p>
							) }
						</fieldset>
					) }
					{ 'none' !== target && (
						<fieldset className="zs-plain">
							<legend className="zs-lbl-strong">
								{ __(
									'When a site already changed a setting',
									'zero-spam'
								) }
							</legend>
							<div className="zs-radios zs-radios-one">
								<label
									className="zs-rcard"
									htmlFor="zs-tp-m-fill"
								>
									<input
										id="zs-tp-m-fill"
										type="radio"
										name="zs-tp-mode"
										value="fill"
										checked={ 'fill' === mode }
										onChange={ () => setMode( 'fill' ) }
									/>
									<span>
										{ __(
											'Keep the site’s own value',
											'zero-spam'
										) }
										<small>
											{ __(
												'Recommended. Only settings the site hasn’t changed are updated.',
												'zero-spam'
											) }
										</small>
									</span>
								</label>
								<label
									className="zs-rcard"
									htmlFor="zs-tp-m-force"
								>
									<input
										id="zs-tp-m-force"
										type="radio"
										name="zs-tp-mode"
										value="force"
										checked={ 'force' === mode }
										onChange={ () => setMode( 'force' ) }
									/>
									<span>
										{ __(
											'Use the template’s value',
											'zero-spam'
										) }
										<small>
											{ __(
												'Replaces site changes. Locked settings are never overwritten.',
												'zero-spam'
											) }
										</small>
									</span>
								</label>
							</div>
						</fieldset>
					) }
				</div>
			</form>
		</Drawer>
	);
}

/**
 * The Templates tab.
 */
export function Templates() {
	const { toast } = useFeedback();
	const schema = useResource( PATHS.schema );
	const list = useResource( PATHS.templates );
	const layer = useResource( PATHS.values );
	const [ drawer, setDrawer ] = useState( null );
	const [ asking, setAsking ] = useState( '' );
	const [ result, setResult ] = useState( null );
	const opener = useRef( null );

	const entries = useMemo( () => byKey( schema.data ), [ schema.data ] );
	const cards = useMemo(
		() => networkCards( schema.data, layer.data ),
		[ schema.data, layer.data ]
	);
	const templates = useMemo(
		() =>
			list.data && 'object' === typeof list.data
				? Object.values( list.data )
				: [],
		[ list.data ]
	);

	if ( ! schema.data || ! list.data ) {
		return (
			<LoadState
				res={ list.data ? schema : list }
				what={ __( 'The templates could not be loaded.', 'zero-spam' ) }
				retry={ () => {
					schema.reload();
					list.reload();
				} }
			/>
		);
	}

	const network = {
		...readLayer( layer.data ),
		sites: Number( boot.network?.sites ) || 0,
	};

	const applyToNetwork = ( template ) => {
		const before = {};
		Object.keys( template.settings || {} ).forEach( ( k ) => {
			if (
				Object.prototype.hasOwnProperty.call( network.values, k ) &&
				entries[ k ] &&
				! entries[ k ].secret
			) {
				before[ k ] = toPayloadValue(
					entries[ k ],
					network.values[ k ]
				);
			}
		} );
		const n = Object.keys( template.settings || {} ).length;
		setAsking( '' );
		request( {
			path: `${ NS }/network-settings/templates/${ encodeURIComponent( template.slug ) }/apply`,
			method: 'POST',
			data: { scope: 'network' },
		} )
			.then( () => {
				layer.reload();
				setResult( null );
				toast(
					sprintf(
						/* translators: 1: template name, 2: number of settings */
						_n(
							'%1$s: %2$s network default updated',
							'%1$s: %2$s network defaults updated',
							n,
							'zero-spam'
						),
						template.name,
						num( n )
					),
					Object.keys( before ).length
						? {
								undo: () =>
									request( {
										path: PATHS.values,
										method: 'PATCH',
										data: before,
									} )
										.then( () => {
											layer.reload();
											toast(
												__(
													'Network defaults restored',
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
			.catch( ( error ) =>
				setResult( {
					ok: false,
					text: errorText(
						error,
						__(
							'The template could not be applied. Please try again.',
							'zero-spam'
						)
					),
				} )
			);
	};

	const remove = ( template ) => {
		setAsking( '' );
		request( {
			path: `${ NS }/network-settings/templates/${ encodeURIComponent( template.slug ) }`,
			method: 'DELETE',
		} )
			.then( () => {
				list.reload();
				document.getElementById( 'zs-net-body' )?.focus();
				toast(
					sprintf(
						/* translators: %s: template name */
						__( 'Template “%s” deleted', 'zero-spam' ),
						template.name
					),
					{
						undo: () =>
							request( {
								path: `${ NS }/network-settings/templates`,
								method: 'POST',
								data: {
									name: template.name,
									slug: template.slug,
									description: template.description || '',
									settings: template.settings || {},
								},
							} )
								.then( () => {
									list.reload();
									toast(
										__( 'Template restored', 'zero-spam' )
									);
								} )
								.catch( () =>
									toast(
										__(
											'The template could not be restored. Please try again.',
											'zero-spam'
										)
									)
								),
					}
				);
			} )
			.catch( ( error ) =>
				setResult( {
					ok: false,
					text: errorText(
						error,
						__(
							'The template could not be deleted. Please try again.',
							'zero-spam'
						)
					),
				} )
			);
	};

	const open = ( template, apply, event ) => {
		opener.current = event?.currentTarget || null;
		setDrawer( { template, apply } );
	};

	return (
		<>
			<div className="zs-tbar">
				<p className="zs-hint zs-grow">
					{ __(
						'A template is a saved set of network settings. Apply it to the network defaults, or to the sites you choose.',
						'zero-spam'
					) }
				</p>
				<Button icon="plus" onClick={ ( e ) => open( null, false, e ) }>
					{ __( 'New template', 'zero-spam' ) }
				</Button>
			</div>
			<ActionResult result={ result } />
			<div className="zs-wall">
				{ templates.map( ( template, i ) => {
					const builtIn = 'built_in' === template.type;
					const { chips, more } = templateChips( template, entries );
					const n = Object.keys( template.settings || {} ).length;
					return (
						<section
							key={ template.slug }
							className="zs-tile zs-s6 zs-tplcard"
							aria-labelledby={ `zs-tpl-${ i }` }
						>
							<div className="zs-tile-h">
								<h2
									className="zs-tile-title"
									id={ `zs-tpl-${ i }` }
								>
									{ template.name }
								</h2>
								<Badge tone={ builtIn ? undefined : 'info' }>
									{ builtIn
										? __( 'Built-in', 'zero-spam' )
										: __( 'Custom', 'zero-spam' ) }
								</Badge>
							</div>
							{ template.description && (
								<p>{ template.description }</p>
							) }
							<div className="zs-sect">
								<h3>
									{ sprintf(
										/* translators: %s: number of settings */
										_n(
											'%s setting',
											'%s settings',
											n,
											'zero-spam'
										),
										num( n )
									) }
								</h3>
								<ul className="zs-chips zs-tplchips">
									{ chips.map( ( c ) => (
										<li
											key={ c.key }
											className="zs-chip zs-tplchip"
										>
											{ c.title }: <b>{ c.value }</b>
										</li>
									) ) }
									{ more > 0 && (
										<li className="zs-chip zs-tplchip">
											{ sprintf(
												/* translators: %s: number of settings */
												__( '+%s more', 'zero-spam' ),
												num( more )
											) }
										</li>
									) }
								</ul>
							</div>
							{ template.created_at && (
								<p className="zs-hint">
									{ sprintf(
										/* translators: %s: date */
										__( 'Created %s', 'zero-spam' ),
										day(
											String(
												template.created_at
											).replace( ' ', 'T' ),
											new Date( nowLocal() )
										)
									) }
								</p>
							) }
							{ asking === `net:${ template.slug }` && (
								<Confirm
									id={ `zs-tpq-${ i }` }
									question={ sprintf(
										/* translators: %s: number of settings */
										_n(
											'This changes %s network default. Sites that changed it keep their value.',
											'This changes %s network defaults. Sites that changed them keep their values.',
											n,
											'zero-spam'
										),
										num( n )
									) }
									yes={ __(
										'Apply to network defaults',
										'zero-spam'
									) }
									onYes={ () => applyToNetwork( template ) }
									onCancel={ () => setAsking( '' ) }
								/>
							) }
							{ asking === `del:${ template.slug }` && (
								<Confirm
									id={ `zs-tpd-${ i }` }
									question={ sprintf(
										/* translators: %s: template name */
										__(
											'Delete the template “%s”?',
											'zero-spam'
										),
										template.name
									) }
									yes={ __( 'Delete template', 'zero-spam' ) }
									onYes={ () => remove( template ) }
									onCancel={ () => setAsking( '' ) }
								/>
							) }
							{ ! asking.endsWith( `:${ template.slug }` ) && (
								<div
									className={ cx(
										'zs-inline-row',
										'zs-tplacts'
									) }
								>
									<Button
										variant="secondary"
										size="sm"
										onClick={ ( e ) =>
											open( template, true, e )
										}
									>
										{ __( 'Apply to sites…', 'zero-spam' ) }
									</Button>
									<Button
										variant="secondary"
										size="sm"
										onClick={ () =>
											setAsking(
												`net:${ template.slug }`
											)
										}
									>
										{ __(
											'Apply to network defaults',
											'zero-spam'
										) }
									</Button>
									<Button
										variant="ghost"
										size="sm"
										icon="edit"
										aria-label={
											builtIn
												? sprintf(
														/* translators: %s: template name */
														__(
															'Edit a copy of %s',
															'zero-spam'
														),
														template.name
													)
												: sprintf(
														/* translators: %s: template name */
														__(
															'Edit %s',
															'zero-spam'
														),
														template.name
													)
										}
										onClick={ ( e ) =>
											open( template, false, e )
										}
									>
										{ builtIn
											? __( 'Edit a copy', 'zero-spam' )
											: __( 'Edit', 'zero-spam' ) }
									</Button>
									{ ! builtIn && (
										<Button
											variant="ghost"
											size="sm"
											icon="trash"
											aria-label={ sprintf(
												/* translators: %s: template name */
												__( 'Delete %s', 'zero-spam' ),
												template.name
											) }
											onClick={ () =>
												setAsking(
													`del:${ template.slug }`
												)
											}
										>
											{ __( 'Delete', 'zero-spam' ) }
										</Button>
									) }
								</div>
							) }
						</section>
					);
				} ) }
			</div>
			{ drawer && (
				<TemplateDrawer
					template={ drawer.template }
					apply={ drawer.apply }
					cards={ cards }
					entries={ entries }
					network={ network }
					onClose={ () => setDrawer( null ) }
					onSaved={ ( message ) => {
						setDrawer( null );
						list.reload();
						toast( message );
					} }
				/>
			) }
		</>
	);
}
