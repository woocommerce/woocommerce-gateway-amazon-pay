/*global jQuery, window, document, setTimeout, console, amazon_payments_advanced, amazon */
( function( $ ) {
	$( function() {
		var button_settings = {
			// set checkout environment
			merchantId: amazon_payments_advanced.merchant_id,
			ledgerCurrency: 'EUR',
			sandbox: amazon_payments_advanced.sandbox === '1' ? true : false,
			// customize the buyer experience
			productType: amazon_payments_advanced.action,
			placement: amazon_payments_advanced.placement,
			buttonColor: amazon_payments_advanced.button_color,
			// configure Create Checkout Session request
			createCheckoutSessionConfig: amazon_payments_advanced.create_checkout_session_config
		};
		amazon.Pay.renderButton( '#pay_with_amazon', button_settings );
	} );
} )( jQuery );
