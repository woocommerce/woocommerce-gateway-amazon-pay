<?php
/**
 * Abstract Class for all WooCommerce Blocks compatibility classes.
 *
 * @package WC_Gateway_Amazon_Pay\Compats\Woo-Blocks
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WooCommerce Blocks compatibility Abstract.
 *
 * @see https://github.com/woocommerce/woocommerce-gutenberg-products-block/blob/trunk/docs/extensibility/payment-method-integration.md
 */
abstract class WC_Amazon_Payments_Advanced_Block_Compat_Abstract extends AbstractPaymentMethodType {

	/**
	 * Checks that classes extending this class have set the properties name and settings_name.
	 *
	 * @throws Exception When name or settings_name properties are not set.
	 */
	final public function __construct() {
		if ( ! isset( $this->name ) || ! isset( $this->settings_name ) ) {
			throw new Exception( 'You have to set both the properties name and settings_name when extending the class ' . __CLASS__ . ' !' );
		}
	}

	/**
	 * Gets called during the server side initialization and sets our settings.
	 *
	 * Overwrite when you need different set of logic.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( $this->settings_name, array() );
	}

	/**
	 * Returns if the Payment Method is active.
	 *
	 * Overwrite when you need different set of logic.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns the frontend scripts required by the payment method.
	 *
	 * Return an array of script handles that have been registered already.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		return $this->scripts_name_per_type( 'frontend' );
	}

	/**
	 * Returns the backend scripts required by the payment method.
	 *
	 * Return an array of script handles that have been registered already.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles_for_admin() {
		return $this->scripts_name_per_type( 'backend' );
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
		return array(
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
			'supports'    => $this->get_supported_features(),
		);
	}

	/**
	 * Returns the scripts required by the payment method based on the $type param.
	 *
	 * @param string $type Can be 'backend' or 'frontend'.
	 * @return array Return an array of script handles that have been registered already.
	 */
	abstract protected function scripts_name_per_type( $type = '' );

	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $gateways[ $this->name ] ) ) {
			return $gateways[ $this->name ]->supports;
		}
		return array();
	}

	/**
	 * Returns supported currencies if multi currency is enabled.
	 *
	 * @return array|false
	 */
	protected function get_allowed_currencies() {
		if ( ! WC_Amazon_Payments_Advanced_Multi_Currency::is_active() ) {
			return false;
		}

		return array_values( WC_Amazon_Payments_Advanced_API::get_selected_currencies() );
	}
}
