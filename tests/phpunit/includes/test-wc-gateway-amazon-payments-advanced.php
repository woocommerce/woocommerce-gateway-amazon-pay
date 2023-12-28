<?php
/**
 * Test cases for WC_Gateway_Amazon_Payments_Advanced.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * WC_Gateway_Amazon_Payments_Advanced_Test tests functionalities in WC_Gateway_Amazon_Payments_Advanced.
 */
class WC_Gateway_Amazon_Payments_Advanced_Test extends WP_UnitTestCase {

	/**
	 * Store an instance of our Gateway being tested.
	 *
	 * @var ?WC_Gateway_Amazon_Payments_Advanced
	 */
	protected static $gateway;

	/**
	 * Set up our DB before tests.
	 *
	 * @return void
	 */
	public static function set_up_before_class() : void {
		parent::set_up_before_class();
		self::$gateway = new WC_Gateway_Amazon_Payments_Advanced();
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_amazon_payments_new_install', WC_AMAZON_PAY_VERSION );
		update_option( 'amazon_api_version', 'V2' );
	}

	/**
	 * Restore the DB's state after tests.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() : void {
		self::$gateway = null;
		delete_option( 'woocommerce_currency' );
		delete_option( 'woocommerce_amazon_payments_advanced_settings' );
		delete_option( 'woocommerce_amazon_payments_new_install' );
		delete_option( 'amazon_api_version' );
		delete_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );
		parent::tear_down_after_class();
	}

	/**
	 * Test that gateway is not available.
	 *
	 * @return void
	 */
	public function test_is_not_available() : void {
		$this->assertFalse( self::$gateway->is_available() );
	}

	/**
	 * Test that gateway is available.
	 *
	 * @return void
	 */
	public function test_is_available() : void {
		// Update plugin settings to make the gateway available.
		$this->make_gateway_available();

		// Test gateway availability.
		$this->assertTrue( self::$gateway->is_available() );
	}

	/**
	 * Test happy path of the express gateway.
	 *
	 * @return void
	 */
	public function test_express_process_payment_succeeds() : void {
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

	/**
	 * Test happy path of classic gateway.
	 *
	 * @return void
	 */
	public function test_classic_process_payment_succeeds() : void {
		$checkout_session_key = apply_filters( 'woocommerce_amazon_pa_checkout_session_key', 'amazon_checkout_session_id' );
		WC()->session->set( $checkout_session_key, null );
		WC()->session->save_data();

		$order_total = 100;

		$order = WC_Helper_Order::create_order( self::$gateway->id, 1, $order_total );

		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced( $order_total );

		$this->assertEquals(
			array(
				'result'                     => 'success',
				'redirect'                   => '#amazon-pay-classic-id-that-should-not-exist',
				'amazonCreateCheckoutParams' => wp_json_encode( WC_Mocker_Amazon_Payments_Advanced_API::get_create_checkout_classic_session_config( array( 'test' ) ) ),
				'amazonEstimatedOrderAmount' => wp_json_encode(
					array(
						'amount'       => number_format( $order_total, 2 ),
						'currencyCode' => get_woocommerce_currency(),
					)
				),
			),
			$mock_gateway->process_payment( $order->get_id() )
		);
	}

	/**
	 * Test happy path of classic gateway.
	 *
	 * @return void
	 */
	public function test_estimated_order_amount_format() : void {
		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced( '100.2501' );

		$this->assertEquals(
			wp_json_encode(
				array(
					'amount'       => '100.25',
					'currencyCode' => get_woocommerce_currency(),
				)
			),
			$mock_gateway::get_estimated_order_amount()
		);
	}

	/**
	 * Test maybe_separator_and_checkout_button_single_product method.
	 *
	 * @return void
	 */
	public function test_maybe_separator_and_checkout_button_single_product() : void {
		ob_start();
		self::$gateway->maybe_separator_and_checkout_button_single_product();
		$should_fail_because_no_global_product = ob_get_clean();

		$this->assertEmpty( $should_fail_because_no_global_product );

		$test_product = WC_Helper_Product::create_and_optionally_save_simple_product( true );
		$test_product->set_stock_status( 'outofstock' );
		$test_product->save();

		$GLOBALS['post'] = get_post( $test_product->get_id() );

		ob_start();
		self::$gateway->maybe_separator_and_checkout_button_single_product();
		$should_fail_because_out_of_stock = ob_get_clean();

		$this->assertEmpty( $should_fail_because_out_of_stock );
		$test_product->set_stock_status( 'instock' );
		$test_product->save();

		$GLOBALS['post'] = get_post( $test_product->get_id() );

		ob_start();
		self::$gateway->maybe_separator_and_checkout_button_single_product();
		$should_fail_because_plugin_option_is_not_enabled = ob_get_clean();

		$this->assertEmpty( $should_fail_because_plugin_option_is_not_enabled );

		// Enable gateway and update single button settings.
		$this->make_gateway_available( array( 'product_button' => 'yes' ) );
		self::$gateway->init_settings();

		$expected_for_success = self::$gateway->checkout_button( false, 'div', 'pay_with_amazon_product' );

		ob_start();
		self::$gateway->maybe_separator_and_checkout_button_single_product();
		$actual_markup = ob_get_clean();

		$this->assertEquals( $expected_for_success, $actual_markup );
	}

	/**
	 * Test load_scripts_on_product_pages method.
	 *
	 * @return void
	 */
	public function test_load_scripts_on_product_pages() {
		// If given true it should always return true, no matter what.
		$this->assertTrue( self::$gateway->load_scripts_on_product_pages( true ) );

		// Should return false, since this is not a product page.
		$this->assertFalse( self::$gateway->load_scripts_on_product_pages( false ) );

		$test_product = WC_Helper_Product::create_and_optionally_save_simple_product( true );
		$test_product->set_stock_status( 'outofstock' );
		$test_product->save();

		// Force the global post to the newly created test product.
		$GLOBALS['post'] = get_post( $test_product->get_id() );

		// Force the global query to be a singular product query.
		$GLOBALS['wp_query']->is_singular    = true;
		$GLOBALS['wp_query']->queried_object = $GLOBALS['post'];

		// Scripts should not be loaded since product is out of stock.
		$this->assertFalse( self::$gateway->load_scripts_on_product_pages( false ) );
		$test_product->set_stock_status( 'instock' );
		$test_product->save();

		// Force the global post to the newly created test product.
		$GLOBALS['post'] = get_post( $test_product->get_id() );

		// Scripts should be loaded since product is in stock.
		$this->assertTrue( self::$gateway->load_scripts_on_product_pages( false ) );
	}

	/**
	 * Helper method to make the Gateway available.
	 *
	 * @param array $extras Extra settings to update the gateway with.
	 * @return void
	 */
	protected function make_gateway_available( array $extras = array() ) {
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings = array_merge(
			$settings,
			array(
				'enabled'        => 'yes',
				'merchant_id'    => 'test_merchant_id',
				'public_key_id'  => 'test_public_key_id',
				'store_id'       => 'test_store_id',
				'payment_region' => 'eu',
			),
			$extras
		);

		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
		update_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, 'TEST_PRIVATE_KEY' );
	}
}
