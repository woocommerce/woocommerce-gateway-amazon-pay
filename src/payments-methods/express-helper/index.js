/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import React from 'react';

const { registerPaymentMethod } = wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from '../express/constants';
import { getBlocksConfiguration, Label, AmazonComponent } from '../../utils';
import { AmazonContent } from './payment-methods';

const settings = getBlocksConfiguration(PAYMENT_METHOD_NAME + '_data');
const label =
	decodeEntities(settings.title) ||
	__('Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced');

const AmazonExpressLabel = ( { label } ) => {

	return (
		<React.Fragment>
			{ label }
			{ ' - ' }
			<small style={{ fontSize: '90%' }}>
				{ __( 'You\'re logged in with your Amazon Account. ', 'woocommerce-gateway-amazon-payments-advanced' ) }
				<a href={ settings.logoutUrl } id="amazon-logout">{ __( 'Log out »', 'woocommerce-gateway-amazon-payments-advanced' ) }</a>
			</small>
		</React.Fragment>
	);
};
/**
 * Amazon Pay "Classic" payment method config object.
 */
const amazonPayPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <Label label={ <AmazonExpressLabel label={ label } /> } />,
	placeOrderButtonLabel: __( 'Proceed to Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
	content: <AmazonComponent RenderedComponent={ AmazonContent } />,
	edit: <AmazonComponent RenderedComponent={ AmazonContent } />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings?.supports ?? [],
	},
};

console.log( 'im loaded', amazonPayPaymentMethod );
/**
 * Registers Amazon Pay "Classic" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
registerPaymentMethod(amazonPayPaymentMethod);
