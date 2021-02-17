<?php
/**
 * Main class to handle compatbility with Multi-currency.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce Multi-currency 3rd party compatibility.
 */
class WC_Amazon_Payments_Advanced_Multi_Currency {

	/**
	 * @var WC_Amazon_Payments_Advanced_Multi_Currency_Abstract
	 */
	public static $compatible_instance;

	/**
	 * List of compatible plugins, with its global variable name or main class.
	 */
	const COMPATIBLE_PLUGINS = array(
		'global_WOOCS'                         => 'WOOCS – Currency Switcher for WooCommerce',
		'class_WC_Product_Price_Based_Country' => 'Price Based on Country for WooCommerce',
		'global_woocommerce_wpml'              => 'WPML WooCommerce Multilingual',
		'class_WC_Currency_Converter'          => 'Currency Converter Widget',
	);

	/**
	 * List of compatible regions supportng multi-currency.
	 */
	const COMPATIBLE_REGIONS = array(
		'eu',
		'gb',
	);

	/**
	 * WC_Amazon_Payments_Advanced_Multi_Currency constructor.
	 *
	 * @param string|null $region Region to inject.
	 */
	public static function init( $region = null ) {
		if ( ! self::compatible_region( $region ) ) {
			return;
		}

		if ( ! self::$compatible_instance ) {
			$compatible_plugin = self::compatible_plugin();
			if ( $compatible_plugin ) {
				require_once 'class-wc-amazon-payments-advanced-multi-currency-abstract.php';

				switch ( $compatible_plugin ) {
					case 'global_WOOCS':
						require_once 'class-wc-amazon-payments-advanced-multi-currency-woocs.php';
						self::$compatible_instance = new WC_Amazon_Payments_Advanced_Multi_Currency_Woocs();
						break;
					case 'class_WC_Product_Price_Based_Country':
						require_once 'class-wc-amazon-payments-advanced-multi-currency-ppbc.php';
						self::$compatible_instance = new WC_Amazon_Payments_Advanced_Multi_Product_Price_Based_Country();
						break;
					case 'global_woocommerce_wpml':
						$wpml_settings = get_option( '_wcml_settings' );
						if ( ( WCML_MULTI_CURRENCIES_DISABLED !== $wpml_settings['enable_multi_currency'] ) ) {
							require_once 'class-wc-amazon-payments-advanced-multi-currency-wpml.php';
							self::$compatible_instance = new WC_Amazon_Payments_Advanced_Multi_Currency_WPML();
						}
						break;
					case 'class_WC_Currency_Converter':
						require_once 'class-wc-amazon-payments-advanced-multi-currency-wccw.php';
						self::$compatible_instance = new WC_Amazon_Payments_Advanced_Multi_Currency_Converted_Widget();
						break;
				}
			}
		}
	}

	/**
	 * Checks if region is compatible.
	 *
	 * @param string|null $region
	 *
	 * @return bool
	 */
	public static function compatible_region( $region = null ) {
		$region = ( $region ) ? $region : WC_Amazon_Payments_Advanced_API::get_region();
		return is_int( array_search( $region, self::COMPATIBLE_REGIONS ) );
	}

	/**
	 * Singleton to get if there is a compatible instance running. Region can be injected.
	 * 
	 * @param bool $region
	 *
	 * @return WC_Amazon_Payments_Advanced_Multi_Currency_Abstract
	 */
	public static function get_compatible_instance( $region = false ) {
		if ( ! self::$compatible_instance ) {
			new self( $region );
		}
		return self::$compatible_instance;
	}

	/**
	 * Multi-currency is active behind the doors, once there is a compatible plugin (active instance).
	 * If plugin is frontend compatible, we consider multi-currency not active, since we don't have to intercede.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return ( isset( self::$compatible_instance ) && ( ! self::$compatible_instance->is_front_end_compatible() ) );
	}

	/**
	 * @return bool
	 */
	public static function reload_wallet_widget() {
		return self::$compatible_instance->reload_wallet_widget();
	}

	/**
	 * Get selected currency from selected currency.
	 *
	 * @return string
	 */
	public static function get_selected_currency() {
		return self::$compatible_instance->get_selected_currency();
	}

	/**
	 * Currency switched in checkout page.
	 * If original currency on checkout is different of the current one.
	 *
	 * @return bool
	 */
	public static function is_currency_switched_on_checkout() {
		if ( self::$compatible_instance->get_currency_switched_times() > 0 ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns name or global/class name of compatible plugin or false.
	 *
	 * @param bool $return_name If name is true, returns commercial name.
	 *
	 * @return string
	 */
	public static function compatible_plugin( $return_name = false ) {

		foreach ( self::COMPATIBLE_PLUGINS as $definition_name => $name ) {
			$match = false;
			if ( 0 === strpos( $definition_name, 'global' ) ) {
				$global_name = str_replace( 'global_', '', $definition_name );

				if ( isset( $GLOBALS[ $global_name ] ) && $GLOBALS[ $global_name ] ) {
					$match = true;
				}
			} elseif ( 0 === strpos( $definition_name, 'class' ) ) {
				$class_name = str_replace( 'class_', '', $definition_name );
				if ( class_exists( $class_name ) ) {
					$match = true;
				}
			}
			if ( $match ) {
				return ( $return_name ) ? $name : $definition_name;
			}
		}
		return false;
	}

	public function is_amazon_settings_page() {
		if ( is_admin() &&
			 ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) &&
			 ( isset( $_GET['section'] ) && 'amazon_payments_advanced' === $_GET['section'] ) ) {
			return true;
		}
		return false;
	}

}

