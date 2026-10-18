/**
 * This is a main entrypoint for Webpack config.
 * All the settings are pulled from node_modules/@eightshift/frontend-libs/webpack.
 * We are loading mostly used configuration but you can always override or turn off the default setup and provide your own.
 * Please referer to Eightshift-libs wiki for details.
 */
module.exports = (env, argv) => {

	const projectConfig = {
		config: {
			projectDir: __dirname, // Current project directory absolute path.
			projectPath: 'wp-content/themes/going-green-transport-block-theme', // Project path relative to project root.
			projectUrl: 'going-green-transport.lndo.site', // Local dev URL (browsersync proxy only).
			// The theme ships no Eightshift blocks (they live in the shared plugin); reuse
			// the "blocks editor" entry slot for editor scripts (assets/editor/).
			blocksAssetsPath: 'assets/editor',
		},
	};

	// Generate webpack config for this project using options object.
	return require('./node_modules/@eightshift/frontend-libs/webpack')(argv.mode, projectConfig);
};
