<?php
/**
 * Main class to handle compatbilities with other plugins.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WooCommerce Amazon Pay compats handler.
 *
 * @since 1.6.0
 */
class WC_Amazon_Payments_Advanced_Compat {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->require_compats(); // Need to require early for some static methods to be available.
		add_action( 'woocommerce_amazon_pa_init', array( $this, 'load_compats' ) );
		add_action( 'woocommerce_amazon_pa_init', array( $this, 'load_multicurrency' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'load_block_compatibility' ) );
	}

	/**
	 * Load compat classes and instantiate it.
	 */
	public function require_compats() {
		// Require built-in compat classes.
		require_once 'compats/class-wc-amazon-payments-advanced-drip-compat.php';
		require_once 'compats/class-wc-amazon-payments-advanced-wgm-compat.php';
		require_once 'compats/class-wc-amazon-payments-advanced-dynamic-pricing-compat.php';
		require_once 'compats/class-wc-amazon-payments-advanced-subscribe-to-newsletter-compat.php';
		require_once 'compats/class-wc-amazon-payments-advanced-woocommerce-multilingual-compat.php';

		// Require multi-currency compat class.
		require_once 'compats/class-wc-amazon-payments-advanced-multi-currency.php';
	}

	/**
	 * Load compat classes and instantiate it.
	 */
	public function load_compats() {
		$compats = array(
			'WC_Amazon_Payments_Advanced_Drip_Compat',
			'WC_Amazon_Payments_Advanced_WGM_Compat',
			'WC_Amazon_Payments_Advanced_Dynamic_Pricing_Compat',
			'WC_Amazon_Payments_Advanced_Subscribe_To_Newsletter_Compat',
			'WC_Amazon_Payments_Advanced_Woocommerce_Multilingual_Compat',
		);

		/**
		 * Filters the WooCommerce Amazon Pay compats.
		 *
		 * @since 1.6.0
		 *
		 * @param array $compats List of class names that provide compatibilities
		 *                       with WooCommerce Amazon Pay.
		 */
		$compats = apply_filters( 'woocommerce_amazon_pa_compats', $compats );
		foreach ( $compats as $compat ) {
			if ( class_exists( $compat ) ) {
				new $compat();
			}
		}
	}

	/**
	 * Init Multicurrency hooks
	 */
	public function load_multicurrency() {
		WC_Amazon_Payments_Advanced_Multi_Currency::init();
	}

	/**
	 * Loads the WooCommerce Block Compatibility Classes,
	 * when the WooCommerce Blocks Plugin is active.
	 *
	 * @return void
	 */
	public function load_block_compatibility() {
		if ( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() && class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) && file_exists( __DIR__ . '/compats/woo-blocks/class-wc-amazon-payments-advanced-block-compatibility.php' ) ) {
			require_once __DIR__ . '/compats/woo-blocks/class-wc-amazon-payments-advanced-block-compatibility.php';
			add_action( 'woocommerce_blocks_payment_method_type_registration', array( WC_Amazon_Payments_Advanced_Block_Compatibility::class, 'init' ) );
		}
	}
}
