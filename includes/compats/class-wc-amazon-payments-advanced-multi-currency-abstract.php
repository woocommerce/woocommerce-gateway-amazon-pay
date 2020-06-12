<?php
/**
 * Abstract Class for all multi currency classes.
 *
 * @package WC_Gateway_Amazon_Pay\Compats
 */

/**
 * WooCommerce Multi-currency Abstract.
 */
abstract class WC_Amazon_Payments_Advanced_Multi_Currency_Abstract {

	const ORIGINAL_CURRENCY_SESSION       = 'original_currency';
	const CURRENCY_TIMES_SWITCHED_SESSION = 'currency_times_switched';

	/**
	 * On Suspended Order References, we have to bypass currency switch.
	 */
	const CURRENCY_BYPASS_SESSION = 'currency_bypass';

	public function __construct() {
		//If multi-currency plugin acts only on frontend level, no need to hook.
		if ( $this->is_front_end_compatible() ) {
			return;
		}

		// If selected currency is not compatible with Amazon.
		if ( ! $this->is_currency_compatible( $this->get_selected_currency() ) ) {
			add_filter( 'woocommerce_amazon_payments_hide_amazon_buttons', '__return_true' );
			add_filter(
				'woocommerce_amazon_payments_logout_checkout_message_html', function() {
					return '';
				}
			);
			add_action( 'woocommerce_checkout_init', array( $this, 'remove_amazon_functionality' ), 99 );
			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'remove_amazon_gateway' ) );
			add_filter( 'woocommerce_pa_hijack_checkout_fields', '__return_false' );
		}

		// Currency switching observer.
		add_action( 'woocommerce_amazon_checkout_init', array( $this, 'capture_original_checkout_currency' ) );
		add_action( 'woocommerce_thankyou_amazon_payments_advanced', array( $this, 'delete_currency_session' ) );
		add_action( 'init', array( $this, 'is_amazon_logout' ), 999 );

		// Add AJAX call to retrieve current currency on frontend
		add_action( 'wp_ajax_amazon_get_currency', array( $this, 'ajax_get_currency' ) );
		add_action( 'wp_ajax_nopriv_amazon_get_currency', array( $this, 'ajax_get_currency' ) );
	}

	/**
	 * @return string
	 */
	abstract public function get_selected_currency();

	/**
	 * If Multi-currency plugin is frontend compatible, meaning all currency changes happens only on frontend level.
	 *
	 * @return bool
	 */
	public function is_front_end_compatible() {
		return false;
	}

	/**
	 * Flag if we need to reload Amazon wallet on frontend.
	 * @return bool
	 */
	public function reload_wallet_widget() {
		return false;
	}

	/**
	 * Check if the $currency_selected is compatible with amazon (and has been selected on settings).
	 *
	 * @param string $currency_selected
	 *
	 * @return bool
	 */
	public function is_currency_compatible( $currency_selected ) {
		$amazon_selected_currencies = WC_Amazon_Payments_Advanced_API::get_selected_currencies();
		return ( false !== ( array_search( $currency_selected, $amazon_selected_currencies ) ) );
	}

	/**
	 * Remove amazon gateway.
	 *
	 * @param $gateways
	 *
	 * @return array
	 */
	public function remove_amazon_gateway( $gateways ) {
		foreach ( $gateways as $gateway_key => $gateway ) {
			if ( 'amazon_payments_advanced' === $gateway_key ) {
				unset( $gateways[ $gateway_key ] );
			}
		}

		return $gateways;
	}

	/**
	 * Remove rendering amazon widgets on checkout page.
	 * Remove filter that removes all available payment gateways.
	 */
	public function remove_amazon_functionality() {
		remove_action( 'woocommerce_checkout_before_customer_details', array( wc_apa(), 'payment_widget' ), 20 );
		remove_action( 'woocommerce_checkout_before_customer_details', array( wc_apa(), 'address_widget' ), 10 );
		remove_filter( 'woocommerce_available_payment_gateways', array( wc_apa(), 'remove_gateways' ), 10 );
	}

	/**
	 * Capture original currency used on checkout and save it to Session.
	 */
	public function capture_original_checkout_currency() {
		$original_currency = WC()->session->get( self::ORIGINAL_CURRENCY_SESSION );

		if ( ! $original_currency ) {
			WC()->session->set( self::ORIGINAL_CURRENCY_SESSION, $this->get_selected_currency() );
			WC()->session->set( self::CURRENCY_TIMES_SWITCHED_SESSION, 0 );
		} else {
			// Only increase once, on ajax checkout render.
			if ( is_ajax() ) {
				$switched_times = WC()->session->get( self::CURRENCY_TIMES_SWITCHED_SESSION );
				WC()->session->set( self::CURRENCY_TIMES_SWITCHED_SESSION, ( $switched_times + 1 ) );
			}
		}
	}

	/**
	 * Triggered on thank you hook, it will delete currency session.
	 */
	public function delete_currency_session() {
		WC()->session->__unset( self::ORIGINAL_CURRENCY_SESSION );
		WC()->session->__unset( self::CURRENCY_TIMES_SWITCHED_SESSION );
		WC()->session->__unset( self::CURRENCY_BYPASS_SESSION );
	}

	/**
	 * Option to bypass currency session.
	 * This will be triggered on order reference statuses equal to pending, where is not allowed switching multicurrency.
	 */
	public function bypass_currency_session() {
		WC()->session->set( self::CURRENCY_BYPASS_SESSION, true );
	}

	/**
	 * If Amazon is logging out, delete currency session.
	 */
	public function is_amazon_logout() {
		if ( ! empty( $_GET['amazon_payments_advanced'] ) && ! empty( $_GET['amazon_logout'] ) ) {
			$this->delete_currency_session();
		}
	}

	/**
	 * Get original currency session.
	 *
	 * @return string
	 */
	public function get_original_checkout_currency() {
		return WC()->session->get( self::ORIGINAL_CURRENCY_SESSION );
	}

	/**
	 * Get amount of times currencies have been changed.
	 *
	 * @return string
	 */
	public function get_currency_switched_times() {
		return ( WC()->session->get( self::CURRENCY_BYPASS_SESSION ) ) ? 0 : WC()->session->get( self::CURRENCY_TIMES_SWITCHED_SESSION );
	}

	/**
	 * Get selected currency, to be used on frontend.
	 */
	public function ajax_get_currency() {
		check_ajax_referer( 'multi_currency_nonce', 'nonce' );
		echo $this->get_selected_currency();
		wp_die();
	}

	/**
	 * If current order reference on checkout, after invalid payment method for instance, check if status is Suspended.
	 *
	 * @return bool
	 */
	function is_order_reference_checkout_suspended() {
		if ( ! defined( 'DOING_AJAX' ) && isset( WC()->session->amazon_reference_id ) && WC()->session->order_awaiting_payment > 0 ) {
			$order_awaiting_payment = WC()->session->order_awaiting_payment;

			// Sometimes called in a hook to soon.
			if ( did_action( 'woocommerce_after_register_post_type' ) ) {
				$order                  = wc_get_order( $order_awaiting_payment );
				$amazon_reference_state = $order->get_meta( 'amazon_reference_state' );
				$amazon_reference_id    = $order->get_meta( 'amazon_reference_id' );
			} else {
				$amazon_reference_state = get_post_meta( $order_awaiting_payment, 'amazon_reference_state', true );
				$amazon_reference_id    = get_post_meta( $order_awaiting_payment, 'amazon_reference_id', true );
			}

			// If amazon_reference_id order_awaiting_payment's order is not the current amazon_reference_id on session, bail out.
			if ( WC()->session->amazon_reference_id !== $amazon_reference_id ) {
				return false;
			}

			if ( 'suspended' === strtolower( $amazon_reference_state ) ) {
				return true;
			}

			$amazon_authorization_state = WC_Amazon_Payments_Advanced_API::get_reference_state( $order_awaiting_payment, $amazon_reference_id );
			if ( 'suspended' === strtolower( $amazon_authorization_state ) ) {
				return true;
			}
		}
		return false;
	}

}

