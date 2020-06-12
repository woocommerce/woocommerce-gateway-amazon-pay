<?php
/**
 * Main class to handle compatbilities with other plugins.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WooCommerce Amazon Pay compats handler.
 *
 * @since 1.6.0
 */
class WC_Amazon_Payments_Advanced_Compat {

	/**
	 * Load compat classes and instantiate it.
	 */
	public function load_compats() {

		// Load built-in compat classes.
		require_once( 'compats/class-wc-amazon-payments-advanced-drip-compat.php' );
		require_once( 'compats/class-wc-amazon-payments-advanced-wgm-compat.php' );
		require_once( 'compats/class-wc-amazon-payments-advanced-dynamic-pricing-compat.php' );
		require_once( 'compats/class-wc-amazon-payments-advanced-subscribe-to-newsletter-compat.php' );
		require_once( 'compats/class-wc-amazon-payments-advanced-woocommerce-multilingual-compat.php' );


		$compats = array(
			'WC_Amazon_Payments_Advanced_Drip_Compat',
			'WC_Amazon_Payments_Advanced_WGM_Compat',
			'WC_Amazon_Payments_Advanced_Dynamic_Pricing_Compat',
			'WC_Amazon_Payments_Advanced_Subscribe_To_Newsletter_Compat',
			'WC_Amazon_Payments_Advanced_Woocommerce_Multilingual_Compat',
		);

		/**
		 * Filters the WooCommerce Amazon Pay compats.
		 *
		 * @since 1.6.0
		 *
		 * @param array $compats List of class names that provide compatibilities
		 *                       with WooCommerce Amazon Pay.
		 */
		$compats = apply_filters( 'woocommerce_amazon_pa_compats', $compats );
		foreach ( $compats as $compat ) {
			if ( class_exists( $compat ) ) {
				new $compat();
			}
		}
	}

	public function load_multicurrency() {
		require_once( 'compats/class-wc-amazon-payments-advanced-multi-currency.php' );
		WC_Amazon_Payments_Advanced_Multi_Currency::init();
	}
}
