import { decodeEntities } from '@wordpress/html-entities';
import { getBlocksConfiguration } from '../../utils';
import { useEffect } from '@wordpress/element';
import { renderAndInitAmazonCheckout } from '../../renderAmazonButton';
import React from 'react';

/**
 * Returns the payment method's description.
 *
 * @returns {string}
 */
const Content = () => {
	const settings = getBlocksConfiguration( 'amazon_payments_advanced_data' );
	return decodeEntities( settings.description || '' );
};

/**
 * Returns a react component and also sets an observer for the onCheckoutAfterProcessingWithSuccess event.
 * @param {object} props
 * @returns React component
 */
const AmazonPayBtn = ( props ) => {
	useEffect( () => {
		const unsubscribe = props.eventRegistration.onCheckoutAfterProcessingWithSuccess(
			async ( { processingResponse } ) => {
				const paymentDetails = processingResponse.paymentDetails || {};
				renderAndInitAmazonCheckout(
					'#classic_pay_with_amazon',
					'classic',
					paymentDetails?.amzCreateCheckoutParams
				);
				return true;
			}
		);
		return () => unsubscribe();
	}, [
		props.eventRegistration.onCheckoutAfterProcessingWithSuccess,
		props.emitResponse.noticeContexts.PAYMENTS,
		props.emitResponse.responseTypes.ERROR,
		props.emitResponse.responseTypes.SUCCESS,
	] );
	return <div id="classic_pay_with_amazon" />;
};

/**
 * Returns the Components that will be used by Amazon Pay "Classic".
 *
 * @param {object} props
 * @returns React Component
 */
export const AmazonContent = ( props ) => {
	return (
		<React.Fragment>
			<Content />
			<AmazonPayBtn { ...props } />
		</React.Fragment>
	);
};
