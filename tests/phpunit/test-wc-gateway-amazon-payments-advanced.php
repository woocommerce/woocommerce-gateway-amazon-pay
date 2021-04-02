<?php
/**
 * PHPUnit bootstrap file
 */

/**
 * WC_Amazon_Payments_Advanced_Test
 */
class WC_Amazon_Payments_Advanced_Test extends WP_UnitTestCase {
	/**
	 * Test wc_apa
	 *
	 * @return void
	 */
	public function test_wc_apa() {
		$this->assertTrue( is_a( wc_apa(), 'WC_Amazon_Payments_Advanced' ) );
	}

	/**
	 * Test API Class is loaded
	 *
	 * @return void
	 */
	public function test_api_class_is_loaded() {
		$this->assertTrue( class_exists( 'WC_Amazon_Payments_Advanced_API' ) );
	}

	/**
	 * Test Compat Class is loaded
	 *
	 * @return void
	 */
	public function test_compat_class_is_loaded() {
		$this->assertTrue( class_exists( 'WC_Amazon_Payments_Advanced_Compat' ) );
	}

	/**
	 * Test hooks are registered
	 *
	 * @return void
	 */
	public function test_callbacks_hooks_are_registered() {
		$this->assertEquals( has_action( 'init', array( wc_apa(), 'init' ) ), 10 );
		$this->assertEquals( has_action( 'wp_loaded', array( wc_apa(), 'init_handlers' ) ), 11 );
		$this->assertEquals( has_action( 'wp_footer', array( wc_apa(), 'maybe_hide_standard_checkout_button' ) ), 10 );
		$this->assertEquals( has_action( 'wp_footer', array( wc_apa(), 'maybe_hide_amazon_buttons' ) ), 10 );
		$this->assertEquals( has_action( 'woocommerce_thankyou_amazon_payments_advanced', array( wc_apa(), 'logout_from_amazon' ) ), 10 );
		$this->assertEquals( has_filter( 'rest_api_init', array( wc_apa(), 'rest_api_register_routes' ) ), 11 );
		$this->assertEquals( has_filter( 'woocommerce_rest_prepare_shop_order', array( wc_apa(), 'rest_api_add_amazon_ref_info' ) ), 10 );
	}

	/**
	 * Test gateway is registered
	 *
	 * @return void
	 */
	public function test_gateway_is_registered() {
		$this->assertContains(
			'amazon_payments_advanced',
			WC()->payment_gateways->get_payment_gateway_ids()
		);
	}

	/**
	 * Test log methods
	 *
	 * @return void
	 */
	public function test_has_log_methods() {
		$this->assertTrue( is_callable( array( wc_apa(), 'log' ) ) );
		$this->assertTrue( is_callable( array( wc_apa(), 'sanitize_remote_request_log' ) ) );
		$this->assertTrue( is_callable( array( wc_apa(), 'sanitize_remote_response_log' ) ) );
	}

	/**
	 * Test amazon logout URL
	 *
	 * @return void
	 */
	public function test_get_amazon_logout_url() {
		$this->assertTrue( false !== strpos( wc_apa()->get_amazon_logout_url(), 'amazon_payments_advanced=true&amazon_logout=true' ) );
	}
}
