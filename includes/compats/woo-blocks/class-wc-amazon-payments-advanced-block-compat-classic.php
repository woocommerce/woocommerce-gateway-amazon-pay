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
		return wc_apa()->get_gateway()->is_available() && wc_apa()->get_gateway()->is_classic_enabled();
	}

	/**
	 * Returns the scripts required by the payment method based on the $type param.
	 *
	 * @param string $type Can be 'backend' or 'frontend'.
	 * @return array Return an array of script handles that have been registered already.
	 */
	protected function scripts_name_per_type( $type = '' ) {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.min' : '';
		$script_data = include wc_apa()->path . '/build/classic/index' . $min . '.asset.php';
		wp_register_script( 'amazon_payments_advanced_classic_block_compat', wc_apa()->plugin_url . '/build/classic/index' . $min . '.js', $script_data['dependencies'], $script_data['version'], true );
		return array( 'amazon_payments_advanced_classic_block_compat' );
	}
}
