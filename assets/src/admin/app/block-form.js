/**
 * Block an IP address or a location, or change its block, in a drawer
 * (prototype §4.1, §4.2): the address (or the location type and value),
 * Temporary or Permanent, a reason, and the start and end in the site's
 * time. Checked in the browser first (data/validate.js: an address in
 * canonical form, no ranges), then saved through
 * `zero-spam/v1/rules/blocks`; the server's field errors are shown next to
 * their fields the same way. Shared by every screen that offers "Block IP",
 * "Block a location" or "Edit block".
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button } from './primitives';
import { Drawer } from './primitives/drawer';
import { NS, nowLocal, request } from '../data/api';
import { LOCATION_TYPES, checkBlockIp, checkLocation } from '../data/validate';
import { blockBody, initialLocationValues, initialValues } from './block-data';

/** @return {Object<string, string>} Location type names. */
export const locationTypes = () => ( {
	country_code: __( 'Country code', 'zero-spam' ),
	region_code: __( 'Region code', 'zero-spam' ),
	city: __( 'City name', 'zero-spam' ),
	zip: __( 'Zip/Postal code', 'zero-spam' ),
} );

/**
 * Finds an address's block, if it has one.
 *
 * @param {string} ip Address.
 * @return {Promise<Object|null>} Block.
 */
export function findBlock( ip ) {
	const wanted = String( ip ).trim().toLowerCase();
	return request( {
		path: `${ NS }/rules/blocks?kind=ip&per_page=10&search=${ encodeURIComponent( wanted ) }`,
	} ).then( ( list ) =>
		Array.isArray( list )
			? list.find(
					( b ) => String( b.ip || '' ).toLowerCase() === wanted
				) || null
			: null
	);
}

/**
 * @param {Object}                          props               Props.
 * @param {boolean}                         props.open          Shown.
 * @param {string}                          [props.kind]        ip (default) or location.
 * @param {string}                          [props.ip]          Address to block.
 * @param {Object|null}                     [props.block]       Block to change.
 * @param {boolean}                         [props.lookup]      Look the address's block up first (it is blocked, the block is not known).
 * @param {(...args: unknown[]) => unknown} props.onClose       Closes.
 * @param {(...args: unknown[]) => unknown} props.onSaved       Receives ( block, created ).
 * @param {(...args: unknown[]) => unknown} [props.returnFocus] Where focus goes on close.
 */
