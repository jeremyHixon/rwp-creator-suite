import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import edit from './edit';
import save from './save';
import blockJson from './block.json';

registerBlockType( blockJson.name, {
	...blockJson,
	edit,
	save,
} );
