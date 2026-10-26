/**
 * Mounts an area's entry point on the page shell printed by
 * Admin_App::render_page() (<div id="zerospam-app" class="zs-app"
 * data-area="…">). Views inside an area are ?tab= arguments kept with the
 * History API (data/routes.js).
 *
 * Every entry imports this module, so the app, the data layer and the
 * styles (index.css) are bundled into each one: WordPress loads translations
 * per script handle, so the build never splits shared chunks.
 */

/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { App } from './app';
import '../styles/index.css';

/**
 * Renders the page's view inside the app's providers.
 *
 * @param {function(): Element} render Returns what the page shows (usually
 *                                     an AreaView); called once the root is
 *                                     found.
 */
export function mount( render ) {
	const root = document.getElementById( 'zerospam-app' );

	if ( ! root ) {
		return;
	}

	root.textContent = '';
	createRoot( root ).render( <App root={ root }>{ render() }</App> );
}
