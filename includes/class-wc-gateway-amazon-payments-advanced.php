<?php
/**
 * Gateway class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Implement payment method for Amazon Pay.
 */
class WC_Gateway_Amazon_Payments_Advanced extends WC_Gateway_Amazon_Payments_Advanced_Abstract {

	protected $checkout_session;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Init Handlers
		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 11 );
	}

	/**
	 * Amazon Pay is available if the following conditions are met (on top of
	 * WC_Payment_Gateway::is_available).
	 *
	 * 1) Gateway enabled
	 * 2) Correctly setup
	 * 2) In checkout pay page.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = parent::is_available() && ! empty( $this->settings['merchant_id'] );

		return apply_filters( 'woocommerce_amazon_pa_is_gateway_available', $is_available );
	}

	/**
	 * Load handlers for cart and orders after WC Cart is loaded.
	 */
	public function init_handlers() {
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( ! isset( $available_gateways[ $this->id ] ) ) {
			return;
		}

		if ( ! apply_filters( 'woocommerce_amazon_payments_init', true ) ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'maybe_handle_apa_action' ) );

		// Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		// Checkout.
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'use_checkout_session_data' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'use_checkout_session_data_single' ), 10, 2 );

		// Cart
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_amazon_pay_button_separator_html' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'checkout_button' ), 25 );
		add_action( 'woocommerce_before_cart_totals', array( $this, 'update_js' ) );
	}

	/**
	 * Add scripts
	 */
	public function scripts() {

		$js_suffix = '.min.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$js_suffix = '.js';
		}

		wp_register_style( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/css/style.css', array(), wc_apa()->version );
		wp_register_script( 'amazon_payments_advanced_checkout', $this->get_region_script(), array(), wc_apa()->version, true );
		wp_register_script( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/js/amazon-wc-checkout' . $js_suffix, array(), wc_apa()->version, true );

		$params = array(
			'ajax_url'                       => admin_url( 'admin-ajax.php' ),
			'create_checkout_session_config' => WC_Amazon_Payments_Advanced_API::get_create_checkout_session_config(),
			'button_color'                   => $this->settings['button_color'],
			'placement'                      => $this->get_current_placement(),
			'action'                         => $this->get_current_cart_action(),
			'sandbox'                        => 'yes' === $this->settings['sandbox'],
			'merchant_id'                    => $this->settings['merchant_id'],
			'shipping_title'                 => esc_html__( 'Shipping details', 'woocommerce' ),
			'checkout_session_id'            => $this->get_checkout_session_id(),
			'button_language'                => $this->settings['button_language'],
		);

		wp_localize_script( 'amazon_payments_advanced', 'amazon_payments_advanced', $params );

		$enqueue_scripts = is_cart() || is_checkout() || is_checkout_pay_page();

		if ( ! apply_filters( 'woocommerce_amazon_pa_enqueue_scripts', $enqueue_scripts ) ) {
			return;
		}

		$this->enqueue_scripts();

	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'amazon_payments_advanced' );
		wp_enqueue_script( 'amazon_payments_advanced_checkout' );
		wp_enqueue_script( 'amazon_payments_advanced' );
	}

	protected function get_current_placement() {
		if ( is_cart() ) {
			return 'Cart';
		}

		if ( is_checkout() || is_checkout_pay_page() ) {
			return 'Checkout';
		}

		return 'Other';
	}

	protected function get_region_script() {
		$region = WC_Amazon_Payments_Advanced_API::get_region();

		$url = false;
		switch ( $region ) {
			case 'us':
				$url = 'https://static-na.payments-amazon.com/checkout.js';
				break;
			case 'uk':
			case 'eu':
				$url = 'https://static-eu.payments-amazon.com/checkout.js';
				break;
			case 'jp':
				$url = 'https://static-fe.payments-amazon.com/checkout.js';
				break;
		}

		return $url;
	}

	/**
	 * Display payment request button separator.
	 *
	 * @since 2.0.0
	 */
	public function display_amazon_pay_button_separator_html() {
		?>
		<p class="wc-apa-button-separator" style="margin:1.5em 0;text-align:center;display:none;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-gateway-amazon-payments-advanced' ); ?> &mdash;</p>
		<?php
	}

	public function checkout_init( $checkout ) {

		/**
		 * Make sure this is checkout initiated from front-end where cart exsits.
		 *
		 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/238
		 */
		if ( ! WC()->cart ) {
			return;
		}

		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_message' ), 5 );
		add_action( 'before_woocommerce_pay', array( $this, 'checkout_message' ), 5 );

		if ( ! $this->is_logged_in() ) {
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_amazon_gateway' ) );
			return;
		}

		// If all prerequisites are meet to be an amazon checkout.
		do_action( 'woocommerce_amazon_checkout_init' );

		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_amazon_customer_info' ) );

		// The default checkout form uses the billing email for new account creation
		// Let's hijack that field for the Amazon-based checkout.
		if ( apply_filters( 'woocommerce_pa_hijack_checkout_fields', true ) ) {
			$this->hijack_checkout_fields( $checkout );
		}
	}

	/**
	 * Checkout Message
	 */
	public function checkout_message() {
		echo '<div class="wc-amazon-checkout-message wc-amazon-payments-advanced-populated">';

		if ( ! $this->is_logged_in() ) {
			echo '<div class="woocommerce-info info wc-amazon-payments-advanced-info">' . $this->checkout_button( false ) . ' ' . apply_filters( 'woocommerce_amazon_pa_checkout_message', __( 'Have an Amazon account?', 'woocommerce-gateway-amazon-payments-advanced' ) ) . '</div>';
		} else {
			$this->logout_checkout_message();
		}

		echo '</div>';

	}

	public function logout_checkout_message() {
		$logout_url      = $this->get_amazon_logout_url();
		$logout_msg_html = '<div class="woocommerce-info info">' . apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ) . ' <a href="' . esc_url( $logout_url ) . '" id="amazon-logout">' . __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a></div>';
		echo apply_filters( 'woocommerce_amazon_payments_logout_checkout_message_html', $logout_msg_html );
	}

	protected function do_logout() {
		unset( WC()->session->amazon_checkout_session_id );
	}

	public function maybe_handle_apa_action() {

		if ( empty( $_GET['amazon_payments_advanced'] ) ) {
			return;
		}

		if ( is_null( WC()->session ) ) {
			return;
		}

		$redirect_url = get_permalink( wc_get_page_id( 'checkout' ) );

		if ( isset( $_GET['amazon_logout'] ) ) {
			$this->do_logout();
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_GET['amazon_login'] ) && isset( $_GET['amazonCheckoutSessionId'] ) ) {
			WC()->session->set( 'amazon_checkout_session_id', $_GET['amazonCheckoutSessionId'] );
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_GET['amazon_return'] ) && isset( $_GET['amazonCheckoutSessionId'] ) ) {
			if ( $_GET['amazonCheckoutSessionId'] !== $this->get_checkout_session_id() ) {
				wc_add_notice( __( 'There was an error after returning from Amazon. Please try again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$this->handle_return();
			// If we didn't redirect and quit yet, lets force redirect to checkout.
			wp_safe_redirect( $redirect_url );
			exit;
		}

	}

	protected function get_checkout_session_id() {
		return WC()->session->get( 'amazon_checkout_session_id', false );
	}

	protected function get_checkout_session( $force = false ) {
		if ( ! $force && ! is_null( $this->checkout_session ) ) {
			return $this->checkout_session;
		}

		$this->checkout_session = WC_Amazon_Payments_Advanced_API::get_checkout_session_data( $this->get_checkout_session_id() );
		return $this->checkout_session;
	}

	protected function is_logged_in() {
		if ( is_null( WC()->session ) ) {
			return false;
		}

		$session_id = $this->get_checkout_session_id();

		return ! empty( $session_id ) ? true : false;
	}

	public function hijack_checkout_fields( $checkout ) {
		$has_billing_fields = ( isset( $checkout->checkout_fields['billing'] ) && is_array( $checkout->checkout_fields['billing'] ) );
		if ( $has_billing_fields ) {
			$this->hijack_checkout_field_account( $checkout );
		}

		// During an Amazon checkout, the standard billing and shipping fields need to be
		// "removed" so that we don't trigger a false negative on form validation -
		// they can be empty since we're using the Amazon widgets.

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

		$session_wc_format = $this->get_woocommerce_data();

		$missing  = array();
		$present  = array();
		$optional = array();
		foreach ( $all_fields as $key ) {
			if ( ! empty( $checkout_fields['billing'][ $key ]['required'] ) ) {
				if ( ! isset( $session_wc_format[ $key ] ) ) {
					$missing[] = $key;
				} else {
					$present[] = $key;
				}
			} else {
				$optional[] = $key;
			}
		}

		if ( ! empty( $present ) ) {
			$this->add_hidden_class_to_fields( $checkout_fields['billing'], array_merge( $present, $optional ) );
		}

		$field_list = array(
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

		$missing  = array();
		$present  = array();
		$optional = array();
		foreach ( $field_list as $key ) {
			if ( ! empty( $checkout_fields['shipping'][ $key ]['required'] ) ) {
				if ( ! isset( $session_wc_format[ $key ] ) ) {
					$missing[] = $key;
				} else {
					$present[] = $key;
				}
			} else {
				$optional[] = $key;
			}
		}

		if ( ! empty( $present ) ) {
			$this->add_hidden_class_to_fields( $checkout_fields['shipping'], array_merge( $present, $optional ) );
		}

		$checkout->checkout_fields = $checkout_fields;
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

	public function display_amazon_customer_info() {

		$checkout_session = $this->get_checkout_session();

		if ( $checkout_session->productType !== $this->get_current_cart_action() ) { // phpcs:ignore WordPress.NamingConventions
			$this->render_login_button_again();
			return;
		}

		$checkout = WC_Checkout::instance();
		// phpcs:disable WordPress.NamingConventions
		?>
		<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated">
			<div class="col2-set">
				<div class="col-1 <?php echo empty( $checkout_session->shippingAddress ) ? 'hidden' : ''; ?>">
					<?php if ( ! empty( $checkout_session->shippingAddress ) ) : ?>
						<div id="shipping_address_widget">
							<h3>
								<a href="#" class="wc-apa-widget-change" id="shipping_address_widget_change">Change</a>
								<?php esc_html_e( 'Shipping Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
							</h3>
							<div class="shipping_address_display">
								<?php echo WC()->countries->get_formatted_address( WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ) ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div class="col-2">
					<div id="payment_method_widget">
						<?php
						$payments     = $checkout_session->paymentPreferences;
						$change_label = esc_html__( 'Change', 'woocommerce-gateway-amazon-payments-advanced' );
						if ( empty( $payments ) ) {
							$change_label = esc_html__( 'Select', 'woocommerce-gateway-amazon-payments-advanced' );
						}
						?>
						<h3>
							<a href="#" class="wc-apa-widget-change" id="payment_method_widget_change"><?php echo $change_label; ?></a>
							<?php esc_html_e( 'Payment Method', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
						</h3>
						<div class="payment_method_display">
							<span class="wc-apa-amazon-logo"></span><?php esc_html_e( 'Your selected Amazon payment method', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
						</div>
					</div>
					<?php if ( ! empty( $checkout_session->billingAddress ) ) : ?>
						<div id="billing_address_widget">
							<h3>
								<?php esc_html_e( 'Billing Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
							</h3>
							<div class="billing_address_display">
								<?php echo WC()->countries->get_formatted_address( WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->billingAddress ) ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

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
		// phpcs:enable WordPress.NamingConventions
	}

	protected function get_woocommerce_data() {
		// TODO: Store in session for performance, always clear when coming back from AMZ
		$checkout_session_id = $this->get_checkout_session_id();
		if ( empty( $checkout_session_id ) ) {
			return array();
		}

		$checkout_session = $this->get_checkout_session();

		$data = array();

		if ( ! empty( $checkout_session->buyer ) ) {
			// Billing
			$wc_billing_address = array();
			if ( ! empty( $checkout_session->billingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
				$wc_billing_address = WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->billingAddress ); // phpcs:ignore WordPress.NamingConventions
			} else {
				if ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
					$wc_billing_address = WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ); // phpcs:ignore WordPress.NamingConventions
				} else {
					$wc_billing_address = WC_Amazon_Payments_Advanced_API::format_name( $checkout_session->buyer->name );
				}
			}
			if ( ! empty( $checkout_session->buyer->email ) ) {
				$wc_billing_address['email'] = $checkout_session->buyer->email;
			}
			foreach ( $wc_billing_address as $field => $val ) {
				$data[ 'billing_' . $field ] = $val;
			}

			$wc_shipping_address = array();
			if ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
				$wc_shipping_address = WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ); // phpcs:ignore WordPress.NamingConventions
			}

			// Shipping
			foreach ( $wc_shipping_address as $field => $val ) {
				$data[ 'shipping_' . $field ] = $val;
			}
		}

		return $data;
	}

	public function use_checkout_session_data( $data ) {
		if ( $data['payment_method'] !== $this->id ) {
			return $data;
		}

		$formatted_session_data = $this->get_woocommerce_data();

		$data = array_merge( $data, array_intersect_key( $formatted_session_data, $data ) ); // only set data that exists in data

		return $data;
	}

	public function use_checkout_session_data_single( $ret, $input ) {
		if ( ! WC()->cart->needs_payment() ) {
			return $ret;
		}

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		if ( ! isset( $available_gateways[ $this->id ] ) ) {
			return $ret;
		}

		if ( true !== $available_gateways[ $this->id ]->chosen ) {
			return $ret;
		}

		if ( ! $this->is_logged_in() ) {
			return $ret;
		}

		$session = $this->get_woocommerce_data();

		if ( isset( $session[ $input ] ) ) {
			return $session[ $input ];
		}

		return $ret;
	}

	/**
	 * Process payment.
	 *
	 * @version 2.0.0
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ) {
		$process = apply_filters( 'woocommerce_amazon_pa_process_payment', null, $order_id );
		if ( ! is_null( $process ) ) {
			return $process;
		}

		$order = wc_get_order( $order_id );

		$checkout_session_id = $this->get_checkout_session_id();

		$checkout_session = $this->get_checkout_session();

		$payments = $checkout_session->paymentPreferences; // phpcs:ignore WordPress.NamingConventions

		try {
			if ( ! $order ) {
				throw new Exception( __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			if ( empty( $payments ) ) {
				throw new Exception( __( 'An Amazon Pay payment method was not chosen.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			// TODO: Add shipping requirement check

			// TODO: Implement Multicurrency

			$order_total = $order->get_total();
			$currency    = wc_apa_get_order_prop( $order, 'order_currency' );

			wc_apa()->log( __METHOD__, "Info: Beginning processing of payment for order {$order_id} for the amount of {$order_total} {$currency}. Checkout Session ID: {$checkout_session_id}." );

			if ( empty( $checkout_session->paymentDetails ) || empty( $checkout_session->paymentDetails->chargeAmount ) || $checkout_session->paymentDetails->chargeAmount->amount !== $order_total ) { // phpcs:ignore WordPress.NamingConventions
				$order->update_meta_data( 'amazon_payment_advanced_version', WC_AMAZON_PAY_VERSION ); // TODO: ask if WC 2.6 support is still needed (it's a 2017 release)
				$order->update_meta_data( 'woocommerce_version', WC()->version );

				$payment_intent = 'AuthorizeWithCapture';
				switch ( $this->settings['payment_capture'] ) {
					case 'authorize':
						$payment_intent = 'Authorize';
						break;
					case 'manual':
						$payment_intent = 'Confirm';
						break;
				}

				$can_do_async = false;
				if ( 'async' === $this->settings['authorization_mode'] && 'authorize' === $this->settings['payment_capture'] ) {
					$can_do_async = true;
				}

				$response = WC_Amazon_Payments_Advanced_API::update_checkout_session_data(
					$checkout_session_id,
					array(
						'paymentDetails'                => array(
							'paymentIntent' => $payment_intent, // TODO: Check Authorize, and Confirm flows.
							'canHandlePendingAuthorization' => $can_do_async,
							// "softDescriptor" => "Descriptor", // TODO: Implement setting, if empty, don't set this. ONLY FOR AuthorizeWithCapture
							'chargeAmount'  => array(
								'amount'       => $order_total,
								'currencyCode' => $currency,
							),
						),
						'merchantMetadata'              => WC_Amazon_Payments_Advanced_API::get_merchant_metadata( $order_id ),
					)
				);

				if ( is_wp_error( $response ) ) {
					wc_apa()->log( __METHOD__, "Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}.\n" . wp_json_encode( $response, JSON_PRETTY_PRINT ) );
					wc_add_notice( __( 'There was an error while processing your payment. Your payment method was not charged. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
					return;
				}
			} else {
				$response = $checkout_session;
			}

			if ( ! empty( $response->constraints ) ) {
				wc_apa()->log( __METHOD__, "Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}.\n" . wp_json_encode( $response->constraints, JSON_PRETTY_PRINT ) );
				wc_add_notice( __( 'There was an error while processing your payment. Your payment method was not charged. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
				return;
			}

			$order->save();

			// Return thank you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $response->webCheckoutDetails->amazonPayRedirectUrl, // phpcs:ignore WordPress.NamingConventions
			);

		} catch ( Exception $e ) {
			wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(), 'error' );
		}
	}

	public function handle_return() {
		$checkout_session_id = $this->get_checkout_session_id();

		$order_id = isset( WC()->session->order_awaiting_payment ) ? absint( WC()->session->order_awaiting_payment ) : 0;

		if ( empty( $order_id ) ) {
			wc_apa()->log( __METHOD__, "Error: Order could not be found. Checkout Session ID: {$checkout_session_id}." );
			wc_add_notice( __( 'There was an error while processing your payment. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			return;
		}

		$order = wc_get_order( $order_id );

		$order_total = $order->get_total();
		$currency    = wc_apa_get_order_prop( $order, 'order_currency' );

		$response = WC_Amazon_Payments_Advanced_API::complete_checkout_session(
			$checkout_session_id,
			array(
				'chargeAmount' => array(
					'amount'       => $order_total,
					'currencyCode' => $currency,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			if ( 'CheckoutSessionCanceled' === $error_code ) {
				$checkout_session = $this->get_checkout_session( true );

				switch ( $checkout_session->statusDetails->reasonCode ) { // phpcs:ignore WordPress.NamingConventions
					case 'Declined':
						wc_add_notice( __( 'There was a problem with previously declined transaction. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
						break;
					case 'BuyerCanceled':
						wc_add_notice( __( 'The transaction was canceled by you. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
						break;
					default:
						wc_apa()->log(
							__METHOD__,
							"Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}.\n" . wp_json_encode(
								array(
									'error_code'       => $error_code,
									'checkout_session' => $checkout_session,
								),
								JSON_PRETTY_PRINT
							)
						);
						wc_add_notice( __( 'There was an error while processing your payment. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
						break;
				}

				$this->do_logout();
			} else {
				wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $response->get_error_message(), 'error' );
			}
			return;
		}

		if ( 'Completed' !== $response->statusDetails->state ) { // phpcs:ignore WordPress.NamingConventions
			// TODO: Handle error. Ask for posibilities of status not to be completed at this stage.
			wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' <pre>' . wp_json_encode( $response->statusDetails, JSON_PRETTY_PRINT ) . '</pre>', 'error' ); // phpcs:ignore WordPress.NamingConventions
			return;
		}

		$charge_permission_id = $response->chargePermissionId; // phpcs:ignore WordPress.NamingConventions
		$order->update_meta_data( 'amazon_charge_permission_id', $charge_permission_id );
		$order->save();
		$charge_id = $response->chargeId; // phpcs:ignore WordPress.NamingConventions
		if ( ! empty( $charge_id ) ) {
			$order->update_meta_data( 'amazon_charge_id', $charge_id );
			$order->save();
			$charge        = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );
			$charge_status = $this->log_charge_status_change( $order, $charge );
		} else {
			$order->update_status( 'on-hold' );
			wc_maybe_reduce_stock_levels( $order->get_id() );
		}
		$order->save();

		// Remove cart.
		WC()->cart->empty_cart();

		// TODO: Maybe log out with JS. Ask.
		$this->do_logout();

		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	public function log_charge_status_change( WC_Order $order, $charge = null ) {
		$charge_id = $order->get_meta( 'amazon_charge_id' );
		// TODO: Maybe support multple charges to be tracked?
		if ( ! is_null( $charge ) && $charge_id !== $charge->chargeId ) { // phpcs:ignore WordPress.NamingConventions
			$order->delete_meta_data( 'amazon_charge_id' );
			$order->delete_meta_data( 'amazon_charge_status' );
			$order->save();
			$charge_id = $charge->chargeId; // phpcs:ignore WordPress.NamingConventions
		}
		if ( is_null( $charge ) ) {
			$charge = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );
		}
		$order->read_meta_data( true ); // Force read from db to avoid concurrent notifications
		$old_status = $this->get_cached_charge_status( $order, true )->status;
		$charge_status = $charge->statusDetails->state; // phpcs:ignore WordPress.NamingConventions
		if ( $charge_status === $old_status ) {
			switch ( $old_status ) {
				case 'AuthorizationInitiated':
				case 'Authorized':
				case 'CaptureInitiated':
					wc_apa()->ipn_handler->schedule_hook( $order->get_id(), 'CHARGE' );
					break;
			}
			return $old_status;
		}
		$this->refresh_cached_charge_status( $order, $charge );
		$order->update_meta_data( 'amazon_charge_id', $charge_id ); // phpcs:ignore WordPress.NamingConventions
		$order->save(); // Save early for less race conditions

		// @codingStandardsIgnoreStart
		$order->add_order_note( sprintf(
			/* translators: 1) Amazon Charge ID 2) Charge status */
			__( 'Charge %1$s with status %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			(string) $charge->chargeId,
			(string) $charge_status
		) );
		// @codingStandardsIgnoreEnd

		switch ( $charge_status ) {
			case 'AuthorizationInitiated':
			case 'Authorized':
			case 'CaptureInitiated':
				// Mark as on-hold.
				$order->update_status( 'on-hold' );
				wc_maybe_reduce_stock_levels( $order->get_id() );
				wc_apa()->ipn_handler->schedule_hook( $order->get_id(), 'CHARGE' );
				break;
			case 'Canceled':
			case 'Declined':
				$order->update_status( 'failed' );
				wc_maybe_increase_stock_levels( $order->get_id() );
				break;
			case 'Captured':
				$order->payment_complete();
				break;
			default:
				// TODO: This is an unknown state, maybe handle?
				break;
		}

		$order->save();

		return $charge_status;
	}

	public function update_js() {
		$data = array(
			'action' => $this->get_current_cart_action(),
		);
		?>
		<script type="text/template" id="wc-apa-update-vals" data-value="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"></script>
		<?php
	}

	public function get_current_cart_action() {
		return WC()->cart->needs_shipping() ? 'PayAndShip' : 'PayOnly';
	}

	public function render_login_button_again() {
		?>
		<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated">
			<div class="col2-set">
				<div class="col-1">
					<div id="shipping_address_widget">
						<h3>
							<?php esc_html_e( 'Confirm payment method', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
						</h3>
						<div class="shipping_address_display">
							<p>Your cart changed, and you need to confirm your selected payment method again.</p>
							<?php $this->checkout_button(); ?>
						</div>
					</div>
				</div>
				<div class="col-2">
				</div>
			</div>
		</div>
		<?php
	}

	public function override_billing_fields( $fields ) {
		$old = ! empty( $fields['billing_state']['required'] );

		$fields = parent::override_billing_fields( $fields );

		$fields['billing_state']['required'] = $old;

		return $fields;
	}

	public function override_shipping_fields( $fields ) {
		$old = ! empty( $fields['shipping_state']['required'] );

		$fields = parent::override_shipping_fields( $fields );

		$fields['shipping_state']['required'] = $old;

		return $fields;
	}

	/**
	 * Unset keys json box.
	 *
	 * @return bool|void
	 */
	public function process_admin_options() {
		if ( check_admin_referer( 'woocommerce-settings' ) ) {
			if ( ! empty( $_POST['woocommerce_amazon_payments_advanced_button_language'] ) ) {
				$region   = $_POST['woocommerce_amazon_payments_advanced_payment_region'];
				$language = $_POST['woocommerce_amazon_payments_advanced_button_language'];
				$regions  = WC_Amazon_Payments_Advanced_API::get_languages_per_region();
				if ( ! isset( $regions[ $region ] ) || ! in_array( $language, $regions[ $region ], true ) ) {
					WC_Admin_Settings::add_error( sprintf( __( '%1$s is not a valid language for the %2$s region.', 'woocommerce-gateway-amazon-payments-advanced' ), $language, WC_Amazon_Payments_Advanced_API::get_region_label( $region ) ) );
					$_POST['woocommerce_amazon_payments_advanced_button_language'] = '';
				}
			}
			parent::process_admin_options();
		}
	}

	private function format_status_details( $status_details ) {
		$charge_status         = $status_details->state; // phpcs:ignore WordPress.NamingConventions
		$charge_status_reasons = $status_details->reasons; // phpcs:ignore WordPress.NamingConventions
		if ( empty( $charge_status_reasons ) ) {
			$charge_status_reasons = array();
		}
		$charge_status_reason = $status_details->reasonCode; // phpcs:ignore WordPress.NamingConventions

		if ( $charge_status_reason ) {
			$charge_status_reasons[] = (object) array(
				'reasonCode'        => $charge_status_reason,
				'reasonDescription' => '',
			);
		}

		return (object) array(
			'status'  => $charge_status,
			'reasons' => $charge_status_reasons,
		);
	}

	public function get_cached_charge_permission_status( WC_Order $order, $read_only = false ) {
		$charge_permission_status = $order->get_meta( 'amazon_charge_permission_status' );
		$charge_permission_status = json_decode( $charge_permission_status );
		if ( ! $read_only && ! is_object( $charge_permission_status ) ) {
			$charge_permission_id = $order->get_meta( 'amazon_charge_permission_id' );
			$charge_permission    = WC_Amazon_Payments_Advanced_API::get_charge_permission( $charge_permission_id );

			$charge_permission_status = $this->format_status_details( $charge_permission->statusDetails ); // phpcs:ignore WordPress.NamingConventions

			$order->update_meta_data( 'amazon_charge_permission_status', wp_json_encode( $charge_permission_status ) );
			$order->save();
		}

		return $charge_permission_status;
	}

	public function get_cached_charge_status( WC_Order $order, $read_only = false ) {
		$charge_status = $order->get_meta( 'amazon_charge_status' );
		$charge_status = json_decode( $charge_status );
		if ( ! is_object( $charge_status ) ) {
			if ( ! $read_only ) {
				$charge_id = $order->get_meta( 'amazon_charge_id' );
				$charge    = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );

				$charge_status = $this->format_status_details( $charge->statusDetails ); // phpcs:ignore WordPress.NamingConventions

				$order->update_meta_data( 'amazon_charge_status', wp_json_encode( $charge_status ) );
				$order->save();
			} else {
				$charge_status = (object) array(
					'status'  => null,
					'reasons' => array(),
				);
			}
		}

		return $charge_status;
	}

	public function refresh_cached_charge_status( WC_Order $order, $charge = null ) {
		if ( ! is_object( $charge ) ) {
			$charge_id = $order->get_meta( 'amazon_charge_id' );
			$charge    = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );
		}

		$charge_status = $this->format_status_details( $charge->statusDetails ); // phpcs:ignore WordPress.NamingConventions

		$order->update_meta_data( 'amazon_charge_status', wp_json_encode( $charge_status ) );
		$order->save();
	}

}
