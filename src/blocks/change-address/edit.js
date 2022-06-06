import { useBlockProps } from '@wordpress/block-editor';

export const Edit = ( props ) => <div { ...useBlockProps() } />;
