<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

abstract class WC_Amazon_Payments_Advanced_Block_Compat_Abstract extends AbstractPaymentMethodType {

	final public function __construct() {
		if ( ! isset( $this->name ) || ! isset( $this->settings_name ) ) {
			throw new Exception( 'You have to set both the properties name and settings_name when extending the class ' . __CLASS__ . ' !' );
		}
	}

	public function initialize() {
		$this->settings = get_option( $this->settings_name, array() );
	}

	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles() {
		return $this->scripts_name_per_type( 'frontend' );
	}

	public function get_payment_method_script_handles_for_admin() {
		return $this->scripts_name_per_type( 'backend' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'],
			'description' => $this->settings['description'],
			'supports'    => $this->get_supported_features(),
			// 'icons'                          => array(),
			// 'showSavedCards'                 => true,
			// 'showSaveOption'                 => true,
			// 'isAdmin'                        => is_admin(),
			// 'shouldShowPaymentRequestButton' => true,
			// 'button'                         => array(
			// 	'customLabel' => 'Pay with Amazon',
			// ),
		);
	}

	abstract protected function scripts_name_per_type( string $type = '' );

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
}
