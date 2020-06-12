/**
 * External dependencies
 */
import config from 'config';
import { until } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { Helper as wcHelper, SingleProductPage, CheckoutOrderReceivedPage } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import CheckoutPage from './checkout-page';
import CustomerFlow from './customer-flow';
import GuestCustomerFlow from './guest-customer-flow';
import StoreOwnerFlow from './store-owner-flow';

let manager;
let driver;

let currentUser;

export const startBrowser = () => {
	manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );
	driver = manager.getDriver();
};

export const quitBrowser = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );
	manager.quitBrowser();
};

export const asStoreOwner = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );

	currentUser = new StoreOwnerFlow( driver, {
		baseUrl: config.get( 'url' ),
		username: config.get( 'users.shopmanager.username' ),
		password: config.get( 'users.shopmanager.password' )
	} );
	return currentUser;
};

export const asCustomer = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );

	currentUser = new CustomerFlow( driver, {
		baseUrl: config.get( 'url' ),
		username: config.get( 'users.customer.username' ),
		password: config.get( 'users.customer.password' )
	} );
	return currentUser;
};

export const asGuestCustomer = () => {
	helper.clearCookiesAndDeleteLocalStorage( driver );

	currentUser = new GuestCustomerFlow( driver, { baseUrl: config.get( 'url' ) } );
	return currentUser;
};

export const setAmazonPaySettings = setting => {
	const storeOwner = asStoreOwner();
	storeOwner.setAmazonPaySettings( setting );
	storeOwner.logout();
};

export const openOnePaymentProduct = () => {
	return new SingleProductPage( driver, {
		url: manager.getPageUrl( config.get( 'products.onePayment' ) )
	} );
};

export const openBillingAgreementProduct = () => {
	return new SingleProductPage( driver, {
		url: manager.getPageUrl( config.get( 'products.billingAgreement' ) )
	} );
};

export const payWithAmazon = ( amazonUsername, amazonPassword, paymentMethodNo, hasBillingAgreement = false ) => {
	const checkout = new CheckoutPage( driver, {
		url: manager.getPageUrl( '/checkout' )
	} );
	checkout.loginWithAmazon( amazonUsername, amazonPassword );

	const nextPageClicks = paymentMethodNo > 6 ? Math.floor( paymentMethodNo / 6 ) : 0;
	for ( let i = 0; i < nextPageClicks; i++ ) {
		checkout.nextPageAmazonPaymentMethod();
	}

	checkout.selectAmazonPaymentMethodNo( paymentMethodNo > 6 ? paymentMethodNo % 6 : paymentMethodNo );
	if ( hasBillingAgreement ) {
		checkout.setAuthorizeBillingAgreement();
	}
	checkout.placeOrder();

	wcHelper.waitTillUIBlockNotPresent( driver, 20000 );
};

export const redirectedTo = ( urlSubstr, timeout = 10000, msg = '' ) => {
	if ( ! msg ) {
		msg = `waiting to be redirected to URL that contains "${ urlSubstr }"`;
	}
	return driver.wait( until.urlContains( urlSubstr ), timeout, msg );
};

export const getAttribute = ( selector, attr ) => {
	return driver.findElement( selector ).getAttribute( attr );
};

export const checkoutHasText = text => {
	const checkout = new CheckoutPage( driver, {
		visit: false
	} );

	return checkout.hasText( text );
};

export const orderReceivedHasText = text => {
	const orderReceeived = new CheckoutOrderReceivedPage( driver, {
		visit: false
	} );

	return orderReceeived.hasText( text );
};

export const walletWidgetHasText = text => {
	const checkout = new CheckoutPage( driver, {
		visit: false
	} );

	return checkout.walletWidgetHasText( text );
};
