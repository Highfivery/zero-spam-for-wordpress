/**
 * The network page (prototype §9): the heading, eight tabs (?tab=, kept
 * with the History API and mirrored in the sidebar) — Overview, Settings,
 * Templates, Audit log, Comparison, Import/Export, Notifications and
 * Network stats — and the Overview itself.
 *
 * Super admins only (manage_network_options; Admin_App registers the page
 * in the network admin). Each tab's first data is preloaded
 * (Admin_App::network_preloads()), so a tab opened from its address makes
 * no request of its own.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Button, Icon } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { networkTabs } from '../app/shell/shell';
import { NS, boot, request, useResource } from '../data/api';
import {
	focusIfEnabled,
	useFocusAfterRender,
} from '../app/use-focus-after-render';
import { num } from '../data/format';
import { PATHS } from './model';
import {
	ActionResult,
	Confirm,
	LoadState,
	NumberTile,
	errorText,
} from './parts';
import { NetworkSettings } from './settings-tab';
import { Templates } from './templates';
import { Audit } from './audit';
import { Comparison } from './comparison';
import { ImportExport } from './io';
import { Notifications } from './notifications';
import { NetworkStats } from './stats';

/**
 * The tab list: arrow keys, Home and End move between tabs (roving tabindex).
 *
 * @param {Object}                          props        Props.
 * @param {string}                          props.tab    Current tab.
 * @param {(...args: unknown[]) => unknown} props.setTab Changes the tab.
 */
