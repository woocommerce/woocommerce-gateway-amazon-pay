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
				'redirect'                   => '#',
				'amazonCreateCheckoutParams' => wp_json_encode( WC_Mocker_Amazon_Payments_Advanced_API::get_create_checkout_classic_session_config( array( 'test' ) ) ),
				'amazonEstimatedOrderAmount' => wp_json_encode(
					array(
						'amount'       => WC_Amazon_Payments_Advanced::format_amount( $order_total, 2 ),
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
	 * Test the checkout banner and its fragment drop the button on a zero total cart.
	 *
	 * @return void
	 */
	public function test_checkout_message_hides_button_when_cart_needs_no_payment() : void {
		$this->make_gateway_available();
		self::$gateway->init_settings();
		$this->set_amazon_checkout_session( null );

		$this->fill_cart( false );
		$this->assertStringContainsString( 'id="pay_with_amazon"', $this->get_output( array( self::$gateway, 'checkout_message' ) ) );

		$this->fill_cart( true );
		$this->assertFalse( WC()->cart->needs_payment() );

		$message = $this->get_output( array( self::$gateway, 'checkout_message' ) );
		$this->assertStringNotContainsString( 'pay_with_amazon', $message );
		$this->assertStringNotContainsString( 'wc-amazon-payments-advanced-populated', $message );
		$this->assertArrayHasKey( '.wc-amazon-checkout-message.wc-amazon-payments-advanced-populated', self::$gateway->update_amazon_fragments( array() ) );
	}

	/**
	 * Test the checkout banner keeps the Amazon logout notice on a zero total cart.
	 *
	 * @return void
	 */
	public function test_checkout_message_keeps_logout_notice_when_cart_needs_no_payment() : void {
		$this->make_gateway_available();
		self::$gateway->init_settings();
		$this->fill_cart( true );

		$this->set_amazon_checkout_session( 'TEST_CHECKOUT_SESSION_ID' );
		$message = $this->get_output( array( self::$gateway, 'checkout_message' ) );
		$this->set_amazon_checkout_session( null );

		$this->assertStringContainsString( 'amazon-logout', $message );
		$this->assertStringContainsString( 'wc-amazon-payments-advanced-populated', $message );
	}

	/**
	 * Test the cart page and mini-cart buttons are hidden on a zero total cart.
	 *
	 * @return void
	 */
	public function test_cart_buttons_hidden_when_cart_needs_no_payment() : void {
		$this->make_gateway_available( array( 'mini_cart_button' => 'yes' ) );
		self::$gateway->init_settings();
		$this->use_generated_private_key();

		$this->fill_cart( false );
		$this->assertStringContainsString( 'id="pay_with_amazon"', $this->get_output( array( self::$gateway, 'maybe_render_button_and_separator' ) ) );
		$this->assertStringContainsString( 'id="pay_with_amazon_cart"', $this->get_output( array( self::$gateway, 'maybe_separator_and_checkout_button' ) ) );

		$this->fill_cart( true );
		$this->assertEmpty( $this->get_output( array( self::$gateway, 'maybe_render_button_and_separator' ) ) );
		$this->assertEmpty( $this->get_output( array( self::$gateway, 'maybe_separator_and_checkout_button' ) ) );
	}

	/**
	 * Test the buttons stay on a zero total cart that still needs payment.
	 *
	 * @return void
	 */
	public function test_buttons_shown_when_zero_total_cart_needs_payment() : void {
		$this->make_gateway_available();
		self::$gateway->init_settings();
		$this->set_amazon_checkout_session( null );
		$this->fill_cart( true );

		add_filter( 'woocommerce_cart_needs_payment', '__return_true' );
		$message   = $this->get_output( array( self::$gateway, 'checkout_message' ) );
		$cart_page = $this->get_output( array( self::$gateway, 'maybe_render_button_and_separator' ) );
		remove_filter( 'woocommerce_cart_needs_payment', '__return_true' );

		$this->assertStringContainsString( 'id="pay_with_amazon"', $message );
		$this->assertStringContainsString( 'id="pay_with_amazon"', $cart_page );
	}

	/**
	 * Test the order-pay banner ignores the cart.
	 *
	 * @return void
	 */
	public function test_checkout_message_shown_on_order_pay_page() : void {
		global $wp;

		$this->make_gateway_available();
		self::$gateway->init_settings();
		$this->set_amazon_checkout_session( null );
		wc_load_cart();
		WC()->cart->empty_cart();

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		$wp->query_vars['order-pay'] = 1;
		$message                     = $this->get_output( array( self::$gateway, 'checkout_message' ) );
		unset( $wp->query_vars['order-pay'] );
		remove_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->assertStringContainsString( 'id="pay_with_amazon"', $message );
	}

	/**
	 * Fill the cart with a virtual product, optionally discounted to zero.
	 *
	 * @param bool $zero_total Apply a 100% coupon.
	 * @return void
	 */
	protected function fill_cart( bool $zero_total ) : void {
		wc_load_cart();
		WC()->cart->empty_cart();

		$product = WC_Helper_Product::create_and_optionally_save_simple_product( false );
		$product->set_virtual( true );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id() );

		if ( $zero_total ) {
			$coupon = new WC_Coupon();
			$coupon->set_code( 'free' . $product->get_id() );
			$coupon->set_discount_type( 'percent' );
			$coupon->set_amount( 100 );
			$coupon->save();
			WC()->cart->apply_coupon( $coupon->get_code() );
		}

		WC()->cart->calculate_totals();
	}

	/**
	 * Set or clear the Amazon checkout session id.
	 *
	 * @param ?string $checkout_session_id Checkout session id, null to log out.
	 * @return void
	 */
	protected function set_amazon_checkout_session( ?string $checkout_session_id ) : void {
		$checkout_session_key = apply_filters( 'woocommerce_amazon_pa_checkout_session_key', 'amazon_checkout_session_id' );
		WC()->session->set( $checkout_session_key, $checkout_session_id );
	}

	/**
	 * Store a generated private key and drop the cached API client.
	 *
	 * @return void
	 */
	protected function use_generated_private_key() : void {
		openssl_pkey_export( openssl_pkey_new( array( 'private_key_bits' => 2048 ) ), $private_key );
		update_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, $private_key );

		$client = new ReflectionProperty( 'WC_Amazon_Payments_Advanced_API', 'amazonpay_client' );
		$client->setAccessible( true );
		$client->setValue( null, null );
	}

	/**
	 * Capture what a callback prints.
	 *
	 * @param callable $callback Callback to run.
	 * @return string
	 */
	protected function get_output( callable $callback ) : string {
		ob_start();
		call_user_func( $callback );
		return (string) ob_get_clean();
	}

	/**
	 * Test the login return URL carries the visitor's login nonce.
	 *
	 * @return void
	 */
	public function test_login_return_url_carries_session_nonce() : void {
		WC()->session->set( 'amazon_nonce', null );

		$method = new ReflectionMethod( WC_Amazon_Payments_Advanced_API::class, 'create_checkout_session_params' );
		$method->setAccessible( true );
		$payload = json_decode( $method->invoke( null ), true );

		$nonce = WC()->session->get( 'amazon_nonce' );
		$this->assertSame( 32, strlen( $nonce ) );

		wp_parse_str( (string) wp_parse_url( $payload['webCheckoutDetails']['checkoutReviewReturnUrl'], PHP_URL_QUERY ), $query );
		$this->assertSame( $nonce, $query['amazon_nonce'] );

		$this->assertSame( $nonce, WC_Amazon_Payments_Advanced_API::get_login_nonce() );
	}

	/**
	 * Test amazon_login rejects a checkout session created with another visitor's nonce (session fixation).
	 *
	 * @return void
	 */
	public function test_amazon_login_rejects_foreign_session_id() : void {
		WC()->session->set( 'amazon_nonce', 'VISITOR_NONCE' );

		$this->set_review_return_nonce( 'ATTACKER_NONCE' );
		$this->assertFalse( $this->is_valid_login_return() );

		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array();
		$this->assertFalse( $this->is_valid_login_return() );

		WC()->session->set( 'amazon_nonce', null );
		$this->set_review_return_nonce( '' );
		$this->assertFalse( $this->is_valid_login_return() );

		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array();
	}

	/**
	 * Test amazon_login rejects a replayed checkout session even when the URL carries the caller's own nonce.
	 *
	 * @return void
	 */
	public function test_amazon_login_rejects_replayed_session_id() : void {
		WC()->session->set( 'amazon_nonce', 'ATTACKER_NONCE' );
		$_GET['amazon_nonce'] = 'ATTACKER_NONCE';

		$this->set_review_return_nonce( 'VICTIM_NONCE' );
		$this->assertFalse( $this->is_valid_login_return() );

		unset( $_GET['amazon_nonce'] );
		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array();
	}

	/**
	 * Test amazon_login accepts a checkout session created with the visitor's own nonce.
	 *
	 * @return void
	 */
	public function test_amazon_login_accepts_matching_token() : void {
		WC()->session->set( 'amazon_nonce', 'VISITOR_NONCE' );

		$this->set_review_return_nonce( 'VISITOR_NONCE' );
		$this->assertTrue( $this->is_valid_login_return() );

		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array();
	}

	/**
	 * Test amazon_login rejects a checkout session ID Amazon cannot find.
	 *
	 * @return void
	 */
	public function test_amazon_login_rejects_unknown_session_id() : void {
		WC()->session->set( 'amazon_nonce', 'VISITOR_NONCE' );
		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_error = new WP_Error( 'ResourceNotFound', 'Checkout session not found.' );

		try {
			$this->assertFalse( $this->is_valid_login_return() );
		} finally {
			WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_error = null;
		}
	}

	/**
	 * Test a logged-in link without the account password is refused.
	 *
	 * @return void
	 */
	public function test_logged_in_link_requires_reauth() : void {
		$user_id = $this->set_up_link_checkout( 'BUYER_REAUTH' );

		$_POST['amazon_link_password'] = 'wrong-password';

		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced();
		$this->expectException( Exception::class );

		try {
			$mock_gateway->handle_account_registration( $user_id );
		} finally {
			$this->tear_down_link_checkout();
		}
	}

	/**
	 * Test the link is only written once the order is paid.
	 *
	 * @return void
	 */
	public function test_link_written_only_on_payment_complete() : void {
		list( $mock_gateway, $order, $user_id ) = $this->place_link_order( 'BUYER_PAID' );

		$order->update_status( 'on-hold' );
		$order->update_status( 'failed' );
		$this->assertFalse( $mock_gateway->get_customer_id_from_buyer( 'BUYER_PAID' ) );

		$order->payment_complete();
		$this->assertSame( $user_id, $mock_gateway->get_customer_id_from_buyer( 'BUYER_PAID' ) );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( 'amazon_buyer_link' ) );
	}

	/**
	 * Test the link goes to the customer verified at checkout, not to a customer the order is reassigned to.
	 *
	 * @return void
	 */
	public function test_link_ignores_reassigned_order_customer() : void {
		list( $mock_gateway, $order, $user_id ) = $this->place_link_order( 'BUYER_REASSIGNED' );

		$order->set_customer_id( self::factory()->user->create() );
		$order->save();

		$order->payment_complete();
		$this->assertSame( $user_id, $mock_gateway->get_customer_id_from_buyer( 'BUYER_REASSIGNED' ) );
	}

	/**
	 * Point the mocked checkout session's review return URL at the given login nonce.
	 *
	 * @param string $nonce Login nonce.
	 * @return void
	 */
	protected function set_review_return_nonce( string $nonce ) : void {
		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array(
			'webCheckoutDetails' => (object) array(
				'checkoutReviewReturnUrl' => add_query_arg( 'amazon_nonce', $nonce, 'https://example.org/checkout/?amazon_login=1' ),
			),
		);
	}

	/**
	 * Run the gateway's login return check for a checkout session ID.
	 *
	 * @return bool
	 */
	protected function is_valid_login_return() : bool {
		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced();
		$method       = new ReflectionMethod( $mock_gateway, 'is_valid_login_return' );
		$method->setAccessible( true );

		return $method->invoke( $mock_gateway, 'PRESENTED_SESSION_ID' );
	}

	/**
	 * Run a logged-in, re-authenticated link checkout and create its unpaid order.
	 *
	 * @param string $buyer_id Amazon buyer ID.
	 * @return array
	 */
	protected function place_link_order( string $buyer_id ) : array {
		$user_id = $this->set_up_link_checkout( $buyer_id );

		$_POST['amazon_link_password'] = 'link-password';

		$mock_gateway = new WC_Mocker_Gateway_Amazon_Payments_Advanced();
		$this->assertSame( $user_id, $mock_gateway->handle_account_registration( $user_id ) );
		$this->tear_down_link_checkout();

		$this->assertFalse( $mock_gateway->get_customer_id_from_buyer( $buyer_id ) );

		add_action( 'woocommerce_payment_complete', array( $mock_gateway, 'maybe_link_buyer_after_payment' ) );

		$order = WC_Helper_Order::create_order( $mock_gateway->id, $user_id );
		$mock_gateway->store_buyer_link( $order );
		$order->save();

		return array( $mock_gateway, $order, $user_id );
	}

	/**
	 * Add the amazon_link opt-in to the checkout posted data.
	 *
	 * @param array $data Posted data.
	 * @return array
	 */
	public static function add_amazon_link_to_posted_data( $data ) {
		$data['amazon_link'] = '1';
		return $data;
	}

	/**
	 * Log in a user with a checkout session from an unlinked Amazon buyer who opted in to linking.
	 *
	 * @param string $buyer_id Amazon buyer ID.
	 * @return int
	 */
	protected function set_up_link_checkout( string $buyer_id ) : int {
		$user_id = self::factory()->user->create( array( 'user_pass' => 'link-password' ) );
		wp_set_current_user( $user_id );

		$checkout_session_key = apply_filters( 'woocommerce_amazon_pa_checkout_session_key', 'amazon_checkout_session_id' );
		WC()->session->set( $checkout_session_key, 'TEST_CHECKOUT_SESSION_ID' );

		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array(
			'buyer' => (object) array(
				'buyerId' => $buyer_id,
				'email'   => 'buyer@example.com',
			),
		);

		add_filter( 'woocommerce_checkout_posted_data', array( self::class, 'add_amazon_link_to_posted_data' ) );

		return $user_id;
	}

	/**
	 * Undo set_up_link_checkout().
	 *
	 * @return void
	 */
	protected function tear_down_link_checkout() : void {
		remove_filter( 'woocommerce_checkout_posted_data', array( self::class, 'add_amazon_link_to_posted_data' ) );
		WC_Mocker_Gateway_Amazon_Payments_Advanced::$session_props = array();
		unset( $_POST['amazon_link_password'] );
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
