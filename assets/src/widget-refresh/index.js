/**
 * Zero Spam dashboard widget refresh (6.0). Placeholder entry point.
 *
 * Plain JavaScript with no WordPress script dependencies, so the dashboard
 * loads as little as possible. Nothing enqueues this file yet; the widget is
 * rebuilt in a later pull request. On a widget root
 * (<div class="zs-widget" data-zs-widget>) it only marks the root as ready.
 */
import './index.css';

document.querySelectorAll( '[data-zs-widget]' ).forEach( ( root ) => {
	root.setAttribute( 'data-zs-ready', '' );
} );
