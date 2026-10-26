<?php
/**
 * Amazon Pay SDK Client Mocker Class.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

declare(strict_types=1);

/**
 * Stands in for Amazon\Pay\API\Client so the API layer can run without network access.
 */
class WC_Mocker_Amazon_Pay_Sdk_Client {

	/**
	 * State reported by getCharge.
	 *
	 * @var string
	 */
	public $charge_state = 'Authorized';

	/**
	 * HTTP status returned by the mutating endpoints.
	 *
	 * @var int
	 */
	public $mutation_status = 200;

	/**
	 * HTTP status returned by the read endpoints.
	 *
	 * @var int
	 */
	public $read_status = 200;

	/**
	 * Reason code returned by the mutating endpoints when they fail.
	 *
	 * @var string
	 */
	public $mutation_reason = 'InvalidChargeStatus';

	/**
	 * Names of the endpoints that were called, in order.
	 *
	 * @var array
	 */
	public $calls = array();

	/**
	 * Install a fresh mocker as the API layer's SDK client.
	 *
	 * @return self
	 */
	public static function install() : self {
		$client   = new self();
		$property = ( new ReflectionClass( 'WC_Amazon_Payments_Advanced_API' ) )->getProperty( 'amazonpay_client' );
		$property->setAccessible( true );
		$property->setValue( null, $client );

		return $client;
	}

	/**
	 * Remove any installed mocker from the API layer.
	 *
	 * @return void
	 */
	public static function uninstall() : void {
		$property = ( new ReflectionClass( 'WC_Amazon_Payments_Advanced_API' ) )->getProperty( 'amazonpay_client' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Count how many times an endpoint was called.
	 *
	 * @param  string $endpoint Endpoint name.
	 * @return int
	 */
	public function call_count( string $endpoint ) : int {
		return count( array_keys( $this->calls, $endpoint, true ) );
	}

	/**
	 * Build a charge payload.
	 *
	 * @param  string $charge_id Charge ID.
	 * @param  string $state Charge state.
	 * @return array
	 */
	protected function charge_response( string $charge_id, string $state ) : array {
		if ( 200 !== $this->read_status ) {
			return array(
				'status'   => $this->read_status,
				'response' => wp_json_encode(
					array(
						'reasonCode' => 'ResourceNotFound',
						'message'    => 'Mocked Amazon read failure.',
					)
				),
			);
		}

		return array(
			'status'   => 200,
			'response' => wp_json_encode(
				array(
					'chargeId'           => $charge_id,
					'chargePermissionId' => 'TEST_CHARGE_PERMISSION_ID',
					'chargeAmount'       => array(
						'amount'       => '50.00',
						'currencyCode' => 'EUR',
					),
					'captureAmount'      => array(
						'amount'       => '50.00',
						'currencyCode' => 'EUR',
					),
					'refundedAmount'     => array(
						'amount'       => '0.00',
						'currencyCode' => 'EUR',
					),
					'creationTimestamp'  => '2026-01-01T10:00:00Z',
					'statusDetails'      => array( 'state' => $state ),
				)
			),
		);
	}

	/**
	 * Build the failure payload for a mutating endpoint.
	 *
	 * @return array
	 */
	protected function mutation_failure() : array {
		return array(
			'status'   => $this->mutation_status,
			'response' => wp_json_encode(
				array(
					'reasonCode' => $this->mutation_reason,
					'message'    => 'Mocked Amazon failure.',
				)
			),
		);
	}

	/**
	 * Mocked getCharge endpoint.
	 *
	 * @param  string     $charge_id Charge ID.
	 * @param  null|array $headers Request headers.
	 * @return array
	 */
	public function getCharge( $charge_id, $headers = null ) : array { // phpcs:ignore WordPress.NamingConventions
		$this->calls[] = 'getCharge';

		return $this->charge_response( (string) $charge_id, $this->charge_state );
	}

	/**
	 * Mocked getChargePermission endpoint.
	 *
	 * @param  string     $charge_permission_id Charge Permission ID.
	 * @param  null|array $headers Request headers.
	 * @return array
	 */
	public function getChargePermission( $charge_permission_id, $headers = null ) : array { // phpcs:ignore WordPress.NamingConventions
		$this->calls[] = 'getChargePermission';

		return array(
			'status'   => 200,
			'response' => wp_json_encode(
				array(
					'chargePermissionId'   => $charge_permission_id,
					'chargePermissionType' => 'OneTime',
					'creationTimestamp'    => '2026-01-01T09:59:00Z',
					'statusDetails'        => array(
						'state'   => 'Chargeable',
						'reasons' => array(),
					),
				)
			),
		);
	}

	/**
	 * Mocked captureCharge endpoint.
	 *
	 * @param  string     $charge_id Charge ID.
	 * @param  array      $data Request payload.
	 * @param  null|array $headers Request headers.
	 * @return array
	 */
	public function captureCharge( $charge_id, $data, $headers = null ) : array { // phpcs:ignore WordPress.NamingConventions
		$this->calls[] = 'captureCharge';

		if ( 200 !== $this->mutation_status ) {
			return $this->mutation_failure();
		}

		$this->charge_state = 'Captured';

		return $this->charge_response( (string) $charge_id, 'Captured' );
	}

	/**
	 * Mocked cancelCharge endpoint.
	 *
	 * @param  string     $charge_id Charge ID.
	 * @param  array      $data Request payload.
	 * @param  null|array $headers Request headers.
	 * @return array
	 */
	public function cancelCharge( $charge_id, $data, $headers = null ) : array { // phpcs:ignore WordPress.NamingConventions
		$this->calls[] = 'cancelCharge';

		if ( 200 !== $this->mutation_status ) {
			return $this->mutation_failure();
		}

		$this->charge_state = 'Canceled';

		return $this->charge_response( (string) $charge_id, 'Canceled' );
	}
}
