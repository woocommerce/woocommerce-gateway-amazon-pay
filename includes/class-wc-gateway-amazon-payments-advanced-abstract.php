<?php
/**
 * Abstract Gateway Class with common implementations between v1 and v2
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Gateway_Amazon_Payments_Advanced_Abstract
 */
abstract class WC_Gateway_Amazon_Payments_Advanced_Abstract extends WC_Payment_Gateway {

	/**
	 * ISO region codes for JAPAN mapped to Japanese.
	 *
	 * Used to pass the region in Japanese to the Amazon API.
	 *
	 * Specifically requested by Amazon's JP Team.
	 */
	const JP_REGION_CODE_MAP = array(
		'JP01' => '北海道',
		'JP02' => '青森県',
		'JP03' => '岩手県',
		'JP04' => '宮城県',
		'JP05' => '秋田県',
		'JP06' => '山形県',
		'JP07' => '福島県',
		'JP08' => '茨城県',
		'JP09' => '栃木県',
		'JP10' => '群馬県',
		'JP11' => '埼玉県',
		'JP12' => '千葉県',
		'JP13' => '東京都',
		'JP14' => '神奈川県',
		'JP15' => '新潟県',
		'JP16' => '富山県',
		'JP17' => '石川県',
		'JP18' => '福井県',
		'JP19' => '山梨県',
		'JP20' => '長野県',
		'JP21' => '岐阜県',
		'JP22' => '静岡県',
		'JP23' => '愛知県',
		'JP24' => '三重県',
		'JP25' => '滋賀県',
		'JP26' => '京都府',
		'JP27' => '大阪府',
		'JP28' => '兵庫県',
		'JP29' => '奈良県',
		'JP30' => '和歌山県',
		'JP31' => '鳥取県',
		'JP32' => '島根県',
		'JP33' => '岡山県',
		'JP34' => '広島県',
		'JP35' => '山口県',
		'JP36' => '徳島県',
		'JP37' => '香川県',
		'JP38' => '愛媛県',
		'JP39' => '高知県',
		'JP40' => '福岡県',
		'JP41' => '佐賀県',
		'JP42' => '長崎県',
		'JP43' => '熊本県',
		'JP44' => '大分県',
		'JP45' => '宮崎県',
		'JP46' => '鹿児島県',
		'JP47' => '沖縄県',
	);

	/**
	 * Amazon Private Key
	 *
	 * @var string
	 */
	protected $private_key;

	/**
	 * Debug enabled status
	 *
	 * @var bool
	 */
	protected $debug;

	/**
	 * Payment Region
	 *
	 * @var string
	 */
	protected $payment_region;

	/**
	 * Merchant ID
	 *
	 * @var string
	 */
	protected $merchant_id;

	/**
	 * Store ID
	 *
	 * @var string
	 */
	protected $store_id;

	/**
	 * Public Key ID
	 *
	 * @var string
	 */
	protected $public_key_id;

	/**
	 * Sandbox Environment
	 *
	 * @var string
	 */
	protected $sandbox;

	/**
	 * Capture Mode
	 *
	 * @var string
	 */
	protected $payment_capture;

	/**
	 * Authorization Mode
	 *
	 * @var string
	 */
	protected $authorization_mode;

	/**
	 * Redirect Authentication (v1)
	 *
	 * @var string
	 */
	protected $redirect_authentication;

	/**
	 * Seller ID (v1)
	 *
	 * @var string
	 */
	protected $seller_id;

	/**
	 * App Client ID (v1)
	 *
	 * @var string
	 */
	protected $app_client_id;

	/**
	 * MWS Access Key (v1)
	 *
	 * @var string
	 */
	protected $mws_access_key;

