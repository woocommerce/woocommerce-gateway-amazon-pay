/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

const { registerExpressPaymentMethod } = wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME, BACKEND_PAYMENT_METHOD_NAME } from './constants';
import { getBlocksConfiguration, Label, AmazonComponent } from '../../utils';
import { AmazonExpressContent } from './payment-methods';

const settings = getBlocksConfiguration( PAYMENT_METHOD_NAME + '_data' );
const label = decodeEntities( settings.title ) || __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' );

/**
 * Amazon Pay "Express" payment method config object.
 */
const amazonPayPaymentMethod = {
    name: PAYMENT_METHOD_NAME,
    paymentMethodId: BACKEND_PAYMENT_METHOD_NAME,
    label: <Label label={ label }/>,
    placeOrderButtonLabel: __( 'Proceed to Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
    content: <AmazonComponent RenderedComponent={ AmazonExpressContent }/>,
    edit: <AmazonComponent RenderedComponent={ AmazonExpressContent }/>,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings?.supports ?? [],
    },
};

/**
 * Registers Amazon Pay "Express" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
 registerExpressPaymentMethod( amazonPayPaymentMethod );