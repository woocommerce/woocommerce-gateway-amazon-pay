/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { settings } from './_settings';
import { activateChange } from '../../_renderAmazonButton';

/**
 * The change Shipping Address Component.
 *
 * @returns React component
 */
const ChangeShippingAddress = () => {
    useEffect( () => {
        activateChange( 'amazon_change_shipping_address', 'changeAddress' );
    }, [] );

    return (
        <a href="#" className="wc-apa-widget-change" id="amazon_change_shipping_address">
            { __( 'Change', 'woocommerce-gateway-amazon-payments-advanced' ) }
        </a>
    );
};

/**
 * The logout Banner component.
 *
 * @returns React component
 */
const LogOutBanner = () => {
    return (
        <div className="woocommerce-info info amazon-pay-first-order">
            { decodeEntities( settings.logoutMessage ) } { " " }
            <a href={ settings.logoutUrl }>
                { decodeEntities( __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) ) }
            </a>
        </div>
    );
};

export const changeShippingAddressOptions = {
    metadata: {
        name: 'amazon-payments-advanced/change-address',
        parent: [ 'woocommerce/checkout-shipping-address-block' ],
    },
    component: () => <ChangeShippingAddress />,
};

export const logOutBannerOptions = {
    metadata: {
        name: 'amazon-payments-advanced/log-out-banner',
        parent: [ 'woocommerce/checkout-fields-block' ],
    },
    component: () => <LogOutBanner />,
};
