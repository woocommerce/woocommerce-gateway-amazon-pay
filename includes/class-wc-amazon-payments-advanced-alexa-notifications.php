<?php
/**
 * Amazon Alexa Notifications class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Alexa Notifications class
 */
class WC_Amazon_Payments_Advanced_Alexa_Notifications {

	/**
	 * Constructor, which registers this instance's enable_alexa_notifications_for_carrier
	 * to the $action provided.
	 *
	 * You can integrate Alexa Notifications in your shipping plugin by calling.
	 * do_action( 'woocommerce_amazon_pa_enable_alexa_notifications', $tracking_number, $carrier, $order_id );
	 */
	public function __construct() {
		add_action(
			'woocommerce_amazon_pa_enable_alexa_notifications',
			array( $this, 'enable_alexa_notifications_for_carrier' ),
			10,
			3
		);
	}

	/**
	 * Enables Alexa Delivery notifications for an Order.
	 *
	 * For debug purposes you should enable logging for the Amazon Pay Gateway
	 * and look the logs Under WooCommerce -> Status -> Logs while testing your
	 * integration.
	 *
	 * $carrier should be supported by Amazon API.
	 *
	 * @see https://developer.amazon.com/docs/amazon-pay-checkout/setting-up-delivery-notifications.html
	 *
	 * For a list of supported carriers,
	 * @see https://eps-eu-external-file-share.s3.eu-central-1.amazonaws.com/Alexa/Delivery+Notifications/amazon-pay-delivery-tracker-supported-carriers-v2.csv
	 *
	 * @throws Exception On error, but the functions catches it and logs it.
	 *
	 * @param mixed      $tracking_number The tracking number provided by the carrier.
	 * @param string     $carrier         The carrier code through the shipping is being handled.
	 * @param string|int $order_id        The order id which the tracking number refers to.
	 * @return void
	 */
	public function enable_alexa_notifications_for_carrier( $tracking_number, $carrier, $order_id ) {
		if ( empty( $tracking_number ) || empty( $order_id ) || empty( $carrier ) ) {
			return;
		}
		$order = wc_get_order( $order_id );

		/* If we cant retrieve the order or if the order doesn't needs shipping we bail. */
		if ( ! class_exists( 'WC_Order' ) || ! ( $order instanceof \WC_Order ) || count( $order->get_items( 'shipping' ) ) <= 0 ) {
			return;
		}

		/* Allow third party plugins to provide a charge permission id. */
		$charge_permission_id = apply_filters( 'woocommerce_amazon_pa_alexa_notification_charge_permission_id', $order->get_meta( 'amazon_charge_permission_id' ), $order, $carrier );

		/* If the order wan't completed through Amazon Pay or if there is no charge permission id we bail. */
		if ( 'amazon_payments_advanced' !== $order->get_payment_method() || ! $charge_permission_id ) {
			return;
		}

		/* Allow third party plugins to alter the payload used for activating Alexa Delivery Notifications. */
		$payload = apply_filters(
			'apa_alexa_notification_payload',
			array(
				'chargePermissionId' => $charge_permission_id,
				'deliveryDetails'    => array(
					array(
						'trackingNumber' => $tracking_number,
						'carrierCode'    => $carrier,
					),
				),
			),
			$order,
			$carrier
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
			do_action( 'woocommerce_amazon_pa_alexa_notification_result', $result, $order, $payload );
		} catch ( Exception $e ) {
			/* Log any Exceptions. */
			wc_apa()->log( 'Exception occurred while trying to enable Alexa Delivery Notifications for order #' . $order_id . ' with charge permission id ' . $charge_permission_id . ' with code: ' . $e->getCode() . ' and message: ' . $e->getMessage() );
			do_action( 'woocommerce_amazon_pa_alexa_notification_exception', $e, $order, $payload );
		}
	}
}
