/**
 * Vitest configuration for the JavaScript unit tests (npm run test:js, which
 * runs wp-scripts test-unit-js). Only the tests next to the admin sources run
 * here; tests/e2e has Playwright's own runner.
 *
 * The sources are JSX in .js files (as `@wordpress/scripts` builds them), so
 * they are transformed as JSX with the automatic runtime; component tests
 * render with react-dom/server.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	oxc: {
		include: /\.js$/,
		exclude: /node_modules/,
		lang: 'jsx',
		jsx: { runtime: 'automatic' },
	},
	test: {
		include: [ 'assets/src/**/*.test.js' ],
		environment: 'node',
	},
} );
