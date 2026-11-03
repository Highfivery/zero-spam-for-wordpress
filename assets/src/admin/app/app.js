/**
 * The app: providers, the shell and the area of this page. Each area has its
 * own entry point (`{area}/index.js`, built to assets/dist/admin-{area}.js),
 * which mounts its view inside these with mount() (./mount.js), so a page
 * loads only its own area's code.
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
import { Shell, networkTabs } from './shell/shell';
import { useTab } from '../data/routes';

const NETWORK_TABS = networkTabs().map( ( [ key ] ) => key );

/**
 * The shell around an area's view. On the network page, children is a
 * function given the current network tab and its setter ( { tab, setTab } ).
 *
 * @param {Object}                                   props          Props.
 * @param {string}                                   props.area     Area.
 * @param {Element|function({tab: string}): Element} props.children The view.
 */
export function AreaView( { area, children } ) {
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
			{ 'function' === typeof children
				? children( { tab: netTab, setTab: setNetTab } )
				: children }
		</Shell>
	);
}

/** Zero Spam's support forum on WordPress.org. */
const SUPPORT_URL = 'https://wordpress.org/support/plugin/zero-spam/';

/**
 * Keeps a rendering error from leaving an empty page: the site is still
 * protected; reloading usually helps, and the support forum is a link away.
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
							'Your site is still protected. Reload the page to try again. If it keeps happening, <a>ask for help in the support forum</a>.',
							'zero-spam'
						),
						{
							// eslint-disable-next-line jsx-a11y/anchor-has-content -- createInterpolateElement puts the translated link text inside.
							a: <a href={ SUPPORT_URL } />,
						}
					) }
				</p>
			</div>
		);
	}
}

/**
 * @param {Object}  props          Props.
 * @param {Element} props.root     The root element.
 * @param {Element} props.children What the page shows: usually an AreaView.
 */
export function App( { root, children } ) {
	return (
		<ErrorBoundary>
			<FeedbackProvider>
				<AppProvider root={ root }>
					{ children }
					<div id="zs-layer" className="zs-layer">
						<CompareDrawer />
					</div>
				</AppProvider>
			</FeedbackProvider>
		</ErrorBoundary>
	);
}
