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
	* Validate API keys when settings are updated.
	*
	* @since 2.0.0
	*
	* @return bool Returns true if API keys are valid
	*/
	public static function validate_api_keys() {

		$settings = self::get_settings();

		wc_apa()->update_migration_status();

		$ret = false;
		if ( empty( $settings['merchant_id'] ) ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			return $ret;
		}

		try {
			if ( empty( $settings['public_key_id'] ) ) {
				throw new Exception( __( 'Error: You must enter Public Key Id.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
			if ( empty( $settings['store_id'] ) ) {
				throw new Exception( __( 'Error: You must enter Store Id.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
			$private_key = get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, false );

			if ( empty( $private_key ) ) {
				throw new Exception( __( 'Error: You must add the private key file.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
			include_once wc_apa()->path . '/vendor/autoload.php';
			$client       = new Amazon\Pay\API\Client( wc_apa()->get_amazonpay_sdk_config() );
			$redirect_url = add_query_arg( 'amazon_payments_advanced', 'true', get_permalink( wc_get_page_id( 'checkout' ) ) );
			$payload      = array(
				'storeId'            => $settings['store_id'],
				'webCheckoutDetails' => array(
					'checkoutReviewReturnUrl' => $redirect_url,
					'checkoutResultReturnUrl' => $redirect_url,
				),
			);

			$payload = wp_json_encode( $payload );

			$headers = array( 'x-amz-pay-Idempotency-Key' => uniqid() );
			$result  = $client->createCheckoutSession( $payload, $headers );
			if ( ! isset( $result['status'] ) || 201 !== $result['status'] ) {
				throw new Exception( __( 'Error: API is not responding.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
			$ret = true;
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 1 );

		} catch ( Exception $e ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			wc_apa()->delete_migration_status();
			WC_Admin_Settings::add_error( $e->getMessage() );
		}
		return $ret;
	}

}
