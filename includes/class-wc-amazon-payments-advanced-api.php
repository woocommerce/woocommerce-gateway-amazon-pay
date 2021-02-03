<?php
/**
 * Amazon API class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay API class
 */
class WC_Amazon_Payments_Advanced_API {

	/**
	 * Login App setup - Client ID Retrieval Instruction URLs
	 *
	 * @var array
	 */
	protected static $client_id_instructions = array(
		'us' => 'https://payments.amazon.com/documentation/express/201728550',
		'gb' => 'https://amazonpayments.s3.amazonaws.com/documents/Get_Your_Login_with_Amazon_Client_ID_EU_ENG.pdf?ld=APUSLPADefault',
		'eu' => 'https://amazonpayments.s3.amazonaws.com/documents/Get_Your_Login_with_Amazon_Client_ID_EU_ENG.pdf?ld=APUSLPADefault',
	);

	/**
	 * API Endpoints.
	 *
	 * @var array
	 */
	protected static $endpoints = array(
		'sandbox' => array(
			'us' => 'https://mws.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/',
			'gb' => 'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/',
			'eu' => 'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/',
			'jp' => 'https://mws.amazonservices.jp/OffAmazonPayments_Sandbox/2013-01-01/',
		),
		'production' => array(
			'us' => 'https://mws.amazonservices.com/OffAmazonPayments/2013-01-01/',
			'gb' => 'https://mws-eu.amazonservices.com/OffAmazonPayments/2013-01-01/',
			'eu' => 'https://mws-eu.amazonservices.com/OffAmazonPayments/2013-01-01/',
			'jp' => 'https://mws.amazonservices.jp/OffAmazonPayments/2013-01-01/',
		),
	);

