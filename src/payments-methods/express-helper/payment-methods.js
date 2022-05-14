import { getBlocksConfiguration } from '../../utils';
import { useEffect, render, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { PAYMENT_METHOD_NAME } from '../express/constants';
import { activateChange } from '../../renderAmazonButton';
import React from 'react';

const settings = getBlocksConfiguration(PAYMENT_METHOD_NAME + '_data');

const LogOutBanner = () => {
	return (
		<div className="woocommerce-info info">
			{ decodeEntities( settings.logoutMessage ) } { " " }
			<a href={ settings.logoutUrl }>
				{ decodeEntities( __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) ) }
			</a>
		</div>
	);
};

const ChangePayment = () => {
	useEffect( () => {
		activateChange( 'amazon_change_payment_method', 'changePayment' );
	}, [] );

	return (
		<a href="#" className="wc-apa-widget-change" id="amazon_change_payment_method">
			{ settings.hasPaymentPreferences ? __( 'Change', 'woocommerce-gateway-amazon-payments-advanced' ) : __( 'Select', 'woocommerce-gateway-amazon-payments-advanced' ) }
		</a>
	);
};

const ChangeShippingAddress = () => {
	useEffect( () => {
		activateChange( 'amazon_change_shipping_address', 'changeAddress' );
	}, [] );

	return (
		<h2 style={{ width: '100%', display: 'flex', flexFlow: 'row nowrap', justifyContent: 'space-between' }} className="wc-block-components-title wc-block-components-checkout-step__title" aria-hidden="true">{ __( 'Shipping address', 'woocommerce' ) }
			<a href="#" className="wc-apa-widget-change" id="amazon_change_shipping_address">
				{ __( 'Change', 'woocommerce-gateway-amazon-payments-advanced' ) }
			</a>
		</h2>
	);
};

/**
 * Returns a react component and also sets an observer for the onCheckoutAfterProcessingWithSuccess event.
 * @param {object} props
 * @returns React component
 */
const AmazonPayBtn = ( props ) => {
	const [ isActive, setIsActive ] = useState( true );

	useEffect( () => {
		activateChange( 'amazon_change_payment_method', 'changePayment' );
	}, [] );

	// console.log( isActive, setIsActive );

	// useEffect( () => {
	// 	const unsubscribe = props.eventRegistration.onCheckoutAfterProcessingWithSuccess(
	// 		async ( { processingResponse } ) => {
	// 			const paymentDetails = processingResponse. || {};
	// 			renderAndInitAmazonCheckout(paymentDetails
	// 				'#classic_pay_with_amazon',
	// 				'classic',
	// 				paymentDetails?.amazonCreateCheckoutParams
	// 			);
	// 			return true;
	// 		}
	// 	);
	// 	return () => unsubscribe();
	// }, [
	// 	props.eventRegistration.onCheckoutAfterProcessingWithSuccess,
	// 	props.emitResponse.noticeContexts.PAYMENTS,
	// 	props.emitResponse.responseTypes.ERROR,
	// 	props.emitResponse.responseTypes.SUCCESS,
	// ] );

	// useEffect( () => {
	// 	const unsubscribe = props.eventRegistration.onPaymentProcessing(
	// 		async () => {
	// 			const shippingPhone = document.getElementById( 'shipping-phone' );
	// 			const billingPhone = document.getElementById( 'phone' );
	// 			if ( ! shippingPhone?.value && ! billingPhone?.value ) {
	// 				return {
	// 					type: 'error',
	// 					message: __( 'A phone number is required to complete your checkout through Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' )
	// 				};
	// 			}
	// 			return true;
	// 		}
	// 	);
	// 	return () => unsubscribe();
	// }, [
	// 	props.eventRegistration.onPaymentProcessing,
	// 	props.emitResponse.noticeContexts.PAYMENTS,
	// 	props.emitResponse.responseTypes.ERROR,
	// 	props.emitResponse.responseTypes.SUCCESS,
	// ] );

	return (
		<React.Fragment>
			<p>
				<ChangePayment />
				{ __( 'Payment Method', 'woocommerce-gateway-amazon-payments-advanced' ) }
			</p>
			<div className="payment_method_display">
				<span className="wc-apa-amazon-logo"></span>{ decodeEntities( settings.selectedPaymentMethod ) }
			</div>
		</React.Fragment>
	);
};

export const AmazonExpressLabel = ( { label, ...props } ) => {

	const { PaymentMethodLabel } = props.components;

	useEffect( () => {
		render( <LogOutBanner/>, document.getElementsByClassName( 'wp-block-woocommerce-checkout-express-payment-block' )[0] );
		render( <ChangeShippingAddress/>, document.querySelector( '.wc-block-checkout__shipping-fields > .wc-block-components-checkout-step__heading' ) );
	}, [] );

	return <PaymentMethodLabel text={ label } />;
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
			<AmazonPayBtn { ...props } />
		</React.Fragment>
	);
};
