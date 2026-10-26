<?php
/**
 * Test cases for the Amazon Pay SDK integration.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * WC_Amazon_Payments_Advanced_SDK_Test tests the plugin's SDK configuration against the installed SDK.
 */
class WC_Amazon_Payments_Advanced_SDK_Test extends WP_UnitTestCase {

	/**
	 * Store a generated private key where onboarding saves it.
	 *
	 * @return void
	 */
	public function set_up() : void {
		parent::set_up();
		include_once wc_apa()->path . '/vendor/autoload.php';

		$key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		openssl_pkey_export( $key, $pem );
		update_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, $pem );
	}

	/**
	 * Remove the stored key and settings.
	 *
	 * @return void
	 */
	public function tear_down() : void {
		delete_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );
		delete_option( 'woocommerce_amazon_payments_advanced_settings' );
		parent::tear_down();
	}

	/**
	 * Test the SDK accepts and signs with the plugin's configuration in every payment region.
	 *
	 * @return void
	 */
	public function test_sdk_signs_with_plugin_config_in_every_region() : void {
		$config = new ReflectionMethod( WC_Amazon_Payments_Advanced_API::class, 'get_amazonpay_sdk_config' );
		$config->setAccessible( true );

		foreach ( array_keys( WC_Amazon_Payments_Advanced_API::get_payment_regions() ) as $region ) {
			update_option(
				'woocommerce_amazon_payments_advanced_settings',
				array(
					'payment_region' => $region,
					'public_key_id'  => 'SANDBOX-QATEST',
					'sandbox'        => 'yes',
				)
			);

			$client    = new Amazon\Pay\API\Client( $config->invoke( null, true ) );
			$signature = $client->generateButtonSignature( '{"storeId":"amzn1.application-oa2-client.test"}' );

			$this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/]{342}==$/', $signature, $region );
		}
	}

	/**
	 * Test the SDK rejects a region it does not support when the client is built.
	 *
	 * @return void
	 */
	public function test_sdk_rejects_unsupported_region() : void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid region' );

		new Amazon\Pay\API\Client(
			array(
				'region'        => 'gb',
				'sandbox'       => true,
				'public_key_id' => 'SANDBOX-QATEST',
				'private_key'   => get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY ),
			)
		);
	}
}
