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

		add_action( 'woocommerce_scheduled_subscription_payment_' . $id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		if ( 'v2' === strtolower( $version ) ) { // These only execute after the migration (not before)
			add_filter( 'woocommerce_amazon_pa_create_checkout_session_params', array( $this, 'recurring_checkout_session' ) );
			add_filter( 'woocommerce_amazon_pa_update_checkout_session_payload', array( $this, 'recurring_checkout_session_update' ), 10, 3 );
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

	private function get_recurring_frequency() {
		$apa_period    = null;
		$apa_interval  = null;
		$apa_timeframe = PHP_INT_MAX;

		foreach ( WC()->cart->recurring_carts as $key => $recurring_cart ) {
			$contents = $recurring_cart->get_cart_contents();
			$first    = reset( $contents );
			if ( ! isset( $first['data'] ) || ! is_a( $first['data'], 'WC_Product' ) ) {
				// Weird, but ok.
				continue;
			}
			$product = $first['data'];

			$interval = WC_Subscriptions_Product::get_interval( $product );
			$period   = WC_Subscriptions_Product::get_period( $product );

			$this_timeframe = PHP_INT_MAX;

			switch ( strtolower( $period ) ) {
				case 'year':
					$this_timeframe = YEAR_IN_SECONDS * $interval;
					break;
				case 'month':
					$this_timeframe = MONTH_IN_SECONDS * $interval;
					break;
				case 'week':
					$this_timeframe = WEEK_IN_SECONDS * $interval;
					break;
				case 'day':
					$this_timeframe = DAY_IN_SECONDS * $interval;
					break;
			}

			if ( $this_timeframe < $apa_timeframe ) {
				$apa_timeframe = $this_timeframe;
				$apa_period    = $period;
				$apa_interval  = $interval;
			}
		}

		switch ( strtolower( $apa_period ) ) {
			case 'year':
			case 'month':
			case 'week':
			case 'day':
				$apa_period = ucfirst( strtolower( $apa_period ) );
				break;
			default:
				$apa_period   = 'Variable';
				$apa_interval = '0';
				break;
		}

		return array(
			'unit'  => $apa_period,
			'value' => $apa_interval,
		);
	}

	public function recurring_checkout_session( $payload ) {
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() && ( ! isset( $_GET['order_id'] ) || ! wcs_order_contains_subscription( $_GET['order_id'] ) ) ) {
			return $payload;
		}

		WC()->cart->calculate_totals();

		$subscriptions_in_cart = is_array( WC()->cart->recurring_carts ) ? count( WC()->cart->recurring_carts ) : 0;

		if ( 0 === $subscriptions_in_cart ) {
			// Weird, but ok.
			return $payload;
		}

		$payload['chargePermissionType'] = 'Recurring';

		$payload['recurringMetadata'] = array(
			'frequency' => $this->get_recurring_frequency(),
			'amount'    => null,
		);

		if ( 1 === $subscriptions_in_cart ) {
			$payload['recurringMetadata']['amount'] = array(
				'amount'       => WC()->cart->get_total( 'edit' ),
				'currencyCode' => get_woocommerce_currency(),
			);
		}

		return $payload;
	}

	public function recurring_checkout_session_update( $payload, $checkout_session_id, $order ) {
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() && ( ! isset( $_GET['order_id'] ) || ! wcs_order_contains_subscription( $_GET['order_id'] ) ) ) {
			return $payload;
		}

		WC()->cart->calculate_totals();

		$subscriptions_in_cart = is_array( WC()->cart->recurring_carts ) ? count( WC()->cart->recurring_carts ) : 0;

		if ( 0 === $subscriptions_in_cart ) {
			// Weird, but ok.
			return $payload;
		}

		$payload['recurringMetadata'] = array(
			'frequency' => $this->get_recurring_frequency(),
			'amount'    => null,
		);

		if ( 1 === $subscriptions_in_cart ) {
			$payload['recurringMetadata']['amount'] = array(
				'amount'       => $order->get_total(),
				'currencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
			);
		}

		return $payload;
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

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $order            The WC_Order object of the order which
	 *                                   the subscription was purchased in.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {
		$version = version_compare( $order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}

		$order_id = $order->get_id();

		$charge_permission_id = $order->get_meta( 'amazon_charge_permission_id' );

		$capture_now = true;
		switch ( WC_Amazon_Payments_Advanced_API::get_settings( 'payment_capture' ) ) {
			case 'authorize':
			case 'manual': // Force manual to be authorize as well
				$capture_now = false;
				break;
		}

		$can_do_async = false;
		if ( ! $capture_now && 'async' === WC_Amazon_Payments_Advanced_API::get_settings( 'authorization_mode' ) ) {
			$can_do_async = true;
		}

		$currency = wc_apa_get_order_prop( $order, 'order_currency' );

		$response = WC_Amazon_Payments_Advanced_API::create_charge(
			$charge_permission_id,
			array(
				'merchantMetadata'              => WC_Amazon_Payments_Advanced_API::get_merchant_metadata( $order_id ),
				'captureNow'                    => $capture_now,
				'canHandlePendingAuthorization' => $can_do_async,
				'chargeAmount'                  => array(
					'amount'       => $amount_to_charge,
					'currencyCode' => $currency,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wc_apa()->log( __METHOD__, "Error processing payment for renewal order #{$order_id}. Charge Permission ID: {$charge_permission_id}", $response );
			$order->add_order_note( sprintf( __( 'Amazon Pay subscription renewal failed - %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );
			return;
		}

		$charge_permission_status = wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
		$charge_status            = wc_apa()->get_gateway()->log_charge_status_change( $order, $response );
	}
}
