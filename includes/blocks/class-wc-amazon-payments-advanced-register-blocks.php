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
	const CUSTOM_ARGS = array(
		'frontend_script',
		'backend_style',
		'frontend_style',
	);

	const BLOCKS = array(
		'change-address' => array(
			'frontend_script' => false,
			'backend_style'   => false,
			'frontend_style'  => false,
		),
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_amazon_blocks' ), 90 );
	}

	public function register_amazon_blocks() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$plugin_root = wc_apa()->path;

		foreach ( self::BLOCKS as $block => $args ) {
			$blocks_json      = $plugin_root . '/blocks-metadata/' . $block . '/block.json';
			$script_data_file = $plugin_root . '/build/blocks/' . $block . '/index' . $min . '.asset.php';

			if ( ! file_exists( $blocks_json ) || ! file_exists( $script_data_file ) ) {
				continue;
			}

			$script_helper_data = include $script_data_file;
			wp_register_script( 'amazon-payments-advanced-blocks-' . $block . '-editor', wc_apa()->plugin_url . '/build/blocks/' . $block . '/index' . $min . '.js', $script_helper_data['dependencies'], $script_helper_data['version'], true );

			if ( $args['frontend_script'] ) {
			}
			if ( $args['frontend_style'] ) {
			}
			if ( $args['backend_style'] ) {
			}

			foreach ( self::CUSTOM_ARGS as $custom_arg ) {
				unset( $args[ $custom_arg ] );
			}

			register_block_type_from_metadata( $blocks_json, $args );
		}
	}
}
