/*global jQuery, window, document, setTimeout, console, amazon_payments_advanced, amazon */
( function( $ ) {
	$( function() {
		function renderButton( button_id ) {
			if ( 0 === $( button_id ).length ) {
				return;
			}
			var separator_id = '.wc-apa-button-separator';
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
			amazon.Pay.renderButton( button_id, button_settings );
			$( button_id ).siblings( separator_id ).show();
		}
		renderButton( '#pay_with_amazon' );

		function isAmazonCheckout() {
			return ( 'amazon_payments_advanced' === $( 'input[name=payment_method]:checked' ).val() );
		}

		function toggleDetailsVisibility( detailsListName ) {
			if ( $( '.' + detailsListName + '__field-wrapper' ).children( ':not(.hidden)' ).length === 0 ) {
				$( '.' + detailsListName ).addClass( 'hidden' );
			} else {
				$( '.' + detailsListName ).removeClass( 'hidden' );
			}
		}

		toggleDetailsVisibility( 'woocommerce-billing-fields' );
		toggleDetailsVisibility( 'woocommerce-shipping-fields' );
		toggleDetailsVisibility( 'woocommerce-additional-fields' );

		function toggleFieldVisibility( type, key ) {
			var fieldWrapper = $( '#' + type + '_' + key + '_field' ),
				fieldValue = $( '#' + type + '_' + key ).val();
			fieldWrapper.addClass( 'hidden' );
			$( '.woocommerce-' + type + '-fields' ).addClass( 'hidden' );
			if ( fieldValue == null || fieldValue === '' ) {
				fieldWrapper.removeClass( 'hidden' );
				$( '.woocommerce-' + type + '-fields' ).removeClass( 'hidden' );
			}
		}

		$( 'body' ).on( 'updated_checkout', function() {
			toggleFieldVisibility( 'shipping', 'state' );
			if ( ! isAmazonCheckout() ) {
				return;
			}
			if ( $( '.woocommerce-billing-fields .woocommerce-billing-fields__field-wrapper > *' ).length > 0 ) {
				$( '.woocommerce-billing-fields' ).insertBefore( '#payment' );
			}
			if ( $( '.woocommerce-shipping-fields .woocommerce-shipping-fields__field-wrapper > *' ).length > 0 ) {
				var title = $( '#ship-to-different-address' );
				title.find( ':checkbox#ship-to-different-address-checkbox' ).hide();
				title.find( 'span' ).text( amazon_payments_advanced.shipping_title );
				$( '.woocommerce-shipping-fields' ).insertBefore( '#payment' );
			}
			$( '.woocommerce-additional-fields' ).insertBefore( '#payment' );
		} );
	} );
} )( jQuery );
