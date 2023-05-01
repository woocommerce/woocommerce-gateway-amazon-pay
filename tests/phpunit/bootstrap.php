<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WC_Gateway_Amazon_Pay
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run tests/bin/install-phpunit-tests-dependencies.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP 8+.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads the plugin early
 */
function _manually_load_plugin() {
	$_plugin_dir = dirname( __FILE__ ) . '/../../';

	require_once $_plugins_dir . '../woocommerce/woocommerce.php';

	require_once $_plugin_dir . 'woocommerce-gateway-amazon-payments-advanced.php';
	require_once $_plugin_dir . 'includes/class-wc-gateway-amazon-payments-advanced-abstract.php';
	require_once $_plugin_dir . 'includes/class-wc-gateway-amazon-payments-advanced.php';

	// Require Test Helpers.
	require_once __DIR__ . '/helpers/class-wc-helper-order.php';
	require_once __DIR__ . '/helpers/class-wc-helper-product.php';
	require_once __DIR__ . '/helpers/class-wc-helper-shipping.php';

	// Require Mockers.
	require_once __DIR__ . '/mockers/class-wc-mocker-gateway-amazon-payments-advanced.php';
	require_once __DIR__ . '/mockers/class-wc-mocker-amazon-payments-advanced-api.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Create Woo Tables forcefully.
tests_add_filter(
	'plugins_loaded',
	function () {
		WC_Install::create_tables();
	},
	1
);

// Clear the DB of all the tables.
tests_add_filter(
	'shutdown',
	function() {
		global $wpdb;

		$tables = array_merge(
			array_values( $wpdb->tables() ),
			array(
				$wpdb->prefix . 'wc_admin_notes',
				$wpdb->prefix . 'wc_admin_note_actions',
				$wpdb->prefix . 'wc_customer_lookup',
				$wpdb->prefix . 'wc_download_log',
				$wpdb->prefix . 'wc_order_coupon_lookup',
				$wpdb->prefix . 'wc_order_product_lookup',
				$wpdb->prefix . 'wc_order_stats',
				$wpdb->prefix . 'wc_order_tax_lookup',
				$wpdb->prefix . 'wc_product_attributes_lookup',
				$wpdb->prefix . 'wc_product_download_directories',
				$wpdb->prefix . 'wc_rate_limits',
				$wpdb->prefix . 'wc_webhooks',
				$wpdb->prefix . 'woocommerce_api_keys',
				$wpdb->prefix . 'woocommerce_attribute_taxonomies',
				$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
				$wpdb->prefix . 'woocommerce_log',
				$wpdb->prefix . 'woocommerce_order_items',
				$wpdb->prefix . 'woocommerce_payment_tokens',
				$wpdb->prefix . 'woocommerce_sessions',
				$wpdb->prefix . 'woocommerce_shipping_zones',
				$wpdb->prefix . 'woocommerce_shipping_zone_locations',
				$wpdb->prefix . 'woocommerce_shipping_zone_methods',
				$wpdb->prefix . 'woocommerce_tax_rates',
				$wpdb->prefix . 'woocommerce_tax_rate_locations',
			)
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table;" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	},
	9999
);

// Bootstrap.
require $_tests_dir . '/includes/bootstrap.php';
