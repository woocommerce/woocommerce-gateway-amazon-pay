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

		do_action( 'woocommerce_amazon_pa_subscriptions_init', $version );
	}

	/**
	 * Convenience method to process generic Amazon API response errors.
	 *
	 * @throws Exception Error from API response.
	 *
	 * @param string $context                     Context.
	 * @param object $response                    API response from self::request().
	 * @param int    $order_id                    Order ID.
	 * @param string $amazon_billing_agreement_id Billing agreement ID.
	 */
	private function handle_generic_api_response_errors( $context, $response, $order_id, $amazon_billing_agreement_id ) {

		if ( is_wp_error( $response ) ) {

			$error_message = $response->get_error_message();

			wc_apa()->log( $context, "Error: WP_Error '{$error_message}' for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}." );

			throw new Exception( $error_message );

		}

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			$error_message = (string) $response->Error->Message;
			wc_apa()->log( $context, "Error: API Error '{$error_message}' for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}." );

			throw new Exception( $error_message );
		}
		// @codingStandardsIgnoreEnd
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
}
