/**
 * The block drawer on a network site: whether the network already blocks
 * the address on every site (`rules/network-blocks`, read-only), and the
 * note that says so ("Blocked by your network"). Shared by every page with
 * the drawer (app/block-form.js), so it lives in app/.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Badge, Icon } from './primitives';
import { NS, boot, request } from '../data/api';

/**
 * The network block of an address, if the network has one.
 *
 * @param {string} ip Address (canonical).
 * @return {Promise<Object|null>} Block.
 */
export function findNetworkBlock( ip ) {
	const wanted = String( ip || '' )
		.trim()
		.toLowerCase();
	if ( ! boot.isMultisite || ! wanted ) {
		return Promise.resolve( null );
	}
	return request( {
		path: `${ NS }/rules/network-blocks?per_page=5&search=${ encodeURIComponent( wanted ) }`,
	} ).then( ( list ) =>
		Array.isArray( list )
			? list.find(
					( b ) => String( b.ip || '' ).toLowerCase() === wanted
				) || null
			: null
	);
}

/**
 * The note shown in the block drawer for an address the network blocks.
 *
 * @param {Object} props       Props.
 * @param {Object} props.block Network block.
 */
export function NetworkBlockNote( { block } ) {
	return (
		<div className="zs-notice zs-notice-info zs-rnotice" role="status">
			<Icon name="network" />
			<div className="zs-li-body">
				<span>
					<Badge className="zs-badge-lock zs-lockbadge" icon="lock">
						<span aria-hidden="true">
							{ __( 'Blocked by your network', 'zero-spam' ) }
						</span>
						<span className="zs-sr-only">
							{ __(
								'Blocked by your network on every site. Only the network administrator can change this block.',
								'zero-spam'
							) }
						</span>
					</Badge>
				</span>
				<span>
					{ sprintf(
						/* translators: %s: IP address */
						__(
							'%s is already blocked on every site by your network administrator. A block you add here applies to this site only.',
							'zero-spam'
						),
						block.ip
					) }
				</span>
			</div>
		</div>
	);
}
