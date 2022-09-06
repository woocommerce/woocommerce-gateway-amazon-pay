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
	 * Instance for the compatible plugin handler
	 *
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
		if ( self::$compatible_instance ) {
			return; // already initialized.
		}

		/* Filter out the active currency selected. */
		add_filter( 'woocommerce_amazon_pa_active_currency', array( self::class, 'get_active_currency' ) );

		// Load multicurrency fields if compatibility. (Only on settings admin).
		if ( is_admin() ) {
			$compatible_region = isset( $_POST['woocommerce_amazon_payments_advanced_payment_region'] ) ? self::compatible_region( $_POST['woocommerce_amazon_payments_advanced_payment_region'] ) : self::compatible_region();
			if ( $compatible_region ) {
				add_filter( 'woocommerce_amazon_pa_form_fields_before_legacy', array( __CLASS__, 'add_currency_fields' ) );
			}
		}

		$region = ! is_null( $region ) ? $region : WC_Amazon_Payments_Advanced_API::get_region();

		if ( ! self::compatible_region( $region ) ) {
			return;
		}

		self::init_compatible_plugin_instance();
	}

	/**
	 * Checks if region is compatible.
	 *
	 * @param string|null $region Region to check for compatibility.
	 *
	 * @return bool
	 */
	public static function compatible_region( $region = null ) {
		$region = ! is_null( $region ) ? $region : WC_Amazon_Payments_Advanced_API::get_region();
		return is_int( array_search( $region, self::COMPATIBLE_REGIONS, true ) );
	}

	/**
	 * Singleton to get if there is a compatible instance running. Region can be injected.
	 *
	 * @param bool $region Region to check for compatibility.
	 *
	 * @return WC_Amazon_Payments_Advanced_Multi_Currency_Abstract
	 */
	public static function get_compatible_instance( $region = null ) {
		if ( ! self::$compatible_instance ) {
			self::init();
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
	 * Reload wallet widget wrapper around the instance
	 *
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
		return self::$compatible_instance::get_active_currency();
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

	/**
	 * Are we on the settings page?
	 *
	 * @return bool
	 */
	public function is_amazon_settings_page() {
		if ( is_admin() &&
			( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) &&
			( isset( $_GET['section'] ) && 'amazon_payments_advanced' === $_GET['section'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Adds multicurrency settings to form fields.
	 *
	 * @param  array $form_fields Admin fields.
	 * @return array
	 */
	public static function add_currency_fields( $form_fields ) {
		if ( ! self::$compatible_instance ) {
			return $form_fields;
		}

		$compatible_plugin = self::compatible_plugin( true );

		$form_fields['multicurrency_options'] = array(
			'title'       => __( 'Multi-Currency', 'woocommerce-gateway-amazon-payments-advanced' ),
			'type'        => 'title',
			/* translators: Compatible plugin */
			'description' => sprintf( __( 'Multi-currency compatibility detected with <strong>%s</strong>', 'woocommerce-gateway-amazon-payments-advanced' ), $compatible_plugin ),
		);

		/**
		 * Only show currency list for plugins that will use the list. Frontend plugins will be exempt.
		 */
		if ( ! self::$compatible_instance->is_front_end_compatible() ) {
			$form_fields['currencies_supported'] = array(
				'title'             => __( 'Select currencies to display Amazon in your shop', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'              => 'multiselect',
				'options'           => WC_Amazon_Payments_Advanced_API::get_supported_currencies( true ),
				'css'               => 'height: auto;',
				'custom_attributes' => array(
					'size' => 10,
					'name' => 'currencies_supported',
				),
			);
		}

		return $form_fields;
	}

	/**
	 * Searches for the compatible plugin instance.
	 *
	 * @param boolean $init When true it will init the found compatible instance and assign it to self::$compatible_instance.
	 *                      When false it will just return the full class name of the compatible instance.
	 * @return string|WC_Amazon_Payments_Advanced_Multi_Currency_Abstract
	 */
	protected static function init_compatible_plugin_instance( $init = true ) {
		$found_plugin_instance = null;

		$compatible_plugin = self::compatible_plugin();
		if ( $compatible_plugin ) {
			require_once 'class-wc-amazon-payments-advanced-multi-currency-abstract.php';

			switch ( $compatible_plugin ) {
				case 'global_WOOCS':
					require_once 'class-wc-amazon-payments-advanced-multi-currency-woocs.php';
					$found_plugin_instance = $init ? new WC_Amazon_Payments_Advanced_Multi_Currency_Woocs() : WC_Amazon_Payments_Advanced_Multi_Currency_Woocs::class;
					break;
				case 'class_WC_Product_Price_Based_Country':
					require_once 'class-wc-amazon-payments-advanced-multi-currency-ppbc.php';
					$found_plugin_instance = $init ? new WC_Amazon_Payments_Advanced_Multi_Currency_PPBC() : WC_Amazon_Payments_Advanced_Multi_Currency_PPBC::class;
					break;
				case 'global_woocommerce_wpml':
					$wpml_settings = get_option( '_wcml_settings' );
					if ( ( WCML_MULTI_CURRENCIES_DISABLED !== $wpml_settings['enable_multi_currency'] ) ) {
						require_once 'class-wc-amazon-payments-advanced-multi-currency-wpml.php';
						$found_plugin_instance = $init ? new WC_Amazon_Payments_Advanced_Multi_Currency_WPML() : WC_Amazon_Payments_Advanced_Multi_Currency_WPML::class;
					}
					break;
				case 'class_WC_Currency_Converter':
					require_once 'class-wc-amazon-payments-advanced-multi-currency-wccw.php';
					$found_plugin_instance = $init ? new WC_Amazon_Payments_Advanced_Multi_Currency_WCCW() : WC_Amazon_Payments_Advanced_Multi_Currency_WCCW::class;
					break;
			}
			if ( $init ) {
				self::$compatible_instance = $found_plugin_instance;
			}
			return $found_plugin_instance;
		}
	}

	/**
	 * Determines current active currency based on the region and the supported multicurrency plugins.
	 *
	 * In the case that its not a supported multicurrency region but there is a supported multicurrency plugin
	 * enabled. We need to find out what's the selected currency is, because Amazon API does not support other currencies
	 * than JPY in Japan region and USD in United States region.
	 *
	 * @param string $currency WooCommerce's currency.
	 * @return string
	 */
	public static function get_active_currency( $currency ) {
		if ( self::is_active() || self::compatible_region() ) {
			return $currency;
		}

		$found_plugin_instance = self::init_compatible_plugin_instance( false );

		return ! is_null( $found_plugin_instance ) ? $found_plugin_instance::get_active_currency() : $currency;
	}
}

