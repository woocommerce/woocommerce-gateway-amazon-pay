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
	 * Merchant identifier of the Solution Provider (SP).
	 *
	 * @see https://developer.amazon.com/docs/amazon-pay-api-v2/checkout-session.html#ERL9CA7OsPD
	 */
	const AMAZON_PAY_FOR_WOOCOMMERCE_SP_ID = 'A1BVJDFFHQ7US4';

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
	 * @param  string $key Key, if retrieving a single key.
	 *
	 * @return array|mixed
	 */
	public static function get_settings( $key = null ) {
		$settings_options_name = 'woocommerce_amazon_payments_advanced_settings';

		$settings = (array) get_option( $settings_options_name, array() );
		$default  = array(
			'enabled'                         => 'yes',
			'title'                           => __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
			'description'                     => __( 'Complete your payment using Amazon Pay!', 'woocommerce-gateway-amazon-payments-advanced' ),
			'merchant_id'                     => '',
			'store_id'                        => '',
			'public_key_id'                   => '',
			'seller_id'                       => '',
			'mws_access_key'                  => '',
			'secret_key'                      => '',
			'payment_region'                  => self::get_payment_region_from_country( WC()->countries->get_base_country() ),
			'enable_login_app'                => ( self::is_new_installation() ) ? 'yes' : 'no',
			'app_client_id'                   => '',
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
			'mini_cart_button'                => 'no',
			'product_button'                  => 'no',
			'alexa_notifications_support'     => 'no',
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
		// Take into consideration external multi-currency plugins when not supported multicurrency region.
		$currency = apply_filters( 'woocommerce_amazon_pa_active_currency', get_option( 'woocommerce_currency' ) );

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
	 * @param  string $region Region, if checking for a specific region. If not defined, will get label for current region.
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
	 * VAT registered sellers - Obtaining the Billing Address.
	 *
	 * @see http://docs.developer.amazonservices.com/en_UK/apa_guide/APAGuide_GetAuthorizationStatus.html
	 *
	 * @param int    $order_id Order ID.
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
	 * Remove address fields that have a string value of "undefined".
	 *
	 * @param SimpleXMLElement $address Address object from Amazon Pay API.
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

			$address_parts = array_filter( array(
				(string) $address->AddressLine1,
				(string) $address->AddressLine2,
				(string) $address->AddressLine3)
			);
			$formatted['address_1'] = array_pop( $address_parts );
			$formatted['company'] = implode( ' ', $address_parts );

		} elseif ( ! empty( $address->CountryCode ) && in_array( $address->CountryCode, array( 'JP' ) ) ) {

			if ( ! empty( $address->AddressLine1 ) ) {
				$formatted['address_1'] = (string) $address->AddressLine1;
			}

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

			if ( 3 === count( $address_lines ) ) {

				$formatted['company']   = $address_lines[0];
				$formatted['address_1'] = $address_lines[1];
				$formatted['address_2'] = $address_lines[2];

			} elseif ( 2 === count( $address_lines ) ) {

				$formatted['address_1'] = $address_lines[0];
				$formatted['address_2'] = $address_lines[1];

			} elseif ( count( $address_lines ) ) {
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

			if ( ! empty( $valid_states ) && is_array( $valid_states ) ) {
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
	 * Validate API Keys signature
	 *
	 * @return bool
	 */
	public static function validate_api_keys() {
		return false;
	}

}
