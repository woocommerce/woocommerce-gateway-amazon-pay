<?php
/**
 * Main class to handle WooCommerce Price Based on Country compatibility.
 * https://wordpress.org/plugins/woocommerce-product-price-based-on-countries/
 * Tested up to: 1.7.18
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce WooCommerce Price Based on Country Multi-currency compatibility.
 */
class WC_Amazon_Payments_Advanced_Multi_Currency_PPBC extends WC_Amazon_Payments_Advanced_Multi_Currency_Abstract {

	/**
	 * Session key to force new region.
	 */
	const FORCE_NEW_ZONE_SESSION = 'force_new_region';

	/**
	 * Holds Product Price Base Country instance.
	 *
	 * @var WC_Product_Price_Based_Country
	 */
	protected $ppbc;

	/**
	 * Specify hooks where compatibility action takes place.
	 */
	public function __construct() {
		$version = is_a( wc_apa()->get_gateway(), 'WC_Gateway_Amazon_Payments_Advanced_Legacy' ) ? 'v1' : 'v2';
		if ( 'v1' === $version ) {
			// Hooks before PPBC inits to inject new zone if needed.
			add_action( 'wc_price_based_country_before_frontend_init', array( $this, 'hook_before_ppbc_update_order_review' ) );
			add_action( 'wc_price_based_country_before_frontend_init', array( $this, 'hook_before_ppbc_get_refreshed_fragments' ) );

			add_action( 'widgets_init', array( $this, 'remove_currency_switcher_on_order_reference_suspended' ) );
		}

		$this->ppbc = WC_Product_Price_Based_Country::instance();
		parent::__construct();
	}

	/**
	 * Get PPBC selected currency.
	 *
	 * @return string
	 */
	public static function get_active_currency() {
		// This is for sandbox mode, changing countries manually.
		if ( isset( $_REQUEST['wcpbc-manual-country'] ) ) {
			$manual_country = wc_clean( wp_unslash( $_REQUEST['wcpbc-manual-country'] ) );
			$selected_zone  = WCPBC_Pricing_Zones::get_zone_by_country( $manual_country );
		} else {
			$selected_zone = wcpbc_get_zone_by_country();
		}
		return ( $selected_zone ) ? $selected_zone->get_currency() : get_woocommerce_currency();
	}

	/**
	 * LEGACY v1 METHODS AND HOOKS
	 */

	/**
	 * Get selected currency, to be used on frontend.
	 */
	public function ajax_get_currency() {
		check_ajax_referer( 'multi_currency_nonce', 'nonce' );
		if ( $this->is_currency_compatible( self::get_active_currency() ) ) {
			$currency = self::get_active_currency();
		} else {
			$currency = wcpbc_get_base_currency();
		}
		echo $currency;
		wp_die();
	}

	/**
	 * As Changing addresses on Address widget changes currency, we need to reload wallet accordingly.
	 *
	 * @return bool
	 */
	public function reload_wallet_widget() {
		return true;
	}

