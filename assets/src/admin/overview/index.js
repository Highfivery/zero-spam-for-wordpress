/**
 * Entry point of the Overview page (assets/dist/admin-overview.js).
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Overview } from './overview';

mount( () => (
	<AreaView area="overview">
		<Overview />
	</AreaView>
) );
