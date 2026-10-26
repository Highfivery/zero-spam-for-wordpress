/**
 * Webpack configuration for the Zero Spam admin front end.
 *
 * Extends the `@wordpress/scripts` default and changes only what this plugin
 * needs:
 *
 * - Explicit entry points (below) instead of the src/ and block.json
 *   discovery: one per admin area (admin-{area}, from
 *   assets/src/admin/{area}/index.js; Admin_App enqueues only the page's
 *   own; the network page is the `network` area), plus widget-refresh
 *   and blocked.
 * - Output in assets/dist/, which is committed and shipped (/build/ is
 *   ignored by git and .distignore). ZEROSPAM_DIST_DIR builds elsewhere;
 *   bin/check-dist.sh uses it to compare a fresh build with the committed one.
 * - One file per entry, never split chunks: WordPress loads translations per
 *   script handle (wp_set_script_translations), so strings in a lazily loaded
 *   or shared chunk would never be translated. The shared admin code (app/,
 *   data/, styles/) is bundled into every admin entry.
 * - No source maps in production builds.
 * - The CSS-only "blocked" entry ships blocked.css and blocked.asset.php
 *   without the empty JavaScript stub webpack writes for it.
 * - icons/sprite.svg is copied to assets/dist/sprite.svg so it ships.
 *
 * The output must be reproducible (the same sources give byte-identical
 * files); bin/check-dist.sh --twice proves it.
 */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const webpack = require( 'webpack' );

/**
 * WordPress dependencies
 */
const scriptsConfig = require( '@wordpress/scripts/config/webpack.config' );

// The default is an array only with WP_EXPERIMENTAL_MODULES (script modules),
// which this plugin does not use; take the classic script configuration.
const defaultConfig = Array.isArray( scriptsConfig )
	? scriptsConfig[ 0 ]
	: scriptsConfig;

const isProduction = process.env.NODE_ENV === 'production';
const src = path.resolve( __dirname, 'assets/src' );
const outputPath = process.env.ZEROSPAM_DIST_DIR
	? path.resolve( process.env.ZEROSPAM_DIST_DIR )
	: path.resolve( __dirname, 'assets/dist' );

// Admin areas with their own entry point: assets/src/admin/{area}/index.js,
// built to assets/dist/admin-{area}.js and .css. Keep this list, the areas of
// Admin_App (AREAS and the network page) and the budgets loop of
// bin/check-dist.sh in step (assets/src/admin/test/areas.test.js checks it).
const adminAreas = [
	'overview',
	'activity',
	'rules',
	'protection',
	'settings',
	'help',
	'network',
];

// Entries whose source is a stylesheet: webpack still emits a JavaScript
// stub for each; it is removed after the asset file has been written.
const cssOnlyEntries = [ 'blocked' ];

/**
 * Removes the JavaScript stubs of CSS-only entries and adds the icon sprite.
 */
class ZeroSpamAssetsPlugin {
	/**
	 * @param {import('webpack').Compiler} compiler Webpack compiler.
	 */
	apply( compiler ) {
		compiler.hooks.thisCompilation.tap(
			'ZeroSpamAssetsPlugin',
			( compilation ) => {
				const spritePath = path.join( src, 'admin/icons/sprite.svg' );
				compilation.fileDependencies.add( spritePath );

				compilation.hooks.processAssets.tap(
					{
						name: 'ZeroSpamAssetsPlugin',
						// After DependencyExtractionWebpackPlugin (ANALYSE),
						// which hashes the stub with the CSS for the version.
						stage: webpack.Compilation.PROCESS_ASSETS_STAGE_REPORT,
					},
					() => {
						for ( const entry of cssOnlyEntries ) {
							for ( const file of [
								`${ entry }.js`,
								`${ entry }.js.map`,
							] ) {
								if ( compilation.getAsset( file ) ) {
									compilation.deleteAsset( file );
								}
							}
						}

						compilation.emitAsset(
							'sprite.svg',
							new webpack.sources.RawSource(
								fs.readFileSync( spritePath )
							)
						);
					}
				);
			}
		);
	}
}

module.exports = {
	...defaultConfig,
	entry: {
		...Object.fromEntries(
			adminAreas.map( ( area ) => [
				`admin-${ area }`,
				path.join( src, `admin/${ area }/index.js` ),
			] )
		),
		'widget-refresh': path.join( src, 'widget-refresh/index.js' ),
		blocked: path.join( src, 'blocked/index.css' ),
	},
	output: {
		...defaultConfig.output,
		path: outputPath,
	},
	devtool: isProduction ? false : defaultConfig.devtool,
	optimization: {
		...defaultConfig.optimization,
		splitChunks: false,
		runtimeChunk: false,
	},
	plugins: [
		...defaultConfig.plugins,
		// Folds any dynamic import() back into its entry file.
		new webpack.optimize.LimitChunkCountPlugin( { maxChunks: 1 } ),
		new ZeroSpamAssetsPlugin(),
	],
};
