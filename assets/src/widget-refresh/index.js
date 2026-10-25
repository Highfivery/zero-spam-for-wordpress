/**
 * Zero Spam dashboard widget (6.0): the Refresh button of the site widget.
 *
 * Plain JavaScript with no WordPress script dependencies, so the dashboard
 * loads as little as possible. Dashboard_Widget enqueues it only on the
 * dashboard, only for users who see the Refresh button, and passes its
 * settings and translated strings in window.zerospamWidget.
 */

/**
 * Internal dependencies
 */
import './index.css';
import { initWidget } from './widget';

document.querySelectorAll( '[data-zs-widget]' ).forEach( ( root ) => {
	initWidget( root, window.zerospamWidget );
	root.setAttribute( 'data-zs-ready', '' );
} );