	/**
	 * Allow PPBC to get proper country every time user changes its address on address widget.
	 * If the new shipping address has associated a currency amazon does not support, set base country to session, then new currency is not force.
	 * Hooks before $ppbc looks for Customer Session.
	 * Amazon has not set up yet the customer information, so we need to set shipping and billing.
	 */
	public function hook_before_ppbc_update_order_review() {
		if ( defined( 'WC_DOING_AJAX' ) &&
			WC_DOING_AJAX &&
			isset( $_GET['wc-ajax'] ) &&
			'update_order_review' === $_GET['wc-ajax'] &&
			isset( $_REQUEST['payment_method'] ) &&
			'amazon_payments_advanced' === $_REQUEST['payment_method']
		) {
			$order_details = $this->get_amazon_order_details();
			// @codingStandardsIgnoreStart
			if ( ! $order_details || ! isset( $order_details->Destination->PhysicalDestination ) ) {
				return;
			}

			$address = WC_Amazon_Payments_Advanced_API::format_address( $order_details->Destination->PhysicalDestination );

			if ( isset( $address['country'] ) ) {

				$ppbc_zone = WCPBC_Pricing_Zones::get_zone_by_country( $address['country'] );

				/**
				 * If zone not defined, fallback zone handled by PPCB
				 */
				if ( ! $ppbc_zone ) {
					$this->set_shipping_billing_customer( $address['country'] );
					return;
				}

				$currency_selected_zone = $ppbc_zone->get_currency();

				if ( $this->is_currency_compatible( $currency_selected_zone ) ) {
					$this->set_shipping_billing_customer( $address['country'] );
				} else {
					// If currency not compatible with Amazon, we set woo base country.
					$base_country = $this->get_base_country();

					$this->set_shipping_billing_customer( $base_country );
					WC()->session->set(self::FORCE_NEW_ZONE_SESSION, $base_country);
				}
			}
		}
	}

	/**
	 * If the country has been forced on hook_before_ppbc_update_order_review, set session again so
	 * get_refreshed_fragments gets same country/currency.
	 */
	public function hook_before_ppbc_get_refreshed_fragments() {
		if ( defined( 'WC_DOING_AJAX' ) &&
		     WC_DOING_AJAX &&
		     isset( $_GET['wc-ajax'] ) &&
		     'get_refreshed_fragments' === $_GET['wc-ajax']
		) {
			$base_country = WC()->session->get( self::FORCE_NEW_ZONE_SESSION );
			if ( $base_country ) {
				$this->set_shipping_billing_customer( $base_country );
				WC()->session->__unset( self::FORCE_NEW_ZONE_SESSION );
			}
		}
	}

	/**
	 * Get base country where the shop is set up.
	 *
	 * @return string
	 */
	public function get_base_country() {
		$base_location = wc_get_base_location();
		return $base_location['country'];
	}

	/**
	 * Sets billing and shipping country on WC customer.
	 *
	 * @param string $country
	 */
	public function set_shipping_billing_customer( $country ) {
		WC()->customer->set_shipping_country( $country );
		WC()->customer->set_billing_country( $country );
	}

	/**
	 * Get Amazon Order Details from current Reference id.
	 *
	 * @return bool|SimpleXMLElement
	 */
	public function get_amazon_order_details() {

		$request_args = array(
			'Action'                 => 'GetOrderReferenceDetails',
			'AmazonOrderReferenceId' => WC_Amazon_Payments_Advanced_API_Legacy::get_reference_id(),
		);

		/**
		 * Full address information is available to the 'GetOrderReferenceDetails' call when we're in
		 * "login app" mode and we pass the AddressConsentToken to the API.
		 *
		 * @see the "Getting the Shipping Address" section here: https://payments.amazon.com/documentation/lpwa/201749990
		 */
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();
		if ( 'yes' == $settings['enable_login_app'] ) {
			$request_args['AddressConsentToken'] = WC_Amazon_Payments_Advanced_API_Legacy::get_access_token();
		}

		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $request_args );

		// @codingStandardsIgnoreStart
		if ( ! is_wp_error( $response ) && isset( $response->GetOrderReferenceDetailsResult->OrderReferenceDetails ) ) {
			return $response->GetOrderReferenceDetailsResult->OrderReferenceDetails;
		}
		// @codingStandardsIgnoreEnd

		return false;
	}

	/**
	 * On OrderReferenceStatus === Suspended, hide currency switcher.
	 */
	public function remove_currency_switcher_on_order_reference_suspended() {
		if ( $this->is_order_reference_checkout_suspended() ) {
			// By Pass Multi-currency, so we don't trigger a new set_order_reference_details on process_payment.
			$this->bypass_currency_session();
			unregister_widget( 'WCPBC_Widget_Country_Selector' );
		}
	}

}

