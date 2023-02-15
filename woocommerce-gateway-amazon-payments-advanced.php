<?php // phpcs:ignore
/*
 * Plugin Name: WooCommerce Amazon Pay
 * Plugin URI: https://woocommerce.com/products/pay-with-amazon/
 * Description: Amazon Pay is embedded directly into your existing web site, and all the buyer interactions with Amazon Pay and Login with Amazon take place in embedded widgets so that the buyer never leaves your site. Buyers can log in using their Amazon account, select a shipping address and payment method, and then confirm their order. Requires an Amazon Pay seller account and supports USA, UK, Germany, France, Italy, Spain, Luxembourg, the Netherlands, Sweden, Portugal, Hungary, Denmark, and Japan.
 * Version: 2.4.1
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Text Domain: woocommerce-gateway-amazon-payments-advanced
 * Domain Path: /languages/
 * Tested up to: 6.0
 * WC tested up to: 7.0
 * WC requires at least: 4.0
 *
 * Copyright: © 2023 WooCommerce
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WC_Gateway_Amazon_Pay
 */

define( 'WC_AMAZON_PAY_VERSION', '2.4.1' ); // WRCS: DEFINED_VERSION.
define( 'WC_AMAZON_PAY_VERSION_CV1', '1.13.1' );

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Amazon Pay main class
 */
class WC_Amazon_Payments_Advanced {

	/**
	 * Plugin's version.
	 *
	 * @since 1.6.0
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Plugin's absolute path.
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Plugin's includes path.
	 *
	 * @var string
	 */
	public $includes_path;
	/**
	 * Plugin's URL.
	 *
	 * @since 1.6.0
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Plugin basename.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $plugin_basename;

	/**
	 * Amazon Pay settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Amazon Pay Gateway
	 *
	 * @var WC_Gateway_Amazon_Payments_Advanced|WC_Gateway_Amazon_Payments_Advanced_Legacy
	 */
	private $gateway;

	/**
	 * Amazon Pay Express Gateway
	 *
	 * @var WC_Gateway_Amazon_Payments_Advanced_Express
	 */
	private $express_gateway = null;

	/**
	 * Amazon Pay Gateway Admin Class
	 *
	 * @var WC_Amazon_Payments_Advanced_Admin
	 */
	private $admin;

	/**
	 * Amazon Pay Gateway Subscriptions Class
	 *
	 * @var WC_Gateway_Amazon_Payments_Advanced_Subscriptions
	 */
	private $subscriptions;

	/**
	 * WC logger instance.
	 *
	 * @var WC_Logger
	 */
	private $logger;

	/**
	 * Logger prefix for the whole transaction
	 *
	 * @var string
	 */
	private $logger_prefix;

	/**
	 * Amazon Pay compat handler.
	 *
	 * @since 1.6.0
	 * @var WC_Amazon_Payments_Advanced_Compat
	 */
	private $compat;

	/**
	 * IPN handler.
	 *
	 * @since 1.8.0
	 * @var WC_Amazon_Payments_Advanced_IPN_Handler
	 */
	public $ipn_handler;

	/**
	 * Simple Path handler.
	 *
	 * @var WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler
	 */
	public $onboarding_handler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version         = WC_AMAZON_PAY_VERSION;
		$this->path            = untrailingslashit( plugin_dir_path( __FILE__ ) );
		$this->plugin_url      = untrailingslashit( plugins_url( '/', __FILE__ ) );
		$this->plugin_basename = plugin_basename( __FILE__ );
		$this->includes_path   = $this->path . '/includes/';

		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-merchant-onboarding-handler.php';
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-api-abstract.php';

		include_once $this->includes_path . 'legacy/class-wc-amazon-payments-advanced-api-legacy.php';
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-api.php';

		// Utils.
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-utils.php';

		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-compat.php';
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-ipn-handler-abstract.php';
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-ipn-handler.php';
		include_once $this->includes_path . 'legacy/class-wc-amazon-payments-advanced-ipn-handler-legacy.php';

		// On install hook.
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-install.php';
		register_activation_hook( __FILE__, array( 'WC_Amazon_Payments_Advanced_Install', 'install' ) );

		/* Amazon Blocks */
		include_once $this->includes_path . 'blocks/class-wc-amazon-payments-advanced-register-blocks.php';
		new WC_Amazon_Payments_Advanced_Register_Blocks();

		add_action( 'woocommerce_init', array( $this, 'init' ) );

		// REST API support.
		add_action( 'rest_api_init', array( $this, 'rest_api_register_routes' ), 11 );
		add_filter( 'woocommerce_rest_prepare_shop_order', array( $this, 'rest_api_add_amazon_ref_info' ), 10, 2 );

