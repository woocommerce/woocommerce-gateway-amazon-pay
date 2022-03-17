import { useEffect } from '@wordpress/element';

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').EmitResponseProps} EmitResponseProps
 * @typedef {import('../stripe-utils/type-defs').Stripe} Stripe
 */

/**
 * Opens the modal for PaymentIntent authorizations.
 *
 * @param {Object}           params                Params object.
 * @param {Object}           params.paymentDetails The payment details from the
 *                                                 server after checkout processing.
 * @param {string}           params.errorContext   Context where errors will be added.
 * @param {string}           params.errorType      Type of error responses.
 * @param {string}           params.successType    Type of success responses.
 */

export const usePaymentIntents = (
	subscriber,
	setSourceId,
	emitResponse
) => {
	useEffect( () => {
		const unsubscribe = subscriber( async ( { processingResponse } ) => {
			// const paymentDetails = processingResponse.paymentDetails || {};
			console.log( processingResponse );
		} );
		return () => unsubscribe();
	}, [
		subscriber,
		emitResponse.noticeContexts.PAYMENTS,
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		setSourceId,
	] );
};
