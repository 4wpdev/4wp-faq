/**
 * Extends @wordpress/scripts with admin React screen entry (4wp-weather pattern).
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config.js' );

module.exports = {
	...defaultConfig,
	entry: {
		...( typeof defaultConfig.entry === 'function'
			? defaultConfig.entry()
			: defaultConfig.entry ),
		index: './src/index.js',
		'faq-list': './src/faq-list/index.js',
		'faq-card': './src/faq-card/index.js',
		'faq-categories': './src/faq-categories/index.js',
		setup: './src/admin/setup/index.js',
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
	},
};
