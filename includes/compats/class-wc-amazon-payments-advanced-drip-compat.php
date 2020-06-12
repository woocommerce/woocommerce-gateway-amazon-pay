<?php
/**
 * Main class to handle compatbility with woocommerce-drip extension.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce APA compatibility with Drip extension.
 *
 * @since 1.6.0
 */
class WC_Amazon_Payments_Advanced_Drip_Compat {

	/**
	 * Specify hooks where compatbility action takes place.
	 */
	public function __construct() {
		add_action( 'wc_amazon_pa_scripts_enqueued', array( $this, 'drip_compat_scripts' ) );
	}

	/**
	 * Action performed to support the compatibility.
	 */
	public function drip_compat_scripts() {
		$js_suffix = '.min.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$js_suffix = '.js';
		}

		$url = wc_apa()->plugin_url . '/assets/js/amazon-wcdrip-compat' . $js_suffix;
		wp_enqueue_script( 'amazon_pa_drip_compat', $url, array( 'amazon_payments_advanced' ), wc_apa()->version, true );
	}

}
