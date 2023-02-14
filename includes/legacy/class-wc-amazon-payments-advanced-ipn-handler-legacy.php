<?php
/**
 * IPN Legacy Handling.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Amazon_Payments_Advanced_IPN_Handler_Legacy
 */
class WC_Amazon_Payments_Advanced_IPN_Handler_Legacy extends WC_Amazon_Payments_Advanced_IPN_Handler_Abstract {
	/**
	 * Required keys for subscription confirmation type.
	 *
	 * @see self::$required_data_keys.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @var array
	 */
	protected $required_subscription_keys_v1 = array(
		array( 'SubscribeURL', 'SubscribeUrl' ),
		'Token',
	);

	/**
	 * Required keys for notification message.
	 *
	 * @see self::$required_data_keys.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @var array
	 */
	protected $required_notification_message_keys_v1 = array(
		'NotificationType',
		'NotificationData',
		'NotificationReferenceId',
		'SellerId',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Validate Keys for V1 Messages.
		add_action( 'woocommerce_amazon_payments_advanced_ipn_validate_notification_keys', array( $this, 'validate_notification_keys_v1' ), 10, 2 );
		add_action( 'woocommerce_amazon_payments_advanced_ipn_validate_subscription_keys', array( $this, 'validate_subscription_keys_v1' ), 10, 1 );

		// Handle valid IPN message.
		add_action( 'woocommerce_amazon_payments_advanced_handle_ipn', array( $this, 'handle_notification_ipn_v1' ) );

		// Scheduled event to check pending synchronous payments on wrong ipn setting.
		add_action( 'wcga_process_pending_syncro_payments', array( $this, 'process_pending_syncro_payments' ), 10, 2 );
		// Unschedule on IPN recieve.
		add_action( 'woocommerce_amazon_payments_advanced_handle_ipn_order', array( $this, 'unschedule_pending_syncro_payments' ) );
	}

	/**
	 * Validate IPN Legacy Message
	 *
	 * @param  array  $message IPN Message.
	 * @param  string $notification_version Notification Version.
	 */
	public function validate_notification_keys_v1( $message, $notification_version ) {
		if ( 'v1' !== $notification_version ) {
			return;
		}
		$this->validate_required_keys( $message['Message'], $this->required_notification_message_keys_v1 );
	}

	/**
	 * Validate IPN Legacy Message for subscriptions
	 *
	 * @param  mixed $message IPN Message.
	 */
	public function validate_subscription_keys_v1( $message ) {
		$this->validate_required_keys( $message['Message'], $this->required_subscription_keys_v1 );
	}

	/**
	 * Handle the IPN.
	 *
	 * At this point, notification message is validated already.
	 *
	 * @throws Exception Missing handler for the notification type.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param array $message Parsed SNS message.
	 */
	public function handle_notification_ipn_v1( $message ) {
		// Ignore non-notification type message.
		if ( 'Notification' !== $message['Type'] ) {
			return;
		}

		$notification_version = isset( $message['Message']['NotificationVersion'] ) ? strtolower( $message['Message']['NotificationVersion'] ) : 'v1';

		if ( 'v1' !== $notification_version || WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler::get_migration_status() ) {
			return;
		}

		$notification      = $message['Message'];
		$notification_data = $this->get_parsed_notification_data( $notification );
		$order             = $this->get_order_from_notification_data( $notification['NotificationType'], $notification_data );

		do_action( 'woocommerce_amazon_payments_advanced_handle_ipn_order', $order );

		switch ( $notification['NotificationType'] ) {
			case 'OrderReferenceNotification':
				$this->handle_ipn_order_reference( $order, $notification_data );
				break;
			case 'PaymentAuthorize':
				$this->handle_ipn_payment_authorize( $order, $notification_data );
				break;
			case 'PaymentCapture':
				$this->handle_ipn_payment_capture( $order, $notification_data );
				break;
			case 'PaymentRefund':
				$this->handle_ipn_payment_refund( $order, $notification_data );
				break;
			default:
				throw new Exception( 'No handler for notification with type ' . $notification['NotificationType'] );
		}
	}

