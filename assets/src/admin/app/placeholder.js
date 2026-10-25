/**
 * Areas built in later steps of 6.0: a page head and an empty state in the
 * prototype's language, with a link to the classic screen meanwhile. The
 * Settings area already carries the user's display preferences (theme and
 * animations), saved with the unsaved-changes bar.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useApp } from './context';
import { Badge, Button, Switch, Tile } from './primitives';
import { UnsavedBar, useFeedback } from './primitives/feedback';
import { boot } from '../data/api';
import { themePref } from '../data/theme';
import { networkTabs } from './shell/shell';

const LEGACY = {
	activity: 'index.php?page=wordpress-zero-spam-dashboard&subview=log',
	rules: 'index.php?page=wordpress-zero-spam-dashboard&subview=blocked-ips',
	protection:
		'options-general.php?page=wordpress-zero-spam-settings&subview=comments',
	settings:
		'options-general.php?page=wordpress-zero-spam-settings&subview=settings',
	help: 'options-general.php?page=wordpress-zero-spam-settings&subview=documentation',
	network: 'network/settings.php?page=zerospam-network-settings',
};

/**
 * Builds a wp-admin address from the Overview's.
 *
 * @param {string} path Path inside wp-admin.
 * @return {string} URL.
 */
const adminUrl = ( path ) => {
	const overview = boot.urls.overview || '';
	const base = overview.slice( 0, overview.indexOf( 'admin.php' ) );
	return base + path;
};

/**
 * The user's display preferences: theme and reduced animations.
 */
