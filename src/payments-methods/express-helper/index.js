/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import React from 'react';

const { registerPaymentMethod, registerPaymentMethodExtensionCallbacks } = wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from '../express/constants';
import { AmazonComponent, Label } from '../../utils';
import { AmazonExpressContent } from './payment-methods';
import { settings } from '../express/settings';

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


/**
 * Amazon Pay "Express" payment method config object.
 */
const amazonPayPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <Label label={ label } />,
	placeOrderButtonLabel: __( 'Proceed to Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
	content: <AmazonComponent RenderedComponent={ AmazonExpressContent } />,
	edit: <AmazonComponent RenderedComponent={ AmazonExpressContent } />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings?.supports ?? [],
	},
};

/**
 * Registers Amazon Pay "Express" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
registerPaymentMethod(amazonPayPaymentMethod);
