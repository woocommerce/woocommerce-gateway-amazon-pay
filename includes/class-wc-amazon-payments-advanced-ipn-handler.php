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
class WC_Amazon_Payments_Advanced_IPN_Handler extends WC_Amazon_Payments_Advanced_IPN_Handler_Abstract {

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
	 * Constructor.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 */
	public function __construct() {
		// Handles notification request from Amazon.
		add_action( 'woocommerce_api_wc_gateway_amazon_payments_advanced', array( $this, 'check_ipn_request' ) );

		// Handle valid IPN message.
		add_action( 'woocommerce_amazon_payments_advanced_handle_ipn', array( $this, 'handle_notification_ipn_v2' ) );

		// Do async polling action (as a fallback).
		add_action( 'wc_amazon_async_polling', array( $this, 'handle_async_polling' ), 10, 2 );
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
		// phpcs:disable PHPCompatibility.Variables.RemovedPredefinedGlobalVariables.http_raw_post_dataDeprecatedRemoved
		global $HTTP_RAW_POST_DATA;

		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $HTTP_RAW_POST_DATA;
		// phpcs:enable PHPCompatibility.Variables.RemovedPredefinedGlobalVariables.http_raw_post_dataDeprecatedRemoved
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
		$certificate = file_get_contents( $message['SigningCertURL'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// Extract the public key.
		$key = openssl_get_publickey( $certificate );
		if ( ! $key ) {
			throw new Exception( 'Cannot get the public key from the certificate.' );
		}

		// Verify the signature of the message.
		$content   = $this->get_string_to_sign( $message );
		$signature = base64_decode( $message['Signature'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( 1 !== openssl_verify( $content, $signature, $key, OPENSSL_ALGO_SHA1 ) ) {
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
		$parsed_url = wp_parse_url( $url );
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

		$this->validate_message( $message );

		switch ( $message['Type'] ) {
			case 'Notification':
				$message['Message']   = $this->decode_raw_post_data( $message['Message'] );
				$notification_version = isset( $message['Message']['NotificationVersion'] ) ? strtolower( $message['Message']['NotificationVersion'] ) : 'v1';
				do_action( 'woocommerce_amazon_payments_advanced_ipn_validate_notification_keys', $message, $notification_version );
				break;
			case 'SubscriptionConfirmation':
			case 'UnsubscribeConfirmation':
				do_action( 'woocommerce_amazon_payments_advanced_ipn_validate_subscription_keys', $message );
				break;
			default:
				throw new Exception( 'No handler for message type ' . $message['Type'] );
		}

		wc_apa()->log( sprintf( 'Valid IPN message %s.', $message['MessageId'] ) );

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
	 * Check incoming IPN request.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 * @throws Exception On Errors.
	 */
	public function check_ipn_request() {
		$raw_post_data = $this->get_raw_post_data();

		wc_apa()->log( 'Received IPN request.' );

		try {
			if ( empty( $raw_post_data ) ) {
				throw new Exception( 'Empty post data.' );
			}

			$message = $this->get_message_from_raw_post_data( $raw_post_data );

			status_header( 200 );

			do_action( 'woocommerce_amazon_payments_advanced_handle_ipn', $message );
			exit;
		} catch ( Exception $e ) {
			wc_apa()->log( 'Failed to handle IPN request: ' . $e->getMessage() );
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
	public function handle_notification_ipn_v2( $message ) {
		// Ignore non-notification type message.
		if ( 'Notification' !== $message['Type'] ) {
			return;
		}

		$notification_version = isset( $message['Message']['NotificationVersion'] ) ? strtolower( $message['Message']['NotificationVersion'] ) : 'v1';

		if ( 'v2' !== $notification_version ) {
			$notification_data = self::safe_load_xml( $message['Message']['NotificationData'], LIBXML_NOCDATA );

			switch ( $message['Message']['NotificationType'] ) {
				case 'PaymentCapture':
					$type      = 'CHARGE';
					$charge_id = (string) $notification_data->CaptureDetails->AmazonCaptureId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
					break;
				case 'PaymentRefund':
					$type      = 'REFUND';
					$charge_id = (string) $notification_data->RefundDetails->AmazonRefundId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
					break;
				default:
					wc_apa()->log( 'No handler for notification with type ' . $message['Message']['NotificationType'], $notification_data );
					return;
			}

			$notification = array(
				'NotificationVersion' => 'v1',
				'NotificationType'    => 'STATE_CHANGE',
				'ObjectType'          => $type,
				'ObjectId'            => $charge_id,
			);
			wc_apa()->log( 'Parsed IPN Notification from V1 to V2', $notification_data );
		} else {
			$notification = $message['Message'];
		}

		if ( ! isset( $notification['MockedIPN'] ) ) { // Only log real IPNs received.
			wc_apa()->log( 'Received IPN', $notification );
		}

		switch ( strtoupper( $notification['ObjectType'] ) ) {
			case 'CHARGE':
				$object   = WC_Amazon_Payments_Advanced_API::get_charge( $notification['ObjectId'] );
				$order_id = $object->merchantMetadata->merchantReferenceId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				break;
			case 'CHARGE_PERMISSION':
				$object   = WC_Amazon_Payments_Advanced_API::get_charge_permission( $notification['ObjectId'] );
				$order_id = $object->merchantMetadata->merchantReferenceId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				break;
			case 'REFUND':
				// on refunds, order_id can be fetched from the charge.
				$object   = WC_Amazon_Payments_Advanced_API::get_refund( $notification['ObjectId'] );
				$charge   = WC_Amazon_Payments_Advanced_API::get_charge( $object->chargeId ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$order_id = $charge->merchantMetadata->merchantReferenceId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				break;
			default:
				throw new Exception( 'Not Implemented' );
		}

		$order_id = apply_filters( 'woocommerce_amazon_pa_merchant_metadata_reference_id_reverse', $order_id );

		if ( is_numeric( $order_id ) ) {
			$order = wc_get_order( $order_id );
		} else {
			throw new Exception( 'Invalid order ID ' . $order_id );
		}

		$order    = apply_filters( 'woocommerce_amazon_pa_ipn_notification_order', $order, $notification );
		$order_id = $order->get_id(); // Refresh variable, in case it changed.

		if ( 'amazon_payments_advanced' !== $order->get_payment_method() ) {
			throw new Exception( 'Order ID ' . $order_id . ' is not paid with Amazon' );
		}

		if ( 'STATE_CHANGE' !== strtoupper( $notification['NotificationType'] ) ) {
			/* translators: 1) Notification Type. */
			throw new Exception( sprintf( __( 'Notification type "%s" not supported', 'woocommerce-gateway-amazon-payments-advanced' ), $notification['NotificationType'] ) );
		}

		if ( ! wc_apa()->get_gateway()->get_lock_for_order( $order_id ) ) {
			if ( ! isset( $notification['MockedIPN'] ) ) {
				wc_apa()->log( sprintf( 'Refusing IPN due to concurrency on order #%d', $order_id ) );
				status_header( 100 );
				header( 'Retry-After: 120' );
				exit;
			} else {
				wc_apa()->log( sprintf( 'Delaying for concurrency on order #%d', $order_id ) );
				$this->schedule_hook( $notification['ObjectId'], $notification['ObjectType'] );
			}
		}

		if ( ! isset( $notification['MockedIPN'] ) ) {
			$this->unschedule_hook( $notification['ObjectId'], $notification['ObjectType'] ); // Unshchedule just in case we're actually on real IPN, we'll schedule again if needed.
		}

		switch ( strtoupper( $notification['ObjectType'] ) ) {
			case 'CHARGE':
				wc_apa()->get_gateway()->log_charge_status_change( $order, $object );
				break;
			case 'CHARGE_PERMISSION':
				wc_apa()->get_gateway()->log_charge_permission_status_change( $order, $object );
				break;
			case 'REFUND':
				wc_apa()->get_gateway()->handle_refund( $order, $object );
				break;
			default:
				wc_apa()->get_gateway()->release_lock_for_order( $order_id );
				throw new Exception( 'Not Implemented' );
		}

		wc_apa()->get_gateway()->release_lock_for_order( $order_id );
	}

	/**
	 * Check if the next hook is scheduled.
	 *
	 * @param  string $hook Hook to check.
	 * @param  array  $args Args to check.
	 * @param  string $group Group to check for.
	 * @return bool
	 */
	private function is_next_scheduled( $hook, $args = null, $group = '' ) {
		$actions = as_get_scheduled_actions(
			array(
				'hook'   => $hook,
				'args'   => $args,
				'group'  => $group,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			),
			'ids'
		);
		return count( $actions ) > 0;
	}

	/**
	 * Schedule the hook for polling.
	 *
	 * @param  string $id Object ID to check for.
	 * @param  string $type Object Type.
	 */
	public function schedule_hook( $id, $type ) {
		$args = array( $id, $type );
		// Schedule action to check pending order next hour.
		if ( false === $this->is_next_scheduled( 'wc_amazon_async_polling', $args, 'wc_amazon_async_polling' ) ) {
			wc_apa()->log( sprintf( 'Scheduling check for %s %s', $type, $id ) );
			as_schedule_single_action( strtotime( '+10 minutes' ), 'wc_amazon_async_polling', $args, 'wc_amazon_async_polling' );
		}
	}

	/**
	 * Unschedule the hook for polling.
	 *
	 * @param  string $id Object ID to check for.
	 * @param  string $type Object Type.
	 */
	public function unschedule_hook( $id, $type ) {
		$args = array( $id, $type );
		wc_apa()->log( sprintf( 'Unscheduling check for %s %s', $type, $id ) );
		as_unschedule_all_actions( 'wc_amazon_async_polling', $args, 'wc_amazon_async_polling' );
	}

	/**
	 * Simulate an IPN request when polling
	 *
	 * @param  string $amazon_id Object ID to check for.
	 * @param  string $type Object Type.
	 */
	public function handle_async_polling( $amazon_id, $type ) {
		switch ( strtoupper( $type ) ) {
			case 'CHARGE':
				if ( empty( $amazon_id ) ) {
					// TODO: Not possible to poll for charge_id only with charge permission id (eg: collect payment from seller central)
					// TIP: Suggested by Federico, use the charge_permission amounts change to infer a charge being made.
					return;
				}
				$object               = WC_Amazon_Payments_Advanced_API::get_charge( $amazon_id );
				$charge_permission_id = $object->chargePermissionId; // phpcs:ignore WordPress.NamingConventions
				break;
			case 'CHARGE_PERMISSION':
				$charge_permission_id = $amazon_id;
				break;
			default:
				return;
		}

		$mock_ipn = array(
			'Type'    => 'Notification',
			'Message' => array(
				'NotificationVersion' => 'V2',
				'ChargePermissionId'  => $charge_permission_id,
				'NotificationType'    => 'STATE_CHANGE',
				'ObjectType'          => $type,
				'ObjectId'            => $amazon_id,
				'MockedIPN'           => true,
			),
		);

		$this->handle_notification_ipn_v2( $mock_ipn );
	}

	/**
	 * Safe load XML.
	 *
	 * @param  string $source  XML input.
	 * @param  int    $options Options.
	 *
	 * @return SimpleXMLElement|bool
	 */
	public static function safe_load_xml( $source, $options = 0 ) {
		$old = null;

		if ( '<' !== substr( $source, 0, 1 ) ) {
			return false;
		}

		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			$old = libxml_disable_entity_loader( true );
		}

		$dom    = new DOMDocument();
		$return = $dom->loadXML( $source, $options );

		if ( ! is_null( $old ) ) {
			libxml_disable_entity_loader( $old );
		}

		if ( ! $return ) {
			return false;
		}

		if ( isset( $dom->doctype ) ) {
			return false;
		}

		return simplexml_import_dom( $dom );
	}
}
