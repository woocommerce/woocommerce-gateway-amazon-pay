/*global amazon_payments_advanced_params, OffAmazonPayments */
jQuery( function( $ ) {
	// Make sure we only try to load the Amazon widgets once
	var widgetsLoaded = false, amazonOrderReferenceId;

	// Login Widget
	function wcAmazonPaymentsButton() {
		// if button skeleton element is not on the page or if button has already been created within the skeleton element
		if ( 0 === $( '#pay_with_amazon' ).length || $( '#pay_with_amazon' ).html().length > 0 ) {
			return;
		}

		new OffAmazonPayments.Widgets.Button( {
			sellerId: amazon_payments_advanced_params.seller_id,
			useAmazonAddressBook: amazon_payments_advanced_params.is_checkout_pay_page ? false : true,
			onSignIn: function( orderReference ) {
				amazonOrderReferenceId = orderReference.getAmazonOrderReferenceId();
				window.location = amazon_payments_advanced_params.redirect + '&amazon_reference_id=' + amazonOrderReferenceId;
			}
		} ).bind( 'pay_with_amazon' );
	}

	wcAmazonPaymentsButton();

	$( 'body' ).on( 'updated_shipping_method', wcAmazonPaymentsButton );

	function isAmazonCheckout() {
		return ( 'amazon_payments_advanced' === $( 'input[name=payment_method]:checked' ).val() );
	}

	$( 'body' ).on( 'updated_checkout', function() {
		if ( isAmazonCheckout() ) {
			loadWidgets();

			$( '#amazon_customer_details' ).show();
			$( '#customer_details :input' ).detach();
		}

		wcAmazonPaymentsButton();
	} );

	// Reload button on any AJAX requests that triggers updated cart totals.
	$( document.body ).on( 'updated_cart_totals', function() {
		wcAmazonPaymentsButton();
	} );

	/**
	 *
	 * The AJAX order review refresh causes some duplicate form fields to be created.
	 * If we're checking out with Amazon enabled and creating a new account, disable the duplicate fields
	 * that don't have values so we don't overwrite the good values in $_POST
	 *
	 */
	$( 'form.checkout' ).on( 'checkout_place_order', function() {
		if ( ! $( ':checkbox[name=createaccount]' ).is( ':checked' ) ) {
			return;
		}

		$( this ).find( ':input[name=billing_email],:input[name=account_password]' ).each( function() {
			var $input = $( this );
			if ( '' === $input.val() && $input.is( ':hidden' ) ) {
				$input.attr( 'disabled', 'disabled' );
			}
		} );
	} );

	// Addressbook widget
	function wcAmazonAddressBookWidget() {
		new OffAmazonPayments.Widgets.AddressBook( {
			sellerId: amazon_payments_advanced_params.seller_id,
			amazonOrderReferenceId: amazonOrderReferenceId || amazon_payments_advanced_params.reference_id,
			onAddressSelect: function( orderReference ) {
				$( 'body' ).trigger( 'update_checkout' );
			},
			design: {
				designMode: 'responsive'
			}
		} ).bind( 'amazon_addressbook_widget' );
	}

	// To be used on some multicurrency solutions, that change currency without reloading the checkout, then we need to rebind the widget with proper currency.
	if ( amazon_payments_advanced_params.multi_currency_supported && amazon_payments_advanced_params.multi_currency_reload_wallet ) {
		$( document.body ).on(
			'updated_checkout', function() {
				jQuery.ajax(
					{
						url: amazon_payments_advanced_params.ajax_url,
						type: 'post',
						data: {
							action: 'amazon_get_currency',
							nonce: amazon_payments_advanced_params.multi_currency_nonce,
						},
						success: function( response ) {
							wcAmazonWalletWidget( response );
						}
					}
				);
			}
		);
	}

	// Holds walletWidget instance.
	var walletWidget = null;

	// Wallet widget
	function wcAmazonWalletWidget( force_currency ) {
		if ( ! walletWidget ) {
			walletWidget = new OffAmazonPayments.Widgets.Wallet(
				{
					sellerId: amazon_payments_advanced_params.seller_id,
					amazonOrderReferenceId: amazonOrderReferenceId || amazon_payments_advanced_params.reference_id,
					design: {
						designMode: 'responsive'
					}
				}
			);
		}

		if ( amazon_payments_advanced_params.multi_currency_supported ) {
			var currency = ( force_currency ) ? force_currency : amazon_payments_advanced_params.current_currency;
			walletWidget.setPresentmentCurrency( currency );
		}
		walletWidget.bind( 'amazon_wallet_widget' );
	}

	// Helper method to load widgets and limit to a single instantiation
	function loadWidgets() {
		if ( widgetsLoaded ) {
			return;
		}

		wcAmazonAddressBookWidget();
		wcAmazonWalletWidget();

		widgetsLoaded = true;

		// Not exactly widgets ready, but no onReady param for standard widgets.
		// Use the same name with app widgets for consistency.
		wcAmazonMaybeTriggerReadyEvent();
	}

	// Only load widgets on the initial render if Amazon Pay is the chosen method
	if ( isAmazonCheckout() ) {
		loadWidgets();
	}

	/**
	 * Maybe trigger wc_amazon_pa_widget_ready.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver
	 */
	function wcAmazonMaybeTriggerReadyEvent() {
		if ( 'function' !== typeof MutationObserver ) {
			return false;
		}

		var triggered = false;
		var observer = new MutationObserver( function( mutations ) {
			mutations.forEach( function( mutation ) {
				if ( ! triggered ) {
					$( document ).trigger( 'wc_amazon_pa_widget_ready' );
					triggered = true;
					observer.disconnect();
				}
			} );
		} );

		observer.observe( document.getElementById( 'amazon_wallet_widget' ), {
			childList: true
		} );
	}

	// Hijack form checkout button to trigger SCA if needed.
	$( 'form.checkout' ).on(
		'checkout_place_order',
		function( evt ) {
			// Check if Amazon is selected
			if ( ! isAmazonCheckout() ) {
				return true;
			}

			// On SCA implementations (EU regions)
			if ( ! amazon_payments_advanced_params.is_sca ) {
				return true;
			}

			var $form = $( this );
			OffAmazonPayments.initConfirmationFlow(
				amazon_payments_advanced_params.seller_id,
				amazonOrderReferenceId || amazon_payments_advanced_params.reference_id,
				function( amazonPayFlow ) {
					placeOrder( amazonPayFlow, $form );
				}
			);

			evt.preventDefault();
			return false;
		}
	);

	/**
	 *
	 * @param amazonPayFlow
	 * @param $form
	 */
	function placeOrder( amazonPayFlow, $form ) {
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		} );

		$.ajax(
			{
				type: 'post',
				url: amazon_payments_advanced_params.ajax_url,
				data: {
					action: 'amazon_sca_processing',
					nonce: amazon_payments_advanced_params.sca_nonce,
					data: $( 'form.checkout' ).serialize(),
				},
				success: function( result ) {
					if ( 'success' === result.result ) {
						amazonPayFlow.success();
					} else if ( 'failure' === result.result ) {
						amazonPayFlow.error();
						// Reload page
						if ( true === result.reload ) {
							window.location.reload();
							return;
						}
						// Trigger update in case we need a fresh nonce
						if ( true === result.refresh ) {
							$( document.body ).trigger( 'update_checkout' );
						}

						submit_error( result.messages, $form );
					}
				},
				error:	function( jqXHR, textStatus, errorThrown ) {
					amazonPayFlow.error();
					submit_error( '<div class="woocommerce-error">' + errorThrown + '</div>', $form );
				}
			}
		);
	}

	/**
	 * Submit error to checkout page coming from AJAX call.
	 *
	 * @param error_message
	 * @param $form
	 */
	function submit_error( error_message, $form ) {
		$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
		$form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
		$form.removeClass( 'processing' ).unblock();
		$form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
		scroll_to_notices();
		$( document.body ).trigger( 'checkout_error' );
	}

	/**
	 * Scroll to errors.
	 */
	function scroll_to_notices() {
		var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );

		if ( ! scrollElement.length ) {
			scrollElement = $( '.form.checkout' );
		}
		$.scroll_to_notices( scrollElement );
	}
} );
