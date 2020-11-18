<?php

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
	 * @var string
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
		$this->path          = untrailingslashit( plugin_dir_path( __FILE__ ) );
		$this->settings      = wc_apa()->get_settings();

		$this->init_order_admin();

		// Plugin list.
		add_filter( 'plugin_action_links_' . wc_apa()->plugin_basename, array( $this, 'plugin_links' ) );
		// Admin notices.
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_amazon_pay_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
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
		include_once( $this->path . '/class-wc-amazon-payments-advanced-order-admin.php' );

		$this->order_admin = new WC_Amazon_Payments_Advanced_Order_Admin();
		$this->order_admin->add_meta_box();
		$this->order_admin->add_ajax_handler();
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
	 * Checks if the amazon keys have already being set and validated.
	 *
	 * @return bool
	 */
	protected function amazon_keys_already_set() {
		return ( isset( $this->settings['amazon_keys_setup_and_validated'] ) ) && ( 1 === $this->settings['amazon_keys_setup_and_validated'] );
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
		global $current_section;

		$notices = array();

		if ( class_exists( 'WooCommerce_Germanized' ) && 'yes' === get_option( 'woocommerce_gzd_checkout_stop_order_cancellation' ) ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_germanized_notice',
				'class'          => 'amazon-pay-wc-germanized-notice',
				'text'           => sprintf( __( '<a href="%s">Disallow cancellation</a> is enabled in WooCommerce Germanized and will cause an issue in Amazon Pay\'s checkout.', 'woocommerce-gateway-amazon-payments-advanced' ), admin_url( 'admin.php?page=wc-settings&tab=germanized' ) ),
				'is_dismissable' => true,
			);
		}

		if ( class_exists( 'WooCommerce' ) && ! WC_Amazon_Payments_Advanced_API::is_region_supports_shop_currency() ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_currency_notice',
				'class'          => 'amazon-pay-currency-notice',
				'text'           => sprintf( __( 'Your shop currency <strong>%1$s</strong> does not match with Amazon payment region <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), get_woocommerce_currency(), WC_Amazon_Payments_Advanced_API::get_region_label() ),
				'is_dismissable' => true,
			);
		}

		if ( ! $this->amazon_keys_already_set() && 'yes' === $this->settings['enabled'] ) {
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
			$in_amazon_pay_settings_section = 'in_settings';
			$is_dismissable                 = false;
		} else {
			$in_amazon_pay_settings_section = '';
			$is_dismissable                 = true;
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
		if ( ! wc_apa()->get_migration_status() ) {
			$notices[] = array(
				'dismiss_action' => 'amazon_pay_dismiss_api_migration_notice',
				'class'          => 'notice notice-error',
				'text'           => sprintf(
					/* translators: 1) The URL to the Amazon Pay settings screen. */
					'<p>' . __( 'Amazon Pay V2 is now available and migration is required. Please go to your <a href="%1$s">Amazon Pay settings</a> to configure your merchant account', 'woocommerce-gateway-amazon-payments-advanced' ) . '</p>',
					esc_url( $this->get_settings_url() )
				),
				'is_dismissable' => false,
			);
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
			<div class="notice notice-warning <?php echo $dismissable_class; ?> <?php echo esc_attr( $notice['class'] ); ?>">
				<p>
				<?php
				echo wp_kses(
					$notice['text'],
					array(
						'a'      => array(
							'href'  => array(),
							'title' => array(),
						),
						'strong' => array(),
						'em'     => array(),
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
			if ( $notice['dismiss_action'] === $_POST['dismiss_action'] ) {
				update_option( $notice['dismiss_action'], 'no' );
				break;
			}
		}
		wp_die();
	}

}
