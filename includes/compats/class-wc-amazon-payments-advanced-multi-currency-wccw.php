<?php
/**
 * Main class to handle Currency Converter Widget compatibility.
 * https://woocommerce.com/products/currency-converter-widget/
 * Tested up to: 1.6.12
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * This plugin does not need hooks to make it compatible, currency switch happens only on frontend level,
 * native currency is being used to process payments.
 *
 * Class WC_Amazon_Payments_Advanced_Multi_Currency_WCCW
 */
class WC_Amazon_Payments_Advanced_Multi_Currency_WCCW extends WC_Amazon_Payments_Advanced_Multi_Currency_Abstract {

	/**
	 * Get Currency_Converted_Widget selected currency.
	 * In this case it correspond always to native currency.
	 *
	 * @return string
	 */
	public static function get_active_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * This plugin does not need hooks to make it compatible, currency switch happens only on frontend level.
	 *
	 * @return bool
	 */
	public function is_front_end_compatible() {
		return true;
	}

}
