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
		add_action( 'wc_amazon_authorization_box_render', array( $this, 'auth_box_render' ), 10, 2 );
		add_action( 'wc_amazon_do_order_action', array( $this, 'do_order_action' ), 10, 4 );
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

	public function do_order_action( $order, $id, $action, $version ) {
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}
		$order_id = $order->get_id();
		wc_apa()->log( __METHOD__, sprintf( 'Info: Trying to perform "%s" for order #%s', $action, $order_id ) );
		switch ( $action ) {
			case 'capture':
				$charge = WC_Amazon_Payments_Advanced_API::capture_charge( $id );
				$charge_status = wc_apa()->get_gateway()->log_charge_status_change( $order, $charge );
				break;
		}
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

	private function status_details_label( $status_details ) {
		$charge_status         = $status_details->state; // phpcs:ignore WordPress.NamingConventions
		$charge_status_reasons = $status_details->reasons; // phpcs:ignore WordPress.NamingConventions
		if ( empty( $charge_status_reasons ) ) {
			$charge_status_reasons = array();
		}
		$charge_status_reason  = $status_details->reasonCode; // phpcs:ignore WordPress.NamingConventions

		if ( $charge_status_reason ) {
			$charge_status_reasons[] = (object) array(
				'reasonCode' => $charge_status_reason,
				'reasonDescription' => '',
			);
		}

		$charge_status_full = $charge_status;
		if ( ! empty( $charge_status_reasons ) ) {
			$charge_status_full .= sprintf( ' (%1$s)', implode( ', ', wp_list_pluck( $charge_status_reasons, 'reasonCode' ) ) );
		}

		return $charge_status_full;
	}

	public function auth_box_render( $order, $version ) {
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}

		$actions = array();

		$charge_permission_id = $order->get_meta( 'amazon_charge_permission_id' );
		$charge_permission    = WC_Amazon_Payments_Advanced_API::get_charge_permission( $charge_permission_id );

		$charge_permission_status_label = $this->status_details_label( $charge_permission->statusDetails ); // phpcs:ignore WordPress.NamingConventions

		echo wpautop( sprintf( __( 'Charge Permission %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $charge_permission_id ), esc_html( $charge_permission_status_label ) ) );

		$charge_id = $order->get_meta( 'amazon_charge_id' );

		if ( ! empty( $charge_id ) ) {
			$charge               = WC_Amazon_Payments_Advanced_API::get_charge( $charge_id );

			$charge_status_label = $this->status_details_label( $charge->statusDetails ); // phpcs:ignore WordPress.NamingConventions

			echo wpautop( sprintf( __( 'Charge %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $charge_id ), esc_html( $charge_status_label ) ) );

			$charge_status = $charge->statusDetails->state; // phpcs:ignore WordPress.NamingConventions

			switch ( $charge_status ) {
				case 'AuthorizationInitiated':
				case 'CaptureInitiated':
					break;
				case 'Canceled':
				case 'Declined':
					break;
				case 'Authorized':
					$actions['capture'] = array(
						'id'     => $charge_id,
						'button' => __( 'Capture funds', 'woocommerce-gateway-amazon-payments-advanced' ),
					);
					break;
				case 'Captured':
					break;
				default:
					// TODO: This is an unknown state, maybe handle?
					break;
			}
		}

		if ( ! empty( $actions ) ) {
			echo '<p class="buttons">';
			foreach ( $actions as $action_name => $action ) {
				$url = add_query_arg(
					array(
						'amazon_action' => $action_name,
						'amazon_id'     => $action['id'],
						'security'      => wp_create_nonce( 'amazon_order_action' ),
					)
				);
				echo '<a href="' . $url . '" class="button">' . esc_html( $action['button'] ) . '</a> ';
			}
			echo '</p>';
		}
	}

}