function DisplayPrefs() {
	const { prefs, savePrefs, osReduce } = useApp();
	const { toast } = useFeedback();
	const saved = {
		theme: themePref( prefs.theme ),
		reduce_motion: true === prefs.reduce_motion,
	};
	const [ draft, setDraft ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const value = draft || saved;
	const dirty =
		Boolean( draft ) &&
		( draft.theme !== saved.theme ||
			draft.reduce_motion !== saved.reduce_motion );
	const set = ( changes ) => setDraft( { ...value, ...changes } );

	const options = [
		[
			'light',
			__( 'Light', 'zero-spam' ),
			__( 'Light surfaces everywhere.', 'zero-spam' ),
		],
		[
			'dark',
			__( 'Dark', 'zero-spam' ),
			__( 'Dark surfaces, easier at night.', 'zero-spam' ),
		],
		[
			'auto',
			__( 'Match my device', 'zero-spam' ),
			__( 'Follows your operating system.', 'zero-spam' ),
		],
	];

	let motionHint = __(
		'Off. Bricks, the live feed and chart sweeps show protection at work.',
		'zero-spam'
	);
	if ( osReduce ) {
		motionHint = __(
			'On, because your device asks for reduced motion.',
			'zero-spam'
		);
	} else if ( value.reduce_motion ) {
		motionHint = __( 'On for you on every device.', 'zero-spam' );
	}

	return (
		<Tile span={ 12 } labelledBy="zs-disp-h" className="zs-display">
			<div className="zs-tile-h">
				<div>
					<div className="zs-eyebrow">
						{ __( 'Display', 'zero-spam' ) }
					</div>
					<h2 id="zs-disp-h" className="zs-tile-title">
						{ __( 'How Zero Spam looks for you', 'zero-spam' ) }
					</h2>
				</div>
				<Badge>{ __( 'Just for you', 'zero-spam' ) }</Badge>
			</div>
			<div className="zs-setrow">
				<div className="zs-desc">
					<b id="zs-theme-l" className="zs-st-t">
						{ __( 'Theme', 'zero-spam' ) }
					</b>
					<p>
						{ __(
							'Choose light or dark, or follow your device. The top bar button switches it too.',
							'zero-spam'
						) }
					</p>
					<span className="zs-rec">
						{ __( 'Recommended: Match my device', 'zero-spam' ) }
					</span>
				</div>
				<div className="zs-ctl">
					<div
						className="zs-radios"
						role="radiogroup"
						aria-labelledby="zs-theme-l"
					>
						{ options.map( ( [ key, label, hint ] ) => (
							<label
								className="zs-rcard"
								key={ key }
								htmlFor={ `zs-theme-${ key }` }
							>
								<input
									id={ `zs-theme-${ key }` }
									type="radio"
									name="zs-theme"
									value={ key }
									checked={ value.theme === key }
									onChange={ () => set( { theme: key } ) }
								/>
								<span>
									{ label }
									<small>{ hint }</small>
								</span>
							</label>
						) ) }
					</div>
				</div>
			</div>
			<div className="zs-setrow">
				<div className="zs-desc">
					<b id="zs-reduce-l" className="zs-st-t">
						{ __( 'Reduce animations', 'zero-spam' ) }
					</b>
					<p>
						{ __(
							'Stops the live feed from playing on its own, the brick animations and the chart sweeps.',
							'zero-spam'
						) }
					</p>
				</div>
				<div className="zs-ctl">
					<Switch
						checked={ osReduce || value.reduce_motion }
						disabled={ osReduce }
						labelledBy="zs-reduce-l"
						describedBy="zs-reduce-s"
						onChange={ ( on ) => set( { reduce_motion: on } ) }
					/>
					<span className="zs-hint zs-sw-status" id="zs-reduce-s">
						{ motionHint }
					</span>
				</div>
			</div>
			{ dirty && (
				<UnsavedBar
					saving={ saving }
					onDiscard={ () => {
						setDraft( null );
						toast( __( 'Changes discarded', 'zero-spam' ) );
					} }
					onSave={ () => {
						setSaving( true );
						const before = saved;
						savePrefs( draft )
							.then( () => {
								setDraft( null );
								toast(
									__(
										'Display preferences saved',
										'zero-spam'
									),
									{
										undo: () => savePrefs( before ),
									}
								);
							} )
							.catch( () => {} )
							.finally( () => setSaving( false ) );
					} }
				/>
			) }
		</Tile>
	);
}

/**
 * @param {Object} props       Props.
 * @param {string} props.area  Area.
 * @param {string} props.title Area name.
 * @param {string} [props.tab] Network tab.
 */
export function Placeholder( { area, title, tab } ) {
	const net = 'network' === area;
	const tabTitle = net
		? ( networkTabs().find( ( [ k ] ) => k === tab ) || [] )[ 1 ]
		: '';

	return (
		<div className="zs-view" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ net ? tabTitle || title : `${ title }.` }
						<span>
							{ __(
								'Coming in the next 6.0 updates.',
								'zero-spam'
							) }
						</span>
					</h1>
					<p>
						{ net
							? __(
									'The new network screens arrive in a later step of the 6.0 build. Until then, the classic network settings keep working.',
									'zero-spam'
								)
							: sprintf(
									/* translators: %s: area name, such as Activity */
									__(
										'The new %s screen arrives in a later step of the 6.0 build. Until then, the classic screen keeps working.',
										'zero-spam'
									),
									title
								) }
					</p>
				</div>
			</div>
			<div className="zs-wall">
				<Tile span={ 12 } labelledBy="zs-soon-h" className="zs-soon">
					<div className="zs-ghost-wall" aria-hidden="true">
						<span />
						<span />
						<span />
						<span />
						<span />
					</div>
					<div className="zs-soon-copy">
						<h2 id="zs-soon-h" className="zs-tile-title">
							{ __(
								'This part of the wall is still being laid',
								'zero-spam'
							) }
						</h2>
						<p className="zs-hint">
							{ __(
								'Everything you need is on the classic screen for now. Your settings and data are the same in both.',
								'zero-spam'
							) }
						</p>
						{ LEGACY[ area ] && (
							<div>
								<Button
									variant="secondary"
									href={ adminUrl( LEGACY[ area ] ) }
								>
									{ __(
										'Open the classic screen',
										'zero-spam'
									) }
								</Button>
							</div>
						) }
					</div>
				</Tile>
				{ 'settings' === area && <DisplayPrefs /> }
			</div>
		</div>
	);
}