function Tabs( { tab, setTab } ) {
	const list = useRef( null );
	const focusAfter = useFocusAfterRender();
	const tabs = networkTabs();
	const keys = tabs.map( ( [ k ] ) => k );

	const onKey = ( event ) => {
		const i = keys.indexOf( tab );
		const next = {
			ArrowRight: keys[ ( i + 1 ) % keys.length ],
			ArrowLeft: keys[ ( i - 1 + keys.length ) % keys.length ],
			Home: keys[ 0 ],
			End: keys[ keys.length - 1 ],
		}[ event.key ];
		if ( next ) {
			event.preventDefault();
			setTab( next );
			focusAfter( () =>
				focusIfEnabled(
					list.current?.querySelector( `#zs-nt-${ next }` )
				)
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
				aria-label={ __( 'Network sections', 'zero-spam' ) }
				onKeyDown={ onKey }
			>
				{ tabs.map( ( [ key, label ] ) => (
					<button
						key={ key }
						id={ `zs-nt-${ key }` }
						type="button"
						role="tab"
						aria-selected={ key === tab ? 'true' : 'false' }
						aria-controls="zs-net-body"
						tabIndex={ key === tab ? 0 : -1 }
						onClick={ () => setTab( key ) }
					>
						{ label }
					</button>
				) ) }
			</div>
		</div>
	);
}

/**
 * The Overview tab: the numbers, site changes and the quick actions.
 *
 * @param {Object}                          props        Props.
 * @param {(...args: unknown[]) => unknown} props.setTab Opens another tab.
 */
function Overview( { setTab } ) {
	const { announce } = useFeedback();
	const overview = useResource( PATHS.overview );
	const [ asking, setAsking ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	const applyBtn = useRef( null );
	const focusAfter = useFocusAfterRender();
	const data = overview.data;
	// The Apply button is shown again once the question is gone: focus it after that render.
	const focusApply = () =>
		focusAfter(
			() => focusIfEnabled( applyBtn.current ),
			() => document.getElementById( 'zs-net-body' )?.focus()
		);

	if ( ! data ) {
		return (
			<LoadState
				res={ overview }
				what={ __(
					'The network overview could not be loaded.',
					'zero-spam'
				) }
			/>
		);
	}

	const apply = () => {
		setBusy( true );
		request( {
			path: `${ NS }/network-settings/apply-all`,
			method: 'POST',
			data: { mode: 'follow' },
		} )
			.then( ( answer ) => {
				const updated = Number( answer?.updated_count ) || 0;
				const text = updated
					? sprintf(
							/* translators: %s: number of sites */
							_n(
								'%s site now follows the network settings again.',
								'%s sites now follow the network settings again.',
								updated,
								'zero-spam'
							),
							num( updated )
						)
					: __(
							'Every site already follows the network settings.',
							'zero-spam'
						);
				setResult( { ok: true, text } );
				overview.reload();
			} )
			.catch( ( error ) =>
				setResult( {
					ok: false,
					text: errorText(
						error,
						__(
							'The network settings could not be applied. Please try again.',
							'zero-spam'
						)
					),
				} )
			)
			.finally( () => {
				setBusy( false );
				setAsking( false );
				focusApply();
			} );
	};

	const overrides = Number( data.overrides ) || 0;

	return (
		<div className="zs-wall">
			<NumberTile
				label={ __( 'Total sites', 'zero-spam' ) }
				value={ data.sites }
			/>
			<NumberTile
				label={ __( 'Network settings', 'zero-spam' ) }
				value={ data.network_settings }
				hint={ __( 'Set at network level', 'zero-spam' ) }
			/>
			<NumberTile
				label={ __( 'Locked settings', 'zero-spam' ) }
				value={ data.locked }
			/>
			<NumberTile
				label={ __( 'Site changes', 'zero-spam' ) }
				value={ overrides }
				hint={ __(
					'Settings sites changed for themselves',
					'zero-spam'
				) }
			/>
			<section className="zs-tile zs-s12" aria-labelledby="zs-nqa-h">
				{ overrides > 0 ? (
					<div className="zs-notice zs-notice-info zs-netact">
						<Icon name="info" />
						<span>
							{ sprintf(
								/* translators: %s: number of setting changes */
								_n(
									'There is %s setting change across your network.',
									'There are %s setting changes across your network.',
									overrides,
									'zero-spam'
								),
								num( overrides )
							) }{ ' ' }
							<button
								type="button"
								className="zs-linkbtn"
								onClick={ () => setTab( 'comparison' ) }
							>
								{ __(
									'Review them in Comparison',
									'zero-spam'
								) }
							</button>
						</span>
					</div>
				) : (
					<p className="zs-hint">
						{ __(
							'Every site uses the network settings: no site changed a network default.',
							'zero-spam'
						) }
					</p>
				) }
				{ data.capped && (
					<p className="zs-hint">
						{ sprintf(
							/* translators: %s: number of sites */
							__(
								'Site changes are counted on the first %s sites.',
								'zero-spam'
							),
							num( data.checked )
						) }
					</p>
				) }
				<h2 className="zs-tile-title" id="zs-nqa-h">
					{ __( 'Quick actions', 'zero-spam' ) }
				</h2>
				{ asking ? (
					<Confirm
						id="zs-napply-q"
						question={ sprintf(
							/* translators: %s: number of sites */
							__(
								'Sites that changed a network setting go back to the network value, on all %s sites. Locked settings already apply everywhere.',
								'zero-spam'
							),
							num( data.sites )
						) }
						yes={ __( 'Apply to all sites', 'zero-spam' ) }
						busy={ busy }
						onYes={ apply }
						onCancel={ () => {
							setAsking( false );
							focusApply();
						} }
					/>
				) : (
					<div className="zs-inline-row">
						<Button
							ref={ applyBtn }
							onClick={ () => {
								setResult( null );
								setAsking( true );
								announce(
									__(
										'Confirm to apply the network settings to every site.',
										'zero-spam'
									)
								);
							} }
						>
							{ __( 'Apply to all sites', 'zero-spam' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => setTab( 'comparison' ) }
						>
							{ __( 'Compare sites', 'zero-spam' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => setTab( 'io' ) }
						>
							{ __( 'Export configuration', 'zero-spam' ) }
						</Button>
					</div>
				) }
				<ActionResult result={ result } />
			</section>
		</div>
	);
}

/**
 * The network page.
 *
 * @param {Object}                          props        Props.
 * @param {string}                          props.tab    Current tab.
 * @param {(...args: unknown[]) => unknown} props.setTab Changes the tab.
 */
export function Network( { tab, setTab } ) {
	const sites = Number( boot.network?.sites ) || 0;
	const focusAfter = useFocusAfterRender();

	// After the other tab is on screen, its panel takes focus.
	const open = ( next ) => {
		setTab( next );
		focusAfter( () =>
			focusIfEnabled( document.getElementById( 'zs-net-body' ) )
		);
	};

	let body = null;
	switch ( tab ) {
		case 'settings':
			body = <NetworkSettings />;
			break;
		case 'templates':
			body = <Templates />;
			break;
		case 'audit':
			body = <Audit />;
			break;
		case 'comparison':
			body = <Comparison setTab={ open } />;
			break;
		case 'io':
			body = <ImportExport />;
			break;
		case 'notifications':
			body = <Notifications />;
			break;
		case 'stats':
			body = <NetworkStats />;
			break;
		default:
			body = <Overview setTab={ open } />;
	}

	return (
		<div className="zs-view zs-network" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<p className="zs-eyebrow">
						{ sprintf(
							/* translators: %s: number of sites */
							_n(
								'Network admin · %s site',
								'Network admin · %s sites',
								sites,
								'zero-spam'
							),
							num( sites )
						) }
					</p>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ __( 'Network.', 'zero-spam' ) }
						<span>
							{ sprintf(
								/* translators: %s: number of sites */
								_n(
									'One wall, %s site.',
									'One wall, %s sites.',
									sites,
									'zero-spam'
								),
								num( sites )
							) }
						</span>
					</h1>
					<p>
						{ __(
							'Set defaults for every site, and lock the ones site administrators shouldn’t change.',
							'zero-spam'
						) }
					</p>
				</div>
			</div>
			<Tabs tab={ tab } setTab={ setTab } />
			<div
				id="zs-net-body"
				role="tabpanel"
				aria-labelledby={ `zs-nt-${ tab }` }
				className="zs-areabody"
				tabIndex={ -1 }
			>
				{ body }
			</div>
		</div>
	);
}
