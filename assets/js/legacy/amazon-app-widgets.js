/*global jQuery, window, document, setTimeout, console, amazon_payments_advanced_params, amazon, OffAmazonPayments */
jQuery( function( $ ) {
	var referenceId, billingAgreementId, addressBookWidgetExists, buttonLoaded = false;
	var renderAddressBookWidget = amazon_payments_advanced_params.render_address_widget;
	var germanSpeakingCountries = [ 'AT', 'DE' ];

	/**
	 * Helper method for logging - don't want to cause an error trying to log an error!
	 */
	function logError() {
		if ( 'undefined' === typeof console.log ) {
			return;
		}

		console.log.apply( console, arguments );
	}

	function wcAmazonErrorToString( error ) {
		var message = '';

		if ( 'object' !== typeof error ) {
			return message;
		}

		if ( 'function' === typeof error.getErrorCode ) {
			message += '(' + error.getErrorCode() + ') ';
		}

		if ( 'function' === typeof error.getErrorMessage ) {
			message += error.getErrorMessage();
		}

		return message;
	}

	function isAmazonCheckout() {
		return ( 'amazon_payments_advanced' === $( 'input[name=payment_method]:checked' ).val() );
	}

	// Login with Amazon Widget.
	wcAmazonPaymentsButton();

	// AddressBook, Wallet, and maybe Recurring Payment Consent widgets.
	addressBookWidgetExists = ( $( '#amazon_addressbook_widget' ).length > 0 );
	if ( renderAddressBookWidget && addressBookWidgetExists ) {
		wcAmazonWidgets();
	} else {
		wcAmazonWalletWidget();
	}

	function wcAmazonOnPaymentSelect( orderReference ) {
		if ( orderReference && orderReference.getAmazonOrderReferenceId ) {
			referenceId = orderReference.getAmazonOrderReferenceId();
		}
		renderReferenceIdInput();
		// When wcAmazonOnPaymentSelect is set on walletConfig it overrides the address update handler
		// so it needs to be called from here.
		wcAmazonUpdateCheckoutAddresses();
	}

	function wcAmazonOnOrderReferenceCreate( orderReference ) {
		if ( referenceId ) {
			return;
		}

		referenceId = orderReference.getAmazonOrderReferenceId();
		renderReferenceIdInput();
	}

	function renderReferenceIdInput() {
		// Added the reference ID field.
		$( 'input.amazon-reference-id' ).remove();

		var referenceIdInput = '<input class="amazon-reference-id" type="hidden" name="amazon_reference_id" value="' + referenceId + '" />';

		$( 'form.checkout' ).append( referenceIdInput );
		$( 'form#order_review' ).append( referenceIdInput );
	}

	function wcAmazonOnBillingAgreementCreate( billingAgreement ) {
		if ( billingAgreementId ) {
			return;
		}

		billingAgreementId = billingAgreement.getAmazonBillingAgreementId();

		var billingAgreementIdInput = '<input class="amazon-billing-agreement-id" type="hidden" name="amazon_billing_agreement_id" value="' + billingAgreementId + '" />';

		$( 'form.checkout' ).append( billingAgreementIdInput );
		$( 'form#order_review' ).append( billingAgreementIdInput );
		$( '#amazon_consent_widget' ).show();
	}

	function editFormInputField( name, value ) {
		if ( $( '#' + name ).length ) {
			$( '#' + name ).val( value || '' ).change();
		}
	}

	function formatAmazonName( name ) {
		// Use fallback value for the last name to avoid field required errors.
		var lastNameFallback = '.';
		var names = name.split( ' ' );
		return {
			first_name: names.shift(),
			last_name: names.join( ' ' ) || lastNameFallback,
		};
	}

	/*
	 * Some address fields could have a string value of "undefined", causing issues
	 * when filling the form. This method removes any field with that value prior to
	 * formatting the addresses and filling the form.
	 */
	function removeUndefinedStrings( address ) {
		var fieldList = Object.keys( address );
		for ( var key in fieldList ) {
			var field = fieldList[ key ];
			if ( 'undefined' === address[ field ] ) {
				delete address[ field ];
			}
		}
	}

	function formatAmazonAddress( address ) {
		removeUndefinedStrings( address ); // Remove eventual "undefined" string values from properties.
		var name = formatAmazonName( address.Name );

		var formattedAddress = {
			first_name: name.first_name,
			last_name: name.last_name,
		};

		// Special handling for German speaking countries.
		//
		// @see https://github.com/woothemes/woocommerce-gateway-amazon-payments-advanced/issues/25
		if ( address.CountryCode && germanSpeakingCountries.includes( address.CountryCode ) ) {
			if ( address.AddressLine3 ) {
				var companyName = address.AddressLine1 + ' ' + address.AddressLine2;
				formattedAddress.company = companyName.trim();
				formattedAddress.address_1 = address.AddressLine3;
			} else if ( address.AddressLine2 ) {
				formattedAddress.company = address.AddressLine1;
				formattedAddress.address_1 = address.AddressLine2;
			} else {
				formattedAddress.address_1 = address.AddressLine1;
			}
		} else {
			var addressLines = [ address.AddressLine1, address.AddressLine2, address.AddressLine3 ].filter( function( a ) {
				return a;
			} );
			if ( 3 === addressLines.length ) {
				formattedAddress.company = addressLines[ 0 ];
				formattedAddress.address_1 = addressLines[ 1 ];
				formattedAddress.address_2 = addressLines[ 2 ];
			} else if ( 2 === addressLines.length ) {
				formattedAddress.address_1 = addressLines[ 0 ];
				formattedAddress.address_2 = addressLines[ 1 ];
			} else {
				formattedAddress.address_1 = addressLines[ 0 ];
			}
		}

		// country needs to be set prior to state due to form validation
		formattedAddress.country = address.CountryCode;
		formattedAddress.phone = address.Phone;
		formattedAddress.city = address.City;
		formattedAddress.postcode = address.PostalCode;
		formattedAddress.state = address.StateOrRegion;

		return formattedAddress;
	}

	function setOrderAddress( prefix, address ) {
		var fieldList = Object.keys( address );
		for ( var key in fieldList ) {
			var field = fieldList[ key ];
			editFormInputField( prefix + field, address[ field ] );
		}
	}

	function clearOrderFormData() {
		var fieldsToBeCleared = [
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_phone',
			'billing_city',
			'billing_postcode',
			'billing_state',
			'billing_country',
			'billing_email',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_phone',
			'shipping_city',
			'shipping_postcode',
			'shipping_state',
			'shipping_country',
		];
		for ( var field in fieldsToBeCleared ) {
			editFormInputField( fieldsToBeCleared[ field ], '' );
		}
	}

	function setOrderFormData( orderReference ) {
		clearOrderFormData();
		var hasShippingAddress = orderReference.Destination && orderReference.Destination.PhysicalDestination;
		var hasBillingAddress = orderReference.BillingAddress && orderReference.BillingAddress.PhysicalAddress;

		var shippingAddress = ( hasShippingAddress ) ? formatAmazonAddress( orderReference.Destination.PhysicalDestination ) : {};
		var billingAddress = null;

		if ( hasBillingAddress ) {
			billingAddress = formatAmazonAddress( orderReference.BillingAddress.PhysicalAddress );
		} else {
			var buyerName = formatAmazonName( orderReference.Buyer.Name );
			billingAddress = Object.assign( {}, shippingAddress );

			billingAddress.first_name = buyerName.first_name;
			billingAddress.last_name = buyerName.last_name;
		}

		billingAddress.email = orderReference.Buyer.Email;
		billingAddress.phone = billingAddress.phone || orderReference.Buyer.Phone;

		setOrderAddress( 'billing_', billingAddress );
		if ( hasShippingAddress ) {
			setOrderAddress( 'shipping_', shippingAddress );
		}
	}

	function wcAmazonUpdateCheckoutAddresses() {
		blockCheckoutForm();
		$.ajax(
			{
				type: 'post',
				url: amazon_payments_advanced_params.ajax_url,
				data: {
					action: 'amazon_get_order_reference',
					nonce: amazon_payments_advanced_params.order_reference_nonce,
					order_reference_id: referenceId,
				},
				success: function( orderReference ) {
					setOrderFormData( orderReference );
					unblockCheckoutForm();
					$( 'body' ).trigger( 'update_checkout' );
				},
				error: function( jqXHR, textStatus, errorThrown ) {
					logError( 'Error encountered in amazon_get_order_reference:', jqXHR.responseJSON || errorThrown );
					unblockCheckoutForm();
				}
			}
		);
	}

	function wcAmazonPaymentsButton() {
		if ( buttonLoaded ) {
			return;
		}

		if ( 0 !== $( '#pay_with_amazon' ).length && 0 === $( '.amazonpay-button-inner-image' ).length ) {
			var popup = true;
			if ( 'optimal' === amazon_payments_advanced_params.redirect_authentication && isSmallScreen() ) {
				popup = false;
			}

			var buttonWidgetParams = {
				type: amazon_payments_advanced_params.button_type,
				color: amazon_payments_advanced_params.button_color,
				size: amazon_payments_advanced_params.button_size,

				authorization: function() {
					var loginOptions = {
						scope: 'profile postal_code payments:widget payments:shipping_address payments:billing_address',
						popup: popup,
					};
					amazon.Login.authorize( loginOptions, amazon_payments_advanced_params.redirect );
				},
				onError: function( error ) {
					var msg = wcAmazonErrorToString( error );

					logError( 'Error encountered in OffAmazonPayments.Button', msg ? ': ' + msg : '' );
				}
			};

			if ( '' !== amazon_payments_advanced_params.button_language ) {
				buttonWidgetParams.language = amazon_payments_advanced_params.button_language;
			}

			OffAmazonPayments.Button( 'pay_with_amazon', amazon_payments_advanced_params.seller_id, buttonWidgetParams );
			buttonLoaded = true;
		}
	}

	function isSmallScreen() {
		return window.innerWidth <= 800;
	}

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

	function blockCheckoutForm( form ) {
		form = ( form ) ? form : $( 'form.checkout' );
		form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		} );
	}

	function unblockCheckoutForm( form ) {
		form = ( form ) ? form : $( 'form.checkout' );
		form.removeClass( 'processing' ).unblock();
	}

	function wcAmazonWidgets() {
		var addressBookConfig = {
			sellerId: amazon_payments_advanced_params.seller_id,
			onReady: function() {
				wcAmazonWalletWidget();
				$( document ).trigger( 'wc_amazon_pa_widget_ready' );
			},
			onOrderReferenceCreate: wcAmazonOnOrderReferenceCreate,
			design: {
				designMode: 'responsive'
			},
			onError: function( error ) {
				var msg = wcAmazonErrorToString( error );
				logError( 'Error encountered in OffAmazonPayments.Widgets.AddressBook', msg ? ': ' + msg : '' );
			}
		};
		var isRecurring = amazon_payments_advanced_params.is_recurring;
		var declinedCode = amazon_payments_advanced_params.declined_code;

		// When declinedCode is 'AmazonRejected' rendering AddressBook may throw
		// an error 'UnknownError: Unknown error in the service', so it's best
		// to bail out instantiating the widget and redirect asap.
		if ( 'AmazonRejected' === declinedCode ) {
			return wcAmazonMaybeRedirectAfterDeclined();
		}

		if ( isRecurring ) {
			addressBookConfig.agreementType = 'BillingAgreement';

			addressBookConfig.onReady = function( billingAgreement ) {
				wcAmazonOnBillingAgreementCreate( billingAgreement );
				wcAmazonWalletWidget();
				wcAmazonConsentWidget();
				$( document ).trigger( 'wc_amazon_pa_widget_ready' );
			};
		}

		if ( declinedCode ) {
			addressBookConfig.displayMode = 'Read';
			addressBookConfig.amazonOrderReferenceId = amazon_payments_advanced_params.reference_id;

			delete addressBookConfig.onOrderReferenceCreate;
		}

		new OffAmazonPayments.Widgets.AddressBook( addressBookConfig ).bind( 'amazon_addressbook_widget' );
	}

	// To be used on some multicurrency solutions, that change currency without reloading the checkout, then we need to rebind the widget with proper currency.
	if ( amazon_payments_advanced_params.multi_currency_supported && amazon_payments_advanced_params.multi_currency_reload_wallet ) {
		$( document.body ).on(
			'updated_checkout',
			function() {
				$.ajax(
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
		// If previously declined with redirection to cart, do not render the
		// wallet widget.
		if ( amazon_payments_advanced_params.declined_redirect_url ) {
			return;
		}

		// Only instantiate walletWidget once
		if ( ! walletWidget ) {
			var walletConfig = {
				sellerId: amazon_payments_advanced_params.seller_id,
				design: {
					designMode: 'responsive'
				},
				// Update the checkout addresses when a shipping address is selected or when payment method changes.
				// Since onPaymentSelect is fired on both actions, we're not using onAddressSelect to avoid fetching the
				// same data twice.
				onPaymentSelect: wcAmazonUpdateCheckoutAddresses,
				onError: function( error ) {
					var msg = wcAmazonErrorToString( error );
					logError( 'Error encountered in OffAmazonPayments.Widgets.Wallet', msg ? ': ' + msg : '' );
				}
			};

			if ( amazon_payments_advanced_params.reference_id ) {
				referenceId = amazon_payments_advanced_params.reference_id;
				walletConfig.amazonOrderReferenceId = referenceId;
				walletConfig.onPaymentSelect = wcAmazonOnPaymentSelect;
			}

			if ( ! renderAddressBookWidget || ! addressBookWidgetExists ) {
				walletConfig.onOrderReferenceCreate = wcAmazonOnOrderReferenceCreate;
			}

			if ( amazon_payments_advanced_params.is_recurring ) {
				walletConfig.agreementType = 'BillingAgreement';

				if ( billingAgreementId ) {
					walletConfig.amazonBillingAgreementId = billingAgreementId;
				} else {
					walletConfig.onReady = function( billingAgreement ) {
						wcAmazonOnBillingAgreementCreate( billingAgreement );
						wcAmazonConsentWidget();
					};
				}
			}
			walletWidget = new OffAmazonPayments.Widgets.Wallet( walletConfig );
		}

		if ( amazon_payments_advanced_params.multi_currency_supported ) {
			var currency = ( force_currency ) ? force_currency : amazon_payments_advanced_params.current_currency;
			walletWidget.setPresentmentCurrency( currency );
		}
		walletWidget.bind( 'amazon_wallet_widget' );
	}

	// Recurring payment consent widget
	function wcAmazonConsentWidget() {
		if ( ! amazon_payments_advanced_params.is_recurring || ! billingAgreementId ) {
			return;
		}

		new OffAmazonPayments.Widgets.Consent( {
			sellerId: amazon_payments_advanced_params.seller_id,
			amazonBillingAgreementId: billingAgreementId,
			design: {
				designMode: 'responsive'
			},
			onReady: toggleAbleCheckoutButton,
			onConsent: toggleAbleCheckoutButton,
			onError: function( error ) {
				var msg = wcAmazonErrorToString( error );
				logError( 'Error encountered in OffAmazonPayments.Widgets.Consent', msg ? ': ' + msg : '' );
			}
		} ).bind( 'amazon_consent_widget' );
	}

	function toggleAbleCheckoutButton( billingAgreementConsentStatus ) {
		var buyerBillingAgreementConsentStatus = billingAgreementConsentStatus.getConsentStatus();
		if ( buyerBillingAgreementConsentStatus !== 'undefined' ) {
			/* eslint-disable eqeqeq */
			$( '#place_order' ).css( 'opacity', ( 'true' == buyerBillingAgreementConsentStatus ) ? 1 : 0.5 );
			$( '#place_order' ).prop( 'disabled', ( 'true' != buyerBillingAgreementConsentStatus ) );
			/* eslint-enable eqeqeq */
			return;
		}
		$( '#amazon_consent_widget' ).hide();
	}

	/**
	 * Maybe redirect to cart page when authorization is declined by Amazon.
	 *
	 * When authorization is declined, `amazon_payments_advanced_params.declined_redirect_url`
	 * will be set globally (by PHP). This func handles the scroll to the notice
	 * and the redirection once page is rendered.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/214
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/247
	 */
	function wcAmazonMaybeRedirectAfterDeclined() {
		if ( amazon_payments_advanced_params.declined_redirect_url ) {
			// Scroll to top so customer notices the error message before being
			// redirected to cancel order URL.
			$( 'body' ).on( 'updated_checkout', function() {
				$( 'html, body' ).scrollTop( 0 );

				// Gives time for customer to read the notice. It's short notice
				// (plus confirmation about redirection) and similar message will
				// be displayed again in the cart page. So 3s should be sufficient.
				setTimeout( function() {
					window.location = amazon_payments_advanced_params.declined_redirect_url;
				}, 3000 );
			} );
		}
	}

	$( 'body' ).on( 'click', '#amazon-logout', function() {
		amazon.Login.logout();
		document.cookie = 'amazon_Login_accessToken=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		window.location = amazon_payments_advanced_params.redirect_url;
	} );

	/**
	 *
	 * The AJAX order review refresh causes some duplicate form fields to be created.
	 * If we're checking out with Amazon enabled and creating a new account, disable the duplicate fields
	 * that don't have values so we don't overwrite the good values in $_POST
	 *
	 */
	$( 'form.checkout' ).on( 'checkout_place_order', function() {
		var fieldSelectors = [
			':input[name=billing_email]',
			':input[name=billing_first_name]',
			':input[name=billing_last_name]',
			':input[name=account_username]',
			':input[name=account_password]',
			':input[name=createaccount]'
		].join( ',' );

		$( this ).find( fieldSelectors ).each( function() {
			var $input = $( this );
			if ( '' === $input.val() && $input.is( ':hidden' ) ) {
				$input.attr( 'disabled', 'disabled' );
			}

			// For createaccount checkbox, the value on dupe element should
			// matches with visible createaccount checkbox.
			if ( 'createaccount' === $input.attr( 'name' ) && $( '#createaccount' ).length ) {
				$input.prop( 'checked', $( '#createaccount' ).is( ':checked' ) );
			}
		} );
	} );
	$( 'body' ).on( 'updated_checkout', wcAmazonConsentWidget );
	$( 'body' ).on( 'updated_checkout', wcAmazonPaymentsButton );
	$( 'body' ).on( 'updated_cart_totals', function() {
		buttonLoaded = false;
		wcAmazonPaymentsButton();
	} );

	$( window.document ).on( 'wc_amazon_pa_widget_ready', function() {
		wcAmazonMaybeRedirectAfterDeclined();
	} );

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
				referenceId,
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
		blockCheckoutForm( $form );

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

	$( 'body' ).on( 'updated_checkout', function() {
		if ( ! isAmazonCheckout() ) {
			return;
		}
		toggleFieldVisibility( 'shipping', 'state' );
		if ( $( '.woocommerce-billing-fields .woocommerce-billing-fields__field-wrapper > *' ).length > 0 ) {
			$( '.woocommerce-billing-fields' ).insertBefore( '#payment' );
		}
		if ( $( '.woocommerce-shipping-fields .woocommerce-shipping-fields__field-wrapper > *' ).length > 0 ) {
			var title = $( '#ship-to-different-address' );
			title.find( ':checkbox#ship-to-different-address-checkbox' ).hide();
			title.find( 'span' ).text( amazon_payments_advanced_params.shipping_title );
			$( '.woocommerce-shipping-fields' ).insertBefore( '#payment' );
		}
		$( '.woocommerce-additional-fields' ).insertBefore( '#payment' );
	} );
} );
