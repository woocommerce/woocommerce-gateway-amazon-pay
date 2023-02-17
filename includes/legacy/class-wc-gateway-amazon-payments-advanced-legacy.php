<?php
/**
 * Legacy Gateway for v1 orders.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Gateway_Amazon_Payments_Advanced_Legacy
 */
class WC_Gateway_Amazon_Payments_Advanced_Legacy extends WC_Gateway_Amazon_Payments_Advanced_Abstract {

	/**
	 * Amazon Order Reference ID (when not in "login app" mode checkout)
	 *
	 * @var string
	 */
	protected $reference_id;

	/**
	 * Amazon Pay Access Token ("login app" mode checkout)
	 *
	 * @var string
	 */
	protected $access_token;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Get Order Refererence ID and/or Access Token.
		$this->reference_id = WC_Amazon_Payments_Advanced_API_Legacy::get_reference_id();
		$this->access_token = WC_Amazon_Payments_Advanced_API_Legacy::get_access_token();

		// Handling for the review page of the German Market Plugin.
		if ( empty( $this->reference_id ) ) {
			if ( isset( $_SESSION['first_checkout_post_array']['amazon_reference_id'] ) ) {
				$this->reference_id = $_SESSION['first_checkout_post_array']['amazon_reference_id'];
			}
		}

