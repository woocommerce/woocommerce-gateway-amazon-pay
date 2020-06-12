/**
 * External dependencies
 */
import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';

/**
 * Internal dependencies
 */
import * as t from './lib/test-helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

test.describe( 'Checkout flow', function() {
	this.timeout( config.get( 'mochaTimeoutMs' ) );

	test.before( function() {
		this.timeout( config.get( 'startBrowserTimeoutMs' ) );
	} );

	test.before( t.startBrowser );

	test.describe( 'One-time payment', function() {
		config.get( 'amazon' ).forEach( amazonSetting => {
			test.before( () => {
				t.setAmazonPaySettings( amazonSetting );
			} );

			test.beforeEach( t.asGuestCustomer );

			test.it( amazonSetting.country + ' - valid', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithAmazon( amazonSetting.buyer.username, amazonSetting.buyer.password, 1 );

				assert.eventually.ok( t.redirectedTo( '/checkout/order-received/' ) );
			} );

			test.it( amazonSetting.country + ' - TransactionTimedOut', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithAmazon( amazonSetting.buyer.username, amazonSetting.buyer.password, 5 );

				assert.eventually.ok(
					t.checkoutHasText(
						'There was a problem with the selected payment method. ' +
						'Transaction was declined and order will be cancelled. ' +
						'You will be redirected to cart page automatically'
					),
					'see notice "There was a problem with the selected payment method..."'
				);
			} );

			test.it( amazonSetting.country + ' - PaymentMethodNotAllowed', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithAmazon( amazonSetting.buyer.username, amazonSetting.buyer.password, 6 );

				assert.eventually.ok(
					t.checkoutHasText(
						'Error: There has been a problem with the selected payment ' +
						'method from your Amazon account. Please update the payment ' +
						'method or choose another one.'
					),
					'see notice "Error: There has been a problem with the selected payment..."'
				);
			} );

			test.it( amazonSetting.country + ' - AmazonRejected', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithAmazon( amazonSetting.buyer.username, amazonSetting.buyer.password, 7 );

				assert.eventually.ok(
					t.checkoutHasText(
						'There was a problem with the selected payment method. ' +
						'Transaction was declined and order will be cancelled. ' +
						'You will be redirected to cart page automatically'
					),
					'see notice "Error: There has been a problem with the selected payment..."'
				);
			} );

			test.it( amazonSetting.country + ' - InvalidPaymentMethod', function() {
				t.openOnePaymentProduct().addToCart();
				t.payWithAmazon( amazonSetting.buyer.username, amazonSetting.buyer.password, 8 );

				assert.eventually.ok(
					t.checkoutHasText(
						'Error: The selected payment method was declined. ' +
						'Please try different payment method.'
					),
					'see notice "Error: The selected payment method was declined..."'
				);
				assert.eventually.ok(
					t.walletWidgetHasText( 'Verify card info or use another card' ),
					'Selected payment instrument displays "Verify card info or use another card"'
				);
			} );
		} );
	} );

	test.describe( 'Billing agreement', function() {
		config.get( 'amazon' ).forEach( amazonSetting => {
			test.before( function() {
				t.setAmazonPaySettings( amazonSetting );
			} );

			test.beforeEach( t.asCustomer );

			test.it( amazonSetting.country + ' - valid', function() {
				t.openBillingAgreementProduct().addToCart();
				t.payWithAmazon( amazonSetting.buyer.username, amazonSetting.buyer.password, 1, true );

				assert.eventually.ok(
					t.orderReceivedHasText( 'Order received' ),
					'see text "Order received"'
				);
				assert.eventually.ok(
					t.orderReceivedHasText( 'Your subscription will be activated when payment clears.' ),
					'see text "Your subscription will be activated when payment clears."'
				);
			} );
		} );
	} );

	test.after( t.quitBrowser );
} );
