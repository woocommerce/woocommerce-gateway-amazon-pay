<?php
/**
 * Test cases for async polling scheduling in WC_Gateway_Amazon_Payments_Advanced.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * WC_Gateway_Amazon_Payments_Advanced_Polling_Test tests when charge polling is scheduled.
 */
class WC_Gateway_Amazon_Payments_Advanced_Polling_Test extends WP_UnitTestCase {

	/**
	 * Polling hook name.
	 *
	 * @var string
	 */
	const HOOK = 'wc_amazon_async_polling';

	/**
	 * Gateway under test.
	 *
	 * @var ?WC_Gateway_Amazon_Payments_Advanced
	 */
	protected $gateway;

	/**
	 * Counter for unique charge ids.
	 *
	 * @var int
	 */
	protected static $counter = 0;

	/**
	 * Charge permission id used by the last run_cp_case() call.
	 *
	 * @var string
	 */
	protected $last_cp_id = '';

	/**
	 * Polling interval recorded by the filter.
	 *
	 * @var int
	 */
	protected $filtered_interval = 0;

	/**
	 * Set up the gateway instance.
	 *
	 * @return void
	 */
	public function set_up() : void {
		parent::set_up();
		$this->gateway = new WC_Gateway_Amazon_Payments_Advanced();
	}

	/**
	 * Build a stub charge object.
	 *
	 * @param string $charge_id Charge id.
	 * @param string $state     Charge state.
	 *
	 * @return object
	 */
	protected function make_charge( $charge_id, $state ) {
		return (object) array(
			'chargeId'           => $charge_id,
			'chargePermissionId' => 'S01-' . $charge_id,
			'creationTimestamp'  => gmdate( 'Ymd\THis\Z' ),
			'statusDetails'      => (object) array(
				'state'   => $state,
				'reasons' => array(),
			),
		);
	}

	/**
	 * Count pending polls for a charge.
	 *
	 * @param string $charge_id Charge id.
	 *
	 * @return int
	 */
	protected function count_polls( $charge_id ) {
		return count(
			as_get_scheduled_actions(
				array(
					'hook'   => self::HOOK,
					'args'   => array( $charge_id, 'CHARGE' ),
					'status' => ActionScheduler_Store::STATUS_PENDING,
				),
				'ids'
			)
		);
	}

	/**
	 * Get the earliest pending poll time for an object, or 0 for none.
	 *
	 * @param string $id   Object id.
	 * @param string $type Object type.
	 *
	 * @return int
	 */
	protected function next_poll_time( $id, $type ) {
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
	 * Run log_charge_status_change and return the order, pending poll count and next poll time.
	 *
	 * @param string $mode      Capture mode setting.
	 * @param string $state     Charge state.
	 * @param bool   $unchanged Whether the cached status already equals the state.
	 * @param int    $prequeue  Seconds ahead to pre-queue a poll, 0 for none.
	 *
	 * @return array
	 */
	protected function run_case( $mode, $state, $unchanged, $prequeue = 0 ) {
		self::$counter++;
		$charge_id = 'P01-337-' . self::$counter . '-' . wp_generate_password( 6, false );

		$this->gateway->settings['payment_capture'] = $mode;

		$order = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$order->update_meta_data( 'amazon_charge_id', $charge_id );
		if ( $unchanged ) {
			$order->update_meta_data(
				'amazon_charge_status',
				wp_json_encode(
					array(
						'status'  => $state,
						'reasons' => array(),
					)
				)
			);
		}
		$order->save();

		if ( $prequeue ) {
			as_schedule_single_action( time() + $prequeue, self::HOOK, array( $charge_id, 'CHARGE' ), self::HOOK );
		}

		$this->gateway->log_charge_status_change( $order, $this->make_charge( $charge_id, $state ) );

		return array( $order, $this->count_polls( $charge_id ), $this->next_poll_time( $charge_id, 'CHARGE' ) );
	}

	/**
	 * Authorize mode, Authorized, unchanged re-checks once a day.
	 *
	 * @return void
	 */
	public function test_authorize_authorized_unchanged_rechecks_daily() : void {
		list( , $count, $next ) = $this->run_case( 'authorize', 'Authorized', true );
		$this->assertSame( 1, $count );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, $next, 60 );
	}

