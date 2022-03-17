/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { getBlocksConfiguration } from '../../utils';
import { AmazonContent } from './payment-methods';

const settings = getBlocksConfiguration( 'amazon_payments_advanced_data' );
const defaultLabel = __( 'Amazon Pay', 'woocommerce' );
const label = decodeEntities( settings.title ) || defaultLabel;

/**
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').RegisteredPaymentMethodProps} RegisteredPaymentMethodProps
 */

/**
 * Content component
 */

const AmazonComponent = ( { RenderedComponent, ...props } ) => {
	const [ errorMessage, setErrorMessage ] = useState( '' );

	useEffect( () => {
		if ( errorMessage ) {
			throw new Error( errorMessage );
		}
	}, [ errorMessage ] );

	return <RenderedComponent { ...props } />
}

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

/**
 * Bank transfer (BACS) payment method config object.
 */
export const amazonPayPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <Label />,
	content: <AmazonComponent RenderedComponent={ AmazonContent }/>,
	edit: <AmazonComponent RenderedComponent={ AmazonContent }/>,
	canMakePayment: () => true,
	ariaLabel: label,
	// eventRegistration: ( { eventRegistration } ) => {
	// 	const onCheckoutAfterProcessingWithSuccess = eventRegistration.onCheckoutAfterProcessingWithSuccess( ( redirectUrl, orderId, customerId, orderNotes, processingResponse ) => {
	// 		console.log( redirectUrl, orderId, customerId, orderNotes, processingResponse );
	// 	} );
	// 	useEffect( () => {
	// 		const unsubscribe = onCheckoutAfterProcessingWithSuccess( () => true );
	// 		return unsubscribe;
	// 	}, [ onCheckoutAfterProcessingWithSuccess ] );
	// },
	supports: {
		features: settings?.supports ?? [],
	},
};
