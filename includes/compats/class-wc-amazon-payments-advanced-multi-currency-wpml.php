<?php
/**
 * Main class to handle WPML WooCommerce Multilingual compatibility.
 * https://wordpress.org/plugins/woocommerce-multilingual/
 * Tested up to: 4.3.7
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce WPML WooCommerce Multilingual Multi-currency compatibility.
 */
class WC_Amazon_Payments_Advanced_Multi_Currency_WPML extends WC_Amazon_Payments_Advanced_Multi_Currency_Abstract {

	/**
	 * @var woocommerce_wpml
	 */
	protected $wpml;

	/**
	 * Specify hooks where compatibility action takes place.
	 */
	public function __construct() {
		global $woocommerce_wpml;
		$this->wpml = $woocommerce_wpml;
		add_filter( 'init', array( $this, 'remove_currency_switcher_on_order_reference_suspended' ), 100 );

		parent::__construct();
	}

	/**
	 * Get WPML selected currency.
	 *
	 * @return string
	 */
	public function get_selected_currency() {
		return ( WC()->session ) ? WC()->session->get( 'client_currency' ) : get_woocommerce_currency();
	}

	/**
	 * On OrderReferenceStatus === Suspended, hide currency switcher.
	 */
	public function remove_currency_switcher_on_order_reference_suspended() {
		if ( $this->is_order_reference_checkout_suspended() ) {
			// By Pass Multi-currency, so we don't trigger a new set_order_reference_details on process_payment
			$this->bypass_currency_session();

			// Remove all WPML hooks to display switchers.
			remove_action( 'currency_switcher', array( $this->wpml->multi_currency->currency_switcher, 'currency_switcher' ) );
			remove_action( 'woocommerce_product_meta_start', array( $this->wpml->multi_currency->currency_switcher, 'show_currency_switcher' ) );
			remove_action( 'wcml_currency_switcher', array( $this->wpml->multi_currency->currency_switcher, 'wcml_currency_switcher' ) );
			remove_shortcode( 'currency_switcher' );
		}
	}

}
