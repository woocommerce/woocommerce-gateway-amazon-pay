<?php
/**
 * Hard Decline email body template
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p><?php _e( 'Valued customer', 'woocommerce-gateway-amazon-payments-advanced' ); ?>,</p>
<p><?php printf( _( 'Unfortunately Amazon Pay declined the payment for your order in our online shop %s. Please contact us.', 'woocommerce-gateway-amazon-payments-advanced' ), get_bloginfo( 'name' ) ); ?></p>
<p><?php _e( 'Kind regards', 'woocommerce-gateway-amazon-payments-advanced' ); ?>,</p>