	/**
	 * Authorize mode, Authorized, changed re-checks once a day and sets on-hold.
	 *
	 * @return void
	 */
	public function test_authorize_authorized_changed_rechecks_daily() : void {
		list( $order, $count, $next ) = $this->run_case( 'authorize', 'Authorized', false );
		$this->assertSame( 1, $count );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, $next, 60 );
		$this->assertSame( 'on-hold', wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * Authorize mode, Authorized, keeps an earlier pre-queued poll.
	 *
	 * @return void
	 */
	public function test_authorize_authorized_keeps_earlier_prequeued() : void {
		list( , $count, $next ) = $this->run_case( 'authorize', 'Authorized', true, 600 );
		$this->assertSame( 1, $count );
		$this->assertEqualsWithDelta( time() + 600, $next, 60 );
	}

	/**
	 * Manual mode, Authorized, changed re-checks once a day.
	 *
	 * @return void
	 */
	public function test_manual_authorized_changed_rechecks_daily() : void {
		list( , $count, $next ) = $this->run_case( 'manual', 'Authorized', false );
		$this->assertSame( 1, $count );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, $next, 60 );
	}

	/**
	 * Record the polling interval passed to the filter.
	 *
	 * @param int $interval Polling time.
	 *
	 * @return int
	 */
	public function record_polling_interval( $interval ) {
		$this->filtered_interval = $interval;
		return $interval;
	}

	/**
	 * The polling interval filter receives the daily re-check time.
	 *
	 * @return void
	 */
	public function test_polling_interval_filter_receives_recheck_time() : void {
		add_filter( 'woocommerce_amazon_pa_polling_interval', array( $this, 'record_polling_interval' ) );
		$this->run_case( 'authorize', 'Authorized', true );
		$this->assertEqualsWithDelta( time() + DAY_IN_SECONDS, $this->filtered_interval, 60 );
	}

	/**
	 * An in-flight state pulls a later pending poll forward.
	 *
	 * @return void
	 */
	public function test_in_flight_pulls_later_poll_forward() : void {
		list( , $count, $next ) = $this->run_case( 'authorize', 'CaptureInitiated', true, DAY_IN_SECONDS );
		$this->assertSame( 1, $count );
		$this->assertEqualsWithDelta( time() + 10 * MINUTE_IN_SECONDS, $next, 60 );
	}

	/**
	 * Default mode, Authorized, unchanged keeps polling.
	 *
	 * @return void
	 */
	public function test_default_authorized_unchanged_polls() : void {
		list( , $count ) = $this->run_case( '', 'Authorized', true );
		$this->assertSame( 1, $count );
	}

	/**
	 * Stored 'no' capture value, Authorized, unchanged keeps polling.
	 *
	 * @return void
	 */
	public function test_no_value_authorized_unchanged_polls() : void {
		list( , $count ) = $this->run_case( 'no', 'Authorized', true );
		$this->assertSame( 1, $count );
	}

	/**
	 * Default mode, Authorized, changed polls.
	 *
	 * @return void
	 */
	public function test_default_authorized_changed_polls() : void {
		list( , $count ) = $this->run_case( '', 'Authorized', false );
		$this->assertSame( 1, $count );
	}

	/**
	 * Authorize mode, AuthorizationInitiated, unchanged keeps polling.
	 *
	 * @return void
	 */
	public function test_authorize_authorization_initiated_unchanged_polls() : void {
		list( , $count ) = $this->run_case( 'authorize', 'AuthorizationInitiated', true );
		$this->assertSame( 1, $count );
	}

	/**
	 * Authorize mode, CaptureInitiated, unchanged keeps polling.
	 *
	 * @return void
	 */
	public function test_authorize_capture_initiated_unchanged_polls() : void {
		list( , $count ) = $this->run_case( 'authorize', 'CaptureInitiated', true );
		$this->assertSame( 1, $count );
	}