		// IPN handler.
		$this->ipn_handler = new WC_Amazon_Payments_Advanced_IPN_Handler();
		new WC_Amazon_Payments_Advanced_IPN_Handler_Legacy(); // TODO: Maybe register legacy hooks differently
		// Simple path registration endpoint.
		$this->onboarding_handler = new WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler();
		// Third party compatibilities.
		$this->compat = new WC_Amazon_Payments_Advanced_Compat();
	}

	/**
	 * Init.
	 *
	 * @since 1.6.0
	 */
	public function init() {
		$this->settings = WC_Amazon_Payments_Advanced_API::get_settings();

		$this->load_plugin_textdomain();
		if ( is_admin() ) {
			include_once $this->includes_path . 'admin/class-wc-amazon-payments-advanced-admin.php';
			$this->admin = new WC_Amazon_Payments_Advanced_Admin();
		}
		$this->init_gateway();

		do_action( 'woocommerce_amazon_pa_init' );
	}

	/**
	 * Load translations.
	 *
	 * @since 1.6.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'woocommerce-gateway-amazon-payments-advanced', false, dirname( $this->plugin_basename ) . '/languages' );
	}

	/**
	 * Init gateway
	 */
	public function init_gateway() {

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		include_once $this->includes_path . 'class-wc-gateway-amazon-payments-advanced-abstract.php';
		include_once $this->includes_path . 'class-wc-gateway-amazon-payments-advanced-privacy.php';

		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );

		// Check for Subscriptions 2.0, and load support if found.
		if ( $subscriptions_installed ) {

			include_once $this->includes_path . 'class-wc-gateway-amazon-payments-advanced-subscriptions.php';
			include_once $this->includes_path . 'legacy/class-wc-gateway-amazon-payments-advanced-subscriptions-legacy.php';

			$this->subscriptions = new WC_Gateway_Amazon_Payments_Advanced_Subscriptions();
			new WC_Gateway_Amazon_Payments_Advanced_Subscriptions_Legacy();

		}

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

		include_once $this->includes_path . 'legacy/class-wc-gateway-amazon-payments-advanced-legacy.php';
		if ( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			include_once $this->includes_path . 'class-wc-gateway-amazon-payments-advanced.php';
			$this->gateway = new WC_Gateway_Amazon_Payments_Advanced();
			WC_Gateway_Amazon_Payments_Advanced_Legacy::legacy_hooks();
		} else {
			$this->gateway = new WC_Gateway_Amazon_Payments_Advanced_Legacy();
		}
		$this->gateway->gateway_settings_init();

		/* Enable Alexa Notifications support based on Gateway's option. */
		if ( ! empty( $this->settings['alexa_notifications_support'] ) && 'yes' === $this->settings['alexa_notifications_support'] ) {
			include_once $this->includes_path . 'class-wc-amazon-payments-advanced-alexa-notifications.php';
			new WC_Amazon_Payments_Advanced_Alexa_Notifications();
		}
	}

	/**
	 * Add Amazon gateway to WC.
	 *
	 * @param array $methods List of payment methods.
	 *
	 * @return array List of payment methods.
	 */
	public function add_gateway( $methods ) {
		$methods[] = $this->gateway;

		if ( $this->should_express_be_loaded() ) {
			require_once $this->includes_path . 'class-wc-gateway-amazon-payments-advanced-express.php';
			$this->express_gateway = new WC_Gateway_Amazon_Payments_Advanced_Express();
			$methods[]             = $this->express_gateway;
		}

		return $methods;
	}

	/**
	 * Helper method to get a sanitized version of the site name.
	 *
	 * @return string
	 */
	public static function get_site_name() {
		// Get site setting for blog name.
		$site_name = get_bloginfo( 'name' );
		return self::sanitize_string( $site_name );
	}

	/**
	 * Helper method to get a sanitized version of the site description.
	 *
	 * @return string
	 */
	public static function get_site_description() {
		// Get site setting for blog name.
		$site_description = get_bloginfo( 'description' );
		return self::sanitize_string( $site_description );
	}

	/**
	 * Helper method to format a number before sending over to Amazon.
	 *
	 * @param string|float|int $num           Amount to format.
	 * @param null|int         $decimals      The amount of decimals the formatted number should have.
	 * @param string           $decimals_sep  The separator of the decimals.
	 * @param string           $thousands_sep The separator of thousands.
	 * @return string
	 */
	public static function format_amount( $num, $decimals = null, $decimals_sep = '.', $thousands_sep = '' ) {
		/* Amazon won't accept any decimals more than 2. */
		$decimals = $decimals > 2 ? null : $decimals;
		$decimals = $decimals ? $decimals : min( wc_get_price_decimals(), 2 );
		return number_format( $num, $decimals, $decimals_sep, $thousands_sep );
	}

	/**
	 * Helper method to get order Version.
	 *
	 * @param int     $order_id Order ID.
	 * @param boolean $force   Wether to force version to be v2 or not.
	 *
	 * @return string
	 */
	public static function get_order_version( $order_id, $force = true ) {
		if ( $force && WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			return 'v2';
		}
		$order   = wc_get_order( $order_id );
		$version = version_compare( $order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';
		return $version;
	}

	/**
	 * Helper method to get order Version.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string
	 */
	public static function get_order_charge_permission( $order_id ) {
		$order                = wc_get_order( $order_id );
		$charge_permission_id = $order->get_meta( 'amazon_charge_permission_id' );
		if ( empty( $charge_permission_id ) ) {
			// For the subscriptions created on versions previous V2 we update the meta.
			$charge_permission_id = $order->get_meta( 'amazon_billing_agreement_id' );
			if ( empty( $charge_permission_id ) ) {
				// For the orders created on versions previous V2 we update the meta.
				$charge_permission_id = $order->get_meta( 'amazon_reference_id' );
			}
			$order->update_meta_data( 'amazon_charge_permission_id', $charge_permission_id );
			$order->save();
		}
		return $charge_permission_id;
	}

	/**
	 * Helper method to get order Version.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string
	 */
	public static function get_order_charge_id( $order_id ) {
		$order     = wc_get_order( $order_id );
		$charge_id = $order->get_meta( 'amazon_charge_id' );
		if ( empty( $charge_id ) ) {
			// For the orders created on versions previous V2 we get the equivalent meta.
			$charge_id = $order->get_meta( 'amazon_capture_id' );
			if ( empty( $charge_id ) ) {
				// For the orders created on versions previous V2 with pending capture
				// we adapt the existing meta.
				$authorization_id = $order->get_meta( 'amazon_authorization_id' );
				$charge_id        = str_replace( '-A', '-C', $authorization_id );
			}
			// For the orders created on versions previous V2 we update the meta.
			$order->update_meta_data( 'amazon_charge_id', $charge_id );
			$order->save();
		}

		return $charge_id;
	}

	/**
	 * Helper method to get a sanitized version of a string.
	 *
	 * @param string $string Sanitize some elements.
	 *
	 * @return string
	 */
	protected static function sanitize_string( $string ) {
		// Decode HTML entities.
		$string = wp_specialchars_decode( $string, ENT_QUOTES );

		// ASCII-ify accented characters.
		$string = remove_accents( $string );

		// Remove non-printable characters.
		$string = preg_replace( '/[[:^print:]]/', '', $string );

		// Clean up leading/trailing whitespace.
		$string = trim( $string );

		return $string;
	}

	/**
	 * Write a message to log if we're in "debug" mode.
	 *
	 * @since 1.6.0
	 *
	 * @param string|WP_Error $message Log message.
	 * @param null|mixed      $object Data to be printed for more detail about the entry.
	 * @param null|string     $context Context for the log.
	 */
	public function log( $message, $object = null, $context = null ) {
		if ( empty( $this->settings['debug'] ) ) {
			return;
		}

		if ( 'yes' !== $this->settings['debug'] ) {
			return;
		}

		if ( ! is_a( $this->logger, 'WC_Logger' ) ) {
			$this->logger = new WC_Logger();
		}

		if ( is_wp_error( $message ) ) {
			$error_data = $message->get_error_data();
			if ( ! is_null( $error_data ) ) {
				$object = $error_data;
			}
			$message = $message->get_error_message();
		}

		if ( empty( $context ) ) {
			$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			array_shift( $backtrace ); // drop current.

			$context = isset( $backtrace[0]['function'] ) ? $backtrace[0]['function'] : '';

			if ( isset( $backtrace[0]['class'] ) ) {
				$context = $backtrace[0]['class'] . '::' . $context;
			}
		}

		$log_message = $context . ' - ' . $message;

		if ( ! is_null( $object ) ) {
			if ( is_array( $object ) || is_object( $object ) ) {
				$log_message .= "\n";
				$log_message .= wp_json_encode( $object, JSON_PRETTY_PRINT );
			} elseif ( is_string( $object ) || is_numeric( $object ) ) {
				$log_message .= ' | ' . $object;
			} else {
				$log_message .= ' | ' . var_export( $object, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			}
		}

		if ( ! isset( $this->logger_prefix ) ) {
			$this->logger_prefix = wp_generate_password( 6, false, false );
		}

		$log_message = $this->logger_prefix . ' - ' . $log_message;

		$this->logger->add( 'woocommerce-gateway-amazon-payments-advanced', $log_message );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Register REST API route for /orders/<order-id>/amazon-payments-advanced/.
	 *
	 * @since 1.6.0
	 */
	public function rest_api_register_routes() {
		// Check to make sure WC is activated and its REST API were loaded
		// first.
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		if ( ! isset( WC()->api ) ) {
			return;
		}
		if ( ! is_a( WC()->api, 'WC_API' ) ) {
			return;
		}

		require_once $this->includes_path . 'class-wc-amazon-payments-advanced-rest-api-controller.php';

		$api_implementation = new WC_Amazon_Payments_Advanced_REST_API_Controller();
		$api_implementation->register_routes();
	}

	/**
	 * Add Amazon reference information in order item response.
	 *
	 * @since 1.6.0
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param WP_Post          $post     Post object.
	 *
	 * @return WP_REST_Response REST response
	 */
	public function rest_api_add_amazon_ref_info( $response, $post ) {
		if ( 'amazon_payments_advanced' !== $response->data['payment_method'] ) {
			return $response;
		}

		$order = wc_get_order( $post->ID );

		if ( ! ( $order instanceof \WC_Order ) ) {
			return $response;
		}

		$response->data['amazon_reference'] = array(
			'amazon_reference_state'     => WC_Amazon_Payments_Advanced_API_Legacy::get_order_ref_state( $post->ID, 'amazon_reference_state' ),
			'amazon_reference_id'        => $order->get_meta( 'amazon_reference_id', true, 'edit' ),
			'amazon_authorization_state' => WC_Amazon_Payments_Advanced_API_Legacy::get_order_ref_state( $post->ID, 'amazon_authorization_state' ),
			'amazon_authorization_id'    => $order->get_meta( 'amazon_authorization_id', true, 'edit' ),
			'amazon_capture_state'       => WC_Amazon_Payments_Advanced_API_Legacy::get_order_ref_state( $post->ID, 'amazon_capture_state' ),
			'amazon_capture_id'          => $order->get_meta( 'amazon_capture_id', true, 'edit' ),
			'amazon_refund_ids'          => $order->get_meta( 'amazon_refund_id', false, 'edit' ),
		);

		return $response;
	}

	/**
	 * Return instance of WC_Gateway_Amazon_Payments_Advanced.
	 *
	 * @since 2.0.0
	 *
	 * @return WC_Gateway_Amazon_Payments_Advanced|WC_Gateway_Amazon_Payments_Advanced_Legacy
	 */
	public function get_gateway() {
		return $this->gateway;
	}

	/**
	 * Return instance of WC_Gateway_Amazon_Payments_Advanced_Express.
	 *
	 * @return WC_Gateway_Amazon_Payments_Advanced_Express
	 */
	public function get_express_gateway() {
		return $this->express_gateway;
	}

	/**
	 * Checks whether Express Gateway should be loaded or not.
	 *
	 * Express gateway should only be loaded if:
	 *
	 * 1) Merchant has migrated to V2 of the Amazon API and as a result
	 *    he is using the WC_Gateway_Amazon_Payments_Advanced class as the
	 *    Amazon Pay main Gateway.
	 * 2) Class WC_Gateway_Amazon_Payments_Advanced has been loaded.
	 * 3) WooCommerce blocks is installed and activated
	 *
	 * @return boolean
	 */
	public function should_express_be_loaded() {
		return WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() && class_exists( 'WC_Gateway_Amazon_Payments_Advanced' ) && class_exists( 'Automattic\WooCommerce\Blocks\Package' );
	}
}

/**
 * Return instance of WC_Amazon_Payments_Advanced.
 *
 * @since 1.6.0
 *
 * @return WC_Amazon_Payments_Advanced
 */
function wc_apa() {
	static $plugin;

	if ( ! isset( $plugin ) ) {
		$plugin = new WC_Amazon_Payments_Advanced();
	}

	return $plugin;
}

/**
 * Get order property with compatibility for WC lt 3.0.
 *
 * @since 1.7.0
 *
 * @param WC_Order $order Order object.
 * @param string   $key   Order property.
 *
 * @return mixed Value of order property.
 */
function wc_apa_get_order_prop( $order, $key ) {
	switch ( $key ) {
		case 'order_currency':
			return is_callable( array( $order, 'get_currency' ) ) ? $order->get_currency() : $order->get_order_currency();
		default:
			$getter = array( $order, 'get_' . $key );
			return is_callable( $getter ) ? call_user_func( $getter ) : $order->{ $key };
	}
}

// Provides backward compatibility.
$GLOBALS['wc_amazon_payments_advanced'] = wc_apa();
