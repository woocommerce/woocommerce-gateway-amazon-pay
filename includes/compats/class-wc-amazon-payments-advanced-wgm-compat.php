<?php
/**
 * Main class to handle compatbility with woocommerce-german-market extension.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce APA compatibility with WooCommerce German Market extension.
 *
 * @since 1.6.0
 */
class WC_Amazon_Payments_Advanced_WGM_Compat {

	/**
	 * Constructor.
	 *
	 * @since 1.6.0
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'maybe_set_amazon_pa_session' ) );
	}

	/**
	 * WGM stores checkout fields data in session before proceeding to second
	 * checkout page.
	 *
	 * @since 1.6.0
	 */
	public function maybe_set_amazon_pa_session() {
		if ( ! $this->wgm_api_exists() ) {
			return;
		}

		if ( $this->is_confirm_and_place_order_page() && $this->has_amazon_reference_id() ) {
			add_action( 'woocommerce_checkout_init', array( $this, 'remove_ui_hooks' ), 99 );
			add_filter( 'woocommerce_pa_hijack_checkout_fields', '__return_false' );

			$this->store_address_details();
		}
	}

	/**
	 * Check if WGM API exists.
	 *
	 * @since 1.6.0
	 *
	 * @return bool Returns true if WGM API exists
	 */
	public function wgm_api_exists() {
		if ( ! class_exists( 'WGM_Session' ) ) {
			return false;
		}
		if ( ! is_callable( array( 'WGM_Session', 'is_set' ) ) ) {
			return false;
		}

		if ( ! class_exists( 'WGM_Helper' ) ) {
			return false;
		}
		if ( ! is_callable( array( 'WGM_Helper', 'get_wgm_option' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if Amazon reference ID exists in WGM session.
	 *
	 * @since 1.6.0
	 *
	 * @return bool Returns true if WGM Session has Amazon reference ID
	 */
	public function has_amazon_reference_id() {
		return WGM_Session::is_set( 'amazon_reference_id', 'first_checkout_post_array' );
	}

	/**
	 * Retrieve Amazon reference ID from WGM Session.
	 *
	 * @since 1.6.0
	 *
	 * @return string Amazon reference ID
	 */
	public function get_amazon_reference_id() {
		return WGM_Session::get( 'amazon_reference_id', 'first_checkout_post_array' );
	}

	/**
	 * Retrieve Amazon access token from WGM Session.
	 *
	 * @since 1.6.0
	 *
	 * @return string Amazon access token
	 */
	public function get_amazon_access_token() {
		return WGM_Session::get( 'amazon_access_token', 'first_checkout_post_array' );
	}

	/**
	 * Check if current page is confirm and place order page from WGM.
	 *
	 * @since 1.6.0
	 *
	 * @return bool Return true if current page is confirm and place order page
	 *              from WGM.
	 */
	public function is_confirm_and_place_order_page() {
		return (
			is_page( WGM_Helper::get_wgm_option( 'check' ) )
			||
			wc_post_content_has_shortcode( 'woocommerce_de_check' )
		);
	}

	/**
	 * Remove any attempt to initialize Amazon widget on second checkout page.
	 *
	 * @since 1.6.0
	 */
	public function remove_ui_hooks() {
		remove_action( 'woocommerce_checkout_before_customer_details', array( wc_apa(), 'payment_widget' ), 20 );
		remove_action( 'woocommerce_checkout_before_customer_details', array( wc_apa(), 'address_widget' ), 10 );
	}

	/**
	 * Store address details from Amazon in WGM Session.
	 *
	 * @since 1.6.0
	 */
	public function store_address_details() {
		$order_reference_details = $this->get_amazon_order_details();
		if ( ! $order_reference_details ) {
			return;
		}

		// @codingStandardsIgnoreStart
		$buyer         = $order_reference_details->Buyer;
		$destination   = $order_reference_details->Destination->PhysicalDestination;
		$shipping_info = WC_Amazon_Payments_Advanced_API::format_address( $destination );

		$this->set_address_in_wgm( $shipping_info, 'shipping' );

		// Some market API endpoint return billing address information, parse it if present.
		if ( isset( $order_reference_details->BillingAddress->PhysicalAddress ) ) {

			$billing_address = WC_Amazon_Payments_Advanced_API::format_address( $order_reference_details->BillingAddress->PhysicalAddress );

		} elseif ( apply_filters( 'woocommerce_amazon_pa_billing_address_fallback_to_shipping_address', true ) ) {

			// Reuse the shipping address information if no bespoke billing info.
			$billing_address = $shipping_info;

		} else {
			$name            = ! empty( $buyer->Name ) ? (string) $buyer->Name : '';
			$billing_address = WC_Amazon_Payments_Advanced_API::format_name( $name );
		}

		$billing_address['email'] = (string) $buyer->Email;
		$billing_address['phone'] = isset( $billing_address['phone'] ) ? $billing_address['phone'] : (string) $buyer->Phone;
		// @codingStandardsIgnoreEnd

		$this->set_address_in_wgm( $billing_address, 'billing' );
	}

	/**
	 * Set address details of given type in WGM Session.
	 *
	 * @since 1.6.0
	 *
	 * @param array  $address Address details.
	 * @param string $type    Address type ('billing' or 'shipping').
	 */
	public function set_address_in_wgm( $address, $type = 'billing' ) {
		$checkout = WC_Checkout::instance();
		foreach ( $address as $key => $value ) {
			$key = $type . '_' . $key;
			WGM_Session::add( $key, $value, 'first_checkout_post_array' );
		}
	}

	/**
	 * Get amazon order details.
	 *
	 * @since 1.6.0
	 *
	 * @return bool|Object Returns OrderReferenceDetails object if succeed,
	 *                     otherwise false is returned
	 */
	public function get_amazon_order_details() {
		$request_args = array(
			'Action'                 => 'GetOrderReferenceDetails',
			'AmazonOrderReferenceId' => $this->get_amazon_reference_id(),
		);

		/**
		 * Full address information is available to the 'GetOrderReferenceDetails'
		 * call when we're in "login app" mode and we pass the AddressConsentToken
		 * to the API.
		 *
		 * @see "Getting the Shipping Address" section here: https://payments.amazon.com/documentation/lpwa/201749990
		 */
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();

		if ( 'yes' === $settings['enable_login_app'] ) {
			$request_args['AddressConsentToken'] = $this->get_amazon_access_token();
		}

		$response = WC_Amazon_Payments_Advanced_API_Legacy::request( $request_args );
		// @codingStandardsIgnoreStart
		if ( ! is_wp_error( $response ) && isset( $response->GetOrderReferenceDetailsResult->OrderReferenceDetails ) ) {
			return $response->GetOrderReferenceDetailsResult->OrderReferenceDetails;
		}
		// @codingStandardsIgnoreEnd

		return false;
	}
}

