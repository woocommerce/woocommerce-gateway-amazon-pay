<?php
/**
 * Main class to handle compatbility with woocommerce-dynamic-pricing extension.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce APA compatibility with Dynamic Pricing extension.
 *
 * @since 1.6.0
 */
class WC_Amazon_Payments_Advanced_Dynamic_Pricing_Compat {

	/**
	 * Specify hooks where compatbility action takes place.
	 */
	public function __construct() {
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_restore_subtotal' ), 9999, 1 );
	}

	/**
	 * Action performed to support the compatibility.
	 *
	 * @param WC_Cart $cart Cart instance.
	 */
	public function maybe_restore_subtotal( $cart ) {
		if ( ! class_exists( 'WC_Dynamic_Pricing' ) ) {
			return;
		}

		if ( ! defined( 'WC_DOING_AJAX' ) || ! WC_DOING_AJAX ) {
			return;
		}

		// Dynamic Pricing sets subtotal to `false` causing cart->needs_payment().
		// becomes `false`.
		if ( false === $cart->subtotal && $cart->total > 0 ) {
			add_filter( 'woocommerce_cart_needs_payment', '__return_true' );
		}
	}
}