export function BlockForm( {
	open,
	kind = 'ip',
	ip = '',
	block = null,
	lookup = false,
	onClose,
	onSaved,
	returnFocus,
} ) {
	const shift = nowLocal() - Date.now();
	const isLocation = 'location' === kind;
	const [ record, setRecord ] = useState( block );
	const [ finding, setFinding ] = useState( lookup && ! block );
	const [ values, setValues ] = useState( () =>
		isLocation
			? initialLocationValues( block, nowLocal(), shift )
			: initialValues( block, ip, nowLocal(), shift )
	);
	const [ errors, setErrors ] = useState( {} );
	const [ failure, setFailure ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const form = useRef( null );

	useEffect( () => {
		if ( ! lookup || block || ! ip ) {
			return;
		}
		let alive = true;
		findBlock( ip )
			.then( ( found ) => {
				if ( alive && found ) {
					setRecord( found );
					setValues( initialValues( found, ip, nowLocal(), shift ) );
				}
			} )
			.catch( () => {} )
			.finally( () => alive && setFinding( false ) );
		return () => {
			alive = false;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Once per opening.
	}, [] );

	const set = ( key ) => ( event ) =>
		setValues( { ...values, [ key ]: event.target.value } );

	const focusField = ( key ) =>
		window.requestAnimationFrame( () =>
			form.current?.querySelector( `[name="${ key }"]` )?.focus()
		);

	const submit = ( event ) => {
		event.preventDefault();
		const local = {};
		const checked = isLocation
			? checkLocation( values.key_type, values.key )
			: checkBlockIp( values.ip );
		if ( checked.error ) {
			local[ isLocation ? 'key' : 'ip' ] = checked.error;
		}
		if ( 'temporary' === values.type && ! values.end ) {
			local.end = __(
				'Choose when the block ends, or make it permanent.',
				'zero-spam'
			);
		}
		setErrors( local );
		setFailure( '' );
		if ( Object.keys( local ).length ) {
			focusField( Object.keys( local )[ 0 ] );
			return;
		}
		setSaving( true );
		request( {
			path: record
				? `${ NS }/rules/blocks/${ record.id }`
				: `${ NS }/rules/blocks`,
			method: record ? 'PUT' : 'POST',
			data: blockBody( {
				...values,
				[ isLocation ? 'key' : 'ip' ]: checked.value,
			} ),
			parse: false,
		} )
			.then( ( response ) =>
				response.json().then( ( saved ) => {
					setSaving( false );
					onSaved( saved, ! record && 201 === response.status );
				} )
			)
			.catch( ( error ) => {
				setSaving( false );
				const read =
					error && 'function' === typeof error.json
						? error.json()
						: Promise.resolve( error );
				read.then(
					( data ) => {
						const params = data?.data?.params;
						if ( params && 'object' === typeof params ) {
							setErrors( params );
							focusField( Object.keys( params )[ 0 ] );
						} else {
							setFailure(
								data?.message ||
									__(
										'The block could not be saved. Please try again.',
										'zero-spam'
									)
							);
						}
					},
					() =>
						setFailure(
							__(
								'The block could not be saved. Please try again.',
								'zero-spam'
							)
						)
				);
			} );
	};

	const field = ( key, label, input, hint ) => (
		<div className="zs-field">
			<label htmlFor={ `zs-bf-${ key }` }>{ label }</label>
			{ input }
			{ hint && (
				<span className="zs-hint" id={ `zs-bf-${ key }-h` }>
					{ hint }
				</span>
			) }
			<span className="zs-ferr" id={ `zs-bf-${ key }-e` }>
				{ errors[ key ] || '' }
			</span>
		</div>
	);

	const described = ( key, hint ) =>
		[ hint && `zs-bf-${ key }-h`, `zs-bf-${ key }-e` ]
			.filter( Boolean )
			.join( ' ' );

	let eyebrow = isLocation
		? __( 'Block a location', 'zero-spam' )
		: __( 'Block an IP', 'zero-spam' );
	if ( record ) {
		eyebrow = __( 'Edit block', 'zero-spam' );
	}
	let heading = values.ip || __( 'New IP block', 'zero-spam' );
	if ( isLocation ) {
		heading = values.key || __( 'New location block', 'zero-spam' );
	}
	let submitLabel = isLocation
		? __( 'Block location', 'zero-spam' )
		: __( 'Block IP', 'zero-spam' );
	if ( record ) {
		submitLabel = __( 'Save block', 'zero-spam' );
	}

	return (
		<Drawer
			open={ open }
			onClose={ onClose }
			labelledBy="zs-bf-title"
			className="zs-blockform"
			returnFocus={ returnFocus }
			busy={ finding }
			header={
				<div className="zs-dh">
					<span className="zs-ico zs-ico-danger">
						<svg
							className="zs-icon"
							aria-hidden="true"
							focusable="false"
						>
							<use
								href={
									isLocation ? '#zs-i-globe' : '#zs-i-ban'
								}
							/>
						</svg>
					</span>
					<div className="zs-grow">
						<div className="zs-eyebrow">{ eyebrow }</div>
						<h2 id="zs-bf-title" className="zs-bigip">
							{ heading }
						</h2>
					</div>
				</div>
			}
			footer={
				<div className="zs-inline-row">
					<Button
						type="submit"
						form="zs-block-form"
						disabled={ saving || finding }
					>
						{ submitLabel }
					</Button>
					<Button variant="ghost" onClick={ onClose }>
						{ __( 'Cancel', 'zero-spam' ) }
					</Button>
				</div>
			}
		>
			<form
				id="zs-block-form"
				ref={ form }
				className="zs-form"
				noValidate
				onSubmit={ submit }
			>
				{ failure && (
					<p className="zs-notice zs-notice-err" role="alert">
						{ failure }
					</p>
				) }
				{ ! isLocation &&
					field(
						'ip',
						__( 'IP address', 'zero-spam' ),
						<input
							id="zs-bf-ip"
							name="ip"
							className="zs-input zs-mono"
							value={ values.ip }
							onChange={ set( 'ip' ) }
							placeholder={ __(
								'e.g. 203.0.113.9',
								'zero-spam'
							) }
							autoComplete="off"
							spellCheck="false"
							aria-invalid={ errors.ip ? 'true' : 'false' }
							aria-describedby={ described( 'ip' ) }
						/>
					) }
				{ isLocation &&
					field(
						'key_type',
						__( 'Location type', 'zero-spam' ),
						<select
							id="zs-bf-key_type"
							name="key_type"
							className="zs-select"
							value={ values.key_type }
							onChange={ set( 'key_type' ) }
							aria-invalid={ errors.key_type ? 'true' : 'false' }
							aria-describedby={ described( 'key_type' ) }
						>
							{ LOCATION_TYPES.map( ( t ) => (
								<option key={ t } value={ t }>
									{ locationTypes()[ t ] }
								</option>
							) ) }
						</select>
					) }
				{ isLocation &&
					field(
						'key',
						__( 'Location', 'zero-spam' ),
						<input
							id="zs-bf-key"
							name="key"
							className="zs-input zs-mono"
							value={ values.key }
							onChange={ set( 'key' ) }
							placeholder={ __( 'e.g. US', 'zero-spam' ) }
							autoComplete="off"
							spellCheck="false"
							aria-invalid={ errors.key ? 'true' : 'false' }
							aria-describedby={ described( 'key', true ) }
						/>,
						__(
							'A two-letter country code, a region code, a city name or a postal code, as the type says.',
							'zero-spam'
						)
					) }
				<fieldset className="zs-field">
					<legend>{ __( 'Type', 'zero-spam' ) }</legend>
					<div className="zs-seg zs-seg-radios">
						{ [
							[ 'temporary', __( 'Temporary', 'zero-spam' ) ],
							[ 'permanent', __( 'Permanent', 'zero-spam' ) ],
						].map( ( [ value, label ] ) => (
							<label
								key={ value }
								className="zs-segr"
								htmlFor={ `zs-bf-type-${ value }` }
							>
								<input
									id={ `zs-bf-type-${ value }` }
									type="radio"
									name="type"
									value={ value }
									checked={ values.type === value }
									onChange={ set( 'type' ) }
								/>
								{ label }
							</label>
						) ) }
					</div>
					<span className="zs-ferr" id="zs-bf-type-e">
						{ errors.type || '' }
					</span>
				</fieldset>
				{ field(
					'reason',
					__( 'Reason', 'zero-spam' ),
					<input
						id="zs-bf-reason"
						name="reason"
						className="zs-input"
						value={ values.reason }
						onChange={ set( 'reason' ) }
						maxLength={ 255 }
						placeholder={ __(
							'e.g. Spammed the contact form',
							'zero-spam'
						) }
						aria-invalid={ errors.reason ? 'true' : 'false' }
						aria-describedby={ described( 'reason' ) }
					/>
				) }
				{ field(
					'start',
					__( 'Start (optional)', 'zero-spam' ),
					<input
						id="zs-bf-start"
						name="start"
						type="datetime-local"
						className="zs-input"
						value={ values.start }
						onChange={ set( 'start' ) }
						aria-invalid={ errors.start ? 'true' : 'false' }
						aria-describedby={ described( 'start', true ) }
					/>,
					__(
						'Empty starts the block now. Times are in the site’s time zone.',
						'zero-spam'
					)
				) }
				{ 'temporary' === values.type &&
					field(
						'end',
						__( 'End', 'zero-spam' ),
						<input
							id="zs-bf-end"
							name="end"
							type="datetime-local"
							className="zs-input"
							value={ values.end }
							onChange={ set( 'end' ) }
							aria-invalid={ errors.end ? 'true' : 'false' }
							aria-describedby={ described( 'end' ) }
						/>
					) }
				{ record && (
					<p className="zs-hint">
						{ sprintf(
							/* translators: %s: IP address */
							__(
								'Saving replaces the current block of %s.',
								'zero-spam'
							),
							record.ip || record.key
						) }
					</p>
				) }
			</form>
		</Drawer>
	);
}
