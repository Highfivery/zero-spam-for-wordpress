/**
 * Entry point of the network admin page (assets/dist/admin-network.js).
 * The network area is not built yet: each network tab shows the
 * placeholder.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Placeholder } from '../app/placeholder';

mount( () => (
	<AreaView area="network">
		{ ( { tab } ) => (
			<Placeholder
				area="network"
				title={ __( 'Network', 'zero-spam' ) }
				tab={ tab }
			/>
		) }
	</AreaView>
) );