	/**
	 * Terminal states remove a pre-queued poll.
	 *
	 * @dataProvider terminal_states
	 *
	 * @param string $state Charge state.
	 *
	 * @return void
	 */
	public function test_terminal_state_unschedules_prequeued( $state ) : void {
		list( , $count ) = $this->run_case( '', $state, true, 600 );
		$this->assertSame( 0, $count );
	}

	/**
	 * Terminal charge states.
	 *
	 * @return array
	 */
	public function terminal_states() {
		return array(
			'Captured' => array( 'Captured' ),
			'Declined' => array( 'Declined' ),
			'Canceled' => array( 'Canceled' ),
		);
	}

	/**
	 * Build a stub charge permission object.
	 *
	 * @param string $cp_id CP id.
	 * @param string $state CP state.
	 * @param string $type  CP type.
	 *
	 * @return object
	 */
	protected function make_charge_permission( $cp_id, $state, $type = 'OneTime' ) {
		return (object) array(
			'chargePermissionId'   => $cp_id,
			'chargePermissionType' => $type,
			'creationTimestamp'    => gmdate( 'Ymd\THis\Z' ),
			'statusDetails'        => (object) array(
				'state'   => $state,
				'reasons' => array(),
			),
		);
	}

	/**
	 * Count pending polls for a charge permission.
	 *
	 * @param string $cp_id CP id.
	 *
	 * @return int
	 */
	protected function count_cp_polls( $cp_id ) {
		return count(
			as_get_scheduled_actions(
				array(
					'hook'   => self::HOOK,
					'args'   => array( $cp_id, 'CHARGE_PERMISSION' ),
					'status' => ActionScheduler_Store::STATUS_PENDING,
				),
				'ids'
			)
		);
	}

	/**
	 * Run log_charge_permission_status_change and return the pending CP poll count.
	 *
	 * @param string      $state         CP state.
	 * @param string|null $cached_state  Cached CP state, null for none.
	 * @param string|null $charge_status Cached charge state, null for no charge.
	 * @param string      $type          CP type.
	 * @param int         $prequeue      Seconds ahead to pre-queue a CP poll, 0 for none.
	 *
	 * @return int
	 */
	protected function run_cp_case( $state, $cached_state, $charge_status, $type = 'OneTime', $prequeue = 0 ) {
		self::$counter++;
		$cp_id = 'P01-336-' . self::$counter . '-' . wp_generate_password( 6, false );

		$this->last_cp_id = $cp_id;

		$order = WC_Helper_Order::create_order( 'amazon_payments_advanced' );
		$order->update_meta_data( 'amazon_charge_permission_id', $cp_id );
		if ( null !== $cached_state ) {
			$order->update_meta_data(
				'amazon_charge_permission_status',
				wp_json_encode(
					array(
						'status'  => $cached_state,
						'reasons' => array(),
						'type'    => $type,
					)
				)
			);
		}
		if ( null !== $charge_status ) {
			$order->update_meta_data( 'amazon_charge_id', $cp_id . '-C1' );
			$order->update_meta_data(
				'amazon_charge_status',
				wp_json_encode(
					array(
						'status'  => $charge_status,
						'reasons' => array(),
					)
				)
			);
		}
		$order->save();

		if ( $prequeue ) {
			as_schedule_single_action( time() + $prequeue, self::HOOK, array( $cp_id, 'CHARGE_PERMISSION' ), self::HOOK );
		}

		$this->gateway->log_charge_permission_status_change( $order, $this->make_charge_permission( $cp_id, $state, $type ) );

		return $this->count_cp_polls( $cp_id );
	}

	/**
	 * OneTime Closed removes a pre-queued poll.
	 *
	 * @return void
	 */
	public function test_cp_closed_changed_unschedules_prequeued() : void {
		$this->assertSame( 0, $this->run_cp_case( 'Closed', 'NonChargeable', 'Captured', 'OneTime', 600 ) );
	}

