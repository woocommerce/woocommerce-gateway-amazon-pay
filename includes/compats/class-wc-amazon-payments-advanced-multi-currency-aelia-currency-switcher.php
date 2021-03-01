<?php
/**
 * Main class to handle Aelia Currency Switcher for WooCommerce compatibility.
 * Tested up to: Aelia Currency Switcher 4.9.14.210215.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 * @link https://aelia.co/shop/currency-switcher-woocommerce/
 */

/**
 * WooCommerce Aelia Currency Switcher for WooCommerce Multi-currency compatibility.
 *
 * @author Aelia
 * @link https://aelia.co/shop/currency-switcher-woocommerce/
 */
class WC_Amazon_Payments_Advanced_Multi_Currency_Aelia_Currency_Switcher extends WC_Amazon_Payments_Advanced_Multi_Currency_Abstract {
	/**
	 * Holds Aelia Currency Switcher instance.
	 *
	 * @var Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher
	 */
	protected $currency_switcher;

	/**
	 * Specify hooks where compatibility action takes place.
	 */
	public function __construct() {
		$this->currency_switcher = $GLOBALS['woocommerce-aelia-currencyswitcher'];
		add_filter( 'init', array( $this, 'remove_shortcode_currency_switcher_on_order_reference_suspended' ) );

		parent::__construct();
	}


	/**
	 * Get the selected currency from the Currency Switcher.
	 *
	 * @return string
	 */
	public function get_selected_currency() {
		return $this->currency_switcher->get_selected_currency();
	}

	/**
	 * The name of this method is misleading. It should return "true" if the multi-currency
	 * plugin only DISPLAYS prices in multiple currencies, but the transactions occur in
	 * a single currency. The Aelia Currency Switcher always ensures that transactions are
	 * completed in the currency used to place an order, therefore this method should return
	 * false.
	 *
	 * @return bool
	 */
	public function is_front_end_compatible() {
		return false;
	}

	/**
	 * On OrderReferenceStatus === Suspended, hide currency switcher.
	 */
	// TODO Clarify what this method does
	public function remove_shortcode_currency_switcher_on_order_reference_suspended( $value ) {
		if ( $this->is_order_reference_checkout_suspended() ) {
			// By Pass Multi-currency, so we don't trigger a new set_order_reference_details on process_payment
			$this->bypass_currency_session();
		}
	}

}
