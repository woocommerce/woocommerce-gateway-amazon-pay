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


	/**
	 * Get non required fields.
	 *
	 * @return array
	 */
	public static function get_non_required_fields() {

		$non_required_fields = array(
			'billing_last_name',
			'billing_state',
			'billing_phone',
			'shipping_last_name',
			'shipping_state',
		);

		return apply_filters( 'woocommerce_amazon_pa_non_required_fields', $non_required_fields );
	}


	/**
	 * Get non required fields per country.
	 *
	 * @return array
	 */
	public static function get_non_required_fields_per_country() {

		$non_required_fields = array(
			'JP' => array(
				'billing_city',
				'shipping_city',
				'billing-city',
				'shipping-city',
			),
		);

		return apply_filters( 'woocommerce_amazon_pa_non_required_fields_per_country', $non_required_fields );
	}
}
