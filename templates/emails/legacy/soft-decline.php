<?php
/**
 * Soft Decline email body template
 *
 * @package WC_Gateway_Amazon_Pay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Languague is only available for Europe. Given the order language we can determinate the domain.
 */
$lang                = '';
$lang_domain_mapping = WC_Amazon_Payments_Advanced_API_Legacy::get_order_language( $order_id );
if ( 'unknown' !== $lang_domain_mapping ) {
	$lang = "?language=$lang_domain_mapping";
	$tld  = WC_Amazon_Payments_Advanced_API::$lang_domains_mapping[ $lang_domain_mapping ];
} else {
	$region = WC_Amazon_Payments_Advanced_API::get_region();
	switch ( $region ) {
		case 'us':
			$tld = 'com';
			break;
		case 'jp':
			$tld = 'co.jp';
			break;
	}
}
$url      = sprintf( 'https://payments.amazon.%1$s/jr/your-account/orders%2$s', $tld, $lang );
$url_link = "<a href='$url' target='_blank'>$url</a>";
?>

<p><?php _e( 'Valued customer', 'woocommerce-gateway-amazon-payments-advanced' ); ?>,</p>
<p>
<?php
/* translators: 1) Site Name */
printf( __( 'Thank you very much for your order at %s.', 'woocommerce-gateway-amazon-payments-advanced' ), get_bloginfo( 'name' ) );
?>
</p>
<p><?php _e( 'Amazon Pay was not able to process your payment.', 'woocommerce-gateway-amazon-payments-advanced' ); ?></p>
<p>
<?php
/* translators: 1) Amazon Pay URL. */
printf( __( 'Please go to %s and update the payment information for your order. Afterwards we will automatically request payment again from Amazon Pay and you will receive a confirmation email.', 'woocommerce-gateway-amazon-payments-advanced' ), $url_link );
?>
</p>
<p><?php _e( 'Kind regards', 'woocommerce-gateway-amazon-payments-advanced' ); ?>,</p>

