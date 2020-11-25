<?php
/**
 * Gateway class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Implement payment method for Amazon Pay.
 */
class WC_Gateway_Amazon_Payments_Advanced extends WC_Gateway_Amazon_Payments_Advanced_Abstract {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		// Init Handlers
		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 11 );
	}

	/**
	 * Load handlers for cart and orders after WC Cart is loaded.
	 */
	public function init_handlers() {
		// Disable if no seller ID.
		if ( ! apply_filters( 'woocommerce_amazon_payments_init', true ) || empty( $this->settings['merchant_id'] ) || 'no' === $this->settings['enabled'] ) {
			return;
		}

		// Scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
	}

	/**
	 * Add scripts
	 */
	public function scripts() {

		$enqueue_scripts = is_cart() || is_checkout() || is_checkout_pay_page();

		if ( ! apply_filters( 'woocommerce_amazon_pa_enqueue_scripts', $enqueue_scripts ) ) {
			return;
		}

		wp_enqueue_script( 'amazon_payments_advanced_checkout', $this->get_region_script(), array(), wc_apa()->version, true );

	}

	protected function get_region_script() {
		$region     = $this->settings['payment_region'];

		// If payment_region is not set in settings, use base country.
		if ( ! $region ) {
			$region = WC_Amazon_Payments_Advanced_API::get_payment_region_from_country( WC()->countries->get_base_country() );
		}

		$url = false;
		switch( $region ) {
			case 'us':
				$url = 'https://static-na.payments-amazon.com/checkout.js';
				break;
			case 'uk':
			case 'eu':
				$url = 'https://static-eu.payments-amazon.com/checkout.js';
				break;
			case 'jp':
				$url = 'https://static-fe.payments-amazon.com/checkout.js';
				break;
		}

		return $url;
	}

}
