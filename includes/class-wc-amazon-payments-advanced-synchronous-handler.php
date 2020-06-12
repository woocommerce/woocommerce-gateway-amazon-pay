<?php
/**
 * Amazon Pay Synchronous handler.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay Synchronous handler.
 *
 * The validation logic of SNS message is borrowed from aws-php-sns-message-validator.
 *
 * @see https://github.com/aws/aws-php-sns-message-validator
 * @see https://pay.amazon.com/us/developer/documentation/lpwa/201985720
 *
 * @since 1.8.0
 */
class WC_Amazon_Payments_Advanced_Synchronous_Handler {
	/**
	 * Constructor.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 */
	public function __construct() {
		// Scheduled event to check pending synchronous payments on wrong ipn setting.
		add_action( 'wcga_process_pending_syncro_payments', array( $this, 'process_pending_syncro_payments' ), 10 , 2 );
		// Unschedule on IPN recieve.
		add_action( 'woocommerce_amazon_payments_advanced_handle_ipn_order', array( $this, 'unschedule_pending_syncro_payments' ) );
	}

	/**
	 * Process pending syncronuos payments.
	 */
	public function process_pending_syncro_payments( $order_id, $amazon_authorization_id ) {

		wc_apa()->log(
			__METHOD__,
			sprintf( 'Processing pending synchronous payment. Order: %s, Auth ID: %s', $order_id, $amazon_authorization_id )
		);
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		try {
			$response = WC_Amazon_Payments_Advanced_API::request( array(
				'Action'                 => 'GetAuthorizationDetails',
				'AmazonAuthorizationId' => $amazon_authorization_id,
			) );
			if ( $order->get_meta( 'amazon_timed_out_transaction' ) ) {
				WC_Amazon_Payments_Advanced_API::handle_synch_payment_authorization_payload( $response , $order, $amazon_authorization_id );
			}
		} catch ( Exception $e ) {
			/* translators: placeholder is error message from Amazon Pay API */
			$order->add_order_note( sprintf( __( 'Error: Unable to authorize funds with Amazon. Reason: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $e->getMessage() ) );
		}
	}

	/**
	 * Unschedule Action for Order.
	 */
	public function unschedule_pending_syncro_payments( $order ) {
		// Unschedule the Action for this order.
		$args = array(
			'order_id'                => $order->get_id(),
			'amazon_authorization_id' => $order->get_meta( 'amazon_authorization_id', true ),
		);
		as_unschedule_action( 'wcga_process_pending_syncro_payments', $args );
	}
}
