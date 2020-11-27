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
			$settings                   = self::get_settings();
			self::$amazonpay_sdk_config = array(
				'public_key_id' => $settings['public_key_id'],
				'private_key'   => get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, false ),
				'sandbox'       => 'yes' === $settings['sandbox'] ? true : false,
				'region'        => $settings['payment_region'],
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

		WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::update_migration_status();

		$ret = false;
		$valid_settings = self::validate_api_settings();
		if( is_wp_error( $valid_settings ) ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::delete_migration_status();
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
			WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::delete_migration_status();
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

	protected static function create_checkout_session_params() {

		$settings = self::get_settings();
		$redirect_url = add_query_arg( 'amazon_payments_advanced', 'true', get_permalink( wc_get_page_id( 'checkout' ) ) );
		$payload      = array(
			'storeId'            => $settings['store_id'],
			'webCheckoutDetails' => array(
				'checkoutReviewReturnUrl' => $redirect_url,
				'checkoutResultReturnUrl' => $redirect_url,
			),
		);

		$payload = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );

		return $payload;

	}

	public static function get_create_checkout_session_config() {
		$settings = self::get_settings();
		$client   = self::get_client();
		$payload  = self::create_checkout_session_params();

		$signature = $client->generateButtonSignature( $payload );
		return array(
			'publicKeyId' => $settings['public_key_id'],
			'payloadJSON' => $payload,
			'signature'   => $signature,
		);
	}

	public static function get_checkout_session_data( $checkout_session_id ) {
		$client   = self::get_client();
		$result = $client->getCheckoutSession( $checkout_session_id );
		if ( ! isset( $result['status'] ) || 200 !== $result['status'] ) {
			return new WP_Error( __( 'Error while getting checkout session.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
		$checkout_session = json_decode( $result['response'] );

		if ( ! empty( $checkout_session->billingAddress ) ) {
			self::normalize_address( $checkout_session->billingAddress );
		}

		if ( ! empty( $checkout_session->shippingAddress ) ) {
			self::normalize_address( $checkout_session->shippingAddress );
		}
		
		return $checkout_session;
	}

	protected static function normalize_address( $address ) {
		foreach( (array) $address as $prop => $val ) {
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

}
