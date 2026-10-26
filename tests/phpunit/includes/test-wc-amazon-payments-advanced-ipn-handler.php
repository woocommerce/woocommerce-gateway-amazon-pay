<?php
/**
 * Test cases for the legacy IPN handler hardening.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * Legacy IPN handler without hook registration.
 */
class WC_Mocker_Amazon_Payments_Advanced_IPN_Handler_Legacy extends WC_Amazon_Payments_Advanced_IPN_Handler_Legacy {

	/**
	 * Skip hook registration.
	 */
	public function __construct() {}
}

/**
 * Tests the legacy handler re-reads the referenced object from Amazon before trusting a notification.
 */
class WC_Amazon_Payments_Advanced_IPN_Handler_Test extends WP_UnitTestCase {

	const MERCHANT_ID = 'AEMGQX8TKDO54';

	/**
	 * Canned Amazon API response body returned for the next request.
	 *
	 * @var string
	 */
	private $amazon_response_body = '';

	/**
	 * Configure the merchant, force the non-migrated state and stub the Amazon API transport.
	 *
	 * @return void
	 */
	public function set_up() : void {
		parent::set_up();

		update_option(
			'woocommerce_amazon_payments_advanced_settings',
			array(
				'merchant_id'    => self::MERCHANT_ID,
				'seller_id'      => self::MERCHANT_ID,
				'mws_access_key' => 'AKIATEST',
				'secret_key'     => 'secret',
				'sandbox'        => 'yes',
				'payment_region' => 'us',
			)
		);
		delete_option( 'woocommerce_amazon_payments_new_install' );

		add_filter( 'pre_http_request', array( $this, 'return_amazon_response' ), 10, 3 );
	}

	/**
	 * Remove the Amazon API transport stub.
	 *
	 * @return void
	 */
	public function tear_down() : void {
		remove_filter( 'pre_http_request', array( $this, 'return_amazon_response' ), 10 );

		parent::tear_down();
	}

