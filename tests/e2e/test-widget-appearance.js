/**
 * External dependencies
 */
import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import { By } from 'selenium-webdriver';
import test from 'selenium-webdriver/testing';

/**
 * Internal dependencies
 */
import * as t from './lib/test-helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

test.describe( 'Widget appearance', function() {
	this.timeout( config.get( 'mochaTimeoutMs' ) );

	test.before( function() {
		this.timeout( config.get( 'startBrowserTimeoutMs' ) );
	} );

	test.before( t.startBrowser );

	test.before( function() {
		t.setAmazonPaySettings( config.get( 'amazon' )[ 0 ] );
	} );

	test.describe( 'Cart login button display', () => {
		test.it( 'Button', () => {
			t.setAmazonPaySettings( { cartLoginButtonDisplay: 'Button' } );

			const guest = t.asGuestCustomer();
			guest.fromProductPathAddToCart( '/product/t-shirt' );
			guest
				.openCart()
				.loginWithAmazonButton(
					config.get( 'amazon' )[ 0 ].buyer.username,
					config.get( 'amazon' )[ 0 ].buyer.password
				);

			assert.eventually.ok( t.redirectedTo( '/checkout/?amazon_payments_advanced=true' ), 'redirected to /checkout/?amazon_payments_advanced=true' );
		} );

		test.it( 'Banner', () => {
			t.setAmazonPaySettings( { cartLoginButtonDisplay: 'Banner' } );

			const guest = t.asGuestCustomer();
			guest.fromProductPathAddToCart( '/product/t-shirt' );
			guest
				.openCart()
				.loginWithAmazonBanner(
					config.get( 'amazon' )[ 0 ].buyer.username,
					config.get( 'amazon' )[ 0 ].buyer.password
				);

			assert.eventually.ok( t.redirectedTo( '/checkout/?amazon_payments_advanced=true' ), 'redirected to /checkout/?amazon_payments_advanced=true' );
		} );

		test.it( 'Disabled', () => {
			t.setAmazonPaySettings( { cartLoginButtonDisplay: 'Disabled' } );

			const guest = t.asGuestCustomer();
			guest.fromProductPathAddToCart( '/product/t-shirt' );
			const cart = guest.openCart();

			assert.eventually.ok( cart.missingAmazonButton() );
			assert.eventually.ok( cart.missingAmazonBanner() );
		} );
	} );

	test.describe( 'Button options', () => {
		const options = [
			{
				type: 'Button with text Login with Amazon',
				size: 'Medium',
				color: 'Gold',
				language: 'Germany\'s German',
				expect: '/de/sandbox/prod/image/lwa/gold/medium/LwA.png'
			},
			{
				type: 'Button with text Amazon Pay',
				size: 'Large',
				color: 'Light gray',
				language: 'UK English',
				expect: '/en_gb/amazonpay/lightgray/large/button'
			},
			{
				type: 'Button with Amazon Pay logo',
				size: 'Small',
				color: 'Dark gray',
				language: 'France\'s French',
				expect: 'fr_fr/a/darkgray/small/button'
			}
		];
		const testDesc = option => {
			return Object.keys( option ).map( k => {
				return `${ k }: ${ option[ k ] }`;
			} ).join( ', ' );
		};

		options.forEach( option => {
			test.it( testDesc( option ), () => {
				t.setAmazonPaySettings( {
					cartLoginButtonDisplay: 'Button',
					buttonType: option.type,
					buttonSize: option.size,
					buttonColor: option.color,
					buttonLanguage: option.language
				} );

				const guest = t.asGuestCustomer();
				guest.fromProductPathAddToCart( '/product/t-shirt' );
				guest.openCart();

				assert.eventually.include(
					t.getAttribute( By.css( '#pay_with_amazon img' ), 'src' ),
					option.expect
				);
			} );
		} );
	} );

	test.after( t.quitBrowser );
} );
