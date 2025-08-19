const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'blocks/instagram-analyzer/index': path.resolve(
			__dirname,
			'src/blocks/instagram-analyzer/index.js'
		),
		'blocks/instagram-analyzer/style': path.resolve(
			__dirname,
			'src/blocks/instagram-analyzer/style.js'
		),
		'blocks/instagram-analyzer/editor': path.resolve(
			__dirname,
			'src/blocks/instagram-analyzer/editor.js'
		),
		'blocks/instagram-banner/index': path.resolve(
			__dirname,
			'src/blocks/instagram-banner/index.js'
		),
		'blocks/instagram-banner/style': path.resolve(
			__dirname,
			'src/blocks/instagram-banner/style.js'
		),
		'blocks/instagram-banner/editor': path.resolve(
			__dirname,
			'src/blocks/instagram-banner/editor.js'
		),
		'blocks/caption-writer/index': path.resolve(
			__dirname,
			'src/blocks/caption-writer/index.js'
		),
		'blocks/caption-writer/editor': path.resolve(
			__dirname,
			'src/blocks/caption-writer/editor.js'
		),
		'blocks/content-repurposer/index': path.resolve(
			__dirname,
			'src/blocks/content-repurposer/index.js'
		),
		'blocks/content-repurposer/editor': path.resolve(
			__dirname,
			'src/blocks/content-repurposer/editor.js'
		),
		'blocks/account-manager/index': path.resolve(
			__dirname,
			'src/blocks/account-manager/index.js'
		),
		'blocks/account-manager/editor': path.resolve(
			__dirname,
			'src/blocks/account-manager/editor.js'
		),
		'blocks/account-manager/style': path.resolve(
			__dirname,
			'src/blocks/account-manager/style.js'
		),
	},
	// Performance monitoring for development
	performance: {
		hints: process.env.NODE_ENV === 'production' ? 'warning' : false,
		maxEntrypointSize: 512000, // 500KB - reasonable for WordPress blocks
		maxAssetSize: 512000,
	},
};