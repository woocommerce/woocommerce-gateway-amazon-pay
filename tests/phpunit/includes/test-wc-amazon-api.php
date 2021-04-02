<?php
/**
 * Test cases for WC_Amazon_Payments_Advanced_API.
 *
 * @package WC_Amazon_Payments_Advanced
 */

/**
 * WC_Amazon_Payments_Advanced_API_Test tests functionalities in WC_Amazon_Payments_Advanced_API.
 *
 * @since 1.6.3
 */
class WC_Amazon_Payments_Advanced_API_Test extends WP_UnitTestCase {
	/**
	 * Test default values from get_settings().
	 *
	 * @since 1.6.3
	 */
	public function test_get_default_settings() {
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_settings(),
			array(
				'enabled'                         => 'yes',
				'title'                           => __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
				'seller_id'                       => '',
				'mws_access_key'                  => '',
				'secret_key'                      => '',
				'payment_region'                  => WC_Amazon_Payments_Advanced_API::get_payment_region_from_country( WC()->countries->get_base_country() ),
				'enable_login_app'                => 'no',
				'app_client_id'                   => '',
				'app_client_secret'               => '',
				'sandbox'                         => 'yes',
				'payment_capture'                 => 'no',
				'authorization_mode'              => 'async',
				'redirect_authentication'         => 'popup',
				'cart_button_display_mode'        => 'button',
				'button_type'                     => 'LwA',
				'button_size'                     => 'small',
				'button_color'                    => 'Gold',
				'button_language'                 => '',
				'hide_standard_checkout_button'   => 'no',
				'debug'                           => 'no',
				'hide_button_mode'                => 'no',
				'amazon_keys_setup_and_validated' => '0',
			)
		);
	}

	/**
	 * Test update settings reflected in get_settings().
	 *
	 * @since 1.6.3
	 */
	public function test_update_settings_reflected_in_get_settings() {
		$settings            = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['enabled'] = 'yes';

		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		$actual = WC_Amazon_Payments_Advanced_API::get_settings();
		$actual = $actual['enabled'];
		$this->assertEquals( $actual, 'yes' );
	}

	/**
	 * Test get_reference_id() when there's amazon_reference_id in query string.
	 * This happens after redirected from Amazon.
	 *
	 * @since 1.6.3
	 */
	public function test_get_reference_id_via_get_request() {
		$_REQUEST['amazon_reference_id'] = 'test';
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_reference_id(),
			'test'
		);
		unset( $_REQUEST['amazon_reference_id'] );
	}

	/**
	 * Test get_reference_id() when there's
	 *
	 * @since 1.6.3
	 */
	public function test_get_reference_id_via_session() {
		WC()->session->amazon_reference_id = 'test';
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_reference_id(),
			'test'
		);
	}

	/**
	 * Test get_region() with no payment_region in settings (fallback to use
	 * region based on base country).
	 *
	 * @since 1.6.3
	 */
	public function test_get_region_from_base_country() {
		// get_region() should be using base_country when it's not set in settings.
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();
		unset( $settings['payment_region'] );
		update_option( 'woocommerce_amazon_advanced_payments_settings', $settings );

		// US.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_region(), 'us' );

		// GB.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_region(), 'gb' );

		// Euro regions.
		foreach ( array( 'DE', 'FR', 'ES', 'BG' ) as $country ) {
			update_option( 'woocommerce_default_country', $country );
			$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_region(), 'eu' );
		}

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_region(), 'jp' );

		// Default to 'US' for unsupported countries.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_region(), 'us' );
	}

	/**
	 * Test get_region() with payment_region exists in settings.
	 *
	 * @since 1.6.3
	 */
	public function test_get_region_from_settings() {
		// get_region() should be using base_country when it's not set in settings.
		$settings                   = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['payment_region'] = 'us';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		$this->assertEquals( 'us', WC_Amazon_Payments_Advanced_API::get_region() );

		// GB.
		$settings['payment_region'] = 'gb';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
		$this->assertEquals( 'gb', WC_Amazon_Payments_Advanced_API::get_region() );

		// Euro regions.
		$settings['payment_region'] = 'eu';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
		foreach ( array( 'DE', 'FR', 'ES', 'BG' ) as $country ) {
			// No matter what base country is, it should uses payment_region from
			// settings.
			update_option( 'woocommerce_default_country', $country );
			$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_region(), 'eu' );
		}

		// JP.
		$settings['payment_region'] = 'jp';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
		$this->assertEquals( 'jp', WC_Amazon_Payments_Advanced_API::get_region() );
	}

	/**
	 * Test get_client_id_instruction_urls().
	 *
	 * @since 1.6.3
	 */
	public function test_get_client_id_instructions_url() {
		// US.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url(), 'https://payments.amazon.com/documentation/express/201728550' );
		// GB.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url(), 'https://amazonpayments.s3.amazonaws.com/documents/Get_Your_Login_with_Amazon_Client_ID_EU_ENG.pdf?ld=APUSLPADefault' );

		// DE.
		update_option( 'woocommerce_default_country', 'DE' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url(), 'https://amazonpayments.s3.amazonaws.com/documents/Get_Your_Login_with_Amazon_Client_ID_EU_ENG.pdf?ld=APUSLPADefault' );

		// Instruction URL is not available for JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url(), '' );

		// Default to US URL for unsupported countries.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals( WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url(), 'https://payments.amazon.com/documentation/express/201728550' );
	}

	/**
	 * Test get_widgets_url() with sandbox and login app enabled.
	 *
	 * @since 1.6.3
	 */
	public function test_get_widgets_url_sandboxed_and_login_app_enabled() {
		$settings                     = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['sandbox']          = 'yes';
		$settings['enable_login_app'] = 'yes';
		$settings['payment_region']   = '';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals(
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);

		// G.B.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals(
			'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/sandbox/lpa/js/Widgets.js',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);

		// Euro.
		foreach ( array( 'DE', 'ES', 'FR' ) as $country ) {
			update_option( 'woocommerce_default_country', $country );
			$this->assertEquals(
				WC_Amazon_Payments_Advanced_API::get_widgets_url(),
				'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/lpa/js/Widgets.js'
			);
		}

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/sandbox/prod/lpa/js/Widgets.js'
		);

		// Unsupported country.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js'
		);
	}

	/**
	 * Test get_widgets_url() with sandbox enabled and login app disabled.
	 *
	 * @since 1.6.3
	 */
	public function test_get_widgets_url_sandboxed_and_login_app_disabled() {
		$settings                     = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['sandbox']          = 'yes';
		$settings['seller_id']        = '123';
		$settings['enable_login_app'] = 'no';
		$settings['payment_region']   = '';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals(
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId=123',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);

		// G.B.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals(
			'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/sandbox/js/Widgets.js?sellerId=123',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);

		// DE.
		update_option( 'woocommerce_default_country', 'DE' );
		$this->assertEquals(
			'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/js/Widgets.js?sellerId=123',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals(
			'https://static-fe.payments-amazon.com/OffAmazonPayments/jp/sandbox/js/Widgets.js?sellerId=123',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);

		// Unsupported country.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals(
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId=123',
			WC_Amazon_Payments_Advanced_API::get_widgets_url()
		);
	}

	/**
	 * Test get_widgets_url() with production and login app enabled.
	 *
	 * @since 1.6.3
	 */
	public function test_get_widgets_url_production_and_login_app_enabled() {
		$settings                     = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['sandbox']          = 'no';
		$settings['enable_login_app'] = 'yes';
		$settings['payment_region']   = '';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js'
		);

		// G.B.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/lpa/js/Widgets.js'
		);

		// DE.
		update_option( 'woocommerce_default_country', 'DE' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/lpa/js/Widgets.js'
		);

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/live/prod/lpa/js/Widgets.js'
		);

		// Unsupported country.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js'
		);
	}

	/**
	 * Test get_widgets_url() with production enabled and login app disabled.
	 *
	 * @since 1.6.3
	 */
	public function test_get_widgets_url_production_and_login_app_disabled() {
		$settings                     = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['sandbox']          = 'no';
		$settings['seller_id']        = '123';
		$settings['enable_login_app'] = 'no';
		$settings['payment_region']   = '';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId=123'
		);

		// G.B.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/js/Widgets.js?sellerId=123'
		);

		// DE.
		update_option( 'woocommerce_default_country', 'DE' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/js/Widgets.js?sellerId=123'
		);

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-fe.payments-amazon.com/OffAmazonPayments/jp/js/Widgets.js?sellerId=123'
		);

		// Unsupported country.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_widgets_url(),
			'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId=123'
		);
	}

	/**
	 * Test get_endpoint() with sandbox enabled.
	 *
	 * @since 1.6.3
	 */
	public function test_get_endpoint_sandboxed() {
		$settings                   = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['sandbox']        = 'yes';
		$settings['payment_region'] = '';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint( true ),
			'https://mws.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/'
		);

		// GB.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint( true ),
			'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/'
		);

		// DE.
		update_option( 'woocommerce_default_country', 'DE' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint( true ),
			'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/'
		);

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint( true ),
			'https://mws.amazonservices.jp/OffAmazonPayments_Sandbox/2013-01-01/'
		);

		// Unsupported country uses U.S endpoint.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint( true ),
			'https://mws.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/'
		);
	}

	/**
	 * Test get_endpoint() with production enabled.
	 *
	 * @since 1.6.3
	 */
	public function test_get_endpoint_production() {
		$settings                   = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['sandbox']        = 'no';
		$settings['payment_region'] = '';
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );

		// U.S.
		update_option( 'woocommerce_default_country', 'US:CA' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint(),
			'https://mws.amazonservices.com/OffAmazonPayments/2013-01-01/'
		);

		// GB.
		update_option( 'woocommerce_default_country', 'GB' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint(),
			'https://mws-eu.amazonservices.com/OffAmazonPayments/2013-01-01/'
		);

		// DE.
		update_option( 'woocommerce_default_country', 'DE' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint(),
			'https://mws-eu.amazonservices.com/OffAmazonPayments/2013-01-01/'
		);

		// JP.
		update_option( 'woocommerce_default_country', 'JP' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint(),
			'https://mws.amazonservices.jp/OffAmazonPayments/2013-01-01/'
		);

		// Unsupported country uses U.S endpoint.
		update_option( 'woocommerce_default_country', 'ID' );
		$this->assertEquals(
			WC_Amazon_Payments_Advanced_API::get_endpoint(),
			'https://mws.amazonservices.com/OffAmazonPayments/2013-01-01/'
		);
	}

	/**
	 * Test request that failed.
	 *
	 * @since 1.8.0
	 */
	public function test_request_error() {
		$resp = $this->filtered_request(
			array(),
			function() {
				return new WP_Error( 'foo', 'bar' );
			}
		);

		$this->assertInstanceOf( 'WP_Error', $resp );
	}

	/**
	 * Test request to authorize order.
	 *
	 * @since 1.8.0
	 */
	public function test_request_authorize() {
		$resp = $this->filtered_request(
			array(),
			function() {
				return array(
					'body' => $this->get_authorize_response(),
				);
			}
		);
		$this->assertInstanceOf( 'SimpleXMLElement', $resp );

		// @codingStandardsIgnoreStart
		$details = $resp->AuthorizeResult->AuthorizationDetails;
		$this->assertEquals( '94.50', (string) $details->AuthorizationAmount->Amount );
		$this->assertEquals( 'USD', (string) $details->AuthorizationAmount->CurrencyCode );
		$this->assertEquals( 'Pending', (string) $details->AuthorizationStatus->State );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Test request to cencel order reference.
	 *
	 * @since 1.8.0
	 */
	public function test_request_cancel_order_reference() {
		$resp = $this->filtered_request(
			array(),
			function() {
				return array(
					'body' => $this->get_cancel_order_reference_response(),
				);
			}
		);
		$this->assertInstanceOf( 'SimpleXMLElement', $resp );
	}

	/**
	 * Test request to capture order.
	 *
	 * @since 1.8.0
	 */
	public function test_request_capture() {
		$resp = $this->filtered_request(
			array(),
			function() {
				return array(
					'body' => $this->get_capture_response(),
				);
			}
		);
		$this->assertInstanceOf( 'SimpleXMLElement', $resp );

		// @codingStandardsIgnoreStart
		$details = $resp->CaptureResult->CaptureDetails;
		$this->assertEquals( '94.50', (string) $details->CaptureAmount->Amount );
		$this->assertEquals( 'USD', (string) $details->CaptureAmount->CurrencyCode );
		$this->assertEquals( 'Completed', (string) $details->CaptureStatus->State );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Test get_signed_amazon_url().
	 *
	 * @since 1.8.0
	 */
	public function test_get_signed_amazon_url() {
		$url        = 'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/?AWSAccessKeyId=AKIAIXTU75HETA53PS4Q&Action=Authorize&AmazonOrderReferenceId=S02-3703223-9973137&AuthorizationAmount.Amount=14.8&AuthorizationAmount.CurrencyCode=EUR&AuthorizationReferenceId=2382-1510161652&CaptureNow=1&SellerId=A3SA32H56X44JJ&SellerOrderAttributes.SellerOrderId=2382&SellerOrderAttributes.StoreName=Local%20WP%20Dev&Timestamp=2017-11-08T17%3A20%3A52Z&TransactionTimeout=0';
		$secret_key = '123';
		$url        = WC_Amazon_Payments_Advanced_API::get_signed_amazon_url( $url, $secret_key );
		$this->assertEquals( 'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/?AWSAccessKeyId=AKIAIXTU75HETA53PS4Q&Action=Authorize&AmazonOrderReferenceId=S02-3703223-9973137&AuthorizationAmount.Amount=14.8&AuthorizationAmount.CurrencyCode=EUR&AuthorizationReferenceId=2382-1510161652&CaptureNow=1&SellerId=A3SA32H56X44JJ&SellerOrderAttributes.SellerOrderId=2382&SellerOrderAttributes.StoreName=Local%20WP%20Dev&SignatureMethod=HmacSHA256&SignatureVersion=2&Timestamp=2017-11-08T17%3A20%3A52Z&TransactionTimeout=0&Signature=dkmsSiOMp8BClfC%2BDMVw3tghDg7u6WdkYHIq35GyIrQ%3D', $url );
	}

	/**
	 * Test get_get_reference_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_reference_state_with_api_request() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$filter = function() {
			return array(
				'body' => $this->get_order_reference_details_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$state = WC_Amazon_Payments_Advanced_API::get_reference_state( $order_id, '123' );
		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( 'Draft', $state );
	}

	/**
	 * Test get_get_reference_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_reference_state_with_api_request_error() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$filter = function() {
			return new WP_Error( 'foo', 'bar' );
		};
		add_filter( 'pre_http_request', $filter );
		$state = WC_Amazon_Payments_Advanced_API::get_reference_state( $order_id, '123' );
		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( false, $state );
	}

	/**
	 * Test get_get_reference_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_reference_state_from_meta() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, 'amazon_reference_state', 'Closed' );

		$this->assertEquals( 'Closed', WC_Amazon_Payments_Advanced_API::get_reference_state( $order_id, '' ) );
	}

	/**
	 * Test get_authorization_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_authorization_state_with_api_request() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$filter = function() {
			return array(
				'body' => $this->get_authorization_details_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$state = WC_Amazon_Payments_Advanced_API::get_authorization_state( $order_id, '123' );
		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( 'Open', $state );
	}

	/**
	 * Test get_authorization_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_authorization_state_with_api_request_error() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$filter = function() {
			return new WP_Error( 'foo', 'bar' );
		};
		add_filter( 'pre_http_request', $filter );
		$state = WC_Amazon_Payments_Advanced_API::get_authorization_state( $order_id, '123' );
		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( false, $state );
	}

	/**
	 * Test get_authorization_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_authorization_state_from_meta() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, 'amazon_authorization_state', 'Closed' );

		$this->assertEquals( 'Closed', WC_Amazon_Payments_Advanced_API::get_authorization_state( $order_id, '' ) );
	}

	/**
	 * Test get_capture_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_capture_state_with_api_request() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$filter = function() {
			return array(
				'body' => $this->get_capture_details_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$state = WC_Amazon_Payments_Advanced_API::get_capture_state( $order_id, '123' );
		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( 'Completed', $state );
	}

	/**
	 * Test get_capture_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_capture_state_with_api_request_error() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$filter = function() {
			return new WP_Error( 'foo', 'bar' );
		};
		add_filter( 'pre_http_request', $filter );
		$state = WC_Amazon_Payments_Advanced_API::get_capture_state( $order_id, '123' );
		remove_filter( 'pre_http_request', $filter );

		$this->assertEquals( false, $state );
	}

	/**
	 * Test get_capture_state().
	 *
	 * @since 1.8.0
	 */
	public function test_get_capture_state_from_meta() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, 'amazon_capture_state', 'Closed' );

		$this->assertEquals( 'Closed', WC_Amazon_Payments_Advanced_API::get_capture_state( $order_id, '' ) );
	}

	/**
	 * Test authorize().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_with_invalid_order() {
		$resp = WC_Amazon_Payments_Advanced_API::authorize( new WC_Order( 0 ) );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'invalid_order', $resp->get_error_code() );
	}

	/**
	 * Test authorize().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_with_order_paid_with_another_payment_method() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'paypal' );

		$resp = WC_Amazon_Payments_Advanced_API::authorize( $order );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'invalid_order', $resp->get_error_code() );
	}

	/**
	 * Test authorize().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_with_missing_amazon_reference_id() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'amazon_payments_advanced' );

		$resp = WC_Amazon_Payments_Advanced_API::authorize( $order );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'order_missing_reference_id', $resp->get_error_code() );
	}

	/**
	 * Test authorize().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_succeed() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'amazon_payments_advanced' );
		update_post_meta( $order_id, 'amazon_reference_id', '123' );

		$filter = function() {
			return array(
				'body' => $this->get_authorize_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$resp = WC_Amazon_Payments_Advanced_API::authorize( $order );
		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( 'SimpleXMLElement', $resp );
	}

	/**
	 * Test authorize().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_declined_with_invalid_payment_method() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'amazon_payments_advanced' );
		update_post_meta( $order_id, 'amazon_reference_id', '123' );

		$filter = function() {
			return array(
				'body' => $this->get_authorize_invalid_payment_method_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$resp = WC_Amazon_Payments_Advanced_API::authorize( $order );
		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'InvalidPaymentMethod', $resp->get_error_code() );
	}

	/**
	 * Test authorize() with a declined response
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_declined_with_amazon_rejected() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'amazon_payments_advanced' );
		update_post_meta( $order_id, 'amazon_reference_id', '123' );

		$filter = function() {
			return array(
				'body' => $this->get_authorize_amazon_rejected_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$resp = WC_Amazon_Payments_Advanced_API::authorize( $order );
		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'AmazonRejected', $resp->get_error_code() );
		$this->assertContains( 'amazon_payments_advanced=true', $resp->get_error_message() );
		$this->assertContains( 'amazon_logout=true', $resp->get_error_message() );
		$this->assertContains( 'amazon_declined=true', $resp->get_error_message() );
	}

	/**
	 * Test get_authorize_request_args().
	 *
	 * @since 1.8.0
	 */
	public function test_get_authorize_request_args() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$this->assertEquals(
			array(
				'Action'                              => 'Authorize',
				'AmazonOrderReferenceId'              => '123',
				'AuthorizationReferenceId'            => $order_id . '-' . time(),
				'AuthorizationAmount.Amount'          => $order->get_total(),
				'AuthorizationAmount.CurrencyCode'    => strtoupper( get_woocommerce_currency() ),
				'CaptureNow'                          => true,
				'TransactionTimeout'                  => 0,
				'SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
				'SellerOrderAttributes.StoreName'     => WC_Amazon_Payments_Advanced::get_site_name(),
			),
			WC_Amazon_Payments_Advanced_API::get_authorize_request_args(
				$order,
				array(
					'amazon_reference_id' => '123',
					'capture_now'         => true,
				)
			)
		);
	}

	/**
	 * Test authorize_recurring().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_recurring_with_invalid_order() {
		$resp = WC_Amazon_Payments_Advanced_API::authorize_recurring( new WC_Order( 0 ) );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'invalid_order', $resp->get_error_code() );
	}

	/**
	 * Test authorize_recurring().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_recurring_with_order_paid_with_another_payment_method() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'paypal' );

		$resp = WC_Amazon_Payments_Advanced_API::authorize_recurring( $order );

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'invalid_order', $resp->get_error_code() );
	}

	/**
	 * Test authorize_recurring().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_recurring_with_missing_billing_agreement_id() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'amazon_payments_advanced' );

		$resp = WC_Amazon_Payments_Advanced_API::authorize_recurring(
			$order,
			array(
				'amazon_billing_agreement_id' => '',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $resp );
		$this->assertEquals( 'order_missing_billing_agreement_id', $resp->get_error_code() );
	}

	/**
	 * Test authorize_recurring().
	 *
	 * @since 1.8.0
	 */
	public function test_authorize_recurring_succeed() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		update_post_meta( $order_id, '_payment_method', 'amazon_payments_advanced' );

		$filter = function() {
			return array(
				'body' => $this->get_authorize_response(),
			);
		};
		add_filter( 'pre_http_request', $filter );
		$resp = WC_Amazon_Payments_Advanced_API::authorize_recurring(
			$order,
			array(
				'amazon_billing_agreement_id' => '123',
			)
		);
		remove_filter( 'pre_http_request', $filter );

		$this->assertInstanceOf( 'SimpleXMLElement', $resp );
	}

	/**
	 * Test get_authorize_recurring_request_args().
	 *
	 * @since 1.8.0
	 */
	public function test_get_authorize_recurring_request_args() {
		$order    = WC_Helper_Order::create_order();
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$this->assertEquals(
			array(
				'Action'                              => 'AuthorizeOnBillingAgreement',
				'AmazonBillingAgreementId'            => '123',
				'AuthorizationReferenceId'            => $order_id . '-' . time(),
				'AuthorizationAmount.Amount'          => $order->get_total(),
				'AuthorizationAmount.CurrencyCode'    => strtoupper( get_woocommerce_currency() ),
				'CaptureNow'                          => true,
				'TransactionTimeout'                  => 0,
				'SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
				'SellerOrderAttributes.StoreName'     => WC_Amazon_Payments_Advanced::get_site_name(),
				'InheritShippingAddress'              => false,
			),
			WC_Amazon_Payments_Advanced_API::get_authorize_recurring_request_args(
				$order,
				array(
					'amazon_billing_agreement_id' => '123',
					'capture_now'                 => true,
				)
			)
		);
	}

	/**
	 * Test that "undefined" strings are removed from address fields by format_address.
	 */
	public function test_format_address_undefined_strings() {
		$address           = new SimpleXMLElement( '<root><AddressLine1>Not empty field</AddressLine1><AddressLine3>undefined</AddressLine3></root>' );
		$formatted_address = WC_Amazon_Payments_Advanced_API::format_address( $address );
		$this->assertEquals(
			array(
				'address_1' => 'Not empty field',
			),
			$formatted_address
		);
	}

	/**
	 * Test that single names fallback to using period in format_address.
	 */
	public function test_format_address_single_name() {
		$address           = new SimpleXMLElement( '<root><Name>Tester</Name></root>' );
		$formatted_address = WC_Amazon_Payments_Advanced_API::format_address( $address );
		$this->assertEquals(
			array(
				'first_name' => 'Tester',
				'last_name'  => '.',
			),
			$formatted_address
		);
	}

	/**
	 * Test that format_address works for null addresses.
	 */
	public function test_format_address_null_address() {
		$formatted_address = WC_Amazon_Payments_Advanced_API::format_address( null );
		$this->assertEquals(
			array(),
			$formatted_address
		);
	}

	/**
	 * Call WC_Amazon_Payments_Advanced_API::request without actually making
	 * outgoing request.
	 *
	 * @since 1.8.0
	 *
	 * @param array    $args   Args for WC_Amazon_Payments_Advanced_API::request.
	 * @param callable $filter Callable to short-circuit wp_remote_post call.
	 *                         Passed to `pre_http_request` filter.
	 *
	 * @return WP_Error|SimpleXMLElement Response.
	 */
	protected function filtered_request( $args, $filter ) {
		add_filter( 'pre_http_request', $filter );
		$resp = WC_Amazon_Payments_Advanced_API::request( $args );
		remove_filter( 'pre_http_request', $filter );

		return $resp;
	}

	/**
	 * Get authorize API response in XML string.
	 *
	 * @since 1.8.0
	 *
	 * @return string Authorize response in XML.
	 */
	protected function get_authorize_response() {
		return trim(
			'
			<AuthorizeResponse xmlns="https://mws.amazonservices.com/schema/OffAmazonPayments/2013-01-01">
				<AuthorizeResult>
					<AuthorizationDetails>
						<AmazonAuthorizationId>P01-1234567-1234567-0000001</AmazonAuthorizationId>
						<AuthorizationReferenceId>test_authorize_1</AuthorizationReferenceId>
						<SellerAuthorizationNote>Lorem ipsum</SellerAuthorizationNote>
						<AuthorizationAmount>
							<CurrencyCode>USD</CurrencyCode>
							<Amount>94.50</Amount>
						</AuthorizationAmount>
						<AuthorizationFee>
							<CurrencyCode>USD</CurrencyCode>
							<Amount>0</Amount>
						</AuthorizationFee>
						<SoftDecline>true</SoftDecline>
						<AuthorizationStatus>
							<State>Pending</State>
							<LastUpdateTimestamp>2012-11-03T19:10:16Z</LastUpdateTimestamp>
						</AuthorizationStatus>
						<CreationTimestamp>2012-11-02T19:10:16Z</CreationTimestamp>
						<ExpirationTimestamp>2012-12-02T19:10:16Z</ExpirationTimestamp>
					</AuthorizationDetails>
				</AuthorizeResult>
				<ResponseMetadata>
					<RequestId>b4ab4bc3-c9ea-44f0-9a3d-67cccef565c6</RequestId>
				</ResponseMetadata>
			</AuthorizeResponse>
			'
		);
	}

	/**
	 * Get authorize API response in XML string.
	 *
	 * @since 1.8.0
	 *
	 * @return string Authorize response in XML.
	 */
	protected function get_authorize_invalid_payment_method_response() {
		$resp = $this->get_authorize_response();
		$resp = str_replace( '</State>', '</State><ReasonCode>InvalidPaymentMethod</ReasonCode>', $resp );
		return $resp;
	}

	/**
	 * Get authorize API response in XML string.
	 *
	 * @since 1.8.0
	 *
	 * @return string Authorize response in XML.
	 */
	protected function get_authorize_amazon_rejected_response() {
		$resp = $this->get_authorize_response();
		$resp = str_replace( '</State>', '</State><ReasonCode>AmazonRejected</ReasonCode>', $resp );
		return $resp;
	}

	/**
	 * Get the cancel order reference API response in XML string.
	 *
	 * @since 1.8.0
	 *
	 * @return string XML string.
	 */
	protected function get_cancel_order_reference_response() {
		return trim(
			'
			<CancelOrderReferenceResponse xmlns="https://mws.amazonservices.com/schema/OffAmazonPayments/2013-01-01">
				<ResponseMetadata>
					<RequestId>5f20169b-7ab2-11df-bcef-d35615e2b044</RequestId>
				</ResponseMetadata>
			</CancelOrderReferenceResponse>
			'
		);
	}

	/**
	 * Get the cancel capture API response.
	 *
	 * @since 1.8.0
	 *
	 * @return string XML string.
	 */
	protected function get_capture_response() {
		return trim(
			'
			<CaptureResponse xmlns="https://mws.amazonservices.com/schema/OffAmazonPayments/2013-01-01">
				<CaptureResult>
					<CaptureDetails>
						<AmazonCaptureId>P01-1234567-1234567-0000002</AmazonCaptureId>
						<CaptureReferenceId>test_capture_1</CaptureReferenceId>
						<SellerCaptureNote>Lorem ipsum</SellerCaptureNote>
						<CaptureAmount>
							<CurrencyCode>USD</CurrencyCode>
							<Amount>94.50</Amount>
						</CaptureAmount>
						<CaptureStatus>
							<State>Completed</State>
							<LastUpdateTimestamp>2012-11-03T19:10:16Z</LastUpdateTimestamp>
						</CaptureStatus>
						<CreationTimestamp>2012-11-03T19:10:16Z</CreationTimestamp>
					</CaptureDetails>
				</CaptureResult>
				<ResponseMetadata>
					<RequestId>b4ab4bc3-c9ea-44f0-9a3d-67cccef565c6</RequestId>
				</ResponseMetadata>
			</CaptureResponse>
			'
		);
	}

	/**
	 * Get the order reference details API response.
	 *
	 * @since 1.8.0
	 *
	 * @return string XML string.
	 */
	protected function get_order_reference_details_response() {
		return trim(
			'
			<GetOrderReferenceDetailsResponse xmlns="http://mws.amazonservices.com/schema/OffAmazonPayments/2013-01-01">
				<GetOrderReferenceDetailsResult>
					<OrderReferenceDetails>
						<AmazonOrderReferenceId>P01-1234567-1234567</AmazonOrderReferenceId>
						<CreationTimestamp>2012-11-05T20:21:19Z</CreationTimestamp>
						<ExpirationTimestamp>2013-05-07T23:21:19Z</ExpirationTimestamp>
						<OrderReferenceStatus>
							<State>Draft</State>
						</OrderReferenceStatus>
						<Destination>
							<DestinationType>Physical</DestinationType>
							<PhysicalDestination>
								<City>New York</City>
								<StateOrRegion>NY</StateOrRegion>
								<PostalCode>10101-9876</PostalCode>
								<CountryCode>US</CountryCode>
							</PhysicalDestination>
						</Destination>
						<ReleaseEnvironment>Live</ReleaseEnvironment>
					</OrderReferenceDetails>
				</GetOrderReferenceDetailsResult>
				<ResponseMetadata>
					<RequestId>5f20169b-7ab2-11df-bcef-d35615e2b044</RequestId>
				</ResponseMetadata>
			</GetOrderReferenceDetailsResponse>
			'
		);
	}

	/**
	 * Get the authorization details API response.
	 *
	 * @since 1.8.0
	 *
	 * @return string XML string.
	 */
	protected function get_authorization_details_response() {
		return trim(
			'
			<GetAuthorizationDetailsResponse xmlns="https://mws.amazonservices.com/schema/OffAmazonPayments/2013-01-01">
				<GetAuthorizationDetailsResult>
					<AuthorizationDetails>
						<AmazonAuthorizationId>P01-1234567-1234567-0000001</AmazonAuthorizationId>
						<AuthorizationReferenceId>test_authorize_1</AuthorizationReferenceId>
						<SellerAuthorizationNote>Lorem ipsum</SellerAuthorizationNote>
						<AuthorizationAmount>
							<CurrencyCode>USD</CurrencyCode>
							<Amount>94.50</Amount>
						</AuthorizationAmount>
						<AuthorizationFee>
							<CurrencyCode>USD</CurrencyCode>
							<Amount>0</Amount>
						</AuthorizationFee>
						<AuthorizationStatus>
							<State>Open</State>
							<LastUpdateTimestamp>2012-12-10T19%3A01%3A11Z</LastUpdateTimestamp>
						</AuthorizationStatus>
						<CreationTimestamp>2012-12-10T19%3A01%3A11Z</CreationTimestamp>
						<ExpirationTimestamp>2013-01-10T19:10:16Z</ExpirationTimestamp>
					</AuthorizationDetails>
				</GetAuthorizationDetailsResult>
				<ResponseMetadata>
					<RequestId>b4ab4bc3-c9ea-44f0-9a3d-67cccef565c6</RequestId>
				</ResponseMetadata>
			</GetAuthorizationDetailsResponse>
			'
		);
	}

	/**
	 * Get the capture details API response.
	 *
	 * @since 1.8.0
	 *
	 * @return string XML string.
	 */
	protected function get_capture_details_response() {
		return trim(
			'
			<GetCaptureDetailsResponse xmlns="http://mws.amazonservices.com/schema/OffAmazonPayments/2013-01-01">
				<GetCaptureDetailsResult>
					<CaptureDetails>
						<CaptureReferenceId>2382-1510161652</CaptureReferenceId>
						<CaptureFee>
							<CurrencyCode>EUR</CurrencyCode>
							<Amount>0.00</Amount>
						</CaptureFee>
						<SoftDescriptor>AMZ*Sugab Adeka</SoftDescriptor>
						<IdList/>
						<CaptureAmount>
							<CurrencyCode>EUR</CurrencyCode>
							<Amount>14.80</Amount>
						</CaptureAmount>
						<AmazonCaptureId>S02-3703223-9973137-C080370</AmazonCaptureId>
						<CreationTimestamp>2017-11-08T17:20:53.364Z</CreationTimestamp>
						<CaptureStatus>
							<LastUpdateTimestamp>2017-11-08T17:20:53.364Z</LastUpdateTimestamp>
							<State>Completed</State>
						</CaptureStatus>
						<SellerCaptureNote/>
						<RefundedAmount>
							<CurrencyCode>EUR</CurrencyCode>
							<Amount>0</Amount>
						</RefundedAmount>
					</CaptureDetails>
				</GetCaptureDetailsResult>
				<ResponseMetadata>
					<RequestId>8e6471d7-b038-4aa1-ab02-c45991c8ff22</RequestId>
				</ResponseMetadata>
			</GetCaptureDetailsResponse>
			'
		);
	}
}
