/**
 * Network Import/Export (prototype §9): the network values as JSON to copy
 * or download (license and API keys and webhook addresses left out), and
 * an import from a file exported by a Zero Spam network (Merge, Replace or
 * Add only), checked and confirmed before anything changes.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { NS, request, useResource } from '../data/api';
import { num } from '../data/format';
import { PATHS, exportJson, parseImport } from './model';
import { ActionResult, Confirm, errorText } from './parts';

/** Largest file accepted, in bytes. */
const MAX_BYTES = 5 * 1024 * 1024;

/**
 * The Import/Export tab.
 */
export function ImportExport() {
	const { toast } = useFeedback();
	const exported = useResource( PATHS.exported );
	const [ mode, setMode ] = useState( 'merge' );
	const [ pending, setPending ] = useState( null );
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const file = useRef( null );
	const json = exportJson( exported.data );

	const copy = () => {
		const done = () =>
			toast( __( 'Network settings copied', 'zero-spam' ) );
		if ( window.navigator?.clipboard?.writeText ) {
			window.navigator.clipboard
				.writeText( json )
				.then( done )
				.catch( () =>
					toast(
						__(
							'Copying did not work. Select the text and copy it.',
							'zero-spam'
						)
					)
				);
		}
	};

	const download = () => {
		const blob = new window.Blob( [ json ], { type: 'application/json' } );
		const url = window.URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = 'zero-spam-network-settings.json';
		document.body.appendChild( link );
		link.click();
		link.remove();
		window.URL.revokeObjectURL( url );
	};

	const check = ( event ) => {
		event.preventDefault();
		setResult( null );
		const chosen = file.current?.files?.[ 0 ];
		const fail = ( text ) => {
			setResult( { ok: false, text } );
			file.current?.focus();
		};
		if ( ! chosen ) {
			fail( __( 'Choose a JSON file to import.', 'zero-spam' ) );
			return;
		}
		if ( ! /\.json$/i.test( chosen.name ) ) {
			fail(
				__(
					'That isn’t a JSON file. Choose a .json file.',
					'zero-spam'
				)
			);
			return;
		}
		if ( chosen.size > MAX_BYTES ) {
			fail( __( 'That file is larger than 5 MB.', 'zero-spam' ) );
			return;
		}
		const reader = new window.FileReader();
		reader.onload = () => {
			try {
				const settings = parseImport( String( reader.result || '' ) );
				setPending( settings );
			} catch {
				fail(
					__(
						'This file isn’t valid JSON settings. Export it again and retry.',
						'zero-spam'
					)
				);
			}
		};
		reader.onerror = () =>
			fail( __( 'The file could not be read. Try again.', 'zero-spam' ) );
		reader.readAsText( chosen );
	};

	const run = () => {
		setBusy( true );
		request( {
			path: `${ NS }/network-settings/import`,
			method: 'POST',
			data: {
				settings: pending,
				mode: 'add' === mode ? 'add_only' : mode,
			},
		} )
			.then( ( answer ) => {
				const imported = Number( answer?.imported_count ) || 0;
				const skipped =
					( Number( answer?.skipped_count ) || 0 ) +
					( Array.isArray( answer?.skipped_keys )
						? answer.skipped_keys.length
						: 0 );
				const invalid = Object.keys( answer?.invalid || {} ).length;
				const parts = [
					sprintf(
						/* translators: %s: number of settings */
						_n(
							'%s setting imported.',
							'%s settings imported.',
							imported,
							'zero-spam'
						),
						num( imported )
					),
				];
				if ( skipped ) {
					parts.push(
						sprintf(
							/* translators: %s: number of settings */
							_n(
								'%s skipped.',
								'%s skipped.',
								skipped,
								'zero-spam'
							),
							num( skipped )
						)
					);
				}
				if ( invalid ) {
					parts.push(
						sprintf(
							/* translators: %s: number of settings */
							_n(
								'%s had a value Zero Spam can’t use.',
								'%s had values Zero Spam can’t use.',
								invalid,
								'zero-spam'
							),
							num( invalid )
						)
					);
				}
				setResult( { ok: true, text: parts.join( ' ' ) } );
				exported.reload();
				if ( file.current ) {
					file.current.value = '';
				}
			} )
			.catch( ( error ) =>
				setResult( {
					ok: false,
					text: errorText(
						error,
						__(
							'The settings could not be imported. Please try again.',
							'zero-spam'
						)
					),
				} )
			)
			.finally( () => {
				setBusy( false );
				setPending( null );
			} );
	};

	const modes = [
		[
			'merge',
			__( 'Merge', 'zero-spam' ),
			__(
				'Keep existing settings and add new ones from the file. Lock status is kept for existing settings.',
				'zero-spam'
			),
		],
		[
			'replace',
			__( 'Replace', 'zero-spam' ),
			__(
				'Replace existing settings with the ones in the file, locks included.',
				'zero-spam'
			),
		],
		[
			'add',
			__( 'Add only', 'zero-spam' ),
			__(
				'Only add settings that don’t exist yet. Skip the rest.',
				'zero-spam'
			),
		],
	];

	return (
		<div className="zs-wall">
			<section className="zs-tile zs-s6" aria-labelledby="zs-nex-h">
				<h2 className="zs-tile-title" id="zs-nex-h">
					{ __( 'Export settings', 'zero-spam' ) }
				</h2>
				<p className="zs-hint">
					{ __(
						'Copy your network settings for backup or another network. License and API keys are left out.',
						'zero-spam'
					) }
				</p>
				<label htmlFor="zs-nex-box" className="zs-sr-only">
					{ __( 'Network settings JSON', 'zero-spam' ) }
				</label>
				<textarea
					id="zs-nex-box"
					className="zs-textarea"
					rows={ 8 }
					readOnly
					value={ undefined === exported.data ? '' : json }
					aria-busy={ exported.loading ? 'true' : undefined }
				/>
				<div className="zs-inline-row">
					<Button
						variant="secondary"
						size="sm"
						icon="copy"
						onClick={ copy }
						disabled={ undefined === exported.data }
					>
						{ __( 'Copy JSON', 'zero-spam' ) }
					</Button>
					<Button
						variant="secondary"
						size="sm"
						icon="down"
						onClick={ download }
						disabled={ undefined === exported.data }
					>
						{ __( 'Download file', 'zero-spam' ) }
					</Button>
				</div>
			</section>
			<form
				className="zs-tile zs-s6"
				aria-labelledby="zs-nim-h"
				onSubmit={ check }
				noValidate
			>
				<h2 className="zs-tile-title" id="zs-nim-h">
					{ __( 'Import settings', 'zero-spam' ) }
				</h2>
				<p className="zs-hint">
					{ __(
						'Choose a JSON file exported from a Zero Spam network.',
						'zero-spam'
					) }
				</p>
				<fieldset className="zs-plain">
					<legend className="zs-lbl-strong">
						{ __( 'Import mode', 'zero-spam' ) }
					</legend>
					<div className="zs-radios zs-radios-one">
						{ modes.map( ( [ value, label, hint ] ) => (
							<label
								key={ value }
								className="zs-rcard"
								htmlFor={ `zs-nim-${ value }` }
							>
								<input
									id={ `zs-nim-${ value }` }
									type="radio"
									name="zs-nim-mode"
									value={ value }
									checked={ mode === value }
									onChange={ () => setMode( value ) }
								/>
								<span>
									{ label }
									<small>{ hint }</small>
								</span>
							</label>
						) ) }
					</div>
				</fieldset>
				<div className="zs-field">
					<label htmlFor="zs-nim-file">
						{ __(
							'Settings file (.json, up to 5 MB)',
							'zero-spam'
						) }
					</label>
					<input
						ref={ file }
						id="zs-nim-file"
						className="zs-input"
						type="file"
						accept=".json,application/json"
						aria-describedby="zs-nim-res"
						onChange={ () => {
							setResult( null );
							setPending( null );
						} }
					/>
				</div>
				<ActionResult result={ result } id="zs-nim-res" />
				{ pending ? (
					<Confirm
						id="zs-nim-q"
						question={ sprintf(
							/* translators: %s: number of settings */
							_n(
								'The file has %s setting. Importing changes the network settings of every site that follows them.',
								'The file has %s settings. Importing changes the network settings of every site that follows them.',
								Object.keys( pending ).length,
								'zero-spam'
							),
							num( Object.keys( pending ).length )
						) }
						yes={ __( 'Import settings', 'zero-spam' ) }
						busy={ busy }
						onYes={ run }
						onCancel={ () => setPending( null ) }
					/>
				) : (
					<div>
						<Button variant="secondary" type="submit" icon="up">
							{ __( 'Import settings', 'zero-spam' ) }
						</Button>
					</div>
				) }
			</form>
		</div>
	);
}
