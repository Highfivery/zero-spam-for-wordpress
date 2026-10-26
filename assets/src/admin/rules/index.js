/**
 * Entry point of the Rules page (assets/dist/admin-rules.js).
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Rules } from './rules';

mount( () => (
	<AreaView area="rules">
		<Rules />
	</AreaView>
) );
