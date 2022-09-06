<?php
/**
 * Registers plugin's blocks.
 *
 * @package WC_Gateway_Amazon_Pay\Blocks
 */

/**
 * Backend registration of Amazon Blocks.
 */
class WC_Amazon_Payments_Advanced_Register_Blocks {
	/**
	 * Custom arguments used to identify if a block has
	 * - frontend script
	 * - frontend style
	 * - backend style
	 */
	const CUSTOM_ARGS = array(
		'frontend_script',
		'backend_style',
		'frontend_style',
	);

	/**
	 * Amazon Pay Blocks
	 */
	const BLOCKS = array(
		'change-address' => array(
			'frontend_script' => false,
			'backend_style'   => false,
			'frontend_style'  => false,
		),
		'log-out-banner' => array(
			'frontend_script' => false,
			'backend_style'   => false,
			'frontend_style'  => true,
		),
	);

	/**
	 * Register our hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_amazon_blocks' ), 90 );
	}

	/**
	 * Registers Amazon Pay Blocks.
	 *
	 * @return void
	 */
	public function register_amazon_blocks() {
		/**
		 * This blocks are only being used along with WooCommerce Blocks.
		 * So if WooCommerce blocks isn't present, we bail registration.
		 */
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
			return;
		}

		$plugin_root = wc_apa()->path;

		foreach ( self::BLOCKS as $block => $args ) {
			$blocks_json      = $plugin_root . '/blocks-metadata/' . $block . '/block.json';
			$script_data_file = $plugin_root . '/build/blocks/' . $block . '/index.asset.php';

			if ( ! file_exists( $blocks_json ) || ! file_exists( $script_data_file ) ) {
				continue;
			}

			$script_helper_data = include $script_data_file;
			wp_register_script( 'amazon-payments-advanced-blocks-' . $block . '-editor', wc_apa()->plugin_url . '/build/blocks/' . $block . '/index.js', $script_helper_data['dependencies'], $script_helper_data['version'], true );

			if ( ! empty( $args['frontend_script'] ) ) {
				$script_helper_data = include $plugin_root . '/build/blocks/' . $block . '/frontend.asset.php';
				wp_register_script( 'amazon-payments-advanced-blocks-' . $block, wc_apa()->plugin_url . '/build/blocks/' . $block . '/frontend.js', $script_helper_data['dependencies'], $script_helper_data['version'], true );
			}

			if ( ! empty( $args['frontend_style'] ) ) {
				wp_register_style( 'amazon-payments-advanced-blocks-' . $block, wc_apa()->plugin_url . '/build/style-blocks/' . $block . '/index.css', array(), $script_helper_data['version'] );
			}

			if ( ! empty( $args['backend_style'] ) ) {
				wp_register_style( 'amazon-payments-advanced-blocks-' . $block . '-editor', wc_apa()->plugin_url . '/build/blocks/' . $block . '/index.css', array(), $script_helper_data['version'] );
			}

			foreach ( self::CUSTOM_ARGS as $custom_arg ) {
				if ( ! isset( $args[ $custom_arg ] ) ) {
					continue;
				}

				unset( $args[ $custom_arg ] );
			}

			register_block_type_from_metadata( $blocks_json, $args );
		}
	}
}
