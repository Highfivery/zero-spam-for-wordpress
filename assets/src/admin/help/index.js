/**
 * Entry point of the Help page (assets/dist/admin-help.js). The Help area
 * is not built yet: the page shows the placeholder.
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { Placeholder } from '../app/placeholder';
import { areaTitles } from '../app/shell/shell';

mount( () => (
	<AreaView area="help">
		<Placeholder area="help" title={ areaTitles().help } />
	</AreaView>
) );
