<?php
/**
 * Gateway class to support WooCommerce Subscriptions.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Gateway_Amazon_Payments_Advanced_Subscriptions.
 *
 * Extending main gateway class and only loaded if Subscriptions is available.
 */
class WC_Gateway_Amazon_Payments_Advanced_Subscriptions {

	/**
	 * Constructor
	 */
	public function __construct() {

		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 12 );

		add_filter( 'woocommerce_amazon_pa_supports', array( $this, 'add_subscription_support' ) );

		// WC Subscription Hook
		add_filter( 'woocommerce_subscriptions_process_payment_for_change_method_via_pay_shortcode', array( $this, 'filter_payment_method_changed_result' ), 10, 2 );

		add_filter( 'woocommerce_amazon_pa_form_fields_before_legacy', array( $this, 'add_enable_subscriptions_field' ) );
	}

	public function init_handlers() {
		$id      = wc_apa()->get_gateway()->id;
		$version = is_a( wc_apa()->get_gateway(), 'WC_Gateway_Amazon_Payments_Advanced_Legacy' ) ? 'v1' : 'v2';

		if ( 'v2' === strtolower( $version ) ) { // These only execute after the migration (not before)
			add_filter( 'woocommerce_amazon_pa_processed_order', array( $this, 'copy_meta_to_sub' ), 10, 2 );
			add_filter( 'wcs_renewal_order_meta', array( $this, 'copy_meta_from_sub' ), 10, 3 );
		}

		do_action( 'woocommerce_amazon_pa_subscriptions_init', $version );
	}

	/**
	 * Set redirect URL if the result redirect URL is empty
	 *
	 * @param mixed $result
	 * @param WC_Subscription $subscription
	 *
	 * @return mixed
	 */
	public function filter_payment_method_changed_result( $result, $subscription ) {
		if ( empty( $result['redirect'] ) && ! empty( $subscription ) && method_exists( $subscription, 'get_view_order_url' ) ) {
			$result['redirect'] = $subscription->get_view_order_url();
		}
		return $result;
	}

	public function add_subscription_support( $supports ) {
		$supports = array_merge(
			$supports,
			array(
				'subscriptions',
				'subscription_date_changes',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_cancellation',
				'multiple_subscriptions',
				'subscription_payment_method_change_customer',
			)
		);

		return $supports;
	}

	public static function array_insert( $array, $element, $key, $operation = 1 ) {
		$keys = array_keys( $array );
		$pos  = array_search( $key, $keys, true );

		switch ( $operation ) {
			case 1:
				$read_until = $pos + $operation;
				$read_from  = $pos + $operation;
				break;
			case 0:
				$read_until = $pos;
				$read_from  = $pos + 1;
				break;
			case -1:
				$read_until = $pos;
				$read_from  = $pos;
				break;
		}

		$first = array_slice( $array, 0, $read_until, true );
		$last  = array_slice( $array, $read_from, null, true );
		return $first + $element + $last;
	}

	public function add_enable_subscriptions_field( $fields ) {
		$fields = self::array_insert(
			$fields,
			array(
				'subscriptions_enabled' => array(
					'title'       => __( 'Subscriptions support', 'woocommerce-gateway-amazon-payments-advanced' ),
					'label'       => __( 'Enable Subscriptions for carts that contain Subscription items (requires WooCommerce Subscriptions to be installed)', 'woocommerce-gateway-amazon-payments-advanced' ),
					'type'        => 'select',
					'description' => __( 'This will enable support for Subscriptions and make transactions more securely', 'woocommerce-gateway-amazon-payments-advanced' ),
					'default'     => 'yes',
					'options'     => array(
						'yes' => __( 'Yes', 'woocommerce-gateway-amazon-payments-advanced' ),
						'no'  => __( 'No', 'woocommerce-gateway-amazon-payments-advanced' ),
					),
				),
			),
			'advanced_configuration',
			-1
		);

		return $fields;
	}

	/**
	 * Check if order contains subscriptions.
	 *
	 * @param  WC_Order/int $order Order / Order ID.
	 * @return bool Returns true of order contains subscription.
	 */
	public static function order_contains_subscription( $order ) {
		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order ) || wcs_order_contains_renewal( $order ) );
	}

	public function copy_meta_to_sub( $order, $response ) {
		if ( ! self::order_contains_subscription( $order ) ) {
			return;
		}

		$meta_keys_to_copy = array(
			'amazon_charge_permission_id',
			'amazon_payment_advanced_version',
			'woocommerce_version',
		);

		$subscriptions = wcs_get_subscriptions_for_order( $order );
		foreach ( $subscriptions as $subscription ) {
			foreach ( $meta_keys_to_copy as $key ) {
				$subscription->update_meta_data( $key, $order->get_meta( $key ) );
			}
			$subscription->save();
			$charge_permission_status = wc_apa()->get_gateway()->log_charge_permission_status_change( $subscription );
		}
	}

	public function copy_meta_from_sub( $meta, $order, $subscription ) {
		$meta_keys_to_copy = array(
			'amazon_charge_permission_id',
			'amazon_payment_advanced_version',
			'woocommerce_version',
		);

		foreach ( $meta_keys_to_copy as $key ) {
			$meta[] = array(
				'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $subscription->get_meta( $key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			);
		}
		return $meta;
	}
}