	/**
	 * OneTime Chargeable, changed, does not poll.
	 *
	 * @return void
	 */
	public function test_cp_chargeable_changed_does_not_poll() : void {
		$this->assertSame( 0, $this->run_cp_case( 'Chargeable', 'NonChargeable', 'Canceled' ) );
	}

	/**
	 * OneTime Chargeable, unchanged, drains a pre-queued poll.
	 *
	 * @return void
	 */
	public function test_cp_chargeable_unchanged_unschedules_prequeued() : void {
		$this->assertSame( 0, $this->run_cp_case( 'Chargeable', 'Chargeable', 'Canceled', 'OneTime', 600 ) );
	}

	/**
	 * OneTime Chargeable without a charge re-checks once a week.
	 *
	 * @return void
	 */
	public function test_cp_chargeable_no_charge_rechecks_weekly() : void {
		$this->assertSame( 1, $this->run_cp_case( 'Chargeable', null, null ) );
		$this->assertEqualsWithDelta( time() + WEEK_IN_SECONDS, $this->next_poll_time( $this->last_cp_id, 'CHARGE_PERMISSION' ), 60 );
	}

	/**
	 * OneTime NonChargeable, changed, with a captured charge polls once.
	 *
	 * @return void
	 */
	public function test_cp_nonchargeable_changed_captured_polls_once() : void {
		$this->assertSame( 1, $this->run_cp_case( 'NonChargeable', null, 'Captured' ) );
	}

	/**
	 * OneTime NonChargeable, unchanged, with a captured charge does not re-arm.
	 *
	 * @return void
	 */
	public function test_cp_nonchargeable_unchanged_captured_does_not_rearm() : void {
		$this->assertSame( 0, $this->run_cp_case( 'NonChargeable', 'NonChargeable', 'Captured', 'OneTime', 600 ) );
	}

	/**
	 * OneTime NonChargeable, changed, without a captured charge does not poll.
	 *
	 * @dataProvider uncaptured_charge_states
	 *
	 * @param string|null $charge_status Cached charge state.
	 *
	 * @return void
	 */
	public function test_cp_nonchargeable_changed_uncaptured_does_not_poll( $charge_status ) : void {
		$this->assertSame( 0, $this->run_cp_case( 'NonChargeable', null, $charge_status ) );
	}

	/**
	 * Charge states other than Captured.
	 *
	 * @return array
	 */
	public function uncaptured_charge_states() {
		return array(
			'Authorized' => array( 'Authorized' ),
			'Declined'   => array( 'Declined' ),
			'Canceled'   => array( 'Canceled' ),
			'No charge'  => array( null ),
		);
	}

	/**
	 * Recurring Chargeable and NonChargeable keep polling in both branches.
	 *
	 * @dataProvider recurring_polling_cases
	 *
	 * @param string      $state        CP state.
	 * @param string|null $cached_state Cached CP state, null for none.
	 *
	 * @return void
	 */
	public function test_cp_recurring_polls( $state, $cached_state ) : void {
		$this->assertSame( 1, $this->run_cp_case( $state, $cached_state, null, 'Recurring' ) );
	}

	/**
	 * Recurring CP states that keep polling.
	 *
	 * @return array
	 */
	public function recurring_polling_cases() {
		return array(
			'Chargeable unchanged'    => array( 'Chargeable', 'Chargeable' ),
			'Chargeable changed'      => array( 'Chargeable', null ),
			'NonChargeable unchanged' => array( 'NonChargeable', 'NonChargeable' ),
			'NonChargeable changed'   => array( 'NonChargeable', null ),
		);
	}

	/**
	 * Recurring Closed removes a pre-queued poll.
	 *
	 * @return void
	 */
	public function test_cp_recurring_closed_unschedules_prequeued() : void {
		$this->assertSame( 0, $this->run_cp_case( 'Closed', 'Chargeable', 'Captured', 'Recurring', 600 ) );
	}
}