	/**
	 * Short-circuit wp_remote_get with the canned Amazon API response.
	 *
	 * @param mixed  $pre  Preempt value.
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 *
	 * @return array
	 */
	public function return_amazon_response( $pre, $args, $url ) {
		return array(
			'body'     => $this->amazon_response_body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * Build a parsed v1 PaymentAuthorize notification.
	 *
	 * @param string $reference_id Attacker supplied AuthorizationReferenceId.
	 *
	 * @return array
	 */
	private function get_authorize_message( string $reference_id ) : array {
		return array(
			'Type'    => 'Notification',
			'Message' => array(
				'NotificationType' => 'PaymentAuthorize',
				'NotificationData' => '<AuthorizationNotification><AuthorizationDetails>'
					. '<AmazonAuthorizationId>S01-0000000-0000000-A000000</AmazonAuthorizationId>'
					. '<AuthorizationReferenceId>' . $reference_id . '</AuthorizationReferenceId>'
					. '<AuthorizationStatus><State>Open</State></AuthorizationStatus>'
					. '</AuthorizationDetails></AuthorizationNotification>',
			),
		);
	}

	/**
	 * Build a parsed v1 OrderReferenceNotification.
	 *
	 * @param int $seller_order_id Attacker supplied SellerOrderId.
	 *
	 * @return array
	 */
	private function get_order_reference_message( int $seller_order_id ) : array {
		return array(
			'Type'    => 'Notification',
			'Message' => array(
				'NotificationType' => 'OrderReferenceNotification',
				'NotificationData' => '<OrderReferenceNotification><OrderReference>'
					. '<AmazonOrderReferenceId>S01-FORGED</AmazonOrderReferenceId>'
					. '<SellerOrderAttributes><SellerOrderId>' . $seller_order_id . '</SellerOrderId></SellerOrderAttributes>'
					. '<OrderReferenceStatus><State>Open</State></OrderReferenceStatus>'
					. '</OrderReference></OrderReferenceNotification>',
			),
		);
	}

	/**
	 * Count the notes on an order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return int
	 */
	private function note_count( int $order_id ) : int {
		return count( wc_get_order_notes( array( 'order_id' => $order_id ) ) );
	}

	/**
	 * A forged authorization id Amazon cannot confirm is rejected.
	 *
	 * @return void
	 */
	public function test_unconfirmed_authorization_is_rejected() : void {
		$order = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$notes = $this->note_count( $order->get_id() );

		$this->amazon_response_body = '<?xml version="1.0"?><ErrorResponse><Error>'
			. '<Type>Sender</Type><Code>InvalidAuthorizationId</Code><Message>No such authorization.</Message>'
			. '</Error></ErrorResponse>';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Could not confirm the Amazon object' );

		try {
			( new WC_Mocker_Amazon_Payments_Advanced_IPN_Handler_Legacy() )->handle_notification_ipn_v1( $this->get_authorize_message( $order->get_id() . '-1' ) );
		} finally {
			$this->assertSame( $notes, $this->note_count( $order->get_id() ) );
		}
	}

	/**
	 * A forged order reference id Amazon cannot confirm is rejected.
	 *
	 * @return void
	 */
	public function test_unconfirmed_order_reference_is_rejected() : void {
		$order = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$notes = $this->note_count( $order->get_id() );

		$this->amazon_response_body = '<?xml version="1.0"?><ErrorResponse><Error>'
			. '<Type>Sender</Type><Code>InvalidOrderReferenceId</Code><Message>No such order reference.</Message>'
			. '</Error></ErrorResponse>';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Could not confirm the Amazon object' );

		try {
			( new WC_Mocker_Amazon_Payments_Advanced_IPN_Handler_Legacy() )->handle_notification_ipn_v1( $this->get_order_reference_message( $order->get_id() ) );
		} finally {
			$this->assertSame( $notes, $this->note_count( $order->get_id() ) );
		}
	}

	/**
	 * A confirmed authorization is processed, resolving the order from Amazon's response.
	 *
	 * @return void
	 */
	public function test_confirmed_authorization_is_processed() : void {
		$order = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$notes = $this->note_count( $order->get_id() );

		$this->amazon_response_body = '<?xml version="1.0"?><GetAuthorizationDetailsResponse><GetAuthorizationDetailsResult><AuthorizationDetails>'
			. '<AmazonAuthorizationId>S01-0000000-0000000-A000000</AmazonAuthorizationId>'
			. '<AuthorizationReferenceId>' . $order->get_id() . '-1</AuthorizationReferenceId>'
			. '<AuthorizationStatus><State>Declined</State><ReasonCode>AmazonRejected</ReasonCode></AuthorizationStatus>'
			. '</AuthorizationDetails></GetAuthorizationDetailsResult></GetAuthorizationDetailsResponse>';

		( new WC_Mocker_Amazon_Payments_Advanced_IPN_Handler_Legacy() )->handle_notification_ipn_v1( $this->get_authorize_message( '9999999-1' ) );

		$this->assertSame( $notes + 1, $this->note_count( $order->get_id() ) );
	}

	/**
	 * The order reference handler acts on Amazon's SellerOrderId, not the notification's.
	 *
	 * @return void
	 */
	public function test_order_reference_resolves_order_from_amazon() : void {
		$amazon_order   = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$spoofed_target = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$amazon_notes   = $this->note_count( $amazon_order->get_id() );
		$spoofed_notes  = $this->note_count( $spoofed_target->get_id() );

		$this->amazon_response_body = '<?xml version="1.0"?><GetOrderReferenceDetailsResponse><GetOrderReferenceDetailsResult><OrderReferenceDetails>'
			. '<AmazonOrderReferenceId>S01-REAL</AmazonOrderReferenceId>'
			. '<OrderReferenceStatus><State>Open</State></OrderReferenceStatus>'
			. '<SellerOrderAttributes><SellerOrderId>' . $amazon_order->get_id() . '</SellerOrderId></SellerOrderAttributes>'
			. '</OrderReferenceDetails></GetOrderReferenceDetailsResult></GetOrderReferenceDetailsResponse>';

		( new WC_Mocker_Amazon_Payments_Advanced_IPN_Handler_Legacy() )->handle_notification_ipn_v1( $this->get_order_reference_message( $spoofed_target->get_id() ) );

		$this->assertSame( $amazon_notes + 1, $this->note_count( $amazon_order->get_id() ), 'The note must land on the order Amazon confirmed.' );
		$this->assertSame( $spoofed_notes, $this->note_count( $spoofed_target->get_id() ), 'The spoofed target order must be untouched.' );
	}

	/**
	 * A forged v1 refund dispatched to every IPN handler never refunds or cancels an order.
	 *
	 * @return void
	 */
	public function test_forged_v1_refund_is_stopped_before_the_legacy_handler() : void {
		$order = WC_Helper_Order::create_order( 'bacs' );
		$order->set_status( 'processing' );
		$order->save();

		$client = new ReflectionProperty( WC_Amazon_Payments_Advanced_API::class, 'amazonpay_client' );
		$client->setAccessible( true );
		$original = $client->getValue();
		$client->setValue( null, new WC_Mocker_Amazon_Pay_Fake_Client() );
		set_error_handler( array( self::class, 'ignore_warning' ), E_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

		$aborted = false;
		try {
			do_action( 'woocommerce_amazon_payments_advanced_handle_ipn', $this->get_refund_message( $order->get_id() ) );
		} catch ( Exception $e ) {
			$aborted = true;
		} finally {
			restore_error_handler();
			$client->setValue( null, $original );
		}

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $aborted );
		$this->assertSame( 0.0, (float) $order->get_total_refunded() );
		$this->assertSame( 'processing', $order->get_status() );
	}

	/**
	 * Build a parsed v1 PaymentRefund notification aimed at an order.
	 *
	 * @param int $order_id Targeted order ID.
	 *
	 * @return array
	 */
	private function get_refund_message( int $order_id ) : array {
		return array(
			'Type'    => 'Notification',
			'Message' => array(
				'NotificationType' => 'PaymentRefund',
				'NotificationData' => '<RefundNotification><RefundDetails>'
					. '<AmazonRefundId>S01-0000000-0000000-R000000</AmazonRefundId>'
					. '<RefundReferenceId>' . $order_id . '-1</RefundReferenceId>'
					. '<RefundType>BuyerCanceled</RefundType>'
					. '<RefundAmount><Amount>10.00</Amount><CurrencyCode>USD</CurrencyCode></RefundAmount>'
					. '<RefundStatus><State>Completed</State></RefundStatus>'
					. '<SellerRefundNote>Forged</SellerRefundNote>'
					. '</RefundDetails></RefundNotification>',
			),
		);
	}

	/**
	 * Swallow a PHP warning.
	 *
	 * @return bool
	 */
	public static function ignore_warning() : bool {
		return true;
	}
}
