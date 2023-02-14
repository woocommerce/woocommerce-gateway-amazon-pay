<?php
/**
 * Main class that registers compatibility with WooCommerce Blocks.
 *
 * @package WC_Gateway_Amazon_Pay\Compats\Woo-Blocks
 */

use \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

/**
 * WooCommerce Blocks Compatibility Class.
 */
class WC_Amazon_Payments_Advanced_Block_Compatibility {

	/**
	 * Holds a map of compatibility classes pointing to the files where they are declared.
	 *
	 * @var array
	 */
	protected static $file_class_compats_map = array(
		'WC_Amazon_Payments_Advanced_Block_Compat_Classic' => __DIR__ . '/class-wc-amazon-payments-advanced-block-compat-classic.php',
		'WC_Amazon_Payments_Advanced_Block_Compat_Express' => __DIR__ . '/class-wc-amazon-payments-advanced-block-compat-express.php',
	);

	/**
	 * Registers the compatible classes to the PaymentMethodRegistry.
	 *
	 * Hooked on woocommerce_blocks_payment_method_type_registration
	 *
	 * @param PaymentMethodRegistry $registry WooCommerce Block's registry instance.
	 * @return void
	 */
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
