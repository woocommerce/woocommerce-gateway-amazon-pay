/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import React from 'react';

/**
 * Internal dependencies
 */
import { getCheckOutFieldsLabel, overrideRequiredFieldValidation, restoreRequiredFieldValidation } from '../../_utils';
import { activateChange } from '../../_renderAmazonButton';
import { settings } from './_settings';
 

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
 * Returns a react component and also sets an observer for the onCheckoutValidation event.
 *
 * @param {object} props
 * @returns React component
 */
const AmazonPayInfo = ( props ) => {
    const { shippingAddress, setShippingAddress } = props.shippingData;

    const { billingData } = props.billing;

    const { amazonBilling, amazonShipping } = settings.amazonAddress;

    const [ overridenFields, setOverridenFields ] = useState( [] );

    useEffect( () => {
        const unsubscribe = props.eventRegistration.onCheckoutValidation(
            async () => {
                restoreRequiredFieldValidation( overridenFields );
                setOverridenFields( overrideRequiredFieldValidation( shippingAddress?.country || '' ) );
                for ( const shippingField in amazonShipping ) {
                     // Values are the same as expected. Bail.
                    if (amazonShipping[ shippingField ] === shippingAddress[ shippingField ]) {
                        continue;
                    }
            
                    const checkoutFieldLabel = getCheckOutFieldsLabel( shippingField, 'shipping' );
                    // Field not present in the form, as a result value can't be supplied. Bail.
                    if ( false === checkoutFieldLabel ) {
                        continue;
                    }
            
                    // Field present in the form but value mismatch. Return error.
                    return {
                        errorMessage: sprintf( __( 'We were expecting "%1$s" but we received "%2$s" instead for the Shipping field "%3$s". Please make any changes to your Shipping details through Amazon."', 'woocommerce-gateway-amazon-payments-advanced' ), amazonShipping[ shippingField ], shippingAddress[ shippingField ], checkoutFieldLabel )
                    };
                }

                return true;
            }
        );
        return () => unsubscribe();
    }, [
        props.eventRegistration.onCheckoutValidation,
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
 * Returns the Components that will be used by Amazon Pay "Express".
 *
 * @param {object} props
 * @returns React Component
 */
export const AmazonContent = ( props ) => {
    return (
        <React.Fragment>
            <AmazonPayInfo { ...props } />
        </React.Fragment>
    );
};
