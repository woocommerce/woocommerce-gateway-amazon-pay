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
	 * API Endpoints.
	 *
	 * @var array
	 */
	protected static $endpoints = array(
		'sandbox'    => array(
			'us' => 'https://mws.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/',
			'gb' => 'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/',
			'eu' => 'https://mws-eu.amazonservices.com/OffAmazonPayments_Sandbox/2013-01-01/',
			'jp' => 'https://mws.amazonservices.jp/OffAmazonPayments_Sandbox/2013-01-01/',
		),
		'production' => array(
			'us' => 'https://mws.amazonservices.com/OffAmazonPayments/2013-01-01/',
			'gb' => 'https://mws-eu.amazonservices.com/OffAmazonPayments/2013-01-01/',
			'eu' => 'https://mws-eu.amazonservices.com/OffAmazonPayments/2013-01-01/',
			'jp' => 'https://mws.amazonservices.jp/OffAmazonPayments/2013-01-01/',
		),
	);

	/**
	 * Get API endpoint.
	 *
	 * @param bool $is_sandbox Whether using sandbox or not.
	 *
	 * @return string
	 */
	public static function get_endpoint( $is_sandbox = false ) {
		$region = self::get_region();

		return $is_sandbox ? self::$endpoints['sandbox'][ $region ] : self::$endpoints['production'][ $region ];
	}

	/**
	 * Make an api request.
	 *
	 * @param  args $args Arguments.
	 *
	 * @return WP_Error|SimpleXMLElement Response.
	 */
	public static function request( $args ) {
		$settings = self::get_settings();
		$defaults = array(
			'AWSAccessKeyId' => $settings['mws_access_key'],
			'SellerId'       => $settings['seller_id'],
		);

		$args     = apply_filters( 'woocommerce_amazon_pa_api_request_args', wp_parse_args( $args, $defaults ) );
		$endpoint = self::get_endpoint( 'yes' === $settings['sandbox'] );

		$url = self::get_signed_amazon_url( $endpoint . '?' . http_build_query( $args, '', '&' ), $settings['secret_key'] );
		wc_apa()->log( sprintf( 'GET: %s', self::sanitize_remote_request_log( $url ) ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$response        = self::safe_load_xml( $response['body'], LIBXML_NOCDATA );
			$logged_response = self::sanitize_remote_response_log( $response );

			wc_apa()->log( sprintf( 'Response: %s', $logged_response ) );
		} else {
			wc_apa()->log( sprintf( 'Error: %s', $response->get_error_message() ) );
		}

		return $response;
	}

	/**
	 * Safe load XML.
	 *
	 * @param  string $source  XML input.
	 * @param  int    $options Options.
	 *
	 * @return SimpleXMLElement|bool
	 */
	public static function safe_load_xml( $source, $options = 0 ) {
		$old = null;

		if ( '<' !== substr( $source, 0, 1 ) ) {
			return false;
		}

		if ( function_exists( 'libxml_disable_entity_loader' ) ) {
			$old = libxml_disable_entity_loader( true );
		}

		$dom    = new DOMDocument();
		$return = $dom->loadXML( $source, $options );

		if ( ! is_null( $old ) ) {
			libxml_disable_entity_loader( $old );
		}

		if ( ! $return ) {
			return false;
		}

		if ( isset( $dom->doctype ) ) {
			return false;
		}

		return simplexml_import_dom( $dom );
	}

	/**
	 * Sanitize log message.
	 *
	 * Used to sanitize logged HTTP response message.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/133
	 * @since 1.6.0
	 *
	 * @param mixed $message Log message.
	 *
	 * @return string Sanitized log message.
	 */
	private static function sanitize_remote_response_log( $message ) {
		if ( ! is_a( $message, 'SimpleXMLElement' ) ) {
			return (string) $message;
		}

		if ( ! is_callable( array( $message, 'asXML' ) ) ) {
			return '';
		}

		$message = $message->asXML();

		// Sanitize response message.
		$patterns    = array();
		$patterns[0] = '/(<Buyer>)(.+)(<\/Buyer>)/ms';
		$patterns[1] = '/(<PhysicalDestination>)(.+)(<\/PhysicalDestination>)/ms';
		$patterns[2] = '/(<BillingAddress>)(.+)(<\/BillingAddress>)/ms';
		$patterns[3] = '/(<SellerNote>)(.+)(<\/SellerNote>)/ms';
		$patterns[4] = '/(<AuthorizationBillingAddress>)(.+)(<\/AuthorizationBillingAddress>)/ms';
		$patterns[5] = '/(<SellerAuthorizationNote>)(.+)(<\/SellerAuthorizationNote>)/ms';
		$patterns[6] = '/(<SellerCaptureNote>)(.+)(<\/SellerCaptureNote>)/ms';
		$patterns[7] = '/(<SellerRefundNote>)(.+)(<\/SellerRefundNote>)/ms';

		$replacements    = array();
		$replacements[0] = '$1 REMOVED $3';
		$replacements[1] = '$1 REMOVED $3';
		$replacements[2] = '$1 REMOVED $3';
		$replacements[3] = '$1 REMOVED $3';
		$replacements[4] = '$1 REMOVED $3';
		$replacements[5] = '$1 REMOVED $3';
		$replacements[6] = '$1 REMOVED $3';
		$replacements[7] = '$1 REMOVED $3';

		return preg_replace( $patterns, $replacements, $message );
	}

	/**
	 * Sanitize logged request.
	 *
	 * Used to sanitize logged HTTP request message.
	 *
	 * @see https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/issues/133
	 * @since 1.6.0
	 *
	 * @param string $message Log message from stringified array structure.
	 *
	 * @return string Sanitized log message
	 */
	private static function sanitize_remote_request_log( $message ) {
		$patterns    = array();
		$patterns[0] = '/(AWSAccessKeyId=)(.+)(&)/ms';
		$patterns[0] = '/(SellerNote=)(.+)(&)/ms';
		$patterns[1] = '/(SellerAuthorizationNote=)(.+)(&)/ms';
		$patterns[2] = '/(SellerCaptureNote=)(.+)(&)/ms';
		$patterns[3] = '/(SellerRefundNote=)(.+)(&)/ms';

		$replacements    = array();
		$replacements[0] = '$1REMOVED$3';
		$replacements[1] = '$1REMOVED$3';
		$replacements[2] = '$1REMOVED$3';
		$replacements[3] = '$1REMOVED$3';

		return preg_replace( $patterns, $replacements, $message );
	}

	/**
	 * Sign a url for amazon.
	 *
	 * @param string $url        URL.
	 * @param string $secret_key Secret key.
	 *
	 * @return string
	 */
	private static function get_signed_amazon_url( $url, $secret_key ) {
		$urlparts = wp_parse_url( $url );

		// Build $params with each name/value pair.
		foreach ( explode( '&', $urlparts['query'] ) as $part ) {
			if ( strpos( $part, '=' ) ) {
				list( $name, $value ) = explode( '=', $part, 2 );
			} else {
				$name  = $part;
				$value = '';
			}
			$params[ $name ] = $value;
		}

		// Include a timestamp if none was provided.
		if ( empty( $params['Timestamp'] ) ) {
			$params['Timestamp'] = gmdate( 'Y-m-d\TH:i:s\Z' );
		}

		$params['SignatureVersion'] = '2';
		$params['SignatureMethod']  = 'HmacSHA256';

		// Sort the array by key.
		ksort( $params );

		// Build the canonical query string.
		$canonical = '';

		// Don't encode here - http_build_query already did it.
		foreach ( $params as $key => $val ) {
			$canonical .= $key . '=' . rawurlencode( utf8_decode( urldecode( $val ) ) ) . '&';
		}

		// Remove the trailing ampersand.
		$canonical = preg_replace( '/&$/', '', $canonical );

		// Some common replacements and ones that Amazon specifically mentions.
		$canonical = str_replace( array( ' ', '+', ',', ';' ), array( '%20', '%20', urlencode( ',' ), urlencode( ':' ) ), $canonical ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode

		// Build the sign.
		$string_to_sign = "GET\n{$urlparts['host']}\n{$urlparts['path']}\n$canonical";

		// Calculate our actual signature and base64 encode it.
		$signature = base64_encode( hash_hmac( 'sha256', $string_to_sign, $secret_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		// Finally re-build the URL with the proper string and include the Signature.
		$url = "{$urlparts['scheme']}://{$urlparts['host']}{$urlparts['path']}?$canonical&Signature=" . rawurlencode( $signature );

		return $url;
	}

	/**
	 * Validate API keys when settings are updated.
	 *
	 * @since 1.6.0
	 *
	 * @return bool Returns true if API keys are valid
	 *
	 * @throws Exception On Errors.
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

			$response = self::request(
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
		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof \WC_Order ) ) {
			return false;
		}

		$state = $order->get_meta( 'amazon_reference_state', true, 'edit' );
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

		$order->update_meta_data( 'amazon_reference_state', $state );
		$order->save();

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
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return false;
		}

		$state = $order->get_meta( 'amazon_authorization_state', true, 'edit' );
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

		$order->update_meta_data( 'amazon_authorization_state', $state );
		$order->save();

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
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return false;
		}

		$state = $order->get_meta( 'amazon_capture_state', true, 'edit' );
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

		$order->update_meta_data( 'amazon_capture_state', $state );
		$order->save();

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
		$reference_state = '';

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof \WC_order ) ) {
			return $reference_state;
		}

		switch ( $state ) {
			case 'amazon_reference_state':
				$reference_id = $order->get_meta( 'amazon_reference_id', true, 'edit' );
				if ( $reference_id ) {
					$reference_state = self::get_reference_state( $order_id, $reference_id );
				}
				break;

			case 'amazon_authorization_state':
				$reference_id = $order->get_meta( 'amazon_authorization_id', true, 'edit' );
				if ( $reference_id ) {
					$reference_state = self::get_authorization_state( $order_id, $reference_id );
				}
				break;

			case 'amazon_capture_state':
				$reference_id = $order->get_meta( 'amazon_capture_id', true, 'edit' );
				if ( $reference_id ) {
					$reference_state = self::get_capture_state( $order_id, $reference_id );
				}
				break;
		}

		return $reference_state;
	}

	/**
	 * Handle the result of an async ipn authorization request.
	 * https://m.media-amazon.com/images/G/03/AMZNPayments/IntegrationGuide/AmazonPay_-_Order_Confirm_And_Omnichronous_Authorization_Including-IPN-Handler._V516642695_.svg
	 *
	 * @param object       $ipn_payload    IPN payload.
	 * @param int|WC_Order $order          Order object.
	 *
	 * @return string Authorization status.
	 */
	public static function handle_async_ipn_payment_authorization_payload( $ipn_payload, $order ) {
		$order = is_int( $order ) ? wc_get_order( $order ) : $order;

		$auth_id = self::get_auth_id_from_response( $ipn_payload );
		if ( ! $auth_id ) {
			return false;
		}

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$order->update_meta_data( 'amazon_authorization_id', $auth_id );

		$authorization_status = self::get_auth_state_from_reponse( $ipn_payload );
		switch ( $authorization_status ) {
			case 'open':
				// Updating amazon_authorization_state.
				$order->update_meta_data( 'amazon_authorization_state', 'Open' );
				// Delete amazon_timed_out_transaction meta.
				$order->delete_meta_data( 'amazon_timed_out_transaction' );
				/* translators: 1) Auth ID. */
				$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
				$order->add_order_note( __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				break;
			case 'closed':
				$order->update_meta_data( 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
				$order->update_meta_data( 'amazon_authorization_state', $authorization_status );
				/* translators: 1) Auth ID. */
				$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
				$order->payment_complete();
				// Delete amazon_timed_out_transaction meta.
				$order->delete_meta_data( 'amazon_timed_out_transaction' );
				// Close order reference.
				self::close_order_reference( $order_id );
				break;
			case 'declined':
				$state_reason_code = self::get_auth_state_reason_code_from_response( $ipn_payload );
				if ( 'InvalidPaymentMethod' === $state_reason_code ) {
					// Soft Decline.
					$order->update_meta_data( 'amazon_authorization_state', 'Suspended' );
					$order->add_order_note( sprintf( __( 'Amazon Order Suspended. Email sent to customer to change its payment method.', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
					$subject = __( 'Please update your payment information', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/soft-decline.php', array( 'order_id' => $order_id ), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					wc_apa()->log( 'EMAIL ' . $message );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'AmazonRejected' === $state_reason_code || 'ProcessingFailure' === $state_reason_code ) {
					// Hard decline.
					/* translators: 1) Reason. */
					$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
					// Hard Decline client's email.
					$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'TransactionTimedOut' === $state_reason_code ) {
					// On the second timedout we need to cancel on woo and amazon.
					if ( ! $order->meta_exists( 'amazon_timed_out_times' ) ) {
						$order->update_meta_data( 'amazon_timed_out_times', 1 );
					} else {
						$order->update_meta_data( 'amazon_timed_out_times', 2 );
						// Hard Decline.
						/* translators: 1) Reason. */
						$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
						// Hard Decline client's email.
						$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
						$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
						self::send_email_notification( $subject, $message, $order->get_billing_email() );
						// Delete amazon_timed_out_transaction meta.
						$order->delete_meta_data( $order_id, 'amazon_timed_out_transaction' );
						// Cancel amazon order.
						self::cancel_order_reference( $order_id );
					}
				}

				break;
		}

		$order->save();

		return $authorization_status;
	}

	/**
	 * Handle the result of an sync authorization request.
	 *
	 * @param  mixed    $response Authorization Details object from the Amazon API.
	 * @param  WC_Order $order Order object.
	 * @param  string   $auth_id Authorization ID.
	 * @return string Authorization status.
	 */
	public static function handle_synch_payment_authorization_payload( $response, $order, $auth_id = false ) {
		$order = is_int( $order ) ? wc_get_order( $order ) : $order;

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$order->update_meta_data( 'amazon_authorization_id', $auth_id );

		$authorization_status = self::get_auth_state_from_reponse( $response );
		wc_apa()->log( sprintf( 'Found authorization status of %s on pending synchronous payment', $authorization_status ) );

		switch ( $authorization_status ) {
			case 'open':
				// Updating amazon_authorization_state.
				$order->update_meta_data( 'amazon_authorization_state', 'Open' );
				// Delete amazon_timed_out_transaction meta.
				$order->delete_meta_data( 'amazon_timed_out_transaction' );
				/* translators: 1) Auth ID. */
				$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
				$order->add_order_note( __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				break;
			case 'closed':
				$order->update_meta_data( 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
				$order->update_meta_data( 'amazon_authorization_state', $authorization_status );
				/* translators: 1) Auth ID. */
				$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
				$order->payment_complete();
				// Delete amazon_timed_out_transaction meta.
				$order->delete_meta_data( 'amazon_timed_out_transaction' );
				// Close order reference.
				self::close_order_reference( $order_id );
				break;
			case 'declined':
				$state_reason_code = self::get_auth_state_reason_code_from_response( $response );
				if ( 'InvalidPaymentMethod' === $state_reason_code ) {
					// Soft Decline.
					$order->update_meta_data( 'amazon_authorization_state', 'Suspended' );
					$order->add_order_note( sprintf( __( 'Amazon Order Suspended. Email sent to customer to change its payment method.', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
					$subject = __( 'Please update your payment information', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/soft-decline.php', array( 'order_id' => $order_id ), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					wc_apa()->log( 'EMAIL ' . $message );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'AmazonRejected' === $state_reason_code || 'ProcessingFailure' === $state_reason_code ) {
					// Hard decline.
					/* translators: 1) Reason. */
					$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
					// Hard Decline client's email.
					$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'TransactionTimedOut' === $state_reason_code ) {
					if ( ! $order->meta_exists( 'amazon_timed_out_times' ) ) {
						$order->update_meta_data( 'amazon_timed_out_times', 1 );
						// Hard Decline.
						/* translators: 1) Reason. */
						$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
						// Hard Decline client's email.
						$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
						$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
						self::send_email_notification( $subject, $message, $order->get_billing_email() );
						// Delete amazon_timed_out_transaction meta.
						$order->delete_meta_data( $order_id, 'amazon_timed_out_transaction' );
						// Cancel amazon order.
						self::cancel_order_reference( $order_id );
					}
				}
				break;
			case 'pending':
				$args = array(
					'order_id'                => $order->get_id(),
					'amazon_authorization_id' => $auth_id,
				);
				// Schedule action to check pending order next hour.
				$next_scheduled_action = as_next_scheduled_action( 'wcga_process_pending_syncro_payments', $args );
				if ( false === $next_scheduled_action || true === $next_scheduled_action ) {
					as_schedule_single_action( strtotime( 'next hour' ), 'wcga_process_pending_syncro_payments', $args );
				}
				break;
		}

		$order->save();

		return $authorization_status;
	}

	/**
	 * Get order language from order metadata.
	 *
	 * @param string $order_id Order ID.
	 *
	 * @return string
	 */
	public static function get_order_language( $order_id ) {
		$order = wc_get_order( $order_id );
		return $order instanceof \WC_Order ? $order->get_meta( 'amazon_order_language', true, 'edit' ) : '';
	}

	/**
	 * Authorize payment against an order reference using 'Authorize' method.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752010
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order.
	 * @param array        $args  Arguments.
	 *
	 * @return bool|WP_Error|SimpleXMLElement Response.
	 */
	public static function authorize( $order, $args = array() ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$order_id = wc_apa_get_order_prop( $order, 'id' );
		$args     = wp_parse_args(
			$args,
			array(
				'capture_now' => false,
			)
		);

		if ( empty( $args['amazon_reference_id'] ) ) {
			$args['amazon_reference_id'] = $order->get_meta( 'amazon_reference_id', true, 'edit' );
		}

		if ( ! $args['amazon_reference_id'] ) {
			return new WP_Error( 'order_missing_reference_id', __( 'Order missing Amazon order reference ID.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$response = self::request( self::get_authorize_request_args( $order, $args ) );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( isset( $response->Error->Message ) ) {
			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}

		if ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$code = isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode )
				? (string) $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode
				: '';

			switch ( $code ) {
				case 'InvalidPaymentMethod':
					return new WP_Error( $code, __( 'The selected payment method was declined. Please try different payment method.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				case 'AmazonRejected':
				case 'ProcessingFailure':
				case 'TransactionTimedOut':
					// If the transaction timed out and async authorization is enabled then just return here, leaving
					// the transaction pending. We'll let the calling code trigger an async authorization.
					$authorization_mode = self::get_authorization_mode();
					if ( 'TransactionTimedOut' === $code && 'async' === $authorization_mode ) {
						return new WP_Error( $code );
					}

					// Now we know that we definitely want to cancel the order reference, let's do that and return an
					// appropriate error message.
					$result = self::cancel_order_reference( $order, $code );

					// Invalid order or missing order reference which unlikely
					// to happen, but log in case happens.
					$failed_before_api_request = (
						is_wp_error( $result )
						&&
						in_array( $result->get_error_code(), array( 'invalid_order', 'order_missing_amazon_reference_id' ), true )
					);
					if ( $failed_before_api_request ) {
						wc_apa()->log( sprintf( 'Failed to cancel order reference: %s', $result->get_error_message() ) );
					}

					$redirect_url = add_query_arg(
						array(
							'amazon_payments_advanced' => 'true',
							'amazon_logout'            => 'true',
							'amazon_declined'          => 'true',
						),
						$order->get_cancel_order_url()
					);

					/* translators: placeholder is redirect URL */
					return new WP_Error( $code, sprintf( __( 'There was a problem with the selected payment method. Transaction was declined and order will be cancelled. You will be redirected to cart page automatically, if not please click <a href="%s">here</a>.', 'woocommerce-gateway-amazon-payments-advanced' ), $redirect_url ) );
			}
		}
		// phpcs:enable

		return $response;
	}

	/**
	 * Authorize payment against an order reference using 'Authorize' method.
	 *
	 * @see: https://payments.amazon.com/documentation/apireference/201752010
	 *
	 * @param int    $order_id            Order ID.
	 * @param string $amazon_reference_id Amazon reference ID.
	 * @param bool   $capture_now         Whether to immediately capture or not.
	 *
	 * @return bool See return value of self::handle_payment_authorization_response.
	 */
	public static function authorize_payment( $order_id, $amazon_reference_id, $capture_now = false ) {
		$response = self::authorize(
			$order_id,
			array(
				'amazon_reference_id' => $amazon_reference_id,
				'capture_now'         => $capture_now,
			)
		);

		return self::handle_payment_authorization_response( $response, $order_id, $capture_now );
	}

	/**
	 * Get args to perform Authorize request.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args  Base args.
	 */
	public static function get_authorize_request_args( $order, $args ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		return apply_filters(
			'woocommerce_amazon_pa_authorize_request_args',
			array(
				'Action'                              => 'Authorize',
				'AmazonOrderReferenceId'              => $args['amazon_reference_id'],
				'AuthorizationReferenceId'            => $order_id . '-' . time(),
				'AuthorizationAmount.Amount'          => $order->get_total(),
				'AuthorizationAmount.CurrencyCode'    => wc_apa_get_order_prop( $order, 'order_currency' ),
				'CaptureNow'                          => $args['capture_now'],
				'TransactionTimeout'                  => ( isset( $args['transaction_timeout'] ) ) ? $args['transaction_timeout'] : 0,
				'SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
				'SellerOrderAttributes.StoreName'     => WC_Amazon_Payments_Advanced::get_site_name(),
				// 'SellerAuthorizationNote'          => '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"AmazonRejected"}}'
			)
		);
	}

	/**
	 * Authorize payment against a billing agreement using 'AuthorizeOnBillingAgreement' method
	 * See: https://payments.amazon.com/documentation/automatic/201752090#201757380
	 *
	 * @param int        $order_id                    Order ID.
	 * @param string     $amazon_billing_agreement_id Reference ID.
	 * @param bool|false $capture_now                 Whether to capture immediately.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function authorize_recurring_payment( $order_id, $amazon_billing_agreement_id, $capture_now = false ) {
		$response = self::authorize_recurring(
			$order_id,
			array(
				'amazon_billing_agreement_id' => $amazon_billing_agreement_id,
				'capture_now'                 => $capture_now,
			)
		);

		return self::handle_payment_authorization_response( $response, $order_id, $capture_now );
	}

	/**
	 * Authorize recurring payment against an order reference using
	 * 'AuthorizeOnBillingAgreement' method.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752010
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 * @param array        $args  Whether to immediately capture or not.
	 *
	 * @return bool|WP_Error
	 */
	public static function authorize_recurring( $order, $args = array() ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'amazon_reference_id' => $order->get_meta( 'amazon_billing_agreement_id', true, 'edit' ),
				'capture_now'         => false,
			)
		);

		if ( ! $args['amazon_billing_agreement_id'] ) {
			return new WP_Error( 'order_missing_billing_agreement_id', __( 'Order missing Amazon billing agreement ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$response = self::request( self::get_authorize_recurring_request_args( $order, $args ) );

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return $response;
	}

	/**
	 * Get args to perform AuthorizeBillingAgreement request.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args  Args.
	 *
	 * @return array Request args.
	 */
	public static function get_authorize_recurring_request_args( $order, $args ) {
		$order_id        = wc_apa_get_order_prop( $order, 'id' );
		$order_shippable = self::maybe_subscription_is_shippable( $order );

		return array(
			'Action'                              => 'AuthorizeOnBillingAgreement',
			'AmazonBillingAgreementId'            => $args['amazon_billing_agreement_id'],
			'AuthorizationReferenceId'            => $order_id . '-' . time(),
			'AuthorizationAmount.Amount'          => $order->get_total(),
			'AuthorizationAmount.CurrencyCode'    => wc_apa_get_order_prop( $order, 'order_currency' ),
			'CaptureNow'                          => $args['capture_now'],
			'TransactionTimeout'                  => 0,
			'SellerOrderAttributes.SellerOrderId' => $order->get_order_number(),
			'SellerOrderAttributes.StoreName'     => WC_Amazon_Payments_Advanced::get_site_name(),
			'InheritShippingAddress'              => $order_shippable,
		);
	}

	/**
	 * Handle the result of an authorization request.
	 *
	 * @param object       $response    Return value from self::request().
	 * @param int|WC_Order $order       Order object.
	 * @param bool         $capture_now Whether to capture immediately or not.
	 * @param string       $auth_method Deprecated. Which API authorization
	 *                                  method was used (Authorize, or
	 *                                  AuthorizeOnBillingAgreement).
	 *
	 * @return bool Whether or not payment was authorized.
	 */
	public static function handle_payment_authorization_response( $response, $order, $capture_now, $auth_method = null ) {
		$order = wc_get_order( $order );

		if ( ! ( $order instanceof \WC_Order ) ) {
			return false;
		}

		if ( null !== $auth_method ) {
			_deprecated_function( 'WC_Amazon_Payments_Advanced_API_Legacy::handle_payment_authorization_response', '1.6.0', 'Parameter auth_method is not used anymore' );
		}

		if ( is_wp_error( $response ) ) {
			/* translators: 1) Reason. */
			$order->add_order_note( sprintf( __( 'Error: Unable to authorize funds with Amazon. Reason: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );

			return false;
		}

		return self::update_order_from_authorize_response( $order, $response, $capture_now );
	}

	/**
	 * Update order from authorization response.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param Object   $response    Response from self::request.
	 * @param bool     $capture_now Whether to capture immediately.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function update_order_from_authorize_response( $order, $response, $capture_now = false ) {
		$auth_id = self::get_auth_id_from_response( $response );
		if ( ! $auth_id ) {
			return false;
		}
		$amazon_reference_id = self::get_reference_id_from_response( $response );

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		$order->update_meta_data( 'amazon_authorization_id', $auth_id );

		// This is true only on AuthorizeOnBillingAgreement, for the recurring payments.
		if ( $amazon_reference_id ) {
			$order->update_meta_data( 'amazon_reference_id', $amazon_reference_id );
		}

		$order->save();

		self::update_order_billing_address( $order_id, self::get_billing_address_from_response( $response ) );

		$state = self::get_auth_state_from_reponse( $response );
		if ( 'declined' === $state ) {
			/* translators: 1) Reason. */
			$order->add_order_note( sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), self::get_auth_state_reason_code_from_response( $response ) ) );
			// Payment was not authorized.
			return false;
		}

		if ( $capture_now ) {
			$order->update_meta_data( 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
			$order->save();

			/* translators: 1) Auth ID. */
			$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
			$order->payment_complete();
		} else {
			/* translators: 1) Auth ID. */
			$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
		}

		return true;
	}

	/**
	 * Cancels a previously confirmed order reference.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $reason Reason for the cancellation.
	 *
	 * @return bool|WP_Error Return true when succeed. Otherwise WP_Error is returned.
	 */
	public static function cancel_order_reference( $order, $reason = '' ) {
		$order    = wc_get_order( $order );
		$order_id = wc_apa_get_order_prop( $order, 'id' );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$amazon_reference_id = $order->get_meta( 'amazon_reference_id', true, 'edit' );
		if ( ! $amazon_reference_id ) {
			return new WP_Error( 'order_missing_amazon_reference_id', __( 'Order missing Amazon reference ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$request_args = array(
			'Action'                 => 'CancelOrderReference',
			'AmazonOrderReferenceId' => $amazon_reference_id,
		);

		if ( $reason ) {
			$request_args['CancelationReason'] = $reason;
		}

		$response = self::request( $request_args );

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( isset( $response->Error->Message ) ) {
			$order->add_order_note( (string) $response->Error->Message );

			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return true;
	}

	/**
	 * Get Authorization ID from reesponse.
	 *
	 * @since 1.6.9
	 *
	 * @param object $response Return value from self::request().
	 *
	 * @return string|bool String of Authorization ID. Otherwise false is returned.
	 */
	public static function get_auth_id_from_response( $response ) {
		$auth_id = false;

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AmazonAuthorizationId ) ) {
			$auth_id = (string) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AmazonAuthorizationId;
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AmazonAuthorizationId ) ) {
			$auth_id = (string) $response->AuthorizeResult->AuthorizationDetails->AmazonAuthorizationId;
		} elseif ( isset( $response->AuthorizationDetails->AmazonAuthorizationId ) ) {
			$auth_id = (string) $response->AuthorizationDetails->AmazonAuthorizationId;
		}
		// @codingStandardsIgnoreEnd

		return $auth_id;
	}

	/**
	 * Get Amazon Order ID from response.
	 *
	 * @since 1.6.0
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return string Amazon Order ID.
	 */
	public static function get_reference_id_from_response( $response ) {
		$details = false;

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AmazonOrderReferenceId ) ) {
			$details = (string) $response->AuthorizeOnBillingAgreementResult->AmazonOrderReferenceId;
		}
		// @codingStandardsIgnoreEnd

		return $details;
	}

	/**
	 * Get billing address from response.
	 *
	 * @since 1.6.0
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return array Billing address.
	 */
	public static function get_billing_address_from_response( $response ) {
		$details = array();

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationBillingAddress ) ) {
			$details = (array) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationBillingAddress;
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationBillingAddress ) ) {
			$details = (array) $response->AuthorizeResult->AuthorizationDetails->AuthorizationBillingAddress;
		}
		// @codingStandardsIgnoreEnd

		return $details;
	}

	/**
	 * Get Authorization state from reesponse.
	 *
	 * @since 1.6.9
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return string|bool String of Authorization state.
	 */
	public static function get_auth_state_from_reponse( $response ) {
		$state = 'pending';

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->State );
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->State );
		} elseif ( isset( $response->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->AuthorizationDetails->AuthorizationStatus->State );
		} elseif ( isset( $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->State ) ) {
			$state = strtolower( (string) $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->State );
		}
		// @codingStandardsIgnoreEnd

		return $state;
	}

	/**
	 * Get Authorization state reason code from reesponse.
	 *
	 * @see   https://payments.amazon.com/documentation/apireference/201752950
	 * @since 1.6.9
	 *
	 * @param object $response Response from self::request().
	 *
	 * @return string|bool String of Authorization state.
	 */
	public static function get_auth_state_reason_code_from_response( $response ) {
		$reason_code = 'Unknown';

		// @codingStandardsIgnoreStart
		if ( isset( $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->AuthorizeOnBillingAgreementResult->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		} elseif ( isset( $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->AuthorizeResult->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		} elseif ( isset( $response->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		} elseif ( isset( $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->ReasonCode ) ) {
			$reason_code = (string) $response->GetAuthorizationDetailsResult->AuthorizationDetails->AuthorizationStatus->ReasonCode;
		}
		// @codingStandardsIgnoreEnd

		return $reason_code;
	}

	/**
	 * Update order billing address.
	 *
	 * @since 1.6.0
	 *
	 * @param int   $order_id Order ID.
	 * @param array $address  Billing address.
	 *
	 * @return bool
	 */
	public static function update_order_billing_address( $order_id, $address = array() ) {
		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof \WC_Order ) ) {
			return false;
		}

		// Format address and map to WC fields.
		$address_lines = array();

		if ( ! empty( $address['AddressLine1'] ) ) {
			$address_lines[] = $address['AddressLine1'];
		}
		if ( ! empty( $address['AddressLine2'] ) ) {
			$address_lines[] = $address['AddressLine2'];
		}
		if ( ! empty( $address['AddressLine3'] ) ) {
			$address_lines[] = $address['AddressLine3'];
		}

		if ( 3 === count( $address_lines ) ) {
			$order->update_meta_data( '_billing_company', $address_lines[0] );
			$order->update_meta_data( '_billing_address_1', $address_lines[1] );
			$order->update_meta_data( '_billing_address_2', $address_lines[2] );
		} elseif ( 2 === count( $address_lines ) ) {
			$order->update_meta_data( '_billing_address_1', $address_lines[0] );
			$order->update_meta_data( '_billing_address_2', $address_lines[1] );
		} elseif ( count( $address_lines ) ) {
			$order->update_meta_data( '_billing_address_1', $address_lines[0] );
		}

		if ( isset( $address['City'] ) ) {
			$order->update_meta_data( '_billing_city', $address['City'] );
		}

		if ( isset( $address['PostalCode'] ) ) {
			$order->update_meta_data( '_billing_postcode', $address['PostalCode'] );
		}

		if ( isset( $address['StateOrRegion'] ) ) {
			$order->update_meta_data( '_billing_state', $address['StateOrRegion'] );
		}

		if ( isset( $address['CountryCode'] ) ) {
			$order->update_meta_data( '_billing_country', $address['CountryCode'] );
		}

		$order->save();

		return true;
	}

	/**
	 * Handle the result of an async ipn order reference request.
	 * We need only to cover the change to Open status.
	 *
	 * URL: https://m.media-amazon.com/images/G/03/AMZNPayments/IntegrationGuide/AmazonPay_-_Order_Confirm_And_Omnichronous_Authorization_Including-IPN-Handler._V516642695_.svg
	 *
	 * @param object       $ipn_payload    IPN payload.
	 * @param int|WC_Order $order          Order object.
	 */
	public static function handle_async_ipn_order_reference_payload( $ipn_payload, $order ) {
		$order                 = is_int( $order ) ? wc_get_order( $order ) : $order;
		$order_reference_state = (string) $ipn_payload->OrderReference->OrderReferenceStatus->State; // phpcs:ignore WordPress.NamingConventions

		$order->update_meta_data( 'amazon_reference_state', $order_reference_state );
		$order->save();

		if ( 'open' === strtolower( $order_reference_state ) ) {
			// New Async Auth.
			$order->add_order_note( __( 'Async Authorized attempt.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			$amazon_reference_id = $order->get_meta( 'amazon_reference_id', true, 'edit' );
			do_action( 'wc_amazon_async_authorize', $order, $amazon_reference_id );
		}
	}

	/**
	 * Close order reference.
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order ID or order object.
	 *
	 * @return bool|WP_Error Return true when succeed. Otherwise WP_Error is returned
	 */
	public static function close_order_reference( $order ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$amazon_reference_id = $order->get_meta( 'amazon_reference_id', true, 'edit' );
		if ( ! $amazon_reference_id ) {
			return new WP_Error( 'order_missing_amazon_reference_id', __( 'Order missing Amazon reference ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$amazon_billing_agreement_id = $order->get_meta( 'amazon_billing_agreement_id', true, 'edit' );
		if ( $amazon_billing_agreement_id ) {
			// If it has a billing agreement Amazon close it on auth, no need to close the order.
			// https://developer.amazon.com/docs/amazon-pay-api/order-reference-states-and-reason-codes.html .
				/* translators: 1) Reference ID. */
			$order->add_order_note( sprintf( __( 'Order reference %s closed ', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_reference_id ) );
			return true;
		}

		$response = self::request(
			array(
				'Action'                 => 'CloseOrderReference',
				'AmazonOrderReferenceId' => $amazon_reference_id,
			)
		);

		// @codingStandardsIgnoreStart
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( isset( $response->Error->Message ) ) {
			$order->add_order_note( (string) $response->Error->Message );

			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		} else {
			$order->add_order_note( sprintf( __( 'Order reference %s closed ', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_reference_id ) );
		}
		// @codingStandardsIgnoreEnd

		return true;
	}

	/**
	 * Close authorization.
	 *
	 * @param int    $order_id                Order ID.
	 * @param string $amazon_authorization_id Authorization ID.
	 *
	 * @return bool|WP_Error True if succeed. Otherwise WP_Error is returned
	 */
	public static function close_authorization( $order_id, $amazon_authorization_id ) {
		$order = new WC_Order( $order_id );

		if ( 'amazon_payments_advanced' === wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			$response = self::request(
				array(
					'Action'                => 'CloseAuthorization',
					'AmazonAuthorizationId' => $amazon_authorization_id,
				)
			);

			// @codingStandardsIgnoreStart
			if ( is_wp_error( $response ) ) {
				$ret = $response;
			} elseif ( isset( $response->Error->Message ) ) {
				$order->add_order_note( (string) $response->Error->Message );
				$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
				$ret = new WP_Error( $code, (string) $response->Error->Message );
			} else {
				$order->delete_meta_data( 'amazon_authorization_id' );
				$order->save();

				$order->add_order_note( sprintf( __( 'Authorization closed (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_authorization_id ) );
				$ret = true;
			}
			// @codingStandardsIgnoreEnd
		} else {
			$ret = new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		return $ret;
	}

	/**
	 * Capture payment.
	 *
	 * @see https://payments.amazon.com/documentation/apireference/201752040
	 *
	 * @since 1.6.0
	 *
	 * @param int|WC_Order $order Order.
	 * @param array        $args  Whether to immediately capture or not.
	 *
	 * @return bool|object|WP_Error
	 */
	public static function capture( $order, $args = array() ) {
		$order = wc_get_order( $order );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', __( 'Invalid order ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		if ( 'amazon_payments_advanced' !== wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			return new WP_Error( 'invalid_order', __( 'Order is not paid via Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'amazon_authorization_id' => $order->get_meta( 'amazon_authorization_id', true, 'edit' ),
				'capture_now'             => false,
			)
		);

		if ( ! $args['amazon_authorization_id'] ) {
			return new WP_Error( 'order_missing_authorization_id', __( 'Order missing Amazon authorization ID', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$response = self::request( self::get_capture_request_args( $order, $args ) );

		// @codingStandardsIgnoreStart
		if ( isset( $response->Error->Message ) ) {
			$code = isset( $response->Error->Code ) ? (string) $response->Error->Code : 'amazon_error_response';
			return new WP_Error( $code, (string) $response->Error->Message );
		}
		// @codingStandardsIgnoreEnd

		return $response;
	}

	/**
	 * Get args to perform Capture request.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $args  Base args.
	 *
	 * @return array
	 */
	public static function get_capture_request_args( $order, $args ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

		return array(
			'Action'                     => 'Capture',
			'AmazonAuthorizationId'      => $args['amazon_authorization_id'],
			'CaptureReferenceId'         => $order_id . '-' . time(),
			'CaptureAmount.Amount'       => $order->get_total(),
			'CaptureAmount.CurrencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
		);
	}

	/**
	 * Capture payment
	 *
	 * @param int    $order_id                Order ID.
	 * @param string $amazon_authorization_id Optional Amazon authorization ID.
	 *                                        If not provided, value from order
	 *                                        meta will be used.
	 */
	public static function capture_payment( $order_id, $amazon_authorization_id = null ) {
		$response = self::capture(
			$order_id,
			array(
				'amazon_authorization_id' => $amazon_authorization_id,
			)
		);

		return self::handle_payment_capture_response( $response, $order_id );
	}

	/**
	 * Handle the result of a capture request.
	 *
	 * @since 1.6.0
	 *
	 * @param object       $response Response from self::request().
	 * @param int|WC_Order $order    Order ID or object.
	 *
	 * @return bool whether or not payment was captured.
	 */
	public static function handle_payment_capture_response( $response, $order ) {
		$order = wc_get_order( $order );

		if ( is_wp_error( $response ) ) {
			/* translators: 1) Reason. */
			$order->add_order_note( sprintf( __( 'Error: Unable to capture funds with Amazon Pay. Reason: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );

			return false;
		}

		return self::update_order_from_capture_response( $order, $response );
	}

	/**
	 * Update order from capture response.
	 *
	 * @since 1.6.0
	 *
	 * @param WC_Order $order Order object.
	 * @param Object   $response Response from self::request.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function update_order_from_capture_response( $order, $response ) {
		// @codingStandardsIgnoreStart
		$capture_id = (string) $response->CaptureResult->CaptureDetails->AmazonCaptureId;
		if ( ! $capture_id ) {
			return false;
		}
		// @codingStandardsIgnoreEnd

		/* translators: 1) Capture ID. */
		$order->add_order_note( sprintf( __( 'Capture Attempted (Capture ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $capture_id ) );

		$order->update_meta_data( 'amazon_capture_id', $capture_id );
		$order->save();

		$order->payment_complete();

		return true;
	}

	/**
	 * Refund a payment
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $capture_id Refund ID.
	 * @param float  $amount     Amount to refund.
	 * @param string $note       Refund note.
	 *
	 * @return bool Returns true if succeed.
	 */
	public static function refund_payment( $order_id, $capture_id, $amount, $note ) {
		$order = wc_get_order( $order_id );
		$ret   = false;

		if ( 'amazon_payments_advanced' === wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			if ( 'US' === WC()->countries->get_base_country() && $amount > $order->get_total() ) {
				/* translators: 1) Reason. */
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), __( 'Refund amount is greater than order total.', 'woocommerce-gateway-amazon-payments-advanced' ) ) );

				return false;
			} elseif ( $amount > min( ( $order->get_total() * 1.15 ), ( $order->get_total() + 75 ) ) ) {
				/* translators: 1) Reason. */
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), __( 'Refund amount is greater than the max refund amount.', 'woocommerce-gateway-amazon-payments-advanced' ) ) );

				return false;
			}

			$response = self::request(
				array(
					'Action'                    => 'Refund',
					'AmazonCaptureId'           => $capture_id,
					'RefundReferenceId'         => $order_id . '-' . time(),
					'RefundAmount.Amount'       => $amount,
					'RefundAmount.CurrencyCode' => wc_apa_get_order_prop( $order, 'order_currency' ),
					'SellerRefundNote'          => $note,
				)
			);

			// @codingStandardsIgnoreStart
			if ( is_wp_error( $response ) ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $response->get_error_message() ) );
			} elseif ( isset( $response->Error->Message ) ) {
				$order->add_order_note( sprintf( __( 'Unable to refund funds via Amazon Pay: %s', 'woocommerce-gateway-amazon-payments-advanced' ), (string) $response->Error->Message ) );
			} else {
				$refund_id = (string) $response->RefundResult->RefundDetails->AmazonRefundId;

				/* Translators: 1: refund amount, 2: refund note */
				$order->add_order_note( sprintf( __( 'Refunded %1$s (%2$s)', 'woocommerce-gateway-amazon-payments-advanced' ), wc_price( $amount ), $note ) );

				$order->update_meta_data( 'amazon_refund_id', $refund_id );
				$order->save();

				$ret = true;
			}
			// @codingStandardsIgnoreEnd
		}

		return $ret;
	}

	/**
	 * Get order ID from reference ID.
	 *
	 * @param string $reference_id Reference ID.
	 *
	 * @return int Order ID.
	 */
	public static function get_order_id_from_reference_id( $reference_id ) {
		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			function ( $query, $query_vars ) {
				if ( empty( $query_vars['amazon_reference_id'] ) ) {
					return $query;
				}

				if ( empty( $query['meta_query'] ) || ! is_array( $query['meta_query'] ) ) {
					$query['meta_query'] = array(); //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				}

				$query['meta_query'][] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'key'   => 'amazon_reference_id',
					'value' => esc_attr( $query_vars['amazon_reference_id'] ),
				);

				return $query;
			},
			10,
			2
		);

		$orders = wc_get_orders(
			array(
				'amazon_reference_id' => $reference_id,
				'limit'               => 1,
			)
		);

		if ( ! empty( $orders->orders[0] ) && $orders->orders[0] instanceof \WC_Order ) {
			return $orders->orders[0]->get_id();
		}

		return 0;
	}

	/**
	 * Send an email notification to the recipient in the woocommerce mail template.
	 *
	 * @param string $subject Subject.
	 * @param string $message Message to be sent.
	 * @param string $recipient Email address.
	 */
	public static function send_email_notification( $subject, $message, $recipient ) {
		$mailer  = WC()->mailer();
		$message = $mailer->wrap_message( $subject, $message );
		$mailer->send( $recipient, wp_strip_all_tags( $subject ), $message );
	}

	/**
	 * Return if subscription is shippable
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return boolean
	 */
	public static function maybe_subscription_is_shippable( WC_Order $order ) {

		if ( ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		$items = $order->get_items();
		if ( empty( $items ) ) {
			return false;
		}

		$order_shippable = false;
		foreach ( $items as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$product = $item->get_product();
				if ( $product && WC_Subscriptions_Product::is_subscription( $product ) && $product->needs_shipping() ) {
					$order_shippable = true;
					break;
				}
			}
		}

		return $order_shippable;
	}

}
