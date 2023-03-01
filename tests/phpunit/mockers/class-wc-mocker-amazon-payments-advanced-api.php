<?php
/**
 * Amazon API Mocker Class.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * Amazon API Mocker Class.
 */
class WC_Mocker_Amazon_Payments_Advanced_API {

	/**
	 * Update Checkout Session Data
	 *
	 * @param string $checkout_session_id Checkout Session Id.
	 * @param array  $payload Data to send to the API.
	 * @return stdClass Mocked API Response.
	 */
	public static function update_checkout_session_data( string $checkout_session_id, array $payload = array() ) : stdClass {
		$response = new stdClass();

		$response->webCheckoutDetails                       = new stdClass(); //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$response->webCheckoutDetails->amazonPayRedirectUrl = 'https://amazon.unit.tests/'; //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $response;
	}

	/**
	 * Get classic create checkout session config to send to Amazon.
	 *
	 * @param  array $payload The payload that will be used to create a checkout session.
	 * @return array
	 */
	public static function get_create_checkout_classic_session_config( array $payload ) : array {
		$json = wp_json_encode(
			array(
				'key_1' => 'value_1',
				'key_2' => 'value_2',
				'key_3' => 'value_3',
			)
		);

		$signature = md5( 'TEST_SIGNATURE' );

		return array(
			'publicKeyId' => 'TEST_PUBLIC_KEY_ID',
			'payloadJSON' => $json,
			'signature'   => $signature,
		);
	}
}
