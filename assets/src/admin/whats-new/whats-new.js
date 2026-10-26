/**
 * "What's new in 6.0" on the Overview of an upgraded site: a tile until
 * the administrator dismisses it (their whats_new_seen preference), and a
 * drawer with the new menu, where each 5.x screen moved and the new
 * switches. Nothing outside Zero Spam's screens.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from '../app/context';
import { Button, Icon, Tile } from '../app/primitives';
import { Drawer } from '../app/primitives/drawer';
import { useFeedback } from '../app/primitives/feedback';
import { boot } from '../data/api';
import { areaUrl } from '../data/routes';
import {
	dismissChanges,
	menuAreas,
	movedRows,
	newSwitches,
	shouldShowWhatsNew,
} from './model';

/**
 * The drawer.
 *
 * @param {Object}                          props           Props.
 * @param {boolean}                         props.open      Shown.
 * @param {(...args: unknown[]) => unknown} props.onClose   Close.
 * @param {(...args: unknown[]) => unknown} props.onDismiss Got it.
 */
function WhatsNewDrawer( { open, onClose, onDismiss } ) {
	const network = boot.isMultisite && Boolean( boot.urls.network );
	return (
		<Drawer
			open={ open }
			onClose={ onClose }
			labelledBy="zs-wnd-h"
			wide
			className="zs-whatsnew-drawer"
			header={
				<>
					<p className="zs-eyebrow">
						{ __( 'Zero Spam 6.0', 'zero-spam' ) }
					</p>
					<h2 id="zs-wnd-h" className="zs-tile-title">
						{ __( "What's new in 6.0", 'zero-spam' ) }
					</h2>
				</>
			}
			footer={
				<div className="zs-inline-row">
					<Button onClick={ onDismiss } data-autofocus>
						{ __( 'Got it', 'zero-spam' ) }
					</Button>
					<Button variant="secondary" onClick={ onClose }>
						{ __( 'Close', 'zero-spam' ) }
					</Button>
				</div>
			}
		>
			<section className="zs-wn-sec" aria-labelledby="zs-wn-menu">
				<h3 id="zs-wn-menu" className="zs-h3">
					{ __( 'Zero Spam has its own menu', 'zero-spam' ) }
				</h3>
				<p className="zs-hint">
					{ __(
						'It sits below Comments. Everything you had is still here, grouped into six areas.',
						'zero-spam'
					) }
				</p>
				<ul className="zs-wn-menu">
					{ menuAreas().map( ( [ area, icon, name, text ] ) => (
						<li key={ area }>
							<Icon name={ icon } />
							<span>
								<a href={ areaUrl( area ) }>{ name }</a>
								<span className="zs-hint">{ text }</span>
							</span>
						</li>
					) ) }
				</ul>
			</section>
			<section className="zs-wn-sec" aria-labelledby="zs-wn-moved">
				<h3 id="zs-wn-moved" className="zs-h3">
					{ __( 'Where things moved', 'zero-spam' ) }
				</h3>
				<div className="zs-tbl-wrap">
					<table className="zs-t zs-wn-map">
						<caption className="zs-sr-only">
							{ __(
								'Where each 5.x screen is in 6.0',
								'zero-spam'
							) }
						</caption>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Before (5.x)', 'zero-spam' ) }
								</th>
								<th scope="col">
									{ __( 'Now (6.0)', 'zero-spam' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ movedRows( network ).map( ( row ) => (
								<tr key={ row.from }>
									<td>{ row.from }</td>
									<td>
										<a
											href={ areaUrl(
												row.area,
												row.args
											) }
										>
											{ row.to }
										</a>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			</section>
			<section className="zs-wn-sec" aria-labelledby="zs-wn-new">
				<h3 id="zs-wn-new" className="zs-h3">
					{ __( 'New switches', 'zero-spam' ) }
				</h3>
				<ul className="zs-wn-new">
					{ newSwitches().map( ( item ) => (
						<li key={ item.title }>
							<Icon name="check" size="sm" />
							<span>
								<b>{ item.title }</b>
								<span className="zs-hint">{ item.text }</span>
							</span>
							<a href={ areaUrl( item.area, item.args ) }>
								{ __( 'Open', 'zero-spam' ) }
								<span className="zs-sr-only">
									{ item.title }
								</span>
							</a>
						</li>
					) ) }
				</ul>
			</section>
		</Drawer>
	);
}

/**
 * The tile (renders nothing when this administrator has seen it).
 */
export function WhatsNewTile() {
	const { prefs, prefsLoaded, savePrefs } = useApp();
	const { toast } = useFeedback();
	const [ open, setOpen ] = useState( false );
	const release = boot.whatsNew;
	const show = shouldShowWhatsNew( {
		release,
		seen: prefs.whats_new_seen || '',
		prefsLoaded,
		onboarding: boot.onboarding,
	} );

	if ( ! show && ! open ) {
		return null;
	}

	const dismiss = () => {
		const before = prefs.whats_new_seen || '';
		setOpen( false );
		savePrefs( dismissChanges( release ) ).then( () =>
			toast(
				__( "What's new is hidden. It won't show again.", 'zero-spam' ),
				{ undo: () => savePrefs( { whats_new_seen: before } ) }
			)
		);
		// The tile goes away: focus moves to the page heading.
		window.setTimeout(
			() => document.getElementById( 'zs-ov-h' )?.focus(),
			0
		);
	};

	return (
		<>
			{ show && (
				<Tile span={ 12 } labelledBy="zs-wn-h" className="zs-whatsnew">
					<span className="zs-ico zs-ico-info" aria-hidden="true">
						<Icon name="flag" />
					</span>
					<div className="zs-grow">
						<div className="zs-eyebrow">
							{ __( 'New in 6.0', 'zero-spam' ) }
						</div>
						<h2 id="zs-wn-h" className="zs-tile-title">
							{ __( 'Zero Spam has a new home', 'zero-spam' ) }
						</h2>
						<p className="zs-hint">
							{ __(
								'Its own menu below Comments, with six areas. See where each screen moved and what is new.',
								'zero-spam'
							) }
						</p>
					</div>
					<div className="zs-inline-row">
						<Button
							variant="secondary"
							onClick={ () => setOpen( true ) }
						>
							{ __( "See what's new", 'zero-spam' ) }
						</Button>
						<Button variant="ghost" onClick={ dismiss }>
							{ __( 'Dismiss', 'zero-spam' ) }
						</Button>
					</div>
				</Tile>
			) }
			<WhatsNewDrawer
				open={ open }
				onClose={ () => setOpen( false ) }
				onDismiss={ dismiss }
			/>
		</>
	);
}
