<?php
/**
 * Main class WooCommerce Amazon Pat REST API.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WooCommerce Amazon Pay REST API class.
 *
 * Expose additional functionalities to WP REST API for order paid with
 * Amazon Pay.
 *
 * @since 1.6.0
 */
class WC_Amazon_Payments_Advanced_REST_API_Controller extends WC_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders/(?P<order_id>[\d]+)/amazon-payments-advanced';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'shop_order';

	/**
	 * Register the routes for order notes.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/reference-state', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reference_state' ),
				'permission_callback' => array( $this, 'get_read_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/authorize', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'authorize' ),
				'permission_callback' => array( $this, 'get_edit_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/authorize-and-capture', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'authorize_and_capture' ),
				'permission_callback' => array( $this, 'get_edit_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/close-authorization', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'close_authorization' ),
				'permission_callback' => array( $this, 'get_edit_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/capture', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'capture' ),
				'permission_callback' => array( $this, 'get_edit_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/refund', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'refund' ),
				'permission_callback' => array( $this, 'get_edit_permissions_check' ),
				'args'                => array(
					'amount' => array(
						'description' => __( 'Refund amount.', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'string',
						'required'    => true,
					),
					'reason' => array(
						'description' => __( 'Reason for refund.', 'woocommerce-gateway-amazon-payments-advanced' ),
						'type'        => 'string',
					),
				),
			),
		) );

	}

	/**
	 * Check whether a given request has permission to read the resource.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_read_permissions_check( $request ) {
		return $this->get_permissions_check( $request, 'read' );
	}

	/**
	 * Check whether a given request has permission to edit the resource.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_edit_permissions_check( $request ) {
		return $this->get_permissions_check( $request, 'edit' );
	}

	/**
	 * Check whether a given request has permission to create a resource.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_create_permissions_check( $request ) {
		return $this->get_permissions_check( $request, 'create' );
	}

	/**
	 * Check whether a given request has permission to create a resource.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_delete_permissions_check( $request ) {
		return $this->get_permissions_check( $request, 'delete' );
	}

	/**
	 * Check whether a given request has permission to perform action at the resource.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $action Action (create, read, edit, or delete).
	 *
	 * @return WP_Error|boolean
	 */
	protected function get_permissions_check( $request, $action ) {
		$has_permission = false;

		switch ( $action ) {
			case 'read':
			case 'create':
				$has_permission = wc_rest_check_post_permissions( $this->post_type, $action );
				break;
			case 'delete':
			case 'edit':
				$post = get_post( (int) $request['order_id'] );
				$has_permission = (
					$post
					&&
					wc_rest_check_post_permissions( $this->post_type, $action, $post->ID )
				);
				break;
		}

		if ( ! $has_permission ) {
			return new WP_Error(
				sprintf( 'woocommerce_rest_cannot_%s', $action ),
				$this->get_permissions_check_error_message( $action ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get error message for insufficient permission accessing or modifying the
	 * resource.
	 *
	 * @since 1.7.0
	 *
	 * @param string $action Action (create, read, edit, or delete).
	 *
	 * @return string Error message.
	 */
	protected function get_permissions_check_error_message( $action ) {
		$message = __( 'Sorry, you don\'t have permission to access this resource.', 'woocommerce-gateway-amazon-payments-advanced' );
		switch ( $action ) {
			case 'read':
				$message = __( 'Sorry, you cannot view this resource.', 'woocommerce-gateway-amazon-payments-advanced' );
				break;
			case 'create':
				$message = __( 'Sorry, you cannot create this resource.', 'woocommerce-gateway-amazon-payments-advanced' );
				break;
			case 'delete':
				$message = __( 'Sorry, you cannot delete this resource.', 'woocommerce-gateway-amazon-payments-advanced' );
				break;
			case 'edit':
				$message = __( 'Sorry, you cannot edit this resource.', 'woocommerce-gateway-amazon-payments-advanced' );
				break;
		}

		return $message;
	}

	/**
	 * Get reference state.
	 *
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return array
	 */
	public function get_reference_state( $request ) {
		$order_post = get_post( (int) $request['order_id'] );

		if ( ! $this->is_valid_order( $order_post ) ) {
			return new WP_Error( 'woocommerce_rest_order_invalid_id', __( 'Invalid order ID.', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 404 ) );
		}

		// If `refresh=1` is passed, cache ref states will be cleared so it
		// makes a call to Amazon to get the details.
		if ( ! empty( $request['refresh'] ) ) {
			delete_post_meta( $order_post->ID, 'amazon_reference_state' );
			delete_post_meta( $order_post->ID, 'amazon_capture_state' );
			delete_post_meta( $order_post->ID, 'amazon_authorization_state' );
		}

		$ref_detail = array(
			'amazon_reference_state'     => WC_Amazon_Payments_Advanced_API::get_order_ref_state( $order_post->ID, 'amazon_reference_state' ),
			'amazon_reference_id'        => get_post_meta( $order_post->ID, 'amazon_reference_id', true ),
			'amazon_authorization_state' => WC_Amazon_Payments_Advanced_API::get_order_ref_state( $order_post->ID, 'amazon_authorization_state' ),
			'amazon_authorization_id'    => get_post_meta( $order_post->ID, 'amazon_authorization_id', true ),
			'amazon_capture_state'       => WC_Amazon_Payments_Advanced_API::get_order_ref_state( $order_post->ID, 'amazon_capture_state' ),
			'amazon_capture_id'          => get_post_meta( $order_post->ID, 'amazon_capture_id', true ),
		);

		return rest_ensure_response( $ref_detail );
	}

	/**
	 * Authorize specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	public function authorize( $request ) {
		$error = $this->get_missing_reference_id_request_error( $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return $this->authorize_order( (int) $request['order_id'] );
	}

	/**
	 * Authorize and capture specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	public function authorize_and_capture( $request ) {
		$error = $this->get_missing_reference_id_request_error( $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return $this->authorize_order( (int) $request['order_id'], array( 'capture_now' => true ) );
	}

	/**
	 * Authorize specified order.
	 *
	 * @param int   $order_id       Order Id.
	 * @param array $authorize_args Optional args to WC_Amazon_Payments_Advanced_API::authorize.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	protected function authorize_order( $order_id, $authorize_args = array() ) {
		$authorize_args = wp_parse_args( $authorize_args, array( 'capture_now' => false ) );

		$resp = WC_Amazon_Payments_Advanced_API::authorize( $order_id, $authorize_args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$result = WC_Amazon_Payments_Advanced_API::handle_payment_authorization_response( $resp, $order_id, $authorize_args['capture_now'] );

		$ret = array(
			'authorized'              => $result,
			'amazon_authorization_id' => get_post_meta( $order_id, 'amazon_authorization_id', true ),
		);

		if ( $authorize_args['capture_now'] ) {
			$ret['captured']          = $result;
			$ret['amazon_capture_id'] = get_post_meta( $order_id, 'amazon_capture_id', true );

			$order_closed = WC_Amazon_Payments_Advanced_API::close_order_reference( $order_id );
			$order_closed = ( ! is_wp_error( $order_closed ) && $order_closed );

			$ret['order_closed'] = $order_closed;
		}

		return rest_ensure_response( $ret );
	}

	/**
	 * Close the authorization of specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	public function close_authorization( $request ) {
		$error = $this->get_missing_authorization_id_request_error( $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$order_id = (int) $request['order_id'];
		$auth_id  = get_post_meta( $order_id, 'amazon_authorization_id', true );
		$resp     = WC_Amazon_Payments_Advanced_API::close_authorization( $order_id, $auth_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$ret = array(
			'authorization_closed' => $resp,
		);

		return rest_ensure_response( $ret );
	}

	/**
	 * Capture specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	public function capture( $request ) {
		$error = $this->get_missing_authorization_id_request_error( $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return $this->capture_order( (int) $request['order_id'] );
	}

	/**
	 * Capture the order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	protected function capture_order( $order_id ) {
		$resp = WC_Amazon_Payments_Advanced_API::capture( $order_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$result = WC_Amazon_Payments_Advanced_API::handle_payment_capture_response( $resp, $order_id );
		if ( $result ) {
			$order_closed = WC_Amazon_Payments_Advanced_API::close_order_reference( $order_id );
			$order_closed = ( ! is_wp_error( $order_closed ) && $order_closed );
		}

		$ret = array(
			'captured'          => $result,
			'amazon_capture_id' => get_post_meta( $order_id, 'amazon_capture_id', true ),
			'order_closed'      => $order_closed,
		);

		return rest_ensure_response( $ret );
	}

	/**
	 * Refund specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	public function refund( $request ) {
		$error = $this->get_missing_capture_id_request_error( $request );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$reason = ! empty( $request['reason'] ) ? $request['reason'] : null;

		return $this->refund_order( (int) $request['order_id'], $request['amount'], $reason );
	}

	/**
	 * Refund the order.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $amount   Amount to refund.
	 * @param string $reason   Reason for refund.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	protected function refund_order( $order_id, $amount, $reason = null ) {
		if ( 0 > $amount ) {
			return new WP_Error( 'woocommerce_rest_invalid_order_refund', __( 'Refund amount must be greater than zero.', 'woocommerce-gateway-amazon-payments-advanced' ), 400 );
		}

		$amazon_capture_id = get_post_meta( $order_id, 'amazon_capture_id', true );
		$refunded          = WC_Amazon_Payments_Advanced_API::refund_payment( $order_id, $amazon_capture_id, $amount, $reason );

		$ret = array( 'refunded' => $refunded );
		if ( $refunded ) {
			$ret['amazon_refund_id'] = get_post_meta( $order_id, 'amazon_refund_id', true );
		}

		return rest_ensure_response( $ret );
	}

	/**
	 * Get error from request when no reference_id from specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return null|WP_Error Null if there's no error in the request.
	 */
	protected function get_missing_reference_id_request_error( $request ) {
		$order_post = get_post( (int) $request['order_id'] );

		if ( ! $this->is_valid_order( $order_post ) ) {
			return new WP_Error( 'woocommerce_rest_order_invalid_id', __( 'Invalid order ID.', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 404 ) );
		}

		$ref_id = get_post_meta( $order_post->ID, 'amazon_reference_id', true );
		if ( ! $ref_id ) {
			return new WP_Error( 'woocommerce_rest_order_missing_amazon_reference_id', __( 'Specified resource does not have Amazon order reference ID', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Get error from request when no authorization_id from specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return null|WP_Error Null if there's no error in the request.
	 */
	protected function get_missing_authorization_id_request_error( $request ) {
		$order_post = get_post( (int) $request['order_id'] );

		if ( ! $this->is_valid_order( $order_post ) ) {
			return new WP_Error( 'woocommerce_rest_order_invalid_id', __( 'Invalid order ID.', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 404 ) );
		}

		$ref_id = get_post_meta( $order_post->ID, 'amazon_authorization_id', true );
		if ( ! $ref_id ) {
			return new WP_Error( 'woocommerce_rest_order_missing_amazon_authorization_id', __( 'Specified resource does not have Amazon authorization ID', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Get error from request when no capture_id from specified order.
	 *
	 * @param WP_REST_Request $request WP HTTP request.
	 *
	 * @return null|WP_Error Null if there's no error in the request.
	 */
	protected function get_missing_capture_id_request_error( $request ) {
		$order_post = get_post( (int) $request['order_id'] );

		if ( ! $this->is_valid_order( $order_post ) ) {
			return new WP_Error( 'woocommerce_rest_order_invalid_id', __( 'Invalid order ID.', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 404 ) );
		}

		$ref_id = get_post_meta( $order_post->ID, 'amazon_capture_id', true );
		if ( ! $ref_id ) {
			return new WP_Error( 'woocommerce_rest_order_missing_amazon_capture_id', __( 'Specified resource does not have Amazon capture ID', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Check whether order is valid to proceed.
	 *
	 * @param WP_Post $order_post Order post object.
	 *
	 * @return bool True if it's valid request.
	 */
	protected function is_valid_order( $order_post ) {
		if ( empty( $order_post->post_type ) || $this->post_type !== $order_post->post_type ) {
			return false;
		}

		return 'amazon_payments_advanced' === get_post_meta( $order_post->ID, '_payment_method', true );
	}
}
