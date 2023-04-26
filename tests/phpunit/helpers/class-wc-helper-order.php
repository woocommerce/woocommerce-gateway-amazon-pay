<?php
/**
 * Order helpers.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * Class WC_Helper_Order.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Helper_Order {

	/**
	 * Delete an order.
	 *
	 * @param int $order_id ID of the order to delete.
	 */
	public static function delete_order( int $order_id ) : void {

		$order = wc_get_order( $order_id );

		// Delete all products in the order.
		foreach ( $order->get_items() as $item ) {
			WC_Helper_Product::delete_product( $item['product_id'] );
		}

		WC_Helper_Shipping::delete_simple_flat_rate();

		// Delete the order post.
		$order->delete( true );
	}

	/**
	 * Create a order.
	 *
	 * @param string     $payment_method_id The ID of the customer the order is for.
	 * @param int        $customer_id The ID of the customer the order is for.
	 * @param int        $total       Total cost of the order. Defaults to 50 (4 x $10 simple helper product + $10 shipping)
	 *                                and can be modified to test $0 orders.
	 * @param WC_Product $product     The product to add to the order.
	 *
	 * @return WC_Order
	 */
	public static function create_order( string $payment_method_id, int $customer_id = 1, int $total = 50, ?WC_Product $product = null ) : WC_Order {
		if ( ! ( $product instanceof WC_Product ) ) {
			$product = WC_Helper_Product::create_and_optionally_save_simple_product();
		}

		WC_Helper_Shipping::create_simple_flat_rate();

		$order_data = array(
			'status'        => 'pending',
			'customer_id'   => $customer_id,
			'customer_note' => '',
			'total'         => '',
		);

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception.
		$order                  = wc_create_order( $order_data );

		// Add order products.
		$item = new WC_Order_Item_Product();
		$item->set_props(
			array(
				'product'  => $product,
				'quantity' => 4,
				'subtotal' => wc_get_price_excluding_tax( $product, array( 'qty' => 4 ) ),
				'total'    => wc_get_price_excluding_tax( $product, array( 'qty' => 4 ) ),
			)
		);
		$item->save();
		$order->add_item( $item );

		// Set billing address.
		$order->set_billing_first_name( 'Jeroen' );
		$order->set_billing_last_name( 'Sormani' );
		$order->set_billing_company( 'Amazon' );
		$order->set_billing_address_1( 'AmazonAddress' );
		$order->set_billing_address_2( '' );
		$order->set_billing_city( 'AmazonCity' );
		$order->set_billing_state( 'I' );
		$order->set_billing_postcode( '12345' );
		$order->set_billing_country( 'GR' );
		$order->set_billing_email( 'admin@example.org' );
		$order->set_billing_phone( '1234567890' );

		// Add shipping costs.
		$shipping_taxes = WC_Tax::calc_shipping_tax( '10', WC_Tax::get_shipping_tax_rates() );
		$rate           = new WC_Shipping_Rate( 'flat_rate_shipping', 'Flat rate shipping', '10', $shipping_taxes, 'flat_rate' );
		$item           = new WC_Order_Item_Shipping();
		$item->set_props(
			array(
				'method_title' => $rate->label,
				'method_id'    => $rate->id,
				'total'        => wc_format_decimal( $rate->cost ),
				'taxes'        => $rate->taxes,
			)
		);
		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
		$order->add_item( $item );

		// Set payment gateway.
		$order->set_payment_method( $payment_method_id );

		// Set totals.
		$order->set_shipping_total( 10 );
		$order->set_discount_total( 0 );
		$order->set_discount_tax( 0 );
		$order->set_cart_tax( 0 );
		$order->set_shipping_tax( 0 );
		$order->set_total( $total );
		$order->save();

		return $order;
	}
}
