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
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		self::$gateway = null;
		delete_option( 'woocommerce_currency' );
	}

	public function tear_down() {
		delete_option( 'woocommerce_amazon_payments_advanced_settings' );
	}

	public function test_is_not_available() {
		$this->assertFalse( self::$gateway->is_available() );
	}

	public function test_is_available() {
		self::update_plugin_settings();
		$this->assertTrue( self::$gateway->is_available() );
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
	}
}
