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

	public $id = 'amazon_payments_advanced_express';

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
		add_action( 'admin_head', array( $this, 'visually_hide_amazon_express_on_backend' ) );
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
	public function visually_hide_amazon_express_on_backend() {
		?>
		<style>table.wc_gateways tr[data-gateway_id="<?php echo esc_attr( $this->id ); ?>"]{display:none!important;}</style>
		<?php
	}

}
