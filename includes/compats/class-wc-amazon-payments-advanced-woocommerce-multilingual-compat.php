<?php
/**
 * Main class to handle compatbility with WPML plugin.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce APA compatibility with WooCommerce multilingual (WPML).
 *
 * @since 1.7.0
 */
class WC_Amazon_Payments_Advanced_Woocommerce_Multilingual_Compat {

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
		if ( ! class_exists( 'woocommerce_wpml' ) ) {
			return;
		}

		if ( ! defined( 'WC_DOING_AJAX' ) || ! WC_DOING_AJAX ) {
			return;
		}

		// WCML sets cart->needs_payment() to become `false`.
		add_filter( 'woocommerce_cart_needs_payment', '__return_true' );
	}
}
