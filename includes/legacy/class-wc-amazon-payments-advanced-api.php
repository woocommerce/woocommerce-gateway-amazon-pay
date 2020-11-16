<?php
/**
 * Amazon API class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay API class
 */
class WC_Amazon_Payments_Advanced_API extends WC_Amazon_Payments_Advanced_API_Abstract {

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

}
