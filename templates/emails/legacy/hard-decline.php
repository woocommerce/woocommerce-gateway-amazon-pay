<?php
/**
 * Hard Decline email body template
 *
 * @package WC_Gateway_Amazon_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p><?php esc_html_e( 'Valued customer', 'woocommerce-gateway-amazon-payments-advanced' ); ?>,</p>
<p>
<?php
/* translators: 1) Site Name. */
printf( esc_html__( 'Unfortunately Amazon Pay declined the payment for your order in our online shop %s. Please contact us.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( get_bloginfo( 'name' ) ) );
?>
</p>
<p><?php esc_html_e( 'Kind regards', 'woocommerce-gateway-amazon-payments-advanced' ); ?>,</p>

