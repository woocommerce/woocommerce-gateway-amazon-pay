<?php
/**
 * Test cases for WC_Gateway_Amazon_Payments_Advanced.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Gateway_Amazon_Payments_Advanced_Test tests functionalities in WC_Gateway_Amazon_Payments_Advanced.
 *
 * @since UNIT_TESTS_VERSION
 */
class WC_Gateway_Amazon_Payments_Advanced_Test extends WP_UnitTestCase {

	protected static $gateway;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$gateway = new WC_Gateway_Amazon_Payments_Advanced();
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_amazon_payments_new_install', WC_AMAZON_PAY_VERSION );
		update_option( 'amazon_api_version', 'V2' );
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		self::$gateway = null;
		delete_option( 'woocommerce_currency' );
		delete_option( 'woocommerce_amazon_payments_advanced_settings' );
		delete_option( 'woocommerce_amazon_payments_new_install' );
		delete_option( 'amazon_api_version' );
		delete_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );
	}

	public function test_is_not_available() {
		$this->assertFalse( self::$gateway->is_available() );
	}

	public function test_is_available() {
		self::update_plugin_settings();
		$this->assertTrue( self::$gateway->is_available() );
	}

	public function test_express_process_payment() {
		$checkout_session_key = apply_filters( 'woocommerce_amazon_pa_checkout_session_key', 'amazon_checkout_session_id' );
		WC()->session->set( $checkout_session_key, 'TEST_CHECKOUT_SESSION_ID' );
		WC()->session->save_data();

		$order = WC_Helper_Order::create_order( self::$gateway->id );

		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced();

		$this->assertEquals(
			array(
				'result'   => 'success',
				'redirect' => 'https://amazon.unit.tests/',
			),
			$mock_gateway->process_payment( $order->get_id() )
		);
	}

	public function test_classic_process_payment() {
		$checkout_session_key = apply_filters( 'woocommerce_amazon_pa_checkout_session_key', 'amazon_checkout_session_id' );
		WC()->session->set( $checkout_session_key, null );
		WC()->session->save_data();

		$order_total = 100;

		$order = WC_Helper_Order::create_order( self::$gateway->id, 1, $order_total );

		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced( $order_total );

		remove_action( 'woocommerce_after_checkout_validation', array( wc_apa()->get_gateway(), 'classic_validation' ), 10 );

		$this->assertEquals(
			array(
				'result'                     => 'success',
				'redirect'                   => '#amazon-pay-classic-id-that-should-not-exist',
				'amazonCreateCheckoutParams' => wp_json_encode( WC_Mocker_Amazon_Payments_Advanced_API::get_create_checkout_classic_session_config( array( 'test' ) ) ),
				'amazonEstimatedOrderAmount' => wp_json_encode(
					array(
						'amount'       => $order_total,
						'currencyCode' => get_woocommerce_currency(),
					)
				),
			),
			$mock_gateway->process_payment( $order->get_id() )
		);
	}

	protected function update_plugin_settings() {
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings = array_merge(
			$settings,
			array(
				'enabled'        => 'yes',
				'merchant_id'    => 'test_merchant_id',
				'public_key_id'  => 'test_public_key_id',
				'store_id'       => 'test_store_id',
				'payment_region' => 'eu',
			)
		);

		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
		update_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, 'TEST_PRIVATE_KEY' );
	}
}
