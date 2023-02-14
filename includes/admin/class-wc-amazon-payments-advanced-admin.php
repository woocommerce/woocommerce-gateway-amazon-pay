<?php
/**
 * Admin Related Functionality
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Amazon_Payments_Advanced_Admin
 */
class WC_Amazon_Payments_Advanced_Admin {

	/**
	 * Plugin's absolute path.
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Plugin's settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Order admin handler instance.
	 *
	 * @since 1.6.0
	 * @var WC_Amazon_Payments_Advanced_Order_Admin
	 */
	protected $order_admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->path = untrailingslashit( plugin_dir_path( __FILE__ ) );

		$this->init_order_admin();

		// Plugin list.
		add_filter( 'plugin_action_links_' . wc_apa()->plugin_basename, array( $this, 'plugin_links' ) );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_amazon_pay_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		// Admin Scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Check for credentials AJAX.
		add_action( 'wp_ajax_amazon_check_credentials', array( $this, 'ajax_check_credentials' ) );

		// Delete credentials AJAX.
		add_action( 'wp_ajax_amazon_delete_credentials', array( $this, 'ajax_delete_credentials' ) );
	}

	/**
	 * Returns the full URL to the plugin's settings page.
	 *
	 * @return string
	 */
	protected function get_settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=amazon_payments_advanced' );
	}

	/**
	 * Init admin handler.
	 *
	 * @since 1.6.0
	 */
	public function init_order_admin() {
		include_once $this->path . '/class-wc-amazon-payments-advanced-order-admin.php';
		$this->order_admin = new WC_Amazon_Payments_Advanced_Order_Admin();
		include_once $this->path . '/legacy/class-wc-amazon-payments-advanced-order-admin-legacy.php';
		new WC_Amazon_Payments_Advanced_Order_Admin_Legacy();
	}

	/**
	 * Plugin page links
	 *
	 * @since 1.0.0
	 * @version 1.7.3
	 *
	 * @param array $links Array links.
	 */
	public function plugin_links( $links ) {
		$plugin_links = array(
			'<a href="' . $this->get_settings_url() . '">' . __( 'Settings', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>',
			'<a href="https://docs.woocommerce.com/">' . __( 'Support', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>',
			'<a href="https://docs.woocommerce.com/document/amazon-payments-advanced/">' . __( 'Docs', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Get admin notices.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return array Array of notices.
	 */
	protected function get_admin_notices() {
		$this->settings = WC_Amazon_Payments_Advanced_API::get_settings();
		global $current_section;

		$login_app_enabled         = 'yes' === $this->settings['enable_login_app'];
		$wc_version_3_9_or_greater = class_exists( 'WooCommerce' ) && version_compare( WC_VERSION, '3.9', '>=' );

		// If we are running WooCommerce 3.9 and up we want the store to be running with the Login App enabled. This
		// allows us to use the features of Login to properly populate order information that these version of
		// WooCommerce expect (as well as other plugins).
		$current_screen = get_current_screen();

		// Send out a different notification if we're on the Amazon Pay settings screen. Non-dismissable when on the
		// settings screen, dismissable if we're anywhere else.
		if ( isset( $current_screen ) &&
			'woocommerce_page_wc-settings' === $current_screen->id &&
			'amazon_payments_advanced' === $current_section
		) {
			$in_settings                    = true;
			$in_amazon_pay_settings_section = 'in_settings';
			$is_dismissable                 = false;
		} else {
			$in_settings                    = false;
			$in_amazon_pay_settings_section = '';
			$is_dismissable                 = true;
		}

		$notices = array();

		$gateway = wc_apa()->get_gateway();

		if ( $gateway->has_v1_settings() && ! $gateway->is_v1_configured() ) {
			if ( ! $in_settings ) {
				$notices[] = array(
					'dismiss_action' => 'amazon_pay_dismiss_cv1_broken',
					'class'          => 'notice notice-warning',
					'text'           => sprintf(
						/* translators: 1) The URL to the Amazon Pay settings screen. */
						'<p>' . __( 'Your Amazon Pay legacy settings seem corrupted. If you see issues processing legacy orders/subscriptions. Please follow the instructions in the <a href="%1$s">Amazon Pay Settings</a> to go through a recovery process.', 'woocommerce-gateway-amazon-payments-advanced' ) . '</p>',
						esc_url( $this->get_settings_url() )
					),
					'is_dismissable' => false,
				);
			} else {
				$notices[] = array(
					'dismiss_action' => 'amazon_pay_dismiss_cv1_broken',
					'class'          => 'notice notice-warning',
					'text'           => '<p>' . __( 'Your Amazon Pay legacy settings seem corrupted. If you see issues processing legacy orders/subscriptions. Please follow the instructions in the <a href="#" class="wcapa-toggle-section wcapa-toggle-scroll"  data-toggle="#v1-settings-container">Previous Version Configuration</a> area go through a recovery process.', 'woocommerce-gateway-amazon-payments-advanced' ) . '</p>',
					'is_dismissable' => false,
				);
			}
			return $notices; // Return early, as this is a critical issue.
		}

		if ( class_exists( 'WooCommerce_Germanized' ) && 'yes' === get_option( 'woocommerce_gzd_checkout_stop_order_cancellation' ) ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_germanized_notice',
				'class'          => 'amazon-pay-wc-germanized-notice',
				// translators: 1) The URL to Disallow cancellation.
				'text'           => sprintf( __( '<a href="%s">Disallow cancellation</a> is enabled in WooCommerce Germanized and will cause an issue in Amazon Pay\'s checkout.', 'woocommerce-gateway-amazon-payments-advanced' ), admin_url( 'admin.php?page=wc-settings&tab=germanized' ) ),
				'is_dismissable' => true,
			);
		}

		if ( class_exists( 'WooCommerce' ) && ! WC_Amazon_Payments_Advanced_API::is_region_supports_shop_currency() ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_currency_notice',
				'class'          => 'amazon-pay-currency-notice notice notice-error',
				// translators: 1) The current shop currency.
				'text'           => sprintf( __( 'Your shop currency <strong>%1$s</strong> does not match with Amazon payment region <strong>%2$s</strong>. Amazon Pay will <strong>not</strong> be available. Please use a seller account from the appropiate region.', 'woocommerce-gateway-amazon-payments-advanced' ), get_woocommerce_currency(), WC_Amazon_Payments_Advanced_API::get_region_label() ),
				'is_dismissable' => false,
			);
		}

		if ( ! WC_Amazon_Payments_Advanced_API::get_amazon_keys_set() && 'yes' === $this->settings['enabled'] ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_enable_notice',
				'class'          => 'amazon-pay-enable-notice',
				'text'           => sprintf(
					// translators: 1) The URL to the Amazon Pay settings screen.
					__( 'Amazon Pay is now enabled for WooCommerce and ready to accept live payments. Please check the <a href="%1$s">configuration</a>. to make sure everything is set correctly.', 'woocommerce-gateway-amazon-payments-advanced' ),
					esc_url( $this->get_settings_url() )
				),
				'is_dismissable' => true,
			);
		}

		if ( $wc_version_3_9_or_greater && ! $login_app_enabled ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_setup_login_app' . $in_amazon_pay_settings_section,
				'class'          => 'amazon-pay-setup-login-app' . $in_amazon_pay_settings_section,
				'text'           => sprintf(
					/* translators: 1) The URL to the Amazon Pay settings screen. 2) The URL to the Login with Amazon App setup instructions. */
					__(
						'<strong>Amazon Pay:</strong> Additional Setup Required - To ensure full compatibility with this version of WooCommerce, please enable the "Use Login with Amazon App" feature from the <a href="%1$s">settings page</a>. After enabling Login with Amazon click the "CONFIGURE/REGISTER NOW" button to re-run configuration and setup your credentials. Alternatively, perform the setup manually using these <a href="%2$s">instructions</a>.',
						'woocommerce-gateway-amazon-payments-advanced'
					),
					$this->get_settings_url(),
					WC_Amazon_Payments_Advanced_API::get_client_id_instructions_url()
				),
				'is_dismissable' => $is_dismissable,
			);
		}
		if ( ! WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			if ( ! $in_settings ) {
				$notices[] = array(
					'dismiss_action' => 'amazon_pay_dismiss_api_migration_notice',
					'class'          => 'notice notice-error',
					'text'           => sprintf(
						/* translators: 1) The URL to the Amazon Pay settings screen. */
						'<p>' . __( 'Amazon Pay V2 is installed. Please click the "Reconnect to Amazon Pay" button in the <a href="%1$s">Amazon Pay Settings</a> to acquire credentials and complete activation. Amazon Pay V1 will continue to function until you complete this process.', 'woocommerce-gateway-amazon-payments-advanced' ) . '</p>',
						esc_url( $this->get_settings_url() )
					),
					'is_dismissable' => false,
				);
			} else {
				$notices[] = array(
					'dismiss_action' => 'amazon_pay_dismiss_api_migration_notice',
					'class'          => 'notice notice-error',
					'text'           => '<p>' . __( 'Amazon Pay V2 is installed. Please click the "Reconnect to Amazon Pay" button below to acquire credentials and complete activation. Amazon Pay V1 will continue to function until you complete this process.', 'woocommerce-gateway-amazon-payments-advanced' ) . '</p>',
					'is_dismissable' => false,
				);
			}
		}
		if ( ! wc_checkout_is_https() ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_ssl_notice',
				'class'          => 'notice notice-error',
				'text'           => sprintf(
					/* translators: 1) The URL to the Amazon Pay settings screen. */
					'<p>' . __( 'Amazon Pay is enabled but a SSL certificate is not detected. Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a> to get the plugin working properly.', 'woocommerce-gateway-amazon-payments-advanced' ) . '</p>',
					'https://en.wikipedia.org/wiki/Transport_Layer_Security'
				),
				'is_dismissable' => false,
			);
		}
		$site_name = WC_Amazon_Payments_Advanced::get_site_name();
		if ( 50 < strlen( $site_name ) ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_site_name_too_long_dismiss_notice',
				'class'          => 'amazon_pay_site_name_too_long',
				'text'           => sprintf(
					/* translators: 1) The Site Name from Settings > General > Site Title. 2) URL to Settings > General > Site Title. */
					__(
						'Amazon Pay Gateway is <strong>not</strong> able to pass to Amazon your site\'s name as the 
						<a target="_blank" rel="nofollow noopener" href="https://developer.amazon.com/docs/amazon-pay-checkout/buyer-communication.html">Merchant store name</a>.<br/>
						This is happening because your current site name exceeds the 50 characters allowed by Amazon Pay API v2.<br/>
						Your current site name is <strong>%1$s</strong> and can be changed from <a href="%2$s">Settings > General > Site Title</a>
						The default you have set in your Amazon Merchant Account will be used instead.<br/>
						This message\'s purpose is to notify you. Amazon Pay Gateway will continue to be functional without requiring an action from you.',
						'woocommerce-gateway-amazon-payments-advanced'
					),
					$site_name,
					esc_url( admin_url( '/options-general.php' ) )
				),
				'is_dismissable' => true,
			);
		}

		return $notices;
	}

	/**
	 * Output admin notices (if any).
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 */
	public function admin_notices() {
		foreach ( $this->get_admin_notices() as $notice ) {
			if ( 'yes' !== get_option( $notice['dismiss_action'], 'yes' ) ) {
				continue;
			}

			if ( $notice['is_dismissable'] ) {
				$dismissable_class = 'is-dismissible';
			} else {
				$dismissable_class = '';
			}

			?>
			<div class="notice notice-warning <?php echo esc_attr( $dismissable_class ); ?> <?php echo esc_attr( $notice['class'] ); ?>">
				<p>
				<?php
				echo wp_kses(
					$notice['text'],
					array(
						'a'      => array(
							'href'        => array(),
							'title'       => array(),
							'class'       => array(),
							'data-toggle' => array(),
							'target'      => array( '_self', '_blank' ),
							'rel'         => array( 'nofollow', 'noopener' ),
						),
						'strong' => array(),
						'em'     => array(),
						'br'     => array(),
					)
				);
				?>
				</p>
				<script type="application/javascript">
				( function( $ ) {
					$( '.<?php echo esc_js( $notice['class'] ); ?>' ).on( 'click', '.notice-dismiss', function() {
						jQuery.post( "<?php echo admin_url( 'admin-ajax.php' ); ?>", {
							action: "amazon_pay_dismiss_notice",
							dismiss_action: "<?php echo esc_js( $notice['dismiss_action'] ); ?>",
							nonce: "<?php echo esc_js( wp_create_nonce( 'amazon_pay_dismiss_notice' ) ); ?>"
						} );
					} );
				} )( jQuery );
				</script>
			</div>
			<?php
		}
	}

	/**
	 * AJAX handler for dismiss notice action.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'amazon_pay_dismiss_notice', 'nonce' );
		foreach ( $this->get_admin_notices() as $notice ) {
			if ( isset( $_POST['dismiss_action'] ) && $notice['dismiss_action'] === $_POST['dismiss_action'] ) {
				update_option( $notice['dismiss_action'], 'no' );
				break;
			}
		}
		wp_die();
	}

	/**
	 * Add scripts to dashboard settings.
	 *
	 * @param string $hook Admin screen ID.
	 *
	 * @throws Exception On Errors.
	 */
	public function admin_scripts( $hook ) {
		global $current_section;

		$current_screen = get_current_screen()->id;

		if ( 'woocommerce_page_wc-settings' === $hook && 'amazon_payments_advanced' === $current_section ) {
			$current_screen = 'wc_apa_settings';
		}

		$screen_to_check = WC_Amazon_Payments_Advanced_Utils::get_edit_order_screen_id();

		switch ( $current_screen ) {
			case $screen_to_check:
			case 'wc_apa_settings':
				break;
			default:
				return;
		}

		$js_suffix = '.min.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$js_suffix = '.js';
		}

		$params = array(
			'simple_path_urls'      => WC_Amazon_Payments_Advanced_API::$registration_urls,
			'spids'                 => WC_Amazon_Payments_Advanced_API::$sp_ids,
			'onboarding_version'    => WC_Amazon_Payments_Advanced_API::$onboarding_version,
			'locale'                => get_locale(),
			'home_url'              => home_url( '', 'https' ),
			'simple_path_url'       => wc_apa()->onboarding_handler->get_simple_path_registration_url(),
			'public_key'            => WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ? wc_apa()->onboarding_handler->get_public_key() : wc_apa()->onboarding_handler->get_public_key( false, true ),
			'privacy_url'           => get_option( 'wp_page_for_privacy_policy' ) ? get_permalink( (int) get_option( 'wp_page_for_privacy_policy' ) ) : '',
			'description'           => WC_Amazon_Payments_Advanced::get_site_description(),
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'credentials_nonce'     => wp_create_nonce( 'amazon_pay_check_credentials' ),
			'login_redirect_url'    => wc_apa()->get_gateway()->get_amazon_payments_checkout_url(),
			'woo_version'           => 'WooCommerce: ' . WC()->version,
			'plugin_version'        => 'WooCommerce Amazon Pay: ' . wc_apa()->version,
			'language_combinations' => WC_Amazon_Payments_Advanced_API::get_languages_per_region(),
			'secret_keys_urls'      => WC_Amazon_Payments_Advanced_API::get_secret_key_page_urls(), // LEGACY FIX.
		);

		wp_register_script( 'amazon_payments_admin', wc_apa()->plugin_url . '/assets/js/amazon-wc-admin' . $js_suffix, array(), wc_apa()->version, true );
		wp_localize_script( 'amazon_payments_admin', 'amazon_admin_params', $params );
		wp_enqueue_script( 'amazon_payments_admin' );

		wp_enqueue_style( 'amazon_payments_admin', wc_apa()->plugin_url . '/assets/css/style-admin.css', array(), wc_apa()->version );
		wp_enqueue_style( 'amazon_payments_advanced_hide_express', wc_apa()->plugin_url . '/assets/css/hide-amazon-express-admin.css', array(), wc_apa()->version );
	}

	/**
	 *  AJAX handler to check if credentials have been set on settings page.
	 */
	public function ajax_check_credentials() {
		check_ajax_referer( 'amazon_pay_check_credentials', 'nonce' );
		$result        = -1;
		$settings      = WC_Amazon_Payments_Advanced_API::get_settings();
		$saved_payload = get_option( 'woocommerce_amazon_payments_advanced_saved_payload' );
		if ( ! empty( $settings['merchant_id'] )
			&& ! empty( $settings['store_id'] )
			&& ! empty( $settings['public_key_id'] )
			&& $saved_payload
		) {
			$result = array(
				'merchant_id'   => $settings['merchant_id'],
				'store_id'      => $settings['store_id'],
				'public_key_id' => $settings['public_key_id'],

			);
			delete_option( 'woocommerce_amazon_payments_advanced_saved_payload' );
		}
		wp_send_json_success( $result );
	}

	/**
	 *  AJAX handler to delete credentials have been set on settings page.
	 */
	public function ajax_delete_credentials() {
		check_ajax_referer( 'amazon_pay_check_credentials', 'nonce' );
		if ( current_user_can( 'manage_options' ) ) {
			WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::destroy_keys();
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			wc_apa()->get_gateway()->update_option( 'public_key_id', '' );
			wc_apa()->get_gateway()->update_option( 'store_id', '' );
			wc_apa()->get_gateway()->update_option( 'merchant_id', '' );
			wp_send_json_success();
		}
		wp_send_json_error();
	}

}
