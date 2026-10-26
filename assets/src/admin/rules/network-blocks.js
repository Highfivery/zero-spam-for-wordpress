/**
 * On a network site: the addresses the network administrator blocks on
 * every site (`rules/network-blocks`, read-only), shown on Blocked IPs as
 * "Blocked by your network", and the lookup the block drawer uses to say
 * an address it is about to block already is.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Icon } from '../app/primitives';
import { NS, boot, request, useResource } from '../data/api';
import { num } from '../data/format';
import { LockBadge, siteDay } from './parts';

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
					<LockBadge
						label={ __( 'Blocked by your network', 'zero-spam' ) }
						sentence={ __(
							'Blocked by your network on every site. Only the network administrator can change this block.',
							'zero-spam'
						) }
					/>
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

/**
 * Blocked IPs: how many addresses the network blocks on every site, with
 * the list on request (nothing is read until it is opened).
 */
export function NetworkBlocks() {
	const count = Number( boot.rules?.counts?.network ) || 0;
	const [ open, setOpen ] = useState( false );
	const list = useResource(
		open ? `${ NS }/rules/network-blocks?per_page=50` : null,
		{ parse: false }
	);

	if ( ! boot.isMultisite || ! count ) {
		return null;
	}

	const rows = Array.isArray( list.data ) ? list.data : [];
	const total = Number( list.headers[ 'x-wp-total' ] ?? count ) || count;

	return (
		<details
			className="zs-fine zs-netblocks"
			onToggle={ ( e ) => setOpen( e.currentTarget.open ) }
		>
			<summary>
				<Icon name="network" size="sm" />
				{ sprintf(
					/* translators: %s: number of addresses */
					_n(
						'Your network blocks %s address on every site',
						'Your network blocks %s addresses on every site',
						count,
						'zero-spam'
					),
					num( count )
				) }
			</summary>
			<p className="zs-hint">
				{ __(
					'Only your network administrator can change these blocks. An address on this site’s Allowed IPs is still let in.',
					'zero-spam'
				) }
			</p>
			{ open && list.loading && ! rows.length && (
				<p className="zs-hint">{ __( 'Loading…', 'zero-spam' ) }</p>
			) }
			{ rows.length > 0 && (
				<ul
					className="zs-cards zs-cards-plain"
					aria-label={ __( 'Blocked by your network', 'zero-spam' ) }
				>
					{ rows.map( ( block ) => (
						<li key={ block.id }>
							<div className="zs-cardmain">
								<span className="zs-cm-t zs-mono">
									{ block.ip }
								</span>
								<span className="zs-cm-m">
									{ [
										block.reason,
										block.date_added &&
											sprintf(
												/* translators: %s: date */
												__( 'Since %s', 'zero-spam' ),
												siteDay( block.date_added )
											),
									]
										.filter( Boolean )
										.join( ' · ' ) }
								</span>
							</div>
							<span className="zs-card-side">
								<span className="zs-cm-b">
									<LockBadge
										label={ __(
											'Blocked by your network',
											'zero-spam'
										) }
										sentence={ __(
											'Blocked by your network on every site. Only the network administrator can change this block.',
											'zero-spam'
										) }
									/>
								</span>
							</span>
						</li>
					) ) }
				</ul>
			) }
			{ total > rows.length && rows.length > 0 && (
				<p className="zs-hint">
					{ sprintf(
						/* translators: 1: addresses shown, 2: addresses in all */
						__( 'Showing %1$s of %2$s.', 'zero-spam' ),
						num( rows.length ),
						num( total )
					) }
				</p>
			) }
		</details>
	);
}
