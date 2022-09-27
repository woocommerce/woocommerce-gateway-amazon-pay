/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import React from 'react';

const { registerExpressPaymentMethod, registerPaymentMethod, registerPaymentMethodExtensionCallbacks } = wc.wcBlocksRegistry;
const { registerCheckoutBlock } = wc.blocksCheckout;

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { AmazonComponent, AmazonPayPreview, Label } from '../../utils';
import { AmazonExpressContent } from './payment-methods-express';
import { AmazonContent } from './payment-methods';
import { settings } from './settings';
import { changeShippingAddressOptions, logOutBannerOptions } from './checkout-blocks';

if ( settings.loggedIn ) {
    const label =
	decodeEntities(settings.title) ||
	__('Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced');

    // Unset all other Gateways.
    if ( settings.allOtherGateways ) {
        let hideAllOtherPaymentGateways = {};
        for ( const offset in settings.allOtherGateways ) {
            hideAllOtherPaymentGateways[ settings.allOtherGateways[ offset ] ] = () => { return false; };
        }
        registerPaymentMethodExtensionCallbacks( 'amazon_payments_advanced', hideAllOtherPaymentGateways );
    }

    // Register our checkout Blocks.
    registerCheckoutBlock( changeShippingAddressOptions );
    registerCheckoutBlock( logOutBannerOptions );

    /**
     * Amazon Pay "Express" payment method config object in the case user is logged in to Amazon.
     * In this case Amazon pay is being registered as a normal WooCommerce Gateway.
     */
    const amazonPayExpressPaymentMethod = {
        name: PAYMENT_METHOD_NAME,
        label: <Label label={ label } />,
        placeOrderButtonLabel: __( 'Proceed to Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
        content: <AmazonComponent RenderedComponent={ AmazonContent } />,
        edit: <AmazonComponent RenderedComponent={ AmazonContent } />,
        canMakePayment: ( {cartTotals } ) => {
            const currencyCode = cartTotals.currency_code;
            const { allowedCurrencies } = settings;
            return allowedCurrencies ? allowedCurrencies.includes( currencyCode ) : true;
        },
        ariaLabel: label,
        supports: {
            features: settings?.supports ?? [],
        },
    };

    /**
     * Registers Amazon Pay "Express" as a Payment Method in the Checkout Block of WooCommerce Blocks.
     */
    registerPaymentMethod( amazonPayExpressPaymentMethod );
} else {
    /**
     * Amazon Pay "Express" payment method config object in the case user is logged out of Amazon.
     * In this case Amazon pay is being registered as an Express WooCommerce Gateway.
     */
    const amazonPayExpressPaymentMethod = {
        name: PAYMENT_METHOD_NAME,
        content: <AmazonComponent RenderedComponent={ AmazonExpressContent }/>,
        edit: <AmazonPayPreview />,
        canMakePayment: ( {cartTotals } ) => {
            const currencyCode = cartTotals.currency_code;
            const { allowedCurrencies } = settings;
            return allowedCurrencies ? allowedCurrencies.includes( currencyCode ) : true;
        },
        supports: {
            features: settings?.supports ?? [],
        },
    };

    /**
     * Don't register as an Express Payment method if the hidden button mode is on,
     * since the layout would appear misleading to users in cases when there are no
     * other registered Express Payment methods.
     *
     * In the cart an "OR" would appear without an actual user selection
     * and in the checkout the express checkout block would render and it would appear empty.
     */
    if ( 'yes' !== settings['hide_button_mode'] ) {
        /**
         * Registers Amazon Pay "Express" as a Payment Method in the Checkout Block of WooCommerce Blocks.
         */
        registerExpressPaymentMethod( amazonPayExpressPaymentMethod );
    }
}