	/**
	 * Widgets URLs.
	 *
	 * @var array
	 */
	protected static $widgets_urls = array(
		'sandbox' => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/sandbox/lpa/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/lpa/js/Widgets.js',
			'jp' => 'https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/sandbox/prod/lpa/js/Widgets.js',
		),
		'production' => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/lpa/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/lpa/js/Widgets.js',
			'jp' => 'https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/live/prod/lpa/js/Widgets.js',
		),
	);

	/**
	 * Language ISO code map to its domain.
	 *
	 * @var array
	 */
	public static $lang_domains_mapping = array(
		'en-GB'   => 'co.uk',
		'de-DE'   => 'de',
		'fr-FR'   => 'fr',
		'it-IT'   => 'it',
		'es-ES'   => 'es',
		'en-US'   => 'com',
		'ja-JP'   => 'co.jp',
	);

	/**
	 * Non-app widgets URLs.
	 *
	 * @since 1.6.3
	 *
	 * @var array
	 */
	protected static $non_app_widgets_urls = array(
		'sandbox' => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/sandbox/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/js/Widgets.js',
			'jp' => 'https://static-fe.payments-amazon.com/OffAmazonPayments/jp/sandbox/js/Widgets.js',
		),
		'production' => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/js/Widgets.js',
			'jp' => 'https://static-fe.payments-amazon.com/OffAmazonPayments/jp/js/Widgets.js',
		),
	);

	/**
	 * List of supported currencies.
	 * https://pay.amazon.com/uk/help/5BDCWHCUC27485L
	 *
	 * @var array
	 */
	protected static $supported_currencies = array(
		'AUD',
		'GBP',
		'DKK',
		'EUR',
		'HKD',
		'JPY',
		'NZD',
		'NOK',
		'ZAR',
		'SEK',
		'CHF',
		'USD',
	);

	/**
	 * Simple Path registration urls.
	 *
	 * @var array
	 */
	public static $registration_urls = array(
		'us' => 'https://payments.amazon.com/register',
		'gb' => 'https://payments-eu.amazon.com/register',
		'eu' => 'https://payments-eu.amazon.com/register',
		'jp' => 'https://pay.amazon.com/jp/signup', // Simple Path not available in jp yet, just a normal url.
	);

	/**
	 * Simple Path public keys urls.
	 *
	 * @var array
	 */
	public static $get_public_keys_urls = array(
		'us' => 'https://payments.amazon.com/register/getpublickey',
		'gb' => 'https://payments-eu.amazon.com/register/getpublickey',
		'eu' => 'https://payments-eu.amazon.com/register/getpublickey',
		'jp' => '', // Not available in jp yet.
	);

	/**
	 * Simple Path spIds.
	 *
	 * @var array
	 */
	public static $spIds = array (
		'us' => 'A1BVJDFFHQ7US4',
		'gb' => 'A3AO8502KEOZS3',
		'eu' => 'A3V6YX13IG1QFQ',
		'jp' => 'A2EBW2CGZKMGE4',
	);

	/**
	 * Get settings
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = (array) get_option( 'woocommerce_amazon_payments_advanced_settings', array() );
		$default  = array(
			'enabled'                         => 'yes',
			'title'                           => __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
			'seller_id'                       => '',
			'mws_access_key'                  => '',
			'secret_key'                      => '',
			'payment_region'                  => self::get_payment_region_from_country( WC()->countries->get_base_country() ),
			'enable_login_app'                => ( self::is_new_installation() ) ? 'yes' : 'no',
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
		);

		return apply_filters( 'woocommerce_amazon_pa_settings', array_merge( $default, $settings ) );
	}

	/**
	 * Get reference ID.
	 *
	 * @return string
	 */
	public static function get_reference_id() {
		$reference_id = ! empty( $_REQUEST['amazon_reference_id'] ) ? $_REQUEST['amazon_reference_id'] : '';

		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );

			if ( isset( $post_data['amazon_reference_id'] ) ) {
				$reference_id = $post_data['amazon_reference_id'];
			}
		}

		return self::check_session( 'amazon_reference_id', $reference_id );
	}

	/**
	 * Get Access token.
	 *
	 * @return string
	 */
	public static function get_access_token() {
		$access_token = ! empty( $_REQUEST['access_token'] ) ? $_REQUEST['access_token'] : ( isset( $_COOKIE['amazon_Login_accessToken'] ) && ! empty( $_COOKIE['amazon_Login_accessToken'] ) ? $_COOKIE['amazon_Login_accessToken'] : '' );

		return self::check_session( 'access_token', $access_token );
	}

	/**
	 * Check WC session for reference ID or access token.
	 *
	 * @since 1.6.0
	 *
	 * @param string $key   Key from query string in URL.
	 * @param string $value Value from query string in URL.
	 *
	 * @return string
	 */
	public static function check_session( $key, $value ) {
		if ( ! in_array( $key, array( 'amazon_reference_id', 'access_token' ) ) ) {
			return $value;
		}

		// Since others might call the get_reference_id or get_access_token
		// too early, WC instance may not exists.
		if ( ! function_exists( 'WC' ) ) {
			return $value;
		}
		if ( ! is_a( WC()->session, 'WC_Session' ) ) {
			return $value;
		}

		if ( false === strstr( $key, 'amazon_' ) ) {
			$key = 'amazon_' . $key;
		}

		// Set and unset reference ID or access token to/from WC session.
		if ( ! empty( $value ) ) {
			// Set access token or reference ID in session after redirected
			// from Amazon Pay window.
			if ( ! empty( $_GET['amazon_payments_advanced'] ) ) {
				WC()->session->{ $key } = $value;
			}
		} else {
			// Don't get anything in URL, check session.
			if ( ! empty( WC()->session->{ $key } ) ) {
				$value = WC()->session->{ $key };
			}
		}

		return $value;
	}

	/**
	 * Get payment region based on a given country.
	 *
	 * @since 1.6.3
	 *
	 * @param string $country Country code.
	 * @param string $default Default country code. Default to 'us' or 'eu' if
	 *                        passed country is in EU union.
	 *
	 * @return string Payment region
	 */
	public static function get_payment_region_from_country( $country, $default = 'us' ) {
		switch ( $country ) {
			case 'GB':
			case 'US':
			case 'JP':
				$region = strtolower( $country );
				break;
			default:
				$region = $default;
				if ( in_array( $country, WC()->countries->get_european_union_countries() ) ) {
					$region = 'eu';
				}
		}

		if ( ! array_key_exists( $region, self::get_payment_regions() ) ) {
			$region = 'us';
		}

		return $region;
	}

	/**
	 * Get payment regions.
	 *
	 * @since 1.6.3
	 *
	 * @return array Payment regions
	 */
	public static function get_payment_regions() {
		return array(
			'eu' => __( 'Euro Region', 'woocommerce-gateway-amazon-payments-advanced' ),
			'gb' => __( 'United Kingdom', 'woocommerce-gateway-amazon-payments-advanced' ),
			'us' => __( 'United States', 'woocommerce-gateway-amazon-payments-advanced' ),
			'jp' => __( 'Japan', 'woocommerce-gateway-amazon-payments-advanced' ),
		);
	}

	/**
	 * Checks whether current payment region supports shop currency.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return bool Returns true if shop currency is supported by current payment region.
	 */
	public static function is_region_supports_shop_currency() {
		$region   = self::get_region();
		// Avoid interferences of external multi-currency plugins
		$currency = get_option( 'woocommerce_currency' );

		switch ( $region ) {
			case 'eu':
				return 'EUR' === $currency;
			case 'gb':
				return 'GBP' === $currency;
			case 'us':
				return 'USD' === $currency;
			case 'jp':
				return 'JPY' === $currency;
		}

		return false;
	}

	/**
	 * Get location.
	 *
	 * @deprecated
	 */
	public static function get_location() {
		_deprecated_function( __METHOD__, '1.6.3', 'WC_Amazon_Payments_Advanced_API::get_region' );
		return self::get_region();
	}

	/**
	 * Get payment region from setting.
	 *
	 * @return string
	 */
	public static function get_region() {
		$settings = self::get_settings();
		$region   = ! empty( $settings['payment_region'] ) ? $settings['payment_region'] : self::get_payment_region_from_country( WC()->countries->get_base_country() );

		return $region;
	}

	/**
	 * If merchant is eu payment region (eu & uk).
	 *
	 * @return bool
	 */
	public static function is_sca_region() {
		return apply_filters(
			'woocommerce_amazon_payments_is_sca_region',
			( 'eu' === self::get_region() || 'gb' === self::get_region() )
		);
	}

	/**
	 * Get the label of payment region from setting.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return string Payment region label.
	 */
	public static function get_region_label() {
		$regions = self::get_payment_regions();
		return $regions[ self::get_region() ];
	}

	/**
	 * Get Login with Amazon App setup URL.
	 *
	 * @return string
	 */
	public static function get_client_id_instructions_url() {
		$region = self::get_region();

		return array_key_exists( $region, self::$client_id_instructions ) ? self::$client_id_instructions[ $region ] : '';
	}

	/**
	 * Get if amazon keys have been set and validated.
	 *
	 * @return bool
	 */
	public static function get_amazon_keys_set() {
		$settings = self::get_settings();
		return ( isset( $settings['amazon_keys_setup_and_validated'] ) ) && ( 1 === $settings['amazon_keys_setup_and_validated'] );
	}

	/**
	 * Get widgets URL.
	 *
	 * @return string
	 */
	public static function get_widgets_url() {
		$settings   = self::get_settings();
		$region     = $settings['payment_region'];
		$is_sandbox = 'yes' === $settings['sandbox'];

		// If payment_region is not set in settings, use base country.
		if ( ! $region ) {
			$region = self::get_payment_region_from_country( WC()->countries->get_base_country() );
		}

		if ( 'yes' === $settings['enable_login_app'] ) {
			return $is_sandbox ? self::$widgets_urls['sandbox'][ $region ] : self::$widgets_urls['production'][ $region ];
		}

		$non_app_url = $is_sandbox ? self::$non_app_widgets_urls['sandbox'][ $region ] : self::$non_app_widgets_urls['production'][ $region ];

		return $non_app_url . '?sellerId=' . $settings['seller_id'];
	}

	/**
	 * Get API endpoint.
	 *
	 * @param bool $is_sandbox Whether using sandbox or not.
	 *
	 * @return string
	 */
	public static function get_endpoint( $is_sandbox = false ) {
		$region = self::get_region();

		return $is_sandbox ? self::$endpoints['sandbox'][ $region ] : self::$endpoints['production'][ $region ];
	}

	/**
	 * Get list of supported currencies.
	 *
	 * @param bool $filter Filters current woocommerce currency.
	 *
	 * @return array
	 */
	public static function get_supported_currencies( $filter = false ) {
		return array_combine( self::$supported_currencies, self::$supported_currencies );
	}

	/**
	 * Returns selected currencies on settings together with native woocommerce currency.
	 *
	 * @return array
	 */
	public static function get_selected_currencies() {
		$settings = self::get_settings();
		return isset( $settings['currencies_supported'] ) && is_array( $settings['currencies_supported'] ) ? $settings['currencies_supported'] : array();
	}

	/**
	 * Returns the current value for the authorization mode setting.
	 *
	 * @return array
	 */
	public static function get_authorization_mode() {
		$settings = self::get_settings();
		return $settings['authorization_mode'];
	}

	/**
	 * Check if it is a new merchant (new install).
	 *
	 * @return bool
	 */
	public static function is_new_installation() {
		$version = get_option( WC_Amazon_Payments_Advanced_Install::APA_NEW_INSTALL_OPTION );
		return (bool) $version;
	}

	/**
	 * Safe load XML.
	 *
	 * @param  string $source  XML input.
	 * @param  int    $options Options.
	 *
	 * @return SimpleXMLElement|bool
	 */
	public static function safe_load_xml( $source, $options = 0 ) {
		$old = null;

		if ( '<' !== substr( $source, 0, 1 ) ) {
			return false;
		}

		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			$old = libxml_disable_entity_loader( true );
		}

		$dom    = new DOMDocument();
		$return = $dom->loadXML( $source, $options );

		if ( ! is_null( $old ) ) {
			libxml_disable_entity_loader( $old );
		}

		if ( ! $return ) {
			return false;
		}

		if ( isset( $dom->doctype ) ) {
			return false;
		}

		return simplexml_import_dom( $dom );
	}

	/**
	 * Make an api request.
	 *
	 * @param  args $args Arguments.
	 *
	 * @return WP_Error|SimpleXMLElement Response.
	 */
	public static function request( $args ) {
		$settings = self::get_settings();
		$defaults = array(
			'AWSAccessKeyId' => $settings['mws_access_key'],
			'SellerId'       => $settings['seller_id'],
		);

		$args     = apply_filters( 'woocommerce_amazon_pa_api_request_args', wp_parse_args( $args, $defaults ) );
		$endpoint = self::get_endpoint( 'yes' === $settings['sandbox'] );

		$url = self::get_signed_amazon_url( $endpoint . '?' . http_build_query( $args, '', '&' ), $settings['secret_key'] );
		wc_apa()->log( __METHOD__, sprintf( 'GET: %s', wc_apa()->sanitize_remote_request_log( $url ) ) );

		$response = wp_remote_get( $url, array(
			'timeout' => 12,
		) );

		if ( ! is_wp_error( $response ) ) {
			$response        = self::safe_load_xml( $response['body'], LIBXML_NOCDATA );
			$logged_response = wc_apa()->sanitize_remote_response_log( $response );

			wc_apa()->log( __METHOD__, sprintf( 'Response: %s', $logged_response ) );
		} else {
			wc_apa()->log( __METHOD__, sprintf( 'Error: %s', $response->get_error_message() ) );
		}

		return $response;
	}

	/**
	 * Sign a url for amazon.
	 *
	 * @param string $url        URL.
	 * @param string $secret_key Secret key.
	 *
	 * @return string
	 */
	public static function get_signed_amazon_url( $url, $secret_key ) {
		$urlparts = parse_url( $url );

		// Build $params with each name/value pair.
		foreach ( explode( '&', $urlparts['query'] ) as $part ) {
			if ( strpos( $part, '=' ) ) {
				list( $name, $value ) = explode( '=', $part, 2 );
			} else {
				$name  = $part;
				$value = '';
			}
			$params[ $name ] = $value;
		}

		// Include a timestamp if none was provided.
		if ( empty( $params['Timestamp'] ) ) {
			$params['Timestamp'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		}

		$params['SignatureVersion'] = '2';
		$params['SignatureMethod']  = 'HmacSHA256';

		// Sort the array by key.
		ksort( $params );

		// Build the canonical query string.
		$canonical = '';

		// Don't encode here - http_build_query already did it.
		foreach ( $params as $key => $val ) {
			$canonical  .= $key . '=' . rawurlencode( utf8_decode( urldecode( $val ) ) ) . '&';
		}

		// Remove the trailing ampersand.
		$canonical = preg_replace( '/&$/', '', $canonical );

		// Some common replacements and ones that Amazon specifically mentions.
		$canonical = str_replace( array( ' ', '+', ',', ';' ), array( '%20', '%20', urlencode( ',' ), urlencode( ':' ) ), $canonical );

		// Build the sign.
		$string_to_sign = "GET\n{$urlparts['host']}\n{$urlparts['path']}\n$canonical";

		// Calculate our actual signature and base64 encode it.
		$signature = base64_encode( hash_hmac( 'sha256', $string_to_sign, $secret_key, true ) );

		// Finally re-build the URL with the proper string and include the Signature.
		$url = "{$urlparts['scheme']}://{$urlparts['host']}{$urlparts['path']}?$canonical&Signature=" . rawurlencode( $signature );

		return $url;
	}

	/**
	 * VAT registered sellers - Obtaining the Billing Address.
	 *
	 * @see http://docs.developer.amazonservices.com/en_UK/apa_guide/APAGuide_GetAuthorizationStatus.html
	 *
	 * @param int   $order_id Order ID.
	 * @param array $result   Result from API response.
	 *
	 * @deprecated
	 */
	public static function maybe_update_billing_details( $order_id, $result ) {
		_deprecated_function( 'WC_Amazon_Payments_Advanced_API::maybe_update_billing_details', '1.6.0', 'WC_Amazon_Payments_Advanced_API::update_order_billing_address' );

		// @codingStandardsIgnoreStart
		if ( ! empty( $result->AuthorizationBillingAddress ) ) {
			$address = (array) $result->AuthorizationBillingAddress;

			self::update_order_billing_address( $order_id, $address );
		}
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Get auth state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $id       Reference ID.
	 *
	 * @return string|bool Returns false if failed
	 */
	public static function get_reference_state( $order_id, $id ) {
		if ( $state = get_post_meta( $order_id, 'amazon_reference_state', true ) ) {
			return $state;
		}

		$response = self::request( array(
			'Action'                 => 'GetOrderReferenceDetails',
			'AmazonOrderReferenceId' => $id,
		) );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}
		$state = (string) $response->GetOrderReferenceDetailsResult->OrderReferenceDetails->OrderReferenceStatus->State;
		// @codingStandardsIgnoreEnd

		update_post_meta( $order_id, 'amazon_reference_state', $state );

		return $state;
	}

	/**
	 * Get auth state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $id       Reference ID.
	 *
	 * @return string|bool Returns false if failed.
	 */
	public static function get_authorization_state( $order_id, $id ) {
		if ( $state = get_post_meta( $order_id, 'amazon_authorization_state', true ) ) {
			return $state;
		}

		$response = self::request( array(
			'Action'                => 'GetAuthorizationDetails',
			'AmazonAuthorizationId' => $id,
		) );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}
		$state = (string) $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->State;
		// @codingStandardsIgnoreEnd

		update_post_meta( $order_id, 'amazon_authorization_state', $state );

		self::update_order_billing_address( $order_id, self::get_billing_address_from_response( $response ) );

		return $state;
	}

	/**
	 * Get capture state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $id       Reference ID.
	 *
	 * @return string|bool Returns false if failed.
	 */
	public static function get_capture_state( $order_id, $id ) {
		if ( $state = get_post_meta( $order_id, 'amazon_capture_state', true ) ) {
			return $state;
		}

		$response = self::request( array(
			'Action'          => 'GetCaptureDetails',
			'AmazonCaptureId' => $id,
		) );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}
		$state = (string) $response->GetCaptureDetailsResult->CaptureDetails->CaptureStatus->State;
		// @codingStandardsIgnoreEnd

		update_post_meta( $order_id, 'amazon_capture_state', $state );

		return $state;
	}

	/**
	 * Get order language from order metadata.
	 *
	 * @param string $order_id Order ID.
	 *
	 * @return string
	 */
	public static function get_order_language( $order_id ) {
		return get_post_meta( $order_id, 'amazon_order_language', true );
	}

	/**
	 * Authorize payment against an order reference using 'Authorize' method.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752010
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order.
	 * @param array        $args  Arguments.
	 *
	 * @return bool|WP_Error|SimpleXMLElement Response.
	 */
	public static function authorize( $order, $args = array() ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$order_id = wc_apa_get_order_prop( $order, 'id' );
		$args     = wp_parse_args(
			$args,
			array(
				'capture_now'         => false,
			)
		);

		if ( empty( $args['amazon_reference_id'] ) ) {
			$args['amazon_reference_id'] = get_post_meta( $order_id, 'amazon_reference_id', true );
		}

		if ( ! $args['amazon_reference_id'] ) {
			return new WP_Error( 'order_missing_reference_id', __( 'Order missing Amazon order reference ID.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$response = self::request( self::get_authorize_request_args( $order, $args ) );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( isset( $response->Error->Message ) ) {
			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}

		if ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$code = isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode )
				? (string) $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode
				: '';

			switch ( $code ) {
				case 'InvalidPaymentMethod':
					return new WP_Error( $code, __( 'The selected payment method was declined. Please try different payment method.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				case 'AmazonRejected':
				case 'ProcessingFailure':
				case 'TransactionTimedOut':
					// If the transaction timed out and async authorization is enabled then just return here, leaving
					// the transaction pending. We'll let the calling code trigger an async authorization.
					$authorization_mode = self::get_authorization_mode();
					if ( 'TransactionTimedOut' === $code && 'async' === $authorization_mode ) {
						return new WP_Error( $code );
					}

					// Now we know that we definitely want to cancel the order reference, let's do that and return an
					// appropriate error message.
					$result = self::cancel_order_reference( $order, $code );

					// Invalid order or missing order reference which unlikely
					// to happen, but log in case happens.
					$failed_before_api_request = (
						is_wp_error( $result )
						&&
						in_array( $result->get_error_code(), array( 'invalid_order', 'order_missing_amazon_reference_id' ) )
					);
					if ( $failed_before_api_request ) {
						wc_apa()->log( __METHOD__, sprintf( 'Failed to cancel order reference: %s', $result->get_error_message() ) );
					}

					$redirect_url = add_query_arg(
						array(
							'amazon_payments_advanced' => 'true',
							'amazon_logout'            => 'true',
							'amazon_declined'          => 'true',
						),
						$order->get_cancel_order_url()
					);

					/* translators: placeholder is redirect URL */
					return new WP_Error( $code, sprintf( __( 'There was a problem with the selected payment method. Transaction was declined and order will be cancelled. You will be redirected to cart page automatically, if not please click <a href="%s">here</a>.', 'woocommerce-gateway-amazon-payments-advanced' ), $redirect_url ) );
			}
		}
		// phpcs:enable

		return $response;
	}

	/**
	 * Get args to perform Authorize request.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args  Base args.
	 */
	public static function get_authorize_request_args( WC_Order $order, $args ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		return apply_filters( 'woocommerce_amazon_pa_authorize_request_args', array(
			'Action'                              => 'Authorize',
			'AmazonOrderReferenceId'              => $args['amazon_reference_id'],
			'AuthorizationReferenceId'            => $order_id . '-' . current_time( 'timestamp', true ),
			'AuthorizationAmount.Amount'          => $order->get_total(),
			'AuthorizationAmount.CurrencyCode'    => wc_apa_get_order_prop( $order, 'order_currency' ),
			'CaptureNow'                          => $args['capture_now'],
			'TransactionTimeout'                  => ( isset( $args['transaction_timeout'] ) ) ? $args['transaction_timeout'] : 0,
			'SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
			'SellerOrderAttributes.StoreName'     => WC_Amazon_Payments_Advanced::get_site_name(),
			// 'SellerAuthorizationNote'          => '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"AmazonRejected"}}'
		) );
	}

	/**
	 * Authorize recurring payment against an order reference using
	 * 'AuthorizeOnBillingAgreement' method.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752010
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 * @param array        $args  Whether to immediately capture or not.
	 *
	 * @return bool|WP_Error
	 */
	public static function authorize_recurring( $order, $args = array() ) {
		$order    = wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$order_id = wc_apa_get_order_prop( $order, 'id' );
		$args     = wp_parse_args(
			$args,
			array(
				'amazon_reference_id' => get_post_meta( $order_id, 'amazon_billing_agreement_id', true ),
				'capture_now'         => false,
			)
		);

		if ( ! $args['amazon_billing_agreement_id'] ) {
			return new WP_Error( 'order_missing_billing_agreement_id', __( 'Order missing Amazon billing agreement ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$response = self::request( self::get_authorize_recurring_request_args( $order, $args ) );

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return $response;
	}

	/**
	 * Get args to perform AuthorizeBillingAgreement request.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args  Args.
	 *
	 * @return array Request args.
	 */
	public static function get_authorize_recurring_request_args( WC_Order $order, $args ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		$order_shippable = self::maybe_subscription_is_shippable( $order );

		return array(
			'Action'                              => 'AuthorizeOnBillingAgreement',
			'AmazonBillingAgreementId'            => $args['amazon_billing_agreement_id'],
			'AuthorizationReferenceId'            => $order_id . '-' . current_time( 'timestamp', true ),
			'AuthorizationAmount.Amount'          => $order->get_total(),
			'AuthorizationAmount.CurrencyCode'    => wc_apa_get_order_prop( $order, 'order_currency' ),
			'CaptureNow'                          => $args['capture_now'],
			'TransactionTimeout'                  => 0,
			'SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
			'SellerOrderAttributes.StoreName'     => WC_Amazon_Payments_Advanced::get_site_name(),
			'InheritShippingAddress'              => $order_shippable,
		);
	}

	/**
	 * Authorize payment against an order reference using 'Authorize' method.
	 *
	 * @see: https://payments.amazon.com/documentation/apireference/201752010
	 *
	 * @param int    $order_id            Order ID.
	 * @param string $amazon_reference_id Amazon reference ID.
	 * @param bool   $capture_now         Whether to immediately capture or not.
	 *
	 * @return bool See return value of self::handle_payment_authorization_response.
	 */
	public static function authorize_payment( $order_id, $amazon_reference_id, $capture_now = false ) {
		$response = self::authorize( $order_id, array(
			'amazon_reference_id' => $amazon_reference_id,
			'capture_now'         => $capture_now,
		) );

		return self::handle_payment_authorization_response( $response, $order_id, $capture_now );
	}

	/**
	 * Authorize payment against a billing agreement using 'AuthorizeOnBillingAgreement' method
	 * See: https://payments.amazon.com/documentation/automatic/201752090#201757380
	 *
	 * @param int        $order_id                    Order ID.
	 * @param string     $amazon_billing_agreement_id Reference ID.
	 * @param bool|false $capture_now                 Whether to capture immediately.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, $capture_now = false ) {
		$response = self::authorize_recurring( $order_id, array(
			'amazon_billing_agreement_id' => $amazon_billing_agreement_id,
			'capture_now'                 => $capture_now,
		) );

		return self::handle_payment_authorization_response( $response, $order_id, $capture_now );
	}

	/**
	 * Handle the result of an authorization request.
	 *
	 * @param object       $response    Return value from self::request().
	 * @param int|WC_Order $order       Order object.
	 * @param bool         $capture_now Whether to capture immediately or not.
	 * @param string       $auth_method Deprecated. Which API authorization
	 *                                  method was used (Authorize, or
	 *                                  AuthorizeOnBillingAgreement).
	 *
	 * @return bool Whether or not payment was authorized.
	 */
	public static function handle_payment_authorization_response( $response, $order, $capture_now, $auth_method = null ) {
		$order = wc_get_order( $order );

		if ( null !== $auth_method ) {
			_deprecated_function( 'WC_Amazon_Payments_Advanced_API::handle_payment_authorization_response', '1.6.0', 'Parameter auth_method is not used anymore' );
		}

		if ( is_wp_error( $response ) ) {
			$order->add_order_note( sprintf( __( 'Error: Unable to authorize funds with Amazon. Reason: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );

			return false;
		}

		return self::update_order_from_authorize_response( $order, $response, $capture_now );
	}

	/**
	 * Handle the result of an async ipn order reference request.
	 * We need only to cover the change to Open status.
	 *
	 * https://m.media-amazon.com/images/G/03/AMZNPayments/IntegrationGuide/AmazonPay_-_Order_Confirm_And_Omnichronous_Authorization_Including-IPN-Handler._V516642695_.svg
	 *
	 * @param object       $ipn_payload    IPN payload.
	 * @param int|WC_Order $order          Order object.
	 *
	 * @return string Authorization status.
	 */
	public static function handle_async_ipn_order_reference_payload( $ipn_payload, $order ) {
		$order                 = is_int( $order ) ? wc_get_order( $order ) : $order;
		$order_id              = wc_apa_get_order_prop( $order, 'id' );
		$order_reference_state = (string) $ipn_payload->OrderReference->OrderReferenceStatus->State;

		update_post_meta( $order_id, 'amazon_reference_state', $order_reference_state );

		if ( 'open' === strtolower( $order_reference_state ) ) {
			// New Async Auth
			$order->add_order_note( __( 'Async Authorized attempt.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			$amazon_reference_id = get_post_meta( $order_id, 'amazon_reference_id', true );
			do_action( 'wc_amazon_async_authorize', $order, $amazon_reference_id );
		}
	}

	/**
	 * Handle the result of an async ipn authorization request.
	 * https://m.media-amazon.com/images/G/03/AMZNPayments/IntegrationGuide/AmazonPay_-_Order_Confirm_And_Omnichronous_Authorization_Including-IPN-Handler._V516642695_.svg
	 *
	 * @param object       $ipn_payload    IPN payload.
	 * @param int|WC_Order $order          Order object.
	 *
	 * @return string Authorization status.
	 */
	public static function handle_async_ipn_payment_authorization_payload( $ipn_payload, $order ) {
		$order = is_int( $order ) ? wc_get_order( $order ) : $order;

		$auth_id = self::get_auth_id_from_response( $ipn_payload );
		if ( ! $auth_id ) {
			return false;
		}

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		update_post_meta( $order_id, 'amazon_authorization_id', $auth_id );

		$authorization_status = self::get_auth_state_from_reponse( $ipn_payload );
		switch ( $authorization_status ) {
			case 'open':
				// Updating amazon_authorization_state
				update_post_meta( $order_id, 'amazon_authorization_state', 'Open' );
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
				$order->add_order_note( __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				break;
			case 'closed':
				update_post_meta( $order_id, 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
				update_post_meta( $order_id, 'amazon_authorization_state', $authorization_status );
				$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
				$order->payment_complete();
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				// Close order reference.
				self::close_order_reference( $order_id );
				break;
			case 'declined':
				$state_reason_code = self::get_auth_state_reason_code_from_response( $ipn_payload );
				if ( 'InvalidPaymentMethod' === $state_reason_code ) {
					// Soft Decline
					update_post_meta( $order_id, 'amazon_authorization_state', 'Suspended' );
					$order->add_order_note( sprintf( __( 'Amazon Order Suspended. Email sent to customer to change its payment method.', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
					$subject = __( 'Please update your payment information', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/soft-decline.php', array( 'order_id' => $order_id ), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					wc_apa()->log( __METHOD__, 'EMAILL ' . $message );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'AmazonRejected' === $state_reason_code || 'ProcessingFailure' === $state_reason_code ) {
					// Hard decline
					$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
					// Hard Decline client's email.
					$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'TransactionTimedOut' === $state_reason_code ) {
					// On the second timedout we need to cancel on woo and amazon.
					if ( ! $order->meta_exists( 'amazon_timed_out_times' ) ) {
						$order->update_meta_data( 'amazon_timed_out_times', 1 );
					} else {
						$order->update_meta_data( 'amazon_timed_out_times', 2 );
						// Hard Decline
						$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
						// Hard Decline client's email.
						$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
						$message = wc_get_template_html( 'emails/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
						self::send_email_notification( $subject, $message, $order->get_billing_email() );
						// Delete amazon_timed_out_transaction meta
						$order->delete_meta_data( $order_id, 'amazon_timed_out_transaction' );
						// Cancel amazon order.
						self::cancel_order_reference( $order_id );
					}
					$order->save();
				}

				break;
		}
		return $authorization_status;
	}

	/**
	 * Handle the result of an sync authorization request.
	 *
	 *
	 * @param object       $result         IPN payload.
	 * @param int|WC_Order $order          Order object.
	 *
	 * @return string Authorization status.
	 */
	public static function handle_synch_payment_authorization_payload( $response , $order, $auth_id = false ) {
		$order = is_int( $order ) ? wc_get_order( $order ) : $order;

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		update_post_meta( $order_id, 'amazon_authorization_id', $auth_id );

		$authorization_status = self::get_auth_state_from_reponse( $response );
		wc_apa()->log(
			__METHOD__,
			sprintf( 'Found authorization status of %s on pending synchronous payment', $authorization_status )
		);

		switch ( $authorization_status ) {
			case 'open':
				// Updating amazon_authorization_state
				update_post_meta( $order_id, 'amazon_authorization_state', 'Open' );
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
				$order->add_order_note( __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				break;
			case 'closed':
				update_post_meta( $order_id, 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
				update_post_meta( $order_id, 'amazon_authorization_state', $authorization_status );
				$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
				$order->payment_complete();
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				// Close order reference.
				self::close_order_reference( $order_id );
				break;
			case 'declined':
				$state_reason_code = self::get_auth_state_reason_code_from_response( $response );
				if ( 'InvalidPaymentMethod' === $state_reason_code ) {
					// Soft Decline
					update_post_meta( $order_id, 'amazon_authorization_state', 'Suspended' );
					$order->add_order_note( sprintf( __( 'Amazon Order Suspended. Email sent to customer to change its payment method.', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
					$subject = __( 'Please update your payment information', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/soft-decline.php', array( 'order_id' => $order_id ), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					wc_apa()->log( __METHOD__, 'EMAILL ' . $message );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'AmazonRejected' === $state_reason_code || 'ProcessingFailure' === $state_reason_code ) {
					// Hard decline
					$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
					// Hard Decline client's email.
					$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'TransactionTimedOut' === $state_reason_code ) {
					if ( ! $order->meta_exists( 'amazon_timed_out_times' ) ) {
						$order->update_meta_data( 'amazon_timed_out_times', 1 );
						// Hard Decline
						$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
						// Hard Decline client's email.
						$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
						$message = wc_get_template_html( 'emails/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
						self::send_email_notification( $subject, $message, $order->get_billing_email() );
						// Delete amazon_timed_out_transaction meta
						$order->delete_meta_data( $order_id, 'amazon_timed_out_transaction' );
						// Cancel amazon order.
						self::cancel_order_reference( $order_id );
					}
					$order->save();
				}
				break;
			case 'pending':
				$args = array(
					'order_id'                => $order->get_id(),
					'amazon_authorization_id' => $auth_id,
				);
				// Schedule action to check pending order next hour.
				$next_scheduled_action = as_next_scheduled_action( 'wcga_process_pending_syncro_payments', $args );
				if ( false === $next_scheduled_action || true === $next_scheduled_action ) {
					as_schedule_single_action( strtotime( 'next hour' ) , 'wcga_process_pending_syncro_payments', $args );
				}
				break;
		}
		return $authorization_status;
	}

	/**
	 * Get Authorization ID from reesponse.
	 *
	 * @since 1.6.9
	 *
	 * @param object $response Return value from self::request().
	 *
	 * @return string|bool String of Authorization ID. Otherwise false is returned.
	 */
	public static function get_auth_id_from_response( $response ) {
		$auth_id = false;

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AmazonAuthorizationId ) ) {
			$auth_id = (string) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AmazonAuthorizationId;
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AmazonAuthorizationId ) ) {
			$auth_id = (string) $response->AuthorizeResult->AuthorizationDetails->AmazonAuthorizationId;
		} elseif ( isset( $response->AuthorizationDetails->AmazonAuthorizationId ) ) {
			$auth_id = (string) $response->AuthorizationDetails->AmazonAuthorizationId;
		}
		// @codingStandardsIgnoreEnd

		return $auth_id;
	}

	/**
	 * Get Authorization state from reesponse.
	 *
	 * @since 1.6.9
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return string|bool String of Authorization state.
	 */
	public static function get_auth_state_from_reponse( $response ) {
		$state = 'pending';

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->State );
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->State );
		} elseif ( isset( $response->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->AuthorizationDetails->AuthorizationStatus->State );
		} elseif ( isset( $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->State );
		}
		// @codingStandardsIgnoreEnd

		return $state;
	}

	/**
	 * Get Authorization state reason code from reesponse.
	 *
	 * @see   https://payments.amazon.com/documentation/apireference/201752950
	 * @since 1.6.9
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return string|bool String of Authorization state.
	 */
	public static function get_auth_state_reason_code_from_response( $response ) {
		$reason_code = 'Unknown';

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		} elseif ( isset( $response->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		} elseif ( isset( $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		}
		// @codingStandardsIgnoreEnd

		return $reason_code;
	}

	/**
	 * Get billing address from response.
	 *
	 * @since 1.6.0
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return array Billing address.
	 */
	public static function get_billing_address_from_response( $response ) {
		$details = array();

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationBillingAddress ) ) {
			$details = (array) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationBillingAddress;
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationBillingAddress ) ) {
			$details = (array) $response->AuthorizeResult->AuthorizationDetails->AuthorizationBillingAddress;
		}
		// @codingStandardsIgnoreEnd

		return $details;
	}

	/**
	 * Get Amazon Order ID from response.
	 *
	 * @since 1.6.0
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return string Amazon Order ID.
	 */
	public static function get_reference_id_from_response( $response ) {
		$details = false;

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AmazonOrderReferenceId ) ) {
			$details = (string) $response->AuthorizeOnBillingAgreementResult->AmazonOrderReferenceId;
		}
		// @codingStandardsIgnoreEnd

		return $details;
	}

	/**
	 * Update order billing address.
	 *
	 * @since 1.6.0
	 *
	 * @param int   $order_id Order ID.
	 * @param array $address  Billing address.
	 *
	 * @return bool
	 */
	public static function update_order_billing_address( $order_id, $address = array() ) {
		// Format address and map to WC fields.
		$address_lines = array();

		if ( ! empty( $address['AddressLine1'] ) ) {
			$address_lines[] = $address['AddressLine1'];
		}
		if ( ! empty( $address['AddressLine2'] ) ) {
			$address_lines[] = $address['AddressLine2'];
		}
		if ( ! empty( $address['AddressLine3'] ) ) {
			$address_lines[] = $address['AddressLine3'];
		}

		if ( 3 === sizeof( $address_lines ) ) {
			update_post_meta( $order_id, '_billing_company', $address_lines[0] );
			update_post_meta( $order_id, '_billing_address_1', $address_lines[1] );
			update_post_meta( $order_id, '_billing_address_2', $address_lines[2] );
		} elseif ( 2 === sizeof( $address_lines ) ) {
			update_post_meta( $order_id, '_billing_address_1', $address_lines[0] );
			update_post_meta( $order_id, '_billing_address_2', $address_lines[1] );
		} elseif ( sizeof( $address_lines ) ) {
			update_post_meta( $order_id, '_billing_address_1', $address_lines[0] );
		}

		if ( isset( $address['City'] ) ) {
			update_post_meta( $order_id, '_billing_city', $address['City'] );
		}

		if ( isset( $address['PostalCode'] ) ) {
			update_post_meta( $order_id, '_billing_postcode', $address['PostalCode'] );
		}

		if ( isset( $address['StateOrRegion'] ) ) {
			update_post_meta( $order_id, '_billing_state', $address['StateOrRegion'] );
		}

		if ( isset( $address['CountryCode'] ) ) {
			update_post_meta( $order_id, '_billing_country', $address['CountryCode'] );
		}

		return true;
	}

	/**
	 * Update order from authorization response.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order       Order object.
	 * @param Object   $response    Response from self::request.
	 * @param bool     $capture_now Whether to capture immediately.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function update_order_from_authorize_response( $order, $response, $capture_now = false ) {
		$auth_id = self::get_auth_id_from_response( $response );
		if ( ! $auth_id ) {
			return false;
		}
		$amazon_reference_id = self::get_reference_id_from_response( $response );

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		update_post_meta( $order_id, 'amazon_authorization_id', $auth_id );

		// This is true only on AuthorizeOnBillingAgreement, for the recurring payments.
		if ( $amazon_reference_id ) {
			update_post_meta( $order_id, 'amazon_reference_id', $amazon_reference_id );
		}
		self::update_order_billing_address( $order_id, self::get_billing_address_from_response( $response ) );

		$state = self::get_auth_state_from_reponse( $response );
		if ( 'declined' === $state ) {
			$order->add_order_note( sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), self::get_auth_state_reason_code_from_response( $response ) ) );
			// Payment was not authorized.
			return false;
		}

		if ( $capture_now ) {
			update_post_meta( $order_id, 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );

			$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
		} else {
			$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
		}

		return true;
	}

	/**
	 * Cancels a previously confirmed order reference.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Order $order  WC Order object.
	 * @param string   $reason Reason for the cancellation.
	 *
	 * @return bool|WP_Error Return true when succeed. Otherwise WP_Error is returned.
	 */
	public static function cancel_order_reference( $order, $reason = '' ) {
		$order    = wc_get_order( $order );
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$amazon_reference_id = get_post_meta( $order_id, 'amazon_reference_id', true );
		if ( ! $amazon_reference_id ) {
			return new WP_Error( 'order_missing_amazon_reference_id', __( 'Order missing Amazon reference ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$request_args = array(
			'Action'                 => 'CancelOrderReference',
			'AmazonOrderReferenceId' => $amazon_reference_id,
		);

		if ( $reason ) {
			$request_args['CancelationReason'] = $reason;
		}

		$response = self::request( $request_args );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( isset( $response->Error->Message ) ) {
			$order->add_order_note( (string) $response->Error->Message );

			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return true;
	}

	/**
	 * Close order reference.
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 *
	 * @return bool|WP_Error Return true when succeed. Otherwise WP_Error is returned
	 */
	public static function close_order_reference( $order ) {
		$order    = wc_get_order( $order );
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$amazon_reference_id = get_post_meta( $order_id, 'amazon_reference_id', true );
		if ( ! $amazon_reference_id ) {
			return new WP_Error( 'order_missing_amazon_reference_id', __( 'Order missing Amazon reference ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$amazon_billing_agreement_id = get_post_meta( $order_id, 'amazon_billing_agreement_id', true );
		if ( $amazon_billing_agreement_id ) {
			// If it has a billing agreement Amazon close it on auth, no need to close the order.
			// https://developer.amazon.com/docs/amazon-pay-api/order-reference-states-and-reason-codes.html
			$order->add_order_note( sprintf( __( 'Order reference %s closed ', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_reference_id ) );
			return true;
		}

		$response = self::request( array(
			'Action'                 => 'CloseOrderReference',
			'AmazonOrderReferenceId' => $amazon_reference_id,
		) );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( isset( $response->Error->Message ) ) {
			$order->add_order_note( (string) $response->Error->Message );

			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		} else {
			$order->add_order_note( sprintf( __( 'Order reference %s closed ', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_reference_id ) );
		}
		// @codingStandardsIgnoreEnd

		return true;
	}

	/**
	 * Close authorization.
	 *
	 * @param int    $order_id                Order ID.
	 * @param string $amazon_authorization_id Authorization ID.
	 *
	 * @return bool|WP_Error True if succeed. Otherwise WP_Error is returned
	 */
	public static function close_authorization( $order_id, $amazon_authorization_id ) {
		$order = new WC_Order( $order_id );

		if ( 'amazon_payments_advanced' == wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			$response = self::request( array(
				'Action'                => 'CloseAuthorization',
				'AmazonAuthorizationId' => $amazon_authorization_id,
			) );

			// @codingStandardsIgnoreStart
			if ( is_wp_error( $response ) ) {
				$ret = $response;
			} elseif ( isset( $response->Error->Message ) ) {
				$order->add_order_note( (string) $response->Error->Message );
				$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
				$ret = new WP_Error( $code, (string) $response->Error->Message );
			} else {
				delete_post_meta( $order_id, 'amazon_authorization_id' );

				$order->add_order_note( sprintf( __( 'Authorization closed (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_authorization_id ) );
				$ret = true;
			}
			// @codingStandardsIgnoreEnd
		} else {
			$ret = new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		return $ret;
	}

	/**
	 * Capture payment.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752040
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order.
	 * @param array        $args  Whether to immediately capture or not.
	 *
	 * @return bool|WP_Error
	 */
	public static function capture( $order, $args = array() ) {
		$order    = wc_get_order( $order );
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'amazon_authorization_id' => get_post_meta( $order_id, 'amazon_authorization_id', true ),
				'capture_now'             => false,
			)
		);

		if ( ! $args['amazon_authorization_id'] ) {
			return new WP_Error( 'order_missing_authorization_id', __( 'Order missing Amazon authorization ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$response = self::request( self::get_capture_request_args( $order, $args ) );

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return $response;
	}

	/**
	 * Get args to perform Capture request.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args  Base args.
	 *
	 * @return array
	 */
	public static function get_capture_request_args( WC_Order $order, $args ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		return array(
			'Action'                     => 'Capture',
			'AmazonAuthorizationId'      => $args['amazon_authorization_id'],
			'CaptureReferenceId'         => $order_id . '-' . current_time( 'timestamp', true ),
			'CaptureAmount.Amount'       => $order->get_total(),
			'CaptureAmount.CurrencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
		);
	}

	/**
	 * Capture payment
	 *
	 * @param int    $order_id                Order ID.
	 * @param string $amazon_authorization_id Optional Amazon authorization ID.
	 *                                        If not provided, value from order
	 *                                        meta will be used.
	 */
	public static function capture_payment( $order_id, $amazon_authorization_id = null ) {
		$response = self::capture( $order_id, array(
			'amazon_authorization_id' => $amazon_authorization_id,
		) );

		return self::handle_payment_capture_response( $response, $order_id );
	}

	/**
	 * Handle the result of a capture request.
	 *
	 * @since 1.6.0
	 *
	 * @param object       $response Response from self::request().
	 * @param int|WC_Order $order    Order ID or object.
	 *
	 * @return bool whether or not payment was captured.
	 */
	public static function handle_payment_capture_response( $response, $order ) {
		$order = wc_get_order( $order );

		if ( is_wp_error( $response ) ) {
			$order->add_order_note( sprintf( __( 'Error: Unable to capture funds with Amazon Pay. Reason: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );

			return false;
		}

		return self::update_order_from_capture_response( $order, $response );
	}

	/**
	 * Update order from capture response.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order    Order object.
	 * @param Object   $response Response from self::request.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function update_order_from_capture_response( $order, $response ) {
		// @codingStandardsIgnoreStart
		$capture_id = (string) $response->CaptureResult->CaptureDetails->AmazonCaptureId;
		$order_id   = wc_apa_get_order_prop( $order, 'id' );
		if ( ! $capture_id ) {
			return false;
		}
		// @codingStandardsIgnoreEnd

		$order->add_order_note( sprintf( __( 'Capture Attempted (Capture ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $capture_id ) );

		update_post_meta( $order_id, 'amazon_capture_id', $capture_id );

		$order->payment_complete();

		return true;
	}

	/**
	 * Refund a payment
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $capture_id Refund ID.
	 * @param float  $amount     Amount to refund.
	 * @param stirng $note       Refund note.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function refund_payment( $order_id, $capture_id, $amount, $note ) {
		$order = new WC_Order( $order_id );
		$ret   = false;

		if ( 'amazon_payments_advanced' === wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			if ( 'US' == WC()->countries->get_base_country() && $amount > $order->get_total() ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), __( 'Refund amount is greater than order total.', 'woocommerce-gateway-amazon-payments-advanced' ) ) );

				return false;
			} elseif ( $amount > min( ( $order->get_total() * 1.15 ), ( $order->get_total() + 75 ) ) ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), __( 'Refund amount is greater than the max refund amount.', 'woocommerce-gateway-amazon-payments-advanced' ) ) );

				return false;
			}

			$response = self::request( array(
				'Action'                    => 'Refund',
				'AmazonCaptureId'           => $capture_id,
				'RefundReferenceId'         => $order_id . '-' . current_time( 'timestamp', true ),
				'RefundAmount.Amount'       => $amount,
				'RefundAmount.CurrencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
				'SellerRefundNote'          => $note,
			) );

			// @codingStandardsIgnoreStart
			if ( is_wp_error( $response ) ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );
			} elseif ( isset( $response->Error->Message ) ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), (string) $response->Error->Message ) );
			} else {
				$refund_id = (string) $response->RefundResult->RefundDetails->AmazonRefundId;

				/* Translators: 1: refund amount, 2: refund note */
				$order->add_order_note( sprintf( __( 'Refunded %1$s (%2$s)', 'woocommerce-gateway-amazon-payments-advanced' ), wc_price( $amount ), $note ) );

				add_post_meta( $order_id, 'amazon_refund_id', $refund_id );

				$ret = true;
			}
			// @codingStandardsIgnoreEnd
		}

		return $ret;
	}

	/**
	 * Get order ID from reference ID.
	 *
	 * @param string $reference_id Reference ID.
	 *
	 * @return int Order ID.
	 */
	public static function get_order_id_from_reference_id( $reference_id ) {
		global $wpdb;

		$order_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key = 'amazon_reference_id'
			AND meta_value = %s
		", $reference_id ) );

		if ( ! is_wp_error( $order_id ) ) {
			return $order_id;
		}

		return 0;
	}

	/**
	 * Get reference state.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $state    State to retrieve.
	 *
	 * @return string Reference state.
	 */
	public static function get_order_ref_state( $order_id, $state = 'amazon_reference_state' ) {
		$ret_state = '';

		switch ( $state ) {
			case 'amazon_reference_state':
				$ref_id = get_post_meta( $order_id, 'amazon_reference_id', true );
				if ( $ref_id ) {
					$ret_state = self::get_reference_state( $order_id, $ref_id );
				}
				break;

			case 'amazon_authorization_state':
				$ref_id = get_post_meta( $order_id, 'amazon_authorization_id', true );
				if ( $ref_id ) {
					$ret_state = self::get_authorization_state( $order_id, $ref_id );
				}
				break;

			case 'amazon_capture_state':
				$ref_id = get_post_meta( $order_id, 'amazon_capture_id', true );
				if ( $ref_id ) {
					$ret_state = self::get_capture_state( $order_id, $ref_id );
				}
				break;
		}

		return $ret_state;
	}

	/**
	 * Remove address fields that have a string value of "undefined".
	 *
	 * @param array $address Address object from Amazon Pay API.
	 */
	private static function remove_undefined_strings( $address ) {
		if ( ! $address instanceof SimpleXMLElement ) {
			return;
		}
		$nodes_to_remove = array();
		foreach ( $address->children() as $child ) {
			if ( 'undefined' === (string) $child ) {
				array_push( $nodes_to_remove, $child );
			}
		}
		foreach ( $nodes_to_remove as $node ) {
			unset( $node[0] );
		}
	}

	/**
	 * Format an Amazon Pay Name for WooCommerce.
	 *
	 * @param string $name Amazon Pay full name field.
	 */
	public static function format_name( $name ) {
		// $name could be empty for non-Login app clients. Set both first and last name as '' in those cases.
		if ( empty( $name ) ) {
			return array(
				'first_name' => '',
				'last_name'  => '',
			);
		}
		// Use fallback value for the last name to avoid field required errors.
		$last_name_fallback = '.';
		$names              = explode( ' ', $name );
		return array(
			'first_name' => array_shift( $names ),
			'last_name'  => empty( $names ) ? $last_name_fallback : implode( ' ', $names ),
		);
	}

	/**
	 * Format an Amazon Pay Address DataType for WooCommerce.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752430
	 *
	 * @param array $address Address object from Amazon Pay API.
	 *
	 * @return array Address formatted for WooCommerce.
	 */
	public static function format_address( $address ) {
		// Some address fields could have a string value of "undefined", causing issues when formatting it.
		self::remove_undefined_strings( $address );

		// Get first and last names.
		// @codingStandardsIgnoreStart
		$address_name = ! empty( $address->Name ) ? (string) $address->Name : '';
		// @codingStandardsIgnoreEnd
		$formatted = self::format_name( $address_name );

		// Special handling for German speaking countries.
		//
		// @see https://github.com/woothemes/woocommerce-gateway-amazon-payments-advanced/issues/25
		// @codingStandardsIgnoreStart
		if ( ! empty( $address->CountryCode ) && in_array( $address->CountryCode, array( 'AT', 'DE' ) ) ) {

			if ( ! empty( $address->AddressLine3 ) ) {

				$formatted['company']   = trim( (string) $address->AddressLine1 . ' ' . (string) $address->AddressLine2 );
				$formatted['address_1'] = (string) $address->AddressLine3;

			} elseif ( ! empty( $address->AddressLine2 ) ) {

				$formatted['company']   = (string) $address->AddressLine1;
				$formatted['address_1'] = (string) $address->AddressLine2;

			} else {

				$formatted['address_1'] = (string) $address->AddressLine1;

			}

		} else {

			// Format address and map to WC fields
			$address_lines = array();

			if ( ! empty( $address->AddressLine1 ) ) {
				$address_lines[] = (string) $address->AddressLine1;
			}
			if ( ! empty( $address->AddressLine2 ) ) {
				$address_lines[] = (string) $address->AddressLine2;
			}
			if ( ! empty( $address->AddressLine3 ) ) {
				$address_lines[] = (string) $address->AddressLine3;
			}

			if ( 3 === sizeof( $address_lines ) ) {

				$formatted['company']   = $address_lines[0];
				$formatted['address_1'] = $address_lines[1];
				$formatted['address_2'] = $address_lines[2];

			} elseif ( 2 === sizeof( $address_lines ) ) {

				$formatted['address_1'] = $address_lines[0];
				$formatted['address_2'] = $address_lines[1];

			} elseif ( sizeof( $address_lines ) ) {
				$formatted['address_1'] = $address_lines[0];
			}

		}

		$formatted['phone'] = isset( $address->Phone ) ? (string) $address->Phone : null;
		$formatted['city'] = isset( $address->City ) ? (string) $address->City : null;
		$formatted['postcode'] = isset( $address->PostalCode ) ? (string) $address->PostalCode : null;
		$formatted['state'] = isset( $address->StateOrRegion ) ? (string) $address->StateOrRegion : null;
		$formatted['country'] = isset( $address->CountryCode ) ? (string) $address->CountryCode : null;
		// @codingStandardsIgnoreEnd

		return array_filter( $formatted );

	}

	/**
	 * Send an email notification to the recipient in the woocommerce mail template.
	 *
	 * @param string $subject
	 * @param string $message
	 * @param string $recipient
	 */
	public static function send_email_notification( $subject, $message, $recipient ) {
		$mailer  = WC()->mailer();
		$message = $mailer->wrap_message( $subject, $message );
		$mailer->send( $recipient, strip_tags( $subject ), $message );
	}

	/**
	 * Return if subscription is shippable
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return boolean
	 */
	public static function maybe_subscription_is_shippable( WC_Order $order ) {

		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		$items = $order->get_items();
		if ( empty( $items ) ) {
			return false;
		}

		$order_shippable = false;
		foreach ( $items as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$product = $item->get_product();
				if ( $product && WC_Subscriptions_Product::is_subscription( $product ) && $product->needs_shipping() ) {
					$order_shippable = true;
					break;
				}
			}
		}

		return $order_shippable;
	}
}
