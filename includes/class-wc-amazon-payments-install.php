<?php
/**
 * Main class for install.
 *
 * @package WC_Gateway_Amazon_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Amazon_Payments_Advanced_Install Class.
 */
class WC_Amazon_Payments_Advanced_Install {

	const APA_NEW_INSTALL_OPTION = 'woocommerce_amazon_payments_new_install';

	/**
	 * On Amazon pay plugin.
	 */
	public static function install() {
		self::log_when_fresh_install();
	}

	/**
	 * If it is a fresh install (new merchant), we will log version to be used for back/forward compatibility.
	 */
	protected static function log_when_fresh_install() {
		$settings = get_option( 'woocommerce_amazon_payments_advanced_settings' );
		if ( ! $settings ) {
			update_option( self::APA_NEW_INSTALL_OPTION, wc_apa()->version );
		}
	}
}