		// Filter order received text for timedout transactions.
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'maybe_render_timeout_transaction_order_received_text' ), 10, 2 );

		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'store_shipping_info_in_session' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'check_customer_coupons' ) );
		add_action( 'wc_amazon_async_authorize', array( $this, 'process_payment_with_async_authorize' ), 10, 2 );

		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 11 );

		add_filter( 'woocommerce_ajax_get_endpoint', array( $this, 'filter_ajax_endpoint' ), 10, 2 );

		add_action( 'wp_footer', array( $this, 'maybe_hide_standard_checkout_button' ) );
		add_action( 'wp_footer', array( $this, 'maybe_hide_amazon_buttons' ) );

		// AJAX calls to get updated order reference details.
		add_action( 'wp_ajax_amazon_get_order_reference', array( $this, 'ajax_get_order_reference' ) );
		add_action( 'wp_ajax_nopriv_amazon_get_order_reference', array( $this, 'ajax_get_order_reference' ) );

		// Add SCA processing and redirect.
		add_action( 'template_redirect', array( $this, 'handle_sca_url_processing' ), 10, 2 );

		// SCA Strong Customer Authentication Upgrade.
		add_action( 'wp_ajax_amazon_sca_processing', array( $this, 'ajax_sca_processing' ) );
		add_action( 'wp_ajax_nopriv_amazon_sca_processing', array( $this, 'ajax_sca_processing' ) );

		// Log out from Amazon handlers.
		add_action( 'woocommerce_thankyou_amazon_payments_advanced', array( $this, 'logout_from_amazon' ) );
		$this->maybe_attempt_to_logout();

		// Declined notice.
		$this->maybe_display_declined_notice();
	}

	/**
	 * Amazon Pay is available if the following conditions are met (on top of
	 * WC_Payment_Gateway::is_available).
	 *
	 * 1) Login App mode is enabled and we have an access token from Amazon
	 * 2) Login App mode is *not* enabled and we have an order reference id
	 * 3) In checkout pay page.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			return parent::is_available();
		}

		$is_available = apply_filters( 'woocommerce_amazon_pa_is_gateway_available', true );

		if ( ! $is_available ) {
			return false;
		}

		$login_app_enabled = ( 'yes' === $this->enable_login_app );
		$standard_mode_ok  = ( ! $login_app_enabled && ! empty( $this->reference_id ) );
		$login_app_mode_ok = ( $login_app_enabled && ! empty( $this->access_token ) );

		return ( parent::is_available() && ( $standard_mode_ok || $login_app_mode_ok ) );
	}

	/**
	 * Has fields.
	 *
	 * @return bool
	 */
	public function has_fields() {
		return is_checkout_pay_page();
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		if ( $this->has_fields() ) : ?>

			<?php if ( empty( $this->reference_id ) && empty( $this->access_token ) ) : ?>
				<div>
					<div id="pay_with_amazon"></div>
					<?php echo esc_html( apply_filters( 'woocommerce_amazon_pa_checkout_message', __( 'Have an Amazon account?', 'woocommerce-gateway-amazon-payments-advanced' ) ) ); ?>
				</div>
			<?php else : ?>
				<div class="wc-amazon-payments-advanced-order-day-widgets">
					<div id="amazon_wallet_widget"></div>
					<div id="amazon_consent_widget"></div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $this->reference_id ) ) : ?>
				<input type="hidden" name="amazon_reference_id" value="<?php echo esc_attr( $this->reference_id ); ?>" />
			<?php endif; ?>
			<?php if ( ! empty( $this->access_token ) ) : ?>
				<input type="hidden" name="amazon_access_token" value="<?php echo esc_attr( $this->access_token ); ?>" />
			<?php endif; ?>

			<?php
		endif;
	}

	/**
	 * Get the shipping address from Amazon and store in session.
	 *
	 * This makes tax/shipping rate calculation possible on AddressBook Widget selection.
	 *
	 * @since 1.0.0
	 * @version 1.8.0
	 */
	public function store_shipping_info_in_session() {
		if ( ! $this->reference_id ) {
			return;
		}

		$order_details = $this->get_amazon_order_details( $this->reference_id );

		// @codingStandardsIgnoreStart
		if ( ! $order_details || ! isset( $order_details->Destination->PhysicalDestination ) ) {
			return;
		}

		$address = WC_Amazon_Payments_Advanced_API::format_address( $order_details->Destination->PhysicalDestination );
		$address = $this->normalize_address( $address );
		// @codingStandardsIgnoreEnd

		foreach ( array( 'country', 'state', 'postcode', 'city' ) as $field ) {
			if ( ! isset( $address[ $field ] ) ) {
				continue;
			}

			$this->set_customer_info( $field, $address[ $field ] );
			$this->set_customer_info( 'shipping_' . $field, $address[ $field ] );
		}
	}

	/**
	 * Normalized address after formatted.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param array $address Address.
	 *
	 * @return array Address.
	 */
	protected function normalize_address( $address ) {
		/**
		 * US postal codes comes back as a ZIP+4 when in "Login with Amazon App"
		 * mode.
		 *
		 * This is too specific for the local delivery shipping method,
		 * and causes the zip not to match, so we remove the +4.
		 */
		if ( 'US' === $address['country'] ) {
			$code_parts          = explode( '-', $address['postcode'] );
			$address['postcode'] = $code_parts[0];
		}

		$states = WC()->countries->get_states( $address['country'] );
		if ( empty( $states ) ) {
			return $address;
		}

		// State might be in city, so use that if state is not passed by
		// Amazon. But if state is available we still need the WC state key.
		$state = '';
		if ( ! empty( $address['state'] ) ) {
			$state = array_search( $address['state'], $states, true );
		}
		if ( ! $state && ! empty( $address['city'] ) ) {
			$state = array_search( $address['city'], $states, true );
		}
		if ( $state ) {
			$address['state'] = $state;
		}

		return $address;
	}

	/**
	 * Set customer info.
	 *
	 * WC 3.0.0 deprecates some methods in customer setter, especially for billing
	 * related address. This method provides compatibility to set customer billing
	 * info.
	 *
	 * @since 1.7.0
	 *
	 * @param string $setter_suffix Setter suffix.
	 * @param mixed  $value         Value to set.
	 */
	protected function set_customer_info( $setter_suffix, $value ) {
		$setter             = array( WC()->customer, 'set_' . $setter_suffix );
		$is_shipping_setter = strpos( $setter_suffix, 'shipping_' ) !== false;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) && ! $is_shipping_setter ) {
			$setter = array( WC()->customer, 'set_billing_' . $setter_suffix );
		}

		call_user_func( $setter, $value );
	}

	/**
	 * Check customer coupons after checkout validation.
	 *
	 * Since the checkout fields were hijacked and billing_email is not present,
	 * we need to call WC()->cart->check_customer_coupons again.
	 *
	 * @since 1.7.0
	 *
	 * @param array $posted Posted data.
	 */
	public function check_customer_coupons( $posted ) {
		if ( ! $this->reference_id ) {
			return;
		}

		$order_details = $this->get_amazon_order_details( $this->reference_id );
		// @codingStandardsIgnoreStart
		if ( ! $order_details || ! isset( $order_details->Buyer->Email ) ) {
			return;
		}

		if ( $this->id === $posted['payment_method'] ) {
			$posted['billing_email'] = empty( $posted['billing_email'] )
				? (string) $order_details->Buyer->Email
				: $posted['billing_email'];

			WC()->cart->check_customer_coupons( $posted );
		}
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Process payment.
	 *
	 * @version 1.7.1
	 *
	 * @param int $order_id Order ID.
	 *
	 * @throws Exception On Errors.
	 */
	public function process_payment( $order_id ) {
		$process = apply_filters( 'woocommerce_amazon_pa_process_payment', null, $order_id );
		if ( ! is_null( $process ) ) {
			return $process;
		}

		$order               = wc_get_order( $order_id );
		$amazon_reference_id = isset( $_POST['amazon_reference_id'] ) ? wc_clean( $_POST['amazon_reference_id'] ) : '';

		try {
			if ( ! $order ) {
				throw new Exception( __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			if ( ! $amazon_reference_id ) {
				throw new Exception( __( 'An Amazon Pay payment method was not chosen.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			/**
			 * If presentmentCurrency is updated (currency switched on checkout):
			 * If you need to update the presentmentCurrency of an order, you must change the presentmentCurrency in the Wallet widget by re-rendering the Wallet widget.
			 * You also must send a SetOrderReferenceDetails API call with the new OrderTotal, which matches the presentmentCurrency in the Wallet widget.
			 *
			 * @link https://pay.amazon.com/uk/developer/documentation/lpwa/4KGCA5DV6XQ4GUJ
			 */
			if ( WC_Amazon_Payments_Advanced_Multi_Currency::is_active() && WC_Amazon_Payments_Advanced_Multi_Currency::is_currency_switched_on_checkout() ) {
				$this->set_order_reference_details( $order, $amazon_reference_id );
			}

			$order_total = $order->get_total();
			$currency    = wc_apa_get_order_prop( $order, 'order_currency' );

			wc_apa()->log( "Info: Beginning processing of payment for order {$order_id} for the amount of {$order_total} {$currency}. Amazon reference ID: {$amazon_reference_id}." );
			$order->update_meta_data( 'amazon_payment_advanced_version', WC_AMAZON_PAY_VERSION_CV1 );
			$order->update_meta_data( 'woocommerce_version', WC()->version );
			$order->save();

			// Get order details and save them to the order later.
			$order_details = $this->get_amazon_order_details( $amazon_reference_id );

			// @codingStandardsIgnoreStart
			$order_language = isset( $order_details->OrderLanguage )
				? (string) $order_details->OrderLanguage
				: 'unknown';
			// @codingStandardsIgnoreEnd

			/**
			 * Only set order reference details if state is 'Draft'.
			 *
			 * @link https://pay.amazon.com/de/developer/documentation/lpwa/201953810
			 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/214
			 */
			// @codingStandardsIgnoreStart
			$state = isset( $order_details->OrderReferenceStatus->State )
				? (string) $order_details->OrderReferenceStatus->State
				: 'unknown';
			// @codingStandardsIgnoreEnd

			if ( 'Draft' === $state ) {
				// Update order reference with amounts.
				$this->set_order_reference_details( $order, $amazon_reference_id );
			}

			// Check if we are under SCA.
			$is_sca = WC_Amazon_Payments_Advanced_API_Legacy::is_sca_region();
			// Confirm order reference.
			$this->confirm_order_reference( $amazon_reference_id, $is_sca );

			/**
			 * If retrieved order details missing `Name`, additional API
			 * request is needed after `ConfirmOrderReference` action to retrieve
			 * more information about `Destination` and `Buyer`.
			 *
			 * This happens when access token is not set in the request or merchants
			 * have **Use Amazon Login App** disabled.
			 *
			 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/234
			 */
			// @codingStandardsIgnoreStart
			if ( ! isset( $order_details->Destination->PhysicalDestination->Name ) || ! isset( $order_details->Buyer->Name ) ) {
				$order_details = $this->get_amazon_order_details( $amazon_reference_id );
			}
			// @codingStandardsIgnoreEnd

			// Store buyer address in order.
			if ( $order_details ) {
				$this->store_order_address_details( $order, $order_details );
			}

			// Store reference ID in the order.
			$order->update_meta_data( 'amazon_reference_id', $amazon_reference_id );
			$order->update_meta_data( '_transaction_id', $amazon_reference_id );
			$order->update_meta_data( 'amazon_order_language', $order_language );
			$order->save();

			wc_apa()->log( sprintf( 'Info: Payment Capture method is %s', $this->payment_capture ? $this->payment_capture : 'authorize and capture' ) );

			// Stop execution if this is being processed by SCA.
			if ( $is_sca ) {
				wc_apa()->log( sprintf( 'Info: SCA processing enabled. Transaction will be captured asynchronously' ) );
				return array(
					'result'   => 'success',
					'redirect' => '',
				);
			}

			// It will process payment and empty the cart.
			$this->process_payment_capture( $order, $amazon_reference_id );

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);

		} catch ( Exception $e ) {
			// Async (optimal) mode on settings.
			if ( 'async' === $this->authorization_mode && isset( $e->transaction_timed_out ) ) {
				$this->process_async_auth( $order, $amazon_reference_id );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}
			wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(), 'error' );
		}
	}

	/**
	 * Checks payment_capture mode and process payment accordingly.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Associated Amazon reference ID.
	 *
	 * @throws Exception Declined transaction.
	 */
	protected function process_payment_capture( $order, $amazon_reference_id ) {

		switch ( $this->payment_capture ) {
			case 'manual':
				$this->process_payment_with_manual( $order, $amazon_reference_id );
				break;
			case 'authorize':
				$this->process_payment_with_authorize( $order, $amazon_reference_id );
				break;
			default:
				$this->process_payment_with_capture( $order, $amazon_reference_id );
		}

		// Remove cart.
		WC()->cart->empty_cart();
	}

	/**
	 * Process asynchronous Authorization.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Reference ID.
	 */
	public function process_async_auth( $order, $amazon_reference_id ) {

		$order->update_meta_data( 'amazon_timed_out_transaction', true );
		$order->save();

		$order->update_status( 'on-hold', __( 'Transaction with Amazon Pay is currently being validated.', 'woocommerce-gateway-amazon-payments-advanced' ) );

		// https://pay.amazon.com/it/developer/documentation/lpwa/201953810
		// Make an ASYNC Authorize API call using a TransactionTimeout of 1440.
		$response = $this->process_payment_with_async_authorize( $order, $amazon_reference_id );

		$amazon_authorization_id = WC_Amazon_Payments_Advanced_API_Legacy::get_auth_id_from_response( $response );
		$args                    = array(
			'order_id'                => $order->get_id(),
			'amazon_authorization_id' => $amazon_authorization_id,
		);
		// Schedule action to check pending order next hour.
		if ( false === as_next_scheduled_action( 'wcga_process_pending_syncro_payments', $args ) ) {
			as_schedule_single_action( strtotime( 'next hour' ), 'wcga_process_pending_syncro_payments', $args );
		}
	}

	/**
	 * Process payment without authorizing and capturing the payment.
	 *
	 * This means store doesn't authorize and capture the payment when an order
	 * is placed. Store owner will manually authorize and capture the payment
	 * via edit order screen.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Amazon Order Reference ID.
	 */
	protected function process_payment_with_manual( $order, $amazon_reference_id ) {
		wc_apa()->log( 'Info: No Authorize or Capture call.' );

		// Mark as on-hold.
		$order->update_status( 'on-hold', __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );

		// Reduce stock levels.

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order->reduce_order_stock();
		} else {
			wc_reduce_stock_levels( $order->get_id() );
		}
	}

	/**
	 * Process payment with authorizing the payment.
	 *
	 * Store owner will capture manually the payment via edit order screen.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Amazon Order Reference ID.
	 *
	 * @throws Exception Declined transaction.
	 */
	protected function process_payment_with_authorize( $order, $amazon_reference_id ) {
		wc_apa()->log( 'Info: Trying to authorize payment in order reference ' . $amazon_reference_id );

		// Authorize only.
		$authorize_args = array(
			'amazon_reference_id' => $amazon_reference_id,
			'capture_now'         => false,
		);

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$result = WC_Amazon_Payments_Advanced_API_Legacy::authorize( $order_id, $authorize_args );
		if ( is_wp_error( $result ) ) {
			$this->process_payment_check_declined_error( $order_id, $result );
		}

		$result = WC_Amazon_Payments_Advanced_API_Legacy::handle_payment_authorization_response( $result, $order_id, false );
		if ( $result ) {
			// Mark as on-hold.
			$order->update_status( 'on-hold', __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );

			// Reduce stock levels.
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$order->reduce_order_stock();
			} else {
				wc_reduce_stock_levels( $order->get_id() );
			}

			wc_apa()->log( 'Info: Successfully authorized in order reference ' . $amazon_reference_id );
		} else {
			$order->update_status( 'failed', __( 'Could not authorize Amazon payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

			wc_apa()->log( 'Error: Failed to authorize in order reference ' . $amazon_reference_id );
		}
	}

	/**
	 * In asynchronous mode, the Authorize operation always returns the State as Pending. The authorisation remains in this state until it is processed by Amazon.
	 * The processing time varies and can be a minute or more. After processing is complete, Amazon notifies you of the final processing status.
	 * Transaction Timeout always set to 1440.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Amazon Order Reference ID.
	 *
	 * @return SimpleXMLElement Response.
	 */
	public function process_payment_with_async_authorize( $order, $amazon_reference_id ) {
		wc_apa()->log( 'Info: Trying to ASYNC authorize payment in order reference ' . $amazon_reference_id );

		$authorize_args = array(
			'amazon_reference_id' => $amazon_reference_id,
			'capture_now'         => ( 'authorize' === $this->payment_capture ) ? false : true,
			'transaction_timeout' => 1440,
		);
		$order_id       = wc_apa_get_order_prop( $order, 'id' );
		return WC_Amazon_Payments_Advanced_API_Legacy::authorize( $order_id, $authorize_args );
	}

	/**
	 * Process payment with authorizing and capturing.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Amazon Order Reference ID.
	 *
	 * @throws Exception Declined transaction.
	 */
	protected function process_payment_with_capture( $order, $amazon_reference_id ) {
		wc_apa()->log( 'Info: Trying to capture payment in order reference ' . $amazon_reference_id );

		// Authorize and capture.
		$authorize_args = array(
			'amazon_reference_id' => $amazon_reference_id,
			'capture_now'         => true,
		);

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$result = WC_Amazon_Payments_Advanced_API_Legacy::authorize( $order_id, $authorize_args );
		if ( is_wp_error( $result ) ) {
			$this->process_payment_check_declined_error( $order_id, $result );
		}

		$result = WC_Amazon_Payments_Advanced_API_Legacy::handle_payment_authorization_response( $result, $order_id, true );
		if ( $result ) {
			// Payment complete.
			$order->payment_complete();

			// Close order reference.
			WC_Amazon_Payments_Advanced_API_Legacy::close_order_reference( $order_id );

			wc_apa()->log( 'Info: Successfully captured in order reference ' . $amazon_reference_id );

		} else {
			$order->update_status( 'failed', __( 'Could not authorize Amazon payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );

			wc_apa()->log( 'Error: Failed to capture in order reference ' . $amazon_reference_id );
		}
	}

	/**
	 * Check authorize result for a declined error.
	 *
	 * @since 1.7.0
	 *
	 * @throws Exception Declined transaction.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $result   Return value from WC_Amazon_Payments_Advanced_API_Legacy::request().
	 */
	protected function process_payment_check_declined_error( $order_id, $result ) {
		if ( ! is_wp_error( $result ) ) {
			return;
		}

		$code = $result->get_error_code();
		WC()->session->set( 'amazon_declined_code', $code );

		// If the transaction timed out and async authorization is enabled then throw an exception here and don't set
		// any of the session state related to declined orders. We'll let the calling code re-try by triggering an async
		// authorization.
		if ( in_array( $code, array( 'TransactionTimedOut' ), true ) && 'async' === $this->authorization_mode ) {
			$e = new Exception( $result->get_error_message() );

			$e->transaction_timed_out = true;
			throw $e;
		}

		WC()->session->set( 'reload_checkout', true );
		if ( in_array( $code, array( 'AmazonRejected', 'ProcessingFailure', 'TransactionTimedOut' ), true ) ) {
			WC()->session->set( 'amazon_declined_order_id', $order_id );
			WC()->session->set( 'amazon_declined_with_cancel_order', true );
		}
		throw new Exception( $result->get_error_message() );
	}

	/**
	 * Process refund.
	 *
	 * @since 1.6.0
	 *
	 * @param  int    $order_id      Order ID.
	 * @param  float  $refund_amount Amount to refund.
	 * @param  string $reason        Reason to refund.
	 *
	 * @return WP_Error|boolean True or false based on success, or a WP_Error object.
	 */
	public static function do_process_refund( $order_id, $refund_amount = null, $reason = '' ) {
		wc_apa()->log( 'Info: Trying to refund for order ' . $order_id );
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			/* translators: Order number */
			return new WP_Error( 'error', sprintf( __( 'Unable to refund order %s. Order id is invalid.', 'woocommerce-gateway-amazon-payments-advanced' ), $order_id ) );
		}

		$amazon_capture_id = $order->get_meta( 'amazon_capture_id', true, 'edit' );
		if ( empty( $amazon_capture_id ) ) {
			/* translators: Order number */
			return new WP_Error( 'error', sprintf( __( 'Unable to refund order %s. Order does not have Amazon capture reference. Make sure order has been captured.', 'woocommerce-gateway-amazon-payments-advanced' ), $order_id ) );
		}

		$ret = WC_Amazon_Payments_Advanced_API_Legacy::refund_payment( $order_id, $amazon_capture_id, $refund_amount, $reason );

		return $ret;
	}

	/**
	 * Instance wrapper to process refund
	 *
	 * @param  int    $order_id      Order ID.
	 * @param  float  $refund_amount Amount to refund.
	 * @param  string $reason        Reason to refund.
	 * @return WP_Error|boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $refund_amount = null, $reason = '' ) {
		return self::do_process_refund( $order_id, $refund_amount, $reason );
	}

	/**
	 * Use 'SetOrderReferenceDetails' action to update details of the order reference.
	 *
	 * By default, use data from the WC_Order and WooCommerce / Site settings, but offer the ability to override.
	 *
	 * @throws Exception Error from API request.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Reference ID.
	 * @param array    $overrides           Optional. Override values sent to
	 *                                      the Amazon Pay API for the
	 *                                      SetOrderReferenceDetails request.
	 *
	 * @return WP_Error|array WP_Error or parsed response array
	 */
	public function set_order_reference_details( $order, $amazon_reference_id, $overrides = array() ) {

		$site_name = WC_Amazon_Payments_Advanced::get_site_name();

		/* translators: Order number and site's name */
		$seller_note = sprintf( __( 'Order %1$s from %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ), $order->get_order_number(), rawurlencode( $site_name ) );
		/* translators: Plugin version */
		$version_note = sprintf( __( 'Created by WC_Gateway_Amazon_Pay/%1$s (Platform=WooCommerce/%2$s)', 'woocommerce-gateway-amazon-payments-advanced' ), WC_AMAZON_PAY_VERSION . '-legacy', WC()->version );

		$request_args = array_merge(
			array(
				'Action'                              => 'SetOrderReferenceDetails',
				'AmazonOrderReferenceId'              => $amazon_reference_id,
				'OrderReferenceAttributes.OrderTotal.Amount' => $order->get_total(),
				'OrderReferenceAttributes.OrderTotal.CurrencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
				'OrderReferenceAttributes.SellerNote' => $seller_note,
				'OrderReferenceAttributes.SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
				'OrderReferenceAttributes.SellerOrderAttributes.StoreName' => $site_name,
				'OrderReferenceAttributes.PlatformId' => WC_Amazon_Payments_Advanced_API_Legacy::AMAZON_PAY_FOR_WOOCOMMERCE_SP_ID,
				'OrderReferenceAttributes.SellerOrderAttributes.CustomInformation' => $version_note,
			),
			$overrides
		);

		// Update order reference with amounts.
		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $request_args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			throw new Exception( (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		/**
		 * Check for constraints. Should bailed early on checkout and prompt
		 * the buyer again with the wallet for corrective action.
		 *
		 * @see https://payments.amazon.com/developer/documentation/apireference/201752890
		 */
		// @codingStandardsIgnoreStart
		if ( isset( $response->SetOrderReferenceDetailsResult->OrderReferenceDetails->Constraints->Constraint->ConstraintID ) ) {
			$constraint = (string) $response->SetOrderReferenceDetailsResult->OrderReferenceDetails->Constraints->Constraint->ConstraintID;
			// @codingStandardsIgnoreEnd

			switch ( $constraint ) {
				case 'BuyerEqualSeller':
					throw new Exception( __( 'You cannot shop on your own store.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				case 'PaymentPlanNotSet':
					throw new Exception( __( 'You have not selected a payment method from your Amazon account. Please choose a payment method for this order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				case 'PaymentMethodNotAllowed':
					throw new Exception( __( 'There has been a problem with the selected payment method from your Amazon account. Please update the payment method or choose another one.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				case 'ShippingAddressNotSet':
					throw new Exception( __( 'You have not selected a shipping address from your Amazon account. Please choose a shipping address for this order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
		}

		return $response;

	}

	/**
	 * Helper method to call 'ConfirmOrderReference' API action.
	 *
	 * @throws Exception Error from API request.
	 *
	 * @param string $amazon_reference_id Reference ID.
	 * @param bool   $is_sca If needs SCA, ConfirmOrderReference needs extra parameters.
	 *
	 * @return WP_Error|array WP_Error or parsed response array
	 */
	public function confirm_order_reference( $amazon_reference_id, $is_sca = false ) {

		$confirm_args = array(
			'Action'                 => 'ConfirmOrderReference',
			'AmazonOrderReferenceId' => $amazon_reference_id,
		);

		if ( $is_sca ) {
			// The buyer is redirected to this URL if the MFA is successful.
			$confirm_args['SuccessUrl'] = wc_get_checkout_url();
			// The buyer is redirected to this URL if the MFA is unsuccessful.
			$confirm_args['FailureUrl'] = wc_get_checkout_url();
		}

		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $confirm_args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			throw new Exception( (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return $response;

	}

	/**
	 * Retrieve full details from the order using 'GetOrderReferenceDetails'.
	 *
	 * @param string $amazon_reference_id Reference ID.
	 *
	 * @return bool|object Boolean false on failure, object of OrderReferenceDetails on success.
	 */
	public function get_amazon_order_details( $amazon_reference_id ) {
		$process = apply_filters( 'woocommerce_amazon_pa_get_amazon_order_details', null, $amazon_reference_id );
		if ( ! is_null( $process ) ) {
			return $process;
		}

		$request_args = array(
			'Action'                 => 'GetOrderReferenceDetails',
			'AmazonOrderReferenceId' => $amazon_reference_id,
		);

		/**
		 * Full address information is available to the 'GetOrderReferenceDetails' call when we're in
		 * "login app" mode and we pass the AddressConsentToken to the API.
		 *
		 * @see the "Getting the Shipping Address" section here: https://payments.amazon.com/documentation/lpwa/201749990
		 */
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();
		if ( 'yes' === $settings['enable_login_app'] ) {
			$request_args['AddressConsentToken'] = $this->access_token;
		}

		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $request_args );

		// @codingStandardsIgnoreStart
		if ( ! is_wp_error( $response ) && isset( $response->GetOrderReferenceDetailsResult->OrderReferenceDetails ) ) {
			return $response->GetOrderReferenceDetailsResult->OrderReferenceDetails;
		}
		// @codingStandardsIgnoreEnd

		return false;

	}

	/**
	 * Format an Amazon Pay Address DataType for WooCommerce.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752430
	 *
	 * @deprecated
	 *
	 * @param array $address Address object from Amazon Pay API.
	 *
	 * @return array Address formatted for WooCommerce.
	 */
	public function format_address( $address ) {
		_deprecated_function( 'WC_Gateway_Amazon_Payments_Advanced::format_address', '1.6.0', 'WC_Amazon_Payments_Advanced_API::format_address' );

		return WC_Amazon_Payments_Advanced_API::format_address( $address );
	}

	/**
	 * Parse the OrderReferenceDetails object and store billing/shipping addresses
	 * in order meta.
	 *
	 * @version 1.7.1
	 *
	 * @param int|WC_order $order                   Order ID or order instance.
	 * @param object       $order_reference_details Amazon API OrderReferenceDetails.
	 */
	public function store_order_address_details( $order, $order_reference_details ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}

		// @codingStandardsIgnoreStart
		$buyer         = $order_reference_details->Buyer;
		$destination   = $order_reference_details->Destination->PhysicalDestination;
		$shipping_info = WC_Amazon_Payments_Advanced_API::format_address( $destination );
		// @codingStandardsIgnoreEnd

		// Override Shipping State if Destination State doesn't exists in the country's states list.
		$order_current_state = $order->get_shipping_state();
		if ( ! empty( $order_current_state ) && $order_current_state !== $shipping_info['state'] ) {
			$states = WC()->countries->get_states( $shipping_info['country'] );
			if ( empty( $states ) || empty( $states[ $shipping_info['state'] ] ) ) {
				$shipping_info['state'] = $order_current_state;
			}
		}

		$order->set_address( $shipping_info, 'shipping' );

		// Some market API endpoint return billing address information, parse it if present.
		// @codingStandardsIgnoreStart
		if ( isset( $order_reference_details->BillingAddress->PhysicalAddress ) ) {
			$billing_address = WC_Amazon_Payments_Advanced_API::format_address( $order_reference_details->BillingAddress->PhysicalAddress );
		} elseif ( apply_filters( 'woocommerce_amazon_pa_billing_address_fallback_to_shipping_address', true ) ) {
			// Reuse the shipping address information if no bespoke billing info.
			$billing_address = $shipping_info;
		} else {
			$name            = ! empty( $buyer->Name ) ? (string) $buyer->Name : '';
			$billing_address = WC_Amazon_Payments_Advanced_API::format_name( $name );
		}

		$billing_address['email'] = (string) $buyer->Email;
		$billing_address['phone'] = isset( $billing_address['phone'] ) ? $billing_address['phone'] : (string) $buyer->Phone;
		// @codingStandardsIgnoreEnd

		$order->set_address( $billing_address, 'billing' );
	}

	/**
	 * If AuthenticationStatus is hit, we need to finish payment process that has been stopped previously.
	 */
	public function handle_sca_url_processing() {
		if ( ! isset( $_GET['AuthenticationStatus'] ) ) {
			return;
		}

		$order_id = isset( WC()->session->order_awaiting_payment ) ? WC()->session->order_awaiting_payment : null;
		if ( ! $order_id ) {
			return;
		}

		$order               = wc_get_order( $order_id );
		$amazon_reference_id = $order->get_meta( 'amazon_reference_id', true );
		WC()->session->set( 'amazon_reference_id', $amazon_reference_id );

		wc_apa()->log( sprintf( 'Info: Continuing payment processing for order %s. Reference ID %s', $order_id, $amazon_reference_id ) );

		$authorization_status = wc_clean( $_GET['AuthenticationStatus'] );
		switch ( $authorization_status ) {
			case 'Success':
				$this->handle_sca_success( $order, $amazon_reference_id );
				break;
			case 'Failure':
			case 'Abandoned':
				$this->handle_sca_failure( $order, $amazon_reference_id, $authorization_status );
				break;
			default:
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
		}
	}

	/**
	 * If redirected to success url, proceed with payment and redirect to thank you page.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Reference ID.
	 */
	protected function handle_sca_success( $order, $amazon_reference_id ) {
		$process = apply_filters( 'woocommerce_amazon_pa_handle_sca_success', null, $order, $amazon_reference_id );
		if ( ! is_null( $process ) ) {
			return $process;
		}

		$redirect = $this->get_return_url( $order );

		try {
			// It will process payment and empty the cart.
			$this->process_payment_capture( $order, $amazon_reference_id );
		} catch ( Exception $e ) {
			// Async (optimal) mode on settings.
			if ( 'async' === $this->authorization_mode && isset( $e->transaction_timed_out ) ) {
				$this->process_async_auth( $order, $amazon_reference_id );
			} else {
				wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(), 'error' );
				$redirect = wc_get_checkout_url();
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * If redirected to failure url, add a notice with right information for the user.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $amazon_reference_id Reference ID.
	 * @param string   $authorization_status Authorization Status.
	 */
	protected function handle_sca_failure( $order, $amazon_reference_id, $authorization_status ) {
		$process = apply_filters( 'woocommerce_amazon_pa_handle_sca_failure', null, $order, $amazon_reference_id, $authorization_status );
		if ( ! is_null( $process ) ) {
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
		}

		if ( 'Abandoned' === $authorization_status ) {
			wc_add_notice( __( 'Authentication for the transaction was not completed, please try again selecting another payment instrument from your Amazon wallet.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			wc_apa()->log( 'MFA (Multi-Factor Authentication) Challenge Fail, Status "Abandoned", reference ' . $amazon_reference_id );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Write a message to log if we're in "debug" mode.
	 *
	 * @deprecated
	 *
	 * @param string $context Context.
	 * @param string $message Message to log.
	 */
	public function log( $context, $message ) {
		_deprecated_function( 'WC_Gateway_Amazon_Payments_Advanced::log', '1.6.0', 'wc_apa()->log()' );

		wc_apa()->log( $message, null, $context );
	}

	/**
	 * Maybe render timeout transaction order received text.
	 *
	 * @param string   $text Text to be displayed.
	 * @param WC_Order $order Order object.
	 *
	 * @return string
	 */
	public function maybe_render_timeout_transaction_order_received_text( $text, $order ) {
		if ( $order && $order->has_status( 'on-hold' ) && $order->get_meta( 'amazon_timed_out_transaction', true, 'edit' ) ) {
			$text = __( 'Your transaction with Amazon Pay is currently being validated. Please be aware that we will inform you shortly as needed.', 'woocommerce-gateway-amazon-payments-advanced' );
		}
		return $text;
	}

	/**
	 * Load handlers for cart and orders after WC Cart is loaded.
	 */
	public function init_handlers() {
		// Disable if no seller ID.
		if ( ! apply_filters( 'woocommerce_amazon_payments_init', true ) || empty( $this->settings['seller_id'] ) || 'no' === $this->settings['enabled'] ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		// Login app actions.
		if ( 'yes' === $this->settings['enable_login_app'] ) {
			// Login app widget.
			add_action( 'wp_head', array( $this, 'init_amazon_login_app_widget' ) );
		}

		if ( 'button' === $this->settings['cart_button_display_mode'] ) {
			add_action( 'woocommerce_proceed_to_checkout', array( $this, 'checkout_button' ), 25 );
		} elseif ( 'banner' === $this->settings['cart_button_display_mode'] ) {
			add_action( 'woocommerce_before_cart', array( $this, 'checkout_message' ), 5 );
		}

		// Checkout.
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'update_amazon_widgets_fragment' ) );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'force_standard_mode_refresh_with_zero_order_total' ) );

		// When transaction is declined with reason code 'InvalidPaymentMethod',
		// the customer will be promopted with read-only address widget and need
		// to fix the chosen payment method. Coupon form should be disabled in
		// this state.
		//
		// @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/244.
		// @see https://pay.amazon.com/de/developer/documentation/lpwa/201953810#sync-invalidpaymentmethod.
		if ( WC()->session && 'InvalidPaymentMethod' === WC()->session->amazon_declined_code ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' );
		}
	}

	/**
	 * Maybe the request to logout from Amazon.
	 *
	 * @since 1.6.0
	 */
	public function maybe_attempt_to_logout() {
		if ( ! empty( $_GET['amazon_payments_advanced'] ) && ! empty( $_GET['amazon_logout'] ) ) {
			$this->logout_from_amazon();
		}
	}

	/**
	 * Logout from Amazon by removing Amazon related session and logout too
	 * from app widget.
	 *
	 * @since 1.6.0
	 */
	public function logout_from_amazon() {
		unset( WC()->session->amazon_reference_id );
		unset( WC()->session->amazon_access_token );

		$this->reference_id = '';
		$this->access_token = '';

		if ( is_order_received_page() && 'yes' === $this->settings['enable_login_app'] ) {
			?>
			<script>
			( function( $ ) {
				$( document ).on( 'wc_amazon_pa_login_ready', function() {
					amazon.Login.logout();
				} );
			} )(jQuery)
			</script>
			<?php
		}
		do_action( 'woocommerce_amazon_pa_logout' );
	}

	/**
	 * Initialize Amazon Pay UI during checkout.
	 *
	 * @param WC_Checkout $checkout Checkout object.
	 */
	public function checkout_init( $checkout ) {

		/**
		 * Make sure this is checkout initiated from front-end where cart exsits.
		 *
		 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/238
		 */
		if ( ! WC()->cart ) {
			return;
		}

		// Are we using the login app?
		$enable_login_app = ( 'yes' === $this->settings['enable_login_app'] );

		// Disable Amazon Pay for zero-total checkouts using the standard button.
		if ( ! WC()->cart->needs_payment() && ! $enable_login_app ) {

			// Render a placeholder widget container instead, in the event we
			// need to populate it later.
			add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'placeholder_widget_container' ) );

			// Render a placeholder checkout message container, in the event we
			// need to populate it later.
			add_action( 'woocommerce_before_checkout_form', array( $this, 'placeholder_checkout_message_container' ), 5 );

			return;

		}

		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_message' ), 5 );
		add_action( 'before_woocommerce_pay', array( $this, 'checkout_message' ), 5 );

		// Don't try to render the Amazon widgets if we don't have the prerequisites
		// for each mode.
		if ( ( ! $enable_login_app && empty( $this->reference_id ) ) || ( $enable_login_app && empty( $this->access_token ) ) ) {
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_amazon_gateway' ) );
			return;
		}

		// If all prerequisites are meet to be an amazon checkout.
		do_action( 'woocommerce_amazon_checkout_init', $enable_login_app );

		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'payment_widget' ), 20 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'address_widget' ), 10 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'capture_shipping_address_for_zero_order_total' ) );
		// The default checkout form uses the billing email for new account creation
		// Let's hijack that field for the Amazon-based checkout.
		if ( apply_filters( 'woocommerce_pa_hijack_checkout_fields', true ) ) {
			$this->hijack_checkout_fields( $checkout );
		}
	}

	/**
	 * Output an empty placeholder widgets container
	 */
	public function placeholder_widget_container() {
		?>
		<div id="amazon_customer_details"></div>
		<?php
	}

	/**
	 * Output an empty placeholder checkout message container
	 */
	public function placeholder_checkout_message_container() {
		?>
		<div class="wc-amazon-checkout-message"></div>
		<?php
	}

	/**
	 * Checkout Message
	 */
	public function checkout_message() {
		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
		$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' === $this->settings['subscriptions_enabled'];
		$cart_contains_sub       = class_exists( 'WC_Subscriptions_Cart' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false;

		if ( $subscriptions_installed && ! $subscriptions_enabled && $cart_contains_sub ) {
			return;
		}

		echo '<div class="wc-amazon-checkout-message wc-amazon-payments-advanced-populated">';

		if ( empty( $this->reference_id ) && empty( $this->access_token ) ) {
			echo '<div class="woocommerce-info info wc-amazon-payments-advanced-info"><div id="pay_with_amazon"></div> ' . apply_filters( 'woocommerce_amazon_pa_checkout_message', __( 'Have an Amazon account?', 'woocommerce-gateway-amazon-payments-advanced' ) ) . '</div>';
		} else {
			$logout_url      = $this->get_amazon_logout_url();
			$logout_msg_html = '<div class="woocommerce-info info">' . apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ) . ' <a href="' . esc_url( $logout_url ) . '" id="amazon-logout">' . __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a></div>';
			echo apply_filters( 'woocommerce_amazon_payments_logout_checkout_message_html', $logout_msg_html );
		}

		echo '</div>';

	}

	/**
	 * Output the address widget HTML
	 */
	public function address_widget() {
		// Skip showing address widget for carts with virtual products only.
		$show_address_widget = apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() );
		$hide_css_style      = ( ! $show_address_widget ) ? 'display: none;' : '';
		?>
		<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated">
			<div class="col2-set">
				<div class="col-1" style="<?php echo esc_attr( $hide_css_style ); ?>">
					<?php if ( 'skip' !== WC()->session->get( 'amazon_billing_agreement_details' ) ) : ?>
						<h3><?php esc_html_e( 'Shipping Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
						<div id="amazon_addressbook_widget"></div>
					<?php endif ?>
					<?php if ( ! empty( $this->reference_id ) ) : ?>
						<input type="hidden" name="amazon_reference_id" value="<?php echo esc_attr( $this->reference_id ); ?>" />
					<?php endif; ?>
					<?php if ( ! empty( $this->access_token ) ) : ?>
						<input type="hidden" name="amazon_access_token" value="<?php echo esc_attr( $this->access_token ); ?>" />
					<?php endif; ?>
				</div>
		<?php
	}

	/**
	 * Output the payment method widget HTML
	 */
	public function payment_widget() {
		$checkout = WC_Checkout::instance();
		?>
				<div class="col-2">
					<h3><?php esc_html_e( 'Payment Method', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
					<div id="amazon_wallet_widget"></div>
					<?php if ( ! empty( $this->reference_id ) ) : ?>
						<input type="hidden" name="amazon_reference_id" value="<?php echo esc_attr( $this->reference_id ); ?>" />
					<?php endif; ?>
					<?php if ( ! empty( $this->access_token ) ) : ?>
						<input type="hidden" name="amazon_access_token" value="<?php echo esc_attr( $this->access_token ); ?>" />
					<?php endif; ?>
					<?php if ( 'skip' === WC()->session->get( 'amazon_billing_agreement_details' ) ) : ?>
						<input type="hidden" name="amazon_billing_agreement_details" value="skip" />
					<?php endif; ?>
				</div>
				<?php if ( 'skip' !== WC()->session->get( 'amazon_billing_agreement_details' ) ) : ?>
					<div id="amazon_consent_widget" style="display: none;"></div>
				<?php endif; ?>

		<?php if ( ! is_user_logged_in() && $checkout->enable_signup ) : ?>

			<?php if ( $checkout->enable_guest_checkout ) : ?>

				<p class="form-row form-row-wide create-account">
					<input class="input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" /> <label for="createaccount" class="checkbox"><?php esc_html_e( 'Create an account?', 'woocommerce-gateway-amazon-payments-advanced' ); ?></label>
				</p>

			<?php endif; ?>

			<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

			<?php if ( ! empty( $checkout->checkout_fields['account'] ) ) : ?>

				<div class="create-account">

					<h3><?php esc_html_e( 'Create Account', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
					<p><?php esc_html_e( 'Create an account by entering the information below. If you are a returning customer please login at the top of the page.', 'woocommerce-gateway-amazon-payments-advanced' ); ?></p>

					<?php foreach ( $checkout->checkout_fields['account'] as $key => $field ) : ?>

						<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

					<?php endforeach; ?>

					<div class="clear"></div>

				</div>

			<?php endif; ?>

			<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>

		<?php endif; ?>
			</div>
		</div>

		<?php
	}

	/**
	 * Capture full shipping address in the case of a $0 order, using the "login app"
	 * version of the API.
	 *
	 * @version 1.7.1
	 *
	 * @param int $order_id Order ID.
	 */
	public function capture_shipping_address_for_zero_order_total( $order_id ) {
		$order = wc_get_order( $order_id );

		/**
		 * Complete address data is only available without a confirmed order if
		 * we're using the login app.
		 *
		 * @see Getting the Shipping Address section at https://payments.amazon.com/documentation/lpwa/201749990
		 */
		if ( ( $order->get_total() > 0 ) || empty( $this->reference_id ) || ( 'yes' !== $this->settings['enable_login_app'] ) || empty( $this->access_token ) ) {
			return;
		}

		// Get FULL address details and save them to the order.
		$order_details = wc_apa()->get_gateway()->get_amazon_order_details( $this->reference_id );

		if ( $order_details ) {
			wc_apa()->get_gateway()->store_order_address_details( $order, $order_details );
		}
	}

	/**
	 * Hijack checkout fields during checkout.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Checkout $checkout WC_Checkout instance.
	 */
	public function hijack_checkout_fields( $checkout ) {
		$has_billing_fields = (
			isset( $checkout->checkout_fields['billing'] )
			&&
			is_array( $checkout->checkout_fields['billing'] )
		);

		if ( $has_billing_fields && 'yes' !== $this->settings['enable_login_app'] ) {
			$this->hijack_checkout_field_account( $checkout );
		}

		// During an Amazon checkout, the standard billing and shipping fields need to be
		// "removed" so that we don't trigger a false negative on form validation -
		// they can be empty since we're using the Amazon widgets.
		$this->hijack_checkout_field_billing( $checkout );
		$this->hijack_checkout_field_shipping( $checkout );
	}

	/**
	 * Alter account checkout field.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Checkout $checkout WC_Checkout instance.
	 */
	protected function hijack_checkout_field_account( $checkout ) {
		$billing_fields_to_copy = array(
			'billing_first_name' => '',
			'billing_last_name'  => '',
			'billing_email'      => '',
		);

		$billing_fields_to_merge = array_intersect_key( $checkout->checkout_fields['billing'], $billing_fields_to_copy );

		/**
		 * WC 3.0 changes a bit a way to retrieve fields.
		 *
		 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/217
		 */
		$checkout_fields = version_compare( WC_VERSION, '3.0', '>=' )
			? $checkout->get_checkout_fields()
			: $checkout->checkout_fields;

		/**
		 * Before 3.0.0, account fields may not set at all if guest checkout is
		 * disabled with account and password generated automatically.
		 *
		 * @see https://github.com/woocommerce/woocommerce/blob/2.6.14/includes/class-wc-checkout.php#L92-L132
		 * @see https://github.com/woocommerce/woocommerce/blob/master/includes/class-wc-checkout.php#L187-L197
		 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/228
		 */
		$checkout_fields['account'] = ! empty( $checkout_fields['account'] )
			? $checkout_fields['account']
			: array();

		$checkout_fields['account'] = array_merge( $billing_fields_to_merge, $checkout_fields['account'] );

		if ( isset( $checkout_fields['account']['billing_email']['class'] ) ) {
			$checkout_fields['account']['billing_email']['class'] = array();
		}

		$checkout->checkout_fields = $checkout_fields;
	}

	/**
	 * Hijack billing checkout field.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Checkout $checkout WC_Checkout instance.
	 */
	protected function hijack_checkout_field_billing( $checkout ) {
		// The following fields cannot be optional for WC compatibility reasons.
		$required_fields = array( 'billing_first_name', 'billing_last_name', 'billing_email' );
		// If the order does not require shipping, these fields can be optional.
		$optional_fields = array(
			'billing_company',
			'billing_country',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_phone',
		);
		$all_fields      = array_merge( $required_fields, $optional_fields );
		$checkout_fields = version_compare( WC_VERSION, '3.0', '>=' )
			? $checkout->get_checkout_fields()
			: $checkout->checkout_fields;

		if ( 'yes' === $this->settings['enable_login_app'] ) {
			// Some merchants might not have access to billing address information, so we need to make those fields optional
			// when the order doesn't require shipping.
			if ( ! apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() ) ) {
				foreach ( $optional_fields as $field ) {
					$checkout_fields['billing'][ $field ]['required'] = false;
				}
			}
			$this->add_hidden_class_to_fields( $checkout_fields['billing'], $all_fields );
		} else {
			// Cannot grab user details when not using login app. Need to unset all fields.
			$this->unset_fields_from_checkout( $checkout_fields['billing'], $all_fields );
		}

		$checkout->checkout_fields = $checkout_fields;
	}

	/**
	 * Hijack shipping checkout field.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Checkout $checkout WC_Checkout instance.
	 */
	protected function hijack_checkout_field_shipping( $checkout ) {
		$field_list      = array(
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_country',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
		);
		$checkout_fields = version_compare( WC_VERSION, '3.0', '>=' )
			? $checkout->get_checkout_fields()
			: $checkout->checkout_fields;

		if ( 'yes' === $this->settings['enable_login_app'] && apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() ) ) {
			$this->add_hidden_class_to_fields( $checkout_fields['shipping'], $field_list );
		} else {
			$this->unset_fields_from_checkout( $checkout_fields['shipping'], $field_list );
		}

		$checkout->checkout_fields = $checkout_fields;
	}

	/**
	 * Adds hidden class to checkout field
	 *
	 * @param array $field reference to the field to be hidden.
	 */
	protected function add_hidden_class_to_field( &$field ) {
		if ( isset( $field['class'] ) ) {
			array_push( $field['class'], 'hidden' );
		} else {
			$field['class'] = array( 'hidden' );
		}
	}

	/**
	 * Adds hidden class to checkout fields based on a list
	 *
	 * @param array $checkout_fields reference to checkout fields.
	 * @param array $field_list fields to be hidden.
	 */
	protected function add_hidden_class_to_fields( &$checkout_fields, $field_list ) {
		foreach ( $field_list as $field ) {
			$this->add_hidden_class_to_field( $checkout_fields[ $field ] );
		}
	}

	/**
	 * Removes fields from checkout based on a list
	 *
	 * @param array $checkout_fields reference to checkout fields.
	 * @param array $field_list fields to be removed.
	 */
	protected function unset_fields_from_checkout( &$checkout_fields, $field_list ) {
		foreach ( $field_list as $field ) {
			unset( $checkout_fields[ $field ] );
		}
	}

	/**
	 * Render the Amazon Pay widgets when an order is updated to require
	 * payment, and the Amazon gateway is available.
	 *
	 * @param array $fragments Fragments.
	 *
	 * @return array
	 */
	public function update_amazon_widgets_fragment( $fragments ) {

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( WC()->cart->needs_payment() ) {

			ob_start();

			$this->checkout_message();

			$fragments['.wc-amazon-checkout-message:not(.wc-amazon-payments-advanced-populated)'] = ob_get_clean();

			if ( array_key_exists( 'amazon_payments_advanced', $available_gateways ) ) {

				ob_start();

				$this->address_widget();

				$this->payment_widget();

				$fragments['#amazon_customer_details:not(.wc-amazon-payments-advanced-populated)'] = ob_get_clean();
			}
		}

		return $fragments;

	}

	/**
	 * Force a page refresh when an order is updated to have a zero total and
	 * we're not using the "login app" mode.
	 *
	 * This ensures that the standard WC checkout form is rendered.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function force_standard_mode_refresh_with_zero_order_total( $cart ) {
		// Avoid constant reload loop in the event we've forced a checkout refresh.
		if ( ! is_ajax() ) {
			unset( WC()->session->reload_checkout );
		}

		// Login app mode can handle zero-total orders.
		if ( 'yes' === $this->settings['enable_login_app'] ) {
			return;
		}

		if ( ! wc_apa()->get_gateway()->is_available() ) {
			return;
		}

		// Get the previous cart total.
		$previous_total = WC()->session->wc_amazon_previous_total;

		// Store the current total.
		WC()->session->wc_amazon_previous_total = $cart->total;

		// If the total is non-zero, and we don't know what the previous total was, bail.
		if ( is_null( $previous_total ) || $cart->needs_payment() ) {
			return;
		}

		// This *wasn't* as zero-total order, but is now.
		if ( $previous_total > 0 ) {
			// Force reload, re-rendering standard WC checkout form.
			WC()->session->reload_checkout = true;
		}
	}

	/**
	 * Maybe hide standard WC checkout button on the cart, if enabled
	 */
	public function maybe_hide_standard_checkout_button() {
		if ( 'yes' === $this->settings['enabled'] && 'yes' === $this->settings['hide_standard_checkout_button'] ) {
			?>
				<style type="text/css">
					.woocommerce a.checkout-button,
					.woocommerce input.checkout-button,
					.cart input.checkout-button,
					.cart a.checkout-button,
					.widget_shopping_cart a.checkout {
						display: none !important;
					}
				</style>
			<?php
		}
	}

	/**
	 * Maybe hides Amazon Pay buttons on cart or checkout pages if hide button mode
	 * is enabled.
	 *
	 * @since 1.6.0
	 */
	public function maybe_hide_amazon_buttons() {
		$hide_button_mode_enabled = ( 'yes' === $this->settings['enabled'] && 'yes' === $this->settings['hide_button_mode'] );
		$hide_button_mode_enabled = apply_filters( 'woocommerce_amazon_payments_hide_amazon_buttons', $hide_button_mode_enabled );

		if ( ! $hide_button_mode_enabled ) {
			return;
		}

		?>
		<style type="text/css">
			.wc-amazon-payments-advanced-info, #pay_with_amazon {
				display: none;
			}
		</style>
		<?php
	}

	/**
	 * When SCA, we hijack "Place Order Button" and perform our own custom Checkout.
	 */
	public function ajax_sca_processing() {
		check_ajax_referer( 'sca_nonce', 'nonce' );
		// Get $_POST and $_REQUEST compatible with process_checkout.
		parse_str( $_POST['data'], $_POST );
		$_REQUEST = $_POST;

		WC()->checkout()->process_checkout();
		wp_send_json_success();
	}

	/**
	 * Init Amazon login app widget.
	 */
	public function init_amazon_login_app_widget() {
		$redirect_page = is_cart() ? $this->get_amazon_payments_checkout_url() : $this->get_amazon_payments_clean_logout_url();
		?>
		<script type='text/javascript'>
			function getURLParameter(name, source) {
			return decodeURIComponent((new RegExp('[?|&|#]' + name + '=' +
				'([^&]+?)(&|#|;|$)').exec(source) || [,""])[1].replace(/\+/g,
				'%20')) || null;
			}

			var accessToken = getURLParameter("access_token", location.hash);

			if (typeof accessToken === 'string' && accessToken.match(/^Atza/)) {
				document.cookie = "amazon_Login_accessToken=" + encodeURIComponent(accessToken) +
				";secure";
				window.location = '<?php echo esc_js( esc_url_raw( $redirect_page ) ); ?>';
			}
		</script>
		<script>
			window.onAmazonLoginReady = function() {
				amazon.Login.setClientId( "<?php echo esc_js( $this->settings['app_client_id'] ); ?>" );
				jQuery( document ).trigger( 'wc_amazon_pa_login_ready' );
			};
		</script>
		<?php
	}

	/**
	 * Filter Ajax endpoint so it carries the query string after buyer is
	 * redirected from Amazon.
	 *
	 * Commit 75cc4f91b534ce3114d19da80586bacd083bb5a8 from WC 3.2 replaces the
	 * REQUEST_URI with `/` so that query string from current URL is not carried.
	 * This plugin hooked into checkout related actions/filters that might be
	 * called from Ajax request and expecting some parameters from query string.
	 *
	 * @since 1.8.0
	 *
	 * @param string $url     Ajax URL.
	 * @param string $request Request type. Expecting only 'checkout'.
	 *
	 * @return string URL.
	 */
	public function filter_ajax_endpoint( $url, $request ) {
		if ( 'checkout' !== $request ) {
			return $url;
		}

		if ( ! empty( $_GET['amazon_payments_advanced'] ) ) {
			$url = add_query_arg( 'amazon_payments_advanced', $_GET['amazon_payments_advanced'], $url );
		}
		if ( ! empty( $_GET['access_token'] ) ) {
			$url = add_query_arg( 'access_token', $_GET['access_token'], $url );
		}

		return $url;
	}

	/**
	 * Ajax handler for fetching order reference
	 */
	public function ajax_get_order_reference() {
		check_ajax_referer( 'order_reference_nonce', 'nonce' );

		if ( empty( $_POST['order_reference_id'] ) ) {
			wp_send_json(
				new WP_Error( 'amazon_missing_order_reference_id', __( 'Missing order reference ID.', 'woocommerce-gateway-amazon-payments-advanced' ) ),
				400
			);
		}

		if ( empty( $this->access_token ) ) {
			wp_send_json(
				new WP_Error( 'amazon_missing_access_token', __( 'Missing access token. Make sure you are logged in.', 'woocommerce-gateway-amazon-payments-advanced' ) ),
				400
			);
		}

		$order_reference_id = sanitize_text_field( wp_unslash( $_POST['order_reference_id'] ) );

		$response = wc_apa()->get_gateway()->get_amazon_order_details( $order_reference_id );

		if ( $response ) {
			wp_send_json( $response );
		} else {
			wp_send_json(
				new WP_Error( 'amazon_get_order_reference_failed', __( 'Failed to get order reference data. Make sure you are logged in and trying to access a valid order reference owned by you.', 'woocommerce-gateway-amazon-payments-advanced' ) ),
				400
			);
		}
	}

	/**
	 * Maybe display declined notice.
	 *
	 * @since 1.7.1
	 * @version 1.7.1
	 */
	public function maybe_display_declined_notice() {
		if ( ! empty( $_GET['amazon_declined'] ) ) {
			wc_add_notice( __( 'There was a problem with previously declined transaction. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
		}
	}

	/**
	 * Add scripts
	 */
	public function scripts() {

		$enqueue_scripts = is_cart() || is_checkout() || is_checkout_pay_page();

		if ( ! apply_filters( 'woocommerce_amazon_pa_enqueue_scripts', $enqueue_scripts ) ) {
			return;
		}

		$js_suffix = '.min.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$js_suffix = '.js';
		}

		$type = ( 'yes' === $this->settings['enable_login_app'] ) ? 'app' : 'standard';

		wp_enqueue_style( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/css/style.css', array(), wc_apa()->version );
		wp_enqueue_script( 'amazon_payments_advanced_widgets', WC_Amazon_Payments_Advanced_API_Legacy::get_widgets_url(), array(), wc_apa()->version, true );
		wp_enqueue_script( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/js/legacy/amazon-' . $type . '-widgets' . $js_suffix, array(), wc_apa()->version, true );

		$redirect_page = is_cart() ? $this->get_amazon_payments_checkout_url() : $this->get_amazon_payments_clean_logout_url();

		$params = array(
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'seller_id'             => $this->settings['seller_id'],
			'reference_id'          => $this->reference_id,
			'redirect'              => esc_url_raw( $redirect_page ),
			'is_checkout_pay_page'  => is_checkout_pay_page(),
			'is_checkout'           => is_checkout(),
			'access_token'          => $this->access_token,
			'logout_url'            => esc_url_raw( $this->get_amazon_logout_url() ),
			'render_address_widget' => apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() ),
			'order_reference_nonce' => wp_create_nonce( 'order_reference_nonce' ),
		);

		if ( 'yes' === $this->settings['enable_login_app'] ) {

			$params['button_type']     = $this->settings['button_type'];
			$params['button_color']    = $this->settings['button_color'];
			$params['button_size']     = $this->settings['button_size'];
			$params['button_language'] = $this->settings['button_language'];
			$params['checkout_url']    = esc_url_raw( get_permalink( wc_get_page_id( 'checkout' ) ) );

		}

		if ( WC()->session->amazon_declined_code ) {
			$params['declined_code'] = WC()->session->amazon_declined_code;
			unset( WC()->session->amazon_declined_code );
		}

		if ( WC()->session->amazon_declined_with_cancel_order ) {
			$order                           = wc_get_order( WC()->session->amazon_declined_order_id );
			$params['declined_redirect_url'] = add_query_arg(
				array(
					'amazon_payments_advanced' => 'true',
					'amazon_logout'            => 'true',
					'amazon_declined'          => 'true',
				),
				$order->get_cancel_order_url()
			);

			unset( WC()->session->amazon_declined_order_id );
			unset( WC()->session->amazon_declined_with_cancel_order );
		}

		if ( class_exists( 'WC_Subscriptions_Cart' ) ) {

			$cart_contains_subscription      = WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal();
			$change_payment_for_subscription = isset( $_GET['change_payment_method'] ) && wcs_is_subscription( absint( $_GET['change_payment_method'] ) );
			$params['is_recurring']          = $cart_contains_subscription || $change_payment_for_subscription;

			// No need to make billing agreement if automatic payments is turned off.
			if ( 'yes' === get_option( 'woocommerce_subscriptions_turn_off_automatic_payments' ) ) {
				unset( $params['is_recurring'] );
			}
		}

		// SCA support. If Merchant is European Region and Order does not contain or is a subscriptions.
		$params['is_sca'] = ( WC_Amazon_Payments_Advanced_API_Legacy::is_sca_region() );
		if ( $params['is_sca'] ) {
			$params['sca_nonce'] = wp_create_nonce( 'sca_nonce' );
		}

		// Multi-currency support.
		$multi_currency                         = WC_Amazon_Payments_Advanced_Multi_Currency::is_active();
		$params['multi_currency_supported']     = $multi_currency;
		$params['multi_currency_nonce']         = wp_create_nonce( 'multi_currency_nonce' );
		$params['multi_currency_reload_wallet'] = ( $multi_currency ) ? WC_Amazon_Payments_Advanced_Multi_Currency::reload_wallet_widget() : false;
		$params['current_currency']             = ( $multi_currency ) ? WC_Amazon_Payments_Advanced_Multi_Currency::get_selected_currency() : '';
		$params['shipping_title']               = esc_html__( 'Shipping details', 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$params['redirect_authentication']      = $this->settings['redirect_authentication'];

		$params = array_map( 'esc_js', apply_filters( 'woocommerce_amazon_pa_widgets_params', $params ) );

		wp_localize_script( 'amazon_payments_advanced', 'amazon_payments_advanced_params', $params );

		do_action( 'wc_amazon_pa_scripts_enqueued', $type, $params );
	}

	/**
	 * Init legacy hooks
	 */
	public static function legacy_hooks() {
		add_filter( 'woocommerce_amazon_pa_process_refund', array( __CLASS__, 'maybe_handle_v1_refund' ), 10, 4 );
	}

	/**
	 * Handle v1 refunds
	 *
	 * @param  mixed  $ret Shortcircuit parameter.
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Amount to refund.
	 * @param  string $reason Reason for the refund.
	 *
	 * @return bool|WP_Error
	 */
	public static function maybe_handle_v1_refund( $ret, $order_id, $amount, $reason ) {
		$version = WC_Amazon_Payments_Advanced::get_order_version( $order_id );
		if ( 'v1' !== strtolower( $version ) ) {
			return $ret;
		}

		return self::do_process_refund( $order_id, $amount, $reason );
	}
}
