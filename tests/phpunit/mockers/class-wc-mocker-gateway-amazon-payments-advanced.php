<?php

class WC_Mocker_Gateway_Amazon_Payments_Advanced extends WC_Gateway_Amazon_Payments_Advanced {
	protected static $order_total;

	public function __construct( $order_total = 50 ) {
		$this->settings = WC_Amazon_Payments_Advanced_API::get_settings();

		self::$order_total = $order_total;
	}

	public function get_checkout_session( $force = false, $checkout_session_id = null ) {
		if ( ! $this->get_checkout_session_id() ) {
			return false;
		}

		$obj = new stdClass();

		$obj->paymentPreferences = true;
		return $obj;
	}

	protected function update_checkout_session_data( $checkout_session_id, $payload ) {
		return WC_Mocker_Amazon_Payments_Advanced_API::update_checkout_session_data(
			$checkout_session_id,
			$payload
		);
	}

	protected function get_create_checkout_classic_session_config( $payload ) {
		return WC_Mocker_Amazon_Payments_Advanced_API::get_create_checkout_classic_session_config( $payload );
	}

	/**
	 * Get the estimated order amount from the cart totals.
	 *
	 * @return string
	 */
	protected static function get_estimated_order_amount() {
		return wp_json_encode(
			array(
				'amount'       => self::$order_total,
				'currencyCode' => get_woocommerce_currency(),
			)
		);
	}
}
