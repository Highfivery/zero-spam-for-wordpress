/**
 * Entry point of the network admin page (assets/dist/admin-network.js): the
 * network page's eight tabs (network.js), with the tab kept in ?tab= by the
 * shell, which mirrors it in the sidebar.
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Network } from './network';

mount( () => (
	<AreaView area="network">
		{ ( { tab, setTab } ) => <Network tab={ tab } setTab={ setTab } /> }
	</AreaView>
) );
