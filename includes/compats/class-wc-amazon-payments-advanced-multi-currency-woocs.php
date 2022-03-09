<?php
/**
 * Main class to handle WOOCS – Currency Switcher for WooCommerce compatibility.
 * https://wordpress.org/plugins/woocommerce-currency-switcher/
 * Tested up to: 1.2.7
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce WOOCS – Currency Switcher for WooCommerce Multi-currency compatibility.
 */
class WC_Amazon_Payments_Advanced_Multi_Currency_Woocs extends WC_Amazon_Payments_Advanced_Multi_Currency_Abstract {


	/**
	 * Specify hooks where compatibility action takes place.
	 */
	public function __construct() {
		$version = is_a( wc_apa()->get_gateway(), 'WC_Gateway_Amazon_Payments_Advanced_Legacy' ) ? 'v1' : 'v2';
		if ( 'v1' === $version ) {
			// Option woocs_restrike_on_checkout_page === 1 will hide switcher on checkout.
			add_filter( 'option_woocs_restrike_on_checkout_page', array( $this, 'remove_currency_switcher_on_order_reference_suspended' ) );
			add_action( 'init', array( $this, 'remove_shortcode_currency_switcher_on_order_reference_suspended' ) );
		}

		parent::__construct();
	}


	/**
	 * Get Woocs selected currency.
	 *
	 * @return string
	 */
	public static function get_active_currency() {
		global $WOOCS; // phpcs:ignore WordPress.NamingConventions
		return is_object( $WOOCS ) && ! empty( $WOOCS->current_currency ) ? $WOOCS->current_currency : get_woocommerce_currency(); // phpcs:ignore WordPress.NamingConventions
	}

	/**
	 * Woocs has 2 ways of work:
	 * Settings > Advanced > Is multiple allowed
	 * If it is set, users will pay on selected currency (where we hook)
	 * otherwise it will just change currency on frontend, but order will be taken on original shop currency.
	 *
	 * @return bool
	 */
	public function is_front_end_compatible() {
		return get_option( 'woocs_is_multiple_allowed' ) ? false : true;
	}

	/**
	 * LEGACY v1 METHODS AND HOOKS
	 */

	/**
	 * On OrderReferenceStatus === Suspended, hide currency switcher.
	 *
	 * @param  bool $value Wether to remove or not the switcher.
	 * @return bool
	 */
	public function remove_currency_switcher_on_order_reference_suspended( $value ) {
		if ( $this->is_order_reference_checkout_suspended() ) {
			// By Pass Multi-currency, so we don't trigger a new set_order_reference_details on process_payment.
			$this->bypass_currency_session();
			return 1;
		}
		return $value;
	}

	/**
	 * On OrderReferenceStatus === Suspended, hide currency switcher.
	 */
	public function remove_shortcode_currency_switcher_on_order_reference_suspended() {
		if ( $this->is_order_reference_checkout_suspended() ) {
			// By Pass Multi-currency, so we don't trigger a new set_order_reference_details on process_payment.
			$this->bypass_currency_session();
			remove_shortcode( 'woocs' );
		}
	}

}
