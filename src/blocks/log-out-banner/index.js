/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './style.scss';
import { Edit } from './edit';
import { save } from './save';

registerBlockType( 'amazon-payments-advanced/log-out-banner', {
	edit: Edit,
	save,
} );
