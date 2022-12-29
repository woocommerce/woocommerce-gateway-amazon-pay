/*global amazon_payments_advanced, amazon, wc_checkout_params */
( function( $ ) {
	$( function() {
		var button_count = 0;
		var button_id = '#pay_with_amazon';
		var classicButtonId = '#classic_pay_with_amazon';
		var amazonCreateCheckoutConfig = null;
		var amazonEstimatedOrderAmount = null;

		/* Handles 'classic' payment method on checkout. */
		$( 'form.checkout' ).on( 'checkout_place_order_success', function( e, result ) {
			if ( 'undefined' !== typeof result.amazonCreateCheckoutParams && $( classicButtonId ).length > 0 ) {
				amazonCreateCheckoutConfig = JSON.parse( result.amazonCreateCheckoutParams );
				amazonEstimatedOrderAmount = 'undefined' !== typeof result.amazonEstimatedOrderAmount && result.amazonEstimatedOrderAmount ? JSON.parse( result.amazonEstimatedOrderAmount ) : null;
				renderAndInitAmazonCheckout( classicButtonId, 'classic', amazonCreateCheckoutConfig );
				return true;
			}
			return true;
		} );

		/* Handles 'classic' payment method on order-pay. */
		$( 'form#order_review' ).on( 'submit', function( e ) {
			if ( isAmazonClassic() ) {
				e.preventDefault();
				var formData = new FormData( document.getElementById( 'order_review' ) );
				var urlSearchParams = new URLSearchParams( window.location.search );
				formData.append( 'amazon-classic-action', '1' );
				formData.append( 'key', urlSearchParams.get( 'key' ) );
				$.ajax(
					{
						type: 'post',
						data: formData,
						processData: false,
						contentType: false,
						success: function( result ) {
							unblock( $( 'form#order_review' ) );
							try {
								if ( 'success' === result.result && 'undefined' !== typeof result.amazonCreateCheckoutParams && $( classicButtonId ).length > 0 ) {
									amazonCreateCheckoutConfig = JSON.parse( result.amazonCreateCheckoutParams );
									amazonEstimatedOrderAmount = 'undefined' !== typeof result.amazonEstimatedOrderAmount && result.amazonEstimatedOrderAmount ? JSON.parse( result.amazonEstimatedOrderAmount ) : null;
									renderAndInitAmazonCheckout( classicButtonId, 'classic', amazonCreateCheckoutConfig );
								} else {
									throw 'Result failure';
								}
							} catch ( err ) {
								// Reload page
								if ( true === result.reload ) {
									window.location.reload();
									return;
								}

								// Trigger update in case we need a fresh nonce
								if ( true === result.refresh ) {
									$( document.body ).trigger( 'update_checkout' );
								}

								// Add new errors
								if ( result.messages ) {
									submit_error( $( 'form#order_review' ), result.messages );
								} else {
									submit_error( $( 'form#order_review' ), '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>' ); // eslint-disable-line max-len
								}
							}
						},
						error: function( jqXHR, textStatus, errorThrown ) {
							unblock( $( 'form#order_review' ) );
							console.error( errorThrown );
						}
					}
				);
			}
			return true;
		} );

		/* Handles Amazon Pay Button on Cart after a cart update. */
		$( document.body ).on( 'wc_fragments_loaded wc_fragments_refreshed', function( event ) {
			renderButton( '#pay_with_amazon_cart', 'cart' );
		} );

		/* Handles Amazon Pay Button on Cart during load. */
		if ( $( '#pay_with_amazon_cart' ).length > 0 ) {
			renderButton( '#pay_with_amazon_cart', 'cart' );
		}

		/* Handles Amazon Pay Button on Product Pages. */

		var amazonProductButtonContainer = $( '#pay_with_amazon_product' );

		if ( amazonProductButtonContainer.length > 0 ) {

			var amazonProductButton = renderButton( '#pay_with_amazon_product', 'product' );

			if ( null !== amazonProductButton ) {

				amazonProductButton.onClick( function() {
					var singleAddToCart = $( '.single_add_to_cart_button' );
					if ( singleAddToCart.hasClass( 'disabled' ) ) {
						singleAddToCart.trigger( 'click' );
						return;
					}
					var elementToBlock = amazonProductButtonContainer.closest( 'div.summary' ).length > 0 ? amazonProductButtonContainer.closest( 'div.summary' ) : false;
					if ( elementToBlock ) {
						block( elementToBlock );
					}

					var pid = singleAddToCart.val() || 0;

					var data = {
						action: amazon_payments_advanced.change_cart_action,
						_change_carts_nonce: amazon_payments_advanced.change_cart_ajax_nonce,
					};

					$.each( singleAddToCart.closest( 'form.cart' ).serializeArray(), function( index, object ) {
						if ( 'add-to-cart' === object.name ) {
							data.product_id = object.value;
						} else {
							data[ object.name ] = object.value;
						}
					} );

					data.quantity = data.quantity || 1;
					data.variation_id = data.variation_id || 0;
					data.product_id = data.product_id || pid;

					$.ajax(
						{
							url: amazon_payments_advanced.ajax_url,
							type: 'get',
							data: $.param( data ),
							success: function( result ) {
								if ( result.data.create_checkout_session_config ) {
									if ( result.data.estimated_order_amount ) {
										var productsEstimatedOrderAmount = JSON.parse( result.data.estimated_order_amount );
										if ( 'undefined' !== typeof productsEstimatedOrderAmount.amount && 'undefined' !== typeof productsEstimatedOrderAmount.currencyCode ) {
											amazonProductButton.updateButtonInfo( productsEstimatedOrderAmount );
										}
									}
									amazonProductButton.initCheckout( {
										createCheckoutSessionConfig: result.data.create_checkout_session_config
									} );
								}
							},
							error: function( jqXHR, textStatus, errorThrown ) {
								console.error( errorThrown );
							}
						}
					).always( function() {
						if ( elementToBlock ) {
							unblock( elementToBlock );
						}
					} );
				} );
			}
		}

		function submit_error( $element, errorMessage ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			$element.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorMessage + '</div>' ); // eslint-disable-line max-len
			$element.removeClass( 'processing' ).unblock();
		}

		function renderAndInitAmazonCheckout( buttonId, flag, checkoutConfig ) {
			var amazonClassicButton = renderButton( buttonId, flag );
			if ( null !== amazonClassicButton ) {
				amazonClassicButton.initCheckout( { createCheckoutSessionConfig: checkoutConfig } );
			}
		}

		function renderButton( buttonId, buttonSettingsFlag ) {
			buttonId = buttonId || button_id;

			/**
			 * On lines 216-219, renderButton is being declared as the callback to jQuery Events.
			 * As a result its being supplied with the callbacks variables.
			 * We make sure here, our variables are set to their defaults when that happens.
			 */
			buttonId = buttonId instanceof $.Event ? button_id : buttonId;
			buttonSettingsFlag = 'string' !== typeof buttonSettingsFlag ? null : buttonSettingsFlag;

			attemptRefreshData( buttonSettingsFlag );
			if ( 0 === $( buttonId ).length ) {
				return;
			}
			button_count++;
			var amazonPayButton = null;
			$( buttonId ).each( function() {
				var thisButton = $( this );
				var thisId = thisButton.attr( 'id' );
				if ( button_id === '#' + thisId && ! thisButton.is( ':visible' ) ) {
					return;
				}
				if ( typeof thisId === 'undefined' ) {
					thisId = 'pay_with_amazon_' + button_count;
					thisButton.attr( 'id', thisId );
				}
				var thisIdRaw = thisId;
				thisId = '#' + thisId;
				var separator_id = '.wc-apa-button-separator';
				var buttonSettings = getButtonSettings( buttonSettingsFlag );

				var thisConfigHash = amazon_payments_advanced.create_checkout_session_hash;
				var oldConfigHash = thisButton.data( 'amazonRenderedSettings' );
				if ( typeof oldConfigHash !== 'undefined' ) {
					if ( oldConfigHash === thisConfigHash ) {
						// Avoid re rendering
						return;
					}

					var newButton = $( '<' + thisButton.get( 0 ).tagName + '/>' ).attr( 'id', thisIdRaw );
					newButton.insertBefore( thisButton );
					thisButton.remove();
					thisButton = newButton;
				}
				thisButton.data( 'amazonRenderedSettings', thisConfigHash );

				amazonPayButton = amazon.Pay.renderButton( thisId, buttonSettings );
				thisButton.siblings( separator_id ).show();
			} );
			return amazonPayButton;
		}
		renderButton();
		$( document.body ).on( 'updated_wc_div', renderButton );
		$( document.body ).on( 'updated_checkout', renderButton );
		$( document.body ).on( 'payment_method_selected', renderButton );
		$( document.body ).on( 'updated_shipping_method', renderButton );

		function attemptRefreshData( flag ) {
			var dataContainer = 'cart' === flag ? $( '#wc-apa-update-vals-cart' ) : $( '#wc-apa-update-vals' );
			if ( ! dataContainer.length ) {
				return;
			}
			var data = dataContainer.data( 'value' );
			$.extend( amazon_payments_advanced, data );
			dataContainer.remove();
		}

		function activateChange( button_id, action ) {
			var $button = $( button_id );
			if ( 0 === $button.length || $button.data( 'wc_apa_chage_bind' ) === action ) {
				return;
			}
			$button.data( 'wc_apa_chage_bind', action );
			$button.on( 'click', function( e ) {
				e.preventDefault();
			} );
			amazon.Pay.bindChangeAction( button_id, {
				amazonCheckoutSessionId: amazon_payments_advanced.checkout_session_id,
				changeAction: action
			} );
		}

		function isAmazonCheckout() {
			return $( '#amazon-logout' ).length > 0 && ( 'amazon_payments_advanced' === $( 'input[name=payment_method]:checked' ).val() );
		}

		function isAmazonClassic() {
			return ! $( '.wc-apa-widget-change' ).length && ( 'amazon_payments_advanced' === $( 'input[name=payment_method]:checked' ).val() );
		}

		function getButtonSettings( buttonSettingsFlag ) {
			var obj = {
				// set checkout environment
				merchantId: amazon_payments_advanced.merchant_id,
				ledgerCurrency: amazon_payments_advanced.ledger_currency,
				sandbox: amazon_payments_advanced.sandbox === '1' ? true : false,
				// customize the buyer experience
				placement: amazon_payments_advanced.placement,
				buttonColor: amazon_payments_advanced.button_color,
				checkoutLanguage: amazon_payments_advanced.button_language !== '' ? amazon_payments_advanced.button_language.replace( '-', '_' ) : undefined,
				productType: amazon_payments_advanced.action,
			};
			if ( 'product' === buttonSettingsFlag ) {
				obj.productType = amazon_payments_advanced.product_action;
			} else if ( 'classic' === buttonSettingsFlag && null !== amazonCreateCheckoutConfig ) {
				obj.productType = 'undefined' !== typeof amazonCreateCheckoutConfig.payloadJSON.addressDetails ? 'PayAndShip' : 'PayOnly';
				amazonCreateCheckoutConfig.payloadJSON = JSON.stringify( amazonCreateCheckoutConfig.payloadJSON );

				if ( null !== amazonEstimatedOrderAmount && 'undefined' !== typeof amazonEstimatedOrderAmount.amount && 'undefined' !== typeof amazonEstimatedOrderAmount.currencyCode ) {
					obj.estimatedOrderAmount = amazonEstimatedOrderAmount;
				}
			} else {
				obj.createCheckoutSessionConfig = amazon_payments_advanced.create_checkout_session_config;
				obj.estimatedOrderAmount = amazon_payments_advanced.estimated_order_amount;
			}
			return obj;
		}

		function toggleDetailsVisibility( detailsListName ) {
			var visibleFields = $( '.' + detailsListName + '__field-wrapper' ).children().filter( function() {
				return $( this ).is( ':not(.hidden)' ) && $( this ).css( 'display' ) !== 'none';
			} );
			if ( visibleFields.length === 0 ) {
				$( '.' + detailsListName ).addClass( 'hidden' );
			} else {
				$( '.' + detailsListName ).removeClass( 'hidden' );
			}
		}

		function is_blocked( $node ) {
			return $node.is( '.processing' ) || $node.parents( '.processing' ).length;
		}

		function block( $node ) {
			if ( ! is_blocked( $node ) ) {
				$node.addClass( 'processing' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );
			}
		}

		function unblock( $node ) {
			$node.removeClass( 'processing' ).unblock();
		}

		function sendConfirmationCode( e ) {
			var $this = $( this );
			e.preventDefault();

			if ( $this.data( 'sending' ) ) {
				return;
			}

			$this.data( 'sending', true );

			var $thisArea = $this.parents('.create-account');

			block( $thisArea );

			$.ajax(
				{
					type: 'post',
					url: $this.prop( 'href' ),
					success: function( result ) {
						unblock( $thisArea );
						$this.data( 'sending', false );
						// TODO: Maybe display some feedback
					},
					error: function( jqXHR, textStatus, errorThrown ) {
						unblock( $thisArea );
						$this.data( 'sending', false );
						// TODO: Maybe display some feedback about what went wrong?
					}
				}
			);
		}

		function initAmazonPaymentFields() {
			if ( ! isAmazonCheckout() ) {
				return;
			}
			toggleDetailsVisibility( 'woocommerce-billing-fields' );
			toggleDetailsVisibility( 'woocommerce-shipping-fields' );
			toggleDetailsVisibility( 'woocommerce-additional-fields' );
			activateChange( '#payment_method_widget_change', 'changePayment' );
			activateChange( '#shipping_address_widget_change', 'changeAddress' );

			if ( $( '.woocommerce-billing-fields .woocommerce-billing-fields__field-wrapper > *' ).length > 0 ) {
				$( '.woocommerce-billing-fields' ).insertBefore( '#payment' );
			}

			if ( $( '.woocommerce-account-fields' ).length > 0 ) {
				$( '.woocommerce-account-fields' ).insertBefore( '#wc-apa-account-fields-anchor' );
			}

			if ( $( '.woocommerce-shipping-fields .woocommerce-shipping-fields__field-wrapper > *' ).length > 0 ) {
				var title = $( '#ship-to-different-address' );
				title.find( ':checkbox#ship-to-different-address-checkbox' ).hide();
				title.find( 'span' ).text( amazon_payments_advanced.shipping_title );
				$( '.woocommerce-shipping-fields' ).insertBefore( '#payment' );
			}
			$( '.woocommerce-additional-fields' ).insertBefore( '#payment' );

			$( '.wc-apa-send-confirm-ownership-code' ).on( 'click', sendConfirmationCode );
		}

		initAmazonPaymentFields();

		$( 'body' ).on( 'updated_checkout', initAmazonPaymentFields );
		$( 'body' ).on( 'payment_method_selected', initAmazonPaymentFields );
	} );
} )( jQuery );
