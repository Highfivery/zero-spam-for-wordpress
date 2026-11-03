/**
 * Entry point of the Help page (assets/dist/admin-help.js).
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Help } from './help';

mount( () => (
	<AreaView area="help">
		<Help />
	</AreaView>
) );
