/**
 * Registers the Statista Chart block.
 *
 * The block is rendered in PHP, so `save` stores nothing but the attributes.
 */
import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';
import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
