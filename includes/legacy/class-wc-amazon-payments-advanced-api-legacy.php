<?php
/**
 * Amazon Legacy API class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay API class
 */
class WC_Amazon_Payments_Advanced_API_Legacy extends WC_Amazon_Payments_Advanced_API_Abstract {

	/**
	 * Widgets URLs.
	 *
	 * @var array
	 */
	protected static $widgets_urls = array(
		'sandbox'    => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/sandbox/lpa/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/lpa/js/Widgets.js',
			'jp' => 'https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/sandbox/prod/lpa/js/Widgets.js',
		),
		'production' => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/lpa/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/lpa/js/Widgets.js',
			'jp' => 'https://origin-na.ssl-images-amazon.com/images/G/09/EP/offAmazonPayments/live/prod/lpa/js/Widgets.js',
		),
	);

	/**
	 * Non-app widgets URLs.
	 *
	 * @since 1.6.3
	 *
	 * @var array
	 */
	protected static $non_app_widgets_urls = array(
		'sandbox'    => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/sandbox/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/sandbox/js/Widgets.js',
			'jp' => 'https://static-fe.payments-amazon.com/OffAmazonPayments/jp/sandbox/js/Widgets.js',
		),
		'production' => array(
			'us' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js',
			'gb' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/gbp/js/Widgets.js',
			'eu' => 'https://static-eu.payments-amazon.com/OffAmazonPayments/eur/js/Widgets.js',
			'jp' => 'https://static-fe.payments-amazon.com/OffAmazonPayments/jp/js/Widgets.js',
		),
	);

	/**
	* Validate API keys when settings are updated.
	*
	* @since 1.6.0
	*
	* @return bool Returns true if API keys are valid
	*/
	public static function validate_api_keys() {

		$settings = self::get_settings();

		$ret = false;
		if ( empty( $settings['mws_access_key'] ) ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
			return $ret;
		}

		try {
			if ( empty( $settings['secret_key'] ) ) {
				throw new Exception( __( 'Error: You must enter MWS Secret Key.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			$response = WC_Amazon_Payments_Advanced_API::request( array(
				'Action'                 => 'GetOrderReferenceDetails',
				'AmazonOrderReferenceId' => 'S00-0000000-0000000',
			) );

			// @codingStandardsIgnoreStart
			if ( ! is_wp_error( $response ) && isset( $response->Error->Code ) && 'InvalidOrderReferenceId' !== (string) $response->Error->Code ) {
				if ( 'RequestExpired' === (string) $response->Error->Code ) {
					$message = sprintf( __( 'Error: MWS responded with a RequestExpired error. This is typically caused by a system time issue. Please make sure your system time is correct and try again. (Current system time: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time( 'timestamp' ) ) );
				} else {
					$message = __( 'Error: MWS keys you provided are not valid. Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-amazon-payments-advanced' );
				}

				throw new Exception( $message );
			}

			$ret = true;
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 1 );

		} catch ( Exception $e ) {
			wc_apa()->get_gateway()->update_option( 'amazon_keys_setup_and_validated', 0 );
		    WC_Admin_Settings::add_error( $e->getMessage() );
		}
		// @codingStandardsIgnoreEnd

		return $ret;

	}

	/**
	 * Get widgets URL.
	 *
	 * @return string
	 */
	public static function get_widgets_url() {
		$settings   = self::get_settings();
		$region     = $settings['payment_region'];
		$is_sandbox = 'yes' === $settings['sandbox'];

		// If payment_region is not set in settings, use base country.
		if ( ! $region ) {
			$region = self::get_payment_region_from_country( WC()->countries->get_base_country() );
		}

		if ( 'yes' === $settings['enable_login_app'] ) {
			return $is_sandbox ? self::$widgets_urls['sandbox'][ $region ] : self::$widgets_urls['production'][ $region ];
		}

		$non_app_url = $is_sandbox ? self::$non_app_widgets_urls['sandbox'][ $region ] : self::$non_app_widgets_urls['production'][ $region ];

		return $non_app_url . '?sellerId=' . $settings['seller_id'];
	}

}
