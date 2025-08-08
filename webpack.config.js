const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'blocks/instagram-analyzer/index': path.resolve(__dirname, 'src/blocks/instagram-analyzer/index.js'),
        'blocks/instagram-analyzer/style': path.resolve(__dirname, 'src/blocks/instagram-analyzer/style.js'),
        'blocks/instagram-analyzer/editor': path.resolve(__dirname, 'src/blocks/instagram-analyzer/editor.js'),
    },
};