<?php
/**
 * Amazon Pay Order Admin class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Handle admin orders interface
 */
class WC_Amazon_Payments_Advanced_Order_Admin {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'wp_ajax_amazon_order_action', array( $this, 'order_actions' ) );
		add_action( 'current_screen', array( $this, 'order_actions_non_ajax' ) );
	}

	/**
	 * AJAX handler that performs order actions.
	 */
	public function order_actions() {
		check_ajax_referer( 'amazon_order_action', 'security' );

		$order_id = absint( $_POST['order_id'] );
		$order    = wc_get_order( $order_id );
		$version  = version_compare( $order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';
		$id       = isset( $_POST['amazon_id'] ) ? wc_clean( $_POST['amazon_id'] ) : '';
		$action   = sanitize_title( $_POST['amazon_action'] );

		do_action( 'wc_amazon_do_order_action', $order, $id, $action, $version );

		die();
	}

	/**
	 * Non AJAX handler that performs order actions.
	 */
	public function order_actions_non_ajax() {
		if ( 'shop_order' !== get_current_screen()->id ) {
			return;
		}

		if ( ! isset( $_GET['amazon_action'] ) ) {
			return;
		}

		check_admin_referer( 'amazon_order_action', 'security' );

		$order_id = absint( $_GET['post'] ); // TODO: This may break when custom data stores are implemented in the future.
		$order    = wc_get_order( $order_id );

		$version = version_compare( $order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';

		$id       = isset( $_GET['amazon_id'] ) ? wc_clean( $_GET['amazon_id'] ) : '';
		$action   = sanitize_title( $_GET['amazon_action'] );

		do_action( 'wc_amazon_do_order_action', $order, $id, $action, $version );

		wp_safe_redirect( remove_query_arg( array( 'amazon_action', 'amazon_id', 'security' ) ) );
		exit;
	}

	/**
	 * Amazon Pay authorization metabox.
	 */
	public function meta_box() {
		global $post, $wpdb;

		$order_id = absint( $post->ID );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return;
		}

		add_meta_box( 'woocommerce-amazon-payments-advanced', __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ), array( $this, 'authorization_box' ), 'shop_order', 'side' );
	}

	/**
	 * Authorization metabox content.
	 */
	public function authorization_box() {
		global $post, $wpdb;

		$order_id = absint( $post->ID );
		$order    = wc_get_order( $order_id );

		$version = version_compare( $order->get_meta( 'amazon_payment_advanced_version' ), '2.0.0' ) >= 0 ? 'v2' : 'v1';

		do_action( 'wc_amazon_authorization_box_render', $order, $version );
	}

}
