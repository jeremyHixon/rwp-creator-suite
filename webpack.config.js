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
		'blocks/hashtag-analysis/index': path.resolve(
			__dirname,
			'src/blocks/hashtag-analysis/index.js'
		),
		'blocks/hashtag-analysis/style': path.resolve(
			__dirname,
			'src/blocks/hashtag-analysis/style.js'
		),
		'blocks/hashtag-analysis/editor': path.resolve(
			__dirname,
			'src/blocks/hashtag-analysis/editor.js'
		),
	},
};
