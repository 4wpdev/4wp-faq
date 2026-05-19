/**
 * Extends @wordpress/scripts with admin React screen entry (4wp-weather pattern).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry,
		index: './src/index.js',
		setup: './src/admin/setup/index.js',
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
	},
};
