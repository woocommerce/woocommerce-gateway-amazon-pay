<?php
/**
 * Gateway class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Implement payment method for Amazon Pay.
 */
class WC_Gateway_Amazon_Payments_Advanced_Express extends WC_Gateway_Amazon_Payments_Advanced {

	/**
	 * Payment's method id.
	 *
	 * @var string
	 */
	public $id = 'amazon_payments_advanced_express';

	/**
	 * Gateways availability.
	 *
	 * Only available before processing checkout through
	 * WooCommerce Blocks.
	 *
	 * @var boolean
	 */
	protected $available = false;

	/**
	 * Constructor
	 *
	 * Needs to remain empty, so no hooks are being registered twice.
	 */
	public function __construct() {
		$this->method_title         = __( 'Amazon Pay Express', 'woocommerce-gateway-amazon-payments-advanced' );
		$this->method_description   = __( 'Amazon Pay is embedded directly into your existing web site, and all the buyer interactions with Amazon Pay and Login with Amazon take place in embedded widgets so that the buyer never leaves your site. Buyers can log in using their Amazon account, select a shipping address and payment method, and then confirm their order. Requires an Amazon Pay seller account and supports USA, UK, Germany, France, Italy, Spain, Luxembourg, the Netherlands, Sweden, Portugal, Hungary, Denmark, and Japan.', 'woocommerce-gateway-amazon-payments-advanced' );
		$this->icon                 = apply_filters( 'woocommerce_amazon_pa_logo', wc_apa()->plugin_url . '/assets/images/amazon-payments.png' );
		$this->view_transaction_url = $this->get_transaction_url_format();
		$this->supports             = array(
			'products',
			'refunds',
		);
		$this->supports             = apply_filters( 'woocommerce_amazon_pa_supports', $this->supports, wc_apa()->get_gateway() );
		$this->private_key          = get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );

		$this->settings = WC_Amazon_Payments_Advanced_API::get_settings();
		$this->load_settings();
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'visually_hide_amazon_express_on_backend' ) );
		add_action( 'woocommerce_amazon_pa_processed_order', array( $this, 'update_orders_payment_method' ), 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'available_on_block_checkout' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'restore_amazon_payments_advanced' ), 30 );
	}

	/**
	 * Skip un-setting amazon_payments_advanced gateway when using WooCommerce Blocks Checkout.
	 *
	 * @return void
	 */
	public function restore_amazon_payments_advanced() {
		if ( $this->using_woo_blocks() ) {
			remove_filter( 'woocommerce_available_payment_gateways', array( wc_apa()->get_gateway(), 'remove_amazon_gateway' ) );
		}
	}

	/**
	 * Make the gateway available when checkout triggered through WooCommerce Blocks.
	 *
	 * @return void
	 */
	public function available_on_block_checkout() {
		$this->available = true;
	}

	/**
	 * Returns Amazon Pay Express availability.
	 *
	 * @return bool
	 */
	protected function get_availability() {
		return parent::get_availability() && $this->available;
	}

	/**
	 * Returns an empty array as the Gateway's settings.
	 *
	 * @return array
	 */
	public function get_form_fields() {
		return array();
	}

	/**
	 * Update a single option.
	 *
	 * @param string $key Option key.
	 * @param mixed  $value Value to set.
	 * @return bool was anything saved?
	 */
	public function update_option( $key, $value = '' ) {
		return true;
	}

	/**
	 * Visually hides the gateway from the Backend list of available gateways.
	 *
	 * @return void
	 */
	public static function visually_hide_amazon_express_on_backend() {
		wp_enqueue_style( 'amazon_payments_advanced_hide_express', wc_apa()->plugin_url . '/assets/css/hide-amazon-express-admin.css', array(), wc_apa()->version );
	}

	/**
	 * Change the payment method after order is completed.
	 *
	 * @param WC_Order $order The order being completed.
	 * @return void
	 */
	public function update_orders_payment_method( $order ) {
		if ( $this->id === $order->get_payment_method() ) {
			$order->set_payment_method( wc_apa()->get_gateway() );
			$order->save();
		}
	}
}
