<?php
/**
 * Utils class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Implement Helper methods.
 */
class WC_Amazon_Payments_Advanced_Utils {

	/**
	 * Returns the edit order's screen id.
	 *
	 * Takes into consideration if HPOS is enabled or not.
	 *
	 * @return string
	 */
	public static function get_edit_order_screen_id() {
		if ( ! function_exists( 'wc_get_container' ) || ! class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			return 'shop_order';
		}

		return wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';
	}
}
