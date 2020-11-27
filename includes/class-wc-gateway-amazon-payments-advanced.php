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
		$is_available = ( 'yes' === $this->enabled );

		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			return $is_available;
		}

		$is_available = apply_filters( 'woocommerce_amazon_pa_is_gateway_available', $is_available );

		return ( $is_available && ! empty( $this->settings['merchant_id'] ) );
	}

	/**
	 * Load handlers for cart and orders after WC Cart is loaded.
	 */
	public function init_handlers() {
		// Disable if no seller ID.
		if ( ! apply_filters( 'woocommerce_amazon_payments_init', true ) || ! $this->is_available() ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'maybe_handle_apa_action' ) );

		// Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		// Checkout.
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );

		// Cart
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_amazon_pay_button_separator_html' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'checkout_button' ), 25 );
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

		wp_enqueue_style( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/css/style.css', array(), wc_apa()->version );
		wp_enqueue_script( 'amazon_payments_advanced_checkout', $this->get_region_script(), array(), wc_apa()->version, true );
		wp_enqueue_script( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/js/amazon-wc-checkout' . $js_suffix, array(), wc_apa()->version, true );

		$params = array(
			'ajax_url'                       => admin_url( 'admin-ajax.php' ),
			'create_checkout_session_config' => WC_Amazon_Payments_Advanced_API::get_create_checkout_session_config(),
			'button_color'                   => $this->settings['button_color'],
			'placement'                      => $this->get_current_placement(),
			'action'                         => WC()->cart->needs_shipping() ? 'PayAndShip' :  'PayOnly',
			'sandbox'                        => 'yes' === $this->settings['sandbox'],
			'merchant_id'                    => $this->settings['merchant_id'],
			'shipping_title'                 => esc_html__( 'Shipping details', 'woocommerce' ),
		);

		wp_localize_script( 'amazon_payments_advanced', 'amazon_payments_advanced', $params );

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
		switch( $region ) {
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
		<p id="wc-apa-button-separator" style="margin:1.5em 0;text-align:center;display:none;">&mdash; <?php esc_html_e( 'OR', 'woocommerce-gateway-amazon-payments-advanced' ); ?> &mdash;</p>
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

		if( ! $this->is_logged_in() ) {
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
			$logout_url      = $this->get_amazon_logout_url();
			$logout_msg_html = '<div class="woocommerce-info info">' . apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ) . ' <a href="' . esc_url( $logout_url ) . '" id="amazon-logout">' . __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a></div>';
			echo apply_filters( 'woocommerce_amazon_payments_logout_checkout_message_html', $logout_msg_html );
		}

		echo '</div>';

	}

	public function maybe_handle_apa_action() {

		if ( empty( $_GET['amazon_payments_advanced'] ) ) {
			return;
		}

		if ( is_null( WC()->session ) ) {
			return;
		}

		if ( isset( $_GET['amazon_logout'] ) ) {
			unset( WC()->session->amazon_checkout_session_id );
			wp_safe_redirect( get_permalink( wc_get_page_id( 'checkout' ) ) );
			exit;
		}

		if ( isset( $_GET['amazonCheckoutSessionId'] ) ) {
			WC()->session->set( 'amazon_checkout_session_id', $_GET['amazonCheckoutSessionId'] );
			wp_safe_redirect( get_permalink( wc_get_page_id( 'checkout' ) ) );
			exit;
		}

	}

	protected function get_checkout_session_id() {
		return WC()->session->get( 'amazon_checkout_session_id', false );
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

		// Some merchants might not have access to billing address information, so we need to make those fields optional
		// when the order doesn't require shipping.
		if ( ! apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() ) ) {
			foreach ( $optional_fields as $field ) {
				$checkout_fields['billing'][ $field ]['required'] = false;
			}
		}
		$this->add_hidden_class_to_fields( $checkout_fields['billing'], $all_fields );

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

		if ( apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() ) ) {
			$this->add_hidden_class_to_fields( $checkout_fields['shipping'], $field_list );
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
		// Skip showing address widget for carts with virtual products only.
		$show_address_widget = apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() );
		$hide_css_style      = ( ! $show_address_widget ) ? 'display: none;' : '';

		$checkout = WC_Checkout::instance();
		?>
		<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated">
			<div class="col2-set">
				<div class="col-1" style="<?php echo esc_attr( $hide_css_style ); ?>">
					<h3><?php esc_html_e( 'Shipping Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
					<div id="amazon_addressbook_widget"></div>
				</div>
				<div class="col-2">
					<h3><?php esc_html_e( 'Payment Method', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
					<div id="amazon_wallet_widget"></div>
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
	}

}
