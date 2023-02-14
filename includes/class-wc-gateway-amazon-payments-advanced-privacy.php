<?php
/**
 * Handle Privacy Cleanup and Export.
 *
 * @package WC_Gateway_Amazon_Pay
 */

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

/**
 * WC_Gateway_Amazon_Payments_Advanced_Privacy
 */
class WC_Gateway_Amazon_Payments_Advanced_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( __( 'Amazon Pay &amp; Login with Amazon', 'woocommerce-gateway-amazon-payments-advanced' ) );

		$this->add_exporter( 'woocommerce-gateway-amazon-payments-advanced-order-data', __( 'WooCommerce Amazon Pay Order Data', 'woocommerce-gateway-amazon-payments-advanced' ), array( $this, 'order_data_exporter' ) );

		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$this->add_exporter( 'woocommerce-gateway-amazon-payments-advanced-subscriptions-data', __( 'WooCommerce Amazon Pay Subscriptions Data', 'woocommerce-gateway-amazon-payments-advanced' ), array( $this, 'subscriptions_data_exporter' ) );
		}

		$this->add_eraser( 'woocommerce-gateway-amazon-payments-advanced-order-data', __( 'WooCommerce Amazon Pay Data', 'woocommerce-gateway-amazon-payments-advanced' ), array( $this, 'order_data_eraser' ) );
	}

	/**
	 * Returns a list of orders that are using one of Amazon's payment methods.
	 *
	 * @param string $email_address Email address to search orders for.
	 * @param int    $page Page being processed.
	 *
	 * @return array WP_Post
	 */
	protected function get_amazon_orders( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query = array(
			'payment_method' => 'amazon_payments_advanced',
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 */
	public function get_privacy_message() {
		/* translators: 1) URL to privacy page. */
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'woocommerce-gateway-amazon-payments-advanced' ), 'https://docs.woocommerce.com/document/privacy-payments/#woocommerce-gateway-amazon-payments-advanced' ) );
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();

		$orders = $this->get_amazon_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-gateway-amazon-payments-advanced' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Amazon Pay authorization id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => $order->get_meta( 'amazon_authorization_id', true, 'edit' ),
						),
						array(
							'name'  => __( 'Amazon Pay capture id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => $order->get_meta( 'amazon_capture_id', true, 'edit' ),
						),
						array(
							'name'  => __( 'Amazon Pay reference id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => $order->get_meta( 'amazon_reference_id', true, 'edit' ),
						),
						array(
							'name'  => __( 'Amazon Pay refunds id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => wp_json_encode( $order->get_meta( 'amazon_refund_id', false, 'edit' ) ),
						),
						array(
							'name'  => __( 'Amazon subscription token', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => $order->get_meta( 'amazon_billing_agreement_id', true, 'edit' ),
						),
						array(
							'name'  => __( 'Amazon Pay charge permission id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => WC_Amazon_Payments_Advanced::get_order_charge_permission( $order->get_id() ),
						),
						array(
							'name'  => __( 'Amazon Pay charge id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Handle exporting data for Subscriptions.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function subscriptions_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$page           = (int) $page;
		$data_to_export = array();

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_payment_method',
				'value'   => 'amazon_payments_advanced',
				'compare' => '=',
			),
			array(
				'key'     => '_billing_email',
				'value'   => $email_address,
				'compare' => '=',
			),
		);

		$subscription_query = array(
			'posts_per_page' => 10,
			'page'           => $page,
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);

		$subscriptions = wcs_get_subscriptions( $subscription_query );

		$done = true;

		if ( 0 < count( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_subscriptions',
					'group_label' => __( 'Subscriptions', 'woocommerce-gateway-amazon-payments-advanced' ),
					'item_id'     => 'subscription-' . $subscription->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'Amazon subscription token', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => $subscription->get_meta( 'amazon_billing_agreement_id', true, 'true' ),
						),
						array(
							'name'  => __( 'Amazon Pay charge permission id', 'woocommerce-gateway-amazon-payments-advanced' ),
							'value' => WC_Amazon_Payments_Advanced::get_order_charge_permission( $subscription->get_id() ),
						),
					),
				);
			}

			$done = 10 > count( $subscriptions );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function order_data_eraser( $email_address, $page ) {
		$orders = $this->get_amazon_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		foreach ( (array) $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			$refunds = $order->get_refunds();
			foreach ( $refunds as $refund ) {
				list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $refund );
				$items_removed                    |= $removed;
				$items_retained                   |= $retained;
				$messages                          = array_merge( $messages, $msgs );
			}

			list( $removed, $retained, $msgs ) = $this->maybe_handle_subscription( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );
			$items_removed                    |= $removed;
			$items_retained                   |= $retained;
			$messages                          = array_merge( $messages, $msgs );
		}

		// Tell core if we have more orders to work on still.
		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Handle eraser of data tied to Subscriptions
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	protected function maybe_handle_subscription( $order ) {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return array( false, false, array() );
		}

		if ( ! wcs_order_contains_subscription( $order ) ) {
			return array( false, false, array() );
		}

		$subscription    = current( wcs_get_subscriptions_for_order( $order->get_id() ) );
		$subscription_id = $subscription->get_id();

		if ( $subscription->has_status( apply_filters( 'wc_amazon_pay_privacy_eraser_subs_statuses', array( 'on-hold', 'active' ) ) ) ) {
			/* translators: 1) Subscription ID. */
			return array( false, true, array( sprintf( __( 'Amazon Payments Advanced data within subscription %1$s has been retained because it is an active Subscription. ', 'woocommerce-gateway-amazon-payments-advanced' ), $subscription_id ) ) );
		}

		return $this->maybe_handle_order( $subscription );
	}

	/**
	 * Handle eraser of data tied to Orders
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	protected function maybe_handle_order( $order ) {
		$meta_to_delete = array(
			'amazon_authorization_id',
			'amazon_authorization_state',
			'amazon_capture_id',
			'amazon_capture_state',
			'amazon_reference_id',
			'amazon_reference_state',
			'amazon_refund_id',
			'amazon_refunds',
			'amazon_billing_agreement_id',
			'amazon_billing_agreement_state',
			'amazon_charge_permission_id',
			'amazon_charge_permission_status',
			'amazon_charge_id',
			'amazon_charge_status',
		);

		$deleted = false;
		foreach ( $meta_to_delete as $key ) {
			$meta_value = $order->get_meta( $key, true, 'edit' );
			if ( empty( $meta_value ) ) {
				continue;
			}
			$order->delete_meta_data( $key );
			$deleted = true;
		}

		$order->save();

		$messages = array();
		if ( $deleted ) {
			$type = __( 'order', 'woocommerce-gateway-amazon-payments-advanced' );
			if ( 'shop_subscription' === $order->get_type() ) {
				$type = __( 'subscription', 'woocommerce-gateway-amazon-payments-advanced' );
			}
			if ( 'shop_order_refund' === $order->get_type() ) {
				$type = __( 'refund', 'woocommerce-gateway-amazon-payments-advanced' );
			}
			/* translators: 1) Object ID 2) Object Type. */
			$messages = array( sprintf( __( 'Amazon Payments Advanced data within %2$s %1$s has been removed.', 'woocommerce-gateway-amazon-payments-advanced' ), $order->get_id(), $type ) );
		}

		return array( $deleted, false, $messages );
	}
}

new WC_Gateway_Amazon_Payments_Advanced_Privacy();
