/**
 * The guides on Help (ui/help/docs): one disclosure per guide, its heading
 * a real h3 (WAI-ARIA accordion pattern, so heading navigation finds every
 * guide), the guide's parts as native <details> inside.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ExtLink, Icon, Tile, TileHead } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { PATHS, useResource } from '../data/api';
import { copyText } from './copy';
import { failedAnswer } from './news-model';
import { LINKS } from './lists';

/**
 * A guide's body.
 *
 * SECURITY: this is the only place the admin app inserts HTML it did not
 * build itself (the mark in app/mark.js inserts the plugin's own static
 * sprite). The HTML comes from `zero-spam/v1/ui/help/docs`, which only
 * administrators can read and which passes every guide through
 * wp_kses_post() on the server as its very last step
 * (Documentation::prepare(); tests/phpunit/integration/rest/test-rest-help-docs.php
 * proves scripts, event handlers and javascript: links are removed, and
 * assets/src/admin/help/test/inner-html.test.js keeps this the only use).
 * Never pass anything else to it.
 *
 * Copy buttons inside a guide copy the code block they name.
 *
 * @param {Object} props      Props.
 * @param {string} props.html Server-sanitised HTML.
 */
function GuideHtml( { html } ) {
	const ref = useRef( null );
	const { toast } = useFeedback();

	useEffect( () => {
		const root = ref.current;
		if ( ! root ) {
			return undefined;
		}
		const onClick = ( event ) => {
			const button = event.target.closest( '.zs-doc-copy' );
			if ( ! button || ! root.contains( button ) ) {
				return;
			}
			const target = root.ownerDocument.getElementById(
				button.getAttribute( 'data-clipboard-target' ) || ''
			);
			if ( target ) {
				copyText( target.textContent || '', toast );
			}
		};
		root.addEventListener( 'click', onClick );
		return () => root.removeEventListener( 'click', onClick );
	}, [ toast ] );

	return (
		<div
			ref={ ref }
			className="zs-doc"
			// Sanitised on the server with wp_kses_post(); see the comment above.
			dangerouslySetInnerHTML={ { __html: html } }
		/>
	);
}

/**
 * @param {Object}                          props          Props.
 * @param {Object}                          props.doc      Guide (id, title, topics, html).
 * @param {boolean}                         props.open     Shown.
 * @param {(...args: unknown[]) => unknown} props.onToggle Toggles it.
 */
function Guide( { doc, open, onToggle } ) {
	const panel = `zs-doc-p-${ doc.id }`;
	return (
		<div className="zs-help-doc" id={ `zs-doc-${ doc.id }` }>
			<h3 className="zs-help-doc-h">
				<button
					type="button"
					aria-expanded={ open ? 'true' : 'false' }
					aria-controls={ panel }
					onClick={ onToggle }
				>
					<span className="zs-grow">{ doc.title }</span>
					<Icon name="chev" size="sm" className="zs-help-doc-i" />
				</button>
			</h3>
			<div className="zs-help-b" id={ panel } hidden={ ! open }>
				{ doc.topics?.length > 0 && (
					<p className="zs-hint">{ doc.topics.join( ' · ' ) }</p>
				) }
				{ open && <GuideHtml html={ doc.html } /> }
			</div>
		</div>
	);
}

/**
 * The Documentation tile.
 *
 * @param {Object} props        Props.
 * @param {string} [props.open] Guide to open first (?section=).
 */
export function DocsTile( { open: initial } ) {
	const docs = useResource( PATHS.helpDocs );
	const list = Array.isArray( docs.data ) ? docs.data : [];
	const [ open, setOpen ] = useState( () =>
		initial ? [ initial ] : [ 'lockout-rescue' ]
	);
	const toggle = ( id ) =>
		setOpen( ( current ) =>
			current.includes( id )
				? current.filter( ( x ) => x !== id )
				: [ ...current, id ]
		);

	return (
		<Tile span={ 7 } labelledBy="zs-hd-h" className="zs-docs">
			<TileHead
				eyebrow={ __( 'Guides', 'zero-spam' ) }
				id="zs-hd-h"
				title={ __( 'Documentation', 'zero-spam' ) }
			/>
			{ docs.loading && ! list.length && (
				<p className="zs-hint" role="status">
					{ __( 'Loading the guides…', 'zero-spam' ) }
				</p>
			) }
			{ ! docs.loading && ! list.length && (
				<div className="zs-empty">
					<b>
						{ failedAnswer( docs )
							? __(
									'The guides could not be loaded',
									'zero-spam'
								)
							: __( 'No guides yet', 'zero-spam' ) }
					</b>
					<span className="zs-hint">
						{ __(
							'Reload the page, or read them on the GitHub wiki.',
							'zero-spam'
						) }
					</span>
				</div>
			) }
			{ list.length > 0 && (
				<div className="zs-help-docs">
					{ list.map( ( doc ) => (
						<Guide
							key={ doc.id }
							doc={ doc }
							open={ open.includes( doc.id ) }
							onToggle={ () => toggle( doc.id ) }
						/>
					) ) }
				</div>
			) }
			<div className="zs-tile-foot">
				<ExtLink
					href={ LINKS.wiki }
					newTab={ __( '(opens in a new tab)', 'zero-spam' ) }
				>
					{ __( 'More guides on the GitHub wiki', 'zero-spam' ) }
				</ExtLink>
			</div>
		</Tile>
	);
}
