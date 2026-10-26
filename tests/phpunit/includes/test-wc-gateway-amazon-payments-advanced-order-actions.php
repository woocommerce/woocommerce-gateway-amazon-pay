<?php
/**
 * Test cases for the manual Amazon Pay order actions.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * Covers reconciliation of the mutating order actions against the live Amazon state.
 */
class WC_Gateway_Amazon_Payments_Advanced_Order_Actions_Test extends WP_UnitTestCase {

	const CHARGE_ID            = 'TEST_CHARGE_PERMISSION_ID-C001';
	const CHARGE_PERMISSION_ID = 'TEST_CHARGE_PERMISSION_ID';

	/**
	 * Gateway under test.
	 *
	 * @var ?WC_Gateway_Amazon_Payments_Advanced
	 */
	protected static $gateway;

	/**
	 * Gateway the plugin held before the tests.
	 *
	 * @var mixed
	 */
	protected static $original_gateway;

	/**
	 * Installed SDK client mocker.
	 *
	 * @var ?WC_Mocker_Amazon_Pay_Sdk_Client
	 */
	protected $client;

	/**
	 * Set up the plugin's expectations about the install.
	 *
	 * @return void
	 */
	public static function set_up_before_class() : void {
		parent::set_up_before_class();
		self::$gateway                              = new WC_Gateway_Amazon_Payments_Advanced();
		self::$gateway->settings['payment_capture'] = '';
		self::$original_gateway                     = self::swap_plugin_gateway( self::$gateway );
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_amazon_payments_new_install', WC_AMAZON_PAY_VERSION );
		update_option( 'amazon_api_version', 'V2' );
	}

	/**
	 * Restore the DB's state after tests.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() : void {
		self::swap_plugin_gateway( self::$original_gateway );
		self::$gateway          = null;
		self::$original_gateway = null;
		delete_option( 'woocommerce_currency' );
		delete_option( 'woocommerce_amazon_payments_new_install' );
		delete_option( 'amazon_api_version' );
		parent::tear_down_after_class();
	}

	/**
	 * Install the SDK client mocker.
	 *
	 * @return void
	 */
	public function set_up() : void {
		parent::set_up();
		$this->client = WC_Mocker_Amazon_Pay_Sdk_Client::install();
	}

	/**
	 * Remove the SDK client mocker.
	 *
	 * @return void
	 */
	public function tear_down() : void {
		WC_Mocker_Amazon_Pay_Sdk_Client::uninstall();
		$this->client = null;
		parent::tear_down();
	}

	/**
	 * Capture on a charge Amazon already captured completes the order without capturing again.
	 *
	 * @return void
	 */
	public function test_capture_reconciles_when_amazon_already_captured() : void {
		$order = $this->create_authorized_order();

		$this->client->charge_state    = 'Captured';
		$this->client->mutation_status = 422;

		$result = self::$gateway->perform_capture( $order, self::CHARGE_ID );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 0, $this->client->call_count( 'captureCharge' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->is_paid() );
		$this->assertSame( 'Captured', $this->cached_charge_state( $order ) );
		$this->assertTrue( $this->has_note( $order, 'was already Captured on Amazon' ) );
	}

	/**
	 * Close Authorization on a charge Amazon already captured syncs instead of erroring.
	 *
	 * @return void
	 */
	public function test_cancel_auth_reconciles_when_amazon_already_captured() : void {
		$order = $this->create_authorized_order();

		$this->client->charge_state    = 'Captured';
		$this->client->mutation_status = 422;

		$result = self::$gateway->perform_cancel_auth( $order, self::CHARGE_ID );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 0, $this->client->call_count( 'cancelCharge' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->is_paid() );
		$this->assertSame( 'Captured', $this->cached_charge_state( $order ) );
		$this->assertTrue( $this->has_note( $order, 'was already Captured on Amazon' ) );
	}

	/**
	 * A genuine capture failure still returns a WP_Error and adds no reconcile note.
	 *
	 * @return void
	 */
	public function test_capture_still_errors_on_genuine_failure() : void {
		$order = $this->create_authorized_order();

		$this->client->charge_state    = 'Authorized';
		$this->client->mutation_status = 503;
		$this->client->mutation_reason = 'InternalServerError';

		$result = self::$gateway->perform_capture( $order, self::CHARGE_ID );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 1, $this->client->call_count( 'captureCharge' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $order->get_status() );
		$this->assertFalse( $this->has_note( $order, 'was already' ) );
	}

	/**
	 * A capture Amazon accepts is still sent and still completes the order.
	 *
	 * @return void
	 */
	public function test_capture_still_captures_when_amazon_is_authorized() : void {
		$order = $this->create_authorized_order();

		$this->client->charge_state    = 'Authorized';
		$this->client->mutation_status = 200;

		$result = self::$gateway->perform_capture( $order, self::CHARGE_ID );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 1, $this->client->call_count( 'captureCharge' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->is_paid() );
		$this->assertFalse( $this->has_note( $order, 'was already' ) );
	}

