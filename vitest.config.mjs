/**
 * Vitest configuration for the JavaScript unit tests (npm run test:js, which
 * runs wp-scripts test-unit-js). Only the tests next to the admin sources run
 * here; tests/e2e has Playwright's own runner.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		include: [ 'assets/src/**/*.test.js' ],
		environment: 'node',
	},
} );
