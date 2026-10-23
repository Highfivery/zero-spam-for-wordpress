/**
 * Zero Spam admin app (6.0). Placeholder entry point.
 *
 * Nothing enqueues this file yet, and it registers nothing: on a page that has
 * the app root (<div id="zerospam-app" class="zs-app">) it only labels it.
 * The admin screens arrive in later pull requests.
 */

/**
 * Internal dependencies
 */
import { getAppLabel } from './app-label';
import './styles/index.css';

const root = document.getElementById('zerospam-app');

if (root) {
	root.setAttribute('aria-label', getAppLabel(root.dataset.version || ''));
}
