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

	protected static $amazonpay_sdk_config;

	protected static $amazonpay_client;

	/**
	 * Set up API V2 SDK.
	 *
	 * @since 2.0.0
	 *
	 * @return array Returns SDK configuration
	 */
	protected static function get_amazonpay_sdk_config( $fresh = false ) {
		if ( $fresh || empty( self::$amazonpay_sdk_config ) ) {
			$settings = self::get_settings();
			$region   = $settings['payment_region']; // TODO: Maybe normalize v1 and v2 different region management
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
	 * Add Amazon reference information in order item response.
	 *
	 * @since 2.0.0
	 *
	 * @return WP_Error|bool Error
	 */
	protected static function validate_api_settings() {
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
			// Skip the whole thing
			if ( count( $shipping_countries ) !== count( $all_countries ) ) {
				foreach ( $shipping_countries as $country => $name ) {
					$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON
				}
				return $zones;
			} else {
				return false; // No restrictions
			}
		}

		foreach ( $raw_zones as $raw_zone ) {
			$zone    = new WC_Shipping_Zone( $raw_zone );
			$methods = $zone->get_shipping_methods( true, 'json' );
			if ( empty( $methods ) ) {
				continue; // If no shipping methods, we assume no support on this region
			}

			$locations  = $zone->get_zone_locations( 'json' );
			$continents = array_filter( $locations, array( __CLASS__, 'location_is_continent' ) );
			$countries  = array_filter( $locations, array( __CLASS__, 'location_is_country' ) );
			$states     = array_filter( $locations, array( __CLASS__, 'location_is_state' ) );
			$postcodes  = array_filter( $locations, array( __CLASS__, 'location_is_postcode' ) ); // HARD TODO: Postcode wildcards can't be implemented afaik

			foreach ( $continents as $location ) {
				foreach ( $all_continents[ $location->code ]['countries'] as $country ) {
					if ( ! isset( $zones[ $country ] ) ) {
						$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON
					}
				}
			}

			foreach ( $countries as $location ) {
				$country = $location->code;
				if ( ! isset( $zones[ $country ] ) ) {
					$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON
				}
			}

			foreach ( $states as $location ) {
				$location_codes = explode( ':', $location->code );
				$country        = strtoupper( $location_codes[0] );
				$state          = $location_codes[1];
				if ( ! isset( $zones[ $country ] ) ) {
					$zones[ $country ] = new stdClass(); // If we use an empty array it'll be treated as an array in JSON
				}

				if ( ! isset( $zones[ $country ]->statesOrRegions ) ) {
					$zones[ $country ]->statesOrRegions = array();
				}

				$zones[ $country ]->statesOrRegions[] = $state;
				if ( 'US' !== $country ) {
					$zones[ $country ]->statesOrRegions[] = $all_states[ $country ][ $state ];
				}
			}
		}

		$zones = array_intersect_key( $zones, $shipping_countries );

		return $zones;
	}

	protected static function create_checkout_session_params( $redirect_url = null ) {

		$settings = self::get_settings();
		if ( is_null( $redirect_url ) ) {
			$redirect_url = get_permalink( wc_get_page_id( 'checkout' ) );
		}
		$redirect_url = add_query_arg( 'amazon_payments_advanced', 'true', $redirect_url );
		$payload      = array(
			'storeId'            => $settings['store_id'],
			'platformId'         => 'A1BVJDFFHQ7US4',
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

		$payload = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return $payload;

	}

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

	public static function update_checkout_session_data( $checkout_session_id, $data = array() ) {
		$client = self::get_client();
		wc_apa()->log( __METHOD__, sprintf( 'Checkout Session ID %s', $checkout_session_id ), $data );
		$result = $client->updateCheckoutSession( $checkout_session_id, $data );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			wc_apa()->log( __METHOD__, sprintf( 'ERROR. Checkout Session ID %s.', $checkout_session_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( __METHOD__, sprintf( 'SUCCESS. Checkout Session ID %s.', $checkout_session_id ), $response );

		return $response;
	}

	public static function complete_checkout_session( $checkout_session_id, $data = array() ) {
		$client = self::get_client();
		wc_apa()->log( __METHOD__, sprintf( 'Checkout Session ID %s', $checkout_session_id ), $data );
		$result = $client->completeCheckoutSession( $checkout_session_id, $data );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 202 ), true ) ) {
			wc_apa()->log( __METHOD__, sprintf( 'ERROR. Checkout Session ID %s.', $checkout_session_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( __METHOD__, sprintf( 'SUCCESS. Checkout Session ID %s.', $checkout_session_id ), $response );

		return $response;
	}

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

	public static function get_charge_permission( $charge_permission_id ) {
		$client = self::get_client();
		$result = $client->getChargePermission( $charge_permission_id );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

	public static function get_charge( $charge_id ) {
		$client = self::get_client();
		$result = $client->getCharge( $charge_id );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

	public static function get_refund( $refund_id ) {
		$client = self::get_client();
		$result = $client->getRefund( $refund_id );

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

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

	public static function capture_charge( $charge_id, $data = array() ) {
		$client = self::get_client();
		if ( empty( $data ) ) {
			$data = array();
		}
		// TODO: Validate entered data
		if ( empty( $data['captureAmount'] ) ) {
			$charge                = self::get_charge( $charge_id );
			$data['captureAmount'] = (array) $charge->chargeAmount; // phpcs:ignore WordPress.NamingConventions
			// TODO: Test with lower amount of captured than charge (multiple charges per capture)
		}
		wc_apa()->log( __METHOD__, sprintf( 'Charge ID %s.', $charge_id ), $data );

		$result = $client->captureCharge(
			$charge_id,
			$data,
			array(
				'x-amz-pay-idempotency-key' => self::generate_uuid(),
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( __METHOD__, sprintf( 'ERROR. Charge ID %s.', $charge_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( __METHOD__, sprintf( 'SUCCESS. Charge ID %s.', $charge_id ), $response );

		return $response;
	}

	public static function refund_charge( $charge_id, $amount = null, $data = array() ) {
		$client = self::get_client();
		if ( empty( $data ) ) {
			$data = array();
		}
		$data['chargeId'] = $charge_id;
		// TODO: Validate entered data
		if ( empty( $data['refundAmount'] ) ) {
			$charge                          = self::get_charge( $charge_id );
			$data['refundAmount']            = (array) $charge->captureAmount; // phpcs:ignore WordPress.NamingConventions
			$data['refundAmount']['amount'] -= (float) $charge->refundedAmount->amount; // phpcs:ignore WordPress.NamingConventions
		}
		if ( ! is_null( $amount ) ) {
			$data['refundAmount']['amount'] = $amount;
		}
		wc_apa()->log( __METHOD__, sprintf( 'Charge ID %s.', $charge_id ), $data );

		$result = $client->createRefund(
			$data,
			array(
				'x-amz-pay-idempotency-key' => self::generate_uuid(),
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( __METHOD__, sprintf( 'ERROR. Charge ID %s.', $charge_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( __METHOD__, sprintf( 'SUCCESS. Charge ID %s.', $charge_id ), $response );

		return $response;
	}

	public static function cancel_charge( $charge_id, $reason = 'Order Cancelled' ) {
		$client = self::get_client();
		wc_apa()->log( __METHOD__, sprintf( 'Charge ID %s.', $charge_id ) );

		$result = $client->cancelCharge(
			$charge_id,
			array(
				'cancellationReason' => $reason, // TODO: Make dynamic
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			wc_apa()->log( __METHOD__, sprintf( 'ERROR. Charge ID %s.', $charge_id ), $result );
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		wc_apa()->log( __METHOD__, sprintf( 'SUCCESS. Charge ID %s.', $charge_id ), $response );

		return $response;
	}

	public static function get_merchant_metadata( $order_id ) {
		/* translators: Plugin version */
		$version_note = sprintf( __( 'Created by WC_Gateway_Amazon_Pay/%1$s (Platform=WooCommerce/%2$s)', 'woocommerce-gateway-amazon-payments-advanced' ), WC_AMAZON_PAY_VERSION, WC()->version );

		return array(
			'merchantReferenceId' => $order_id,
			'merchantStoreName'   => WC_Amazon_Payments_Advanced::get_site_name(),
			'customInformation'   => $version_note,
		);
	}

	public static function create_charge( $charge_permission_id, $data ) {
		$client = self::get_client();
		if ( empty( $data ) ) {
			$data = array();
		}
		$data['chargePermissionId'] = $charge_permission_id;
		// TODO: Validate entered data
		if ( empty( $data['chargeAmount'] ) ) {
			$charge_permission    = self::get_charge_permission( $charge_permission_id );
			$data['chargeAmount'] = (array) $charge_permission->limits->amountLimit; // phpcs:ignore WordPress.NamingConventions
			// TODO: Test with lower amount of captured than charge (multiple charges per capture)
		}

		$result = $client->createCharge(
			$data,
			array(
				'x-amz-pay-idempotency-key' => self::generate_uuid(),
			)
		);

		$response = json_decode( $result['response'] );

		if ( ! isset( $result['status'] ) || ! in_array( $result['status'], array( 200, 201 ), true ) ) {
			return new WP_Error( $response->reasonCode, $response->message ); // phpcs:ignore WordPress.NamingConventions
		}

		return $response;
	}

}
