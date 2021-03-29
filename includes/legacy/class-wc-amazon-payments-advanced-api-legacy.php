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

			$response = WC_Amazon_Payments_Advanced_API::request(
				array(
					'Action'                 => 'GetOrderReferenceDetails',
					'AmazonOrderReferenceId' => 'S00-0000000-0000000',
				)
			);

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

	/**
	 * Get reference ID.
	 *
	 * @return string
	 */
	public static function get_reference_id() {
		// TODO: Move to legacy.
		$reference_id = ! empty( $_REQUEST['amazon_reference_id'] ) ? $_REQUEST['amazon_reference_id'] : '';

		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );

			if ( isset( $post_data['amazon_reference_id'] ) ) {
				$reference_id = $post_data['amazon_reference_id'];
			}
		}

		return self::check_session( 'amazon_reference_id', $reference_id );
	}

	/**
	 * Get Access token.
	 *
	 * @return string
	 */
	public static function get_access_token() {
		// TODO: Move to legacy.
		$access_token = ! empty( $_REQUEST['access_token'] ) ? $_REQUEST['access_token'] : ( isset( $_COOKIE['amazon_Login_accessToken'] ) && ! empty( $_COOKIE['amazon_Login_accessToken'] ) ? $_COOKIE['amazon_Login_accessToken'] : '' );

		return self::check_session( 'access_token', $access_token );
	}

	/**
	 * Check WC session for reference ID or access token.
	 *
	 * @since 1.6.0
	 *
	 * @param string $key   Key from query string in URL.
	 * @param string $value Value from query string in URL.
	 *
	 * @return string
	 */
	private static function check_session( $key, $value ) {
		if ( ! in_array( $key, array( 'amazon_reference_id', 'access_token' ), true ) ) {
			return $value;
		}

		// Since others might call the get_reference_id or get_access_token
		// too early, WC instance may not exists.
		if ( ! function_exists( 'WC' ) ) {
			return $value;
		}
		if ( ! is_a( WC()->session, 'WC_Session' ) ) {
			return $value;
		}

		if ( false === strstr( $key, 'amazon_' ) ) {
			$key = 'amazon_' . $key;
		}

		// Set and unset reference ID or access token to/from WC session.
		if ( ! empty( $value ) ) {
			// Set access token or reference ID in session after redirected
			// from Amazon Pay window.
			if ( ! empty( $_GET['amazon_payments_advanced'] ) ) {
				WC()->session->{ $key } = $value;
			}
		} else {
			// Don't get anything in URL, check session.
			if ( ! empty( WC()->session->{ $key } ) ) {
				$value = WC()->session->{ $key };
			}
		}

		return $value;
	}

	/**
	 * If merchant is eu payment region (eu & uk).
	 *
	 * @return bool
	 */
	public static function is_sca_region() {
		return apply_filters(
			'woocommerce_amazon_payments_is_sca_region',
			( 'eu' === self::get_region() || 'gb' === self::get_region() )
		);
	}

	/**
	 * Get auth state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $id       Reference ID.
	 *
	 * @return string|bool Returns false if failed
	 */
	public static function get_reference_state( $order_id, $id ) {
		$state = get_post_meta( $order_id, 'amazon_reference_state', true );
		if ( $state ) {
			return $state;
		}

		$response = self::request(
			array(
				'Action'                 => 'GetOrderReferenceDetails',
				'AmazonOrderReferenceId' => $id,
			)
		);

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}
		$state = (string) $response->GetOrderReferenceDetailsResult->OrderReferenceDetails->OrderReferenceStatus->State;
		// @codingStandardsIgnoreEnd

		update_post_meta( $order_id, 'amazon_reference_state', $state );

		return $state;
	}

	/**
	 * Get auth state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $id       Reference ID.
	 *
	 * @return string|bool Returns false if failed.
	 */
	public static function get_authorization_state( $order_id, $id ) {
		$state = get_post_meta( $order_id, 'amazon_authorization_state', true );
		if ( $state ) {
			return $state;
		}

		$response = self::request(
			array(
				'Action'                => 'GetAuthorizationDetails',
				'AmazonAuthorizationId' => $id,
			)
		);

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}
		$state = (string) $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->State;
		// @codingStandardsIgnoreEnd

		update_post_meta( $order_id, 'amazon_authorization_state', $state );

		self::update_order_billing_address( $order_id, self::get_billing_address_from_response( $response ) );

		return $state;
	}

	/**
	 * Get capture state from amazon API.
	 *
	 * @param string $order_id Order ID.
	 * @param string $id       Reference ID.
	 *
	 * @return string|bool Returns false if failed.
	 */
	public static function get_capture_state( $order_id, $id ) {
		$state = get_post_meta( $order_id, 'amazon_capture_state', true );
		if ( $state ) {
			return $state;
		}

		$response = self::request(
			array(
				'Action'          => 'GetCaptureDetails',
				'AmazonCaptureId' => $id,
			)
		);

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) || isset( $response->Error->Message ) ) {
			return false;
		}
		$state = (string) $response->GetCaptureDetailsResult->CaptureDetails->CaptureStatus->State;
		// @codingStandardsIgnoreEnd

		update_post_meta( $order_id, 'amazon_capture_state', $state );

		return $state;
	}

	/**
	 * Get reference state.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $state    State to retrieve.
	 *
	 * @return string Reference state.
	 */
	public static function get_order_ref_state( $order_id, $state = 'amazon_reference_state' ) {
		$ret_state = '';

		switch ( $state ) {
			case 'amazon_reference_state':
				$ref_id = get_post_meta( $order_id, 'amazon_reference_id', true );
				if ( $ref_id ) {
					$ret_state = self::get_reference_state( $order_id, $ref_id );
				}
				break;

			case 'amazon_authorization_state':
				$ref_id = get_post_meta( $order_id, 'amazon_authorization_id', true );
				if ( $ref_id ) {
					$ret_state = self::get_authorization_state( $order_id, $ref_id );
				}
				break;

			case 'amazon_capture_state':
				$ref_id = get_post_meta( $order_id, 'amazon_capture_id', true );
				if ( $ref_id ) {
					$ret_state = self::get_capture_state( $order_id, $ref_id );
				}
				break;
		}

		return $ret_state;
	}

}
