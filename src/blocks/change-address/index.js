/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { Edit } from './edit';
import { save } from './save';

registerBlockType( 'amazon-payments-advanced/change-address', {
	edit: Edit,
	save,
} );
