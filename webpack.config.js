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
		'blocks/content-repurposer/style': path.resolve(
			__dirname,
			'src/blocks/content-repurposer/style.js'
		),
		'blocks/content-repurposer/editor': path.resolve(
			__dirname,
			'src/blocks/content-repurposer/editor.js'
		),
	},
};
