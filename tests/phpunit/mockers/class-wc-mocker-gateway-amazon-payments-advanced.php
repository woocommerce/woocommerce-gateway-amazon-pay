<?php
/**
 * Amazon Pay Gateway Mocker Class.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * Amazon Pay Gateway Mocker Class.
 */
class WC_Mocker_Gateway_Amazon_Payments_Advanced extends WC_Gateway_Amazon_Payments_Advanced {
	/**
	 * Stores the total to set the order to.
	 *
	 * @var int
	 */
	protected static $order_total;

	/**
	 * Overwrite actual constructor so we avoid hooking twice.
	 *
	 * @param int|string $order_total The order total to be set.
	 */
	public function __construct( $order_total = 50 ) {
		$this->settings = WC_Amazon_Payments_Advanced_API::get_settings();

		self::$order_total = $order_total;
	}

	/**
	 * Get a mocked checkout session object
	 *
	 * @param  bool    $force Wether to force read from amazon, or use the cached data if available.
	 * @param  ?string $checkout_session_id The checkout session id if it exists.
	 * @return false|stdClass the mocked Checkout Session Object from Amazon API
	 */
	public function get_checkout_session( $force = false, $checkout_session_id = null ) {
		if ( ! $this->get_checkout_session_id() ) {
			return false;
		}

		$obj = new stdClass();

		$obj->paymentPreferences = true; //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $obj;
	}

	/**
	 * Update Checkout Session Data
	 *
	 * @param  string $checkout_session_id Checkout Session Id.
	 * @param  array  $payload Data to send to the API.
	 * @return stdClass Mocked API Response.
	 */
	protected function update_checkout_session_data( $checkout_session_id, $payload = array() ) : stdClass {
		return WC_Mocker_Amazon_Payments_Advanced_API::update_checkout_session_data(
			$checkout_session_id,
			$payload
		);
	}

	/**
	 * Get classic create checkout session config to send to Amazon.
	 *
	 * @param  array $payload The payload that will be used to create a checkout session.
	 * @return array
	 */
	protected function get_create_checkout_classic_session_config( $payload ) : array {
		return WC_Mocker_Amazon_Payments_Advanced_API::get_create_checkout_classic_session_config( $payload );
	}

	/**
	 * Get the estimated order amount from the cart totals.
	 *
	 * @return string
	 */
	public static function get_estimated_order_amount() : string {
		return wp_json_encode(
			array(
				'amount'       => WC_Amazon_Payments_Advanced::format_amount( self::$order_total ),
				'currencyCode' => get_woocommerce_currency(),
			)
		);
	}
}
