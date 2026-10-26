/**
 * Areas built in later steps of 6.0: a page head and an empty state in the
 * prototype's language, with a link to the classic screen meanwhile.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Button, Tile } from './primitives';
import { boot } from '../data/api';
import { networkTabs } from './shell/shell';

const LEGACY = {
	activity: 'index.php?page=wordpress-zero-spam-dashboard&subview=log',
	rules: 'index.php?page=wordpress-zero-spam-dashboard&subview=blocked-ips',
	protection:
		'options-general.php?page=wordpress-zero-spam-settings&subview=comments',
	settings:
		'options-general.php?page=wordpress-zero-spam-settings&subview=settings',
	help: 'options-general.php?page=wordpress-zero-spam-settings&subview=documentation',
	network: 'network/settings.php?page=zerospam-network-settings',
};

/**
 * Builds a wp-admin address from the Overview's.
 *
 * @param {string} path Path inside wp-admin.
 * @return {string} URL.
 */
const adminUrl = ( path ) => {
	const overview = boot.urls.overview || '';
	const base = overview.slice( 0, overview.indexOf( 'admin.php' ) );
	return base + path;
};

/**
 * @param {Object} props       Props.
 * @param {string} props.area  Area.
 * @param {string} props.title Area name.
 * @param {string} [props.tab] Network tab.
 */
export function Placeholder( { area, title, tab } ) {
	const net = 'network' === area;
	const tabTitle = net
		? ( networkTabs().find( ( [ k ] ) => k === tab ) || [] )[ 1 ]
		: '';

	return (
		<div className="zs-view" aria-labelledby="zs-area-h">
			<div className="zs-head">
				<div>
					<h1 className="zs-h1" id="zs-area-h" tabIndex={ -1 }>
						{ net ? tabTitle || title : `${ title }.` }
						<span>
							{ __(
								'Coming in the next 6.0 updates.',
								'zero-spam'
							) }
						</span>
					</h1>
					<p>
						{ net
							? __(
									'The new network screens arrive in a later step of the 6.0 build. Until then, the classic network settings keep working.',
									'zero-spam'
								)
							: sprintf(
									/* translators: %s: area name, such as Activity */
									__(
										'The new %s screen arrives in a later step of the 6.0 build. Until then, the classic screen keeps working.',
										'zero-spam'
									),
									title
								) }
					</p>
				</div>
			</div>
			<div className="zs-wall">
				<Tile span={ 12 } labelledBy="zs-soon-h" className="zs-soon">
					<div className="zs-ghost-wall" aria-hidden="true">
						<span />
						<span />
						<span />
						<span />
						<span />
					</div>
					<div className="zs-soon-copy">
						<h2 id="zs-soon-h" className="zs-tile-title">
							{ __(
								'This part of the wall is still being laid',
								'zero-spam'
							) }
						</h2>
						<p className="zs-hint">
							{ __(
								'Everything you need is on the classic screen for now. Your settings and data are the same in both.',
								'zero-spam'
							) }
						</p>
						{ LEGACY[ area ] && (
							<div>
								<Button
									variant="secondary"
									href={ adminUrl( LEGACY[ area ] ) }
								>
									{ __(
										'Open the classic screen',
										'zero-spam'
									) }
								</Button>
							</div>
						) }
					</div>
				</Tile>
			</div>
		</div>
	);
}
