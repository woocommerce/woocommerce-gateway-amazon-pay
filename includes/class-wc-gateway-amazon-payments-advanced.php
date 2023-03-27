<?php
/**
 * Gateway class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Implement payment method for Amazon Pay.
 */
class WC_Gateway_Amazon_Payments_Advanced extends WC_Gateway_Amazon_Payments_Advanced_Abstract {

	/**
	 * Cached object for the checkout session
	 *
	 * @var object
	 */
	protected $checkout_session;

	/**
	 * Helper to store the current refund being handled
	 *
	 * @var WC_Order_Refund
	 */
	protected $current_refund;

	/**
	 * WooCommerce's session keys that get deleted during empty_cart().
	 *
	 * @var array
	 */
	protected static $session_keys = array(
		'cart',
		'cart_totals',
		'applied_coupons',
		'coupon_discount_totals',
		'coupon_discount_tax_totals',
		'removed_cart_contents',
		'order_awaiting_payment',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		if ( $this->is_logged_in() ) {
			// This needs to be hooked early for other plugins to take advantage of it. wp_loaded is too late.
			// At this point we're on woocommerce_init.
			$wc_data = $this->get_woocommerce_data();
			foreach ( $wc_data as $field => $value ) {
				add_filter( 'woocommerce_customer_get_' . $field, array( $this, 'filter_customer_field' ) );
			}
		}

		// Init Handlers.
		add_action( 'wp_loaded', array( $this, 'init_handlers' ), 11 );
		add_action( 'woocommerce_create_refund', array( $this, 'current_refund_set' ) );
		add_action( 'wp', array( __CLASS__, 'ajax_pay_action' ), 10 );
		add_action( 'wp_ajax_amazon_change_wc_carts', array( $this, 'ajax_amazon_change_wc_carts' ) );
		add_action( 'wp_ajax_nopriv_amazon_change_wc_carts', array( $this, 'ajax_amazon_change_wc_carts' ) );

		// Mini cart regeneration needs to be hooked really early.
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'maybe_separator_and_checkout_button' ), 30 );

		// Sets the payload's addressDetails property if checking out using Amazon "Classic" and there is a physical product.
		add_filter( 'woocommerce_amazon_pa_update_checkout_session_payload', array( $this, 'update_address_details_for_classic' ), 10, 4 );
	}

	/**
	 * Amazon Pay is available if the following conditions are met (on top of
	 * WC_Payment_Gateway::is_available).
	 *
	 * 1) Merchant id exists.
	 * 2) Region supports selected currency.
	 * 2) In checkout pay page OR in product page with the product button placement enabled OR the mini-cart button placement enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		return apply_filters( 'woocommerce_amazon_pa_is_gateway_available', $this->get_availability() );
	}

	/**
	 * Returns Amazon Pay availability.
	 *
	 * @return bool
	 */
	protected function get_availability() {

		if ( ! parent::is_available() ) {
			return false;
		}

		if ( is_wp_error( WC_Amazon_Payments_Advanced_API::validate_api_settings() ) ) {
			return false;
		}

		if ( ! WC_Amazon_Payments_Advanced_API::is_region_supports_shop_currency() ) {
			return false;
		}

		return true;
	}

	/**
	 * Has fields.
	 *
	 * @return bool
	 */
	public function has_fields() {
		$has_fields = false;

		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			$has_fields = true;
		}

		return apply_filters( 'woocommerce_amazon_pa_gateway_has_fields', $has_fields );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
		}
		if ( $this->has_fields() ) {
			if ( $this->is_logged_in() ) {
				$checkout_session = $this->get_checkout_session();
				if ( ! $this->maybe_render_login_button_again( $checkout_session, false ) ) {
					return;
				}
				$this->display_payment_method_selected( $checkout_session );
				// ASK: Maybe add a note that address is not used?
				// TODO: If using addresses from checkoutSession, maybe fix shipping and billing state issues by displaying a custom form from WC.
			}
		}
	}

	/**
	 * Load handlers for cart and orders after WC Cart is loaded.
	 */
	public function init_handlers() {
		if ( is_null( WC()->cart ) ) {
			return;
		}

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( ! isset( $available_gateways[ $this->id ] ) ) {
			return;
		}

		// Scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'woocommerce_before_checkout_form', array( $this, 'checkout_message' ), 5 );
		add_action( 'before_woocommerce_pay', array( $this, 'checkout_message' ), 5 );
		add_action( 'before_woocommerce_pay', array( $this, 'remove_amazon_gateway_order_pay' ), 5 );

		// Mini cart.
		add_filter( 'woocommerce_amazon_pa_enqueue_scripts', array( $this, 'load_scripts_globally_if_button_enabled_on_cart' ), 20 );

		// Single Product.
		add_action( 'woocommerce_single_product_summary', array( $this, 'maybe_separator_and_checkout_button_single_product' ), 30 );
		add_filter( 'woocommerce_amazon_pa_enqueue_scripts', array( $this, 'load_scripts_on_product_pages' ), 10 );

		// Thank you.
		add_action( 'woocommerce_before_thankyou', array( $this, 'remember_forgotten_cart' ) );

		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'update_amazon_fragments' ) );

		if ( ! apply_filters( 'woocommerce_amazon_payments_init', true ) ) {
			add_filter( 'woocommerce_amazon_pa_is_gateway_available', '__return_false' );
			return;
		}

		add_action( 'template_redirect', array( $this, 'maybe_handle_apa_action' ) );

		// Checkout.
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'use_checkout_session_data' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'use_checkout_session_data_single' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_form', array( $this, 'classic_integration_button' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'classic_validation' ), 10, 2 );
		if ( $this->doing_ajax() ) {
			add_action( 'woocommerce_review_order_after_order_total', array( $this, 'update_js' ) );
		}

		// Pay Order.
		add_action( 'woocommerce_pay_order_after_submit', array( $this, 'classic_integration_button' ) );
		if ( $this->doing_ajax() ) {
			add_action( 'woocommerce_before_cart_totals', array( $this, 'update_js' ) );
		}

		// Cart.
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_amazon_pay_button_separator_html' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'checkout_button' ), 25 );
		if ( $this->doing_ajax() ) {
			add_action( 'woocommerce_review_order_before_order_total', array( $this, 'update_js' ) );
		}

		// Maybe Hide Cart Buttons.
		add_action( 'wp_footer', array( $this, 'maybe_hide_standard_checkout_button' ) );
		add_action( 'wp_footer', array( $this, 'maybe_hide_amazon_buttons' ) );

		add_filter( 'woocommerce_amazon_pa_checkout_session_key', array( $this, 'maybe_change_session_key' ) );
	}

	/**
	 * Add scripts
	 */
	public function scripts() {

		$js_suffix = '.min.js';
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$js_suffix = '.js';
		}

		wp_register_style( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/css/style.css', array(), wc_apa()->version );
		wp_register_script( 'amazon_payments_advanced_checkout', $this->get_region_script(), array(), wc_apa()->version, true );
		wp_register_script( 'amazon_payments_advanced', wc_apa()->plugin_url . '/assets/js/amazon-wc-checkout' . $js_suffix, array(), wc_apa()->version, true );

		$params = $this->get_js_params();

		wp_localize_script( 'amazon_payments_advanced', 'amazon_payments_advanced', $params );

		$enqueue_scripts = is_cart() || is_checkout() || is_checkout_pay_page();

		/**
		 * Careful with the order being hooked here. Should be from most specific to most generic.
		 *
		 * @hooked load_scripts_on_product_pages                   - 10
		 * @hooked load_scripts_globally_if_button_enabled_on_cart - 20
		 */
		if ( ! apply_filters( 'woocommerce_amazon_pa_enqueue_scripts', $enqueue_scripts ) ) {
			return;
		}

		$this->enqueue_scripts();

	}

	/**
	 * Returns the params used by JS.
	 *
	 * @return array
	 */
	protected function get_js_params() {
		$checkout_session_config = WC_Amazon_Payments_Advanced_API::get_create_checkout_session_config();

		$params = array();
		if ( ! empty( $this->settings['product_button'] ) && 'yes' === $this->settings['product_button'] ) {
			$params['change_cart_ajax_nonce'] = wp_create_nonce( 'amazon_change_wc_carts' );
			$params['change_cart_action']     = 'amazon_change_wc_carts';
			$params['product_action']         = $this->get_current_product_action();
		}

		return array_merge(
			array(
				'ajax_url'                       => admin_url( 'admin-ajax.php' ),
				'create_checkout_session_config' => $checkout_session_config,
				'create_checkout_session_hash'   => wp_hash( $checkout_session_config['payloadJSON'] . self::get_estimated_order_amount() ),
				'button_color'                   => $this->settings['button_color'],
				'placement'                      => $this->get_current_placement(),
				'action'                         => $this->get_current_cart_action(),
				'sandbox'                        => 'yes' === $this->settings['sandbox'],
				'merchant_id'                    => $this->settings['merchant_id'],
				'shipping_title'                 => esc_html__( 'Shipping details', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
				'checkout_session_id'            => $this->get_checkout_session_id(),
				'button_language'                => $this->settings['button_language'],
				'ledger_currency'                => $this->get_ledger_currency(),
				'estimated_order_amount'         => self::get_estimated_order_amount(),
			),
			$params
		);
	}

	/**
	 * Actually enqueue scripts if needed.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'amazon_payments_advanced' );
		wp_enqueue_script( 'amazon_payments_advanced_checkout' );
		wp_enqueue_script( 'amazon_payments_advanced' );
		if ( WC()->session->amazon_checkout_do_logout ) {
			wp_add_inline_script( 'amazon_payments_advanced', 'amazon.Pay.signout();' );
			unset( WC()->session->amazon_checkout_do_logout );
		}
	}

	/**
	 * Get button placement string (to send to the JS)
	 *
	 * @return string Either Cart, Checkout or Other
	 */
	protected function get_current_placement() {
		if ( is_cart() ) {
			return 'Cart';
		}

		if ( is_checkout() || is_checkout_pay_page() ) {
			return 'Checkout';
		}

		if ( is_product() ) {
			return 'Product';
		}

		if ( is_front_page() ) {
			return 'Home';
		}

		return 'Other';
	}

	/**
	 * Get regional version of the checkout.js script
	 *
	 * @return string URL of the script
	 */
	protected function get_region_script() {
		$region = WC_Amazon_Payments_Advanced_API::get_region();

		$url = false;
		switch ( strtolower( $region ) ) {
			case 'us':
				$url = 'https://static-na.payments-amazon.com/checkout.js';
				break;
			case 'gb':
			case 'eu':
				$url = 'https://static-eu.payments-amazon.com/checkout.js';
				break;
			case 'jp':
				$url = 'https://static-fe.payments-amazon.com/checkout.js';
				break;
		}

		return $url;
	}

	/**
	 * Get ledger currency
	 *
	 * @return bool|string Returns the currency code for the region ledger, or false if the region is invalid.
	 */
	protected function get_ledger_currency() {
		$region = WC_Amazon_Payments_Advanced_API::get_region();

		switch ( strtolower( $region ) ) {
			case 'us':
				return 'USD';
			case 'gb':
				return 'GBP';
			case 'eu':
				return 'EUR';
			case 'jp':
				return 'JPY';
		}

		return false;
	}

	/**
	 * Display payment request button separator.
	 *
	 * @since 2.0.0
	 */
	public function display_amazon_pay_button_separator_html() {
		?>
		<p class="wc-apa-button-separator">&mdash; <?php esc_html_e( 'OR', 'woocommerce-gateway-amazon-payments-advanced' ); ?> &mdash;</p>
		<?php
	}

	/**
	 * Will maybe create the buyer index table.
	 */
	public function maybe_create_index_table() {
		// TODO: Do a better check here to make this less heavy.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$query = "
		CREATE TABLE {$wpdb->prefix}woocommerce_amazon_buyer_index (
			buyer_id varchar(100) NOT NULL,
			customer_id bigint(20) NOT NULL,
			PRIMARY KEY (buyer_id,customer_id),
			UNIQUE KEY customer_id (customer_id)
		  ) $collate;";

		$queries = dbDelta( $query, false );

		if ( ! empty( $queries ) ) {
			dbDelta( $query );
		}
	}

	/**
	 * Gets a customer ID from the buyer ID
	 *
	 * @param  mixed $buyer_id Buyer ID.
	 * @return bool|int The Customer ID
	 */
	public function get_customer_id_from_buyer( $buyer_id ) {
		global $wpdb;
		$this->maybe_create_index_table();

		$customer_id = $wpdb->get_var( $wpdb->prepare( "SELECT customer_id FROM {$wpdb->prefix}woocommerce_amazon_buyer_index WHERE buyer_id = %s", $buyer_id ) );

		return ! empty( $customer_id ) ? intval( $customer_id ) : false;
	}

	/**
	 * Stores in the index the buyer ID for a specific Customer ID.
	 *
	 * @param  string $buyer_id Buyer ID.
	 * @param  int    $customer_id WC Customer ID.
	 * @return bool True on success, false on failure.
	 */
	public function set_customer_id_for_buyer( $buyer_id, $customer_id ) {
		global $wpdb;
		$this->maybe_create_index_table();

		$inserted = $wpdb->insert(
			"{$wpdb->prefix}woocommerce_amazon_buyer_index",
			array(
				'buyer_id'    => $buyer_id,
				'customer_id' => $customer_id,
			)
		);

		if ( ! $inserted ) {
			return false;
		}

		return true;
	}

	/**
	 * Add filter that will self remove and will validate data entered to validate ownership of an account already created.
	 */
	public function signal_account_hijack() {
		add_filter( 'woocommerce_checkout_customer_id', array( $this, 'handle_account_registration' ) );
	}

	/**
	 * Validate data entered to verify ownership of an account already created.
	 *
	 * @throws Exception When there's an error.
	 *
	 * @param  int $customer_id WC Customer ID.
	 * @return int Return the customer id
	 */
	public function handle_account_registration( $customer_id ) {
		// unhook ourselves, since we only need this after checkout started, not every time.
		remove_filter( 'woocommerce_checkout_customer_id', array( $this, 'handle_account_registration' ) );

		$checkout = WC()->checkout();
		$data     = $checkout->get_posted_data();

		if ( $customer_id && ! empty( $data['amazon_link'] ) ) {
			$checkout_session = $this->get_checkout_session();
			$buyer_id         = $checkout_session->buyer->buyerId;

			$buyer_user_id = $this->get_customer_id_from_buyer( $buyer_id );
			if ( ! $buyer_user_id ) {
				$this->set_customer_id_for_buyer( $buyer_id, $customer_id );
			}
		}

		if ( $customer_id ) { // Already registered, or logged in. Return normally.
			return $customer_id;
		}

		// FROM: WC_Checkout->process_customer.
		if ( ! is_user_logged_in() && ( $checkout->is_registration_required() || ! empty( $data['createaccount'] ) ) ) {
			$checkout_session = $this->get_checkout_session();
			$buyer_id         = $checkout_session->buyer->buyerId;
			$buyer_email      = $checkout_session->buyer->email;
			$buyer_user_id    = $this->get_customer_id_from_buyer( $buyer_id );

			if ( isset( $data['amazon_validate'] ) ) {
				if ( $buyer_user_id ) {
					return $customer_id; // We shouldn't be here anyways.
				}
				$user_id = email_exists( $buyer_email );
				if ( ! $user_id ) {
					return $customer_id; // We shouldn't be here anyways.
				}

				if ( empty( $data['amazon_validate'] ) ) {
					throw new Exception( __( 'You did not enter the password to validate your account. If you want, you can continue as guest.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				}

				$user = get_user_by( 'id', $user_id );

				if ( ! wp_check_password( $data['amazon_validate'], $user->user_pass, $user->ID ) ) {
					throw new Exception( __( 'The password you entered did not match the one on the account. Try again, or continue as guest.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				}

				$customer_id = $user_id;

				$this->set_customer_id_for_buyer( $buyer_id, $customer_id );
			}

			if ( ! $customer_id ) {
				$username = ! empty( $data['account_username'] ) ? $data['account_username'] : '';
				$password = ! empty( $data['account_password'] ) ? $data['account_password'] : '';

				$customer_id = wc_create_new_customer(
					$data['billing_email'],
					$username,
					$password,
					array(
						'first_name' => ! empty( $data['billing_first_name'] ) ? $data['billing_first_name'] : '',
						'last_name'  => ! empty( $data['billing_last_name'] ) ? $data['billing_last_name'] : '',
					)
				);

				if ( is_wp_error( $customer_id ) ) {
					throw new Exception( $customer_id->get_error_message() );
				}

				$this->set_customer_id_for_buyer( $buyer_id, $customer_id );
			}

			wc_set_customer_auth_cookie( $customer_id );

			// As we are now logged in, checkout will need to refresh to show logged in data.
			WC()->session->set( 'reload_checkout', true );

			// Also, recalculate cart totals to reveal any role-based discounts that were unavailable before registering.
			WC()->cart->calculate_totals();
		}

		return $customer_id;
	}

	/**
	 * Generate the HTML for the extra fields required for validation
	 *
	 * @param  string $html HTML to be rendered.
	 * @return string
	 */
	public function print_validate_button( $html ) {
		$html  = '<p class="form-row" id="amazon_validate_notice_field" data-priority="">';
		$html .= __( 'An account with your Amazon Pay email address exists already. Is that you? If so, enter your password below.', 'woocommerce-gateway-amazon-payments-advanced' );
		$html .= '</p>';
		return $html;
	}

	/**
	 * Initialize hooks when on checkout
	 *
	 * @param  WC_Checkout $checkout WC_Checkout instance.
	 */
	public function checkout_init( $checkout ) {

		/**
		 * Make sure this is checkout initiated from front-end where cart exists.
		 *
		 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/238
		 */
		if ( ! WC()->cart ) {
			return;
		}

		if ( ! $this->is_logged_in() ) {
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_amazon_gateway' ) );
			return;
		}

		add_action( 'woocommerce_checkout_process', array( $this, 'signal_account_hijack' ) );
		add_filter( 'woocommerce_form_field_amazon_validate_notice', array( $this, 'print_validate_button' ), 10 );

		// If all prerequisites are meet to be an amazon checkout.
		do_action( 'woocommerce_amazon_checkout_init' );

		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'display_amazon_customer_info' ) );

		// The default checkout form uses the billing email for new account creation
		// Let's hijack that field for the Amazon-based checkout.
		if ( apply_filters( 'woocommerce_pa_hijack_checkout_fields', true ) ) {
			$this->hijack_checkout_fields( $checkout );
		}
	}

	/**
	 * Prints the Amazon Pay button on the mini cart.
	 *
	 * When the setting is on and there are products in cart.
	 *
	 * @hooked on 'woocommerce_widget_shopping_cart_buttons'
	 *
	 * @return void
	 */
	public function maybe_separator_and_checkout_button() {
		if ( $this->is_available() && $this->possible_subscription_cart_supported() && $this->is_mini_cart_button_enabled() && WC()->cart->get_cart_contents_count() > 0 ) {
			?>
			<div class="woocommerce-mini-cart__buttons buttons">
				<?php
				$this->display_amazon_pay_button_separator_html();
				$this->checkout_button( true, 'div', 'pay_with_amazon_cart' );
				$this->update_js( 'wc-apa-update-vals-cart' );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Prints the Amazon Pay button on the product page when the setting is on.
	 *
	 * @hooked on 'woocommerce_single_product_summary' on 35 priority
	 *
	 * @return void
	 */
	public function maybe_separator_and_checkout_button_single_product() {
		if ( $this->is_available() && $this->possible_subscription_product_supported() && $this->is_product_button_enabled() ) {
			$this->checkout_button( true, 'div', 'pay_with_amazon_product' );
		}
	}

	/**
	 * Checkout Message
	 */
	public function checkout_message() {
		$class = array( 'wc-amazon-checkout-message' );
		if ( $this->is_available() ) {
			$class[] = 'wc-amazon-payments-advanced-populated';
		}
		$class = implode( ' ', $class );
		echo '<div class="' . esc_attr( $class ) . '">';

		if ( $this->is_available() ) {
			if ( ! $this->is_logged_in() ) {
				echo '<div class="woocommerce-info info wc-amazon-payments-advanced-info">' . $this->checkout_button( false ) . ' ' . apply_filters( 'woocommerce_amazon_pa_checkout_message', __( 'Have an Amazon account?', 'woocommerce-gateway-amazon-payments-advanced' ) ) . '</div>';
			} else {
				$this->logout_checkout_message();
			}
		}

		echo '</div>';

	}

	/**
	 * Print logout notice at the top of the checkout.
	 */
	public function logout_checkout_message() {
		$logout_url      = $this->get_amazon_logout_url();
		$logout_msg_html = '<div class="woocommerce-info info">' . apply_filters( 'woocommerce_amazon_pa_checkout_logout_message', __( 'You\'re logged in with your Amazon Account.', 'woocommerce-gateway-amazon-payments-advanced' ) ) . ' <a href="' . esc_url( $logout_url ) . '" id="amazon-logout">' . __( 'Log out &raquo;', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a></div>';
		echo apply_filters( 'woocommerce_amazon_payments_logout_checkout_message_html', $logout_msg_html );
	}

	/**
	 * Returns the checkout session key to use
	 *
	 * @return string
	 */
	protected function get_checkout_session_key() {
		return apply_filters( 'woocommerce_amazon_pa_checkout_session_key', 'amazon_checkout_session_id' );
	}

	/**
	 * Actually logout the user from Amazon.
	 */
	protected function do_logout() {
		$session_key = $this->get_checkout_session_key();
		unset( WC()->session->$session_key );
		WC()->session->amazon_checkout_do_logout = true;
		do_action( 'woocommerce_amazon_pa_logout' );
	}

	/**
	 * Signal a force refresh of the checkout session is required.
	 *
	 * @param  string $reason Reason to do the refresh for.
	 */
	protected function do_force_refresh( $reason ) {
		WC()->session->force_refresh_message = $reason;
	}

	/**
	 * Returns the reason a force refresh is required.
	 *
	 * @return null|string Null if there's no reason, or the reason string.
	 */
	protected function get_force_refresh() {
		return WC()->session->force_refresh_message;
	}

	/**
	 * Remove the signal for a force refresh.
	 */
	protected function unset_force_refresh() {
		unset( WC()->session->force_refresh_message );
	}

	/**
	 * Check if we need to force refresh.
	 *
	 * @return bool True if we need to force refresh, false if not.
	 */
	protected function need_to_force_refresh() {
		return ! is_null( WC()->session->force_refresh_message );
	}

	/**
	 * Handle the Amazon Payments actions (login, logout, return)
	 */
	public function maybe_handle_apa_action() {

		if ( empty( $_GET['amazon_payments_advanced'] ) ) {
			return;
		}

		$parts        = wp_parse_url( home_url() );
		$path         = ! empty( $parts['path'] ) ? $parts['path'] : '';
		$redirect_url = "{$parts['scheme']}://{$parts['host']}{$path}" . remove_query_arg( array( 'amazon_payments_advanced' ) );

		if ( ! empty( $_GET['amazon_return_classic'] ) && ! empty( $_GET['amazonCheckoutSessionId'] ) ) {
			$redirect_url = remove_query_arg( array( 'amazon_return_classic', 'amazonCheckoutSessionId' ), $redirect_url );
			$this->handle_return( $_GET['amazonCheckoutSessionId'] );
			// If we didn't redirect and quit yet, lets force redirect to checkout.
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( is_null( WC()->session ) ) {
			return;
		}

		if ( isset( $_GET['amazon_logout'] ) ) {
			$redirect_url = remove_query_arg( array( 'amazon_logout' ), $redirect_url );
			$this->do_logout();
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_GET['amazon_login'] ) && isset( $_GET['amazonCheckoutSessionId'] ) ) {
			$redirect_url = remove_query_arg( array( 'amazon_login', 'amazonCheckoutSessionId' ), $redirect_url );
			$session_key  = $this->get_checkout_session_key();
			WC()->session->set( $session_key, $_GET['amazonCheckoutSessionId'] );
			$this->unset_force_refresh();
			WC()->session->save_data();

			if ( ! is_user_logged_in() ) {
				$checkout_session = $this->get_checkout_session();
				$buyer_id         = $checkout_session->buyer->buyerId;
				$buyer_email      = $checkout_session->buyer->email;

				$buyer_user_id = $this->get_customer_id_from_buyer( $buyer_id );

				if ( ! empty( $buyer_user_id ) ) {
					wc_set_customer_auth_cookie( $buyer_user_id );
				}
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( isset( $_GET['amazon_return'] ) && isset( $_GET['amazonCheckoutSessionId'] ) ) {
			$redirect_url = remove_query_arg( array( 'amazon_return', 'amazonCheckoutSessionId' ), $redirect_url );
			if ( $_GET['amazonCheckoutSessionId'] !== $this->get_checkout_session_id() ) {
				wc_add_notice( __( 'There was an error after returning from Amazon. Please try again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$this->handle_return();
			// If we didn't redirect and quit yet, lets force redirect to checkout.
			wp_safe_redirect( $redirect_url );
			exit;
		}

	}

	/**
	 * Get the checkout session id
	 *
	 * @return bool|string False if no session is active, the session id otherwise.
	 */
	public function get_checkout_session_id() {
		$session_key = $this->get_checkout_session_key();
		return ! empty( WC()->session ) ? WC()->session->get( $session_key, false ) : false;
	}

	/**
	 * Get the checkout session object
	 *
	 * @param  mixed       $force Wether to force read from amazon, or use the cached data if available.
	 * @param  null|string $checkout_session_id The checkout session id if it exists.
	 * @return object the Checkout Session Object from Amazon API
	 */
	public function get_checkout_session( $force = false, $checkout_session_id = null ) {
		if ( ! $force && null !== $this->checkout_session ) {
			return $this->checkout_session;
		}

		$this->checkout_session = WC_Amazon_Payments_Advanced_API::get_checkout_session_data( $checkout_session_id ? $checkout_session_id : $this->get_checkout_session_id() );
		return $this->checkout_session;
	}

	/**
	 * Check wether the user is logged in to amazon or not.
	 *
	 * @return bool
	 */
	protected function is_logged_in() {
		if ( is_null( WC()->session ) ) {
			return false;
		}

		$session_id = $this->get_checkout_session_id();

		return ! empty( $session_id ) ? true : false;
	}

	/**
	 * Hijack the checkout fields when logged in to amazon.
	 *
	 * @param  WC_Checkout $checkout WC_Checkout instance.
	 */
	public function hijack_checkout_fields( $checkout ) {
		$this->hijack_checkout_field_account( $checkout );

		// During an Amazon checkout, the standard billing and shipping fields need to be
		// "removed" so that we don't trigger a false negative on form validation -
		// they can be empty since we're using the Amazon widgets.

		// The following fields cannot be optional for WC compatibility reasons.
		$required_fields = array( 'billing_first_name', 'billing_last_name', 'billing_email' );
		// If the order does not require shipping, these fields can be optional.
		$optional_fields = array(
			'billing_company',
			'billing_country',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_phone',
		);
		$all_fields      = array_merge( $required_fields, $optional_fields );
		$checkout_fields = version_compare( WC_VERSION, '3.0', '>=' )
			? $checkout->get_checkout_fields()
			: $checkout->checkout_fields;

		$session_wc_format = $this->get_woocommerce_data();

		$missing  = array();
		$present  = array();
		$optional = array();
		foreach ( $all_fields as $key ) {
			if ( ! empty( $checkout_fields['billing'][ $key ]['required'] ) ) {
				if ( ! isset( $session_wc_format[ $key ] ) ) {
					$missing[] = $key;
				} else {
					$present[] = $key;
				}
			} else {
				$optional[] = $key;
			}
		}

		if ( ! empty( $present ) ) {
			$this->add_hidden_class_to_fields( $checkout_fields['billing'], array_merge( $present, $optional ) );
		}

		$field_list = array(
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_country',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
		);

		$missing  = array();
		$present  = array();
		$optional = array();
		foreach ( $field_list as $key ) {
			if ( ! empty( $checkout_fields['shipping'][ $key ]['required'] ) ) {
				if ( ! isset( $session_wc_format[ $key ] ) ) {
					$missing[] = $key;
				} else {
					$present[] = $key;
				}
			} else {
				$optional[] = $key;
			}
		}

		if ( ! empty( $present ) ) {
			$this->add_hidden_class_to_fields( $checkout_fields['shipping'], array_merge( $present, $optional ) );
		}

		$checkout->checkout_fields = $checkout_fields;
	}

	/**
	 * Alter account checkout field.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Checkout $checkout WC_Checkout instance.
	 */
	protected function hijack_checkout_field_account( $checkout ) {
		if ( is_user_logged_in() ) {
			return; // There's nothing to do here if the user is logged in.
		}

		$checkout_session = $this->get_checkout_session();
		$buyer_id         = $checkout_session->buyer->buyerId;
		$buyer_email      = $checkout_session->buyer->email;

		$buyer_user_id = $this->get_customer_id_from_buyer( $buyer_id );

		if ( $buyer_user_id ) {
			return; // We shouldn't be here anyways.
		}

		$user_id = email_exists( $buyer_email );
		if ( ! $user_id ) {
			return; // We shouldn't be here anyways.
		}

		/**
		 * WC 3.0 changes a bit a way to retrieve fields.
		 *
		 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/217
		 */
		$checkout_fields = version_compare( WC_VERSION, '3.0', '>=' )
			? $checkout->get_checkout_fields()
			: $checkout->checkout_fields;

		$checkout_fields['account'] = array();

		$checkout_fields['account']['amazon_validate_notice'] = array(
			'type' => 'amazon_validate_notice',
		);

		$checkout_fields['account']['amazon_validate'] = array(
			'type'     => 'password',
			'label'    => __( 'Password', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
			'required' => true,
		);

		$checkout->checkout_fields = $checkout_fields;
	}

	/**
	 * Adds hidden class to checkout field
	 *
	 * @param array $field reference to the field to be hidden.
	 */
	protected function add_hidden_class_to_field( &$field ) {
		if ( isset( $field['class'] ) ) {
			array_push( $field['class'], 'hidden' );
		} else {
			$field['class'] = array( 'hidden' );
		}
	}

	/**
	 * Adds hidden class to checkout fields based on a list
	 *
	 * @param array $checkout_fields reference to checkout fields.
	 * @param array $field_list fields to be hidden.
	 */
	protected function add_hidden_class_to_fields( &$checkout_fields, $field_list ) {
		foreach ( $field_list as $field ) {
			$this->add_hidden_class_to_field( $checkout_fields[ $field ] );
		}
	}

	/**
	 * Render the selected payment method information widget.
	 *
	 * @param  null|object $checkout_session Checkout session object to use.
	 */
	public function display_payment_method_selected( $checkout_session = null ) {
		if ( null === $checkout_session ) {
			$checkout_session = $this->get_checkout_session();
		}
		?>
		<div id="payment_method_widget">
			<?php
			$change_label = esc_html__( 'Change', 'woocommerce-gateway-amazon-payments-advanced' );
			if ( ! $this->has_payment_preferences( $checkout_session ) ) {
				$change_label = esc_html__( 'Select', 'woocommerce-gateway-amazon-payments-advanced' );
			}

			?>
			<h3>
				<a href="#" class="wc-apa-widget-change" id="payment_method_widget_change"><?php echo $change_label; ?></a>
				<?php esc_html_e( 'Payment Method', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
			</h3>
			<div class="payment_method_display">
				<span class="wc-apa-amazon-logo"></span><?php echo esc_html( $this->get_selected_payment_label( $checkout_session ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Returns whether there are payment preferences set in the checkout
	 * session object.
	 *
	 * @param  object $checkout_session The active checkout session.
	 * @return boolean
	 */
	public function has_payment_preferences( $checkout_session = null ) {
		if ( null === $checkout_session ) {
			$checkout_session = $this->get_checkout_session();
		}

		if ( ! $checkout_session || is_wp_error( $checkout_session ) ) {
			return false;
		}

		return ! empty( $checkout_session->paymentPreferences ); // phpcs:ignore WordPress.NamingConventions
	}

	/**
	 * Returns the selected payment method from Amazon.
	 *
	 * @param object $checkout_session The active checkout session.
	 * @return string
	 */
	public function get_selected_payment_label( $checkout_session = null ) {
		if ( null === $checkout_session ) {
			$checkout_session = $this->get_checkout_session();
		}

		$default = esc_html__( 'Your selected Amazon payment method', 'woocommerce-gateway-amazon-payments-advanced' );

		if ( ! $checkout_session || is_wp_error( $checkout_session ) ) {
			return $default;
		}

		if ( empty( $checkout_session->paymentPreferences ) ) { // phpcs:ignore WordPress.NamingConventions
			return $default;
		}

		$selected_label = '';

		foreach ( $checkout_session->paymentPreferences as $pref ) { // phpcs:ignore WordPress.NamingConventions
			if ( isset( $pref->paymentDescriptor ) ) { // phpcs:ignore WordPress.NamingConventions
				$selected_label = $pref->paymentDescriptor; // phpcs:ignore WordPress.NamingConventions
				break;
			}
		}

		return $selected_label ? $selected_label : $default;
	}

	/**
	 * Render billing address information widget
	 *
	 * @param  null|object $checkout_session Checkout session object to use.
	 */
	public function display_billing_address_selected( $checkout_session = null ) {
		if ( null === $checkout_session ) {
			$checkout_session = $this->get_checkout_session();
		}
		if ( ! empty( $checkout_session->billingAddress ) ) : // phpcs:ignore WordPress.NamingConventions
			?>
			<div id="billing_address_widget">
				<h3>
					<?php esc_html_e( 'Billing Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
				</h3>
				<div class="billing_address_display">
					<?php echo WC()->countries->get_formatted_address( WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->billingAddress ) ); // phpcs:ignore WordPress.NamingConventions ?>
				</div>
			</div>
			<?php
		endif;
	}

	/**
	 * Render shipping address information widget.
	 *
	 * @param  null|object $checkout_session Checkout session object to use.
	 */
	public function display_shipping_address_selected( $checkout_session = null ) {
		if ( is_null( $checkout_session ) ) {
			$checkout_session = $this->get_checkout_session();
		}
		if ( ! empty( $checkout_session->shippingAddress ) ) : // phpcs:ignore WordPress.NamingConventions
			?>
			<div id="shipping_address_widget">
				<h3>
					<a href="#" class="wc-apa-widget-change" id="shipping_address_widget_change">
					<?php esc_html_e( 'Change', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
					</a>
					<?php esc_html_e( 'Shipping Address', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
				</h3>
				<div class="shipping_address_display">
					<?php echo WC()->countries->get_formatted_address( WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ) ); // phpcs:ignore WordPress.NamingConventions ?>
				</div>
			</div>
			<?php
		endif;
	}

	/**
	 * Render layout for Amazon Checkout
	 */
	public function display_amazon_customer_info() {

		if ( $this->need_to_force_refresh() ) {
			$this->render_login_button_again( $this->get_force_refresh() );
			return;
		}

		$checkout_session = $this->get_checkout_session();

		if ( ! $this->maybe_render_login_button_again( $checkout_session ) ) {
			return;
		}

		$checkout = WC_Checkout::instance();
		// phpcs:disable WordPress.NamingConventions
		?>
		<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated">
			<div class="col2-set">
				<div class="col-1 <?php echo empty( $checkout_session->shippingAddress ) ? 'hidden' : ''; ?>">
					<?php $this->display_shipping_address_selected( $checkout_session ); ?>
				</div>
				<div class="col-2">
					<?php $this->display_payment_method_selected( $checkout_session ); ?>
					<?php $this->display_billing_address_selected( $checkout_session ); ?>
				</div>

				<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>

					<div id="wc-apa-account-fields-anchor"></div>

				<?php endif; ?>

				<?php if ( is_user_logged_in() ) : ?>
					<?php
					$checkout_session = $this->get_checkout_session();
					$buyer_id         = $checkout_session->buyer->buyerId;
					$buyer_email      = $checkout_session->buyer->email;

					$buyer_user_id = $this->get_customer_id_from_buyer( $buyer_id );
					?>
					<?php if ( ! $buyer_user_id ) : ?>
						<div class="woocommerce-account-fields">
							<div class="link-account">
								<?php
								$key   = 'amazon_link';
								$value = $checkout->get_value( $key );
								if ( empty( $value ) ) {
									$value = '1';
								}
								woocommerce_form_field(
									$key,
									array(
										'type'  => 'checkbox',
										'label' => __( 'Link Amazon Pay Account', 'woocommerce-gateway-amazon-payments-advanced' ),
									),
									$value
								);
								?>
								<p><?php _e( 'By checking this box, every time you will log in with the same Amazon account, you will also be logged in with your existing shop account.', 'woocommerce-gateway-amazon-payments-advanced' ); ?></p>
								<div class="clear"></div>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<?php
		// phpcs:enable WordPress.NamingConventions
	}

	/**
	 * Get a WC Compatible version of address data from the current checkout session.
	 *
	 * @return array
	 */
	protected function get_woocommerce_data() {
		// TODO: Store in session for performance, always clear when coming back from AMZ.
		$checkout_session_id = $this->get_checkout_session_id();
		if ( empty( $checkout_session_id ) ) {
			return array();
		}

		$checkout_session = $this->get_checkout_session();

		$data = array();

		if ( ! empty( $checkout_session->buyer ) ) {
			// Billing.
			$wc_billing_address = array();
			if ( ! empty( $checkout_session->billingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
				$wc_billing_address = WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->billingAddress ); // phpcs:ignore WordPress.NamingConventions
			} else {
				if ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
					$wc_billing_address = WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ); // phpcs:ignore WordPress.NamingConventions
				} else {
					$wc_billing_address = WC_Amazon_Payments_Advanced_API::format_name( $checkout_session->buyer->name );
				}
			}
			if ( ! empty( $checkout_session->buyer->email ) ) {
				$wc_billing_address['email'] = $checkout_session->buyer->email;
			}
			foreach ( $wc_billing_address as $field => $val ) {
				$data[ 'billing_' . $field ] = $val;
			}

			$wc_shipping_address = array();
			if ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
				$wc_shipping_address = WC_Amazon_Payments_Advanced_API::format_address( $checkout_session->shippingAddress ); // phpcs:ignore WordPress.NamingConventions
			}

			// Shipping.
			foreach ( $wc_shipping_address as $field => $val ) {
				$data[ 'shipping_' . $field ] = $val;
			}
		}

		return $data;
	}

	/**
	 * Filters the session data and replaces relevant data with information from the Checkout Session
	 *
	 * @param  array $data Data from WooCommerce.
	 * @return array
	 */
	public function use_checkout_session_data( $data ) {
		if ( $data['payment_method'] !== $this->id ) {
			return $data;
		}

		$formatted_session_data = $this->get_woocommerce_data();

		$data = array_merge( $data, array_intersect_key( $formatted_session_data, $data ) ); // only set data that exists in data.

		if ( isset( $_REQUEST['amazon_link'] ) ) {
			$data['amazon_link'] = $_REQUEST['amazon_link'];
		}

		return $data;
	}

	/**
	 * Filter a single value for session data.
	 *
	 * @param  mixed $ret Previous value.
	 * @param  mixed $input Field being retrieved.
	 * @return mixed
	 */
	public function use_checkout_session_data_single( $ret, $input ) {
		if ( ! WC()->cart->needs_payment() ) {
			return $ret;
		}

		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		if ( ! isset( $available_gateways[ $this->id ] ) ) {
			return $ret;
		}

		if ( true !== $available_gateways[ $this->id ]->chosen ) {
			return $ret;
		}

		if ( ! $this->is_logged_in() ) {
			return $ret;
		}

		switch ( $input ) {
			case 'amazon_link':
				if ( isset( $_REQUEST[ $input ] ) ) {
					return $_REQUEST[ $input ];
				}
				break;
			default:
				$session = $this->get_woocommerce_data();

				if ( isset( $session[ $input ] ) ) {
					return $session[ $input ];
				}
				break;
		}

		return $ret;
	}

	/**
	 * Checks that a phone has been provided when using Amazon Pay "Classic"
	 *
	 * @param array    $data   The submitted user data.
	 * @param WP_Error $errors Validation errors.
	 * @return void
	 */
	public function classic_validation( $data, $errors ) {
		/* If any of those is true equivalent. This isn't Amazon "Classic", so we bail. */
		if ( empty( $data['payment_method'] ) || 'amazon_payments_advanced' !== $data['payment_method'] || $this->get_checkout_session_id() ) {
			return;
		}

		// If its virtual only bail.
		if ( 'PayOnly' === $this->get_current_cart_action() ) {
			return;
		}

		if ( ! empty( $data['billing_phone'] ) || ! empty( $data['shipping_phone'] ) ) {
			return;
		}

		$errors->add( 'amazon-pay-classic', __( 'A phone number is required to complete your checkout through Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' ) );
	}

	/**
	 * Process payment.
	 *
	 * @version 2.0.0
	 *
	 * @throws Exception On errors.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_payment( $order_id ) {
		$process = apply_filters( 'woocommerce_amazon_pa_process_payment', null, $order_id );
		if ( ! is_null( $process ) ) {
			return $process;
		}

		$order = wc_get_order( $order_id );

		$checkout_session_id = $this->get_checkout_session_id();

		$checkout_session = $this->get_checkout_session();

		$payments = ! empty( $checkout_session->paymentPreferences ) ? $checkout_session->paymentPreferences : false; // phpcs:ignore WordPress.NamingConventions

		try {
			if ( ! $order ) {
				throw new Exception( __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			$doing_classic_payment = empty( $checkout_session_id );

			if ( empty( $payments ) && ! $doing_classic_payment ) {
				throw new Exception( __( 'An Amazon Pay payment method was not chosen.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			/**
			 * ASK: We could change billing and shipping address, but that could affect shipping costs calculation.
			 * They can change, but that would be a big change on top of the way WC does things, and as such, could
			 * carry compatibility issues with shipping methods, and how they do calculations.
			 *
			 * For now, we're keeping the billing and shipping address unchanged.
			 *
			 * if ( is_wc_endpoint_url( 'order-pay' ) ) {
			 *
			 * }
			 */

			$order_total = WC_Amazon_Payments_Advanced::format_amount( $order->get_total() );
			$currency    = wc_apa_get_order_prop( $order, 'order_currency' );
			$payload     = array();

			if ( $doing_classic_payment ) {
				$payload = WC_Amazon_Payments_Advanced_API::create_checkout_session_classic_params( $order->get_checkout_payment_url() );
				wc_apa()->log( "Info: Beginning processing of payment for order {$order_id} for the amount of {$order_total} {$currency}. Checkout Session ID not available yet." );
			} else {
				wc_apa()->log( "Info: Beginning processing of payment for order {$order_id} for the amount of {$order_total} {$currency}. Checkout Session ID: {$checkout_session_id}." );
			}

			$order->update_meta_data( 'amazon_payment_advanced_version', WC_AMAZON_PAY_VERSION ); // ASK: ask if WC 2.6 support is still needed (it's a 2017 release).
			$order->update_meta_data( 'woocommerce_version', WC()->version );

			$payment_intent = 'AuthorizeWithCapture';
			switch ( $this->settings['payment_capture'] ) {
				case 'authorize':
					$payment_intent = 'Authorize';
					break;
				case 'manual':
					$payment_intent = 'Confirm';
					break;
			}

			$can_do_async = false;
			if ( 'async' === $this->settings['authorization_mode'] && 'authorize' === $this->settings['payment_capture'] ) {
				$can_do_async = true;
			}

			$payload['paymentDetails'] = array_merge(
				isset( $payload['paymentDetails'] ) && is_array( $payload['paymentDetails'] ) ? $payload['paymentDetails'] : array(),
				array(
					'paymentIntent'                 => $payment_intent,
					'canHandlePendingAuthorization' => $can_do_async,
					// "softDescriptor" => "Descriptor", // TODO: Implement setting, if empty, don't set this. ONLY FOR AuthorizeWithCapture
					'chargeAmount'                  => array(
						'amount'       => $order_total,
						'currencyCode' => $currency,
					),
				)
			);

			$payload['merchantMetadata'] = WC_Amazon_Payments_Advanced_API::get_merchant_metadata( $order_id );

			$payload = apply_filters( 'woocommerce_amazon_pa_update_checkout_session_payload', $payload, $checkout_session_id, $order, $doing_classic_payment );

			if ( ! $doing_classic_payment ) {
				wc_apa()->log( "Updating checkout session data for #{$order_id}." );

				$response = WC_Amazon_Payments_Advanced_API::update_checkout_session_data(
					$checkout_session_id,
					$payload
				);

				if ( is_wp_error( $response ) ) {
					wc_apa()->log( "Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}", $response );
					wc_add_notice( __( 'There was an error while processing your payment. Your payment method was not charged. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
					return array();
				}

				if ( ! empty( $response->constraints ) ) {
					wc_apa()->log( "Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}.", $response->constraints );
					wc_add_notice( __( 'There was an error while processing your payment. Your payment method was not charged. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
					return array();
				}
				$redirect = $response->webCheckoutDetails->amazonPayRedirectUrl; // phpcs:ignore WordPress.NamingConventions
			} else {
				$create_checkout_config = WC_Amazon_Payments_Advanced_API::get_create_checkout_classic_session_config( $payload );
				$order->update_status( 'pending', __( 'Awaiting payment.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				wc_apa()->log( "Creating checkout config for order #{$order_id}.", $create_checkout_config );
				$redirect = '#amazon-pay-classic-id-that-should-not-exist';
			}

			$order->save();

			// Return thank you page redirect.
			return array_merge(
				array(
					'result'   => 'success',
					'redirect' => $redirect,
				),
				( $doing_classic_payment ? array(
					'amazonCreateCheckoutParams' => wp_json_encode( $create_checkout_config ),
					'amazonEstimatedOrderAmount' => self::get_estimated_order_amount(),
				) : array() )
			);

		} catch ( Exception $e ) {
			wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(), 'error' );
		}
		return array();
	}

	/**
	 * Handle the return from amazon after a confirmed checkout.
	 *
	 * @param string $checkout_session_id The checkout session id if provided.
	 * @return void
	 */
	public function handle_return( $checkout_session_id = '' ) {

		/* If checkout_session_id has been supplied, the classic payment method is being used. */
		$checkout_session_id = $checkout_session_id ? $checkout_session_id : $this->get_checkout_session_id();

		$order_id = isset( WC()->session->order_awaiting_payment ) ? absint( WC()->session->order_awaiting_payment ) : 0;
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
		}

		/* Fallback in case WC()->session->order_awaiting_payment was unset by 3rd party. */
		if ( empty( $order_id ) ) {
			$checkout_session = $this->get_checkout_session( true );
			$order_id         = ! empty( $checkout_session->merchantMetadata->merchantReferenceId ) ? $checkout_session->merchantMetadata->merchantReferenceId : $order_id; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			/**
			 * Merchants that use a filter to manipulate the merchantReferenceId provided to Amazon,
			 * should migrate to woocommerce_amazon_pa_merchant_metadata_reference_id and implement the woocommerce_amazon_pa_merchant_metadata_reference_id_reverse
			 * filter as well to provide the actual order's id when needed by the plugin.
			 */
			$order_id = apply_filters( 'woocommerce_amazon_pa_merchant_metadata_reference_id_reverse', $order_id );
		}

		if ( empty( $order_id ) ) {
			wc_apa()->log( "Error: Order could not be found. Checkout Session ID: {$checkout_session_id}." );
			wc_add_notice( __( 'There was an error while processing your payment. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			return;
		}

		$order = wc_get_order( $order_id );

		$order_total = WC_Amazon_Payments_Advanced::format_amount( $order->get_total() );
		$currency    = wc_apa_get_order_prop( $order, 'order_currency' );

		wc_apa()->log( "Completing checkout session data for #{$order_id}." );

		$this->get_lock_for_order( $order_id, true );

		$response = WC_Amazon_Payments_Advanced_API::complete_checkout_session(
			$checkout_session_id,
			apply_filters(
				'woocommerce_amazon_pa_update_complete_checkout_session_payload',
				array(
					'chargeAmount' => array(
						'amount'       => $order_total,
						'currencyCode' => $currency,
					),
				),
				$checkout_session_id,
				$order
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			if ( 'CheckoutSessionCanceled' === $error_code ) {
				$checkout_session = $this->get_checkout_session( true, $checkout_session_id );

				switch ( $checkout_session->statusDetails->reasonCode ) { // phpcs:ignore WordPress.NamingConventions
					case 'Declined':
						wc_add_notice( __( 'There was a problem with previously declined transaction. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
						break;
					case 'BuyerCanceled':
						wc_add_notice( __( 'The transaction was canceled by you. Please try placing the order again.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
						break;
					default:
						$detail_debug = array(
							'error_code'       => $error_code,
							'checkout_session' => $checkout_session,
						);
						wc_apa()->log( "Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}.", $detail_debug );
						wc_add_notice( __( 'There was an error while processing your payment. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
						break;
				}

				$this->do_force_refresh( __( 'Click the button below to select another payment method', 'woocommerce-gateway-amazon-payments-advanced' ) );
			} else {
				wc_add_notice( __( 'Error:', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $response->get_error_message(), 'error' );
			}
			$this->release_lock_for_order( $order_id );
			return;
		}

		if ( 'Completed' !== $response->statusDetails->state ) { // phpcs:ignore WordPress.NamingConventions
			// ASK: Ask for posibilities of status not to be completed at this stage.
			wc_apa()->log( "Error processing payment for order {$order_id}. Checkout Session ID: {$checkout_session_id}.", $response->statusDetails ); // phpcs:ignore WordPress.NamingConventions
			wc_add_notice( __( 'There was an error while processing your payment. Please try again. If the error persist, please contact us about your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
			$this->release_lock_for_order( $order_id );
			return;
		}

		do_action( 'woocommerce_amazon_pa_before_processed_order', $order, $response );

		$charge_permission_id = $response->chargePermissionId; // phpcs:ignore WordPress.NamingConventions
		$order->update_meta_data( 'amazon_charge_permission_id', $charge_permission_id );

		$this->maybe_set_transaction_id( $order, $charge_permission_id, $response->chargeId ); // phpcs:ignore WordPress.NamingConventions

		$order->save();
		$charge_id   = $response->chargeId; // phpcs:ignore WordPress.NamingConventions
		$order_total = (float) $order->get_total( 'edit' );
		if ( 0 >= $order_total ) {
			$order->payment_complete();
		} elseif ( ! empty( $charge_id ) ) {
			$order->update_meta_data( 'amazon_charge_id', $charge_id );
			$order->save();
			$this->log_charge_status_change( $order );
		} else {
			if ( apply_filters( 'woocommerce_amazon_pa_no_charge_order_on_hold', true, $order ) ) {
				$order->update_status( 'on-hold' );
				wc_maybe_reduce_stock_levels( $order->get_id() );
			}
		}
		$this->log_charge_permission_status_change( $order );
		$order->save();

		do_action( 'woocommerce_amazon_pa_processed_order', $order, $response );

		$this->release_lock_for_order( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		$this->do_logout();

		$redirect_url = apply_filters( 'woocommerce_amazon_pa_processed_order_redirect', $order->get_checkout_order_received_url(), $order, $response );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Log a change to the charge status stored in an order.
	 *
	 * @param  WC_Order           $order Order object.
	 * @param  null|string|object $charge Optional. Can be the charge_id, or the charge object from the amazon API.
	 */
	public function log_charge_status_change( $order, $charge = null ) {
		$charge_id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );
		// TODO: Maybe support multple charges to be tracked?
		if ( ! is_null( $charge ) ) {
			if ( is_string( $charge ) ) {
				$charge = WC_Amazon_Payments_Advanced_API::get_charge( $charge );
			}
			if ( ! empty( $charge_id ) && $charge_id !== $charge->chargeId ) { // phpcs:ignore WordPress.NamingConventions
				$old_charge = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );
				$new_charge = $charge;
				$old_time   = strtotime( $old_charge->creationTimestamp ); // phpcs:ignore WordPress.NamingConventions
				$new_time   = strtotime( $new_charge->creationTimestamp ); // phpcs:ignore WordPress.NamingConventions
				if ( $old_time > $new_time ) {
					wc_apa()->log( sprintf( 'Discarding change of chargeId on #%1$d from "%2$s" to "%3$s". "%2$s" is actually newer and shoud stay linked to #%1$d.', $order->get_id(), $charge_id, $charge->chargeId ) ); // phpcs:ignore WordPress.NamingConventions
					// An old charge cannot replace a newer one created for the same order.
					return;
				}
				wc_apa()->log( sprintf( 'Changing ChargeId on #%1$d from "%2$s" to "%3$s"', $order->get_id(), $charge_id, $charge->chargeId ) ); // phpcs:ignore WordPress.NamingConventions
				$order->delete_meta_data( 'amazon_charge_id' );
				$order->delete_meta_data( 'amazon_charge_status' );
				$order->save();
			}
			$charge_id = $charge->chargeId; // phpcs:ignore WordPress.NamingConventions
		}
		if ( is_null( $charge ) ) {
			if ( empty( $charge_id ) ) {
				return null;
			}
			$charge = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );
		}
		$order->read_meta_data( true ); // Force read from db to avoid concurrent notifications.
		$old_status    = $this->get_cached_charge_status( $order, true )->status;
		$charge_status = $charge->statusDetails->state; // phpcs:ignore WordPress.NamingConventions
		if ( $charge_status === $old_status ) {
			switch ( $old_status ) {
				case 'AuthorizationInitiated':
				case 'Authorized':
				case 'CaptureInitiated':
					wc_apa()->ipn_handler->schedule_hook( $charge_id, 'CHARGE' );
					break;
			}
			return $old_status;
		}
		$this->refresh_cached_charge_status( $order, $charge );
		$order->update_meta_data( 'amazon_charge_id', $charge_id );
		$this->maybe_set_transaction_id( $order, '', $charge_id );
		$order->save(); // Save early for less race conditions.

		// @codingStandardsIgnoreStart
		$order->add_order_note( sprintf(
			/* translators: 1) Amazon Charge ID 2) Charge status */
			__( 'Charge %1$s with status %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			(string) $charge_id,
			(string) $charge_status
		) );
		// @codingStandardsIgnoreEnd

		switch ( $charge_status ) {
			case 'AuthorizationInitiated':
			case 'Authorized':
			case 'CaptureInitiated':
				// Mark as on-hold.
				$order->update_status( 'on-hold' );
				wc_maybe_reduce_stock_levels( $order->get_id() );
				wc_apa()->ipn_handler->schedule_hook( $charge_id, 'CHARGE' );
				break;
			case 'Canceled':
				if ( 'cancelled' !== $order->get_status() ) {
					$order->update_status( 'pending' );
				}
				wc_maybe_reduce_stock_levels( $order->get_id() );
				break;
			case 'Declined':
				$order->update_status( 'failed' );
				wc_maybe_increase_stock_levels( $order->get_id() );
				break;
			case 'Captured':
				$order->payment_complete();
				break;
			default:
				// TODO: This is an unknown state, maybe handle?
				break;
		}

		$order->save();

		return $charge_status;
	}

	/**
	 * Log a change to the charge permission status stored in an order.
	 *
	 * @param  WC_Order           $order Order object.
	 * @param  null|string|object $charge_permission Optional. Can be the charge_permission_id, or the charge permission object from the amazon API.
	 * @return null|string Returns null on error, or the new status (even if it's the same as the old one).
	 */
	public function log_charge_permission_status_change( $order, $charge_permission = null ) {
		$charge_permission_id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $order->get_id() );

		// TODO: Maybe support multple charges to be tracked?
		if ( ! is_null( $charge_permission ) ) {
			if ( is_string( $charge_permission ) ) {
				$charge_permission = WC_Amazon_Payments_Advanced_API::get_charge_permission( $charge_permission );
			}
			if ( ! empty( $charge_permission_id ) && $charge_permission_id !== $charge_permission->chargePermissionId ) { // phpcs:ignore WordPress.NamingConventions
				$old_charge_permission = WC_Amazon_Payments_Advanced_API::get_charge_permission( $charge_permission_id );
				$new_charge_permission = $charge_permission;
				$old_time              = strtotime( $old_charge_permission->creationTimestamp ); // phpcs:ignore WordPress.NamingConventions
				$new_time              = strtotime( $new_charge_permission->creationTimestamp ); // phpcs:ignore WordPress.NamingConventions
				if ( $old_time > $new_time ) {
					wc_apa()->log( sprintf( 'Discarding change of chargePermissionId on #%1$d from "%2$s" to "%3$s". "%2$s" is actually newer and shoud stay linked to #%1$d.', $order->get_id(), $charge_permission_id, $charge_permission->chargePermissionId ) ); // phpcs:ignore WordPress.NamingConventions
					// An old charge permission cannot replace a newer one created for the same order.
					return null;
				}
				wc_apa()->log( sprintf( 'Changing chargePermissionId on #%1$d from "%2$s" to "%3$s"', $order->get_id(), $charge_permission_id, $charge_permission->chargePermissionId ) ); // phpcs:ignore WordPress.NamingConventions
				$order->delete_meta_data( 'amazon_charge_permission_id' );
				$order->delete_meta_data( 'amazon_charge_permission_status' );
				$order->save();
			}
			$charge_permission_id = $charge_permission->chargePermissionId; // phpcs:ignore WordPress.NamingConventions
		}
		if ( is_null( $charge_permission ) ) {
			if ( empty( $charge_permission_id ) ) {
				return null;
			}
			$charge_permission = WC_Amazon_Payments_Advanced_API::get_charge_permission( $charge_permission_id );
		}
		$order->read_meta_data( true ); // Force read from db to avoid concurrent notifications.
		$old_status               = $this->get_cached_charge_permission_status( $order, true )->status;
		$charge_permission_status = $charge_permission->statusDetails->state; // phpcs:ignore WordPress.NamingConventions
		if ( $charge_permission_status === $old_status ) {
			switch ( $charge_permission_status ) {
				case 'Chargeable':
				case 'NonChargeable':
					wc_apa()->ipn_handler->schedule_hook( $charge_permission_id, 'CHARGE_PERMISSION' );
					break;
			}
			return $old_status;
		}
		$this->refresh_cached_charge_permission_status( $order, $charge_permission );
		$order->update_meta_data( 'amazon_charge_permission_id', $charge_permission_id ); // phpcs:ignore WordPress.NamingConventions

		$order->save(); // Save early for less race conditions.

		$this->add_status_change_note( $order, (string) $charge_permission_id, (string) $charge_permission_status );

		switch ( $charge_permission_status ) {
			case 'Chargeable':
			case 'NonChargeable':
				wc_apa()->ipn_handler->schedule_hook( $charge_permission_id, 'CHARGE_PERMISSION' );
				break;
			case 'Closed':
				$order_has_charge = is_null( $this->get_cached_charge_status( $order, true )->status );
				if ( apply_filters( 'woocommerce_amazon_pa_charge_permission_status_should_fail_order', $order_has_charge, $order ) ) {
					$order->update_status( 'failed', __( 'Amazon charge status was closed, moving order to failed.', 'woocommerce-gateway-amazon-payments-advanced' ) );
					wc_maybe_increase_stock_levels( $order->get_id() );
				}
				break;
			default:
				// TODO: This is an unknown state, maybe handle?
				break;
		}

		$order->save();

		return $charge_permission_status;
	}

	/**
	 * Adds a note to an order stating the status change for a charge permission
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $charge_permission_id Charge Permission ID.
	 * @param  string   $new_status New status.
	 */
	public function add_status_change_note( $order, $charge_permission_id, $new_status ) {
		// @codingStandardsIgnoreStart
		$order->add_order_note( sprintf(
			/* translators: 1) Amazon Charge ID 2) Charge status */
			__( 'Charge Permission %1$s with status %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			(string) $charge_permission_id,
			(string) $new_status
		) );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Render tag that will be read in the browser to update data in the JS environment.
	 *
	 * @param string $id The value to provide the id attribute of the script with.
	 * @return void
	 */
	public function update_js( $id = '' ) {
		$id = $id ? $id : 'wc-apa-update-vals';

		$checkout_session_config = WC_Amazon_Payments_Advanced_API::get_create_checkout_session_config();

		$data = array(
			'action'                         => $this->get_current_cart_action(),
			'create_checkout_session_config' => $checkout_session_config,
			'create_checkout_session_hash'   => wp_hash( $checkout_session_config['payloadJSON'] . self::get_estimated_order_amount() ),
			'estimated_order_amount'         => self::get_estimated_order_amount(),
		);
		?>
		<script type="text/template" id="<?php echo esc_attr( $id ); ?>" data-value="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"></script>
		<?php
	}

	/**
	 * Get Product Type used on the Amazon button. This depends on wether the payment needs shipping or not.
	 *
	 * @return string Either PayAndShip or PayOnly.
	 */
	public function get_current_cart_action() {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id = get_query_var( 'order-pay' );
			$order    = wc_get_order( $order_id );

			// If we can't retrieve the order, lets bail.
			if ( ! is_a( $order, 'WC_Order' ) ) {
				return false;
			}

			$needs_shipping = count( $order->get_items( 'shipping' ) ) > 0;
		} else {
			// If the cart is not set, we are on the backend. So lets bail.
			if ( empty( WC()->cart ) ) {
				return false;
			}

			$needs_shipping = WC()->cart->needs_shipping();
		}
		return apply_filters( 'woocommerce_amazon_pa_current_cart_action', $needs_shipping ? 'PayAndShip' : 'PayOnly' );
	}

	/**
	 * Product Type used on the Amazon Pay button. Depends to whether the product needs shipping or not.
	 *
	 * @return string Either PayAndShip or PayOnly.
	 */
	public function get_current_product_action() {
		if ( is_product() ) {
			$product = wc_get_product( get_the_ID() );
			return $product->is_virtual() ? 'PayOnly' : 'PayAndShip';
		}
		return 'PayOnly';
	}

	/**
	 * Render the amazon button when the session is not valid anymore.
	 *
	 * @param  string $message Message to render along with the button.
	 * @param  bool   $col_wrap Wether to wrap the button in columns or not.
	 */
	public function render_login_button_again( $message = null, $col_wrap = true ) {
		?>
		<?php if ( $col_wrap ) : ?>
			<div id="amazon_customer_details" class="wc-amazon-payments-advanced-populated"><div class="col2-set"><div class="col-1">
		<?php endif; ?>
		<div id="shipping_address_widget">
			<h3>
				<?php esc_html_e( 'Confirm payment method', 'woocommerce-gateway-amazon-payments-advanced' ); ?>
			</h3>
			<div class="shipping_address_display">
				<p class="wc_apa_login_again_text">
				<?php
				if ( empty( $message ) ) {
					$message = __( 'Your cart changed, and you need to confirm your selected payment method again.', 'woocommerce-gateway-amazon-payments-advanced' );
				}

				echo esc_html( $message );
				?>
				</p>
				<?php $this->checkout_button(); ?>
			</div>
		</div>
		<?php if ( $col_wrap ) : ?>
			</div><div class="col-2"></div></div></div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Validate checkout session with desired current config
	 *
	 * @param  mixed $current Compare source.
	 * @param  mixed $desired Optional. Desired values to compare against.
	 * @param  mixed $path    Optional. Current property path, to add to the error data. Used recursively.
	 * @return bool|WP_Error
	 */
	private function validate_session_properties( $current, $desired = null, $path = array() ) {
		$current = (array) $current;

		if ( is_null( $desired ) ) {
			$current_create_checkout_session = WC_Amazon_Payments_Advanced_API::get_create_checkout_session_config();

			$desired = json_decode( $current_create_checkout_session['payloadJSON'], true );
		}

		$desired = (array) $desired; // recast, just in case.

		foreach ( $desired as $prop => $value ) {
			array_push( $path, $prop );
			if ( is_object( $value ) ) {
				$valid = $this->validate_session_properties( $current[ $prop ], $value, $path );
			} elseif ( is_array( $value ) ) {
				if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
					// associative array, recurse.
					$valid = $this->validate_session_properties( $current[ $prop ], $value, $path );
				} else {
					// sequential array, which might be in different order, so lets just diff.
					$valid = array_diff( $value, $current[ $prop ] );
					$valid = count( $valid ) === 0;
				}
			} else {
				$valid = $current[ $prop ] === $value;
			}

			/**
			 * If the condition is true, that means that a multi currency plugin changed the
			 * currency after the Amazon Pay button was initially clicked.
			 *
			 * We shouldn't throw an error since Amazon Pay docs mention that using the currencyCode prop
			 * during updating checkout will also automatically update the checkout session's presentmentCurrency
			 * prop if they are different.
			 *
			 * @see https://developer.amazon.com/docs/amazon-pay-checkout/multi-currency-integration.html#2-set-payment-currencycode
			 */
			if ( 'presentmentCurrency' === $prop && false === $valid ) {
				$valid = true;
			}

			/**
			 * Same as above, but for subscriptions.
			 */
			if ( ( 'recurringMetadata.amount.currencyCode' === implode( '.', $path ) || 'recurringMetadata.amount.amount' === implode( '.', $path ) ) && false === $valid ) {
				$valid = true;
			}

			if ( false === $valid ) {
				$full_prop = implode( '.', $path );

				$data = (object) array(
					'prop'      => $full_prop,
					'is'        => $current[ $prop ],
					'should_be' => $value,
				);

				$valid = apply_filters( 'woocommerce_amazon_pa_invalid_session_property', $valid, $data );

				if ( false === $valid ) {
					$valid = new WP_Error(
						'invalid_prop',
						sprintf( 'Invalid property \'%s\'', $full_prop ),
						$data
					);
					return $valid;
				}
			}

			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			array_pop( $path );
		}
		return true;
	}

	/**
	 * Check wether the checkout session is still valid.
	 *
	 * @param  object $checkout_session Checkout Session Object from the Amazon API.
	 * @return bool|WP_Error True if valid, WP_Error in case of error.
	 */
	public function is_checkout_session_still_valid( $checkout_session ) {
		if ( $this->need_to_force_refresh() ) {
			return new WP_Error( 'force_refresh', $this->get_force_refresh() );
		}

		$props_validation = $this->validate_session_properties( $checkout_session );
		if ( is_wp_error( $props_validation ) ) {
			return new WP_Error( 'session_changed', __( 'Something went wrong with your session. Please log in again.', 'woocommerce-gateway-amazon-payments-advanced' ), $props_validation->get_error_data() );
		}

		if ( 'Open' !== $checkout_session->statusDetails->state ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return new WP_Error( 'not_open', __( 'Something went wrong with your session. Please log in again.', 'woocommerce-gateway-amazon-payments-advanced' ), $checkout_session->statusDetails->state ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		if ( $checkout_session->productType !== $this->get_current_cart_action() ) { // phpcs:ignore WordPress.NamingConventions
			return new WP_Error( 'product_type_changed', __( 'Your cart changed, and you need to confirm your selected payment method again.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
		return apply_filters( 'woocommerce_amazon_pa_is_checkout_session_still_valid', true, $checkout_session );
	}

	/**
	 * Maybe render login button again, if the session is not valid anymore
	 *
	 * @param  object $checkout_session Checkout Session Object from the Amazon API.
	 * @param  bool   $wrap Wether to wrap the button in columns or not.
	 */
	public function maybe_render_login_button_again( $checkout_session, $wrap = true ) {
		$is_valid = $this->is_checkout_session_still_valid( $checkout_session );
		if ( is_wp_error( $is_valid ) ) {
			wc_apa()->log( $is_valid );
			$this->render_login_button_again( $is_valid->get_error_message(), $wrap );
			return;
		}

		return true;
	}

	/**
	 * Filter billing fields
	 *
	 * @param  array $fields Billing fields from WooCommerce.
	 * @return array
	 */
	public function override_billing_fields( $fields ) {
		$old = ! empty( $fields['billing_state']['required'] );

		$fields = parent::override_billing_fields( $fields );

		$fields['billing_state']['required'] = $old;

		$checkout_session = $this->get_checkout_session();

		$address = null;
		if ( ! empty( $checkout_session->billingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
			$address = $checkout_session->billingAddress; // phpcs:ignore WordPress.NamingConventions
		} elseif ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
			$address = $checkout_session->shippingAddress; // phpcs:ignore WordPress.NamingConventions
		}

		if ( is_null( $address ) ) {
			return $fields;
		}

		if ( ! empty( $address->CountryCode ) && in_array( $address->CountryCode, array( 'JP' ), true ) ) { // phpcs:ignore WordPress.NamingConventions
			$fields['billing_city']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Filter shipping fields
	 *
	 * @param  array $fields Shipping fields from WooCommerce.
	 * @return array
	 */
	public function override_shipping_fields( $fields ) {
		$old = ! empty( $fields['shipping_state']['required'] );

		$fields = parent::override_shipping_fields( $fields );

		$fields['shipping_state']['required'] = $old;

		$checkout_session = $this->get_checkout_session();

		$address = null;
		if ( ! empty( $checkout_session->shippingAddress ) ) { // phpcs:ignore WordPress.NamingConventions
			$address = $checkout_session->shippingAddress; // phpcs:ignore WordPress.NamingConventions
		}

		if ( is_null( $address ) ) {
			return $fields;
		}

		if ( ! empty( $address->CountryCode ) && in_array( $address->CountryCode, array( 'JP' ), true ) ) { // phpcs:ignore WordPress.NamingConventions
			$fields['shipping_city']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Unset keys json box.
	 */
	public function process_admin_options() {
		if ( check_admin_referer( 'woocommerce-settings' ) ) {
			if ( ! empty( $_POST['woocommerce_amazon_payments_advanced_button_language'] ) ) {
				$region   = $_POST['woocommerce_amazon_payments_advanced_payment_region'];
				$language = $_POST['woocommerce_amazon_payments_advanced_button_language'];
				$regions  = WC_Amazon_Payments_Advanced_API::get_languages_per_region();
				if ( ! isset( $regions[ $region ] ) || ! in_array( $language, $regions[ $region ], true ) ) {
					/* translators: 1) Language 2) Region */
					WC_Admin_Settings::add_error( sprintf( __( '%1$s is not a valid language for the %2$s region.', 'woocommerce-gateway-amazon-payments-advanced' ), $language, WC_Amazon_Payments_Advanced_API::get_region_label( $region ) ) );
					$_POST['woocommerce_amazon_payments_advanced_button_language'] = '';
				}
			}
			parent::process_admin_options();
		}
	}

	/**
	 * Standarize status and it's reasons from a Status Details object from the Amazon API
	 *
	 * @param  object $status_details Amazon status details.
	 * @return object
	 */
	private function format_status_details( $status_details ) {
		$charge_status         = $status_details->state; // phpcs:ignore WordPress.NamingConventions
		$charge_status_reasons = isset( $status_details->reasons ) && is_array( $status_details->reasons ) ? $status_details->reasons : array(); // phpcs:ignore WordPress.NamingConventions

		$charge_status_reason = isset( $status_details->reasonCode ) ? $status_details->reasonCode : null; // phpcs:ignore WordPress.NamingConventions

		if ( $charge_status_reason ) {
			$charge_status_reasons[] = (object) array(
				'reasonCode'        => $charge_status_reason,
				'reasonDescription' => '',
			);
		}

		return (object) array(
			'status'  => $charge_status,
			'reasons' => $charge_status_reasons,
		);
	}

	/**
	 * Get cached charge permission status for an order
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $read_only If true, will not fetch status from API. Defaults to false.
	 * @return object
	 */
	public function get_cached_charge_permission_status( $order, $read_only = false ) {
		$charge_permission_status = $order->get_meta( 'amazon_charge_permission_status' );
		$charge_permission_status = json_decode( $charge_permission_status );
		if ( ! is_object( $charge_permission_status ) ) {
			if ( ! $read_only ) {
				$charge_permission_status = $this->refresh_cached_charge_permission_status( $order );
			} else {
				$charge_permission_status = (object) array(
					'status'  => null,
					'reasons' => array(),
				);
			}
		}

		return $charge_permission_status;
	}

	/**
	 * Refresh cached charge permission status for an order
	 *
	 * @param  WC_Order $order Order object.
	 * @param  object   $charge_permission Optional. Charge permission object from the Amazon API.
	 * @return object
	 */
	public function refresh_cached_charge_permission_status( $order, $charge_permission = null ) {
		if ( ! is_object( $charge_permission ) ) {
			$charge_permission_id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $order->get_id() );

			if ( empty( $charge_permission_id ) ) {
				return new WP_Error( 'no_charge_permission', 'You cannot refresh this order\'s charge_permission, as it has no charge_permission_id, and you didn\'t specify a charge permission object' );
			}

			$charge_permission = WC_Amazon_Payments_Advanced_API::get_charge_permission( $charge_permission_id );
		} else {
			$charge_permission_id = $charge_permission->chargePermissionId; // phpcs:ignore WordPress.NamingConventions
		}

		if ( is_wp_error( $charge_permission ) ) {
			return $charge_permission;
		}

		$charge_permission_status       = $this->format_status_details( $charge_permission->statusDetails ); // phpcs:ignore WordPress.NamingConventions
		$charge_permission_status->type = $charge_permission->chargePermissionType; // phpcs:ignore WordPress.NamingConventions

		wc_apa()->log( sprintf( 'Caching amazon_charge_permission_status on #%1$d', $order->get_id() ), $charge_permission_status );

		$order->update_meta_data( 'amazon_charge_permission_status', wp_json_encode( $charge_permission_status ) );
		$order->save();

		do_action( 'woocommerce_amazon_pa_refresh_cached_charge_permission_status', $order, $charge_permission_id, $charge_permission_status );

		return $charge_permission_status;
	}

	/**
	 * Get cached charge status for an order
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $read_only If true, will not fetch status from API. Defaults to false.
	 * @return object
	 */
	public function get_cached_charge_status( $order, $read_only = false ) {
		$charge_status = $order->get_meta( 'amazon_charge_status' );
		$charge_status = json_decode( $charge_status );
		if ( ! is_object( $charge_status ) ) {
			if ( ! $read_only ) {
				$charge_status = $this->refresh_cached_charge_status( $order );
			} else {
				$charge_status = (object) array(
					'status'  => null,
					'reasons' => array(),
				);
			}
		}

		return $charge_status;
	}

	/**
	 * Refresh cached charge status for an order
	 *
	 * @param  WC_Order $order Order object.
	 * @param  object   $charge Optional. Charge object from the Amazon API.
	 * @return object
	 */
	public function refresh_cached_charge_status( $order, $charge = null ) {
		if ( ! is_object( $charge ) ) {
			$charge_id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );

			if ( empty( $charge_id ) ) {
				return new WP_Error( 'no_charge', 'You cannot refresh this order\'s charge, as it has no charge_id, and you didn\'t specify a charge object' );
			}

			$charge = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );
		}

		$charge_status = $this->format_status_details( $charge->statusDetails ); // phpcs:ignore WordPress.NamingConventions

		wc_apa()->log( sprintf( 'Caching amazon_charge_status on #%1$d', $order->get_id() ), $charge_status );

		$order->update_meta_data( 'amazon_charge_status', wp_json_encode( $charge_status ) );
		$order->save();

		return $charge_status;
	}

	/**
	 * Handle refund creation on the WC side from an Amazon Refund object from the API.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  object   $refund Refund object from the Amazon API.
	 * @param  int      $wc_refund_id Optional. WC Refund ID (if it's already created).
	 * @return bool
	 */
	public function handle_refund( $order, $refund, $wc_refund_id = null ) {
		$wc_refund        = null;
		$previous_refunds = wp_list_pluck( $order->get_meta( 'amazon_refund_id', false ), 'value' );
		if ( empty( $wc_refund_id ) ) {
			if ( ! empty( $previous_refunds ) ) {
				$refunds = $order->get_refunds();
				foreach ( $refunds as $this_wc_refund ) {
					$this_refund_id = $this_wc_refund->get_meta( 'amazon_refund_id' );
					if ( $this_refund_id === $refund->refundId ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$wc_refund = $this_wc_refund;
					}
				}
			}
			if ( empty( $wc_refund ) ) {
				$wc_refund = wc_create_refund(
					array(
						'amount'   => $refund->refundAmount->amount, // phpcs:ignore WordPress.NamingConventions
						'order_id' => $order->get_id(),
					)
				);

				if ( is_wp_error( $wc_refund ) ) {
					return false;
				}
			}
			$wc_refund_id = $wc_refund->get_id();
		} else {
			$wc_refund = wc_get_order( $wc_refund_id );
		}

		if ( ! in_array( $refund->refundId, $previous_refunds, true ) ) { // phpcs:ignore WordPress.NamingConventions
			$order->add_meta_data( 'amazon_refund_id', $refund->refundId ); // phpcs:ignore WordPress.NamingConventions
			$order->save();
		}

		$wc_refund->update_meta_data( 'amazon_refund_id', $refund->refundId ); // phpcs:ignore WordPress.NamingConventions
		$wc_refund->set_refunded_payment( true );
		$wc_refund->save();
		return true;
	}

	/**
	 * Do process refund on an order
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Amount to refund.
	 * @param  string $reason Optional. Message to be added to the WC Refund object as reason for the refund.
	 * @return bool|WP_Error WP_Error if error, otherwise, true on success, false on failure.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$process = apply_filters( 'woocommerce_amazon_pa_process_refund', null, $order_id, $amount, $reason );
		if ( ! is_null( $process ) ) {
			return $process;
		}

		$order     = wc_get_order( $order_id );
		$charge_id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );
		if ( empty( $charge_id ) ) {
			wc_apa()->log( 'Order #' . $order_id . ' doesnt have a charge' );
			return new WP_Error( 'no_charge', 'No charge to refund on this order' );
		}
		wc_apa()->log( 'Processing refund from admin for order #' . $order_id );
		wc_apa()->log( 'Processing refund from admin for order #' . $order_id . '. Temporary refund ID #' . $this->current_refund->get_id() );
		$refund = WC_Amazon_Payments_Advanced_API::refund_charge( $charge_id, $amount );
		wc_apa()->get_gateway()->handle_refund( $order, $refund, $this->current_refund->get_id() );
		return true;
	}

	/**
	 * Store wc refund object in a previous hook for later use.
	 *
	 * @param  WC_Order_Refund $wc_refund Current Order Refund.
	 */
	public function current_refund_set( $wc_refund ) {
		$this->current_refund = $wc_refund; // Cache refund object in a hook before process_refund is called.
	}

	/**
	 * Maybe change session key when on the order-pay screen.
	 *
	 * @param  string $session_key Session Key.
	 * @return string
	 */
	public function maybe_change_session_key( $session_key ) {
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id = get_query_var( 'order-pay' );
			return 'amazon_checkout_session_id_order_pay_' . $order_id;
		}
		return $session_key;
	}

	/**
	 * Lock an order to be processed only in the current request.
	 *
	 * @param  int  $order Order ID.
	 * @param  bool $force Wether to force acquisition or not.
	 * @return bool True if acquired, false if not.
	 */
	public function get_lock_for_order( $order, $force = false ) {
		$key = 'amazon_processing_order_' . $order;

		if ( false === $force ) {
			$transient = get_transient( $key );
			if ( false !== $transient ) {
				return false;
			}
		}
		set_transient( $key, 'yes', 2 * MINUTE_IN_SECONDS ); // This is shortlived to avoid issues.
		return true;
	}

	/**
	 * Release an acquired lock for an order.
	 *
	 * @param  int $order Order ID.
	 */
	public function release_lock_for_order( $order ) {
		$key = 'amazon_processing_order_' . $order;
		delete_transient( $key );
	}

	/**
	 * Maybe hides Amazon Pay buttons on cart or checkout pages if hide button mode
	 * is enabled.
	 *
	 * @since 1.6.0
	 */
	public function maybe_hide_amazon_buttons() {
		$hide_button_mode_enabled = 'yes' === $this->settings['hide_button_mode'];
		$hide_button_mode_enabled = apply_filters( 'woocommerce_amazon_payments_hide_amazon_buttons', $hide_button_mode_enabled );

		if ( ! $hide_button_mode_enabled ) {
			return;
		}

		?>
		<style type="text/css">
			.wc-apa-button-separator, .wc-amazon-payments-advanced-info, #pay_with_amazon, #pay_with_amazon_cart, #pay_with_amazon_product {
				display: none;
			}
		</style>
		<?php
	}

	/**
	 * Perform an authorization on an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  bool     $capture_now Wether to capture now or not.
	 * @param  string   $id Charge Permission ID.
	 * @return object|WP_Error Charge object from the API, or WP_Error in case of error.
	 */
	public function perform_authorization( $order, $capture_now = true, $id = null ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return new WP_Error( 'not_an_order', 'The object provided is not an order' );
		}

		$order_id = $order->get_id();
		if ( empty( $id ) ) {
			$id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $order_id );
		}

		if ( empty( $id ) ) {
			return new WP_Error( 'no_charge_permission', 'The specified order doesn\'t have a charge permission' );
		}

		$can_do_async = false;
		if ( ! $capture_now && 'async' === WC_Amazon_Payments_Advanced_API::get_settings( 'authorization_mode' ) ) {
			$can_do_async = true;
		}

		$currency = wc_apa_get_order_prop( $order, 'order_currency' );

		$charge = WC_Amazon_Payments_Advanced_API::create_charge(
			$id,
			array(
				'merchantMetadata'              => WC_Amazon_Payments_Advanced_API::get_merchant_metadata( $order_id ),
				'captureNow'                    => $capture_now,
				'canHandlePendingAuthorization' => $can_do_async,
				'chargeAmount'                  => array(
					'amount'       => WC_Amazon_Payments_Advanced::format_amount( $order->get_total() ),
					'currencyCode' => $currency,
				),
			)
		);

		if ( is_wp_error( $charge ) ) {
			return $charge;
		}

		wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
		wc_apa()->get_gateway()->log_charge_status_change( $order, $charge );

		return $charge;
	}

	/**
	 * Cancel an authorization
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $id Charge ID.
	 * @return object|WP_Error Charge object from the API, or WP_Error in case of error.
	 */
	public function perform_cancel_auth( $order, $id = null ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return new WP_Error( 'not_an_order', 'The object provided is not an order' );
		}

		$order_id = $order->get_id();
		if ( empty( $id ) ) {
			$id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );
		}

		if ( empty( $id ) ) {
			return new WP_Error( 'no_charge', 'The specified order doesn\'t have a charge' );
		}

		$charge = WC_Amazon_Payments_Advanced_API::cancel_charge( $id );

		if ( is_wp_error( $charge ) ) {
			return $charge;
		}

		wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
		wc_apa()->get_gateway()->log_charge_status_change( $order, $charge );

		return $charge;
	}

	/**
	 * Capture an authorization
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $id Charge ID.
	 * @return object|WP_Error Charge object from the API, or WP_Error in case of error.
	 */
	public function perform_capture( $order, $id = null ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return new WP_Error( 'not_an_order', 'The object provided is not an order' );
		}

		$order_id = $order->get_id();
		if ( empty( $id ) ) {
			$id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );
		}

		if ( empty( $id ) ) {
			return new WP_Error( 'no_charge', 'The specified order doesn\'t have a charge' );
		}

		$charge = WC_Amazon_Payments_Advanced_API::capture_charge( $id );

		if ( is_wp_error( $charge ) ) {
			return $charge;
		}

		wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
		wc_apa()->get_gateway()->log_charge_status_change( $order, $charge );

		return $charge;
	}

	/**
	 * Perform a refund on an order
	 *
	 * @param  WC_Order $order Order object.
	 * @param  float    $amount Amount to refund.
	 * @param  string   $id Charge ID.
	 * @return object|WP_Error Refund object from the API, or WP_Error in case of error.
	 */
	public function perform_refund( $order, $amount = null, $id = null ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return new WP_Error( 'not_an_order', 'The object provided is not an order' );
		}

		$order_id = $order->get_id();
		if ( empty( $id ) ) {
			$id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );
		}

		if ( empty( $id ) ) {
			return new WP_Error( 'no_charge', 'The specified order doesn\'t have a charge' );
		}

		$refund = WC_Amazon_Payments_Advanced_API::refund_charge( $id, $amount );

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		wc_apa()->get_gateway()->handle_refund( $order, $refund );

		return $refund;
	}

	/**
	 * Check if we're on an AJAX call
	 *
	 * @return bool
	 */
	public function doing_ajax() {
		$doing = wp_doing_ajax();
		if ( $doing ) {
			return $doing;
		}

		if ( isset( $_REQUEST['woocommerce-shipping-calculator-nonce'] ) && isset( $_REQUEST['calc_shipping'] ) ) {
			return true;
		}

		/**
		 * To capture the cart redirect on update cart actions.
		 *
		 * wp_get_referer will return false when the request comes from the same page.
		 */
		if ( false === wp_get_referer() ) {
			return true;
		}

		return false;
	}

	/**
	 * Filters the fragments
	 *
	 * @param  array $fragments Fragments to be returned.
	 * @return array
	 */
	public function update_amazon_fragments( $fragments ) {
		ob_start();
		$this->checkout_message();
		$ret = ob_get_clean();
		if ( $this->is_available() ) {
			$fragments['.wc-amazon-checkout-message:not(.wc-amazon-payments-advanced-populated)'] = $ret;
		} else {
			$fragments['.wc-amazon-checkout-message.wc-amazon-payments-advanced-populated'] = $ret;
		}
		return $fragments;
	}

	/**
	 * Filter a customer field.
	 *
	 * @param  string $value Value to filter.
	 * @return string
	 */
	public function filter_customer_field( $value ) {
		if ( ! $this->is_logged_in() ) {
			return $value;
		}
		$current_field = str_replace( 'woocommerce_customer_get_', '', current_filter() );
		$wc_data       = $this->get_woocommerce_data();
		if ( ! isset( $wc_data[ $current_field ] ) ) {
			return $value;
		}
		return $wc_data[ $current_field ];
	}

	/**
	 * Maybe hide standard WC checkout button on the cart, if enabled
	 */
	public function maybe_hide_standard_checkout_button() {
		if ( ! $this->is_available() || $this->has_other_gateways_enabled() ) {
			return;
		}

		if ( 'yes' !== $this->settings['hide_standard_checkout_button'] ) {
			return;
		}

		?>
			<style type="text/css">
				.woocommerce a.checkout-button,
				.woocommerce input.checkout-button,
				.cart input.checkout-button,
				.cart a.checkout-button,
				.widget_shopping_cart a.checkout,
				.wc-apa-button-separator {
					display: none !important;
				}
			</style>
		<?php
	}

	/**
	 * Maybe set order transaction id.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $charge_permission_id Charge Permission.
	 * @param string   $charge_id Charge Id.
	 */
	public function maybe_set_transaction_id( $order, $charge_permission_id, $charge_id ) {
		if ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order ) || wcs_order_contains_renewal( $order ) ) ) {
			$charge_permission_id = substr( $charge_id, 0, strrpos( $charge_id, '-C' ) );
		}
		if ( ! empty( $charge_permission_id ) ) {
			$order->set_transaction_id( $charge_permission_id );
		}
	}

	/**
	 * Removes Amazon Pay from order-pay endpoint.
	 *
	 * @return void
	 */
	public function remove_amazon_gateway_order_pay() {
		if ( ! $this->is_logged_in() ) {
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_amazon_gateway' ) );
		}
	}

	/**
	 * Process the pay form.
	 *
	 * @throws Exception On payment error.
	 */
	public static function ajax_pay_action() {
		global $wp;
		if ( isset( $_POST['amazon-classic-action'], $_POST['woocommerce_pay'], $_POST['key'] ) ) {

			$nonce_value = wc_get_var( $_REQUEST['woocommerce-pay-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

			if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-pay' ) ) {
				self::send_ajax_failure_response();
			}

			ob_start();

			// Pay for existing order.
			$order_key = wp_unslash( $_POST['key'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$order_id  = absint( $wp->query_vars['order-pay'] );
			$order     = wc_get_order( $order_id );

			if ( $order_id === $order->get_id() && hash_equals( $order->get_order_key(), $order_key ) && $order->needs_payment() ) {

				do_action( 'woocommerce_before_pay_action', $order );

				WC()->customer->set_props(
					array(
						'billing_country'  => $order->get_billing_country() ? $order->get_billing_country() : null,
						'billing_state'    => $order->get_billing_state() ? $order->get_billing_state() : null,
						'billing_postcode' => $order->get_billing_postcode() ? $order->get_billing_postcode() : null,
						'billing_city'     => $order->get_billing_city() ? $order->get_billing_city() : null,
					)
				);
				WC()->customer->save();

				if ( ! empty( $_POST['terms-field'] ) && empty( $_POST['terms'] ) ) {
					wc_add_notice( __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-gateway-amazon-payments-advanced' ), 'error' );
					self::send_ajax_failure_response();
				}

				// Update payment method.
				if ( $order->needs_payment() ) {
					try {
						$payment_method_id = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : false;

						if ( ! $payment_method_id ) {
							throw new Exception( __( 'Invalid payment method.', 'woocommerce-gateway-amazon-payments-advanced' ) );
						}

						$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
						$payment_method     = isset( $available_gateways[ $payment_method_id ] ) ? $available_gateways[ $payment_method_id ] : false;

						if ( ! $payment_method ) {
							throw new Exception( __( 'Invalid payment method.', 'woocommerce-gateway-amazon-payments-advanced' ) );
						}

						$order->set_payment_method( $payment_method );
						$order->save();

						$payment_method->validate_fields();

						if ( 0 === wc_notice_count( 'error' ) ) {

							$result = $payment_method->process_payment( $order_id );

							// Redirect to success/confirmation/payment page.
							if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
								$result['order_id'] = $order_id;

								$result = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );

								wp_send_json( $result );
							}
						}
					} catch ( Exception $e ) {
						wc_add_notice( $e->getMessage(), 'error' );
					}
				} else {
					$order->payment_complete();
					wc_empty_cart();
					wp_send_json(
						array(
							'result'   => 'success',
							'redirect' => apply_filters( 'woocommerce_checkout_no_payment_needed_redirect', $order->get_checkout_order_received_url(), $order ),
						)
					);
				}
			}
			self::send_ajax_failure_response();
		}
	}

	/**
	 * If checkout failed during an AJAX call, send failure response.
	 */
	protected static function send_ajax_failure_response() {
		if ( is_ajax() ) {
			// Only print notices if not reloading the checkout, otherwise they're lost in the page reload.
			if ( ! isset( WC()->session->reload_checkout ) ) {
				$messages = wc_print_notices( true );
			}

			$response = array(
				'result'   => 'failure',
				'messages' => isset( $messages ) ? $messages : '',
				'refresh'  => isset( WC()->session->refresh_totals ),
				'reload'   => isset( WC()->session->reload_checkout ),
			);

			unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

			wp_send_json( $response );
		}
	}

	/**
	 * Loads scripts globally when the setting to display Amazon Pay button on mini cart is enabled.
	 *
	 * @param bool $load_scripts Whether to load Amazon Pay scripts or not.
	 * @return bool
	 */
	public function load_scripts_globally_if_button_enabled_on_cart( $load_scripts ) {
		return $load_scripts ? $load_scripts : $this->is_mini_cart_button_enabled();
	}

	/**
	 * Loads scripts on product pages when the setting to display Amazon Pay button on products is enabled.
	 *
	 * @param bool $load_scripts Whether to load Amazon Pay scripts or not.
	 * @return bool
	 */
	public function load_scripts_on_product_pages( $load_scripts ) {
		return $load_scripts ? $load_scripts : is_product() && $this->is_product_button_enabled();
	}

	/**
	 * Restores a cart stored in session.
	 *
	 * @return void
	 */
	public function remember_forgotten_cart() {
		if ( WC()->session->get( 'amazon_pay_cart' ) ) {
			foreach ( self::$session_keys as $session_key ) {
				WC()->session->set( $session_key, WC()->session->get( 'amazon_pay_' . $session_key ) );
				WC()->session->set( 'amazon_pay_' . $session_key, null );
			}
			WC()->session->save_data();
			WC()->cart->get_cart_from_session();
		}
	}

	/**
	 * Stores current cart in session and creates a new one.
	 * Notifies JS of the current checkout session config params.
	 *
	 * Ajax endpoint hooked in the 'amazon_change_wc_carts_change_wc_cart' action.
	 *
	 * @return void
	 */
	public function ajax_amazon_change_wc_carts() {
		check_ajax_referer( 'amazon_change_wc_carts', '_change_carts_nonce' );
		if ( ! empty( $_GET['product_id'] ) && ! empty( $_GET['quantity'] ) ) {
			$selected_product = absint( $_GET['product_id'] );
			$quantity         = absint( $_GET['quantity'] );
			$variation_id     = ! empty( $_GET['variation_id'] ) ? absint( $_GET['variation_id'] ) : 0;
			if ( $variation_id ) {
				$variation = new WC_Product_Variation( $variation_id );
				$attrs     = $variation->get_variation_attributes();
			} else {
				$attrs = array();
			}
			if ( WC()->cart->get_cart_contents_count() > 0 ) {
				WC()->session->set( 'amazon_pay_cart', WC()->cart->get_cart_for_session() );
				foreach ( self::$session_keys as $session_key ) {
					if ( 'cart' === $session_key ) {
						continue;
					}
					WC()->session->set( 'amazon_pay_' . $session_key, WC()->session->get( $session_key ) );
				}
				WC()->session->save_data();
				WC()->cart->empty_cart();
			}
			WC()->cart->add_to_cart( $selected_product, $quantity, $variation_id, $attrs );
			$checkout_session_config = WC_Amazon_Payments_Advanced_API::get_create_checkout_session_config();

			$data = array(
				'create_checkout_session_config' => $checkout_session_config,
				'estimated_order_amount'         => self::get_estimated_order_amount(),
			);
			wp_send_json_success( $data, 200 );
		}
		wp_send_json_error();
	}

	/**
	 * Checks if a product is a subscription while we don't support subscriptions.
	 *
	 * Runs before outputting the markup for the Amazon Pay button in single products.
	 * If the subscription support is enabled it will always return true.
	 * If not, it will return true only when the product is not a subscription.
	 *
	 * @return bool
	 */
	protected function possible_subscription_product_supported() {
		if ( ( ! empty( $this->settings['subscriptions_enabled'] ) && 'yes' === $this->settings['subscriptions_enabled'] ) || ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return true;
		}

		$pid = get_the_ID();

		/**
		 * If we can't access product id's while the WC_Subscriptions_Product class exists.
		 * We should return false.
		 */
		if ( ! $pid ) {
			return false;
		}

		return ! WC_Subscriptions_Product::is_subscription( $pid );
	}

	/**
	 * Checks if the current cart contains a subscription.
	 *
	 * Runs before outputting the markup for the Amazon Pay button in the mini cart.
	 * If the subscription support is enabled it will always return true.
	 * If not, it will return true only when the cart does not contain any subscriptions.
	 *
	 * @return bool
	 */
	protected function possible_subscription_cart_supported() {
		if ( 'yes' === $this->settings['subscriptions_enabled'] || ! class_exists( 'WC_Subscriptions_Cart' ) ) {
			return true;
		}

		return ! WC_Subscriptions_Cart::cart_contains_subscription();
	}

	/**
	 * Filter the payload to add the addressDetails data to the checkout session object.
	 *
	 * @param  array    $payload Payload to send to the API before proceeding to checkout.
	 * @param  string   $checkout_session_id Checkout Session Id.
	 * @param  WC_Order $order Order object.
	 * @param  bool     $doing_classic_payment Indicates whether this is an Amazon "Classic" Transaction or not.
	 * @return array
	 */
	public function update_address_details_for_classic( $payload, $checkout_session_id, $order, $doing_classic_payment ) {
		if ( ! $doing_classic_payment || 'PayAndShip' !== wc_apa()->get_gateway()->get_current_cart_action() ) {
			return $payload;
		}

		$phone_number = $order->get_shipping_phone();
		$phone_number = $phone_number ? $phone_number : $order->get_billing_phone();

		$shipping_state   = $order->get_shipping_state();
		$shipping_country = $order->get_shipping_country( 'edit' );

		if ( 'JP' === strtoupper( $shipping_country ) && 'JP' === strtoupper( WC_Amazon_Payments_Advanced_API::get_region() ) && isset( self::JP_REGION_CODE_MAP[ $shipping_state ] ) ) {
			$shipping_state = self::JP_REGION_CODE_MAP[ $shipping_state ];
		}

		$payload['addressDetails'] = array(
			'name'          => rawurlencode( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() ),
			'addressLine1'  => rawurlencode( $order->get_shipping_address_1() ),
			'addressLine2'  => rawurlencode( $order->get_shipping_address_2() ),
			'city'          => rawurlencode( $order->get_shipping_city() ),
			'stateOrRegion' => rawurlencode( $shipping_state ),
			'postalCode'    => rawurlencode( $order->get_shipping_postcode() ),
			'countryCode'   => $shipping_country,
			'phoneNumber'   => rawurlencode( $phone_number ),
		);

		/**
		 * Address Validation for the EU region.
		 *
		 * @see https://developer.amazon.com/docs/amazon-pay-checkout/address-formatting-and-validation.html#address-validation
		 */
		if ( in_array( $payload['addressDetails']['countryCode'], array( 'UK', 'GB', 'SG', 'AE', 'MX' ), true ) ) {
			$payload['addressDetails']['districtOrCounty'] = $payload['addressDetails']['stateOrRegion'];
			unset( $payload['addressDetails']['stateOrRegion'] );
			$payload['addressDetails'] = array_filter( $payload['addressDetails'] );
		}

		return $payload;
	}

	/**
	 * Get the estimated order amount from the cart totals.
	 *
	 * @return string
	 */
	private static function get_estimated_order_amount() {
		if ( null === WC()->cart ) {
			return '';
		}

		return wp_json_encode(
			array(
				'amount'       => WC()->cart->get_total( 'amount' ),
				'currencyCode' => get_woocommerce_currency(),
			)
		);
	}
}
