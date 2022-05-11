import { getBlocksConfiguration } from '../../utils';
import { PAYMENT_METHOD_NAME } from './constants';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { renderAmazonButton } from '../../renderAmazonButton';

/**
 * Returns a react component and also sets an observer for the onCheckoutAfterProcessingWithSuccess event.
 * @param {object} props
 * @returns React component
 */
const AmazonPayExpressBtn = ( props ) => {
	useEffect( () => {
		renderAmazonButton( '#pay_with_amazon_express', 'express', null, getBlocksConfiguration( PAYMENT_METHOD_NAME + '_data' )?.jsParams || null );
	}, [] );

	return <div id="pay_with_amazon_express" />;
};

/**
 * Returns the Components that will be used by Amazon Pay "Express".
 *
 * @param {object} props
 * @returns React Component
 */
export const AmazonExpressContent = ( props ) => {
	return <AmazonPayExpressBtn { ...props } />;
};
