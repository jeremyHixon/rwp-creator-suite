import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './editor';
import './style';

const { name } = metadata;

registerBlockType(name, {
    ...metadata,
    edit: Edit,
    save,
});