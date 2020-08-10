<?php
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = sys_get_temp_dir() . '/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	$base_dir = dirname( dirname( dirname( __FILE__ ) ) );
	require $base_dir . '/woocommerce-gateway-amazon-payments-advanced.php';
	require $base_dir . '/../woocommerce/woocommerce.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

tests_add_filter( 'shutdown', function() {
	global $wpdb;

	$tables = array_merge(
		array_values( $wpdb->tables() ),
		array(
			$wpdb->prefix . 'woocommerce_api_keys',
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
			$wpdb->prefix . 'woocommerce_order_items',
			$wpdb->prefix . 'woocommerce_payment_tokens',
			$wpdb->prefix . 'woocommerce_payment_tokens',
			$wpdb->prefix . 'woocommerce_shipping_zone_locations',
			$wpdb->prefix . 'woocommerce_sessions',
			$wpdb->prefix . 'woocommerce_shipping_zones',
			$wpdb->prefix . 'woocommerce_shipping_zone_methods',
			$wpdb->prefix . 'woocommerce_tax_rates',
			$wpdb->prefix . 'woocommerce_tax_rate_locations',
		)
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS $table;" );
	}
}, 9999 );

require $_tests_dir . '/includes/bootstrap.php';

// Require the WooCommerce tests bootstrap so that we can use WooCommerce's testing resources as well.
$plugins_dir = dirname( dirname( dirname( dirname( __FILE__ ) ) ) );
require_once $plugins_dir . '/woocommerce/tests/legacy/bootstrap.php';
