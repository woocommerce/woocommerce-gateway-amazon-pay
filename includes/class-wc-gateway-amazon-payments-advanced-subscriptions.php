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

		// Legacy methods needed when dealing with legacy subscriptions
		add_action( 'woocommerce_scheduled_subscription_payment_' . $id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . $id, array( $this, 'cancelled_subscription' ) );
		// TODO: Check if needed, may be able to upgrade subscription to use v2 at this point
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $id, array( $this, 'update_failing_payment_method' ), 10, 2 );
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
		if ( 'v1' !== strtolower( $version ) ) {
			return;
		}

		$order_id                    = wc_apa_get_order_prop( $order, 'id' );
		$amazon_billing_agreement_id = get_post_meta( $order_id, 'amazon_billing_agreement_id', true );
		$currency                    = wc_apa_get_order_prop( $order, 'currency' );

		// Cloned meta in renewal order might be prefixed with `_`.
		if ( ! $amazon_billing_agreement_id ) {
			$amazon_billing_agreement_id = get_post_meta( $order_id, '_amazon_billing_agreement_id', true );
		}

		try {
			if ( ! $amazon_billing_agreement_id ) {
				/* translators: placeholder is order ID. */
				throw new Exception( sprintf( __( 'An Amazon Billing Agreement ID was not found in order #%s.', 'woocommerce-gateway-amazon-payments-advanced' ), $order_id ) );
			}

			wc_apa()->log( __METHOD__, "Info: Begin recurring payment for (subscription) order {$order_id} for the amount of {$order->get_total()} {$currency}." );

			/**
			 * 'AuthorizeOnBillingAgreement' has a maximum request quota of 10
			 * and a restore rate of one request every second.
			 *
			 * In sandbox mode, quota = 2 and restore = one every two seconds.
			 *
			 * @see https://payments.amazon.com/documentation/apireference/201751630#201751940
			 */
			$settings = WC_Amazon_Payments_Advanced_API::get_settings();

			sleep( ( 'yes' === $settings['sandbox'] ) ? 2 : 1 );

			// Authorize/Capture recurring payment.
			$result = WC_Amazon_Payments_Advanced_API::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, true );

			if ( $result ) {
				// Payment complete.
				$order->payment_complete();

				wc_apa()->log( __METHOD__, "Info: Successful recurring payment for (subscription) order {$order_id} for the amount of {$order->get_total()} {$currency}." );
			} else {
				$order->update_status( 'failed', __( 'Could not authorize Amazon Pay payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				wc_apa()->log( __METHOD__, "Error: Could not authorize Amazon Pay payment for (subscription) order {$order_id} for the amount of {$order->get_total()} {$currency}." );
			}
		} catch ( Exception $e ) {
			$order->add_order_note( sprintf( __( 'Amazon Pay subscription renewal failed - %s', 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ) );

			wc_apa()->log( __METHOD__, "Error: Exception encountered: {$e->getMessage()}" );
		}
	}

	/**
	 * Use 'CloseBillingAgreement' to disallow future authorizations after
	 * cancelling a subscription.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function cancelled_subscription( $order ) {
		$version = version_compare( $order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';
		if ( 'v1' !== strtolower( $version ) ) {
			return;
		}

		$order_id                    = wc_apa_get_order_prop( $order, 'id' );
		$amazon_billing_agreement_id = get_post_meta( $order_id, 'amazon_billing_agreement_id', true );

		if ( $amazon_billing_agreement_id ) {
			try {
				/**
				 * 'CloseBillingAgreement' has a maximum request quota of 10 and
				 * a restore rate of one request every second.
				 *
				 * In sandbox mode, quota = 2 and restore = one every two seconds.
				 *
				 * @see https://payments.amazon.com/documentation/apireference/201751710#201751950
				 */
				$settings = WC_Amazon_Payments_Advanced_API::get_settings();

				sleep( ( 'yes' === $settings['sandbox'] ) ? 2 : 1 );

				$response = WC_Amazon_Payments_Advanced_API::request(
					array(
						'Action'                   => 'CloseBillingAgreement',
						'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
					)
				);

				$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

				wc_apa()->log( __METHOD__, "Info: CloseBillingAgreement for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}." );
			} catch ( Exception $e ) {
				wc_apa()->log( __METHOD__, "Error: Exception encountered: {$e->getMessage()}" );

				/* translators: placeholder is error message from Amazon Pay API */
				$order->add_order_note( sprintf( __( "Exception encountered in 'CloseBillingAgreement': %s", 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ) );
			}
		} else {
			wc_apa()->log( __METHOD__, "Error: No Amazon Pay billing agreement found for order {$order_id}." );
		}
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
	 * Copy over the billing reference id and billing/shipping address info from
	 * a successful manual payment for a failed renewal.
	 *
	 * @param WC_Subscription $subscription  The subscription for which the
	 *                                       failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful
	 *                                       payment (to make up for the failed
	 *                                       automatic payment).
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$version = version_compare( $renewal_order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';
		if ( 'v1' !== strtolower( $version ) ) {
			return;
		}

		$meta_keys_to_copy = array(
			'amazon_billing_agreement_id',
			'_billing_first_name',
			'_billing_last_name',
			'_billing_email',
			'_billing_phone',
			'_shipping_first_name',
			'_shipping_last_name',
			'_shipping_company',
			'_shipping_address_1',
			'_shipping_address_2',
			'_shipping_city',
			'_shipping_postcode',
			'_shipping_state',
			'_shipping_country',
		);

		$renewal_order_id = wc_apa_get_order_prop( $renewal_order, 'id' );

		foreach ( $meta_keys_to_copy as $meta_key ) {
			$meta_value = get_post_meta( $renewal_order_id, $meta_key, true );

			if ( $meta_value ) {
				$subscription_id = wc_apa_get_order_prop( $subscription, 'id' );
				update_post_meta( $subscription_id, $meta_key, $meta_value );
			}
		}
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