	/**
	 * Handle IPN for order reference notification.
	 *
	 * Currently only log the event in the order notes.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param WC_Order         $order Order object.
	 * @param SimpleXMLElement $notification_data Notification data.
	 */
	protected function handle_ipn_order_reference( $order, $notification_data ) {
		// @codingStandardsIgnoreStart
		$order->add_order_note( sprintf(
			/* translators: 1) Amazon order reference ID 2) order reference status */
			__( 'Received IPN for order reference %1$s with status %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			(string) $notification_data->OrderReference->AmazonOrderReferenceId,
			(string) $notification_data->OrderReference->OrderReferenceStatus->State
		) );
		// @codingStandardsIgnoreEnd

		if ( $order->get_meta( 'amazon_timed_out_transaction' ) ) {
			WC_Amazon_Payments_Advanced_API_Legacy::handle_async_ipn_order_reference_payload( $notification_data, $order );
		}
	}

	/**
	 * Handle IPN for payment authorize notification.
	 *
	 * Currently only log the event in the order notes.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param WC_Order         $order Order object.
	 * @param SimpleXMLElement $notification_data Notification data.
	 */
	protected function handle_ipn_payment_authorize( $order, $notification_data ) {
		// @codingStandardsIgnoreStart
		$order->add_order_note( sprintf(
			/* translators: 1) Amazon authorize ID 2) authorization status */
			__( 'Received IPN for payment authorize %1$s with status %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			(string) $notification_data->AuthorizationDetails->AmazonAuthorizationId,
			(string) $notification_data->AuthorizationDetails->AuthorizationStatus->State
		) );
		// @codingStandardsIgnoreEnd

		if ( $order->get_meta( 'amazon_timed_out_transaction' ) ) {
			WC_Amazon_Payments_Advanced_API_Legacy::handle_async_ipn_payment_authorization_payload( $notification_data, $order );
		}
	}

	/**
	 * Handle IPN for payment capture notification.
	 *
	 * Currently only log the event in the order notes.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param WC_Order         $order Order object.
	 * @param SimpleXMLElement $notification_data Notification data.
	 */
	protected function handle_ipn_payment_capture( $order, $notification_data ) {
		// @codingStandardsIgnoreStart
		$order->add_order_note( sprintf(
			/* translators: 1) Amazon capture ID 2) capture status */
			__( 'Received IPN for payment capture %1$s with status %2$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			(string) $notification_data->CaptureDetails->AmazonCaptureId,
			(string) $notification_data->CaptureDetails->CaptureStatus->State
		) );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Handle IPN for payment payment refund.
	 *
	 * Currently only log the event in the order notes.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param WC_Order         $order Order object.
	 * @param SimpleXMLElement $notification_data Notification data.
	 */
	protected function handle_ipn_payment_refund( $order, $notification_data ) {
		// @codingStandardsIgnoreStart
		$refund_id     = (string) $notification_data->RefundDetails->AmazonRefundId;
		$refund_status = (string) $notification_data->RefundDetails->RefundStatus->State;
		$refund_amount = (float)  $notification_data->RefundDetails->RefundAmount->Amount;
		$refund_type   = (string) $notification_data->RefundDetails->RefundType;
		$refund_reason = (string) $notification_data->RefundDetails->SellerRefundNote;
		// @codingStandardsIgnoreEnd

		$order->add_order_note(
			sprintf(
				// translators: 1) Amazon refund ID 2) refund status 3) refund amount.
				__( 'Received IPN for payment refund %1$s with status %2$s. Refund amount: %3$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
				$refund_id,
				$refund_status,
				wc_price( $refund_amount )
			)
		);

		if ( 'refunded' === $order->get_status() ) {
			return;
		}

		$max_refund = wc_format_decimal( $order->get_total() - $order->get_total_refunded() );

		if ( ! $max_refund ) {
			return;
		}

		$refund_amount = min( $refund_amount, $max_refund );

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$wc_refund        = false;
		$previous_refunds = wp_list_pluck( $order->get_meta( 'amazon_refund_id', false ), 'value' );
		if ( ! empty( $previous_refunds ) ) {
			foreach ( $previous_refunds as $this_refund_id ) {
				if ( $this_refund_id === $refund_id ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$wc_refund = true;
					break;
				}
			}
		}
		if ( empty( $wc_refund ) ) {
			wc_create_refund(
				array(
					'amount'   => $refund_amount,
					'reason'   => $refund_reason,
					'order_id' => $order_id,
				)
			);

			$order->update_meta_data( 'amazon_refund_id', $refund_id );
			$order->save();
		}

		// Buyer canceled the order.
		if ( 'BuyerCanceled' === $refund_type ) {
			$order->update_status( 'cancelled', __( 'Order cancelled by customer via Amazon.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
	}

	/**
	 * Get the parsed notification data (from XML).
	 *
	 * @since 1.8.0
	 *
	 * @throws Exception Failed to parse the XML.
	 *
	 * @param array $notification Notification message.
	 *
	 * @return SimpleXMLElement Parsed XML object.
	 */
	protected function get_parsed_notification_data( $notification ) {
		// Chargeback notification has different notification message and it's
		// not XML, so return as it's.
		if ( 'ChargebackDetailedNotification' === $notification['NotificationType'] ) {
			return $notification['NotificationData'];
		}

		$data = WC_Amazon_Payments_Advanced_API_Legacy::safe_load_xml( $notification['NotificationData'], LIBXML_NOCDATA );
		if ( ! $data ) {
			throw new Exception( 'Failed to parse the XML in NotificationData.' );
		}

		return $data;
	}

	/**
	 * Get order from notification data.
	 *
	 * @since 1.8.0
	 *
	 * @throws Exception Failed to get order information from notification data.
	 *
	 * @param string           $notification_type Notification type.
	 * @param SimpleXMLElement $notification_data Notification data.
	 *
	 * @return WC_Order Order object.
	 */
	protected function get_order_from_notification_data( $notification_type, $notification_data ) {
		$order_id = null;

		// @codingStandardsIgnoreStart
		switch ( $notification_type ) {
			case 'OrderReferenceNotification':
				$order_id = (int) $notification_data->OrderReference->SellerOrderAttributes->SellerOrderId;
				break;
			case 'PaymentAuthorize':
				$auth_ref = (string) $notification_data->AuthorizationDetails->AuthorizationReferenceId;
				$auth_ref = explode( '-', $auth_ref );
				$order_id = $auth_ref[0];
				break;
			case 'PaymentCapture':
				$capture_ref = (string) $notification_data->CaptureDetails->CaptureReferenceId;
				$capture_ref = explode( '-', $capture_ref );
				$order_id = $capture_ref[0];
				break;
			case 'PaymentRefund':
				$refund_id    = (string) $notification_data->RefundDetails->AmazonRefundId;
				$refund_parts = explode( '-', $refund_id );
				unset( $refund_parts[3] );

				$order_ref = implode( '-', $refund_parts );
				$order_id  = WC_Amazon_Payments_Advanced_API_Legacy::get_order_id_from_reference_id( $order_ref );

				// When no order stores refund reference ID, checks RefundReferenceId.
				if ( ! $order_id ) {
					$refund_ref = (string) $notification_data->RefundDetails->RefundReferenceId;
					$refund_ref = explode( '-', $refund_ref );
					$order_id   = $refund_ref[0];
				}
				break;
		}
		// @codingStandardsIgnoreEnd

		if ( ! $order_id ) {
			throw new Exception( 'Could not found order information from notification data.' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Exception( 'Invalid order ID ' . $order_id );
		}

		return $order;
	}

	/**
	 * Process pending syncronuos payments.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  string $amazon_authorization_id Authorization ID.
	 * @return null|WP_Error WP_Error on error, null if processed
	 */
	public function process_pending_syncro_payments( $order_id, $amazon_authorization_id ) {

		wc_apa()->log( sprintf( 'Processing pending synchronous payment. Order: %s, Auth ID: %s', $order_id, $amazon_authorization_id ) );
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		try {
			$response = WC_Amazon_Payments_Advanced_API_Legacy::request(
				array(
					'Action'                => 'GetAuthorizationDetails',
					'AmazonAuthorizationId' => $amazon_authorization_id,
				)
			);
			if ( $order->get_meta( 'amazon_timed_out_transaction' ) ) {
				WC_Amazon_Payments_Advanced_API_Legacy::handle_synch_payment_authorization_payload( $response, $order, $amazon_authorization_id );
			}
		} catch ( Exception $e ) {
			/* translators: placeholder is error message from Amazon Pay API */
			$order->add_order_note( sprintf( __( 'Error: Unable to authorize funds with Amazon. Reason: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ) );
		}
	}

	/**
	 * Unschedule Action for Order.
	 *
	 * @param  WC_Order $order Order object.
	 */
	public function unschedule_pending_syncro_payments( $order ) {
		// Unschedule the Action for this order.
		$args = array(
			'order_id'                => $order->get_id(),
			'amazon_authorization_id' => $order->get_meta( 'amazon_authorization_id', true ),
		);
		as_unschedule_action( 'wcga_process_pending_syncro_payments', $args );
	}
}
