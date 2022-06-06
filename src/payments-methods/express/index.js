/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const { registerExpressPaymentMethod } = wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { AmazonComponent, AmazonPayPreview } from '../../utils';
import { AmazonExpressContent } from './payment-methods';
import { settings } from './settings';

/**
 * Amazon Pay "Express" payment method config object.
 */
const amazonPayPaymentMethod = {
    name: PAYMENT_METHOD_NAME,
    content: <AmazonComponent RenderedComponent={ AmazonExpressContent }/>,
    edit: <AmazonPayPreview />,
    canMakePayment: () => true,
    supports: {
        features: settings?.supports ?? [],
    },
};

/**
 * Registers Amazon Pay "Express" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
registerExpressPaymentMethod( amazonPayPaymentMethod );