	/**
	 * MWS Secret Key (v1)
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * Enable Login App (v1)
	 *
	 * @var string
	 */
	protected $enable_login_app;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->method_title         = __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' );
		$this->method_description   = __( 'Amazon Pay is embedded directly into your existing web site, and all the buyer interactions with Amazon Pay and Login with Amazon take place in embedded widgets so that the buyer never leaves your site. Buyers can log in using their Amazon account, select a shipping address and payment method, and then confirm their order. Requires an Amazon Pay seller account and supports USA, UK, Germany, France, Italy, Spain, Luxembourg, the Netherlands, Sweden, Portugal, Hungary, Denmark, and Japan.', 'woocommerce-gateway-amazon-payments-advanced' );
		$this->id                   = 'amazon_payments_advanced';
		$this->icon                 = apply_filters( 'woocommerce_amazon_pa_logo', wc_apa()->plugin_url . '/assets/images/amazon-payments.png' );
		$this->view_transaction_url = $this->get_transaction_url_format();
		$this->supports             = array(
			'products',
			'refunds',
		);
		$this->supports             = apply_filters( 'woocommerce_amazon_pa_supports', $this->supports, $this );
		$this->private_key          = get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'validate_api_keys' ) );

		add_action( 'woocommerce_amazon_checkout_init', array( $this, 'checkout_init_common' ) );

	}

	/**
	 * Gateway Settings Init
	 *
	 * @since 2.3.4
	 */
	public function gateway_settings_init() {
		// Load the settings.
		$this->init_settings();

		// Load saved settings.
		$this->load_settings();

		// Set Debug option.
		$this->debug = ( 'yes' === $this->get_option( 'debug' ) );
	}

	/**
	 * Get Amazon logout URL.
	 *
	 * @since 1.6.0
	 * @param  string $url URL to logout from.
	 *
	 * @return string Amazon logout URL
	 */
	public function get_amazon_logout_url( $url = null ) {
		if ( empty( $url ) ) {
			$url = get_permalink( wc_get_page_id( 'checkout' ) );
		}
		if ( empty( $url ) ) {
			$url = trailingslashit( home_url() );
		}
		return add_query_arg(
			array(
				'amazon_payments_advanced' => 'true',
				'amazon_logout'            => 'true',
			),
			$url
		);
	}

	/**
	 * Get Amazon Payments Checkout URL
	 *
	 * @return string
	 */
	public function get_amazon_payments_checkout_url() {
		$url = get_permalink( wc_get_page_id( 'checkout' ) );
		if ( empty( $url ) ) {
			$url = trailingslashit( home_url() );
		}
		$url = add_query_arg( array( 'amazon_payments_advanced' => 'true' ), $url );
		return $url;
	}

	/**
	 * Remove Amazon Payments Checkout URL from the current URL
	 *
	 * @return string
	 */
	public function get_amazon_payments_clean_logout_url() {
		$url = add_query_arg(
			array(
				'amazon_payments_advanced' => 'true',
				'amazon_logout'            => false,
			)
		);
		return $url;
	}

	/**
	 * Get transaction URL format.
	 *
	 * @since 1.6.0
	 *
	 * @return string URL format
	 */
	public function get_transaction_url_format() {
		$url = 'https://sellercentral.amazon.com';

		$eu_countries = WC()->countries->get_european_union_countries();
		$base_country = WC()->countries->get_base_country();

		if ( in_array( $base_country, $eu_countries, true ) ) {
			$url = 'https://sellercentral-europe.amazon.com';
		} elseif ( 'JP' === $base_country ) {
			$url = 'https://sellercentral-japan.amazon.com';
		}

		$url .= '/hz/me/pmd/payment-details?orderReferenceId=%s';

		return apply_filters( 'woocommerce_amazon_pa_transaction_url_format', $url );
	}

	/**
	 * Define user set variables.
	 */
	public function load_settings() {
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();

		$this->title                   = $settings['title'];
		$this->description             = $settings['description'];
		$this->payment_region          = $settings['payment_region'];
		$this->merchant_id             = $settings['merchant_id'];
		$this->store_id                = $settings['store_id'];
		$this->public_key_id           = $settings['public_key_id'];
		$this->seller_id               = $settings['seller_id'];
		$this->mws_access_key          = $settings['mws_access_key'];
		$this->secret_key              = $settings['secret_key'];
		$this->enable_login_app        = $settings['enable_login_app'];
		$this->app_client_id           = $settings['app_client_id'];
		$this->sandbox                 = $settings['sandbox'];
		$this->payment_capture         = $settings['payment_capture'];
		$this->authorization_mode      = $settings['authorization_mode'];
		$this->redirect_authentication = $settings['redirect_authentication'];
	}

	/**
	 * Init payment gateway form fields
	 */
	public function get_form_fields() {

		$login_app_setup_url = WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url();
		/* translators: Login URL */
		$label_format           = __( 'This option makes the plugin to work with the latest API from Amazon, this will enable support for Subscriptions and make transactions more securely. <a href="%s" target="_blank">You must create a Login with Amazon App to be able to use this option.</a>', 'woocommerce-gateway-amazon-payments-advanced' );
		$label_format           = wp_kses(
			$label_format,
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
				),
			)
		);
		$enable_login_app_label = sprintf( $label_format, $login_app_setup_url );
		$redirect_url           = $this->get_amazon_payments_checkout_url();
		$valid                  = isset( $this->settings['amazon_keys_setup_and_validated'] ) ? $this->settings['amazon_keys_setup_and_validated'] : false;

		$button_desc = __( 'Register for a new Amazon Pay merchant account, or sign in with your existing Amazon Pay Seller Central credentials to complete the plugin upgrade and configuration', 'woocommerce-gateway-amazon-payments-advanced' );
		$button_btn  = '<a class="register_now button-primary">' . __( 'Connect to Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>';
		if ( $this->private_key ) {
			$button_desc = __( 'In order to connect to a different account you need to disconect first, this will delete current Account Settings, you will need to go throught all the configuration process again', 'woocommerce-gateway-amazon-payments-advanced' );
			$button_btn  = '<a class="delete-settings button-primary">' . __( 'Disconnect Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>';
		}
		if ( ! WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			$button_desc = __( 'In order to keep using Amazon Pay, you need to reconnect to your account.', 'woocommerce-gateway-amazon-payments-advanced' );
			$button_btn  = '<a class="register_now button-primary">' . __( 'Reconnect to Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>';
		}

		$this->form_fields = array(
			'important_note'                => array(
				'title'       => __( 'Important note, before you sign up:', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'title',
				'description' => __( 'Before you start the registration, make sure you sign out of all Amazon accounts you might have. Use an email address that you have never used for any Amazon account.   If you have an Amazon Seller account (Selling on Amazon), sign out and use a different address to register your Amazon Payments account.', 'woocommerce-gateway-amazon-payments-advanced' ),
			),
			'payment_region'                => array(
				'title'       => __( 'Payment Region', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'select',
				'description' => '',
				'default'     => WC_Amazon_Payments_Advanced_API::get_payment_region_from_country( WC()->countries->get_base_country() ),
				'options'     => WC_Amazon_Payments_Advanced_API::get_payment_regions(),
			),
			'register_now'                  => array(
				'title'       => __( 'Connect your Amazon Pay merchant account', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'title',
				'description' => $button_desc . '<br/><br/>' . $button_btn,
			),
			'enabled'                       => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'title'                         => array(
				'title'       => __( 'Title', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
			),
			'description'                   => array(
				'title'       => __( 'Description', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => __( 'Complete your payment using Amazon Pay!', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
			),
			'account_details'               => array(
				'title'       => __( 'Amazon Pay Merchant account details', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'title',
				'description' => '',
			),
			'manual_notice'                 => array(
				'type' => 'custom',
				'html' => '<p>Problems with automatic setup? <a href="#" class="wcapa-toggle-section" data-toggle="#manual-settings-container, #automatic-settings-container">Click here</a> to manually enter your keys.</p>',
			),
			'manual_container_start'        => array(
				'type' => 'custom',
				'html' => '<div id="manual-settings-container" class="hidden">',
			),
			'keys_json'                     => array(
				'title'       => __( 'Manual Keys JSON', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'file',
				'description' => __( 'JSON format, retrieve the JSON clicking the "Download JSON file" button in Seller Central under "INTEGRATION- Central - Existing API keys', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'private_key'                   => array(
				'title'       => __( 'Private Key File', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Add .pem file with the private key generated in the Amazon seller Central', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'file',
				'description' => __( 'This key is created automatically when you Connect your Amazon Pay merchant account form the Configure button, but can be created by logging into Seller Central and create keys in INTEGRATION - Central', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
			),
			'manual_container_end'          => array(
				'type' => 'custom',
				'html' => '</div>',
			),
			'default_container_start'       => array(
				'type' => 'custom',
				'html' => '<div id="automatic-settings-container">',
			),
			'merchant_id'                   => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'text',
				'description' => __( 'Obtained from your Amazon account. Usually found on Integration central after logging into your merchant account.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'store_id'                      => array(
				'title'       => __( 'Store ID', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'text',
				'description' => __( 'Obtained from your Amazon account. Usually found on Integration central after logging into your merchant account.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'public_key_id'                 => array(
				'title'       => __( 'Public Key Id', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'text',
				'description' => __( 'Obtained from your Amazon account. You can get these keys by logging into Seller Central and clicking the "See Details" button under INTEGRATION - Central - Existing API keys.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'default_container_end'         => array(
				'type' => 'custom',
				'html' => '</div>',
			),
			'sandbox'                       => array(
				'title'       => __( 'Use Sandbox', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable sandbox mode during testing and development - live payments will not be taken if enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'no',
				'options'     => array(
					'yes' => __( 'Yes', 'woocommerce-gateway-amazon-payments-advanced' ),
					'no'  => __( 'No', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'advanced_configuration'        => array(
				'title'       => __( 'Advanced configurations', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: Merchant URL to copy and paste */
					__( 'In order to get the quickest updates while working with Async mode, complete your Seller Central configuration on plugin setup. From your seller Central home page, go to “Settings – Integration Settings”. In this page, please paste the URL below to the “Merchant URL” input field. <br /><code>%1$s</code>', 'woocommerce-gateway-amazon-payments-advanced' ),
					wc_apa()->ipn_handler->get_notify_url()
				),
			),
			'payment_capture'               => array(
				'title'       => __( 'Payment Capture', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'select',
				'description' => '',
				'default'     => '',
				'options'     => array(
					''          => __( 'Authorize and Capture the payment when the order is placed.', 'woocommerce-gateway-amazon-payments-advanced' ),
					'authorize' => __( 'Authorize the payment when the order is placed.', 'woocommerce-gateway-amazon-payments-advanced' ),
					'manual'    => __( 'Don’t Authorize the payment when the order is placed (i.e. for pre-orders).', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'authorization_mode'            => array(
				'title'       => __( 'Authorization processing mode', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'select',
				'description' => '',
				'default'     => 'sync',
				'options'     => array(
					'sync'  => __( 'Synchronous', 'woocommerce-gateway-amazon-payments-advanced' ),
					'async' => __( 'Asynchronous', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'display_options'               => array(
				'title'       => __( 'Display options', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'Customize the appearance of Amazon widgets.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'title',
			),
			'button_language'               => array(
				'title'       => __( 'Button language', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'Language to use in Login with Amazon or a Amazon Pay button. Only used when Login with Amazon App is enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'default'     => '',
				'options'     => array(
					''      => __( 'Detect from buyer\'s browser', 'woocommerce-gateway-amazon-payments-advanced' ),
					'en-US' => __( 'US English', 'woocommerce-gateway-amazon-payments-advanced' ),
					'en-GB' => __( 'UK English', 'woocommerce-gateway-amazon-payments-advanced' ),
					'de-DE' => __( 'Germany\'s German', 'woocommerce-gateway-amazon-payments-advanced' ),
					'fr-FR' => __( 'France\'s French', 'woocommerce-gateway-amazon-payments-advanced' ),
					'it-IT' => __( 'Italy\'s Italian', 'woocommerce-gateway-amazon-payments-advanced' ),
					'es-ES' => __( 'Spain\'s Spanish', 'woocommerce-gateway-amazon-payments-advanced' ),
					'ja-JP' => __( 'Japan\'s Japanese', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'button_color'                  => array(
				'title'       => __( 'Button color', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'Button color to display on cart and checkout pages. Only used when Login with Amazon App is enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'default'     => 'Gold',
				'options'     => array(
					'Gold'      => __( 'Gold', 'woocommerce-gateway-amazon-payments-advanced' ),
					'LightGray' => __( 'Light gray', 'woocommerce-gateway-amazon-payments-advanced' ),
					'DarkGray'  => __( 'Dark gray', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'hide_standard_checkout_button' => array(
				'title'       => __( 'Standard checkout button', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'Hide standard checkout button on chart page. Only applies when there are not other gateways enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'label'       => __( 'Hide standard checkout button on cart page', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => 'no',
			),
			'misc_options'                  => array(
				'title' => __( 'Miscellaneous', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'  => 'title',
			),
			'debug'                         => array(
				'title'       => __( 'Debug', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable debugging messages', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'checkbox',
				'description' => __( 'Sends debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			),
			'hide_button_mode'              => array(
				'title'       => __( 'Hide Button Mode', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable hide button mode', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'This will hides Amazon buttons on the mini-cart and on cart, checkout and product pages so the gateway looks not available to the customers. It will not hide the classic integration, if it\'s enabled. The buttons are hidden via CSS. Only enable this when troubleshooting your integration.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'enable_classic_gateway'        => array(
				'title'       => __( 'Classic Gateway', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable Amazon Pay as a classic Gateway Option', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'This will enable Amazon Pay to also appear along other Gateway Options. Compatible with the Checkout Block of WooCommerce Blocks.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'yes',
			),
			'using_woo_blocks'              => array(
				'title'       => __( 'WooCommerce Blocks', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Are you using WooCommerce Blocks for your checkout page?', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'Compatibility with WooCommerce blocks should work fine out of the box. This option for the time being ensures compatibility only when using WooCommerce Blocks Checkout without the "Classic" Amazon Gateway enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'mini_cart_button'              => array(
				'title'       => __( 'Amazon Pay on mini cart', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable Amazon Pay on mini cart', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'This will only work if you are using WooCommerce\'s mini cart. If you enable it and the Amazon Pay does not show please disable since it also enables loading of required assets globally in your frontend. Compatible with WooCommerce Blocks.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'product_button'                => array(
				'title'       => __( 'Amazon Pay on product pages', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable Amazon Pay on product pages', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'This will enable the Amazon Pay button on the product pages next to the Add to Cart button. Compatible with WooCommerce Blocks.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'no',
			),
			'alexa_notifications_support'   => array(
				'title'       => __( 'Support Alexa Delivery Notifications', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable support for Alexa Delivery Notifications', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'This will enable support for Alexa Delivery notifications.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'no',
			),
		);

		if ( $this->has_other_gateways_enabled() ) {
			$this->form_fields['hide_standard_checkout_button'] = array_merge(
				$this->form_fields['hide_standard_checkout_button'],
				array(
					'disabled' => true,
				)
			);
		}

		/**
		 * For new merchants "enforce" the use of LPA ( Hide "Use Login with Amazon App" and consider it ticked.)
		 * For old merchants, keep "Use Login with Amazon App" checkbox, as they can fallback to APA (no client id)
		 *
		 * @since 1.9.0
		 */
		if ( WC_Amazon_Payments_Advanced_API::is_new_installation() ) {
			unset( $this->form_fields['enable_login_app'] );
		}

		$this->form_fields = apply_filters( 'woocommerce_amazon_pa_form_fields_before_legacy', $this->form_fields );

		if ( $this->has_v1_settings() && ! WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			$this->form_fields = array_merge(
				$this->form_fields,
				array(
					'account_details_v1'       => array(
						'title'       => __( 'Previous Version Configuration Details', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'title',
						'description' => 'These credentials and settings are read-only and cannot be modified. You must complete the plugin upgrade as instructed at the top of the page in order to make any changes. <a href="#" class="wcapa-toggle-section"  data-toggle="#v1-settings-container">Toggle visibility</a>.',
					),
					'container_start'          => array(
						'type' => 'custom',
						'html' => '<div id="v1-settings-container" class="hidden">',
					),
					'seller_id'                => array(
						'title'       => __( 'Seller ID', 'woocommerce-gateway-amazon-payments-advanced' ),
						'description' => $this->settings['seller_id'],
						'default'     => '',
						'type'        => 'hidden',
					),
					'mws_access_key'           => array(
						'title'       => __( 'MWS Access Key', 'woocommerce-gateway-amazon-payments-advanced' ),
						'description' => $this->settings['mws_access_key'],
						'default'     => '',
						'type'        => 'hidden',
					),
					'secret_key'               => array(
						'title'       => __( 'MWS Secret Key', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden_masked',
						'description' => __( 'Hidden secret key', 'woocommerce-gateway-amazon-payments-advanced' ),
						'default'     => '',
					),
					'app_client_id'            => array(
						'title'       => __( 'App Client ID', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
						'description' => $this->settings['app_client_id'],
						'default'     => '',
					),
					'enable_login_app'         => array(
						'title'       => __( 'Use Login with Amazon App', 'woocommerce-gateway-amazon-payments-advanced' ),
						'label'       => $enable_login_app_label,
						'type'        => 'hidden',
						'description' => $this->settings['enable_login_app'],
						'default'     => '',
					),
					'redirect_authentication'  => array(
						'title'       => __( 'Login Authorization Mode', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
						'description' => '<strong>' . $this->settings['redirect_authentication'] . '</strong><br>' . sprintf(
							/* translators: Redirect URL to copy and paste */
							__( 'Optimal mode requires setting the Allowed Return URLs and Allowed Javascript Origins in Amazon Seller Central. Click the Configure/Register Now button and sign in with your existing account to update the configuration and automatically set these values. If the URL is not added automatically to the Allowed Return URLs field in Amazon Seller Central, please copy and paste the one below manually. <br> <code>%1$s</code>', 'woocommerce-gateway-amazon-payments-advanced' ),
							$redirect_url
						),
					),
					'cart_button_display_mode' => array(
						'title'       => __( 'Cart login button display', 'woocommerce-gateway-amazon-payments-advanced' ),
						'description' => '<strong>' . $this->settings['cart_button_display_mode'] . '</strong><br>' . __( 'How the login with Amazon button gets displayed on the cart page. This requires cart page served via HTTPS. If HTTPS is not available in cart page, please select disabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
						'default'     => 'button',
					),
					'button_type'              => array(
						'title'       => __( 'Button type', 'woocommerce-gateway-amazon-payments-advanced' ),
						'description' => '<strong>' . $this->settings['button_type'] . '</strong><br>' . __( 'Type of button image to display on cart and checkout pages. Only used when Amazon Login App is enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
					),
					'button_size'              => array(
						'title'       => __( 'Button size', 'woocommerce-gateway-amazon-payments-advanced' ),
						'description' => '<strong>' . $this->settings['button_size'] . '</strong><br>' . __( 'Button size to display on cart and checkout pages. Only used when Login with Amazon App is enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
					),
					'container_end'            => array(
						'type' => 'custom',
						'html' => '</div>',
					),
				)
			);

			if ( empty( $this->settings['secret_key'] ) ) {
				$this->form_fields['secret_key'] = array(
					'title'       => __( 'MWS Secret Key', 'woocommerce-gateway-amazon-payments-advanced' ),
					'type'        => 'text',
					'description' => sprintf(
						'<span style="color: red;">%s</span> %s',
						__( 'Corrupted.', 'woocommerce-gateway-amazon-payments-advanced' ),
						sprintf(
							/* translators: 1) Label from Seller Central 2) Seller Central URL 3) Seller Central Guide. */
							__( 'Please log in to <a href="%2$s" class="wcapa-seller-central-secret-key-url" target="_blank">Seller Central</a> and get your <strong>%1$s</strong> from there. More details about this <a href="%3$s" target="_blank">here</a>.', 'woocommerce-gateway-amazon-payments-advanced' ),
							'Secret Access Key',
							esc_url( WC_Amazon_Payments_Advanced_API::get_secret_key_page_url() ),
							esc_url( 'https://eps-eu-external-file-share.s3.eu-central-1.amazonaws.com/bianchif/WooCommerce/WooCommerce+legacy+fix.pdf' )
						)
					),
					'default'     => '',
				);
			}

			/**
			 * For new merchants "enforce" the use of LPA ( Hide "Use Login with Amazon App" and consider it ticked.)
			 * For old merchants, keep "Use Login with Amazon App" checkbox, as they can fallback to APA (no client id)
			 *
			 * @since 1.9.0
			 */
			if ( WC_Amazon_Payments_Advanced_API::is_new_installation() ) {
				unset( $this->form_fields['enable_login_app'] );
			}
		}

		$this->form_fields = apply_filters( 'woocommerce_amazon_pa_form_fields', $this->form_fields );

		return parent::get_form_fields();
	}

	/**
	 * Generate Custom HTML.
	 *
	 * @param  string $id Field ID.
	 * @param  array  $conf Field configuration.
	 * @return string
	 */
	public function generate_custom_html( $id, $conf ) {
		$html = isset( $conf['html'] ) ? wp_kses_post( $conf['html'] ) : '';

		if ( $html ) {
			ob_start();
			?>
			</table>
			<?php echo $html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<table class="form-table">
			<?php
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Generate Text Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_hidden_masked_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get field value.
	 *
	 * @param string $key Field key.
	 * @param array  $value Field value.
	 * @return string
	 */
	public function validate_hidden_masked_field( $key, $value ) {
		if ( ! empty( $this->settings[ $key ] ) ) {
			$value = $this->settings[ $key ];
		}
		return $value;
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 */
	public function admin_options() {
		?>
		<h2>
			<?php
			echo esc_html( $this->get_method_title() );
			wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			?>
		</h2>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table><!--/.form-table-->
		<script>
		( function( $ ) {
			var dependentsToggler = {
				init: function() {
					$( '[data-dependent-selector]' )
						.on( 'change', this.toggleDependents )
						.trigger( 'change' );
				},

				toggleDependents: function( e ) {
					var $toggler    = $( e.target ),
						$dependents = $( $toggler.data( 'dependent-selector' ) ),
						showCondition = $toggler.data( 'dependent-show-condition' );

					$dependents.closest( 'tr' ).toggle( $toggler.is( showCondition ) );
				}
			};

			function run() {
				dependentsToggler.init();
			}

			$( run );
		} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Unset keys json box.
	 *
	 * @return bool|void
	 */
	public function process_admin_options() {
		if ( check_admin_referer( 'woocommerce-settings' ) ) {
			if ( isset( $_FILES['woocommerce_amazon_payments_advanced_keys_json'] ) && isset( $_FILES['woocommerce_amazon_payments_advanced_keys_json']['size'] ) && 0 < $_FILES['woocommerce_amazon_payments_advanced_keys_json']['size'] ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				$json_file = $_FILES['woocommerce_amazon_payments_advanced_keys_json'];
				$this->process_settings_from_file( $json_file, true );
			}
			if ( isset( $_FILES['woocommerce_amazon_payments_advanced_private_key'] ) && isset( $_FILES['woocommerce_amazon_payments_advanced_private_key']['size'] ) && 0 < $_FILES['woocommerce_amazon_payments_advanced_private_key']['size'] ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				$pem_file = $_FILES['woocommerce_amazon_payments_advanced_private_key'];

				$finfo = new finfo( FILEINFO_MIME_TYPE );
				$ext   = $finfo->file( $pem_file['tmp_name'] );
				if ( 'text/plain' === $ext && isset( $pem_file['tmp_name'] ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$private_key = file_get_contents( $pem_file['tmp_name'] );
					$this->save_private_key( $private_key );
				}
			}
			parent::process_admin_options();
		}
	}

	/**
	 * Process settings in a file
	 *
	 * @param array $import_file PHP $_FILES (or similar) entry.
	 * @param  bool  $clean_post Wether to clean the post or not.
	 */
	protected function process_settings_from_file( $import_file, $clean_post = false ) {
		$fn_parts  = explode( '.', $import_file['name'] );
		$extension = end( $fn_parts );

		if ( 'json' !== $extension ) {
			wp_die( esc_html__( 'Please upload a valid .json file', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json_settings = (array) json_decode( file_get_contents( $import_file['tmp_name'] ) );
		if ( isset( $json_settings['private_key'] ) ) {
			$private_key = $json_settings['private_key'];
			unset( $json_settings['private_key'] );
			$this->save_private_key( $private_key );
		}

		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					if ( isset( $json_settings[ $key ] ) ) {
						$this->settings[ $key ] = $json_settings[ $key ];
						if ( $clean_post ) {
							$post_key = 'woocommerce_amazon_payments_advanced_' . $key;
							if ( isset( $this->data ) && is_array( $this->data ) && isset( $this->data[ $post_key ] ) ) {
								$this->data[ $post_key ] = $this->settings[ $key ];
							}
							if ( isset( $_POST[ $post_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
								$_POST[ $post_key ] = $this->settings[ $key ];
							}
						}
						unset( $json_settings[ $key ] );
					}
				} catch ( Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
			}
		}

		update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
	}

	/**
	 * Save private key.
	 *
	 * @param string $private_key Private key PEM string.
	 */
	protected function save_private_key( $private_key ) {
		$validate_private_key = openssl_pkey_get_private( $private_key );
		if ( $validate_private_key ) {
			update_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY, $private_key );
			return true;
		}

		return false;
	}

	/**
	 * Validate api keys.
	 */
	public function validate_api_keys() {
		if ( ! empty( $this->settings['merchant_id'] ) || WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			WC_Amazon_Payments_Advanced_API::validate_api_keys();
		} else {
			WC_Amazon_Payments_Advanced_API_Legacy::validate_api_keys();
		}
	}

	/**
	 * Checkout Button
	 *
	 * Triggered from the 'woocommerce_proceed_to_checkout' action.
	 *
	 * @param  bool   $echo Wether to echo or not.
	 * @param  string $elem HTML tag to render.
	 * @param  string $id   The id attribute to provide the HTML tag with.
	 * @return bool|string|void
	 */
	public function checkout_button( $echo = true, $elem = 'div', $id = 'pay_with_amazon' ) {
		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
		$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' === $this->settings['subscriptions_enabled'];
		$cart_contains_sub       = class_exists( 'WC_Subscriptions_Cart' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false;

		if ( $subscriptions_installed && ! $subscriptions_enabled && $cart_contains_sub ) {
			return;
		}

		$button_placeholder = '<' . $elem . ' id="' . esc_attr( $id ) . '"></' . $elem . '>';

		if ( false === $echo ) {
			return $button_placeholder;
		} else {
			echo $button_placeholder; // phpcs:ignore WordPress.Security.EscapeOutput
			return true;
		}
	}

	/**
	 * Classic Checkout button
	 *
	 * Triggered from 'woocommerce_after_checkout_form' and 'woocommerce_pay_order_after_submit'.
	 *
	 * @param bool   $echo Wether to echo or not.
	 * @param string $elem HTML tag to render.
	 * @return bool|string|void
	 */
	public function classic_integration_button( $echo = true, $elem = 'div' ) {
		if ( $this->is_available() && $this->is_classic_enabled() ) {
			$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
			$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' === $this->settings['subscriptions_enabled'];
			$cart_contains_sub       = class_exists( 'WC_Subscriptions_Cart' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false;

			if ( $subscriptions_installed && ! $subscriptions_enabled && $cart_contains_sub ) {
				return;
			}

			$button_placeholder = '<' . $elem . ' id="classic_pay_with_amazon"></' . $elem . '>';

			if ( false === $echo ) {
				return $button_placeholder;
			} else {
				echo $button_placeholder; // phpcs:ignore WordPress.Security.EscapeOutput
				return true;
			}
		}
	}

	/**
	 * Remove amazon gateway.
	 *
	 * @param array $gateways Gateways registered.
	 *
	 * @return array
	 */
	public function remove_amazon_gateway( $gateways ) {
		if ( ! $this->is_classic_enabled() && isset( $gateways[ $this->id ] ) ) {
			unset( $gateways[ $this->id ] );
		}

		return $gateways;
	}

	/**
	 * Remove all gateways except amazon
	 *
	 * @param array $gateways List of payment methods.
	 *
	 * @return array List of payment methods.
	 */
	public function remove_gateways( $gateways ) {

		foreach ( $gateways as $gateway_key => $gateway ) {
			if ( 'amazon_payments_advanced' !== $gateway_key ) {
				unset( $gateways[ $gateway_key ] );
			}
		}

		return $gateways;
	}

	/**
	 * Override billing fields when checking out with Amazon.
	 *
	 * @param array $fields billing fields.
	 */
	public function override_billing_fields( $fields ) {
		// Last name and State are not required on Amazon billing addrress forms.
		$fields['billing_last_name']['required'] = false;
		$fields['billing_state']['required']     = false;
		// Phone field is missing on some sandbox accounts.
		$fields['billing_phone']['required'] = false;

		return $fields;
	}

	/**
	 * Override address fields when checking out with Amazon.
	 *
	 * @param array $fields default address fields.
	 */
	public function override_shipping_fields( $fields ) {
		// Last name and State are not required on Amazon shipping address forms.
		$fields['shipping_last_name']['required'] = false;
		$fields['shipping_state']['required']     = false;

		return $fields;
	}

	/**
	 * Init common hooks on checkout_init hook
	 */
	public function checkout_init_common() {
		// Remove other gateways after being logged in.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_gateways' ) );
		// Some fields are not enforced on Amazon's side. Marking them as optional avoids issues with checkout.
		add_filter( 'woocommerce_billing_fields', array( $this, 'override_billing_fields' ) );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'override_shipping_fields' ) );
		// Always ship to different address.
		add_action( 'woocommerce_ship_to_different_address_checked', '__return_true' );
	}

	/**
	 * Check if the v1 settings are configured
	 *
	 * @return bool
	 */
	public function is_v1_configured() {
		if ( empty( $this->settings['secret_key'] ) || empty( $this->settings['mws_access_key'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if there's settings for v1
	 *
	 * @return bool
	 */
	public function has_v1_settings() {
		return ! empty( $this->settings['seller_id'] );
	}

	/**
	 * Checks if site has other gateways enabled.
	 *
	 * @return bool
	 */
	public function has_other_gateways_enabled() {
		$gateways = WC()->payment_gateways()->get_available_payment_gateways();
		unset( $gateways['amazon_payments_advanced'] );
		if ( ! empty( $gateways ) ) {
			foreach ( $gateways as $gateway ) {
				if ( 'yes' === $gateway->enabled ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Returns if the classic integration is enabled.
	 *
	 * @return boolean
	 */
	public function is_classic_enabled() {
		return empty( $this->settings['enable_classic_gateway'] ) || 'yes' === $this->settings['enable_classic_gateway'];
	}

	/**
	 * Returns if the mini-cart button placement is enabled.
	 *
	 * @return boolean
	 */
	protected function is_mini_cart_button_enabled() {
		return ! empty( $this->settings['mini_cart_button'] ) && 'yes' === $this->settings['mini_cart_button'];
	}

	/**
	 * Returns if the product button placement is enabled.
	 *
	 * @return boolean
	 */
	protected function is_product_button_enabled() {
		return ! empty( $this->settings['product_button'] ) && 'yes' === $this->settings['product_button'];
	}

	/**
	 * Returns if the user is using woo blocks.
	 *
	 * @return boolean
	 */
	protected function using_woo_blocks() {
		return ! empty( $this->settings['using_woo_blocks'] ) && 'yes' === $this->settings['using_woo_blocks'];
	}
}
