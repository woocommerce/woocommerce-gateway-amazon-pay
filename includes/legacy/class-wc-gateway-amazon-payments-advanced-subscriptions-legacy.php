<?php
/**
 * Gateway class to support WooCommerce Subscriptions on v1.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Gateway_Amazon_Payments_Advanced_Subscriptions_Legacy.
 */
class WC_Gateway_Amazon_Payments_Advanced_Subscriptions_Legacy {

	/**
	 * Constructor
	 */
	public function __construct() {

		add_action( 'woocommerce_amazon_pa_subscriptions_init', array( $this, 'init_handlers' ), 12 );

	}

	/**
	 * Init Handlers for subscription products
	 *
	 * @param mixed $version gateway current version.
	 */
	public function init_handlers( $version ) {
		$id = wc_apa()->get_gateway()->id;

		// Legacy methods needed when dealing with legacy subscriptions.
		add_action( 'woocommerce_scheduled_subscription_payment_' . $id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . $id, array( $this, 'cancelled_subscription' ) );
		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $id, array( $this, 'update_failing_payment_method' ), 10, 2 );

		add_filter( 'woocommerce_amazon_pa_v1_order_admin_actions_panel', array( $this, 'admin_actions_panel' ), 10, 3 );
		add_action( 'woocommerce_amazon_pa_v1_order_admin_action_authorize_recurring', array( $this, 'admin_action_authorize_recurring' ), 10, 2 );
		add_action( 'woocommerce_amazon_pa_v1_order_admin_action_authorize_capture_recurring', array( $this, 'admin_action_authorize_capture_recurring' ), 10, 2 );
		add_action( 'woocommerce_amazon_pa_v1_cleared_stored_states', array( $this, 'clear_stored_billing_agreement_state' ) );

