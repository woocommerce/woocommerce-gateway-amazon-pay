<?php
/**
 * Gateway class to support WooCommerce Subscriptions on v1.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Gateway_Amazon_Payments_Advanced_Subscriptions_Legacy.
 */
class WC_Gateway_Amazon_Payments_Advanced_Subscriptions_Legacy {

	/**
	 * Constructor
	 */
	public function __construct() {

		add_action( 'woocommerce_amazon_pa_subscriptions_init', array( $this, 'init_handlers' ), 12 );

	}

	public function init_handlers( $version ) {
		$id      = wc_apa()->get_gateway()->id;
	}
}
