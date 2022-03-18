<?php
/**
 * Integrates Amazon Pay "Classic" in the Checkout Block of WooCommerce Blocks.
 *
 * @package WC_Gateway_Amazon_Pay\Compats\Woo-Blocks
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
	 * @inheritDoc
	 */
	protected function scripts_name_per_type( string $type = '' ) {
		$script_data = include wc_apa()->path . '/build/index.asset.php';
		wp_register_script( 'amazon_payments_advanced_classic_block_compat', wc_apa()->plugin_url . '/build/index.js', $script_data['dependencies'], $script_data['version'], true );
		$scripts = array();
		switch ( $type ) {
			case 'frontend':
			case 'backend':
			default:
				$scripts[] = 'amazon_payments_advanced_classic_block_compat';
				break;
		}
		return $scripts;
	}
}
