/**
 * Puts a Local site's plugin state back the way the run found it (options
 * with their autoload values, every Zero Spam table, the error log,
 * users_can_register, default_role) and ends the run, so the test mu-plugin
 * stops stubbing the site's outgoing HTTP. Nothing to do on wp-env, which is
 * disposable.
 */

import { test as teardown } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

import { ZsApi } from '../support/e2e-api';
import { ZsRequestUtils } from '../support/request-utils';
import { resolveTarget, storageStatePath } from '../support/targets';

const target = resolveTarget();

teardown( 'restore the Local site from the snapshot', async () => {
	teardown.skip( 'local' !== target.kind, 'wp-env sites are disposable.' );
	const file = join( target.artifacts, 'local-backup', 'snapshot.json' );
	teardown.skip( ! existsSync( file ), 'No snapshot was taken.' );

	const utils = await ZsRequestUtils.setupFor( { user: target.users.admin!, baseURL: target.baseURL, token: target.token, storageStatePath: storageStatePath( target, 'admin' ) } );
	const api = new ZsApi( utils );
	try {
		await api.call( 'POST', 'snapshot', JSON.parse( readFileSync( file, 'utf8' ) ) );
	} finally {
		// End the run even if the restore failed (the mark would expire on its own after 3 hours).
		await api.call( 'POST', 'run', { active: false } );
		await utils.request.dispose();
	}
} );