	/**
	 * An unreadable charge leaves the order untouched instead of corrupting its status.
	 *
	 * @return void
	 */
	public function test_sync_is_skipped_when_charge_cannot_be_read() : void {
		$order  = $this->create_authorized_order();
		$before = $this->cached_charge_state( $order );

		$this->client->read_status = 404;

		$this->assertNull( self::$gateway->log_charge_status_change( $order ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $order->get_status() );
		$this->assertSame( $before, $this->cached_charge_state( $order ) );
	}

	/**
	 * Charge states Close Authorization treats as already closed on Amazon.
	 *
	 * @return array
	 */
	public function closed_charge_states() : array {
		return array(
			'canceled' => array( 'Canceled' ),
			'declined' => array( 'Declined' ),
		);
	}

	/**
	 * Close Authorization on a charge Amazon already closed syncs without cancelling again.
	 *
	 * @dataProvider closed_charge_states
	 *
	 * @param  string $state Charge state on Amazon.
	 * @return void
	 */
	public function test_cancel_auth_reconciles_when_amazon_already_closed( string $state ) : void {
		$order = $this->create_authorized_order();

		$this->client->charge_state    = $state;
		$this->client->mutation_status = 422;

		$result = self::$gateway->perform_cancel_auth( $order, self::CHARGE_ID );

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 0, $this->client->call_count( 'cancelCharge' ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertFalse( $order->is_paid() );
		$this->assertSame( $state, $this->cached_charge_state( $order ) );
		$this->assertTrue( $this->has_note( $order, "was already $state on Amazon" ) );
	}

	/**
	 * A reconciled capture leaves no charge check scheduled.
	 *
	 * @return void
	 */
	public function test_capture_reconcile_leaves_no_charge_check_pending() : void {
		$order = $this->create_authorized_order();
		$args  = array( self::CHARGE_ID, 'CHARGE' );
		$this->assertNotFalse( as_next_scheduled_action( 'wc_amazon_async_polling', $args, 'wc_amazon_async_polling' ) );

		$this->client->charge_state = 'Captured';
		self::$gateway->perform_capture( $order, self::CHARGE_ID );

		$this->assertFalse( as_next_scheduled_action( 'wc_amazon_async_polling', $args, 'wc_amazon_async_polling' ) );
	}

	/**
	 * A failed order action leaves an order note with Amazon's error.
	 *
	 * @return void
	 */
	public function test_failed_order_action_adds_order_note() : void {
		require_once wc_apa()->path . '/includes/admin/class-wc-amazon-payments-advanced-order-admin.php';
		$order = $this->create_authorized_order();

		$this->client->mutation_status = 503;
		$this->client->mutation_reason = 'InternalServerError';

		( new WC_Amazon_Payments_Advanced_Order_Admin() )->do_order_action( $order, self::CHARGE_ID, 'capture', 'v2' );

		$this->assertTrue( $this->has_note( wc_get_order( $order->get_id() ), 'Amazon Pay action "capture" failed' ) );
	}

	/**
	 * Replace the gateway returned by wc_apa()->get_gateway().
	 *
	 * @param  mixed $gateway Gateway to install.
	 * @return mixed The gateway it replaced.
	 */
	protected static function swap_plugin_gateway( $gateway ) {
		$property = ( new ReflectionClass( wc_apa() ) )->getProperty( 'gateway' );
		$property->setAccessible( true );
		$previous = $property->getValue( wc_apa() );
		$property->setValue( wc_apa(), $gateway );

		return $previous;
	}

	/**
	 * Build an order in the state a deferred authorization leaves behind.
	 *
	 * @return WC_Order
	 */
	protected function create_authorized_order() : WC_Order {
		$order = WC_Helper_Order::create_order( self::$gateway->id );
		$order->update_meta_data( 'amazon_charge_permission_id', self::CHARGE_PERMISSION_ID );
		$order->update_meta_data( 'woocommerce_amazon_payments_advanced_version', WC_AMAZON_PAY_VERSION );
		$order->save();

		$this->client->charge_state = 'Authorized';

		self::$gateway->log_charge_permission_status_change( $order, self::CHARGE_PERMISSION_ID );
		self::$gateway->log_charge_status_change( $order, self::CHARGE_ID );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $order->get_status() );
		$this->assertSame( 'Authorized', $this->cached_charge_state( $order ) );

		$this->client->calls = array();

		return $order;
	}

	/**
	 * Read the charge state cached on an order.
	 *
	 * @param  WC_Order $order Order object.
	 * @return ?string
	 */
	protected function cached_charge_state( WC_Order $order ) : ?string {
		$order->read_meta_data( true );

		return self::$gateway->get_cached_charge_status( $order, true )->status;
	}

	/**
	 * Check whether any order note contains the given text.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $needle Text to look for.
	 * @return bool
	 */
	protected function has_note( WC_Order $order, string $needle ) : bool {
		foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
			if ( false !== strpos( $note->content, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
