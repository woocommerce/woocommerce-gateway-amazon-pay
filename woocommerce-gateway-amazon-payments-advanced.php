<?php
/**
 * Main class and core functions.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/*
 * Plugin Name: WooCommerce Amazon Pay
 * Plugin URI: https://woocommerce.com/products/pay-with-amazon/
 * Description: Amazon Pay is embedded directly into your existing web site, and all the buyer interactions with Amazon Pay and Login with Amazon take place in embedded widgets so that the buyer never leaves your site. Buyers can log in using their Amazon account, select a shipping address and payment method, and then confirm their order. Requires an Amazon Pay seller account and supports USA, UK, Germany, France, Italy, Spain, Luxembourg, the Netherlands, Sweden, Portugal, Hungary, Denmark, and Japan.
 * Version: 2.0.0
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 *
 * Text Domain: woocommerce-gateway-amazon-payments-advanced
 * Domain Path: /languages/
 * Tested up to: 5.5
 * WC tested up to: 4.4
 * WC requires at least: 2.6
 *
 * Copyright: © 2020 WooCommerce
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

define( 'WC_AMAZON_PAY_VERSION', '2.0.0' );

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
	 * Reference ID
	 *
	 * @var string
	 */
	private $reference_id;


	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Amazon Pay Gateway
	 *
	 * @var WC_Gateway_Amazon_Payments_Advanced
	 */
	private $gateway;

	/**
	 * WC logger instance.
	 *
	 * @var WC_Logger
	 */
	private $logger;

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
	 * Synchronous handler.
	 *
	 * @since 1.8.0
	 * @var WC_Amazon_Payments_Advanced_Synchronous_Handler
	 */
	public $synchro_handler;

	/**
	 * Simple Path handler.
	 *
	 * @var WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler
	 */
	public $onboarding_handler;

	/**
	 * API migration Status.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	public $api_migration;

	/**
	 * SDK config.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public $amazonpay_sdk_config;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version         = WC_AMAZON_PAY_VERSION;
		$this->path            = untrailingslashit( plugin_dir_path( __FILE__ ) );
		$this->plugin_url      = untrailingslashit( plugins_url( '/', __FILE__ ) );
		$this->plugin_basename = plugin_basename( __FILE__ );
		$this->get_migration_status();
		$this->includes_path   = $this->path . '/includes/';

		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-merchant-onboarding-handler.php';
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-api-abstract.php';

		include_once $this->includes_path . 'legacy/class-wc-amazon-payments-advanced-api-legacy.php';
		include_once $this->includes_path . 'class-wc-amazon-payments-advanced-api.php';

		include_once( $this->includes_path . 'class-wc-amazon-payments-advanced-compat.php' );
		include_once( $this->includes_path . 'class-wc-amazon-payments-advanced-ipn-handler.php' );
		include_once( $this->includes_path . 'class-wc-amazon-payments-advanced-synchronous-handler.php' );

		// On install hook.
		include_once( $this->includes_path . 'class-wc-amazon-payments-install.php' );
		register_activation_hook( __FILE__, array( 'WC_Amazon_Payments_Advanced_Install', 'install' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'woocommerce_init', array( $this, 'multicurrency_init' ), 0 );
		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 11 );

		// REST API support.
		add_action( 'rest_api_init', array( $this, 'rest_api_register_routes' ), 11 );
		add_filter( 'woocommerce_rest_prepare_shop_order', array( $this, 'rest_api_add_amazon_ref_info' ), 10, 2 );

		// IPN handler.
		$this->ipn_handler = new WC_Amazon_Payments_Advanced_IPN_Handler();
		// Synchronous handler.
		$this->synchro_handler = new WC_Amazon_Payments_Advanced_Synchronous_Handler();
		// Simple path registration endpoint.
		$this->onboarding_handler = new WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler();
	}

	/**
	 * Get API Migration status.
	 */
	public function get_migration_status( $fresh = false ) {
		if ( $fresh || empty( $this->api_migration ) ) {
			$status              = get_option( 'amazon_api_version' );
			$old_install         = version_compare( get_option( 'woocommerce_amazon_payments_new_install' ), '2.0.0', '>=' );
			$this->api_migration = 'V2' === $status || $old_install ? true : false;
		}
		return $this->api_migration;
	}

	/**
	 * Update migration status update
	 */
	public function update_migration_status() {
		update_option( 'amazon_api_version', 'V2' );
	}

	/**
	 * Downgrade migration status update
	 */
	public function delete_migration_status() {
		delete_option( 'amazon_api_version' );
	}

	public function get_settings( $fresh = false ) {
		if ( ! isset( $this->settings ) || $fresh ) {
			$this->settings = WC_Amazon_Payments_Advanced_API::get_settings();
		}
		return $this->settings;
	}

	/**
	 * Init.
	 *
	 * @since 1.6.0
	 */
	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->settings     = WC_Amazon_Payments_Advanced_API::get_settings();
		$this->reference_id = WC_Amazon_Payments_Advanced_API::get_reference_id();
		$this->access_token = WC_Amazon_Payments_Advanced_API::get_access_token();

		$this->compat = new WC_Amazon_Payments_Advanced_Compat();
		$this->compat->load_compats();

		$this->load_plugin_textdomain();
		if ( is_admin() ) {
			include_once( $this->includes_path . 'admin/class-wc-amazon-payments-advanced-admin.php' );
			$this->admin = new WC_Amazon_Payments_Advanced_Admin();
		}
		$this->init_gateway();
	}

	/**
	 * Set up API V2 SDK.
	 */
	public function get_amazonpay_sdk_config( $fresh = false ) {
		if ( $fresh || empty( $this->amazonpay_sdk_config ) ) {
			$this->settings             = WC_Amazon_Payments_Advanced_API::get_settings();
			$this->amazonpay_sdk_config = array(
				'public_key_id' => $this->settings['public_key_id'],
				'private_key'   => get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, false ),
				'sandbox'       => 'yes' === $this->settings['sandbox'] ? true : false,
				'region'        => $this->settings['payment_region'],
			);
		}
		return $this->amazonpay_sdk_config;
	}


	/**
	 * Multi-currency Init.
	 */
	public function multicurrency_init() {
		$this->compat = new WC_Amazon_Payments_Advanced_Compat();
		$this->compat->load_multicurrency();
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

		include_once( $this->includes_path . 'class-wc-gateway-amazon-payments-advanced.php' );
		include_once( $this->includes_path . 'class-wc-gateway-amazon-payments-advanced-privacy.php' );

		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
		$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' == $this->settings['subscriptions_enabled'];

		// Check for Subscriptions 2.0, and load support if found.
		if ( $subscriptions_installed && $subscriptions_enabled ) {

			include_once( $this->includes_path . 'class-wc-gateway-amazon-payments-advanced-subscriptions.php' );

			$this->gateway = new WC_Gateway_Amazon_Payments_Advanced_Subscriptions();

		} else {

			$this->gateway = new WC_Gateway_Amazon_Payments_Advanced();

		}

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	}

	/**
	 * Load handlers for cart and orders after WC Cart is loaded.
	 */
	public function init_handlers() {
		// Disable if no seller ID.
		if ( ! apply_filters( 'woocommerce_amazon_payments_init', true ) || empty( $this->settings['seller_id'] ) || 'no' == $this->settings['enabled'] ) {
			return;
		}
	}

	/**
	 * Checkout Button
	 *
	 * Triggered from the 'woocommerce_proceed_to_checkout' action.
	 */
	public function checkout_button() {
		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
		$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' == $this->settings['subscriptions_enabled'];
		$cart_contains_sub       = class_exists( 'WC_Subscriptions_Cart' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false;

		if ( $subscriptions_installed && ! $subscriptions_enabled && $cart_contains_sub ) {
			return;
		}

		echo '<div id="pay_with_amazon"></div>';
	}

	/**
	 * Checkout Message
	 */
	public function checkout_message() {
		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
		$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' == $this->settings['subscriptions_enabled'];
		$cart_contains_sub       = class_exists( 'WC_Subscriptions_Cart' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false;

		if ( $subscriptions_installed && ! $subscriptions_enabled && $cart_contains_sub ) {
			return;
		}

		echo '<div class="wc-amazon-checkout-message wc-amazon-payments-advanced-populated">';

		if ( empty( $this->reference_id ) && empty( $this->access_token ) ) {
			echo '<div class="woocommerce-info info wc-amazon-payments-advanced-info"><div id="pay_with_amazon"></div> ' . apply_filters( 'woocommerce_amazon_pa_checkout_message', __( 'Have an Amazon account?', 'woocommerce-gateway-amazon-payments-advanced' ) ) . '</div>';
		} else {
			$logout_url = $this->get_amazon_logout_url();
			$logout_msg_html = '<div class="woocommerce-info info">' . apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ) . ' <a href="' . esc_url( $logout_url ) . '" id="amazon-logout">' . __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a></div>';
			echo apply_filters( 'woocommerce_amazon_payments_logout_checkout_message_html', $logout_msg_html );
		}

		echo '</div>';

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

		return $methods;
	}

	/**
	 * Output the address widget HTML
	 */
	public function address_widget() {
		// Skip showing address widget for carts with virtual products only
		$show_address_widget = apply_filters( 'woocommerce_amazon_show_address_widget', WC()->cart->needs_shipping() );
		$hide_css_style      = ( ! $show_address_widget ) ? 'display: none;' : '';
		?>
		<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated">
			<div class="col2-set">
				<div class="col-1" style="<?php echo esc_attr( $hide_css_style ); ?>">
					<?php if ( 'skip' !== WC()->session->get( 'amazon_billing_agreement_details' ) ) : ?>
						<h3><?php esc_html_e( 'Shipping Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
						<div id="amazon_addressbook_widget"></div>
					<?php endif ?>
					<?php if ( ! empty( $this->reference_id ) ) : ?>
						<input type="hidden" name="amazon_reference_id" value="<?php echo esc_attr( $this->reference_id ); ?>" />
					<?php endif; ?>
					<?php if ( ! empty( $this->access_token ) ) : ?>
						<input type="hidden" name="amazon_access_token" value="<?php echo esc_attr( $this->access_token ); ?>" />
					<?php endif; ?>
				</div>
		<?php
	}

	/**
	 * Output the payment method widget HTML
	 */
	public function payment_widget() {
		$checkout = WC_Checkout::instance();
		?>
				<div class="col-2">
					<h3><?php _e( 'Payment Method', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
					<div id="amazon_wallet_widget"></div>
					<?php if ( ! empty( $this->reference_id ) ) : ?>
						<input type="hidden" name="amazon_reference_id" value="<?php echo esc_attr( $this->reference_id ); ?>" />
					<?php endif; ?>
					<?php if ( ! empty( $this->access_token ) ) : ?>
						<input type="hidden" name="amazon_access_token" value="<?php echo esc_attr( $this->access_token ); ?>" />
					<?php endif; ?>
					<?php if ( 'skip' === WC()->session->get( 'amazon_billing_agreement_details' ) ) : ?>
						<input type="hidden" name="amazon_billing_agreement_details" value="skip" />
					<?php endif; ?>
				</div>
				<?php if ( 'skip' !== WC()->session->get( 'amazon_billing_agreement_details' ) ) : ?>
					<div id="amazon_consent_widget" style="display: none;"></div>
				<?php endif; ?>

		<?php if ( ! is_user_logged_in() && $checkout->enable_signup ) : ?>

			<?php if ( $checkout->enable_guest_checkout ) : ?>

				<p class="form-row form-row-wide create-account">
					<input class="input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ) ?> type="checkbox" name="createaccount" value="1" /> <label for="createaccount" class="checkbox"><?php _e( 'Create an account?', 'woocommerce-gateway-amazon-payments-advanced' ); ?></label>
				</p>

			<?php endif; ?>

			<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

			<?php if ( ! empty( $checkout->checkout_fields['account'] ) ) : ?>

				<div class="create-account">

					<h3><?php _e( 'Create Account', 'woocommerce-gateway-amazon-payments-advanced' ); ?></h3>
					<p><?php _e( 'Create an account by entering the information below. If you are a returning customer please login at the top of the page.', 'woocommerce-gateway-amazon-payments-advanced' ); ?></p>

					<?php foreach ( $checkout->checkout_fields['account'] as $key => $field ) : ?>

						<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

					<?php endforeach; ?>

					<div class="clear"></div>

				</div>

			<?php endif; ?>

			<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>

		<?php endif; ?>
			</div>
		</div>

		<?php
	}

	/**
	 * Helper method to get a sanitized version of the site name.
	 *
	 * @return string
	 */
	public static function get_site_name() {
		// Get site setting for blog name.
		$site_name = get_bloginfo( 'name' );
		return self::sanitize_string($site_name);
	}

	/**
	 * Helper method to get a sanitized version of the site description.
	 *
	 * @return string
	 */
	public static function get_site_description() {
		// Get site setting for blog name.
		$site_description = get_bloginfo( 'description' );
		return self::sanitize_string( $site_description);
    }

	/**
     * Helper method to get a sanitized version of a string.
     *
	 * @param $string
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
	 * @param string $context Context for the log.
	 * @param string $message Log message.
	 */
	public function log( $context, $message ) {
		if ( empty( $this->settings['debug'] ) ) {
			return;
		}

		if ( 'yes' !== $this->settings['debug'] ) {
			return;
		}

		if ( ! is_a( $this->logger, 'WC_Logger' ) ) {
			$this->logger = new WC_Logger();
		}

		$log_message = $context . ' - ' . $message;

		$this->logger->add( 'woocommerce-gateway-amazon-payments-advanced', $log_message );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $log_message );
		}
	}

	/**
	 * Sanitize log message.
	 *
	 * Used to sanitize logged HTTP response message.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/133
	 * @since 1.6.0
	 *
	 * @param mixed $message Log message.
	 *
	 * @return string Sanitized log message.
	 */
	public function sanitize_remote_response_log( $message ) {
		if ( ! is_a( $message, 'SimpleXMLElement' ) ) {
			return (string) $message;
		}

		if ( ! is_callable( array( $message, 'asXML' ) ) ) {
			return '';
		}

		$message = $message->asXML();

		// Sanitize response message.
		$patterns    = array();
		$patterns[0] = '/(<Buyer>)(.+)(<\/Buyer>)/ms';
		$patterns[1] = '/(<PhysicalDestination>)(.+)(<\/PhysicalDestination>)/ms';
		$patterns[2] = '/(<BillingAddress>)(.+)(<\/BillingAddress>)/ms';
		$patterns[3] = '/(<SellerNote>)(.+)(<\/SellerNote>)/ms';
		$patterns[4] = '/(<AuthorizationBillingAddress>)(.+)(<\/AuthorizationBillingAddress>)/ms';
		$patterns[5] = '/(<SellerAuthorizationNote>)(.+)(<\/SellerAuthorizationNote>)/ms';
		$patterns[6] = '/(<SellerCaptureNote>)(.+)(<\/SellerCaptureNote>)/ms';
		$patterns[7] = '/(<SellerRefundNote>)(.+)(<\/SellerRefundNote>)/ms';

		$replacements    = array();
		$replacements[0] = '$1 REMOVED $3';
		$replacements[1] = '$1 REMOVED $3';
		$replacements[2] = '$1 REMOVED $3';
		$replacements[3] = '$1 REMOVED $3';
		$replacements[4] = '$1 REMOVED $3';
		$replacements[5] = '$1 REMOVED $3';
		$replacements[6] = '$1 REMOVED $3';
		$replacements[7] = '$1 REMOVED $3';

		return preg_replace( $patterns, $replacements, $message );
	}

	/**
	 * Sanitize logged request.
	 *
	 * Used to sanitize logged HTTP request message.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/133
	 * @since 1.6.0
	 *
	 * @param string $message Log message from stringified array structure.
	 *
	 * @return string Sanitized log message
	 */
	public function sanitize_remote_request_log( $message ) {
		$patterns    = array();
		$patterns[0] = '/(AWSAccessKeyId=)(.+)(&)/ms';
		$patterns[0] = '/(SellerNote=)(.+)(&)/ms';
		$patterns[1] = '/(SellerAuthorizationNote=)(.+)(&)/ms';
		$patterns[2] = '/(SellerCaptureNote=)(.+)(&)/ms';
		$patterns[3] = '/(SellerRefundNote=)(.+)(&)/ms';

		$replacements    = array();
		$replacements[0] = '$1REMOVED$3';
		$replacements[1] = '$1REMOVED$3';
		$replacements[2] = '$1REMOVED$3';
		$replacements[3] = '$1REMOVED$3';

		return preg_replace( $patterns, $replacements, $message );
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

		require_once( $this->includes_path . 'class-wc-amazon-payments-advanced-rest-api-controller.php' );

		WC()->api->WC_Amazon_Payments_Advanced_REST_API_Controller = new WC_Amazon_Payments_Advanced_REST_API_Controller();
		WC()->api->WC_Amazon_Payments_Advanced_REST_API_Controller->register_routes();
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
		if ( 'amazon_payments_advanced' === $response->data['payment_method'] ) {
			$response->data['amazon_reference'] = array(

				'amazon_reference_state'     => WC_Amazon_Payments_Advanced_API::get_order_ref_state( $post->ID, 'amazon_reference_state' ),
				'amazon_reference_id'        => get_post_meta( $post->ID, 'amazon_reference_id', true ),
				'amazon_authorization_state' => WC_Amazon_Payments_Advanced_API::get_order_ref_state( $post->ID, 'amazon_authorization_state' ),
				'amazon_authorization_id'    => get_post_meta( $post->ID, 'amazon_authorization_id', true ),
				'amazon_capture_state'       => WC_Amazon_Payments_Advanced_API::get_order_ref_state( $post->ID, 'amazon_capture_state' ),
				'amazon_capture_id'          => get_post_meta( $post->ID, 'amazon_capture_id', true ),
				'amazon_refund_ids'          => get_post_meta( $post->ID, 'amazon_refund_id', false ),
			);
		}

		return $response;
	}

	/**
	 * Get Amazon logout URL.
	 *
	 * @since 1.6.0
	 *
	 * @return string Amazon logout URL
	 */
	public function get_amazon_logout_url() {
		return add_query_arg(
			array(
				'amazon_payments_advanced' => 'true',
				'amazon_logout'            => 'true',
			),
			get_permalink( wc_get_page_id( 'checkout' ) )
		);
	}

	public function get_gateway() {
		return $this->gateway;
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
			break;
		default:
			$getter = array( $order, 'get_' . $key );
			return is_callable( $getter ) ? call_user_func( $getter ) : $order->{ $key };
	}
}

// Provides backward compatibility.
$GLOBALS['wc_amazon_payments_advanced'] = wc_apa();
