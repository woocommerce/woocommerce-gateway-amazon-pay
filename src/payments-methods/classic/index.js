/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useEffect, useState } from '@wordpress/element';

const { registerPaymentMethod } = wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { getBlocksConfiguration } from '../../utils';
import { AmazonContent } from './payment-methods';

const settings = getBlocksConfiguration( 'amazon_payments_advanced_data' );
const defaultLabel = __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' );
const label = decodeEntities( settings.title ) || defaultLabel;

/**
 * Returns a React Component.
 *
 * @param {object} param0  RenderedComponent and props
 * @returns {RenderedComponent}
 */
const AmazonComponent = ( { RenderedComponent, ...props } ) => {
	const [ errorMessage, setErrorMessage ] = useState( '' );

	useEffect( () => {
		if ( errorMessage ) {
			throw new Error( errorMessage );
		}
	}, [ errorMessage ] );

	return <RenderedComponent { ...props } />;
};

/**
 * Label component
 *
 * @param {object} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

/**
 * Amazon Pay "Classic" payment method config object.
 */
const amazonPayPaymentMethod = {
	name: PAYMENT_METHOD_NAME,
	label: <Label />,
	placeOrderButtonLabel: __( 'Proceed to Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
	content: <AmazonComponent RenderedComponent={ AmazonContent }/>,
	edit: <AmazonComponent RenderedComponent={ AmazonContent }/>,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings?.supports ?? [],
	},
};

/**
 * Registers Amazon Pay "Classic" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
registerPaymentMethod( amazonPayPaymentMethod );
