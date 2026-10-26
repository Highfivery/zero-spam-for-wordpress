/**
 * Entry point of the Protection page (assets/dist/admin-protection.js).
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Protection } from './protection';

mount( () => (
	<AreaView area="protection">
		<Protection />
	</AreaView>
) );
