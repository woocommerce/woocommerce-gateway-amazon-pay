<?php
/**
 * Amazon API abstract class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay API abstract class
 */
abstract class WC_Amazon_Payments_Advanced_API_Abstract {

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
		'sandbox'    => array(
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
	 * Language ISO code map to its domain.
	 *
	 * @var array
	 */
	public static $lang_domains_mapping = array(
		'en-GB' => 'co.uk',
		'de-DE' => 'de',
		'fr-FR' => 'fr',
		'it-IT' => 'it',
		'es-ES' => 'es',
		'en-US' => 'com',
		'ja-JP' => 'co.jp',
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
	public static $sp_ids = array(
		'us' => 'A1BVJDFFHQ7US4',
		'gb' => 'A3AO8502KEOZS3',
		'eu' => 'A3V6YX13IG1QFQ',
		'jp' => 'A2EBW2CGZKMGE4',
	);

	/**
	 * Simple Onboarding Version.
	 *
	 * @var int
	 */
	public static $onboarding_version = 2;

	/**
	 * Get settings
	 *
	 * @return array
	 */
	public static function get_settings( $key = null ) {
		$settings_options_name = 'woocommerce_amazon_payments_advanced_settings';

		$settings = (array) get_option( $settings_options_name, array() );
		$default  = array(
			'enabled'                         => 'yes',
			'title'                           => __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
			'merchant_id'                     => '',
			'store_id'                        => '',
			'public_key_id'                   => '',
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
			'subscriptions_enabled'           => 'yes',
		);

		$settings = apply_filters( 'woocommerce_amazon_pa_settings', array_merge( $default, $settings ) );

		if ( is_null( $key ) ) {
			return $settings;
		} else {
			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}
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
				if ( in_array( $country, WC()->countries->get_european_union_countries(), true ) ) {
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
		$region = self::get_region();
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
	 * Get the label of payment region from setting.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return string Payment region label.
	 */
	public static function get_region_label( $region = null ) {
		if ( is_null( $region ) ) {
			$region = self::get_region();
		}
		$regions = self::get_payment_regions();
		return isset( $regions[ $region ] ) ? $regions[ $region ] : '';
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
		return isset( $settings['currencies_supported'] ) ? $settings['currencies_supported'] : array();
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
		wc_apa()->log( sprintf( 'GET: %s', self::sanitize_remote_request_log( $url ) ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$response        = self::safe_load_xml( $response['body'], LIBXML_NOCDATA );
			$logged_response = self::sanitize_remote_response_log( $response );

			wc_apa()->log( sprintf( 'Response: %s', $logged_response ) );
		} else {
			wc_apa()->log( sprintf( 'Error: %s', $response->get_error_message() ) );
		}

		return $response;
	}

	/**
	 * Sanitize log message.
	 *
	 * Used to sanitize logged HTTP response message.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/133
	 * @since 1.6.0
	 *
	 * @param mixed $message Log message.
	 *
	 * @return string Sanitized log message.
	 */
	public static function sanitize_remote_response_log( $message ) {
		if ( ! is_a( $message, 'SimpleXMLElement' ) ) {
			return (string) $message;
		}

		if ( ! is_callable( array( $message, 'asXML' ) ) ) {
			return '';
		}

		$message = $message->asXML();

		// Sanitize response message.
		$patterns    = array();
		$patterns[0] = '/(<Buyer>)(.+)(<\/Buyer>)/ms';
		$patterns[1] = '/(<PhysicalDestination>)(.+)(<\/PhysicalDestination>)/ms';
		$patterns[2] = '/(<BillingAddress>)(.+)(<\/BillingAddress>)/ms';
		$patterns[3] = '/(<SellerNote>)(.+)(<\/SellerNote>)/ms';
		$patterns[4] = '/(<AuthorizationBillingAddress>)(.+)(<\/AuthorizationBillingAddress>)/ms';
		$patterns[5] = '/(<SellerAuthorizationNote>)(.+)(<\/SellerAuthorizationNote>)/ms';
		$patterns[6] = '/(<SellerCaptureNote>)(.+)(<\/SellerCaptureNote>)/ms';
		$patterns[7] = '/(<SellerRefundNote>)(.+)(<\/SellerRefundNote>)/ms';

		$replacements    = array();
		$replacements[0] = '$1 REMOVED $3';
		$replacements[1] = '$1 REMOVED $3';
		$replacements[2] = '$1 REMOVED $3';
		$replacements[3] = '$1 REMOVED $3';
		$replacements[4] = '$1 REMOVED $3';
		$replacements[5] = '$1 REMOVED $3';
		$replacements[6] = '$1 REMOVED $3';
		$replacements[7] = '$1 REMOVED $3';

		return preg_replace( $patterns, $replacements, $message );
	}

	/**
	 * Sanitize logged request.
	 *
	 * Used to sanitize logged HTTP request message.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/133
	 * @since 1.6.0
	 *
	 * @param string $message Log message from stringified array structure.
	 *
	 * @return string Sanitized log message
	 */
	public static function sanitize_remote_request_log( $message ) {
		$patterns    = array();
		$patterns[0] = '/(AWSAccessKeyId=)(.+)(&)/ms';
		$patterns[0] = '/(SellerNote=)(.+)(&)/ms';
		$patterns[1] = '/(SellerAuthorizationNote=)(.+)(&)/ms';
		$patterns[2] = '/(SellerCaptureNote=)(.+)(&)/ms';
		$patterns[3] = '/(SellerRefundNote=)(.+)(&)/ms';

		$replacements    = array();
		$replacements[0] = '$1REMOVED$3';
		$replacements[1] = '$1REMOVED$3';
		$replacements[2] = '$1REMOVED$3';
		$replacements[3] = '$1REMOVED$3';

		return preg_replace( $patterns, $replacements, $message );
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
		$urlparts = wp_parse_url( $url );

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
			$canonical .= $key . '=' . rawurlencode( utf8_decode( urldecode( $val ) ) ) . '&';
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
	 * @param object $result   Result from API response.
	 *
	 * @deprecated
	 */
	public static function maybe_update_billing_details( $order_id, $result ) {
		_deprecated_function( 'WC_Amazon_Payments_Advanced_API::maybe_update_billing_details', '1.6.0', 'WC_Amazon_Payments_Advanced_API_Legacy::update_order_billing_address' );

		// @codingStandardsIgnoreStart
		if ( ! empty( $result->AuthorizationBillingAddress ) ) {
			$address = (array) $result->AuthorizationBillingAddress;

			WC_Amazon_Payments_Advanced_API_Legacy::update_order_billing_address( $order_id, $address );
		}
		// @codingStandardsIgnoreEnd
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
			if ( 'US' === WC()->countries->get_base_country() && $amount > $order->get_total() ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), __( 'Refund amount is greater than order total.', 'woocommerce-gateway-amazon-payments-advanced' ) ) );

				return false;
			} elseif ( $amount > min( ( $order->get_total() * 1.15 ), ( $order->get_total() + 75 ) ) ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), __( 'Refund amount is greater than the max refund amount.', 'woocommerce-gateway-amazon-payments-advanced' ) ) );

				return false;
			}

			$response = self::request(
				array(
					'Action'                    => 'Refund',
					'AmazonCaptureId'           => $capture_id,
					'RefundReferenceId'         => $order_id . '-' . time(),
					'RefundAmount.Amount'       => $amount,
					'RefundAmount.CurrencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
					'SellerRefundNote'          => $note,
				)
			);

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

		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT post_id
			FROM $wpdb->postmeta
			WHERE meta_key = 'amazon_reference_id'
			AND meta_value = %s
		",
				$reference_id
			)
		);

		if ( ! is_wp_error( $order_id ) ) {
			return $order_id;
		}

		return 0;
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
	 * @param object $address Address object from Amazon Pay API.
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

		} elseif ( ! empty( $address->CountryCode ) && in_array( $address->CountryCode, array( 'JP' ) ) ) {

			$formatted['address_1'] = (string) $address->AddressLine1;

			if ( ! empty( $address->AddressLine2 ) ) {
				$formatted['address_2'] = (string) $address->AddressLine2;
			}

			if ( ! empty( $address->AddressLine3 ) ) {
				$formatted['company']   = (string) $address->AddressLine3;
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
		if ( ! empty( $address->CountryCode ) && in_array( $address->CountryCode, array( 'JP' ) ) ) {
			if ( empty( $formatted['city'] ) ) {
				$formatted['city'] = ''; // Force empty city
			}
		}
		$formatted['postcode'] = isset( $address->PostalCode ) ? (string) $address->PostalCode : null;
		$formatted['state'] = isset( $address->StateOrRegion ) ? (string) $address->StateOrRegion : null;
		$formatted['country'] = isset( $address->CountryCode ) ? (string) $address->CountryCode : null;

		// Handle missmatches of states in AMZ and WC
		if ( ! is_null( $formatted['state'] ) ) {
			$valid_states = WC()->countries->get_states( $formatted['country'] );

			if ( ! empty( $valid_states ) && is_array( $valid_states ) && count( $valid_states ) > 0 ) {
				$valid_state_values = array_map( 'wc_strtoupper', array_flip( array_map( 'wc_strtoupper', $valid_states ) ) );
				$uc_state       = wc_strtoupper( $formatted['state'] );

				if ( isset( $valid_state_values[ $uc_state ] ) ) {
					// With this part we consider state value to be valid as well, convert it to the state key for the valid_states check below.
					$uc_state = $valid_state_values[ $uc_state ];
				}

				if ( ! in_array( $uc_state, $valid_state_values, true ) ) {
					$formatted['state'] = null;
				} else {
					$formatted['state'] = $uc_state;
				}
			}
		}
		// @codingStandardsIgnoreEnd

		$formatted = array_filter(
			$formatted,
			function( $v ) {
				return ! is_null( $v );
			}
		);

		return $formatted;

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
		$mailer->send( $recipient, wp_strip_all_tags( $subject ), $message );
	}

	public static function validate_api_keys() {
		return false;
	}

}
