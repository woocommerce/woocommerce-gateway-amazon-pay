<?php
/**
 * Integrates Amazon Pay "Express" in the Checkout Block of WooCommerce Blocks.
 *
 * @package WC_Gateway_Amazon_Pay\Compats\Woo-Blocks
 */

/**
 * Adds support for Amazon Pay "Express" in the checkout Block of WooCommerce Blocks.
 */
class WC_Amazon_Payments_Advanced_Block_Compat_Express extends WC_Amazon_Payments_Advanced_Block_Compat_Abstract {

	/**
	 * The payment method's name.
	 *
	 * @var string
	 */
	public $name = 'amazon_payments_advanced_express';

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
		return wc_apa()->get_gateway()->is_available() && wc_apa()->get_gateway()->is_express_enabled();
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
		return array_merge( $this->settings, array(
			'supports'              => $this->get_supported_features(),
			'logoutUrl'             => wc_apa()->get_gateway()->get_amazon_logout_url(),
			'logoutMessage'         => apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ),
			'selectedPaymentMethod' => esc_html( wc_apa()->get_gateway()->get_selected_payment_label() ),
			'hasPaymentPreferences' => wc_apa()->get_gateway()->has_payment_preferences(),
		) );
	}

	/**
	 * Returns the scripts required by the payment method based on the $type param.
	 *
	 * @param string $type Can be 'backend' or 'frontend'.
	 * @return array Return an array of script handles that have been registered already.
	 */
	protected function scripts_name_per_type( $type = '' ) {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		/* Registering Express Payment Script. */
		$script_data        = include wc_apa()->path . '/build/express/index' . $min . '.asset.php';
		wp_register_script( 'amazon_payments_advanced_express_block_compat', wc_apa()->plugin_url . '/build/express/index' . $min . '.js', $script_data['dependencies'], $script_data['version'], true );

		/* Registering Regular Payment Script, which takes over after user is logged in via Amazon. */
		$script_helper_data = include wc_apa()->path . '/build/express-helper/index' . $min . '.asset.php';
		wp_register_script( 'amazon_payments_advanced_express-helper_block_compat', wc_apa()->plugin_url . '/build/express-helper/index' . $min . '.js', $script_helper_data['dependencies'], $script_helper_data['version'], true );

		/* If the user is logged in via Amazon and in FrontEnd, return the helper script. */
		$script_dir_suffix = ! is_admin() && wc_apa()->get_gateway()->get_checkout_session_id() ? '-helper' : '';
		return array( 'amazon_payments_advanced_express' . $script_dir_suffix . '_block_compat' );
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['amazon_payments_advanced'] ) ) {
			return $gateways['amazon_payments_advanced']->supports;
		}
		return array();
	}
}
