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

	public function checkout_init() {

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

	protected function is_logged_in() {
		if ( is_null( WC()->session ) ) {
			return false;
		}

		$session_id = WC()->session->get( 'amazon_checkout_session_id', false );

		return ! empty( $session_id ) ? true : false;
	}

}