		if ( 'v1' === strtolower( $version ) ) { // These are only needed when legacy is the active gateway (prior to migration).
			add_filter( 'woocommerce_amazon_pa_process_payment', array( $this, 'process_payment' ), 10, 2 );
			add_filter( 'woocommerce_amazon_pa_get_amazon_order_details', array( $this, 'get_amazon_order_details' ), 10, 2 );
			add_filter( 'woocommerce_amazon_pa_handle_sca_success', array( $this, 'handle_sca_success' ), 10, 3 );
			add_filter( 'woocommerce_amazon_pa_handle_sca_failure', array( $this, 'handle_sca_failure' ), 10, 4 );
		}
	}

	/**
	 * Process payment
	 *
	 * @param mixed $process Shortcircuit parameter.
	 * @param int   $order_id Order that payment is being processed for.
	 *
	 * @return mixed|array If not a subscription, will return the shortcircuit parameter. Otherwise the process_payment typical array.
	 * @throws Exception On errors with payment processing.
	 */
	public function process_payment( $process, $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! WC_Gateway_Amazon_Payments_Advanced_Subscriptions::order_contains_subscription( $order ) && ! wcs_is_subscription( $order ) ) {
			return $process;
		}

		$amazon_reference_id              = isset( $_POST['amazon_reference_id'] ) ? wc_clean( $_POST['amazon_reference_id'] ) : '';
		$amazon_billing_agreement_id      = isset( $_POST['amazon_billing_agreement_id'] ) ? wc_clean( $_POST['amazon_billing_agreement_id'] ) : '';
		$amazon_billing_agreement_details = WC()->session->get( 'amazon_billing_agreement_details' ) ? wc_clean( WC()->session->get( 'amazon_billing_agreement_details' ) ) : false;

		if ( ! $amazon_billing_agreement_id && 'yes' === get_option( 'woocommerce_subscriptions_turn_off_automatic_payments' ) ) {
			return $process;
		}

		try {

			if ( ! $amazon_billing_agreement_id ) {
				throw new Exception( __( 'An Amazon Pay payment method was not chosen.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			$order_total = $order->get_total();
			$currency    = wc_apa_get_order_prop( $order, 'order_currency' );

			$order->update_meta_data( 'amazon_reference_id', $amazon_reference_id );

			wc_apa()->log( "Info: Beginning processing of payment for (subscription) order {$order_id} for the amount of {$order_total} {$currency}." );
			$order->update_meta_data( 'amazon_payment_advanced_version', WC_AMAZON_PAY_VERSION_CV1 );
			$order->update_meta_data( 'woocommerce_version', WC()->version );
			$order->save();

			// Check if we are under SCA.
			$is_sca = WC_Amazon_Payments_Advanced_API_Legacy::is_sca_region();

			if ( 'skip' !== $amazon_billing_agreement_details ) {
				// Set the Billing Agreement Details.
				$this->set_billing_agreement_details( $order, $amazon_billing_agreement_id );
			}
			// Confirm the Billing Agreement.
			$this->confirm_billing_agreement( $order_id, $amazon_billing_agreement_id, $is_sca );

			// Get the Billing Agreement Details, with FULL address (now that we've confirmed).
			$result = $this->get_billing_agreement_details( $order_id, $amazon_billing_agreement_id );
			if ( is_wp_error( $result ) ) {
				$error_msg = $result->get_error_message( 'billing_agreemment_details_failed' ) ? $result->get_error_message( 'billing_agreemment_details_failed' ) : $result->get_error_message();
				throw new Exception( $error_msg );
			}

			// Store the subscription destination.
			$this->store_subscription_destination( $order_id, $result );

			// Store Billing Agreement ID on the order and it's subscriptions.
			$order->update_meta_data( 'amazon_billing_agreement_id', $amazon_billing_agreement_id );
			$order->save();

			wc_apa()->log( "Info: Successfully stored billing agreement in meta for order {$order_id}." );

			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			foreach ( $subscriptions as $subscription ) {
				$subscription_id = wc_apa_get_order_prop( $subscription, 'id' );
				$subscription->update_meta_data( 'amazon_billing_agreement_id', $amazon_billing_agreement_id );
				$subscription->save();

				wc_apa()->log( "Info: Successfully stored billing agreement in meta for subscription {$subscription_id} (parent order {$order_id})." );
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

			wc_apa()->log( "Info: Zero-total initial payment for (subscription) order {$order_id}. Payment marked as complete." );

			// Remove items from cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => wc_apa()->get_gateway()->get_return_url( $order ),
			);
		} catch ( Exception $e ) {

			wc_apa()->log( "Error: Exception encountered: {$e->getMessage()}" );
			/* translators: 1) Error message. */
			wc_add_notice( sprintf( __( 'Error: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ), 'error' );
			return false;
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
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_billing_agreement_id Billing agreement ID.
	 *
	 * @return WP_Error|array WP_Error or parsed response array.
	 */
	private function set_billing_agreement_details( $order, $amazon_billing_agreement_id ) {

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
		/* translators: 1) Amazon Pay Version 2) WooCommerce Version. */
		$version_note = sprintf( __( 'Created by WC_Gateway_Amazon_Pay/%1$s (Platform=WooCommerce/%2$s)', 'woocommerce-gateway-amazon-payments-advanced' ), WC_AMAZON_PAY_VERSION_CV1, WC()->version );

		$request_args = array(
			'Action'                                => 'SetBillingAgreementDetails',
			'AmazonBillingAgreementId'              => $amazon_billing_agreement_id,
			/* translators: 1) Order Number 2) Site URL. */
			'BillingAgreementAttributes.SellerNote' => sprintf( __( 'Order %1$s from %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ), $order->get_order_number(), urlencode( $site_name ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode
			/* translators: 1) Subscription IDs */
			'BillingAgreementAttributes.SellerBillingAgreementAttributes.SellerBillingAgreementId' => sprintf( __( 'Subscription(s): %s.', 'woocommerce-gateway-amazon-payments-advanced' ), implode( ', ', $subscription_ids ) ),
			'BillingAgreementAttributes.SellerBillingAgreementAttributes.StoreName' => $site_name,
			'BillingAgreementAttributes.PlatformId' => WC_Amazon_Payments_Advanced_API_Legacy::AMAZON_PAY_FOR_WOOCOMMERCE_SP_ID,
			'BillingAgreementAttributes.SellerBillingAgreementAttributes.CustomInformation' => $version_note,
		);

		// Update order reference with amounts.
		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $request_args );
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		try {
			$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

			wc_apa()->log( "Info: SetBillingAgreementDetails for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}." );
		} catch ( Exception $e ) {
			wc_apa()->log( "Error: Exception encountered in 'SetBillingAgreementDetails': {$e->getMessage()}" );
		}

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
	private function confirm_billing_agreement( $order_id, $amazon_billing_agreement_id, $is_sca = false ) {
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

		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $confirm_args );
		try {
			$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

			wc_apa()->log( "Info: ConfirmBillingAgreement for Billing Agreement ID: {$amazon_billing_agreement_id}." );
		} catch ( Exception $e ) {
			wc_apa()->log( "Error: Exception encountered in 'ConfirmBillingAgreement': {$e->getMessage()}" );
		}

		return $response;

	}

	/**
	 * Do authorization on an order with a recurring charge.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_billing_agreement_id Recurring object.
	 */
	private function do_authorize_payment( $order, $amazon_billing_agreement_id ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$settings = WC_Amazon_Payments_Advanced_API::get_settings();

		switch ( $settings['payment_capture'] ) {

			case 'manual':
				// Mark as on-hold.
				$order->update_status( 'on-hold', __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				// Reduce stock levels.
				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$order->reduce_order_stock();
				} else {
					wc_reduce_stock_levels( $order->get_id() );
				}

				wc_apa()->log( "Info: 'manual' payment_capture processed for (subscription) order {$order_id}." );

				break;

			case 'authorize':
				// Authorize only.
				$result = WC_Amazon_Payments_Advanced_API_Legacy::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, false );

				if ( $result ) {

					// Mark as on-hold.
					$order->update_status( 'on-hold', __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					// Reduce stock levels.
					if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
						$order->reduce_order_stock();
					} else {
						wc_reduce_stock_levels( $order->get_id() );
					}

					wc_apa()->log( "Info: 'authorize' payment_capture processed for (subscription) order {$order_id}." );

				} else {

					$order->update_status( 'failed', __( 'Could not authorize Amazon payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					wc_apa()->log( "Error: 'authorize' payment_capture failed for (subscription) order {$order_id}." );

				}

				break;

			default:
				// Capture.
				$result = WC_Amazon_Payments_Advanced_API_Legacy::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, true );

				if ( $result ) {

					// Payment complete.
					$order->payment_complete();

					wc_apa()->log( "Info: authorize and capture processed for (subscription) order {$order_id}." );

				} else {

					$order->update_status( 'failed', __( 'Could not authorize Amazon payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					wc_apa()->log( "Error: authorize and capture failed for (subscription) order {$order_id}." );

				}

				break;

		}
	}

	/**
	 * Authorize (and potentially capture) payment for an order w/subscriptions.
	 *
	 * @param int|WC_Order $order                       Order ID or order object.
	 * @param string       $amazon_billing_agreement_id Billing agreement ID.
	 *
	 * @return array Array value for process_payment method.
	 */
	private function authorize_payment( $order, $amazon_billing_agreement_id ) {
		$this->do_authorize_payment( $order, $amazon_billing_agreement_id );

		WC()->cart->empty_cart();

		// Return thank you page redirect.
		return array(
			'result'   => 'success',
			'redirect' => wc_apa()->get_gateway()->get_return_url( $order ),
		);
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
	private function get_billing_agreement_details( $order_id, $amazon_billing_agreement_id ) {
		$response = WC_Amazon_Payments_Advanced_API_Legacy::request(
			array(
				'Action'                   => 'GetBillingAgreementDetails',
				'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
			)
		);

		try {
			$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

			wc_apa()->log( "Info: GetBillingAgreementDetails for Billing Agreement ID: {$amazon_billing_agreement_id}." );
		} catch ( Exception $e ) {
			wc_apa()->log( "Error: Exception encountered in 'GetBillingAgreementDetails': {$e->getMessage()}" );

			//phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return new WP_Error(
				'billing_agreemment_details_failed',
				is_object( $response ) && ! empty( $response->Error ) && is_object( $response->Error->Message ) && ! empty( $response->Error->Message ) ?
				$response->Error->Message :
				/* Translators: The billing agreement id. */
				sprintf( __( 'Amazon API responded with an unexpected error when requesting for "GetBillingAgreementDetails" of billing agreement with ID %s', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_billing_agreement_id )
			);
			//phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		return $response;
	}

	/**
	 * Store the billing and shipping addresses for this order in meta for both
	 * the order and the subscriptions it contains.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $response SetBillingAgreementDetails response object.
	 */
	private function store_subscription_destination( $order_id, $response ) {

		// @codingStandardsIgnoreStart
		if ( ! is_wp_error( $response ) && isset( $response->GetBillingAgreementDetailsResult->BillingAgreementDetails->Destination->PhysicalDestination ) ) {

			$billing_agreement_details = $response->GetBillingAgreementDetailsResult->BillingAgreementDetails;
			// @codingStandardsIgnoreEnd

			wc_apa()->get_gateway()->store_order_address_details( $order_id, $billing_agreement_details );

			$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			foreach ( $subscriptions as $subscription ) {
				$subscription_id = wc_apa_get_order_prop( $subscription, 'id' );
				wc_apa()->get_gateway()->store_order_address_details( $subscription_id, $billing_agreement_details );
			}
		}
	}

	/**
	 * Convenience method to process generic Amazon API response errors.
	 *
	 * @throws Exception Error from API response.
	 *
	 * @param string $context                     Context.
	 * @param object $response                    API response from WC_Amazon_Payments_Advanced_API_Legacy::request().
	 * @param int    $order_id                    Order ID.
	 * @param string $amazon_billing_agreement_id Billing agreement ID.
	 */
	private function handle_generic_api_response_errors( $context, $response, $order_id, $amazon_billing_agreement_id ) {

		if ( is_wp_error( $response ) ) {

			$error_message = $response->get_error_message();

			wc_apa()->log( "Error: WP_Error '{$error_message}' for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}.", null, $context );

			throw new Exception( $error_message );

		}

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			$error_message = (string) $response->Error->Message;
			wc_apa()->log( "Error: API Error '{$error_message}' for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}.", null, $context );

			throw new Exception( $error_message );
		}
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Retrieve full details from the order using 'GetBillingAgreementDetails' (if it contains a subscription).
	 *
	 * @param mixed  $process Shortcircuit parameter.
	 * @param string $amazon_reference_id Reference ID.
	 *
	 * @return mixed|bool|object $process parameter if not supposed to run, Boolean false on failure, object of OrderReferenceDetails on success.
	 */
	public function get_amazon_order_details( $process, $amazon_reference_id ) {
		$not_subscription = (
			! WC_Subscriptions_Cart::cart_contains_subscription()
			||
			'yes' === get_option( 'woocommerce_subscriptions_turn_off_automatic_payments' )
		);

		if ( $not_subscription ) {
			return $process;
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
			$request_args['AddressConsentToken'] = WC_Amazon_Payments_Advanced_API_Legacy::get_access_token();
		}

		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $request_args );

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
	 * @param mixed    $process Shortcircuit parameter.
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Reference ID.
	 *
	 * @return mixed|void $process parameter if not supposed to run, will redirect and exit if it runs.
	 */
	public function handle_sca_success( $process, $order, $amazon_reference_id ) {
		if ( ! WC_Gateway_Amazon_Payments_Advanced_Subscriptions::order_contains_subscription( $order ) && ! wcs_is_subscription( $order ) ) {
			return $process;
		}
		$redirect = wc_apa()->get_gateway()->get_return_url( $order );

		$settings = WC_Amazon_Payments_Advanced_API::get_settings();

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
			if ( 'async' === $settings['authorization_mode'] && isset( $e->transaction_timed_out ) ) {
				wc_apa()->get_gateway()->process_async_auth( $order, $amazon_reference_id );
			} else {
				wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(), 'error' );
				$redirect = wc_get_checkout_url();
			}
		}
		WC()->session->set( 'amazon_billing_agreement_details', 'false' );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * If redirected to failure url, add a notice with right information for the user.
	 *
	 * @param mixed    $process Shortcircuit parameter.
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Reference ID.
	 * @param string   $authorization_status Authorization Status.
	 *
	 * @return mixed|void $process parameter if not supposed to run, will redirect and exit if it runs.
	 */
	public function handle_sca_failure( $process, $order, $amazon_reference_id, $authorization_status ) {
		if ( ! WC_Gateway_Amazon_Payments_Advanced_Subscriptions::order_contains_subscription( $order ) && ! wcs_is_subscription( $order ) ) {
			return $process;
		}
		$redirect = wc_get_checkout_url();

		// Failure will mock AmazonRejected behaviour.
		if ( 'Failure' === $authorization_status ) {
			// Cancel order.
			$order->update_status( 'cancelled', __( 'Could not authorize Amazon payment. Failure on MFA (Multi-Factor Authentication) challenge.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			// Cancel order on amazon.
			WC_Amazon_Payments_Advanced_API_Legacy::cancel_order_reference( $order, 'MFA Failure' );

			// Redirect to cart and amazon logout.
			$redirect = wc_apa()->get_gateway()->get_amazon_logout_url( wc_get_cart_url() );

			// Adds notice and logging.
			wc_add_notice( __( 'There was a problem authorizing your transaction using Amazon Pay. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			wc_apa()->log( 'MFA (Multi-Factor Authentication) Challenge Fail, Status "Failure", reference ' . $amazon_reference_id );
			WC()->session->set( 'amazon_billing_agreement_details', 'false' );
		}

		if ( 'Abandoned' === $authorization_status ) {
			wc_add_notice( __( 'Authentication for the transaction was not completed, please try again selecting another payment instrument from your Amazon wallet.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			wc_apa()->log( 'MFA (Multi-Factor Authentication) Challenge Fail, Status "Abandoned", reference ' . $amazon_reference_id );
			WC()->session->set( 'amazon_billing_agreement_details', 'skip' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float    $amount_to_charge The amount to charge.
	 * @param WC_Order $order Order object.
	 *
	 * @throws Exception When there's an error with the payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $order->get_id() );
		if ( 'v1' !== strtolower( $version ) ) {
			return;
		}

		$order_id                    = wc_apa_get_order_prop( $order, 'id' );
		$amazon_billing_agreement_id = $order->get_meta( 'amazon_billing_agreement_id', true, 'edit' );
		$currency                    = wc_apa_get_order_prop( $order, 'currency' );

		// Cloned meta in renewal order might be prefixed with `_`.
		if ( ! $amazon_billing_agreement_id ) {
			$amazon_billing_agreement_id = $order->get_meta( '_amazon_billing_agreement_id', true, 'edit' );
		}

		try {
			if ( ! $amazon_billing_agreement_id ) {
				/* translators: placeholder is order ID. */
				throw new Exception( sprintf( __( 'An Amazon Billing Agreement ID was not found in order #%s.', 'woocommerce-gateway-amazon-payments-advanced' ), $order_id ) );
			}

			wc_apa()->log( "Info: Begin recurring payment for (subscription) order {$order_id} for the amount of {$order->get_total()} {$currency}." );

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

			$this->do_authorize_payment( $order, $amazon_billing_agreement_id );
		} catch ( Exception $e ) {
			/* translators: 1) Reason. */
			$order->add_order_note( sprintf( __( 'Amazon Pay subscription renewal failed - %s', 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ) );

			wc_apa()->log( "Error: Exception encountered trying to renew subscription with Amazon Pay: {$e->getMessage()}" );
		}
	}

	/**
	 * Use 'CloseBillingAgreement' to disallow future authorizations after
	 * cancelling a subscription.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function cancelled_subscription( $order ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $order->get_id() );
		if ( 'v1' !== strtolower( $version ) ) {
			return;
		}

		$order_id                    = wc_apa_get_order_prop( $order, 'id' );
		$amazon_billing_agreement_id = $order->get_meta( 'amazon_billing_agreement_id', true, 'edit' );

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

				$response = WC_Amazon_Payments_Advanced_API_Legacy::request(
					array(
						'Action'                   => 'CloseBillingAgreement',
						'AmazonBillingAgreementId' => $amazon_billing_agreement_id,
					)
				);

				$this->handle_generic_api_response_errors( __METHOD__, $response, $order_id, $amazon_billing_agreement_id );

				wc_apa()->log( "Info: CloseBillingAgreement for order {$order_id} with billing agreement: {$amazon_billing_agreement_id}." );
			} catch ( Exception $e ) {
				wc_apa()->log( "Error: Exception encountered in 'CloseBillingAgreement': {$e->getMessage()}" );

				/* translators: placeholder is error message from Amazon Pay API */
				$order->add_order_note( sprintf( __( "Exception encountered in 'CloseBillingAgreement': %s", 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ) );
			}
		} else {
			wc_apa()->log( "Error: No Amazon Pay billing agreement found for order {$order_id}." );
		}
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
		$version = WC_Amazon_Payments_Advanced::get_order_version( $renewal_order->get_id() );
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

		foreach ( $meta_keys_to_copy as $meta_key ) {
			$meta_value = $renewal_order->get_meta( $meta_key, true, 'edit' );

			if ( $meta_value ) {
				$subscription->update_meta_data( $meta_key, $meta_value );
				$subscription->save();
			}
		}
	}

	/**
	 * Filter the admin actions available on the admin for the orders.
	 *
	 * @param array    $ret Shortcircuit parameter.
	 * @param WC_Order $order Order object.
	 * @param array    $actions Actions defined so far.
	 *
	 * @return array
	 */
	public function admin_actions_panel( $ret, $order, $actions ) {
		$order_id = $order->get_id();

		$amazon_billing_agreement_id = $order->get_meta( 'amazon_billing_agreement_id', true, 'edit' );
		if ( empty( $amazon_billing_agreement_id ) ) {
			return $ret;
		}

		$amazon_authorization_id = $order->get_meta( 'amazon_authorization_id', true, 'edit' );
		$amazon_capture_id       = $order->get_meta( 'amazon_capture_id', true, 'edit' );

		if ( ! empty( $amazon_authorization_id ) || ! empty( $amazon_capture_id ) ) {
			return $ret;
		}

		$amazon_billing_agreement_state = $this->get_billing_agreement_state( $order_id, $amazon_billing_agreement_id ); // phpcs:ignore WordPress.NamingConventions

		/* translators: 1) Billing Agreement ID 2) Billing Agreement Status. */
		echo wpautop( sprintf( __( 'Billing Agreement %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_billing_agreement_id ), esc_html( $amazon_billing_agreement_state ) ) );

		switch ( $amazon_billing_agreement_state ) {
			case 'Open':
				if ( 'shop_order' === $order->get_type() ) {
					$actions['authorize_recurring'] = array(
						'id'     => $amazon_billing_agreement_id,
						'button' => __( 'Authorize', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

					$actions['authorize_capture_recurring'] = array(
						'id'     => $amazon_billing_agreement_id,
						'button' => __( 'Authorize &amp; Capture', 'woocommerce-gateway-amazon-payments-advanced' ),
					);
				}

				break;
			case 'Suspended':
				echo wpautop( __( 'The agreement has been suspended. Another form of payment is required.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				break;
			case 'Canceled':
			case 'Suspended':
				echo wpautop( __( 'The agreement has been cancelled/closed. No authorizations can be made.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				break;
		}

		return array(
			'actions' => $actions,
		);
	}

	/**
	 * Perform an authorization on a recurring billing agreement
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_billing_agreement_id Billing Agreement ID.
	 */
	public function admin_action_authorize_recurring( $order, $amazon_billing_agreement_id ) {
		$order_id = $order->get_id();
		$order->delete_meta_data( 'amazon_authorization_id' );
		$order->delete_meta_data( 'amazon_capture_id' );
		$order->save();

		// $amazon_billing_agreement_id is billing agreement.
		wc_apa()->log( 'Info: Trying to authorize payment in billing agreement ' . $amazon_billing_agreement_id );

		WC_Amazon_Payments_Advanced_API_Legacy::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, false );
	}

	/**
	 * Perform an authorization and capture on a recurring billing agreement
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_billing_agreement_id Billing Agreement ID.
	 */
	public function admin_action_authorize_capture_recurring( $order, $amazon_billing_agreement_id ) {
		$order_id = $order->get_id();
		$order->delete_meta_data( 'amazon_authorization_id' );
		$order->delete_meta_data( 'amazon_capture_id' );
		$order->save();

		// $amazon_billing_agreement_id is billing agreement.
		wc_apa()->log( 'Info: Trying to authorize and capture payment in billing agreement ' . $amazon_billing_agreement_id );

		WC_Amazon_Payments_Advanced_API_Legacy::authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, true );
	}

	/**
	 * Get auth state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $amazon_billing_agreement_id       Reference ID.
	 *
	 * @return string|bool Returns false if failed
	 */
	public function get_billing_agreement_state( $order_id, $amazon_billing_agreement_id ) {
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return false;
		}

		$state = $order->get_meta( 'amazon_billing_agreement_state', true, 'edit' );
		if ( $state ) {
			return $state;
		}

		$response = $this->get_billing_agreement_details( $order_id, $amazon_billing_agreement_id );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}

		$state = (string) $response->GetBillingAgreementDetailsResult->BillingAgreementDetails->BillingAgreementStatus->State; // phpcs:ignore WordPress.NamingConventions
		// @codingStandardsIgnoreEnd

		$order->update_meta_data( 'amazon_billing_agreement_state', $state );
		$order->save();

		return $state;
	}

	/**
	 * Clear stored billing agreement state
	 *
	 * @param int $order_id Order ID.
	 */
	public function clear_stored_billing_agreement_state( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		$order->delete_meta_data( 'amazon_billing_agreement_state' );
		$order->save();
	}
}
