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
class WC_Gateway_Amazon_Payments_Advanced_Subscriptions extends WC_Gateway_Amazon_Payments_Advanced {

	/**
	 * Constructor
	 */
	public function __construct() {

		parent::__construct();

		$this->supports = array_merge(
			$this->supports,
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

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		add_action( 'woocommerce_subscription_cancelled_' . $this->id, array( $this, 'cancelled_subscription' ) );

		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 2 );
	}

	/**
	 * Enforce: "Amazon imposes a $500 per calendar month limit on the amount of
	 * funds you can charge a buyer.".
	 *
	 * @since 1.0.0
	 * @version 1.7.3
	 *
	 * @see https://payments.amazon.com/documentation/automatic/201752090#201757640
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = parent::is_available();

		// No subscription product in cart.
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return $is_available;
		}

		return $is_available;
	}

	/**
	 * Check if order contains subscriptions.
	 *
	 * @param  int $order_id Order ID.
	 * @return bool Returns true of order contains subscription.
	 */
	protected function order_contains_subscription( $order_id ) {
		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ) {

		if ( ! $this->order_contains_subscription( $order_id ) && ! wcs_is_subscription( $order_id ) ) {
			return parent::process_payment( $order_id );
		}

		$amazon_reference_id              = isset( $_POST['amazon_reference_id'] ) ? wc_clean( $_POST['amazon_reference_id'] ) : '';
		$amazon_billing_agreement_id      = isset( $_POST['amazon_billing_agreement_id'] ) ? wc_clean( $_POST['amazon_billing_agreement_id'] ) : '';
		$amazon_billing_agreement_details = WC()->session->get( 'amazon_billing_agreement_details' ) ? wc_clean( WC()->session->get( 'amazon_billing_agreement_details' ) ) : false;

		if ( ! $amazon_billing_agreement_id && 'yes' === get_option( 'woocommerce_subscriptions_turn_off_automatic_payments' ) ) {
			return parent::process_payment( $order_id );
		}

		try {

			if ( ! $amazon_billing_agreement_id ) {
				throw new Exception( __( 'An Amazon Pay payment method was not chosen.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			$order       = wc_get_order( $order_id );
			$order_total = $order->get_total();
			$currency    = wc_apa_get_order_prop( $order, 'order_currency' );

			update_post_meta( $order_id, 'amazon_reference_id', $amazon_reference_id );

			wc_apa()->log( __METHOD__, "Info: Beginning processing of payment for (subscription) order {$order_id} for the amount of {$order_total} {$currency}." );
			update_post_meta( $order_id, 'amazon_payment_advanced_version', WC_AMAZON_PAY_VERSION );
			update_post_meta( $order_id, 'woocommerce_version', WC()->version );

			// Check if we are under SCA.
			$is_sca = WC_Amazon_Payments_Advanced_API::is_sca_region();

			if ( 'skip' !== $amazon_billing_agreement_details ) {
				// Set the Billing Agreement Details.
				$this->set_billing_agreement_details( $order, $amazon_billing_agreement_id );
			}
			// Confirm the Billing Agreement.
			$this->confirm_billing_agreement( $order_id, $amazon_billing_agreement_id, $is_sca );

			// Get the Billing Agreement Details, with FULL address (now that we've confirmed).
			$result = $this->get_billing_agreement_details( $order_id, $amazon_billing_agreement_id );

			// Store the subscription destination.
			$this->store_subscription_destination( $order_id, $result );

			// Store Billing Agreement ID on the order and it's subscriptions.
			$result = update_post_meta( $order_id, 'amazon_billing_agreement_id', $amazon_billing_agreement_id );

			if ( $result ) {
				wc_apa()->log( __METHOD__, "Info: Successfully stored billing agreement in meta for order {$order_id}." );
			} else {
				wc_apa()->log( __METHOD__, "Error: Failed to store billing agreement in meta for order {$order_id}." );
			}

			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			foreach ( $subscriptions as $subscription ) {
				$subscription_id = wc_apa_get_order_prop( $subscription, 'id' );
				$result          = update_post_meta( $subscription_id, 'amazon_billing_agreement_id', $amazon_billing_agreement_id );

				if ( $result ) {
					wc_apa()->log( __METHOD__, "Info: Successfully stored billing agreement in meta for subscription {$subscription_id} (parent order {$order_id})." );
				} else {
					wc_apa()->log( __METHOD__, "Error: Failed to store billing agreement in meta for subscription {$subscription_id} (parent order {$order_id})." );
				}
			}

			// Stop execution if this is being processed by SCA.
			if ( $is_sca ) {
				return array(
					'result'   => 'success',
					'redirect' => '',
				);
			}

			// Authorize/Capture initial payment, if initial payment required.
			if ( $order_total > 0 ) {
				return $this->authorize_payment( $order, $amazon_billing_agreement_id );
			}

			// No payment needed now, free trial or coupon used - mark order as complete.
			$order->payment_complete();

			wc_apa()->log( __METHOD__, "Info: Zero-total initial payment for (subscription) order {$order_id}. Payment marked as complete." );

			// Remove items from cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {

			wc_apa()->log( __METHOD__, "Error: Exception encountered: {$e->getMessage()}" );
			wc_add_notice( sprintf( __( 'Error: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ), 'error' );
			return;
		}
	}

	/**
	 * Use 'SetBillingAgreementDetails' action to update details of the billing
	 * agreement.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201751700
	 *
	 * @throws Exception Exception from API response error.
	 *
	 * @param WC_Order $order                       Order object.
	 * @param string   $amazon_billing_agreement_id Billing agreement ID.
	 *
	 * @return WP_Error|array WP_Error or parsed response array.
	 */
	public function set_billing_agreement_details( $order, $amazon_billing_agreement_id ) {

		$site_name     = WC_Amazon_Payments_Advanced::get_site_name();
		$subscriptions = wcs_get_subscriptions_for_order( $order );

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$subscription_ids = array();
			foreach ( $subscriptions as $subscription ) {
				$subscription_ids[ $subscription->get_id() ] = $subscription->get_id();
			}
		} else {
			$subscription_ids = wp_list_pluck( $subscriptions, 'id' );
		}
		$version_note = sprintf( __( 'Created by WC_Gateway_Amazon_Pay/%1$s (Platform=WooCommerce/%2$s)', 'woocommerce-gateway-amazon-payments-advanced' ),  WC_AMAZON_PAY_VERSION, WC()->version );

		$request_args = array(
			'Action'                                                                               => 'SetBillingAgreementDetails',
			'AmazonBillingAgreementId'                                                             => $amazon_billing_agreement_id,
			'BillingAgreementAttributes.SellerNote'                                                => sprintf( __( 'Order %1$s from %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ), $order->get_order_number(), urlencode( $site_name ) ),
			'BillingAgreementAttributes.SellerBillingAgreementAttributes.SellerBillingAgreementId' => sprintf( __( 'Subscription(s): %s.', 'woocommerce-gateway-amazon-payments-advanced' ), implode( ', ', $subscription_ids ) ),
			'BillingAgreementAttributes.SellerBillingAgreementAttributes.StoreName'                => $site_name,
			'BillingAgreementAttributes.PlatformId'                                                => 'A1BVJDFFHQ7US4',
			'BillingAgreementAttributes.SellerBillingAgreementAttributes.CustomInformation'        => $version_note,
		);

		// Update order reference with amounts.
		$response = WC_Amazon_Payments_Advanced_API::request( $request_args );
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

		wc_apa()->log( __METHOD__, "Info: SetBillingAgreementDetails for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}." );

		return $response;

	}

	/**
	 * Use 'ConfirmBillingAgreement' action to confirm the billing agreement.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201751710
	 *
	 * @throws Exception Error from API error response.
	 *
	 * @param int    $order_id                    Order ID.
	 * @param string $amazon_billing_agreement_id Billing agreement ID.
	 * @param bool   $is_sca If needs SCA, ConfirmOrderReference needs extra parameters.
	 *
	 * @return WP_Error|array WP_Error or parsed response array
	 */
	public function confirm_billing_agreement( $order_id, $amazon_billing_agreement_id, $is_sca = false ) {
		$confirm_args = array(
			'Action'                   => 'ConfirmBillingAgreement',
			'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
		);

		if ( $is_sca ) {
			// The buyer is redirected to this URL if the MFA is successful.
			$confirm_args['SuccessUrl'] = wc_get_checkout_url();
			// The buyer is redirected to this URL if the MFA is unsuccessful.
			$confirm_args['FailureUrl'] = wc_get_checkout_url();
		}

		$response = WC_Amazon_Payments_Advanced_API::request( $confirm_args );

		$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

		wc_apa()->log( __METHOD__, "Info: ConfirmBillingAgreement for Billing Agreement ID: {$amazon_billing_agreement_id}." );

		return $response;

	}

	/**
	 * Use 'ValidateBillingAgreement' action to validate the billing agreement.
	 *
	 * @see https://payments.amazon.com/documentation/automatic/201752090#201757360
	 *
	 * @throws Exception Error from API response.
	 *
	 * @param string $amazon_billing_agreement_id Billing agreement ID.
	 *
	 * @return WP_Error|array WP_Error or parsed response array
	 */
	public function validate_billing_agreement( $amazon_billing_agreement_id ) {
		$response = WC_Amazon_Payments_Advanced_API::request( array(
			'Action'                   => 'ValidateBillingAgreement',
			'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
		) );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			throw new Exception( (string) $response->Error->Message );
		}

		if ( isset( $response->ValidateBillingAgreementResult->FailureReasonCode ) ) {
			throw new Exception( (string) $response->ValidateBillingAgreementResult->FailureReasonCode );
		}
		// @codingStandardsIgnoreEnd

		return $response;
	}

	/**
	 * Authorize (and potentially capture) payment for an order w/subscriptions.
	 *
	 * @param int|WC_Order $order                       Order ID or order object.
	 * @param string       $amazon_billing_agreement_id Billing agreement ID.
	 *
	 * @return array Array value for process_payment method.
	 */
	public function authorize_payment( $order, $amazon_billing_agreement_id ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		switch ( $this->payment_capture ) {

			case 'manual' :

				// Mark as on-hold.
				$order->update_status( 'on-hold', __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				// Reduce stock levels.
				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$order->reduce_order_stock();
				} else {
					wc_reduce_stock_levels( $order->get_id() );
				}

				wc_apa()->log( __METHOD__, "Info: 'manual' payment_capture processed for (subscription) order {$order_id}." );

				break;

			case 'authorize' :

				// Authorize only.
				$result = WC_Amazon_Payments_Advanced_API::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, false );

				if ( $result ) {

					// Mark as on-hold.
					$order->update_status( 'on-hold', __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					// Reduce stock levels.
					if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
						$order->reduce_order_stock();
					} else {
						wc_reduce_stock_levels( $order->get_id() );
					}

					wc_apa()->log( __METHOD__, "Info: 'authorize' payment_capture processed for (subscription) order {$order_id}." );

				} else {

					$order->update_status( 'failed', __( 'Could not authorize Amazon payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					wc_apa()->log( __METHOD__, "Error: 'authorize' payment_capture failed for (subscription) order {$order_id}." );

				}

				break;

			default :

				// Capture.
				$result = WC_Amazon_Payments_Advanced_API::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, true );

				if ( $result ) {

					// Payment complete.
					$order->payment_complete();

					wc_apa()->log( __METHOD__, "Info: authorize and capture processed for (subscription) order {$order_id}." );

				} else {

					$order->update_status( 'failed', __( 'Could not authorize Amazon payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					wc_apa()->log( __METHOD__, "Error: authorize and capture failed for (subscription) order {$order_id}." );

				}

				break;

		}

		WC()->cart->empty_cart();

		// Return thank you page redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $order            The WC_Order object of the order which
	 *                                   the subscription was purchased in.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {
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
	 * Use 'GetBillingAgreementDetails' action to retrieve details of the billing agreement.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201751710#201751690
	 *
	 * @throws Exception Exception.
	 *
	 * @param int    $order_id                    Order ID.
	 * @param string $amazon_billing_agreement_id Billing agreement ID.
	 *
	 * @return WP_Error|array WP_Error or parsed response array.
	 */
	public function get_billing_agreement_details( $order_id, $amazon_billing_agreement_id ) {
		$response = WC_Amazon_Payments_Advanced_API::request( array(
			'Action'                   => 'GetBillingAgreementDetails',
			'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
		) );

		$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

		wc_apa()->log( __METHOD__, "Info: GetBillingAgreementDetails for Billing Agreement ID: {$amazon_billing_agreement_id}." );
		return $response;
	}

	/**
	 * Store the billing and shipping addresses for this order in meta for both
	 * the order and the subscriptions it contains.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $response SetBillingAgreementDetails response object.
	 */
	public function store_subscription_destination( $order_id, $response ) {

		// @codingStandardsIgnoreStart
		if ( ! is_wp_error( $response ) && isset( $response->GetBillingAgreementDetailsResult->BillingAgreementDetails->Destination->PhysicalDestination ) ) {

			$billing_agreement_details = $response->GetBillingAgreementDetailsResult->BillingAgreementDetails;
			// @codingStandardsIgnoreEnd

			$this->store_order_address_details( $order_id, $billing_agreement_details );

			$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			foreach ( $subscriptions as $subscription ) {
				$subscription_id = wc_apa_get_order_prop( $subscription, 'id' );
				$this->store_order_address_details( $subscription_id, $billing_agreement_details );
			}
		}
	}

	/**
	 * Use 'CloseBillingAgreement' to disallow future authorizations after
	 * cancelling a subscription.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function cancelled_subscription( $order ) {
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

				$response = WC_Amazon_Payments_Advanced_API::request( array(
					'Action'                   => 'CloseBillingAgreement',
					'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
				) );

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
	protected function handle_generic_api_response_errors( $context, $response, $order_id, $amazon_billing_agreement_id ) {

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
	 * Retrieve full details from the order using 'GetBillingAgreementDetails' (if it contains a subscription).
	 *
	 * @param string $amazon_reference_id Reference ID.
	 *
	 * @return bool|object Boolean false on failure, object of OrderReferenceDetails on success.
	 */
	public function get_amazon_order_details( $amazon_reference_id ) {
		$not_subscription = (
			! WC_Subscriptions_Cart::cart_contains_subscription()
			||
			'yes' === get_option( 'woocommerce_subscriptions_turn_off_automatic_payments' )
		);

		if ( $not_subscription ) {
			return parent::get_amazon_order_details( $amazon_reference_id );
		}

		$request_args = array(
			'Action'                   => 'GetBillingAgreementDetails',
			'AmazonBillingAgreementId' => $amazon_reference_id,
		);

		/**
		 * Full address information is available to the 'GetOrderReferenceDetails' call when we're in
		 * "login app" mode and we pass the AddressConsentToken to the API.
		 *
		 * @see the "Getting the Shipping Address" section here: https://payments.amazon.com/documentation/lpwa/201749990
		 */
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();
		if ( 'yes' === $settings['enable_login_app'] ) {
			$request_args['AddressConsentToken'] = WC_Amazon_Payments_Advanced_API::get_access_token();
		}

		$response = WC_Amazon_Payments_Advanced_API::request( $request_args );

		// @codingStandardsIgnoreStart
		if ( ! is_wp_error( $response ) && isset( $response->GetBillingAgreementDetailsResult->BillingAgreementDetails ) ) {
			return $response->GetBillingAgreementDetailsResult->BillingAgreementDetails;
		}
		// @codingStandardsIgnoreEnd

		return false;
	}

	/**
	 * If redirected to success url, proceed with payment and redirect to thank you page.
	 *
	 * @param WC_Order $order
	 * @param string $amazon_reference_id
	 */
	protected function handle_sca_success( $order, $amazon_reference_id ) {
		if ( ! $this->order_contains_subscription( $order->get_id() ) && ! wcs_is_subscription( $order->get_id() ) ) {
			return parent::handle_sca_success( $order, $amazon_reference_id );
		}
		$redirect = $this->get_return_url( $order );

		try {
			// It will process payment and empty the cart.
			// Authorize/Capture initial payment, if initial payment required.
			$order_total = $order->get_total();
			if ( $order_total > 0 ) {
				$this->authorize_payment( $order, $amazon_reference_id );
			} else {
				// No payment needed now, free trial or coupon used - mark order as complete.
				$order->payment_complete();
			}
		} catch ( Exception $e ) {
			// Async (optimal) mode on settings.
			if ( 'async' === $this->authorization_mode && isset( $e->transaction_timed_out ) ) {
				$this->process_async_auth( $order, $amazon_reference_id );
			} else {
				wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(), 'error' );
				$redirect = wc_get_checkout_url();
			}
		}
		WC()->session->set( 'amazon_billing_agreement_details' ,'false' );
		wp_redirect( $redirect );
		exit;
	}

	/**
	 * If redirected to failure url, add a notice with right information for the user.
	 *
	 * @param WC_Order $order
	 * @param string $amazon_reference_id
	 * @param string $authorization_status
	 */
	protected function handle_sca_failure( $order, $amazon_reference_id, $authorization_status ) {
		if ( ! $this->order_contains_subscription( $order->get_id() ) && ! wcs_is_subscription( $order->get_id() ) ) {
			return parent::handle_sca_failure( $order, $amazon_reference_id, $authorization_status );
		}
		$redirect = wc_get_checkout_url();

		// Failure will mock AmazonRejected behaviour.
		if ( 'Failure' === $authorization_status ) {
			// Cancel order.
			$order->update_status( 'cancelled', __( 'Could not authorize Amazon payment. Failure on MFA (Multi-Factor Authentication) challenge.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			// Cancel order on amazon.
			WC_Amazon_Payments_Advanced_API::cancel_order_reference( $order, 'MFA Failure' );

			// Redirect to cart and amazon logout.
			$redirect = wc_apa()->get_amazon_logout_url( wc_get_cart_url() );

			// Adds notice and logging.
			wc_add_notice( __( 'There was a problem authorizing your transaction using Amazon Pay. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			wc_apa()->log( __METHOD__, 'MFA (Multi-Factor Authentication) Challenge Fail, Status "Failure", reference ' . $amazon_reference_id );
			WC()->session->set( 'amazon_billing_agreement_details' ,'false' );
		}

		if ( 'Abandoned' === $authorization_status ) {
			wc_add_notice( __( 'Authentication for the transaction was not completed, please try again selecting another payment instrument from your Amazon wallet.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			wc_apa()->log( __METHOD__, 'MFA (Multi-Factor Authentication) Challenge Fail, Status "Abandoned", reference ' . $amazon_reference_id );
			WC()->session->set( 'amazon_billing_agreement_details' ,'skip' );
		}

		wp_redirect( $redirect );
		exit;
	}
}
