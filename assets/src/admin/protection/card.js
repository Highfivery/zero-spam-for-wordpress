/**
 * One Protection card: icon, title and blurb, a status badge, the
 * supported signals, the module's switch (or switches, or key field), its
 * detections, and every other setting of the section behind "Fine-tune".
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Badge, Icon, cx } from '../app/primitives';
import { boot, nowLocal } from '../data/api';
import { cardStatus, detectionLine, signalNames } from './cards';
import { Field } from './fields';
import { holdsValue } from './settings-model';

/**
 * The address of a wp-admin screen, built from the Overview's.
 *
 * @param {string} path Path inside wp-admin.
 * @return {string} URL.
 */
export const adminUrl = ( path ) => {
	const overview = boot.urls.overview || '';
	return overview.slice( 0, overview.indexOf( 'admin.php' ) ) + path;
};

/**
 * Renders the settings of a card.
 *
 * @param {Object}                          props            Props.
 * @param {Object[]}                        props.list       Schema entries.
 * @param {Object}                          props.settings   useSettings() editor.
 * @param {boolean}                         [props.off]      Disable the controls.
 * @param {(...args: unknown[]) => unknown} [props.onAction] After an action ran.
 */
function Rows( { list, settings, off = false, onAction } ) {
	return list.map( ( entry ) => (
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
			disabled={ off }
			onAction={ onAction }
		/>
	) );
}

/**
 * @param {Object}                          props            Props.
 * @param {Object}                          props.card       Card (buildCards()).
 * @param {Object}                          props.settings   useSettings() editor.
 * @param {Object}                          [props.license]  license route answer.
 * @param {unknown}                         [props.children] Content above the settings (the license panel).
 * @param {(...args: unknown[]) => unknown} [props.filter]   Picks the visible settings to show.
 * @param {(...args: unknown[]) => unknown} [props.onAction] After an action ran.
 */
export function Card( {
	card,
	settings,
	license,
	children,
	filter,
	onAction,
} ) {
	const id = `zs-card-${ card.key }`;
	const headingId = `zs-ch-${ card.key }`;
	const status = cardStatus( card, settings.valueOf, license );
	const line = detectionLine( card, nowLocal() );
	const visible = filter ? card.visible.filter( filter ) : card.visible;
	const fineValues = card.fine.filter( holdsValue ).length;
	const fineError = card.fine.some( ( e ) => settings.errors[ e.key ] );
	const [ fineOpen, setFineOpen ] = useState( false );
	const signals = signalNames();

	// A save error inside Fine-tune opens it, so the message is seen.
	useEffect( () => {
		if ( fineError ) {
			setFineOpen( true );
		}
	}, [ fineError ] );

	if ( card.inactive ) {
		const name = card.plugin?.name || card.title;
		return (
			<section
				className="zs-tile zs-pcard zs-pcard-na"
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
							<p className="zs-hint">
								{ sprintf(
									/* translators: %s: plugin name, such as WPForms */
									__(
										'Install and activate %s to protect its forms. This card turns on with it.',
										'zero-spam'
									),
									name
								) }
							</p>
						</div>
					</div>
					<Badge>{ __( 'Plugin not active', 'zero-spam' ) }</Badge>
				</div>
				{ card.signals.length > 0 && (
					<ul
						className="zs-sig"
						aria-label={ __( 'Supported signals', 'zero-spam' ) }
					>
						{ card.signals.map( ( s ) => (
							<li className="zs-tag" key={ s }>
								{ signals[ s ] }
							</li>
						) ) }
					</ul>
				) }
				{ card.settings.length > 0 && (
					<details className="zs-fine">
						<summary>
							{ __(
								"Settings for when it's active",
								'zero-spam'
							) }
							<span className="zs-c">
								{ card.settings.filter( holdsValue ).length }
							</span>
						</summary>
						<Rows
							list={ card.settings }
							settings={ settings }
							onAction={ onAction }
						/>
					</details>
				) }
				{ ! boot.isMultisite && card.notInstalled && (
					<p className="zs-pcard-foot">
						<a href={ adminUrl( 'plugins.php' ) }>
							{ __( 'Go to Plugins', 'zero-spam' ) }
						</a>
					</p>
				) }
			</section>
		);
	}

	return (
		<section
			className={ cx(
				'zs-tile',
				'zs-pcard',
				card.locked && 'zs-pcard-locked'
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
					{ card.locked && (
						<Badge
							className="zs-badge-lock zs-lockbadge"
							icon="lock"
						>
							<span aria-hidden="true">
								{ __( 'Locked by network', 'zero-spam' ) }
							</span>
							<span className="zs-sr-only">
								{ sprintf(
									/* translators: %s: card name, such as Comments */
									__(
										'%s is locked by the network administrator, so its switch cannot be changed on this site.',
										'zero-spam'
									),
									card.title
								) }
							</span>
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
			{ card.signals.length > 0 && (
				<ul
					className="zs-sig"
					aria-label={ __( 'Supported signals', 'zero-spam' ) }
				>
					{ card.signals.map( ( s ) => (
						<li className="zs-tag" key={ s }>
							{ signals[ s ] }
						</li>
					) ) }
				</ul>
			) }
			{ children }
			{ ( visible.length > 0 || line || card.fine.length > 0 ) && (
				<div className="zs-pcard-b">
					<Rows
						list={ visible }
						settings={ settings }
						onAction={ onAction }
					/>
					{ line && <p className="zs-pcard-st">{ line }</p> }
					{ card.fine.length > 0 && (
						<details
							className="zs-fine"
							open={ fineOpen }
							onToggle={ ( event ) =>
								setFineOpen( event.currentTarget.open )
							}
						>
							<summary>
								{ __( 'Fine-tune', 'zero-spam' ) }
								{ fineValues > 0 && (
									<span className="zs-c">
										<span className="zs-sr-only">
											{ __(
												'Settings:',
												'zero-spam'
											) }{ ' ' }
										</span>
										{ fineValues }
									</span>
								) }
							</summary>
							<Rows
								list={ card.fine }
								settings={ settings }
								onAction={ onAction }
							/>
						</details>
					) }
				</div>
			) }
		</section>
	);
}
