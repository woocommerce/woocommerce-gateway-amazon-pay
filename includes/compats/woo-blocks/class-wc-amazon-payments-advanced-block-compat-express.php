<?php
/**
 * Integrates Amazon Pay "Express" in the Checkout Block of WooCommerce Blocks.
 *
 * @package WC_Gateway_Amazon_Pay\Compats\Woo-Blocks
 */

/**
 * Adds support for Amazon Pay "Express" in the checkout Block of WooCommerce Blocks.
 */
class WC_Amazon_Payments_Advanced_Block_Compat_Express extends WC_Amazon_Payments_Advanced_Block_Compat_Abstract {

	/**
	 * The payment method's name.
	 *
	 * @var string
	 */
	public $name = 'amazon_payments_advanced_express';

	/**
	 * The option where the payment method stores its settings.
	 *
	 * @var string
	 */
	public $settings_name = 'woocommerce_amazon_payments_advanced_settings';

	/**
	 * Returns if the payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return wc_apa()->should_express_be_loaded() && wc_apa()->get_gateway()->is_available();
	}

	/**
	 * Returns the frontend accessible data.
	 *
	 * Can be accessed by calling
	 * const settings = wc.wcSettings.getSetting( '{paymentMethodName}_data' );
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$wc_apa_gateway = wc_apa()->get_gateway();

		$checkout_session = $wc_apa_gateway->get_checkout_session_id() ? $wc_apa_gateway->get_checkout_session() : null;

		return array_merge(
			$this->settings,
			array(
				'loggedIn'              => ! is_admin() && $checkout_session && ! is_wp_error( $checkout_session ),
				'supports'              => $this->get_supported_features(),
				'logoutUrl'             => $wc_apa_gateway->get_amazon_logout_url(),
				'logoutMessage'         => apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ),
				'selectedPaymentMethod' => esc_html( $wc_apa_gateway->get_selected_payment_label( $checkout_session ) ),
				'hasPaymentPreferences' => $wc_apa_gateway->has_payment_preferences( $checkout_session ),
				'allOtherGateways'      => $this->gateways_to_unset_on_fe(),
				'allowedCurrencies'     => $this->get_allowed_currencies(),
				'amazonPayPreviewUrl'   => esc_url( wc_apa()->plugin_url . '/assets/images/amazon-pay-preview.png' ),
				'amazonAddress'         => array(
					'amazonBilling'  => $checkout_session && ! is_wp_error( $checkout_session ) && ! empty( $checkout_session->billingAddress ) ? WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->billingAddress ) : null, // phpcs:ignore WordPress.NamingConventions
					'amazonShipping' => $checkout_session && ! is_wp_error( $checkout_session ) && ! empty( $checkout_session->shippingAddress ) ? WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ) : null, // phpcs:ignore WordPress.NamingConventions
				),
			)
		);
	}

	/**
	 * Returns the scripts required by the payment method based on the $type param.
	 *
	 * @param string $type Can be 'backend' or 'frontend'.
	 * @return array Return an array of script handles that have been registered already.
	 */
	protected function scripts_name_per_type( $type = '' ) {
		/* Registering Express Payment Script. */
		$script_data = include wc_apa()->path . '/build/payments-methods/express/index.asset.php';
		wp_register_script( 'amazon_payments_advanced_express_block_compat', wc_apa()->plugin_url . '/build/payments-methods/express/index.js', $script_data['dependencies'], $script_data['version'], true );

		return array( 'amazon_payments_advanced_express_block_compat' );
	}

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways['amazon_payments_advanced'] ) ) {
			return $gateways['amazon_payments_advanced']->supports;
		}
		return array();
	}

	/**
	 * Returns an array of payment method ids to unset on the frontend.
	 *
	 * @return array
	 */
	protected function gateways_to_unset_on_fe() {
		$available_gateways = WC()->payment_gateways->payment_gateways();

		$express_gateway = wc_apa()->get_express_gateway();

		if ( null === $express_gateway ) {
			return array();
		}

		$regular_gateway = wc_apa()->get_gateway();

		if ( empty( $express_gateway->id ) || empty( $regular_gateway->id ) ) {
			return array();
		}

		if ( empty( $available_gateways[ $express_gateway->id ] ) || empty( $available_gateways[ $regular_gateway->id ] ) ) {
			return array();
		}

		return array_diff( array_keys( $available_gateways ), array( $express_gateway->id, $regular_gateway->id ) );
	}
}
