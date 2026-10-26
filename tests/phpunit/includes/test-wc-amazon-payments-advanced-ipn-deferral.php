<?php
/**
 * Test cases for deferring notifications in WC_Amazon_Payments_Advanced_IPN_Handler.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * WC_Amazon_Payments_Advanced_IPN_Deferral_Test tests notifications that meet a locked order.
 */
class WC_Amazon_Payments_Advanced_IPN_Deferral_Test extends WP_UnitTestCase {

	/**
	 * Polling hook name.
	 *
	 * @var string
	 */
	const HOOK = 'wc_amazon_async_polling';

	/**
	 * Fake SDK client.
	 *
	 * @var WC_Mocker_Amazon_Pay_Fake_Client
	 */
	protected $client;

	/**
	 * Gateway active before the test.
	 *
	 * @var object
	 */
	protected $original_gateway;

	/**
	 * SDK client cached before the test.
	 *
	 * @var mixed
	 */
	protected $original_client;

	/**
	 * Use the V2 gateway and the fake SDK client.
	 *
	 * @return void
	 */
	public function set_up() : void {
		parent::set_up();
		$gateway                = $this->property( get_class( wc_apa() ), 'gateway' );
		$this->original_gateway = $gateway->getValue( wc_apa() );
		$gateway->setValue( wc_apa(), new WC_Gateway_Amazon_Payments_Advanced() );

		$client                = $this->property( 'WC_Amazon_Payments_Advanced_API', 'amazonpay_client' );
		$this->original_client = $client->getValue();
		$this->client          = new WC_Mocker_Amazon_Pay_Fake_Client();
		$client->setValue( null, $this->client );
	}

	/**
	 * Restore the gateway and the SDK client.
	 *
	 * @return void
	 */
	public function tear_down() : void {
		$this->property( get_class( wc_apa() ), 'gateway' )->setValue( wc_apa(), $this->original_gateway );
		$this->property( 'WC_Amazon_Payments_Advanced_API', 'amazonpay_client' )->setValue( null, $this->original_client );
		parent::tear_down();
	}

	/**
	 * Get an accessible reflection property.
	 *
	 * @param string $class_name Class name.
	 * @param string $name       Property name.
	 *
	 * @return ReflectionProperty
	 */
	protected function property( $class_name, $name ) {
		$property = new ReflectionProperty( $class_name, $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		return $property;
	}

	/**
	 * Create an Amazon Pay order with a fixture charge.
	 *
	 * @param string $state Charge state.
	 *
	 * @return array Order and charge id.
	 */
	protected function make_order_with_charge( $state ) {
		$order     = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$charge_id = 'P01-DEF-' . $order->get_id() . '-C1';
		$order->update_meta_data( 'amazon_charge_id', $charge_id );
		$order->save();
		$this->client->objects[ $charge_id ] = array(
			'chargeId'            => $charge_id,
			'chargePermissionId'  => 'P01-DEF-' . $order->get_id(),
			'creationTimestamp'   => gmdate( 'Ymd\THis\Z' ),
			'expirationTimestamp' => gmdate( 'Ymd\THis\Z', time() + 30 * DAY_IN_SECONDS ),
			'merchantMetadata'    => array( 'merchantReferenceId' => (string) $order->get_id() ),
			'statusDetails'       => array(
				'state'   => $state,
				'reasons' => array(),
			),
		);
		return array( $order, $charge_id );
	}

	/**
	 * Hold the processing lock for an order.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return void
	 */
	protected function lock( $order ) {
		set_transient( 'amazon_processing_order_' . $order->get_id(), 'yes', 2 * MINUTE_IN_SECONDS );
	}

	/**
	 * Build a real v2 notification.
	 *
	 * @param string $id   Object id.
	 * @param string $type Object type.
	 *
	 * @return array
	 */
	protected function notification( $id, $type ) {
		return array(
			'Type'    => 'Notification',
			'Message' => array(
				'NotificationVersion' => 'V2',
				'ChargePermissionId'  => 'P01-DEF',
				'NotificationType'    => 'STATE_CHANGE',
				'ObjectType'          => $type,
				'ObjectId'            => $id,
			),
		);
	}

	/**
	 * Get the earliest pending check time for an object, or 0 for none.
	 *
	 * @param string $id   Object id.
	 * @param string $type Object type.
	 *
	 * @return int
	 */
	protected function next_check( $id, $type ) {
		$actions = as_get_scheduled_actions(
			array(
				'hook'     => self::HOOK,
				'args'     => array( $id, $type ),
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => 1,
				'orderby'  => 'date',
				'order'    => 'ASC',
			)
		);
		return $actions ? reset( $actions )->get_schedule()->get_date()->getTimestamp() : 0;
	}

	/**
	 * A scheduled check that meets a locked order is deferred, not processed.
	 *
	 * @return void
	 */
	public function test_scheduled_check_deferred_when_order_locked() : void {
		list( $order, $charge_id ) = $this->make_order_with_charge( 'Captured' );
		$this->lock( $order );
		wc_apa()->ipn_handler->handle_async_polling( $charge_id, 'CHARGE' );
		$this->assertEqualsWithDelta( time() + MINUTE_IN_SECONDS, $this->next_check( $charge_id, 'CHARGE' ), 30 );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( 'amazon_charge_status' ) );
	}

