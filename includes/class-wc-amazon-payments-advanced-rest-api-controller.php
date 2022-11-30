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
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reference-state',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_reference_state' ),
					'permission_callback' => array( $this, 'get_read_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/authorize',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'authorize' ),
					'permission_callback' => array( $this, 'get_edit_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/authorize-and-capture',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'authorize_and_capture' ),
					'permission_callback' => array( $this, 'get_edit_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/close-authorization',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'close_authorization' ),
					'permission_callback' => array( $this, 'get_edit_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/capture',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'capture' ),
					'permission_callback' => array( $this, 'get_edit_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/refund',
			array(
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
			)
		);

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
				$post           = wc_get_order( (int) $request['order_id'] );
				$has_permission = (
					$post instanceof \WC_Order
					&&
					wc_rest_check_post_permissions( $this->post_type, $action, $post->get_id() )
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
	 * @return array|WP_Error|WP_REST_Response
	 */
	public function get_reference_state( $request ) {
		$or = $this->is_valid_order( $request['order_id'] );

		if ( is_wp_error( $or ) ) {
			return $or;
		}

		// If `refresh=1` is passed, cache ref states will be cleared so it
		// makes a call to Amazon to get the details.
		if ( ! empty( $request['refresh'] ) ) {
			$or->delete_meta_data( 'amazon_reference_state' );
			$or->delete_meta_data( 'amazon_capture_state' );
			$or->delete_meta_data( 'amazon_authorization_state' );
			$or->save();
			wc_apa()->get_gateway()->refresh_cached_charge_permission_status( $or );
			wc_apa()->get_gateway()->get_cached_charge_status( $or );
		}

		$charge_permission_id            = WC_Amazon_Payments_Advanced::get_order_charge_permission( $or->get_id() );
		$charge_permission_cached_status = wc_apa()->get_gateway()->get_cached_charge_permission_status( $or, true );

		$charge_id            = WC_Amazon_Payments_Advanced::get_order_charge_id( $or->get_id() );
		$charge_cached_status = wc_apa()->get_gateway()->get_cached_charge_status( $or, true );

		// TODO: Implement subscriptions v1 billing agreement, along with auth and capture methods for that.

		$ref_detail = array(
			'amazon_reference_state'         => WC_Amazon_Payments_Advanced_API_Legacy::get_order_ref_state( $or->get_id(), 'amazon_reference_state' ),
			'amazon_reference_id'            => $or->get_meta( 'amazon_reference_id', true, 'edit' ),
			'amazon_authorization_state'     => WC_Amazon_Payments_Advanced_API_Legacy::get_order_ref_state( $or->get_id(), 'amazon_authorization_state' ),
			'amazon_authorization_id'        => $or->get_meta( 'amazon_authorization_id', true, 'edit' ),
			'amazon_capture_state'           => WC_Amazon_Payments_Advanced_API_Legacy::get_order_ref_state( $or->get_id(), 'amazon_capture_state' ),
			'amazon_capture_id'              => $or->get_meta( 'amazon_capture_id', true, 'edit' ),
			'amazon_charge_permission_state' => $charge_permission_cached_status->status ? $charge_permission_cached_status->status : '',
			'amazon_charge_permission_id'    => $charge_permission_id,
			'amazon_charge_state'            => $charge_cached_status->status ? $charge_cached_status->status : '',
			'amazon_charge_id'               => $charge_id,
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
		$or = $this->is_valid_order( $request['order_id'] );
		if ( is_wp_error( $or ) ) {
			return $or;
		}

		$version = WC_Amazon_Payments_Advanced::get_order_version( $or->get_id() );
		if ( 'v1' === strtolower( $version ) ) {
			$error = $this->get_missing_reference_id_request_error( $or );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			return $this->authorize_order_v1( $or->get_id() );
		} else {
			$result             = array();
			$charge             = wc_apa()->get_gateway()->perform_authorization( $or, false );
			$result['captured'] = false;
			if ( is_wp_error( $charge ) ) {
				$result['authorized'] = false;
			} else {
				$result['authorized']       = true;
				$result['amazon_charge_id'] = WC_Amazon_Payments_Advanced::get_order_charge_id( $or->get_id() );
			}
			return rest_ensure_response( $result );
		}
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
		$or = $this->is_valid_order( $request['order_id'] );
		if ( is_wp_error( $or ) ) {
			return $or;
		}

		$version = WC_Amazon_Payments_Advanced::get_order_version( $or->get_id() );
		if ( 'v1' === strtolower( $version ) ) {
			$error = $this->get_missing_reference_id_request_error( $or );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			return $this->authorize_order_v1( $or->get_id(), array( 'capture_now' => true ) );
		} else {
			$result = array();
			$charge = wc_apa()->get_gateway()->perform_authorization( $or, true );
			if ( is_wp_error( $charge ) ) {
				$result['authorized'] = false;
				$result['captured']   = false;
			} else {
				$result['authorized']       = true;
				$result['captured']         = true;
				$result['amazon_charge_id'] = WC_Amazon_Payments_Advanced::get_order_charge_id( $or->get_id() );
			}
			return rest_ensure_response( $result );
		}
	}

	/**
	 * Authorize specified order.
	 *
	 * @param int   $order_id       Order Id.
	 * @param array $authorize_args Optional args to WC_Amazon_Payments_Advanced_API_Legacy::authorize.
	 *
	 * @return WP_Error|WP_HTTP_Response WP_Error if response generated an error,
	 *                                   WP_HTTP_Response if response is already
	 *                                   an instance, otherwise returns a new
	 *                                   WP_REST_Response instance.
	 */
	protected function authorize_order_v1( $order_id, $authorize_args = array() ) {
		$authorize_args = wp_parse_args( $authorize_args, array( 'capture_now' => false ) );

		$resp = WC_Amazon_Payments_Advanced_API_Legacy::authorize( $order_id, $authorize_args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$or = wc_get_order( $order_id );

		$result = WC_Amazon_Payments_Advanced_API_Legacy::handle_payment_authorization_response( $resp, $order_id, $authorize_args['capture_now'] );

		$ret = array(
			'authorized'              => $result,
			'amazon_authorization_id' => $or->get_meta( 'amazon_authorization_id', true, 'edit' ),
		);

		if ( $authorize_args['capture_now'] ) {
			$ret['captured']          = $result;
			$ret['amazon_capture_id'] = $or->get_meta( 'amazon_capture_id', true, 'edit' );

			$order_closed = WC_Amazon_Payments_Advanced_API_Legacy::close_order_reference( $order_id );
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
		$or = $this->is_valid_order( $request['order_id'] );
		if ( is_wp_error( $or ) ) {
			return $or;
		}

		$version = WC_Amazon_Payments_Advanced::get_order_version( $or->get_id() );
		if ( 'v1' === strtolower( $version ) ) {
			$error = $this->get_missing_authorization_id_request_error( $or );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			$auth_id  = $or->get_meta( 'amazon_authorization_id', true, true );
			$resp     = WC_Amazon_Payments_Advanced_API_Legacy::close_authorization( $or->get_id(), $auth_id );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}

			$ret = array(
				'authorization_closed' => $resp,
			);

			return rest_ensure_response( $ret );
		} else {
			$result = array();
			$charge = wc_apa()->get_gateway()->perform_cancel_auth( $or );
			if ( is_wp_error( $charge ) ) {
				$result['authorization_closed'] = false;
			} else {
				$result['authorization_closed'] = true;
			}
			return rest_ensure_response( $result );
		}
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
		$or = $this->is_valid_order( $request['order_id'] );
		if ( is_wp_error( $or ) ) {
			return $or;
		}

		$version = WC_Amazon_Payments_Advanced::get_order_version( $or->get_id() );
		if ( 'v1' === strtolower( $version ) ) {
			$error = $this->get_missing_authorization_id_request_error( $or );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			$resp = WC_Amazon_Payments_Advanced_API_Legacy::capture( $or->get_id() );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}

			$order_closed = false;

			$result = WC_Amazon_Payments_Advanced_API_Legacy::handle_payment_capture_response( $resp, $or->get_id() );
			if ( $result ) {
				$order_closed = WC_Amazon_Payments_Advanced_API_Legacy::close_order_reference( $or->get_id() );
				$order_closed = ( ! is_wp_error( $order_closed ) && $order_closed );
			}

			$ret = array(
				'captured'          => $result,
				'amazon_capture_id' => $or->get_meta( 'amazon_capture_id', true, 'edit' ),
				'order_closed'      => $order_closed,
			);

			return rest_ensure_response( $ret );
		} else {
			$result = array();
			$charge = wc_apa()->get_gateway()->perform_capture( $or );
			if ( is_wp_error( $charge ) ) {
				$result['captured'] = false;
			} else {
				$result['captured']         = true;
				$result['amazon_charge_id'] = WC_Amazon_Payments_Advanced::get_order_charge_id( $or->get_id() );
			}
			return rest_ensure_response( $result );
		}
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
		$or = $this->is_valid_order( $request['order_id'] );
		if ( is_wp_error( $or ) ) {
			return $or;
		}

		$version = WC_Amazon_Payments_Advanced::get_order_version( $or->get_id() );
		if ( 'v1' === strtolower( $version ) ) {
			$error = $this->get_missing_capture_id_request_error( $or );
			if ( is_wp_error( $error ) ) {
				return $error;
			}

			$amount   = $request['amount'];
			$reason   = ! empty( $request['reason'] ) ? $request['reason'] : null;

			if ( 0 > $amount ) {
				return new WP_Error( 'woocommerce_rest_invalid_order_refund', __( 'Refund amount must be greater than zero.', 'woocommerce-gateway-amazon-payments-advanced' ), 400 );
			}

			$amazon_capture_id = $or->get_meta( 'amazon_capture_id', true, 'edit' );
			$refunded          = WC_Amazon_Payments_Advanced_API_Legacy::refund_payment( $or->get_id(), $amazon_capture_id, $amount, $reason );

			$ret = array( 'refunded' => $refunded );
			if ( $refunded ) {
				$ret['amazon_refund_id'] = $or->get_meta( 'amazon_refund_id', true, 'edit' );
			}

			return rest_ensure_response( $ret );
		} else {
			$amount = $request['amount'];

			// TODO: Reason is not implemented in API v2.
			$reason = ! empty( $request['reason'] ) ? $request['reason'] : null;

			if ( 0 > $amount ) {
				return new WP_Error( 'woocommerce_rest_invalid_order_refund', __( 'Refund amount must be greater than zero.', 'woocommerce-gateway-amazon-payments-advanced' ), 400 );
			}

			$result = array();

			$refund = wc_apa()->get_gateway()->perform_refund( $or, $amount );
			if ( is_wp_error( $refund ) ) {
				$result['refunded'] = false;
			} else {
				$result['refunded']         = true;
				$result['amazon_refund_id'] = $refund->refundId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
			return rest_ensure_response( $result );
		}
	}

	/**
	 * Get error from request when no reference_id from specified order.
	 *
	 * @param WP_Post $order_post WP Post object.
	 *
	 * @return null|WP_Error Null if there's no error in the request.
	 */
	protected function get_missing_reference_id_request_error( $order_post ) {
		$ref_id = $order_post->get_meta( 'amazon_reference_id', true, 'edit' );
		if ( ! $ref_id ) {
			return new WP_Error( 'woocommerce_rest_order_missing_amazon_reference_id', __( 'Specified resource does not have Amazon order reference ID', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Get error from request when no authorization_id from specified order.
	 *
	 * @param WP_Post $order_post WP Post object.
	 *
	 * @return null|WP_Error Null if there's no error in the request.
	 */
	protected function get_missing_authorization_id_request_error( $order_post ) {
		$ref_id = $order_post->get_meta( 'amazon_authorization_id', true, 'edit' );
		if ( ! $ref_id ) {
			return new WP_Error( 'woocommerce_rest_order_missing_amazon_authorization_id', __( 'Specified resource does not have Amazon authorization ID', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Get error from request when no capture_id from specified order.
	 *
	 * @param WP_Post $order_post WP Post object.
	 *
	 * @return null|WP_Error Null if there's no error in the request.
	 */
	protected function get_missing_capture_id_request_error( $order_post ) {
		$ref_id = $order_post->get_meta( 'amazon_capture_id', true, 'edit' );
		if ( ! $ref_id ) {
			return new WP_Error( 'woocommerce_rest_order_missing_amazon_capture_id', __( 'Specified resource does not have Amazon capture ID', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 400 ) );
		}

		return null;
	}

	/**
	 * Check whether order is valid to proceed.
	 *
	 * @param int $order_id Order post ID.
	 *
	 * @return WP_Post|WP_Error Post object if it's valid, WP_Error if it's invalid.
	 */
	protected function is_valid_order( $order_id ) {
		$or = wc_get_order( (int) $order_id );

		if ( ! ( $or instanceof \WC_Order ) ) {
			return new WP_Error( 'woocommerce_rest_order_invalid_id', __( 'Invalid order ID.', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 404 ) );
		}

		$is_valid = 'amazon_payments_advanced' === $or->get_payment_method()->id;

		return $is_valid ? $or : new WP_Error( 'woocommerce_rest_order_invalid_id', __( 'Invalid order ID.', 'woocommerce-gateway-amazon-payments-advanced' ), array( 'status' => 404 ) );
	}
}
