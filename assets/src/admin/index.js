/**
 * Zero Spam admin app (6.0): mounts on the page shell printed by
 * Admin_App::render_page() (<div id="zerospam-app" class="zs-app"
 * data-area="…">). The area comes from the root; views inside an area are
 * ?tab= arguments kept with the History API (data/routes.js).
 */

/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { App } from './app/app';
import './styles/index.css';

const AREAS = [
	'overview',
	'activity',
	'rules',
	'protection',
	'settings',
	'help',
	'network',
];

const root = document.getElementById( 'zerospam-app' );

if ( root ) {
	const area = AREAS.includes( root.dataset.area )
		? root.dataset.area
		: 'overview';
	root.textContent = '';
	createRoot( root ).render( <App root={ root } area={ area } /> );
}
