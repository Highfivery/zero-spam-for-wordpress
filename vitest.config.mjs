/**
 * Vitest configuration for the JavaScript unit tests (npm run test:js, which
 * runs wp-scripts test-unit-js). Only the tests next to the admin sources run
 * here; tests/e2e has Playwright's own runner.
 *
 * The admin sources are .js files with JSX (as `@wordpress/scripts` builds
 * them); Vite only reads JSX from .jsx files, so they are transformed as JSX
 * here (React's automatic runtime, as in the build), which lets a test
 * render a component to markup with react-dom/server.
 */
import { transformWithOxc } from 'vite';
import { defineConfig } from 'vitest/config';

const jsxInJs = {
	name: 'zero-spam-jsx-in-js',
	enforce: 'pre',
	transform( code, id ) {
		if ( ! /[\\/]assets[\\/]src[\\/].+\.js$/.test( id ) ) {
			return null;
		}
		return transformWithOxc( code, id.replace( /\.js$/, '.jsx' ), {
			jsx: { runtime: 'automatic', importSource: 'react' },
		} );
	},
};

export default defineConfig( {
	plugins: [ jsxInJs ],
	test: {
		include: [ 'assets/src/**/*.test.js' ],
		environment: 'node',
	},
} );
