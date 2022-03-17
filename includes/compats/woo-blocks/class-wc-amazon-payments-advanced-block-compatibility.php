<?php

use \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

class WC_Amazon_Payments_Advanced_Block_Compatibility {

	protected static $file_class_compats_map = array(
		'WC_Amazon_Payments_Advanced_Block_Compat_Classic' => __DIR__ . '/class-wc-amazon-payments-advanced-block-compat-classic.php',
	);

	public static function init( PaymentMethodRegistry $registry ) {
		$compats = apply_filters( 'woocommerce_amazon_pa_block_compatibility_class_array', self::$file_class_compats_map );
		if ( ! empty( $compats ) ) {
			require_once __DIR__ . '/class-wc-amazon-payments-advanced-block-compat-abstract.php';
		}
		foreach ( $compats as $class => $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				if ( class_exists( $class ) ) {
					$registry->register( new $class() );
				}
			}
		}
	}
}
