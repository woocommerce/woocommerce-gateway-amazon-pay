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

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ), 10, 2 );
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
		$version  = WC_Amazon_Payments_Advanced::get_order_version( $order_id );
		$id       = isset( $_POST['amazon_id'] ) ? wc_clean( $_POST['amazon_id'] ) : '';
		$action   = sanitize_title( $_POST['amazon_action'] );

		do_action( 'wc_amazon_do_order_action', $order, $id, $action, $version );

		die();
	}

	/**
	 * Non AJAX handler that performs order actions.
	 */
	public function order_actions_non_ajax() {
		$screen_to_check = WC_Amazon_Payments_Advanced_Utils::get_edit_order_screen_id();

		if ( get_current_screen()->id !== $screen_to_check ) {
			return;
		}

		if ( ! isset( $_GET['amazon_action'] ) ) {
			return;
		}

		check_admin_referer( 'amazon_order_action', 'security' );

		// Find order id with HPOS disabled.
		$order_id = ! empty( $_GET['post'] ) ? $_GET['post'] : false;
		if ( ! $order_id ) {
			// Find order id with HPOS enabled.
			$order_id = ! empty( $_GET['id'] ) ? $_GET['id'] : false;
		}

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		$version = WC_Amazon_Payments_Advanced::get_order_version( $order_id );

		$id     = isset( $_GET['amazon_id'] ) ? wc_clean( $_GET['amazon_id'] ) : '';
		$action = sanitize_title( $_GET['amazon_action'] );

		do_action( 'wc_amazon_do_order_action', $order, $id, $action, $version );

		wp_safe_redirect( remove_query_arg( array( 'amazon_action', 'amazon_id', 'security' ) ) );
		exit;
	}

	/**
	 * Do the order action
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $id A charge_id, refund_id or charge_permission_id.
	 * @param  string   $action Action to be performed.
	 * @param  string   $version Version of the order.
	 */
	public function do_order_action( $order, $id, $action, $version ) {
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}
		$order_id = $order->get_id();
		wc_apa()->log( sprintf( 'Info: Trying to perform "%s" for order #%s', $action, $order_id ) );
		switch ( $action ) {
			case 'refresh':
				wc_apa()->get_gateway()->log_charge_permission_status_change( $order );
				wc_apa()->get_gateway()->log_charge_status_change( $order );
				break;
			case 'authorize':
			case 'authorize_capture':
				$capture_now = ( 'authorize_capture' === $action );
				wc_apa()->get_gateway()->perform_authorization( $order, $capture_now, $id );
				break;
			case 'close_authorization':
				wc_apa()->get_gateway()->perform_cancel_auth( $order, $id );
				break;
			case 'capture':
				wc_apa()->get_gateway()->perform_capture( $order, $id );
				break;
			case 'refund':
				wc_apa()->get_gateway()->perform_refund( $order, null, $id );
				break;
		}
	}

	/**
	 * Amazon Pay authorization metabox.
	 *
	 * @param Object           $screen The current screen object.
	 * @param WC_Order|WP_Post $order The current post/order object.
	 * @return void
	 */
	public function meta_box( $screen, $order ) {

		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order->ID );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return;
		}

		$screen = WC_Amazon_Payments_Advanced_Utils::get_edit_order_screen_id();

		$post_types = apply_filters( 'woocommerce_amazon_pa_admin_meta_box_post_types', array( $screen ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'woocommerce-amazon-payments-advanced',
				__( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ),
				array( $this, 'authorization_box' ),
				$post_type,
				'side'
			);
		}
	}

	/**
	 * Authorization metabox content.
	 *
	 * @param WC_Order|WP_Post $object The current post/order object.
	 * @return void
	 */
	public function authorization_box( $object ) {
		$order = $object instanceof WC_Order ? $object : wc_get_order( $object->ID );

		$version = WC_Amazon_Payments_Advanced::get_order_version( $order->get_id() );

		do_action( 'wc_amazon_authorization_box_render', $order, $version );
	}

	/**
	 * Turn a status object into a string for a label
	 *
	 * @param  object $status_details Status details object.
	 * @return string
	 */
	private function status_details_label( $status_details ) {
		$charge_status_full = $status_details->status;
		if ( ! empty( $status_details->reasons ) ) {
			$charge_status_full .= sprintf( ' (%1$s)', implode( ', ', wp_list_pluck( $status_details->reasons, 'reasonCode' ) ) );
		}

		return $charge_status_full;
	}

	/**
	 * Render the Admin meta box
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $version Version of the order.
	 */
	public function auth_box_render( $order, $version ) {
		if ( 'v2' !== strtolower( $version ) ) {
			return;
		}

		$actions = array(
			'refresh' => array(
				'id'     => $order->get_id(),
				'button' => __( 'Refresh', 'woocommerce-gateway-amazon-payments-advanced' ),
			),
		);

		$need_refresh = false;

		$charge_permission_id = WC_Amazon_Payments_Advanced::get_order_charge_permission( $order->get_id() );

		$charge_permission_cached_status = wc_apa()->get_gateway()->get_cached_charge_permission_status( $order );

		$charge_permission_status_label = ! is_wp_error( $charge_permission_cached_status ) ? $this->status_details_label( $charge_permission_cached_status ) : false;

		$charge_permission_status_label = $charge_permission_status_label ? $charge_permission_status_label : __( 'Invalid', 'woocommerce-gateway-amazon-payments-advanced' );
		/* translators: 1) Charge Permission ID 2) Status. */
		echo wpautop( sprintf( __( 'Charge Permission %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $charge_permission_id ), esc_html( $charge_permission_status_label ) ) );

		$charge_permission_status = ! is_wp_error( $charge_permission_cached_status ) ? $charge_permission_cached_status->status : '';

		switch ( $charge_permission_status ) {
			case 'Chargeable':
				$actions['authorize'] = array(
					'id'     => $charge_permission_id,
					'button' => __( 'Authorize', 'woocommerce-gateway-amazon-payments-advanced' ),
				);

				$actions['authorize_capture'] = array(
					'id'     => $charge_permission_id,
					'button' => __( 'Authorize &amp; Capture', 'woocommerce-gateway-amazon-payments-advanced' ),
				);
				$need_refresh                 = true;
				break;
			case 'Closed':
				break;
			case 'NonChargeable':
				$need_refresh = true;
				break;
			default:
				// TODO: This is an unknown state, maybe handle?
				break;
		}

		$charge_id = WC_Amazon_Payments_Advanced::get_order_charge_id( $order->get_id() );

		if ( ! empty( $charge_id ) ) {
			$charge_cached_status = wc_apa()->get_gateway()->get_cached_charge_status( $order );

			$charge_status_label = $this->status_details_label( $charge_cached_status );
			$charge_status_label = $charge_status_label ? $charge_status_label : __( 'Invalid', 'woocommerce-gateway-amazon-payments-advanced' );
			/* translators: 1) Charge ID 2) Status. */
			echo wpautop( sprintf( __( 'Charge %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $charge_id ), esc_html( $charge_status_label ) ) );

			$charge_status = $charge_cached_status->status;

			switch ( $charge_status ) {
				case 'AuthorizationInitiated':
					$actions['close_authorization'] = array(
						'id'     => $charge_id,
						'button' => __( 'Close Authorization', 'woocommerce-gateway-amazon-payments-advanced' ),
					);
					$need_refresh                   = true;
					break;
				case 'CaptureInitiated':
					$need_refresh = true;
					break;
				case 'Canceled':
				case 'Declined':
					break;
				case 'Authorized':
					$actions['capture'] = array(
						'id'     => $charge_id,
						'button' => __( 'Capture funds', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

					$actions['close_authorization'] = array(
						'id'     => $charge_id,
						'button' => __( 'Close Authorization', 'woocommerce-gateway-amazon-payments-advanced' ),
					);
					$need_refresh                   = true;
					break;
				case 'Captured':
					// TODO: Handle fully refunded charges.
					$actions['refund'] = array(
						'id'     => $charge_id,
						'button' => __( 'Make a refund?', 'woocommerce-gateway-amazon-payments-advanced' ),
						'href'   => '#toggle-refunds',
					);
					break;
				default:
					// TODO: This is an unknown state, maybe handle?
					break;
			}
		}

		if ( ! $need_refresh ) {
			unset( $actions['refresh'] );
		}

		$actions = apply_filters( 'woocommerce_amazon_pa_order_admin_actions', $actions, $order );

		if ( ! empty( $actions ) ) {
			echo '<p class="buttons">';
			foreach ( $actions as $action_name => $action ) {
				$url                            = add_query_arg(
					array(
						'amazon_action' => $action_name,
						'amazon_id'     => $action['id'],
						'security'      => wp_create_nonce( 'amazon_order_action' ),
					)
				);
				$actions[ $action_name ]['url'] = $url;
				if ( isset( $action['href'] ) ) {
					$url = $action['href'];
				}

				echo '<a href="' . esc_url( $url ) . '" class="button">' . esc_html( $action['button'] ) . '</a> ';
			}
			echo '</p>';
		}
	}

}
