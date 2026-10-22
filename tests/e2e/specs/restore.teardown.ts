/**
 * Puts a Local site's plugin state back the way the run found it (options,
 * log and blocked tables, error log, users_can_register, default_role).
 * Nothing to do on wp-env, which is disposable.
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
	// The uninstall test may have deactivated the plugin; make sure it is back first.
	await api.call( 'POST', 'snapshot', JSON.parse( readFileSync( file, 'utf8' ) ) );
	await utils.request.dispose();
} );
