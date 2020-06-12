(function($) {

	/**
	 * If keys are set, hide the box, on label click, show the box.
	 */
	var wc_json_key_handler = {
		key_box_id: 'woocommerce_amazon_payments_advanced_keys_json',
		are_keys_set: amazon_admin_params.keys_already_set,
		init: function() {
			if ( this.are_keys_set ) {
				wc_json_key_handler.hide_key_box();
			}
			$( 'label[for=' + this.key_box_id + ']' ).click(
				function() {
					wc_json_key_handler.show_key_box();
				}
			);
		},
		hide_key_box: function() {
			$( '#' + this.key_box_id ).hide();
		},
		show_key_box: function() {
			$( '#' + this.key_box_id ).show();
		},

	};
	wc_json_key_handler.init();

	var wc_simple_path_form = {
		simple_path_form_id : 'simple_path',
		payment_region_input : $( '#woocommerce_amazon_payments_advanced_payment_region' ),
		json_key_input : $( '#woocommerce_amazon_payments_advanced_keys_json' ),
		action_url : '#',
		spId : '',
		register_now_link : $( 'a.register_now' ),
		locale: amazon_admin_params.locale,
		home_url: amazon_admin_params.home_url,
		merchant_unique_id: amazon_admin_params.merchant_unique_id,
		public_key: amazon_admin_params.public_key,
		exchange_url: amazon_admin_params.simple_path_url,
		privacy_url: amazon_admin_params.privacy_url,
		site_description: amazon_admin_params.description,
		login_redirect_url: amazon_admin_params.login_redirect_url,
		poll_timer: false,
		poll_interval: 3000,
		init: function() {
			// Init values if region is already selected
			wc_simple_path_form.payment_region_on_change();

			wc_simple_path_form.create_form();
			wc_simple_path_form.payment_region_input.on( 'change', this.payment_region_on_change );
			wc_simple_path_form.json_key_input.on( 'change', this.json_key_on_change );
			wc_simple_path_form.register_now_link.on( 'click', this.register_link_on_click );
		},
		payment_region_on_change: function() {
			if ( 'jp' === wc_simple_path_form.get_region_selected() ) {
				// JP does not have Simple Path Registration, we open a new url for it.
				wc_simple_path_form.register_now_link.attr( 'href', wc_simple_path_form.get_simple_path_url() );
				wc_simple_path_form.register_now_link.attr( 'target', '_blank' );
			} else {
				// For any other region, we use Simple Path form.
				wc_simple_path_form.register_now_link.attr( 'href', '#' );
				wc_simple_path_form.register_now_link.removeAttr( 'target' );

				// Updating values every time the region is changed.
				wc_simple_path_form.action_url = wc_simple_path_form.get_simple_path_url();
				wc_simple_path_form.spId       = wc_simple_path_form.get_spId();

				$( '#' + wc_simple_path_form.simple_path_form_id ).attr( 'action', wc_simple_path_form.action_url );
				$( 'input[name=spId]' ).val( wc_simple_path_form.spId );
			}
		},
		json_key_on_change: function() {
			try {
				var json_value = $.parseJSON( $( this ).val() );
				wc_simple_path_form.json_key_input.removeClass( 'json_key_error' );

				if ( json_value.encryptedKey ) {
					// Halt polling, we don't want to reload the page since we are doing it manually.
					wc_simple_path_form.poll_timer = false;
					// Start manual decrypt process.
					return wc_simple_path_form.json_encrypted_on_change( json_value );
				}

				// Setting Values
				wc_simple_path_form.set_credentials_values(
					json_value.merchant_id,
					json_value.access_key,
					json_value.secret_key,
					json_value.client_id,
					json_value.client_secret
				);
				wc_simple_path_form.json_key_input.addClass( 'json_key_valid' );

			} catch (err) {
				wc_simple_path_form.json_key_input.removeClass( 'json_key_valid' );
				wc_simple_path_form.json_key_input.addClass( 'json_key_error' );
			}
		},
		json_encrypted_on_change: function(json_value) {
			$.ajax(
				{
					url:     amazon_admin_params.ajax_url,
					data:    {
						'action' : 'amazon_manual_exchange',
						'nonce' : amazon_admin_params.manual_exchange_nonce,
						'data' : json_value,
						'region': wc_simple_path_form.payment_region_input.val()
					},
					type:    'POST',
					success: function( result ) {
						try {
							var json_value = $.parseJSON( result.data );

							// Set Credentials.
							wc_simple_path_form.set_credentials_values(
								json_value.merchant_id,
								json_value.access_key,
								json_value.secret_key,
								json_value.client_id,
								json_value.client_secret
							);

							wc_simple_path_form.json_key_input.val( result.data );
							wc_simple_path_form.json_key_input.removeClass( 'json_key_error' );
							wc_simple_path_form.json_key_input.addClass( 'json_key_valid' );

						} catch (err) {
							wc_simple_path_form.json_key_input.removeClass( 'json_key_valid' );
							wc_simple_path_form.json_key_input.addClass( 'json_key_error' );
							wc_simple_path_form.poll_timer = true; // Turn it to not false, so it's starts polling immediately
							wc_simple_path_form.poll_for_keys();
						}
					},
					error: function () {
						wc_simple_path_form.json_key_input.removeClass( 'json_key_valid' );
						wc_simple_path_form.json_key_input.addClass( 'json_key_error' );
						wc_simple_path_form.poll_timer = true; // Turn it to not false, so it's starts polling immediately
						wc_simple_path_form.poll_for_keys();
					}
				}
			);
		},
		set_credentials_values: function(merchant_id, access_key, secret_key, client_id, client_secret) {
			//Seller Id
			$( '#woocommerce_amazon_payments_advanced_seller_id' ).val( merchant_id );
			//MWS Access Key
			$( '#woocommerce_amazon_payments_advanced_mws_access_key' ).val( access_key );
			//MWS Secret Key
			$( '#woocommerce_amazon_payments_advanced_secret_key' ).val( secret_key );
			// App Client Id
			$( '#woocommerce_amazon_payments_advanced_app_client_id' ).val( client_id );
			// App Client Secret
			$( '#woocommerce_amazon_payments_advanced_app_client_secret' ).val( client_secret );
		},
		register_link_on_click: function() {
			// Trigger simple path form on all regions except JP.
			if ( 'jp' !== wc_simple_path_form.get_region_selected() ) {
				document.getElementById( wc_simple_path_form.simple_path_form_id ).submit.click();
				wc_simple_path_form.poll_timer = setTimeout( wc_simple_path_form.poll_for_keys, wc_simple_path_form.poll_interval );
			}
			$( '#woocommerce_amazon_payments_advanced_redirect_authentication' ).val( 'optimal' );
		},
		create_form : function() {
			$( ".wrap.woocommerce" ).append(
				$(
					"<form/>",
					{
						id: wc_simple_path_form.simple_path_form_id,
						action: wc_simple_path_form.action_url,
						method: 'post',
						target: '_blank',
					}
				).append(
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'spId',
							value: wc_simple_path_form.spId
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'publicKey',
							value: wc_simple_path_form.public_key
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'keyShareURL',
							value: wc_simple_path_form.exchange_url
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'locale',
							value: wc_simple_path_form.locale
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'merchantLoginDomains[]',
							value: wc_simple_path_form.home_url
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'merchantLoginRedirectURLs[]',
							value: wc_simple_path_form.exchange_url
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'merchantLoginRedirectURLs[]',
							value: wc_simple_path_form.login_redirect_url
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'merchantPrivacyNoticeURL',
							value: wc_simple_path_form.privacy_url
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'merchantStoreDescription',
							value: wc_simple_path_form.site_description
						}
					),
					$(
						"<input/>",
						{
							type: 'hidden',
							name: 'source',
							value: 'SPPL'
						}
					),
					$(
						"<input/>",
						{
							type: 'submit',
							name: 'submit',
							style: 'display:none',
							value: 'submit'
						}
					)
				)
			);
		},
		get_region_selected: function() {
			return wc_simple_path_form.payment_region_input.val();
		},
		get_simple_path_url: function() {
			return amazon_admin_params.simple_path_urls[ wc_simple_path_form.get_region_selected() ];
		},
		get_spId: function() {
			return amazon_admin_params.spids[ wc_simple_path_form.get_region_selected() ];
		},
		poll_for_keys: function () {
			clearTimeout( wc_simple_path_form.poll_timer );
			$.ajax(
				{
					url:     amazon_admin_params.ajax_url,
					data:    {
						'action' : 'amazon_check_credentials',
						'nonce' : amazon_admin_params.credentials_nonce
					},
					type:    'GET',
					success: function( result ) {
						if ( '1' === result ) {
							location.reload();
						} else {
							// Halt Polling.
							if (false === wc_simple_path_form.poll_timer) {
								return;
							}
							wc_simple_path_form.poll_timer = setTimeout( wc_simple_path_form.poll_for_keys, wc_simple_path_form.poll_interval );
						}
					}
				}
			);
		}
	};
	wc_simple_path_form.init();

})( jQuery );
