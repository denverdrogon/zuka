/**
 * @wordpress/scripts config with the plugin's entries and hashed lazy chunks:
 * chunks are loaded by URL at runtime, outside wp_enqueue_script's version
 * query, so their file name must change with their content.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		motion: path.resolve( __dirname, 'assets/src/motion/index.js' ),
		admin: path.resolve( __dirname, 'assets/src/admin/index.js' ),
		editor: path.resolve( __dirname, 'assets/src/editor/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
		chunkFilename: '[name].[contenthash:8].js',
	},
	plugins: defaultConfig.plugins.map( ( plugin ) => {
		if ( 'MiniCssExtractPlugin' === plugin.constructor.name ) {
			plugin.options.chunkFilename = '[name].[contenthash:8].css';
		}
		return plugin;
	} ),
};
