/*global amazon_payments_advanced, amazon, wc_checkout_params */
( function( $ ) {
	$( function() {
		var button_count = 0;
		var button_id = '#pay_with_amazon';
		var classic_button_id = '#classic_pay_with_amazon';
		var amzCreateCheckoutConfig = null;
		$( 'form.checkout' ).on( 'checkout_place_order_success', function( e, result ) {
			if ( 'undefined' !== typeof result.amzCreateCheckoutParams && $( classic_button_id ).length > 0 ) {
				amzCreateCheckoutConfig = result.amzCreateCheckoutParams;
				renderButton( classic_button_id, 'classic' );
				$( classic_button_id ).trigger( 'click' );
				return true;
			}
			return true;
		} );
		$( document.body ).on( 'added_to_cart removed_from_cart', function( fragments, cartHash, clickedBtn ) {
			renderButton( '#pay_with_amazon_cart' );
		} );
		if ( $( '#pay_with_amazon_cart' ).length > 0 ) {
			renderButton( '#pay_with_amazon_cart' );
		}
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
								if ( 'success' === result.result && 'undefined' !== typeof result.amzCreateCheckoutParams && $( classic_button_id ).length > 0 ) {
									amzCreateCheckoutConfig = result.amzCreateCheckoutParams;
									renderButton( classic_button_id, 'classic' );
									$( classic_button_id ).trigger( 'click' );
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
						error:	function( jqXHR, textStatus, errorThrown ) {
							unblock( $( 'form#order_review' ) );
							console.error( error );
						}
					}
				);
			}
			return true;
		} );

		function submit_error( $elem, error_message ) {
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			$elem.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len
			$elem.removeClass( 'processing' ).unblock();
		}

		function renderButton( btnId, buttonSettingsFlag ) {
			btnId = btnId || button_id;
			attemptRefreshData();
			if ( 0 === $( btnId ).length ) {
				return;
			}
			button_count++;
			$( btnId ).each( function() {
				var thisButton = $( this );
				var thisId = thisButton.attr( 'id' );
				if ( ! thisButton.is( ':visible' ) && classic_button_id !== '#' + thisId ) {
					return;
				}
				if ( typeof thisId === 'undefined' ) {
					thisId = 'pay_with_amazon_' + button_count;
					thisButton.attr( 'id', thisId );
				}
				var thisIdRaw = thisId;
				thisId = '#' + thisId;
				var separator_id = '.wc-apa-button-separator';
				var button_settings = getButtonSettings( buttonSettingsFlag );

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

				amazon.Pay.renderButton( thisId, button_settings );
				thisButton.siblings( separator_id ).show();
			} );
		}
		renderButton();
		$( document.body ).on( 'updated_wc_div', renderButton );
		$( document.body ).on( 'updated_checkout', renderButton );
		$( document.body ).on( 'payment_method_selected', renderButton );
		$( document.body ).on( 'updated_shipping_method', renderButton );

		function attemptRefreshData() {
			var dataCont = $( '#wc-apa-update-vals' );
			if ( ! dataCont.length ) {
				return;
			}
			var data = dataCont.data( 'value' );
			$.extend( amazon_payments_advanced, data );
			dataCont.remove();
		}

		function activateChange( button_id, action ) {
			var $btn = $( button_id );
			if ( 0 === $btn.length || $btn.data( 'wc_apa_chage_bind' ) === action ) {
				return;
			}
			$btn.data( 'wc_apa_chage_bind', action );
			$btn.on( 'click', function( e ) {
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
				productType: amazon_payments_advanced.action,
				placement: amazon_payments_advanced.placement,
				buttonColor: amazon_payments_advanced.button_color,
				checkoutLanguage: amazon_payments_advanced.button_language !== '' ? amazon_payments_advanced.button_language.replace( '-', '_' ) : undefined,
				// configure Create Checkout Session request
				createCheckoutSessionConfig: amazon_payments_advanced.create_checkout_session_config
			};
			if ( 'classic' === buttonSettingsFlag && null !== amzCreateCheckoutConfig ) {
				obj.productType = 'undefined' !== typeof amzCreateCheckoutConfig.payloadJSON.addressDetails ? 'PayAndShip' : 'PayOnly';
				amzCreateCheckoutConfig.payloadJSON = JSON.stringify( amzCreateCheckoutConfig.payloadJSON );
				obj.createCheckoutSessionConfig = amzCreateCheckoutConfig;
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
					error:	function( jqXHR, textStatus, errorThrown ) {
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
