<?php
/**
 * Fake Amazon Pay SDK client for tests.
 *
 * @package WC_Gateway_Amazon_Pay/Tests
 */

/**
 * WC_Mocker_Amazon_Pay_Fake_Client answers object reads from in-memory fixtures.
 */
class WC_Mocker_Amazon_Pay_Fake_Client {

	/**
	 * Fixture objects by id.
	 *
	 * @var array
	 */
	public $objects = array();

	/**
	 * Return a fixture charge.
	 *
	 * @param string     $charge_id Charge id.
	 * @param array|null $headers   Request headers.
	 *
	 * @return array
	 */
	public function getCharge( $charge_id, $headers = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->respond( $charge_id );
	}

	/**
	 * Return a fixture charge permission.
	 *
	 * @param string     $charge_permission_id Charge permission id.
	 * @param array|null $headers              Request headers.
	 *
	 * @return array
	 */
	public function getChargePermission( $charge_permission_id, $headers = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->respond( $charge_permission_id );
	}

	/**
	 * Return a fixture refund.
	 *
	 * @param string     $refund_id Refund id.
	 * @param array|null $headers   Request headers.
	 *
	 * @return array
	 */
	public function getRefund( $refund_id, $headers = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $this->respond( $refund_id );
	}

	/**
	 * Build an SDK-style response for a fixture id.
	 *
	 * @param string $id Object id.
	 *
	 * @return array
	 */
	protected function respond( $id ) {
		if ( ! isset( $this->objects[ $id ] ) ) {
			return array(
				'status'   => 404,
				'response' => wp_json_encode(
					array(
						'reasonCode' => 'ResourceNotFound',
						'message'    => 'Not found',
					)
				),
			);
		}
		return array(
			'status'   => 200,
			'response' => wp_json_encode( $this->objects[ $id ] ),
		);
	}
}
