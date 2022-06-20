/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { renderAmazonButton } from '../../renderAmazonButton';

/**
 * Returns a react component and also sets an observer for the onCheckoutAfterProcessingWithSuccess event.
 * @param {object} props
 * @returns React component
 */
const AmazonPayExpressBtn = ( props ) => {
	useEffect( () => {
		renderAmazonButton( '#pay_with_amazon_express', 'express', null );
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
