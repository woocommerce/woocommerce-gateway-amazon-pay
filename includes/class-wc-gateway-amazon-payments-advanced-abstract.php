<?php
abstract class WC_Gateway_Amazon_Payments_Advanced_Abstract extends WC_Payment_Gateway {

	/**
	 * Amazon Private Key
	 *
	 * @var string
	 */
	protected $private_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->method_title         = __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' );
		$this->method_description   = __( 'Amazon Pay is embedded directly into your existing web site, and all the buyer interactions with Amazon Pay and Login with Amazon take place in embedded widgets so that the buyer never leaves your site. Buyers can log in using their Amazon account, select a shipping address and payment method, and then confirm their order. Requires an Amazon Pay seller account and supports USA, UK, Germany, France, Italy, Spain, Luxembourg, the Netherlands, Sweden, Portugal, Hungary, Denmark, and Japan.', 'woocommerce-gateway-amazon-payments-advanced' );
		$this->id                   = 'amazon_payments_advanced';
		$this->icon                 = apply_filters( 'woocommerce_amazon_pa_logo', wc_apa()->plugin_url . '/assets/images/amazon-payments.png' );
		$this->debug                = ( 'yes' === $this->get_option( 'debug' ) );
		$this->view_transaction_url = $this->get_transaction_url_format();
		$this->supports             = array(
			'products',
			'refunds',
		);
		$this->supports             = apply_filters( 'woocommerce_amazon_pa_supports', $this->supports, $this );
		$this->private_key          = get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );

		// Load multicurrency fields if compatibility. (Only on settings admin).
		if ( is_admin() ) {
			$compatible_region = isset( $_POST['woocommerce_amazon_payments_advanced_payment_region'] ) ? WC_Amazon_Payments_Advanced_Multi_Currency::compatible_region( $_POST['woocommerce_amazon_payments_advanced_payment_region'] ) : WC_Amazon_Payments_Advanced_Multi_Currency::compatible_region();
			if ( $compatible_region && WC_Amazon_Payments_Advanced_Multi_Currency::get_compatible_instance( $compatible_region ) ) {
				add_filter( 'woocommerce_amazon_pa_form_fields_before_legacy', array( $this, 'add_currency_fields' ) );
			}
		}

		// Load the settings.
		$this->init_settings();

		// Load saved settings.
		$this->load_settings();

		// Load the form fields.
		$this->init_form_fields();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'validate_api_keys' ) );

		// Import / Export
		add_action( 'admin_init', array( $this, 'process_settings_import' ) );
		add_action( 'admin_init', array( $this, 'process_settings_export' ) );
		add_action( 'woocommerce_after_settings_checkout', array( $this, 'import_export_fields_output' ) );

		add_action( 'woocommerce_amazon_checkout_init', array( $this, 'checkout_init_common' ) );

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
	 * Adds multicurrency settings to form fields.
	 */
	public function add_currency_fields( $form_fields ) {
		$compatible_plugin = WC_Amazon_Payments_Advanced_Multi_Currency::compatible_plugin( true );

		$form_fields['multicurrency_options'] = array(
			'title'       => __( 'Multi-Currency', 'woocommerce-gateway-amazon-payments-advanced' ),
			'type'        => 'title',
			/* translators: Compatible plugin */
			'description' => sprintf( __( 'Multi-currency compatibility detected with <strong>%s</strong>', 'woocommerce-gateway-amazon-payments-advanced' ), $compatible_plugin ),
		);

		/**
		 * Only show currency list for plugins that will use the list. Frontend plugins will be exempt.
		 */
		if ( ! WC_Amazon_Payments_Advanced_Multi_Currency::$compatible_instance->is_front_end_compatible() ) {
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
	 * Define user set variables.
	 */
	public function load_settings() {
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();

		$this->title                   = $settings['title'];
		$this->payment_region          = $settings['payment_region'];
		$this->merchant_id             = $settings['merchant_id'];
		$this->store_id                = $settings['store_id'];
		$this->public_key_id           = $settings['public_key_id'];
		$this->seller_id               = $settings['seller_id'];
		$this->mws_access_key          = $settings['mws_access_key'];
		$this->secret_key              = $settings['secret_key'];
		$this->enable_login_app        = $settings['enable_login_app'];
		$this->app_client_id           = $settings['app_client_id'];
		$this->app_client_secret       = $settings['app_client_secret'];
		$this->sandbox                 = $settings['sandbox'];
		$this->payment_capture         = $settings['payment_capture'];
		$this->authorization_mode      = $settings['authorization_mode'];
		$this->redirect_authentication = $settings['redirect_authentication'];
	}

	/**
	 * Init payment gateway form fields
	 */
	public function init_form_fields() {

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
		$redirect_url           = add_query_arg( 'amazon_payments_advanced', 'true', get_permalink( wc_get_page_id( 'checkout' ) ) );
		$valid                  = isset( $this->settings['amazon_keys_setup_and_validated'] ) ? $this->settings['amazon_keys_setup_and_validated'] : false;

		$connect_desc    = __( 'Register for a new Amazon Pay merchant account, or sign in with your existing Amazon Pay Seller Central credentials to complete the plugin upgrade and configuration', 'woocommerce-gateway-amazon-payments-advanced' );
		$connect_btn     = '<a class="register_now button-primary">' . __( 'Connect to Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>';
		$disconnect_desc = __( 'In order to connect to a different account you need to disconect first, this will delete current Account Settings, you will need to go throught all the configuration process again', 'woocommerce-gateway-amazon-payments-advanced' );
		$disconnect_btn  = '<a class="delete-settings button-primary">' . __( 'Disconnect Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>';

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
				'description' => $this->private_key ? $disconnect_desc . '<br/><br/>' . $disconnect_btn : $connect_desc . '<br/><br/>' . $connect_btn,
			),
			'enabled'                       => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable Amazon Pay &amp; Login with Amazon', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
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
			'subscriptions_enabled'         => array(
				'title'       => __( 'Subscriptions support', 'woocommerce-gateway-amazon-payments-advanced' ),
				'label'       => __( 'Enable Subscriptions for carts that contain Subscription items (requires WooCommerce Subscriptions to be installed)', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'        => 'select',
				'description' => __( 'This will enable support for Subscriptions and make transactions more securely', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default'     => 'yes',
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
					__( 'To process payment the Optimal way complete your Seller Central configuration on plugin setup. From your seller Central home page, go to “Settings – Integration Settings”. In this page, please paste the URL below to the “Merchant URL” input field. <br /><code>%1$s</code>', 'woocommerce-gateway-amazon-payments-advanced' ),
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
				'class'       => 'show-if-app-is-enabled',
				'default'     => '',
				'options'     => array(
					''      => __( 'Detect from buyer\'s browser', 'woocommerce-gateway-amazon-payments-advanced' ),
					'en-GB' => __( 'UK English', 'woocommerce-gateway-amazon-payments-advanced' ),
					'de-DE' => __( 'Germany\'s German', 'woocommerce-gateway-amazon-payments-advanced' ),
					'fr-FR' => __( 'France\'s French', 'woocommerce-gateway-amazon-payments-advanced' ),
					'it-IT' => __( 'Italy\'s Italian', 'woocommerce-gateway-amazon-payments-advanced' ),
					'es-ES' => __( 'Spain\'s Spanish', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'button_color'                  => array(
				'title'       => __( 'Button color', 'woocommerce-gateway-amazon-payments-advanced' ),
				'description' => __( 'Button color to display on cart and checkout pages. Only used when Login with Amazon App is enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'select',
				'class'       => 'show-if-app-is-enabled',
				'default'     => 'Gold',
				'options'     => array(
					'Gold'      => __( 'Gold', 'woocommerce-gateway-amazon-payments-advanced' ),
					'LightGray' => __( 'Light gray', 'woocommerce-gateway-amazon-payments-advanced' ),
					'DarkGray'  => __( 'Dark gray', 'woocommerce-gateway-amazon-payments-advanced' ),
				),
			),
			'hide_standard_checkout_button' => array(
				'title'   => __( 'Standard checkout button', 'woocommerce-gateway-amazon-payments-advanced' ),
				'type'    => 'checkbox',
				'label'   => __( 'Hide standard checkout button on cart page', 'woocommerce-gateway-amazon-payments-advanced' ),
				'default' => 'no',
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
				'description' => __( 'This will hides Amazon buttons on cart and checkout pages so the gateway looks not available to the customers. The buttons are hidden via CSS. Only enable this when troubleshooting your integration.', 'woocommerce-gateway-amazon-payments-advanced' ),
				'desc_tip'    => true,
				'type'        => 'checkbox',
				'default'     => 'no',
			),
		);

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

		if ( ! empty( $this->settings['seller_id'] ) ) {
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
						'type'        => 'hidden',
						'description' => __( 'Hidden secret key', 'woocommerce-gateway-amazon-payments-advanced' ),
						'default'     => '',
					),
					'app_client_id'            => array(
						'title'       => __( 'App Client ID', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
						'description' => $this->settings['app_client_id'],
						'default'     => '',
					),
					'app_client_secret'        => array(
						'title'       => __( 'App Client Secret', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
						'description' => __( 'Hidden secret key', 'woocommerce-gateway-amazon-payments-advanced' ),
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
							__( 'Optimal mode requires setting the Allowed Return URLs and Allowed Javascript Origins in Amazon Seller Central. Click the Configure/Register Now button and sign in with your existing account to update the configuration and automatically set these values. If the URL is not added automatically to the Allowed Return URLs field in Amazon Seller Central, please copy and paste the one below manually. <br> <code>%1$s</code>' ),
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
						'class'       => 'show-if-app-is-enabled',
					),
					'button_size'              => array(
						'title'       => __( 'Button size', 'woocommerce-gateway-amazon-payments-advanced' ),
						'description' => '<strong>' . $this->settings['button_size'] . '</strong><br>' . __( 'Button size to display on cart and checkout pages. Only used when Login with Amazon App is enabled.', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'hidden',
						'class'       => 'show-if-app-is-enabled',
					),
					'container_end'            => array(
						'type' => 'custom',
						'html' => '</div>',
					),
				)
			);
		}

		$this->form_fields = apply_filters( 'woocommerce_amazon_pa_form_fields', $this->form_fields );
	}

	/**
	 * Generate Custom HTML.
	 */
	public function generate_custom_html( $id, $conf ) {
		$html = isset( $conf['html'] ) ? wp_kses_post( $conf['html'] ) : '';

		if ( $html ) {
			ob_start();
			?>
			</table>
			<?php echo $html; ?>
			<table class="form-table">
			<?php
			$html = ob_get_clean();
		}

		return $html;
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
			wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
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

		update_option( $this->get_option_key( true ), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
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
	 * Process a settings import from a json file.
	 */
	public function process_settings_import() {

		if ( empty( $_POST['amazon_pay_action'] ) || 'import_settings' !== $_POST['amazon_pay_action'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST['amazon_pay_import_nonce'] ) && ! wp_verify_nonce( $_POST['amazon_pay_import_nonce'], 'amazon_pay_import_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Please upload a file to import', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$import_file = $_FILES['import_file'];

		$this->process_settings_from_file( $import_file );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id ) );
		exit;
	}

	/**
	 * Process a settings export that generates a .json file
	 */
	public function process_settings_export() {

		if ( empty( $_POST['amazon_pay_action'] ) || 'export_settings' !== $_POST['amazon_pay_action'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST['amazon_pay_export_nonce'] ) && ! wp_verify_nonce( $_POST['amazon_pay_export_nonce'], 'amazon_pay_export_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings    = get_option( $this->get_option_key() );
		$private_key = get_option( WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::KEYS_OPTION_PRIVATE_KEY );

		$settings['private_key'] = $private_key;

		ignore_user_abort( true );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wc-amazon-pay-settings-export-' . gmdate( 'm-d-Y' ) . '.json' );
		header( 'Expires: 0' );

		echo wp_json_encode( $settings );
		exit;
	}

	/**
	 * Print the forms to import and export the settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function import_export_fields_output() {
		if ( isset( $_GET['section'] ) && 'amazon_payments_advanced' === $_GET['section'] ) { //  phpcs:ignore WordPress.Security.NonceVerification.Recommended 
			?>
			<div class="wrap">
				<div class="metabox-holder">
					<div class="postbox">
						<h3><span><?php esc_html_e( 'Export Settings', 'woocommerce-gateway-amazon-payments-advanced' ); ?></span></h3>
						<div class="inside">
							<p><?php esc_html_e( 'Export the plugin settings for this site as a .json file. This allows you to easily import the configuration into another site.' ); ?></p>
							<form method="post">
								<p><input type="hidden" name="amazon_pay_action" value="export_settings" /></p>
								<p>
									<?php wp_nonce_field( 'amazon_pay_export_nonce', 'amazon_pay_export_nonce' ); ?>
									<?php submit_button( __( 'Export', 'woocommerce-gateway-amazon-payments-advanced' ), 'secondary', 'submit', false ); ?>
								</p>
							</form>
						</div><!-- .inside -->
						<h3><span><?php esc_html_e( 'Import Settings', 'woocommerce-gateway-amazon-payments-advanced' ); ?></span></h3>
						<div class="inside">
							<p><?php esc_html_e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', 'woocommerce-gateway-amazon-payments-advanced' ); ?></p>
							<form method="post" enctype="multipart/form-data">
								<p>
									<input type="file" name="import_file"/>
								</p>
								<p>
									<input type="hidden" name="amazon_pay_action" value="import_settings" />
									<?php wp_nonce_field( 'amazon_pay_import_nonce', 'amazon_pay_import_nonce' ); ?>
									<?php submit_button( esc_html__( 'Import', 'woocommerce-gateway-amazon-payments-advanced' ), 'secondary', 'import_submit', false ); ?>
								</p>
							</form>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Checkout Button
	 *
	 * Triggered from the 'woocommerce_proceed_to_checkout' action.
	 */
	public function checkout_button( $echo = true, $elem = 'div' ) {
		$subscriptions_installed = class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' );
		$subscriptions_enabled   = empty( $this->settings['subscriptions_enabled'] ) || 'yes' === $this->settings['subscriptions_enabled'];
		$cart_contains_sub       = class_exists( 'WC_Subscriptions_Cart' ) ? WC_Subscriptions_Cart::cart_contains_subscription() : false;

		if ( $subscriptions_installed && ! $subscriptions_enabled && $cart_contains_sub ) {
			return;
		}

		$button_placeholder = '<' . $elem . ' id="pay_with_amazon"></' . $elem . '>';

		if ( false === $echo ) {
			return $button_placeholder;
		} else {
			echo $button_placeholder;
			return true;
		}
	}

	/**
	 * Remove amazon gateway.
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function remove_amazon_gateway( $gateways ) {
		if ( isset( $gateways[ $this->id ] ) ) {
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
		// Last name and State are not required on Amazon shipping addrress forms.
		$fields['shipping_last_name']['required'] = false;
		$fields['shipping_state']['required']     = false;

		return $fields;
	}

	public function checkout_init_common() {
		// Remove other gateways after being logged in
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_gateways' ) );
		// Some fields are not enforced on Amazon's side. Marking them as optional avoids issues with checkout.
		add_filter( 'woocommerce_billing_fields', array( $this, 'override_billing_fields' ) );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'override_shipping_fields' ) );
		// Always ship to different address
		add_action( 'woocommerce_ship_to_different_address_checked', '__return_true' );
	}

}
