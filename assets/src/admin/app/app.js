/**
 * The app: providers, the shell and the area of this page (data-area on
 * the root, set by Admin_App::render_page()).
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Component, createInterpolateElement } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { AppProvider } from './context';
import { FeedbackProvider } from './primitives/feedback';
import { CompareDrawer } from './compare';
import { Placeholder } from './placeholder';
import { Shell, areaTitles, networkTabs } from './shell/shell';
import { Overview } from '../overview/overview';
import { Activity } from '../activity/activity';
import { Help } from '../help/help';
import { Onboarding } from '../onboarding/onboarding';
import { useTab } from '../data/routes';
import { boot } from '../data/api';

const NETWORK_TABS = networkTabs().map( ( [ key ] ) => key );

/**
 * @param {Object} props      Props.
 * @param {string} props.area Area.
 */
function AreaView( { area } ) {
	const titles = areaTitles();
	const [ netTab, setNetTab ] = useTab( 'overview', NETWORK_TABS );
	const network = 'network' === area;
	const tabName = network
		? ( networkTabs().find( ( [ k ] ) => k === netTab ) || [] )[ 1 ]
		: '';

	return (
		<Shell
			area={ area }
			netTab={ network ? netTab : undefined }
			setNetTab={ network ? setNetTab : undefined }
			crumb={
				network
					? `${ __( 'Network', 'zero-spam' ) } / ${ tabName }`
					: undefined
			}
		>
			{ 'overview' === area && <Overview /> }
			{ 'activity' === area && <Activity /> }
			{ 'help' === area && <Help /> }
			{ ! [ 'overview', 'activity', 'help' ].includes( area ) && (
				<Placeholder
					area={ area }
					title={
						network ? __( 'Network', 'zero-spam' ) : titles[ area ]
					}
					tab={ netTab }
				/>
			) }
		</Shell>
	);
}

/**
 * Keeps a rendering error from leaving an empty page: the site is still
 * protected, and the classic screens still work.
 */
class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	render() {
		if ( ! this.state.failed ) {
			return this.props.children;
		}
		return (
			<div className="zs-noscript" role="alert">
				<h1 className="zs-tile-title">
					{ __( 'This screen could not be shown', 'zero-spam' ) }
				</h1>
				<p>
					{ createInterpolateElement(
						__(
							'Your site is still protected. Reload the page, or use the classic settings screen. <a>Open the classic settings</a>',
							'zero-spam'
						),
						{
							// eslint-disable-next-line jsx-a11y/anchor-has-content -- createInterpolateElement puts the translated link text inside.
							a: <a href={ this.props.legacy } />,
						}
					) }
				</p>
			</div>
		);
	}
}

/**
 * @param {Object}  props      Props.
 * @param {Element} props.root The root element.
 * @param {string}  props.area Area.
 */
export function App( { root, area } ) {
	return (
		<ErrorBoundary legacy={ boot.urls.legacy }>
			<FeedbackProvider>
				<AppProvider root={ root }>
					{ /* First-run setup replaces the Overview of a fresh install (Admin_App::view_for()). */ }
					{ boot.onboarding ? (
						<Onboarding />
					) : (
						<AreaView area={ area } />
					) }
					<div id="zs-layer" className="zs-layer">
						<CompareDrawer />
					</div>
				</AppProvider>
			</FeedbackProvider>
		</ErrorBoundary>
	);
}
