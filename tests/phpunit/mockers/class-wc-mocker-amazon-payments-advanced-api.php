<?php

class WC_Mocker_Amazon_Payments_Advanced_API {
	public static function update_checkout_session_data( $checkout_session_id, $payload ) {
		$response = new stdClass();

		$response->webCheckoutDetails                       = new stdClass();
		$response->webCheckoutDetails->amazonPayRedirectUrl = 'https://amazon.unit.tests/';
		return $response;
	}

	public static function get_create_checkout_classic_session_config( $payload ) {
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
