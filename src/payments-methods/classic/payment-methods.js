import { decodeEntities } from '@wordpress/html-entities';
import { getBlocksConfiguration } from '../../utils';
import { useEffect } from '@wordpress/element';
import { renderAndInitAmazonCheckout } from '../../renderAmazonButton';
import React from 'react';

const Content = () => {
	const settings = getBlocksConfiguration( 'amazon_payments_advanced_data' );
	return decodeEntities( settings.description || '' );
};

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

export const AmazonContent = ( props ) => {
	return (
		<React.Fragment>
			<Content />
			<AmazonPayBtn { ...props } />
		</React.Fragment>
	);
};
