/**
 * On a network site: the addresses the network administrator blocks on
 * every site (`rules/network-blocks`, read-only), shown on Blocked IPs as
 * "Blocked by your network". The block drawer's lookup and note are in
 * app/network-block-note.js (shared by every page with the drawer).
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
import { NS, boot, useResource } from '../data/api';
import { num } from '../data/format';
import { LockBadge, siteDay } from './parts';

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
