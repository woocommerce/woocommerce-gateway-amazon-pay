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

		add_filter( 'woocommerce_amazon_pa_form_fields_before_legacy', array( $this, 'add_enable_subscriptions_field' ) );

		if ( 'yes' !== WC_Amazon_Payments_Advanced_API::get_settings( 'subscriptions_enabled' ) ) {
			return;
		}

		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 12 );

		add_filter( 'woocommerce_amazon_pa_supports', array( $this, 'add_subscription_support' ) );

		// WC Subscription Hook.
		add_filter( 'woocommerce_subscriptions_process_payment_for_change_method_via_pay_shortcode', array( $this, 'filter_payment_method_changed_result' ), 10, 2 );
	}

	/**
	 * Initialize Handlers For subscriptions
	 */
	public function init_handlers() {
		$id      = wc_apa()->get_gateway()->id;
		$version = is_a( wc_apa()->get_gateway(), 'WC_Gateway_Amazon_Payments_Advanced_Legacy' ) ? 'v1' : 'v2';

		add_action( 'woocommerce_scheduled_subscription_payment_' . $id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . $id, array( $this, 'cancelled_subscription' ) );
		add_filter( 'woocommerce_amazon_pa_charge_permission_status_should_fail_order', array( $this, 'subs_not_on_hold' ), 10, 2 );
		add_filter( 'woocommerce_amazon_pa_no_charge_order_on_hold', array( $this, 'subs_not_on_hold' ), 10, 2 );
		add_action( 'woocommerce_amazon_pa_refresh_cached_charge_permission_status', array( $this, 'propagate_status_update_to_related' ), 10, 3 );
		add_filter( 'woocommerce_amazon_pa_checkout_session_key', array( $this, 'maybe_change_session_key' ) );
		add_action( 'woocommerce_amazon_pa_before_processed_order', array( $this, 'maybe_change_payment_method' ), 10, 2 );
		add_filter( 'woocommerce_amazon_pa_processed_order_redirect', array( $this, 'maybe_redirect_to_subscription' ), 10, 2 );
		add_filter( 'woocommerce_amazon_pa_admin_meta_box_post_types', array( $this, 'add_subscription_post_type' ) );
		add_filter( 'woocommerce_amazon_pa_order_admin_actions', array( $this, 'remove_charge_permission_actions_on_recurring' ), 10, 2 );
		add_filter( 'woocommerce_amazon_pa_invalid_session_property', array( $this, 'ignore_amounts_in_session_validation' ), 10, 2 );

		if ( 'v2' === strtolower( $version ) ) { // These only execute after the migration (not before).
			add_filter( 'woocommerce_amazon_pa_create_checkout_session_params', array( $this, 'recurring_checkout_session' ) );
			add_filter( 'woocommerce_amazon_pa_create_checkout_session_classic_params', array( $this, 'recurring_checkout_session' ) );
			add_filter( 'woocommerce_amazon_pa_update_checkout_session_payload', array( $this, 'recurring_checkout_session_update' ), 10, 4 );
			add_filter( 'woocommerce_amazon_pa_update_complete_checkout_session_payload', array( $this, 'recurring_complete_checkout_session_update' ), 10, 3 );
			add_filter( 'woocommerce_amazon_pa_processed_order', array( $this, 'copy_meta_to_sub' ), 10, 2 );
			add_filter( 'wcs_renewal_order_meta', array( $this, 'copy_meta_from_sub' ), 10, 3 );
			add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'maybe_not_update_payment_method' ), 10, 2 );
		}

		do_action( 'woocommerce_amazon_pa_subscriptions_init', $version );
	}

	/**
	 * Set redirect URL if the result redirect URL is empty
	 *
	 * @param mixed           $result Result object to filter.
	 * @param WC_Subscription $subscription Subscription object.
	 *
	 * @return mixed
	 */
	public function filter_payment_method_changed_result( $result, $subscription ) {
		if ( empty( $result['redirect'] ) && ! empty( $subscription ) && method_exists( $subscription, 'get_view_order_url' ) ) {
			$result['redirect'] = $subscription->get_view_order_url();
		}
		return $result;
	}

	/**
	 * Add subscription support to the gateway
	 *
	 * @param  array $supports List of supported features.
	 * @return array
	 */
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
				'subscription_amount_changes',
				// TODO: Implement upgrades/downgrades.
			)
		);

		return $supports;
	}

	/**
	 * Insert an item in an array before or after another item.
	 *
	 * @param  array $array Source array.
	 * @param  mixed $element Element to insert.
	 * @param  mixed $key Key to insert around.
	 * @param  int   $operation Operation to perform.
	 * @return array Returns a new array
	 */
	public static function array_insert( $array, $element, $key, $operation = 1 ) {
		$keys = array_keys( $array );
		$pos  = array_search( $key, $keys, true );

		switch ( $operation ) {
			case 0:
				$read_until = $pos;
				$read_from  = $pos + 1;
				break;
			case -1:
				$read_until = $pos;
				$read_from  = $pos;
				break;
			default:
				$read_until = $pos + $operation;
				$read_from  = $pos + $operation;
				break;
		}

		$first = array_slice( $array, 0, $read_until, true );
		$last  = array_slice( $array, $read_from, null, true );
		return $first + $element + $last;
	}

	/**
	 * Add subscription field to the gateway settings
	 *
	 * @param  array $fields Admin fields.
	 * @return array
	 */
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
	 * @param  WC_Order|int $order Order / Order ID.
	 * @return bool Returns true of order contains subscription.
	 */
	public static function order_contains_subscription( $order ) {
		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order ) || wcs_order_contains_renewal( $order ) );
	}

	/**
	 * Get recurring frequency from the cart
	 *
	 * @return array Standard data object to be used in API calls.
	 */
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

		return $this->parse_interval_to_apa_frequency( $apa_period, $apa_interval );
	}

	/**
	 * Parse WC interval into Amazon Pay frequency object.
	 *
	 * @param  string     $apa_period WC Period.
	 * @param  int|string $apa_interval WC Interval.
	 * @return array
	 */
	public function parse_interval_to_apa_frequency( $apa_period = null, $apa_interval = null ) {
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

		if ( is_null( $apa_interval ) ) {
			$apa_interval = '1';
		}

		return array(
			'unit'  => $apa_period,
			/**
			 * Amazon accepts the recurringMetadata.frequency.value as string.
			 *
			 * Casting $apa_interval to string ensures consistency.
			 *
			 * @see https://developer.amazon.com/docs/amazon-pay-api-v2/checkout-session.html#type-frequency
			 */
			'value' => (string) $apa_interval,
		);
	}

	/**
	 * Filter the payload to add recurring data to the checkout session creation object.
	 *
	 * @param  array $payload Payload to create checkout session (JS button).
	 * @return array
	 */
	public function recurring_checkout_session( $payload ) {
		if ( ! class_exists( 'WC_Subscriptions_Cart' ) ) {
			return $payload;
		}

		if ( 'yes' === get_option( 'woocommerce_subscriptions_turn_off_automatic_payments' ) ) {
			return $payload;
		}

		$cart_contains_subscription      = WC_Subscriptions_Cart::cart_contains_subscription();
		$cart_contains_renewal           = wcs_cart_contains_renewal();
		$change_payment_for_subscription = isset( $_GET['change_payment_method'] ) && wcs_is_subscription( absint( $_GET['change_payment_method'] ) );

		if ( ! $cart_contains_renewal && ! $cart_contains_subscription && ! $change_payment_for_subscription ) {
			return $payload;
		}

		if ( ! is_wc_endpoint_url( 'order-pay' ) && $cart_contains_subscription ) {
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
				$first_recurring                        = reset( WC()->cart->recurring_carts );
				$payload['recurringMetadata']['amount'] = array(
					'amount'       => WC_Amazon_Payments_Advanced::format_amount( $first_recurring->get_total( 'edit' ) ),
					'currencyCode' => get_woocommerce_currency(),
				);
			}
		} elseif ( $cart_contains_renewal || $change_payment_for_subscription ) {
			if ( $cart_contains_renewal ) {
				if ( ! isset( $cart_contains_renewal['subscription_renewal'] ) || ! isset( $cart_contains_renewal['subscription_renewal']['subscription_id'] ) ) {
					return $payload;
				}

				$subscription = wcs_get_subscription( $cart_contains_renewal['subscription_renewal']['subscription_id'] );
			} elseif ( $change_payment_for_subscription ) {
				if ( ! isset( $_GET['change_payment_method'] ) ) {
					return $payload;
				}

				$subscription = wcs_get_subscription( absint( $_GET['change_payment_method'] ) );
			} else {
				return $payload;
			}

			$payload['chargePermissionType'] = 'Recurring';

			$payload['recurringMetadata'] = array(
				'frequency' => $this->parse_interval_to_apa_frequency( $subscription->get_billing_period( 'edit' ), $subscription->get_billing_interval( 'edit' ) ),
				'amount'    => array(
					'amount'       => WC_Amazon_Payments_Advanced::format_amount( $subscription->get_total() ),
					'currencyCode' => wc_apa_get_order_prop( $subscription, 'order_currency' ),
				),
			);
		}

		return $payload;
	}

	/**
	 * Filter the payload to add recurring data to the checkout session update object.
	 *
	 * @param  array    $payload Payload to send to the API before proceeding to checkout.
	 * @param  string   $checkout_session_id Checkout Session Id.
	 * @param  WC_Order $order Order object.
	 * @param  bool     $doing_classic_payment Indicates whether this is an Amazon "Classic" Transaction or not.
	 * @return array
	 */
	public function recurring_checkout_session_update( $payload, $checkout_session_id, $order, $doing_classic_payment ) {
		if ( isset( $_POST['_wcsnonce'] ) && isset( $_POST['woocommerce_change_payment'] ) && $order->get_id() === absint( $_POST['woocommerce_change_payment'] ) ) {
			$checkout_session = wc_apa()->get_gateway()->get_checkout_session();

			$payload['paymentDetails']['paymentIntent'] = 'Confirm';
			unset( $payload['paymentDetails']['canHandlePendingAuthorization'] );

			$payload['paymentDetails']['chargeAmount'] = WC_Amazon_Payments_Advanced::format_amount( $checkout_session->recurringMetadata->amount ); // phpcs:ignore WordPress.NamingConventions

			return $payload;
		}

		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() && ( ! isset( $_GET['order_id'] ) || ! wcs_order_contains_subscription( $_GET['order_id'] ) ) ) {
			return $payload;
		}

		WC()->cart->calculate_totals();

		$subscriptions_in_cart = is_array( WC()->cart->recurring_carts ) ? count( WC()->cart->recurring_carts ) : 0;

		if ( 0 === $subscriptions_in_cart ) {
			return $payload;
		}

		$payload['recurringMetadata'] = array(
			'frequency' => $this->get_recurring_frequency(),
			'amount'    => null,
		);

		$recurring_total = 0;
		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$recurring_total += $recurring_cart->get_total( 'edit' );
		}

		$recurring_total = wc_format_decimal( $recurring_total, '' );

		if ( 1 === $subscriptions_in_cart ) {
			$payload['recurringMetadata']['amount'] = array(
				'amount'       => WC_Amazon_Payments_Advanced::format_amount( $recurring_total ),
				'currencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
			);
		}

		if ( 0 >= (float) WC()->cart->get_total( 'edit' ) ) {
			$payload['paymentDetails']['paymentIntent'] = 'Confirm';
			unset( $payload['paymentDetails']['canHandlePendingAuthorization'] );

			$payload['paymentDetails']['chargeAmount']['amount'] = WC_Amazon_Payments_Advanced::format_amount( $recurring_total );
		}

		return $payload;
	}

	/**
	 * Filter payload to complete recurring checkout session
	 *
	 * @param  array $payload Payload for the complete checkout session API call.
	 * @return array
	 */
	public function recurring_complete_checkout_session_update( $payload ) {
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() && ( ! isset( $_GET['order_id'] ) || ! wcs_order_contains_subscription( $_GET['order_id'] ) ) ) {
			return $payload;
		}
		WC()->cart->calculate_totals();

		$subscriptions_in_cart = is_array( WC()->cart->recurring_carts ) ? count( WC()->cart->recurring_carts ) : 0;

		if ( 0 === $subscriptions_in_cart ) {
			// Weird, but ok.
			return $payload;
		}

		$amount = (float) $payload['chargeAmount']['amount'];
		if ( 0 < $amount ) {
			return $payload;
		}

		$recurring_total = 0;
		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$recurring_total += $recurring_cart->get_total( 'edit' );
		}

		$recurring_total = wc_format_decimal( $recurring_total, '' );

		$payload['chargeAmount']['amount'] = WC_Amazon_Payments_Advanced::format_amount( $recurring_total );

		return $payload;
	}

	/**
	 * Copy meta from order to the relevant subscriptions
	 *
	 * @param  WC_Order $order Order object.
	 * @param  object   $response Response from the API.
	 */
	public function copy_meta_to_sub( $order, $response ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $order->get_id() );
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}

		if ( ! self::order_contains_subscription( $order ) ) {
			return;
		}

		$meta_keys_to_copy = array(
			'amazon_payment_advanced_version',
			'woocommerce_version',
		);

		$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => array( 'parent', 'renewal' ) ) );
		foreach ( $subscriptions as $subscription ) {
			$perm_status = wc_apa()->get_gateway()->get_cached_charge_permission_status( $subscription, true );
			if ( isset( $perm_status->status ) && 'Closed' !== $perm_status->status ) {
				$this->cancelled_subscription( $subscription );
			}
			wc_apa()->get_gateway()->log_charge_permission_status_change( $subscription, $response->chargePermissionId ); // phpcs:ignore WordPress.NamingConventions
			foreach ( $meta_keys_to_copy as $key ) {
				$value = $order->get_meta( $key );
				if ( empty( $value ) ) {
					continue;
				}

				$subscription->update_meta_data( $key, $value );
			}
			$subscription->save();
		}
	}

	/**
	 * Filter data to be copied from the subscription to the renewal
	 *
	 * @param  array           $meta Array of meta to copy from the subscription.
	 * @param  WC_Order        $order Order object.
	 * @param  WC_Subscription $subscription Susbcription Object.
	 * @return array
	 */
	public function copy_meta_from_sub( $meta, $order, $subscription ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $subscription->get_id() );
		if ( 'v2' !== strtolower( $version ) ) {
			return $meta;
		}

		$meta_keys_to_copy = array(
			'amazon_charge_permission_id',
			'amazon_charge_permission_status',
			'amazon_payment_advanced_version',
			'woocommerce_version',
		);

		foreach ( $meta_keys_to_copy as $key ) {
			$value = $subscription->get_meta( $key );
			if ( empty( $value ) ) {
				continue;
			}

			$meta[] = array(
				'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			);
		}
		return $meta;
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $order Order object.
	 *                                   the subscription was purchased in.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $order->get_id() );
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}

		$order_id = $order->get_id();

		$charge_permission_id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $order->get_id() );

		$capture_now = true;
		switch ( WC_Amazon_Payments_Advanced_API::get_settings( 'payment_capture' ) ) {
			case 'authorize':
			case 'manual': // Force manual to be authorize as well.
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
					'amount'       => WC_Amazon_Payments_Advanced::format_amount( $amount_to_charge ),
					'currencyCode' => $currency,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wc_apa()->log( "Error processing payment for renewal order #{$order_id}. Charge Permission ID: {$charge_permission_id}", $response );
			/* translators: 1) Reason. */
			$order->add_order_note( sprintf( __( 'Amazon Pay subscription renewal failed - %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );
			wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
			$order->update_status( 'failed' );
			wc_maybe_increase_stock_levels( $order->get_id() );
			return;
		}

		wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
		wc_apa()->get_gateway()->log_charge_status_change( $order, $response );
	}

	/**
	 * Cancelled subscription hook
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 */
	public function cancelled_subscription( $subscription ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $subscription->get_id() );
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}

		// Prevent double running. WCS Bug.
		// TODO: Report bug. PR that introduced this in WCS https://github.com/woocommerce/woocommerce-subscriptions/pull/2777 .
		if ( isset( $subscription->handled_cancel ) ) {
			return;
		}

		$subscription->handled_cancel = true;

		$order_id = $subscription->get_id();

		$charge_permission_id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $order_id );

		if ( empty( $charge_permission_id ) ) {
			unset( $subscription->handled_cancel );
			return;
		}

		$response = WC_Amazon_Payments_Advanced_API::close_charge_permission( $charge_permission_id, 'Subscription Cancelled' );

		if ( is_wp_error( $response ) ) {
			wc_apa()->log( "Error processing cancellation for subscription #{$order_id}. Charge Permission ID: {$charge_permission_id}", $response );
			unset( $subscription->handled_cancel );
			return;
		}

		wc_apa()->get_gateway()->log_charge_permission_status_change( $subscription );

		unset( $subscription->handled_cancel );
	}

	/**
	 * Wether the subscription should move to on-hold
	 *
	 * @param  bool     $fail Wether to fail or not.
	 * @param  WC_Order $order Order object.
	 * @return bool
	 */
	public function subs_not_on_hold( $fail, $order ) {
		if ( is_a( $order, 'WC_Subscription' ) ) {
			return false;
		}
		return $fail;
	}

	/**
	 * Propagate status change from one order or subscription, to related orders.
	 *
	 * @param  WC_Order|WC_Subscription $_order Order object.
	 * @param  string                   $charge_permission_id Charge Permission ID.
	 * @param  object                   $charge_permission_status Charge Permission Status.
	 */
	public function propagate_status_update_to_related( $_order, $charge_permission_id, $charge_permission_status ) {
		$order = $_order;

		if ( wcs_is_subscription( $order ) ) {
			$related = $order->get_related_orders( 'all', array( 'parent' ) );
			$order   = reset( $related );
		}

		$log_note = sprintf( 'Propagating status change on Order ID #%d.', $order->get_id() );
		if ( $order->get_id() !== $_order->get_id() ) {
			$log_note .= ' ' . sprintf( 'Source Order ID #%d.', $_order->get_id() );
		}

		wc_apa()->log( $log_note );

		if ( $order->get_id() !== $_order->get_id() ) {
			$this->handle_order_propagation( $order, $charge_permission_id, $charge_permission_status );
		}

		$subs = wcs_get_subscriptions_for_order( $order );

		foreach ( $subs as $subscription ) {
			if ( $_order->get_id() !== $subscription->get_id() ) {
				$this->handle_order_propagation( $subscription, $charge_permission_id, $charge_permission_status );
			}

			$related_orders = $subscription->get_related_orders( 'all', array( 'renewal' ) );
			foreach ( $related_orders as $rel_order ) {
				if ( $_order->get_id() !== $rel_order->get_id() ) {
					$this->handle_order_propagation( $rel_order, $charge_permission_id, $charge_permission_status );
				}
			}
		}
	}

	/**
	 * Do a specific order status propagation action
	 *
	 * @param  WC_Order $rel_order Order Object.
	 * @param  string   $charge_permission_id Charge Permission ID.
	 * @param  object   $charge_permission_status Charge Permission Status.
	 */
	protected function handle_order_propagation( $rel_order, $charge_permission_id, $charge_permission_status ) {
		$rel_type = 'order';
		if ( is_a( $rel_order, 'WC_Subscription' ) ) {
			$rel_type = 'subscription';
		}
		$current_charge_permission_id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $rel_order->get_id() );
		if ( $current_charge_permission_id !== $charge_permission_id ) {
			return;
		}
		$old_status = wc_apa()->get_gateway()->get_cached_charge_permission_status( $rel_order, true )->status;
		$new_status = $charge_permission_status->status; // phpcs:ignore WordPress.NamingConventions
		$need_note  = $new_status !== $old_status;
		wc_apa()->log( sprintf( 'Propagating status to %2$s ID #%1$d.', $rel_order->get_id(), $rel_type ) );
		$rel_order->update_meta_data( 'amazon_charge_permission_status', wp_json_encode( $charge_permission_status ) );
		$rel_order->save();

		if ( $need_note ) {
			wc_apa()->log( sprintf( 'Adding status change note for %2$s #%1$d', $rel_order->get_id(), $rel_type ) );
			wc_apa()->get_gateway()->add_status_change_note( $rel_order, $charge_permission_id, $new_status );
		}
	}

	/**
	 * Maybe change session key to store checkout session on order pay screen.
	 *
	 * @param  string $session_key Session Key Used.
	 * @return string
	 */
	public function maybe_change_session_key( $session_key ) {
		if ( isset( $_POST['_wcsnonce'] ) && isset( $_POST['woocommerce_change_payment'] ) ) {
			$order_id = absint( $_POST['woocommerce_change_payment'] );
			return 'amazon_checkout_session_id_order_pay_' . $order_id;
		}
		return $session_key;
	}

	/**
	 * Maybe not update payment method if it's already the same.
	 *
	 * @param  bool   $update Wether to Update.
	 * @param  string $method New method.
	 * @return bool   False if the gateway shouldn't update. True, otherwise.
	 */
	public function maybe_not_update_payment_method( $update, $method ) {
		$id = wc_apa()->get_gateway()->id;
		if ( $method === $id ) {
			return false;
		}
		return $update;
	}

	/**
	 * Change payment method after processing order.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  object   $response Charge Completion response from the Amazon API.
	 */
	public function maybe_change_payment_method( $order, $response ) {
		if ( ! isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		if ( ! is_a( $order, 'WC_Subscription' ) ) {
			return;
		}

		WC_Subscriptions_Change_Payment_Gateway::update_payment_method( $order, wc_apa()->get_gateway()->id );
	}

	/**
	 * Filter the redirect URL
	 *
	 * @param  string   $redirect Redirect URL.
	 * @param  WC_Order $order Order object.
	 * @return string
	 */
	public function maybe_redirect_to_subscription( $redirect, $order ) {
		if ( ! isset( $_GET['change_payment_method'] ) ) {
			return $redirect;
		}

		if ( ! is_a( $order, 'WC_Subscription' ) ) {
			return $redirect;
		}

		return $order->get_view_order_url();
	}

	/**
	 * Add subscription post type to the admin meta box to be rendered.
	 *
	 * @param  array $post_types Post Types to render the meta box on.
	 * @return array
	 */
	public function add_subscription_post_type( $post_types ) {
		$post_types[] = 'shop_subscription';
		return $post_types;
	}

	/**
	 * Clean up some charge permission meta box actions on recurring.
	 *
	 * @param  array    $actions Ations on the meta box.
	 * @param  WC_Order $order Order object.
	 * @return array
	 */
	public function remove_charge_permission_actions_on_recurring( $actions, $order ) {
		$charge_permission_cached_status = wc_apa()->get_gateway()->get_cached_charge_permission_status( $order );
		if ( ! isset( $charge_permission_cached_status->type ) || 'Recurring' !== $charge_permission_cached_status->type ) {
			return $actions;
		}
		if ( 'shop_order' === $order->get_type() ) {
			if ( ! $order->has_status( array( 'pending', 'on-hold', 'failed' ) ) ) {
				unset( $actions['authorize'] );
				unset( $actions['authorize_capture'] );
			} else {
				$charge_cached_status = wc_apa()->get_gateway()->get_cached_charge_status( $order, true );
				if ( ! is_null( $charge_cached_status->status ) && 'Canceled' !== $charge_cached_status->status ) {
					unset( $actions['authorize'] );
					unset( $actions['authorize_capture'] );
				}
			}
		} elseif ( 'shop_subscription' === $order->get_type() ) {
			unset( $actions['authorize'] );
			unset( $actions['authorize_capture'] );
		}
		return $actions;
	}

	/**
	 * Ignore recurring properties
	 *
	 * @param  bool   $valid Wether the data is invalid or not.
	 * @param  object $data Order object.
	 * @return bool
	 */
	public function ignore_amounts_in_session_validation( $valid, $data ) {
		if ( $valid ) {
			return $valid;
		}

		switch ( $data->prop ) {
			case 'recurringMetadata.amount.amount':
				return true; // returning true turns ignores the invalid value, and considers it valid.
		}

		return $valid;
	}
}
