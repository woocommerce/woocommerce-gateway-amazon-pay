/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import React from 'react';

const { registerCheckoutBlock } = wc.blocksCheckout;

/**
 * Internal dependencies
 */
import { getCheckOutFieldsLabel } from '../../utils';
import { activateChange } from '../../renderAmazonButton';
import { settings } from '../express/settings';
import { changeShippingAddressOptions, logOutBannerOptions } from './checkout-blocks';

// Register our checkout Blocks.
registerCheckoutBlock( changeShippingAddressOptions );
registerCheckoutBlock( logOutBannerOptions );

/**
 * The change Payment method component.
 *
 * @returns React component
 */
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

/**
 * Returns a react component and also sets an observer for the onCheckoutValidationBeforeProcessing event.
 *
 * @param {object} props
 * @returns React component
 */
const AmazonPayInfo = ( props ) => {
	const { shippingAddress, setShippingAddress } = props.shippingData;

	const { billingData } = props.billing;

	const { amazonBilling, amazonShipping } = settings.amazonAddress;

	useEffect( () => {
		const unsubscribe = props.eventRegistration.onCheckoutValidationBeforeProcessing(
			async () => {
				for ( const shippingField in amazonShipping ) {
					if ( amazonShipping[ shippingField ] !== shippingAddress[ shippingField ] ) {
						return {
							errorMessage: __( 'We were expecting "', 'woocommerce-gateway-amazon-payments-advanced' ) + amazonShipping[ shippingField ] + __( '" but we received "', 'woocommerce-gateway-amazon-payments-advanced' ) + shippingAddress[ shippingField ] + __( '" instead for the Shipping field "', 'woocommerce-gateway-amazon-payments-advanced' ) + getCheckOutFieldsLabel( shippingField, 'shipping' ) + __( '". Please make any changes to your Shipping details through Amazon.', 'woocommerce-gateway-amazon-payments-advanced' )
						};
					}
				}
				// Not sure if we should check billing details as well. @todo
				// if ( 'undefined' !== typeof jQuery ) {
				// 	if ( ! jQuery( '.wc-block-checkout__use-address-for-billing input.wc-block-components-checkbox__input' ).is( ':checked' ) ) {
				// 		for ( const billingField in amazonBilling ) {
				// 			if ( amazonBilling[ billingField ] !== billingData[ billingField ] ) {
				// 				return {
				// 					errorMessage: __( 'We were expecting "', 'woocommerce-gateway-amazon-payments-advanced' ) + amazonBilling[ billingField ] + __( '" but we received "', 'woocommerce-gateway-amazon-payments-advanced' ) + billingData[ billingField ] + __( '" instead for the Billing field "', 'woocommerce-gateway-amazon-payments-advanced' ) + getCheckOutFieldsLabel( billingField, 'billing' ) + __( '". Please make any changes to your Billing details through Amazon.', 'woocommerce-gateway-amazon-payments-advanced' )
				// 				};
				// 			}
				// 		}
				// 	}
				// }
				return true;
			}
		);
		return () => unsubscribe();
	}, [
		props.eventRegistration.onCheckoutValidationBeforeProcessing,
		billingData,
		shippingAddress,
		amazonBilling,
		amazonShipping,
		props.emitResponse.noticeContexts.PAYMENTS,
		props.emitResponse.responseTypes.ERROR,
		props.emitResponse.responseTypes.SUCCESS,
	] );

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

/**
 * Return the Component that will be used as the label for Amazon Pay "Express".
 *
 * @param {string} label The text label.
 * @param {object} props Props from payment API.
 * @returns React Component
 */
export const AmazonExpressLabel = ( { label, ...props } ) => {
	const { PaymentMethodLabel } = props.components;

	return <PaymentMethodLabel text={ label } />;
};

/**
 * Returns the Components that will be used by Amazon Pay "Express".
 *
 * @param {object} props
 * @returns React Component
 */
export const AmazonExpressContent = ( props ) => {
	return (
		<React.Fragment>
			<AmazonPayInfo { ...props } />
		</React.Fragment>
	);
};
