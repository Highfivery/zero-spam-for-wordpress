/**
 * Zero Spam network admin app (6.0). Placeholder entry point.
 *
 * Nothing enqueues this file yet, and it registers nothing: on a page that has
 * the network app root (<div id="zerospam-network-app" class="zs-app">) it
 * only labels it. The network screens arrive in later pull requests.
 */

/**
 * Internal dependencies
 */
import { getAppLabel } from '../admin/app-label';
import '../admin/styles/index.css';

const root = document.getElementById( 'zerospam-network-app' );

if ( root ) {
	root.setAttribute(
		'aria-label',
		getAppLabel( root.dataset.version || '' )
	);
}
