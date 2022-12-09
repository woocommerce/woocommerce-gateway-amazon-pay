<?php
/**
 * Test cases for WC_Amazon_Payments_Advanced_API.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Amazon_Payments_Advanced_API_Test tests functionalities in WC_Amazon_Payments_Advanced_API.
 *
 * @since UNIT_TESTS_VERSION
 */
class WC_Amazon_Payments_Advanced_API_Test extends WP_UnitTestCase {
	public function test_get_default_settings() {
		$this->assertEquals(
			array(
				'enabled'                         => 'yes',
				'title'                           => __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description'                     => __( 'Complete your payment using Amazon Pay!', 'woocommerce-gateway-amazon-payments-advanced' ),
				'merchant_id'                     => '',
				'store_id'                        => '',
				'public_key_id'                   => '',
				'seller_id'                       => '',
				'mws_access_key'                  => '',
				'secret_key'                      => '',
				'payment_region'                  => WC_Amazon_Payments_Advanced_API::get_payment_region_from_country( WC()->countries->get_base_country() ),
				'enable_login_app'                => ( WC_Amazon_Payments_Advanced_API::is_new_installation() ) ? 'yes' : 'no',
				'app_client_id'                   => '',
				'sandbox'                         => 'yes',
				'payment_capture'                 => 'no',
				'authorization_mode'              => 'async',
				'redirect_authentication'         => 'popup',
				'cart_button_display_mode'        => 'button',
				'button_type'                     => 'LwA',
				'button_size'                     => 'small',
				'button_color'                    => 'Gold',
				'button_language'                 => '',
				'hide_standard_checkout_button'   => 'no',
				'debug'                           => 'no',
				'hide_button_mode'                => 'no',
				'amazon_keys_setup_and_validated' => '0',
				'subscriptions_enabled'           => 'yes',
				'mini_cart_button'                => 'no',
				'product_button'                  => 'no',
				'alexa_notifications_support'     => 'no',
			),
			WC_Amazon_Payments_Advanced_API::get_settings(),
		);
	}
}
