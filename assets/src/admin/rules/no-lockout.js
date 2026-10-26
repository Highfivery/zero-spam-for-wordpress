/**
 * No-lockout addresses (prototype v18): the administrator addresses Zero
 * Spam never blocks from the site, saved when an administrator activated
 * or updated it (Admin_Safe_IPs). Read-only apart from Remove, which can
 * be undone for two minutes.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { Badge, Button, Icon, Tile } from '../app/primitives';
import { useFeedback } from '../app/primitives/feedback';
import { request, useResource } from '../data/api';
import { num } from '../data/format';
import { errorMessage } from '../activity/undo';
import { removeNoLockout } from './actions';
import { EmptyState } from './parts';
import { NO_LOCKOUT_PATH } from './query';

/**
 * @param {Object}                          props         Props.
 * @param {(...args: unknown[]) => unknown} props.onCount Receives the number of addresses.
 * @param {Object}                          [props.panel] Ref of the tab panel (focus after a removal).
 */
export function NoLockout( { onCount, panel } ) {
	const { toast } = useFeedback();
	const res = useResource( NO_LOCKOUT_PATH );
	const items = Array.isArray( res.data?.items ) ? res.data.items : [];
	const loaded = undefined !== res.data;
	const max = Number( res.data?.max ) || 10;

	useEffect( () => {
		if ( loaded ) {
			onCount( items.length );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- onCount is a setter.
	}, [ loaded, items.length ] );

	const remove = ( ip ) => {
		window.requestAnimationFrame( () => panel?.current?.focus() );
		removeNoLockout( { ip, request, toast, refresh: res.reload } );
	};

	return (
		<Tile className="zs-scard" labelledBy="zs-nl-h">
			<div className="zs-tile-h">
				<div className="zs-scard-t">
					<span className="zs-ico">
						<Icon name="lock" />
					</span>
					<div>
						<h2 id="zs-nl-h" className="zs-tile-title">
							{ __(
								'Administrator addresses that can never be locked out',
								'zero-spam'
							) }
						</h2>
						<p className="zs-hint">
							{ sprintf(
								/* translators: %s: how many addresses are kept */
								__(
									'Saved when an administrator activated or updated Zero Spam. Zero Spam never blocks these addresses from the site, but still checks comments, registrations and forms sent from them. The last %s are kept.',
									'zero-spam'
								),
								num( max )
							) }
						</p>
					</div>
				</div>
			</div>
			<div aria-busy={ res.loading ? 'true' : undefined }>
				{ items.length > 0 && (
					<ul
						className="zs-list zs-safelist"
						aria-label={ __( 'No-lockout addresses', 'zero-spam' ) }
					>
						{ items.map( ( item ) => (
							<li key={ item.ip }>
								<span className="zs-ico zs-ico-ok">
									<Icon name="checkc" />
								</span>
								<div className="zs-li-body">
									<b className="zs-mono">{ item.ip }</b>
									{ item.current && (
										<span>
											<Badge tone="info">
												{ __(
													'Your address now',
													'zero-spam'
												) }
											</Badge>
										</span>
									) }
								</div>
								<Button
									variant="ghost"
									size="sm"
									aria-label={ sprintf(
										/* translators: %s: IP address */
										__(
											'Remove %s from the administrator addresses that can never be locked out',
											'zero-spam'
										),
										item.ip
									) }
									onClick={ () => remove( item.ip ) }
								>
									{ __( 'Remove', 'zero-spam' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) }
				{ loaded && ! items.length && (
					<EmptyState
						title={ __( 'No addresses saved.', 'zero-spam' ) }
						hint={ __(
							'Your address is saved the next time you update Zero Spam.',
							'zero-spam'
						) }
					/>
				) }
				{ res.error && ! loaded && (
					<p className="zs-notice zs-notice-err" role="alert">
						{ errorMessage(
							res.error,
							__(
								'The list could not be loaded. Reload the page to try again.',
								'zero-spam'
							)
						) }
					</p>
				) }
			</div>
		</Tile>
	);
}
