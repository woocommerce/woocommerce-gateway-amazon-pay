<?php

class WC_Amazon_Payments_Advanced_Block_Compat_Classic extends WC_Amazon_Payments_Advanced_Block_Compat_Abstract {

	public $name = 'amazon_payments_advanced';

	public $settings_name = 'woocommerce_amazon_payments_advanced_settings';

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

	public function is_active() {
		return wc_apa()->get_gateway()->is_available() && wc_apa()->get_gateway()->is_classic_enabled();
	}

	protected function scripts_name_per_type( string $type = '' ) {
		$script_data = include wc_apa()->path . '/build/index.asset.php';
		wp_register_script( 'amazon_payments_advanced_classic_block_compat', wc_apa()->plugin_url . '/build/index.js', $script_data['dependencies'], $script_data['version'], true );
		$scripts = array();
		switch ( $type ) {
			case 'frontend':
				// $scripts[] = 'amazon_payments_advanced_classic_block_compat';
				// $scripts[] = 'amazon_payments_advanced';
				// break;
			case 'backend':
			default:
				$scripts[] = 'amazon_payments_advanced_classic_block_compat';
				break;
		}
		return $scripts;
	}
}
