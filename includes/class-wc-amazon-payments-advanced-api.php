<?php
/**
 * Amazon API class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay API class
 */
class WC_Amazon_Payments_Advanced_API extends WC_Amazon_Payments_Advanced_API_Abstract {

	/**
	 * Helper to store the current refund being handled
	 *
	 * @var array
	 */
	protected static $amazonpay_sdk_config;

	/**
	 * Helper to store the current refund being handled
	 *
	 * @var \Amazon\Pay\API\Client
	 */
	protected static $amazonpay_client;

	/**
	 * Set up API V2 SDK.
	 *
	 * @since 2.0.0
	 * @param  bool $fresh Force refresh, or get from cache.
	 *
	 * @return array Returns SDK configuration
	 */
	protected static function get_amazonpay_sdk_config( $fresh = false ) {
		if ( $fresh || empty( self::$amazonpay_sdk_config ) ) {
			$settings = self::get_settings();
			$region   = $settings['payment_region']; // TODO: Maybe normalize v1 and v2 different region management.
			if ( 'gb' === $region ) {
				$region = 'eu';
			}

			self::$amazonpay_sdk_config = array(
				'public_key_id' => $settings['public_key_id'],
				'private_key'   => get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, false ),
				'sandbox'       => 'yes' === $settings['sandbox'] ? true : false,
				'region'        => $region,
			);
		}
		return self::$amazonpay_sdk_config;
	}

	/**
	 * Validate API keys when settings are updated.
	 *
	 * @since 2.0.0
	 *
	 * @return bool Returns true if API keys are valid
	 *
	 * @throws Exception On Errors.
	 */
	public static function validate_api_keys() {

		$settings = self::get_settings();

		$ret            = false;
		$valid_settings = self::validate_api_settings();
		if ( is_wp_error( $valid_settings ) ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			WC_Admin_Settings::add_error( $valid_settings->get_error_message() );
			return $ret;
		}

		try {
			$client  = self::get_client();
			$payload = self::create_checkout_session_params();

			$headers = array( 'x-amz-pay-Idempotency-Key' => uniqid() );
			$result  = $client->createCheckoutSession( $payload, $headers );
			if ( ! isset( $result['status'] ) || 201 !== $result['status'] ) {
				throw new Exception( __( 'Error: API is not responding.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
			$ret = true;
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 1 );

			do_action( 'wc_amazon_keys_setup_and_validated', '2' );

		} catch ( Exception $e ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			WC_Admin_Settings::add_error( $e->getMessage() );
		}
		return $ret;
	}

	/**
	 * Enables Alexa Notifications for an order specified in $payload.
	 *
	 * @param array $payload Data passed to Amazon to Enable delivery notifications on the user's Alexa.
	 * @return array
	 */
	public static function trigger_alexa_notifications( $payload ) {
		return self::get_client()->deliveryTrackers( $payload );
	}

	/**
	 * Add Amazon reference information in order item response.
	 *
	 * @since 2.0.0
	 *
	 * @return WP_Error|bool Error
	 */
	public static function validate_api_settings() {
		$settings = self::get_settings();

		if ( empty( $settings['merchant_id'] ) ) {
			return new WP_Error( 'missing_merchant_id', __( 'Error: You must enter a Merchant ID.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( empty( $settings['public_key_id'] ) ) {
			return new WP_Error( 'missing_public_key_id', __( 'Error: You must enter a Public Key Id.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
		if ( empty( $settings['store_id'] ) ) {
			return new WP_Error( 'missing_store_id', __( 'Error: You must enter a Store Id.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$private_key = get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, false );
		if ( empty( $private_key ) ) {
			return new WP_Error( 'missing_private_key', __( 'Error: You must add the private key file.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		return true;
	}

	/**
	 * Get Amazon Pay SDK Client
	 *
	 * @since 2.0.0
	 *
	 * @return Amazon\Pay\API\Client Instance
	 */
	protected static function get_client() {
		if ( isset( self::$amazonpay_client ) ) {
			return self::$amazonpay_client;
		}
		include_once wc_apa()->path . '/vendor/autoload.php';

		self::$amazonpay_client = new Amazon\Pay\API\Client( self::get_amazonpay_sdk_config( true ) );

		return self::$amazonpay_client;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private static function location_is_continent( $location ) {
		return 'continent' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private static function location_is_country( $location ) {
		return 'country' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private static function location_is_state( $location ) {
		return 'state' === $location->type;
	}

	/**
	 * Location type detection.
	 *
	 * @param  object $location Location to check.
	 * @return boolean
	 */
	private static function location_is_postcode( $location ) {
		return 'postcode' === $location->type;
	}

	/**
	 * Remove string from string letters.
	 *
	 * @param  string $string String to clean.
	 * @return string
	 */
	private static function remove_signs( $string ) {
		$marked_letters = array(
			'Š' => 'S',
			'š' => 's',
			'Ž' => 'Z',
			'ž' => 'z',
			'À' => 'A',
			'Á' => 'A',
			'Â' => 'A',
			'Ã' => 'A',
			'Ä' => 'A',
			'Å' => 'A',
			'Æ' => 'A',
			'Ç' => 'C',
			'È' => 'E',
			'É' => 'E',
			'Ê' => 'E',
			'Ë' => 'E',
			'Ì' => 'I',
			'Í' => 'I',
			'Î' => 'I',
			'Ï' => 'I',
			'Ñ' => 'N',
			'Ò' => 'O',
			'Ó' => 'O',
			'Ô' => 'O',
			'Õ' => 'O',
			'Ö' => 'O',
			'Ø' => 'O',
			'Ù' => 'U',
			'Ú' => 'U',
			'Û' => 'U',
			'Ü' => 'U',
			'Ý' => 'Y',
			'Þ' => 'B',
			'ß' => 'Ss',
			'à' => 'a',
			'á' => 'a',
			'â' => 'a',
			'ã' => 'a',
			'ä' => 'a',
			'å' => 'a',
			'æ' => 'a',
			'ç' => 'c',
			'è' => 'e',
			'é' => 'e',
			'ê' => 'e',
			'ë' => 'e',
			'ì' => 'i',
			'í' => 'i',
			'î' => 'i',
			'ï' => 'i',
			'ð' => 'o',
			'ñ' => 'n',
			'ò' => 'o',
			'ó' => 'o',
			'ô' => 'o',
			'õ' => 'o',
			'ö' => 'o',
			'ø' => 'o',
			'ù' => 'u',
			'ú' => 'u',
			'û' => 'u',
			'ý' => 'y',
			'þ' => 'b',
			'ÿ' => 'y',
		);
		return strtr( $string, apply_filters( 'woocommerce_amazon_pa_signs_to_remove', $marked_letters ) );
	}

	/**
	 * Return shipping restrictions for checkout sessions
	 *
	 * @return bool|array
	 */
	protected static function get_shipping_restrictions() {
		$data_store         = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones          = $data_store->get_zones();
		$zones              = array();
		$shipping_countries = WC()->countries->get_shipping_countries();

		$all_continents = WC()->countries->get_continents();
		$all_countries  = WC()->countries->get_countries();
		$all_states     = WC()->countries->get_states();

		$row_zone = new WC_Shipping_Zone( 0 );
		$methods  = $row_zone->get_shipping_methods( true, 'json' );
		if ( ! empty( $methods ) ) {
			// Rest of the World has shipping methods, so we can assume we can ship to all shipping countries
			// Skip the whole thing.
			if ( count( $shipping_countries ) !== count( $all_countries ) ) {
				foreach ( $shipping_countries as $country => $name ) {
					$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON.
				}
				return $zones;
			} else {
				return false; // No restrictions.
			}
		}

		foreach ( $raw_zones as $raw_zone ) {
			$zone    = new WC_Shipping_Zone( $raw_zone );
			$methods = $zone->get_shipping_methods( true, 'json' );
			if ( empty( $methods ) ) {
				continue; // If no shipping methods, we assume no support on this region.
			}

			$locations  = $zone->get_zone_locations( 'json' );
			$continents = array_filter( $locations, array( __CLASS__, 'location_is_continent' ) );
			$countries  = array_filter( $locations, array( __CLASS__, 'location_is_country' ) );
			$states     = array_filter( $locations, array( __CLASS__, 'location_is_state' ) );
			$postcodes  = array_filter( $locations, array( __CLASS__, 'location_is_postcode' ) ); // HARD TODO: Postcode wildcards can't be implemented afaik.

			foreach ( $continents as $location ) {
				foreach ( $all_continents[ $location->code ]['countries'] as $country ) {
					if ( ! isset( $zones[ $country ] ) ) {
						$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON.
					}
				}
			}

			foreach ( $countries as $location ) {
				$country = $location->code;
				// We're forcing it to be an empty, since it will override if the full country is allowed anywhere.
				$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON.
			}

			foreach ( $states as $location ) {
				$location_codes = explode( ':', $location->code );
				$country        = strtoupper( $location_codes[0] );
				$state          = $location_codes[1];
				if ( ! isset( $zones[ $country ] ) ) {
					$zones[ $country ]                  = new stdClass(); // If we use an empty array it'll be treated as an array in JSON.
					$zones[ $country ]->statesOrRegions = array();
				} else {
					if ( ! isset( $zones[ $country ]->statesOrRegions ) ) {
						// Do not override anything if the country is allowed fully.
						continue;
					}
				}

				$zones[ $country ]->statesOrRegions[] = $state;
				if ( 'US' !== $country ) {

					$zones[ $country ]->statesOrRegions[] = $all_states[ $country ][ $state ];
					$variation_state                      = self::remove_signs( $all_states[ $country ][ $state ] );
					if ( $variation_state !== $all_states[ $country ][ $state ] ) {
						$zones[ $country ]->statesOrRegions[] = $variation_state;
					}
				}
			}
		}

		$zones = array_intersect_key( $zones, $shipping_countries );

		return $zones;
	}

	/**
	 * Create checkout session parameters for button
	 *
	 * @param  string $redirect_url Redirect URL on success.
	 * @return string JSON encoded object
	 */
	protected static function create_checkout_session_params( $redirect_url = null ) {

		$settings = self::get_settings();
		if ( is_null( $redirect_url ) ) {
			if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
				$parts        = wp_parse_url( home_url() );
				$path         = ! empty( $parts['path'] ) ? $parts['path'] : '';
				$redirect_url = "{$parts['scheme']}://{$parts['host']}{$path}" . add_query_arg( null, null );
			} else {
				$redirect_url = get_permalink( wc_get_page_id( 'checkout' ) );
			}
		}
		$redirect_url = add_query_arg( 'amazon_payments_advanced', 'true', $redirect_url );
		$payload      = array(
			'storeId'            => $settings['store_id'],
			'platformId'         => self::AMAZON_PAY_FOR_WOOCOMMERCE_SP_ID,
			'webCheckoutDetails' => array(
				'checkoutReviewReturnUrl' => add_query_arg( 'amazon_login', '1', $redirect_url ),
				'checkoutResultReturnUrl' => add_query_arg( 'amazon_return', '1', $redirect_url ),
			),
		);

		$restrictions = self::get_shipping_restrictions();
		if ( $restrictions ) {
			$payload['deliverySpecifications'] = array(
				'addressRestrictions' => array(
					'type'         => 'Allowed',
					'restrictions' => $restrictions,
				),
			);
		}

		$payload = apply_filters( 'woocommerce_amazon_pa_create_checkout_session_params', $payload, $redirect_url );

		$payload = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return $payload;

	}

	/**
	 * Create classic checkout session parameters for button.
	 *
	 * @param string $redirect_url Redirect URL on success.
	 * @return array
	 */
	public static function create_checkout_session_classic_params( $redirect_url = null ) {

		$settings = self::get_settings();
		if ( is_null( $redirect_url ) ) {
			if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
				$parts        = wp_parse_url( home_url() );
				$path         = ! empty( $parts['path'] ) ? $parts['path'] : '';
				$redirect_url = "{$parts['scheme']}://{$parts['host']}{$path}" . add_query_arg( null, null );
			} else {
				$redirect_url = get_permalink( wc_get_page_id( 'checkout' ) );
			}
		}
		$redirect_url = add_query_arg( 'amazon_payments_advanced', 'true', $redirect_url );
		$payload      = array(
			'storeId'            => $settings['store_id'],
			'platformId'         => self::AMAZON_PAY_FOR_WOOCOMMERCE_SP_ID,
			'webCheckoutDetails' => array(
				'checkoutMode'            => 'ProcessOrder',
				'checkoutResultReturnUrl' => add_query_arg( 'amazon_return_classic', '1', $redirect_url ),
			),
		);

		$restrictions = self::get_shipping_restrictions();
		if ( $restrictions ) {
			$payload['deliverySpecifications'] = array(
				'addressRestrictions' => array(
					'type'         => 'Allowed',
					'restrictions' => $restrictions,
				),
			);
		}

		$payload = apply_filters( 'woocommerce_amazon_pa_create_checkout_session_classic_params', $payload, $redirect_url );

		return $payload;

	}

	/**
	 * Get create checkout session config to send to the
	 *
	 * @param  string $redirect_url Redirect URL on success.
	 * @return array
	 */
	public static function get_create_checkout_session_config( $redirect_url = null ) {
		$settings = self::get_settings();
		$client   = self::get_client();
		$payload  = self::create_checkout_session_params( $redirect_url );

		$signature = $client->generateButtonSignature( $payload );
		return array(
			'publicKeyId' => $settings['public_key_id'],
			'payloadJSON' => $payload,
			'signature'   => $signature,
		);
	}

	/**
	 * Get classic create checkout session config to send to Amazon.
	 *
	 * @param  array $payload The payload that will be used to create a checkout session.
	 * @return array
	 */
	public static function get_create_checkout_classic_session_config( $payload ) {
		$settings  = self::get_settings();
		$signature = self::get_client()->generateButtonSignature( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		return array(
			'publicKeyId' => $settings['public_key_id'],
			'payloadJSON' => $payload,
			'signature'   => $signature,
		);
	}

	/**
	 * Get Checkout Session Data.
	 *
	 * @param  string $checkout_session_id Checkout Session Id.
	 * @return object Checkout Session from the API
	 */
	public static function get_checkout_session_data( $checkout_session_id ) {
		$client = self::get_client();
		$result = $client->getCheckoutSession( $checkout_session_id );
		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $result['status'], __( 'Error while getting checkout session.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
		$checkout_session = json_decode( $result['response'] );

		if ( ! empty( $checkout_session->billingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
			self::normalize_address( $checkout_session->billingAddress ); // phpcs:ignore WordPress.NamingConventions
		}

		if ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
			self::normalize_address( $checkout_session->shippingAddress ); // phpcs:ignore WordPress.NamingConventions
		}

		return $checkout_session;
	}

	/**
	 * Normalize Address Data from the API
	 *
	 * @param  object $address Object that will be adjusted.
	 */
	protected static function normalize_address( $address ) {
		foreach ( (array) $address as $prop => $val ) {
			switch ( strtolower( $prop ) ) {
				case 'phonenumber':
					$ucprop = 'Phone';
					break;
				default:
					$ucprop = ucfirst( $prop );
					break;
			}
			unset( $address->$prop );
			$address->$ucprop = $val;
		}
	}

	/**
	 * Update Checkout Session Data
	 *
	 * @param  string $checkout_session_id Checkout Session Id.
	 * @param  array  $data Data to send to the API.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function update_checkout_session_data( $checkout_session_id, $data = array() ) {
		$client = self::get_client();

		$headers = self::get_extra_headers( __FUNCTION__ );

		wc_apa()->log(
			sprintf( 'Checkout Session ID %s', $checkout_session_id ),
			array(
				'data'    => $data,
				'headers' => $headers,
			)
		);

		$result = $client->updateCheckoutSession( $checkout_session_id, $data, $headers );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			wc_apa()->log( sprintf( 'ERROR. Checkout Session ID %s.', $checkout_session_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Checkout Session ID %s.', $checkout_session_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Complete Checkout session
	 *
	 * @param  string $checkout_session_id Checkout Session Id.
	 * @param  array  $data Data to send to the API.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function complete_checkout_session( $checkout_session_id, $data = array() ) {
		$client = self::get_client();
		wc_apa()->log( sprintf( 'Checkout Session ID %s', $checkout_session_id ), $data );
		$result = $client->completeCheckoutSession( $checkout_session_id, $data );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 202 ), true ) ) {
			wc_apa()->log( sprintf( 'ERROR. Checkout Session ID %s.', $checkout_session_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Checkout Session ID %s.', $checkout_session_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Get Languages available per region
	 *
	 * @return array
	 */
	public static function get_languages_per_region() {
		return array(
			'eu' => array(
				'en-GB',
				'de-DE',
				'fr-FR',
				'it-IT',
				'es-ES',
			),
			'gb' => array(
				'en-GB',
				'de-DE',
				'fr-FR',
				'it-IT',
				'es-ES',
			),
			'us' => array(
				'en-US',
			),
			'jp' => array(
				'ja-JP',
			),
		);
	}

	/**
	 * Get Charge Permission object
	 *
	 * @param  string $charge_permission_id Charge Permission ID.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function get_charge_permission( $charge_permission_id ) {
		$client = self::get_client();
		$result = $client->getChargePermission( $charge_permission_id );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

	/**
	 * Get Charge object
	 *
	 * @param  string $charge_id Charge ID.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function get_charge( $charge_id ) {
		$client = self::get_client();
		$result = $client->getCharge( $charge_id );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

	/**
	 * Get Refund object
	 *
	 * @param  string $refund_id Refund ID.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function get_refund( $refund_id ) {
		$client = self::get_client();
		$result = $client->getRefund( $refund_id );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

	/**
	 * Generate UUID to use as idempotency
	 *
	 * @return string
	 */
	private static function generate_uuid() {
		return sprintf(
			'%04x%04x%04x%04x%04x%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	/**
	 * Capture a Charge
	 *
	 * @param  string $charge_id Charge ID.
	 * @param  array  $data Data to send to the API.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function capture_charge( $charge_id, $data = array() ) {
		$client = self::get_client();
		if ( empty( $data ) ) {
			$data = array();
		}
		// TODO: Validate entered data.
		if ( empty( $data['captureAmount'] ) ) {
			$charge                = self::get_charge( $charge_id );
			$data['captureAmount'] = (array) $charge->chargeAmount; // phpcs:ignore WordPress.NamingConventions
			// TODO: Test with lower amount of captured than charge (multiple charges per capture).
		}

		$headers = self::get_extra_headers( __FUNCTION__ );

		wc_apa()->log(
			sprintf( 'Charge ID %s.', $charge_id ),
			array(
				'data'    => $data,
				'headers' => $headers,
			)
		);

		$result = $client->captureCharge(
			$charge_id,
			$data,
			array_merge(
				$headers,
				array(
					'x-amz-pay-idempotency-key' => self::generate_uuid(),
				)
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( sprintf( 'ERROR. Charge ID %s.', $charge_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Charge ID %s.', $charge_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Refund a Charge
	 *
	 * @param  string $charge_id Charge ID.
	 * @param  float  $amount Amount to refund.
	 * @param  array  $data Data to send to the API.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function refund_charge( $charge_id, $amount = null, $data = array() ) {
		$client = self::get_client();
		if ( empty( $data ) ) {
			$data = array();
		}
		$data['chargeId'] = $charge_id;
		// TODO: Validate entered data.
		if ( empty( $data['refundAmount'] ) ) {
			$charge                          = self::get_charge( $charge_id );
			$data['refundAmount']            = (array) $charge->captureAmount; // phpcs:ignore WordPress.NamingConventions
			$data['refundAmount']['amount'] -= (float) $charge->refundedAmount->amount; // phpcs:ignore WordPress.NamingConventions
		}
		if ( ! is_null( $amount ) ) {
			$data['refundAmount']['amount'] = $amount;
		}

		$headers = self::get_extra_headers( __FUNCTION__ );

		wc_apa()->log(
			sprintf( 'Charge ID %s.', $charge_id ),
			array(
				'data'    => $data,
				'headers' => $headers,
			)
		);

		$result = $client->createRefund(
			$data,
			array_merge(
				$headers,
				array(
					'x-amz-pay-idempotency-key' => self::generate_uuid(),
				)
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( sprintf( 'ERROR. Charge ID %s.', $charge_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Charge ID %s.', $charge_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Cancel a charge
	 *
	 * @param  string $charge_id Charge ID.
	 * @param  string $reason Reason for the cancellation of the charge.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function cancel_charge( $charge_id, $reason = 'Order Cancelled' ) {
		$client = self::get_client();
		wc_apa()->log( sprintf( 'Charge ID %s.', $charge_id ) );

		$result = $client->cancelCharge(
			$charge_id,
			array(
				'cancellationReason' => $reason, // TODO: Make dynamic.
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( sprintf( 'ERROR. Charge ID %s.', $charge_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Charge ID %s.', $charge_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Get Merchant Metadata object
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public static function get_merchant_metadata( $order_id ) {
		/* translators: Plugin version */
		$version_note = sprintf( 'Created by WC_Gateway_Amazon_Pay/%1$s (Platform=WooCommerce/%2$s)', WC_AMAZON_PAY_VERSION, WC()->version );

		$merchant_metadata = array(
			'merchantReferenceId' => apply_filters( 'woocommerce_amazon_pa_merchant_metadata_reference_id', $order_id ),
			'customInformation'   => $version_note,
		);

		/**
		 * Amazon Pay API v2 supports a merchantStoreName property of a 50 chars max length.
		 *
		 * @see https://developer.amazon.com/docs/amazon-pay-api-v2/charge.html#type-merchantmetadata
		 *
		 * We could completely avoid providing this property, since it is used to overwrite what the merchant
		 * has already configured it in his Amazon merchant account.
		 * @see https://developer.amazon.com/docs/amazon-pay-checkout/buyer-communication.html
		 *
		 * For backwards compatibility though, we set the property for stores with an equal or
		 * shorter than 50 chars site name.
		 */
		$site_name = WC_Amazon_Payments_Advanced::get_site_name();
		if ( 50 >= strlen( $site_name ) ) {
			$merchant_metadata['merchantStoreName'] = $site_name;
		}

		return $merchant_metadata;
	}

	/**
	 * Function to handle simulation strings
	 *
	 * @param  string $type Function being called.
	 * @return array
	 */
	protected static function get_extra_headers( $type ) {
		$settings = self::get_settings();
		$headers  = array();

		if ( 'yes' !== $settings['sandbox'] ) {
			return $headers;
		}

		$simluation_codes_stack = get_option( 'amazon_pay_simulation_stack', array() );

		if ( empty( $simluation_codes_stack ) || ! is_array( $simluation_codes_stack ) ) {
			$simluation_codes_stack = array(
				/**
				 * Define here things in the following form
				 *
				 * > array( 'create_charge', 'HardDeclined' ),
				 *
				 * where:
				 *  * create_charge is the name of the call to add the header to (function name on this class)
				 *  * HardDeclined is the simulation code to use in that function
				 */
			);
		}

		foreach ( $simluation_codes_stack as $i => $simulation ) {
			list( $function, $code ) = $simulation;
			if ( strtolower( $function ) === strtolower( $type ) ) {
				$headers['x-amz-pay-simulation-code'] = $code;
				unset( $simluation_codes_stack[ $i ] );
			}
		}

		if ( empty( $simluation_codes_stack ) ) {
			delete_option( 'amazon_pay_simulation_stack' );
		} else {
			$simluation_codes_stack = array_values( $simluation_codes_stack );
			update_option( 'amazon_pay_simulation_stack', $simluation_codes_stack, false );
		}

		return $headers;
	}

	/**
	 * Create a charge
	 *
	 * @param  string $charge_permission_id Charge Permission ID.
	 * @param  array  $data Data to send to the API.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function create_charge( $charge_permission_id, $data ) {
		$client = self::get_client();
		if ( empty( $data ) ) {
			$data = array();
		}
		$data['chargePermissionId'] = $charge_permission_id;
		// TODO: Validate entered data.
		if ( empty( $data['chargeAmount'] ) ) {
			$charge_permission    = self::get_charge_permission( $charge_permission_id );
			$data['chargeAmount'] = (array) $charge_permission->limits->amountBalance; // phpcs:ignore WordPress.NamingConventions
		}

		$headers = self::get_extra_headers( __FUNCTION__ );

		wc_apa()->log(
			sprintf( 'Charge Permission ID %s.', $charge_permission_id ),
			array(
				'data'    => $data,
				'headers' => $headers,
			)
		);

		$result = $client->createCharge(
			$data,
			array_merge(
				$headers,
				array(
					'x-amz-pay-idempotency-key' => self::generate_uuid(),
				)
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( sprintf( 'ERROR. Charge Permission ID %s.', $charge_permission_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Charge Permission ID %s.', $charge_permission_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Close Charge Permission
	 *
	 * @param  string $charge_permission_id Charge Permission ID.
	 * @param  string $reason Reason for cancelling the recurring charge permission.
	 * @return object|WP_Error API Response, or WP_Error.
	 */
	public static function close_charge_permission( $charge_permission_id, $reason = 'Subscription Cancelled' ) {
		$client = self::get_client();

		$headers = self::get_extra_headers( __FUNCTION__ );

		wc_apa()->log( sprintf( 'Charge Permission ID %s.', $charge_permission_id ), array( 'headers' => $headers ) );

		$result = $client->closeChargePermission(
			$charge_permission_id,
			array(
				'closureReason' => $reason, // TODO: Make dynamic.
			),
			$headers
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( sprintf( 'ERROR. Charge Permission ID %s.', $charge_permission_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( sprintf( 'SUCCESS. Charge Permission ID %s.', $charge_permission_id ), self::sanitize_remote_response_log( $response ) );

		return $response;
	}

	/**
	 * Sanitize the object before sending it to the logs
	 *
	 * @param  object|array $obj Object to send to the logs.
	 * @param  bool         $recursing If we're recursing or not.
	 * @return array
	 */
	private static function sanitize_remote_response_log( $obj, $recursing = false ) {
		if ( ! $recursing ) { // Only force array on the first call.
			$obj = json_decode( wp_json_encode( $obj ), true );
		}
		foreach ( $obj as $key => $val ) {
			switch ( $key ) {
				case 'billingAddress':
				case 'shippingAddress':
				case 'paymentPreferences':
				case 'buyer':
					if ( ! is_null( $val ) ) {
						$val = '*** REMOVED FROM LOGS ***';
					}
					break;
				default:
					if ( is_array( $val ) ) {
						$val = self::sanitize_remote_response_log( $val, true );
					}
					break;
			}
			$obj[ $key ] = $val;
		}
		return $obj;
	}

	/**
	 * Get URLs from where to get the secret key in seller central, per region.
	 *
	 * @return array
	 */
	public static function get_secret_key_page_urls() {
		return array(
			'us' => 'https://sellercentral.amazon.com/gp/pyop/seller/mwsaccess',
			'eu' => 'https://sellercentral-europe.amazon.com/gp/pyop/seller/mwsaccess',
			'gb' => 'https://sellercentral-europe.amazon.com/gp/pyop/seller/mwsaccess',
			'jp' => 'https://sellercentral-japan.amazon.com/gp/pyop/seller/mwsaccess',
		);
	}

	/**
	 * Get URL from where to get the secret key in seller central, for the current region.
	 *
	 * @return string|bool
	 */
	public static function get_secret_key_page_url() {
		$region = self::get_settings( 'payment_region' );
		$urls   = self::get_secret_key_page_urls();
		return isset( $urls[ $region ] ) ? $urls[ $region ] : false;
	}

}
