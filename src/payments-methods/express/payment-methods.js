import { getBlocksConfiguration, Content } from '../../utils';
import { PAYMENT_METHOD_NAME } from './constants';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { renderAndInitAmazonCheckout } from '../../renderAmazonButton';
import React from 'react';

/**
 * Returns a react component and also sets an observer for the onCheckoutAfterProcessingWithSuccess event.
 * @param {object} props
 * @returns React component
 */
const AmazonPayExpressBtn = ( props ) => {
	useEffect( () => {
		const unsubscribe = props.eventRegistration.onCheckoutAfterProcessingWithSuccess(
			async ( { processingResponse } ) => {
				const paymentDetails = processingResponse.paymentDetails || {};
				renderAndInitAmazonCheckout(
					'#classic_pay_with_amazon',
					'classic',
					paymentDetails?.amazonCreateCheckoutParams
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

	useEffect( () => {
		const unsubscribe = props.eventRegistration.onPaymentProcessing(
			async () => {
				const shippingPhone = document.getElementById( 'shipping-phone' );
				const billingPhone = document.getElementById( 'phone' );
				if ( ! shippingPhone?.value && ! billingPhone?.value ) {
					return {
						type: 'error',
						message: __( 'A phone number is required to complete your checkout through Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' )
					};
				}
				return true;
			}
		);
		return () => unsubscribe();
	}, [
		props.eventRegistration.onPaymentProcessing,
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
export const AmazonExpressContent = ( props ) => {
	return (
		<React.Fragment>
			<Content description={ getBlocksConfiguration( PAYMENT_METHOD_NAME + '_data' )?.description || '' } />
			<AmazonPayExpressBtn { ...props } />
		</React.Fragment>
	);
};
