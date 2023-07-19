/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { Edit } from './_edit';
import { save } from './_save';

registerBlockType( 'amazon-payments-advanced/change-address', {
	edit: Edit,
	save,
} );
