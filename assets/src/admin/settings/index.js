/**
 * Entry point of the Settings page (assets/dist/admin-settings.js).
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Settings } from './settings';

mount( () => (
	<AreaView area="settings">
		<Settings />
	</AreaView>
) );
