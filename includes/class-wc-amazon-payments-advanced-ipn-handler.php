<?php
/**
 * Amazon Pay IPN handler.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay IPN handler.
 *
 * The validation logic of SNS message is borrowed from aws-php-sns-message-validator.
 *
 * @see https://github.com/aws/aws-php-sns-message-validator
 * @see https://pay.amazon.com/us/developer/documentation/lpwa/201985720
 *
 * @since 1.8.0
 */
class WC_Amazon_Payments_Advanced_IPN_Handler {

	/**
	 * This constant tells you the signature version that's passed as `SignatureVersion`.
	 * For Amazon SNS notifications, Amazon SNS currently supports signature version 1.
	 *
	 * @see http://docs.aws.amazon.com/sns/latest/dg/SendMessageToHttp.verify.signature.html.
	 */
	const SIGNATURE_VERSION_1 = '1';

	/**
	 * A pattern that will match all regional SNS endpoints.
	 *
	 * For example:
	 *
	 * - sns.<region>.amazonaws.com        (AWS)
	 * - sns.us-gov-west-1.amazonaws.com   (AWS GovCloud)
	 * - sns.cn-north-1.amazonaws.com.cn   (AWS China)
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @var string
	 */
	protected $host_pattern = '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/';

	/**
	 * Required data keys to present in Simple Notification Service (SNS).
	 *
	 * These keys are used to validate incoming SNS data in IPN request. If
	 * one of the key is not present then validation failed. If one of the key
	 * is array, it means at least one of the element in the array must be
	 * present.
	 *
	 * @see self::get_message_from_raw_post_data()
	 * @see self::validate_required_keys()
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @var array
	 */
	protected $required_data_keys = array(
		'Message',
		'MessageId',
		'Timestamp',
		'TopicArn',
		'Type',
		'Signature',
		array( 'SigningCertURL', 'SigningCertUrl' ),
		'SignatureVersion',
	);

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
	protected $required_subscription_keys = array(
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
	protected $required_notification_message_keys = array(
		'NotificationType',
		'NotificationData',
		'NotificationReferenceId',
		'SellerId',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 */
	public function __construct() {
		// Handles notification request from Amazon.
		add_action( 'woocommerce_api_wc_gateway_amazon_payments_advanced', array( $this, 'check_ipn_request' ) );

		// Handle valid IPN message.
		add_action( 'woocommerce_amazon_payments_advanced_handle_ipn', array( $this, 'handle_ipn' ) );
	}

	/**
	 * Get notify URL.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return string Notify URL.
	 */
	public function get_notify_url() {
		return WC()->api_request_url( 'WC_Gateway_Amazon_Payments_Advanced' );
	}

	/**
	 * Retrieves the raw request data (body).
	 *
	 * `$HTTP_RAW_POST_DATA` is deprecated in PHP 5.6 and removed in PHP 5.7,
	 * it's used here for server that has issue with reading `php://input`
	 * stream.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return string Raw request data.
	 */
	protected function get_raw_post_data() {
		global $HTTP_RAW_POST_DATA;

		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	/**
	 * Validates given SNS message from post data.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Invalid message.
	 *
	 * @param array|null $message SNS message.
	 */
	protected function validate_message( $message ) {
		if ( ! function_exists( 'openssl_get_publickey' ) || ! function_exists( 'openssl_verify' ) ) {
			throw new Exception( 'OpenSSL extension is not available in your server.' );
		}

		if ( $this->is_lambda_style( $message ) ) {
			$message = $this->normalize_lambda_message( $message );
		}

		// Get the certificate.
		$this->validate_certificate_url( $message['SigningCertURL'] );
		$certificate = file_get_contents( $message['SigningCertURL'] );

		// Extract the public key.
		$key = openssl_get_publickey( $certificate );
		if ( ! $key ) {
			throw new Exception( 'Cannot get the public key from the certificate.' );
		}

		// Verify the signature of the message.
		$content   = $this->get_string_to_sign( $message );
		$signature = base64_decode( $message['Signature'] );

		if ( 1 != openssl_verify( $content, $signature, $key, OPENSSL_ALGO_SHA1 ) ) {
			throw new Exception( 'The message signature is invalid.' );
		}
	}

	/**
	 * Checks if a given message in lambda style.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param array|null $message SNS message.
	 *
	 * @return bool Returns true if message in lambda style.
	 */
	protected function is_lambda_style( $message ) {
		return isset( $message['SigningCertUrl'] );
	}

	/**
	 * Normalize lambda message.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param array $message SNS message.
	 *
	 * @return array SNS message.
	 */
	protected function normalize_lambda_message( $message ) {
		$key_replacements = array(
			'SigningCertUrl' => 'SigningCertURL',
			'SubscribeUrl'   => 'SubscribeURL',
			'UnsubscribeUrl' => 'UnsubscribeURL',
		);

		foreach ( $key_replacements as $lambda_key => $canonical_key ) {
			if ( isset( $message[ $lambda_key ] ) ) {
				$message[ $canonical_key ] = $message[ $lambda_key ];
				unset( $message[ $lambda_key ] );
			}
		}

		return $message;
	}

	/**
	 * Validate certificate URL.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Invalid certificate URL.
	 *
	 * @param string $url Cert URL.
	 */
	protected function validate_certificate_url( $url ) {
		$parsed_url = parse_url( $url );
		if ( empty( $parsed_url['scheme'] )
			|| empty( $parsed_url['host'] )
			|| 'https' !== $parsed_url['scheme']
			|| '.pem' !== substr( $url, -4 )
			|| ! preg_match( $this->host_pattern, $parsed_url['host'] ) ) {
			throw new Exception( 'Invalid certificate URL.' );
		}
	}

	/**
	 * Builds string-to-sign according to the SNS message spec.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Unsupported signature version.
	 *
	 * @param array $message SNS message.
	 *
	 * @return string String to sign.
	 */
	protected function get_string_to_sign( $message ) {
		$signable_keys = array(
			'Message',
			'MessageId',
			'Subject',
			'SubscribeURL',
			'Timestamp',
			'Token',
			'TopicArn',
			'Type',
		);

		if ( self::SIGNATURE_VERSION_1 !== $message['SignatureVersion'] ) {
			throw new Exception( 'The SignatureVersion ' . $message['SignatureVersion'] . ' is not supported.' );
		}

		$string_to_sign = '';
		foreach ( $signable_keys as $key ) {
			if ( isset( $message[ $key ] ) ) {
				$string_to_sign .= "{$key}\n{$message[ $key ]}\n";
			}
		}

		return $string_to_sign;
	}

	/**
	 * Get message array from string of raw post data.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Missing handler.
	 *
	 * @param string $raw_post_data Raw post data.
	 *
	 * @return array Message.
	 */
	protected function get_message_from_raw_post_data( $raw_post_data ) {
		$message = $this->decode_raw_post_data( $raw_post_data );

		$this->validate_required_keys( $message, $this->required_data_keys );

		switch ( $message['Type'] ) {
			case 'Notification':
				$notification_message = $this->decode_raw_post_data( $message['Message'] );
				$this->validate_required_keys( $notification_message, $this->required_notification_message_keys );
				break;
			case 'SubscriptionConfirmation':
			case 'UnsubscribeConfirmation':
				$this->validate_required_keys( $message, $this->required_subscription_keys );
				break;
			default:
				throw new Exception( 'No handler for message type ' . $message['Type'] );
		}

		return $message;
	}

	/**
	 * Decode raw post data.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Failed to decode the message.
	 *
	 * @param string $raw_post_data Raw post data.
	 *
	 * @return array Message.
	 */
	protected function decode_raw_post_data( $raw_post_data ) {
		$message    = json_decode( $raw_post_data, true );
		$json_error = json_last_error();
		if ( JSON_ERROR_NONE !== $json_error || ! is_array( $message ) ) {
			throw new Exception( 'Invalid POST data. Failed to decode the message: ' . $this->get_json_error_message( $json_error ) );
		}

		return $message;
	}

	/**
	 * Get nice JSON error message from a given JSON error flag.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @param int $json_error JSON error flag.
	 *
	 * @return string Error message.
	 */
	protected function get_json_error_message( $json_error ) {
		$message = 'Unknown error.';
		switch ( $json_error ) {
			case JSON_ERROR_DEPTH:
				$message = 'Maximum stack depth exceeded.';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$message = 'Underflow or the modes mismatch.';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$message = 'Unexpected control character found.';
				break;
			case JSON_ERROR_SYNTAX:
				$message = 'Syntax error, malformed JSON.';
				break;
			case JSON_ERROR_UTF8:
				$message = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
				break;
		}

		return $message;
	}

	/**
	 * Validate required keys that need to be present in message.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Missing required key in the message.
	 *
	 * @param array $message Message to check.
	 * @param array $keys    Required keys to be present.
	 */
	protected function validate_required_keys( $message, $keys ) {
		foreach ( $keys as $key ) {
			$key_has_options = is_array( $key );
			if ( ! $key_has_options ) {
				$found = isset( $message[ $key ] );
			} else {
				$found = false;
				foreach ( $key as $option ) {
					if ( isset( $message[ $option ] ) ) {
						$found = true;
						break;
					}
				}
			}

			if ( ! $found ) {
				if ( $key_has_options ) {
					$key = $key[0];
				}

				throw new Exception( $key . ' is required to verify the SNS message.' );
			}
		}
	}

	/**
	 * Check incoming IPN request.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 */
	public function check_ipn_request() {
		$raw_post_data = $this->get_raw_post_data();

		wc_apa()->log( __METHOD__, 'Received IPN request.' );

		try {
			if ( empty( $raw_post_data ) ) {
				throw new Exception( 'Empty post data.' );
			}

			$message = $this->get_message_from_raw_post_data( $raw_post_data );

			$this->validate_message( $message );

			wc_apa()->log( __METHOD__, sprintf( 'Valid IPN message %s.', $message['MessageId'] ) );

			header( 'HTTP/1.1 200 OK' );

			do_action( 'woocommerce_amazon_payments_advanced_handle_ipn', $message );
			exit;
		} catch ( Exception $e ) {
			wc_apa()->log( __METHOD__, 'Failed to handle IPN request: ' . $e->getMessage() );
			wp_die(
				$e->getMessage(),
				'Bad request',
				array(
					'response' => 400, // Send 40x to tell 'no retriees'.
				)
			);
		}
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
	public function handle_ipn( $message ) {
		// Ignore non-notification type message.
		if ( 'Notification' !== $message['Type'] ) {
			return;
		}

		$notification      = $this->decode_raw_post_data( $message['Message'] );
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
	 * @param WC_Order         $order             Order object.
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
			WC_Amazon_Payments_Advanced_API::handle_async_ipn_order_reference_payload( $notification_data, $order );
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
	 * @param WC_Order         $order             Order object.
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
			WC_Amazon_Payments_Advanced_API::handle_async_ipn_payment_authorization_payload( $notification_data, $order );
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
	 * @param WC_Order         $order             Order object.
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
	 * @param WC_Order         $order             Order object.
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

		$order->add_order_note( sprintf(
			/* translators: 1) Amazon refund ID 2) refund status 3) refund amount */
			__( 'Received IPN for payment refund %1$s with status %2$s. Refund amount: %3$s.', 'woocommerce-gateway-amazon-payments-advanced' ),
			$refund_id,
			$refund_status,
			wc_price( $refund_amount )
		) );

		if ( 'refunded' === $order->get_status() ) {
			return;
		}

		$order_id = wc_apa_get_order_prop( $order, 'id' );
		if ( $order->get_total() == $refund_amount ) {
			wc_order_fully_refunded( $order_id );
		} else {
			wc_create_refund( array(
				'amount'   => $refund_amount,
				'reason'   => $refund_reason,
				'order_id' => $order_id,
			) );
		}
		add_post_meta( $order_id, 'amazon_refund_id', $refund_id );

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

		$data = WC_Amazon_Payments_Advanced_API::safe_load_xml( $notification['NotificationData'], LIBXML_NOCDATA );
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
		$order_id  = null;

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
				$order_id  = WC_Amazon_Payments_Advanced_API::get_order_id_from_reference_id( $order_ref );

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
}
