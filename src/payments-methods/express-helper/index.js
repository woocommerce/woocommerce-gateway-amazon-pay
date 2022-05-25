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
import { getBlocksConfiguration, AmazonComponent } from '../../utils';
import { AmazonContent, AmazonExpressLabel } from './payment-methods';

const settings = getBlocksConfiguration(PAYMENT_METHOD_NAME + '_data');
const label =
	decodeEntities(settings.title) ||
	__('Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced');

// Unsets all other Gateways.
if ( settings.allOtherGateways ) {
	let hideAllOtherPaymentGateways = {};
	for ( const offset in settings.allOtherGateways ) {
		hideAllOtherPaymentGateways[ settings.allOtherGateways[ offset ] ] = () => { return false; };
	}
	registerPaymentMethodExtensionCallbacks( 'amazon_payments_advanced', hideAllOtherPaymentGateways );
}


/**
 * Amazon Pay "Classic" payment method config object.
 */
const amazonPayPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <AmazonExpressLabel label={ label } />,
	placeOrderButtonLabel: __( 'Proceed to Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
	content: <AmazonComponent RenderedComponent={ AmazonContent } />,
	edit: <AmazonComponent RenderedComponent={ AmazonContent } />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings?.supports ?? [],
	},
};

/**
 * Registers Amazon Pay "Classic" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
registerPaymentMethod(amazonPayPaymentMethod);
