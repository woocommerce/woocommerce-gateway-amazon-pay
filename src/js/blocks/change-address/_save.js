/**
 * External dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

export const save = ( { attributes } ) => <div { ...useBlockProps.save() } data-block-name="amazon-payments-advanced/change-address" />;
