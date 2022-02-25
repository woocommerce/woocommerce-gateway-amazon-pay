<?php
/**
 * Amazon Alexa Notifications abstract class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Alexa Notifications abstract class
 */
abstract class WC_Amazon_Payments_Advanced_Alexa_Notifications_Abstract {

	/**
	 * Action on which we are hooking on.
	 *
	 * We are expecting action to provide as parameters the tracking number and
	 * the order id of which we are to enable Alexa Delivery notifications.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * The carrier code through the shipping is being handled.
	 *
	 * Should be supported by Amazon API.
	 * @see https://developer.amazon.com/docs/amazon-pay-checkout/setting-up-delivery-notifications.html
	 *
	 * For a list of supported carriers,
	 * @see https://eps-eu-external-file-share.s3.eu-central-1.amazonaws.com/Alexa/Delivery+Notifications/amazon-pay-delivery-tracker-supported-carriers-v2.csv
	 *
	 * @var string
	 */
	protected $carrier;

	/**
	 * Constructor, which registers this instance's enable_alexa_notifications_for_carrier
	 * to the $action provided.
	 *
	 * @param string $action
	 * @param string $carrier
	 */
	public function __construct( string $action, string $carrier ) {
		$this->action  = $action;
		$this->carrier = $carrier;

		add_action(
			$this->action,
			array( $this, 'enable_alexa_notifications_for_carrier' ),
			/* Allow for third party plugins to alter the priority of this action. */
			apply_filters( 'apa_enable_alexa_notifications_for_carrier_priority_' . str_replace( ' ', '_', strtolower( $this->carrier ) ), 10 ),
			2
		);
	}

	/**
	 * Enables Alexa Delivery notifications for an Order.
	 *
	 * For debug purposes you should enable logging for the Amazon Pay Gateway
	 * and look the logs Under WooCommerce -> Status -> Logs while testing your
	 * integration.
	 *
	 * @param mixed      $tracking_number The tracking numbers provided by the carrier.
	 * @param string|int $order_id        The order id which the tracking number refers to.
	 * @return void
	 */
	public function enable_alexa_notifications_for_carrier( $tracking_number, $order_id ) {
		/* Allow third party plugins to declare their own handler or completely bypass this one. */
		$handler = apply_filters( 'apa_alexa_notification_handler_' . str_replace( ' ', '_', strtolower( $this->carrier ) ), __METHOD__ );
		if ( __METHOD__ !== $handler ) {
			if ( is_callable( $handler ) ) {
				return call_user_func_array( $handler, array( $tracking_number, $order_id ) );
			}
			if ( is_null( $handler ) ) {
				return;
			}
		}

		if ( ! empty( $tracking_number ) && ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );

			/* If we cant retrieve the order or if the order doesn't needs shipping we bail. */
			if ( ! is_a( $order, 'WC_Order' ) || ! ( count( $order->get_items( 'shipping' ) ) > 0 ) ) {

				/* Allow third party plugins to provide a charge permission id. */
				$charge_permission_id = apply_filters( 'apa_alexa_notification_charge_permission_id', $order->get_meta( 'amazon_charge_permission_id' ), $order, $this->carrier );

				/* If the order wan't completed through Amazon Pay of if there is no charge permission id we bail. */
				if ( 'amazon_payments_advanced' === $order->get_payment_method() && $charge_permission_id ) {

					/* Allow third party plugins to alter the payload used for activating Alexa Delivery Notifications. */
					$payload = apply_filters(
						'apa_alexa_notification_payload_' . str_replace( ' ', '_', strtolower( $this->carrier ) ),
						array(
							'chargePermissionId' => $charge_permission_id,
							'deliveryDetails'    => array(
								'trackingNumber' => $tracking_number,
								'carrierCode'    => $this->carrier,
							),
						)
					);

					try {

						/* Bail early if class WC_Amazon_Payments_Advanced_API is not available for some reason. */
						if ( ! class_exists( 'WC_Amazon_Payments_Advanced_API' ) ) {
							throw new Exception( 'Class WC_Amazon_Payments_Advanced_API does not exists!', 1001 );
						}

						$result = WC_Amazon_Payments_Advanced_API::trigger_alexa_notifications( $payload );

						if ( ! empty( $result['status'] ) && 200 === $result['status'] ) {
							/* Log the successful result. */
							wc_apa()->log( 'Successfully enabled Alexa Delivery Notifications for order #' . $order_id . ' with charge permission id ' . $charge_permission_id . ' and got the below response', $result['response'] );
						} else {
							/* Log the error provided by Amazon. */
							wc_apa()->log( 'Failed to enable Alexa Delivery Notifications for order #' . $order_id . ' with charge permission id ' . $charge_permission_id . ' with status: ' . $result['status'] . ' and got the below response', $result['response'] );
						}
					} catch ( Exception $e ) {
						/* Log any possible Exceptions. */
						wc_apa()->log( 'Exception occurred while trying to enable Alexa Delivery Notifications for order #' . $order_id . ' with charge permission id ' . $charge_permission_id . ' with code: ' . $e->getCode() . ' and message: ' . $e->getMessage() );
					}
				}
			}
		}
	}
}
