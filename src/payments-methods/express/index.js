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
    registerExpressPaymentMethod( amazonPayPaymentMethod );
}