	/**
	 * A real notification that meets a locked order is deferred and returns.
	 *
	 * @return void
	 */
	public function test_real_ipn_deferred_when_order_locked() : void {
		list( $order, $charge_id ) = $this->make_order_with_charge( 'Captured' );
		$this->lock( $order );
		wc_apa()->ipn_handler->handle_notification_ipn_v2( $this->notification( $charge_id, 'CHARGE' ) );
		$this->assertEqualsWithDelta( time() + MINUTE_IN_SECONDS, $this->next_check( $charge_id, 'CHARGE' ), 30 );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( 'amazon_charge_status' ) );
	}

	/**
	 * A refund check that meets a locked order is deferred.
	 *
	 * @return void
	 */
	public function test_refund_check_deferred_when_order_locked() : void {
		list( $order, $charge_id )           = $this->make_order_with_charge( 'Captured' );
		$refund_id                           = $charge_id . '-R1';
		$this->client->objects[ $refund_id ] = array(
			'refundId'      => $refund_id,
			'chargeId'      => $charge_id,
			'statusDetails' => array(
				'state'   => 'Refunded',
				'reasons' => array(),
			),
		);
		$this->lock( $order );
		wc_apa()->ipn_handler->handle_async_polling( $refund_id, 'REFUND' );
		$this->assertEqualsWithDelta( time() + MINUTE_IN_SECONDS, $this->next_check( $refund_id, 'REFUND' ), 30 );
	}

	/**
	 * A charge check whose read fails returns without an exception.
	 *
	 * @return void
	 */
	public function test_charge_check_read_error_returns_quietly() : void {
		wc_apa()->ipn_handler->handle_async_polling( 'P01-DEF-MISSING-C1', 'CHARGE' );
		$this->assertSame( 0, $this->next_check( 'P01-DEF-MISSING-C1', 'CHARGE' ) );
	}

	/**
	 * An unlocked check processes the charge and releases the lock.
	 *
	 * @return void
	 */
	public function test_unlocked_check_processes_and_releases_lock() : void {
		list( $order, $charge_id ) = $this->make_order_with_charge( 'Captured' );
		wc_apa()->ipn_handler->handle_async_polling( $charge_id, 'CHARGE' );
		$this->assertSame( 'Captured', wc_apa()->get_gateway()->get_cached_charge_status( wc_get_order( $order->get_id() ), true )->status );
		$this->assertFalse( get_transient( 'amazon_processing_order_' . $order->get_id() ) );
	}
}
