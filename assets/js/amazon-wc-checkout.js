/*global jQuery, window, document, setTimeout, console, amazon_payments_advanced, amazon */
( function( $ ) {
	$( function() {
		var button_settings = {
			// set checkout environment
			merchantId: amazon_payments_advanced.merchant_id,
			ledgerCurrency: 'EUR',
			sandbox: true,
			// customize the buyer experience
			checkoutLanguage: 'es_ES',
			productType: 'PayAndShip',
			placement: 'Cart',
			buttonColor: 'Gold',
			// configure Create Checkout Session request
			createCheckoutSessionConfig: amazon_payments_advanced.create_checkout_session_config
		};
		amazon.Pay.renderButton( '#pay_with_amazon', button_settings );
	} );
} )( jQuery );
