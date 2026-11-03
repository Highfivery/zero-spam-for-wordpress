/**
 * Entry point of the Overview page (assets/dist/admin-overview.js). While
 * first-run setup is pending (Admin_App::view_for() on the Overview sets
 * boot.onboarding), the setup replaces the Overview and its shell.
 */

/**
 * Internal dependencies
 */
import { mount } from '../app/mount';
import { AreaView } from '../app/app';
import { boot } from '../data/api';
import { Onboarding } from '../onboarding/onboarding';
import { Overview } from './overview';

mount( () =>
	boot.onboarding ? (
		<Onboarding />
	) : (
		<AreaView area="overview">
			<Overview />
		</AreaView>
	)
);
