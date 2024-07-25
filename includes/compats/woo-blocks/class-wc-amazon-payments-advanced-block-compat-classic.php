<?php
/**
 * Integrates Amazon Pay "Classic" in the Checkout Block of WooCommerce Blocks.
 *
 * @package WC_Gateway_Amazon_Pay\Compats\Woo-Blocks
 */

/**
 * Adds support for Amazon Pay "Classic" in the checkout Block of WooCommerce Blocks.
 */
class WC_Amazon_Payments_Advanced_Block_Compat_Classic extends WC_Amazon_Payments_Advanced_Block_Compat_Abstract {

	/**
	 * The payment method's name.
	 *
	 * @var string
	 */
	public $name = 'amazon_payments_advanced';

	/**
	 * The option where the payment method stores its settings.
	 *
	 * @var string
	 */
	public $settings_name = 'woocommerce_amazon_payments_advanced_settings';

	/**
	 * Returns if the payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$wc_apa_gateway = wc_apa()->get_gateway();
		return $wc_apa_gateway->is_available() && $wc_apa_gateway->is_classic_enabled() && ! $wc_apa_gateway->get_checkout_session_id();
	}

	/**
	 * Returns the frontend accessible data.
	 *
	 * Can be accessed by calling
	 * const settings = wc.wcSettings.getSetting( '{paymentMethodName}_data' );
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'               => $this->settings['title'],
			'description'         => $this->settings['description'],
			'supports'            => $this->get_supported_features(),
			'amazonPayPreviewUrl' => esc_url( wc_apa()->plugin_url . '/build/images/amazon-pay-preview.png' ),
			'action'              => wc_apa()->get_gateway()->get_current_cart_action(),
			'allowedCurrencies'   => $this->get_allowed_currencies(),
		);
	}

	/**
	 * Returns the scripts required by the payment method based on the $type param.
	 *
	 * @param string $type Can be 'backend' or 'frontend'.
	 * @return array Return an array of script handles that have been registered already.
	 */
	protected function scripts_name_per_type( $type = '' ) {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$script_data = include wc_apa()->path . '/build/js/payments-methods/classic/index.asset.php';
		wp_register_script( 'amazon_payments_advanced_classic_block_compat', wc_apa()->plugin_url . '/build/js/payments-methods/classic/index' . $min . '.js', $script_data['dependencies'], $script_data['version'], true );
		wp_set_script_translations( 'amazon_payments_advanced_classic_block_compat', 'woocommerce-gateway-amazon-payments-advanced' );

		return array( 'amazon_payments_advanced_classic_block_compat' );
	}
}
