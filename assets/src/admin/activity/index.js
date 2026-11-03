/**
 * Entry point of the Activity page (assets/dist/admin-activity.js).
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Activity } from './activity';

mount( () => (
	<AreaView area="activity">
		<Activity />
	</AreaView>
) );
