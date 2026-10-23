/**
 * ESLint flat config for the admin front end (npm run lint:js): the WordPress
 * rules from `@wordpress/eslint-plugin`, the zero-spam text domain, and Vitest
 * for the unit tests. tests/e2e is type-checked by TypeScript instead
 * (npm run typecheck:e2e).
 */
const wpPlugin = require('@wordpress/eslint-plugin');

module.exports = [
	{
		ignores: [
			'**/node_modules/**',
			'**/vendor/**',
			'build/**',
			'artifacts/**',
			'assets/dist/**',
			'assets/js/**',
			'tests/**',
			'.claude/**',
		],
	},
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve('@wordpress/babel-preset-default'),
					],
				},
			},
		},
		rules: {
			'@wordpress/i18n-text-domain': [
				'error',
				{ allowedTextDomain: 'zero-spam' },
			],
		},
	},
	{
		files: ['webpack.config.js', 'eslint.config.cjs'],
		languageOptions: {
			globals: {
				__dirname: 'readonly',
				module: 'writable',
				require: 'readonly',
				process: 'readonly',
			},
		},
	},
	{
		// Vitest is imported explicitly, so only its node:* helpers need this.
		files: ['assets/src/**/*.test.js'],
		languageOptions: {
			globals: { __dirname: 'readonly' },
		},
		rules: {
			'import/no-extraneous-dependencies': 'off',
		},
	},
];
