/**
 * External dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './style.scss';
import { Edit } from './_edit';
import { save } from './_save';

registerBlockType( 'amazon-payments-advanced/log-out-banner', {
	edit: Edit,
	save,
} );
