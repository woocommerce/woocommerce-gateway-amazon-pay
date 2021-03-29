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

		update_post_meta( $order_id, 'amazon_authorization_id', $auth_id );

		$authorization_status = self::get_auth_state_from_reponse( $ipn_payload );
		switch ( $authorization_status ) {
			case 'open':
				// Updating amazon_authorization_state
				update_post_meta( $order_id, 'amazon_authorization_state', 'Open' );
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
				$order->add_order_note( __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				break;
			case 'closed':
				update_post_meta( $order_id, 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
				update_post_meta( $order_id, 'amazon_authorization_state', $authorization_status );
				$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
				$order->payment_complete();
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				// Close order reference.
				self::close_order_reference( $order_id );
				break;
			case 'declined':
				$state_reason_code = self::get_auth_state_reason_code_from_response( $ipn_payload );
				if ( 'InvalidPaymentMethod' === $state_reason_code ) {
					// Soft Decline
					update_post_meta( $order_id, 'amazon_authorization_state', 'Suspended' );
					$order->add_order_note( sprintf( __( 'Amazon Order Suspended. Email sent to customer to change its payment method.', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
					$subject = __( 'Please update your payment information', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/soft-decline.php', array( 'order_id' => $order_id ), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					wc_apa()->log( 'EMAIL ' . $message );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'AmazonRejected' === $state_reason_code || 'ProcessingFailure' === $state_reason_code ) {
					// Hard decline
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
						// Hard Decline
						$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
						// Hard Decline client's email.
						$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
						$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
						self::send_email_notification( $subject, $message, $order->get_billing_email() );
						// Delete amazon_timed_out_transaction meta
						$order->delete_meta_data( $order_id, 'amazon_timed_out_transaction' );
						// Cancel amazon order.
						self::cancel_order_reference( $order_id );
					}
					$order->save();
				}

				break;
		}
		return $authorization_status;
	}

	/**
	 * Handle the result of an sync authorization request.
	 *
	 *
	 * @param object       $result         IPN payload.
	 * @param int|WC_Order $order          Order object.
	 *
	 * @return string Authorization status.
	 */
	public static function handle_synch_payment_authorization_payload( $response, $order, $auth_id = false ) {
		$order = is_int( $order ) ? wc_get_order( $order ) : $order;

		$order_id = wc_apa_get_order_prop( $order, 'id' );

		update_post_meta( $order_id, 'amazon_authorization_id', $auth_id );

		$authorization_status = self::get_auth_state_from_reponse( $response );
		wc_apa()->log( sprintf( 'Found authorization status of %s on pending synchronous payment', $authorization_status ) );

		switch ( $authorization_status ) {
			case 'open':
				// Updating amazon_authorization_state
				update_post_meta( $order_id, 'amazon_authorization_state', 'Open' );
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
				$order->add_order_note( __( 'Amazon order opened. Use the "Amazon Pay" box to authorize and/or capture payment. Authorized payments must be captured within 7 days.', 'woocommerce-gateway-amazon-payments-advanced' ) );
				break;
			case 'closed':
				update_post_meta( $order_id, 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );
				update_post_meta( $order_id, 'amazon_authorization_state', $authorization_status );
				$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
				$order->payment_complete();
				// Delete amazon_timed_out_transaction meta
				delete_post_meta( $order_id, 'amazon_timed_out_transaction' );
				// Close order reference.
				self::close_order_reference( $order_id );
				break;
			case 'declined':
				$state_reason_code = self::get_auth_state_reason_code_from_response( $response );
				if ( 'InvalidPaymentMethod' === $state_reason_code ) {
					// Soft Decline
					update_post_meta( $order_id, 'amazon_authorization_state', 'Suspended' );
					$order->add_order_note( sprintf( __( 'Amazon Order Suspended. Email sent to customer to change its payment method.', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
					$subject = __( 'Please update your payment information', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/soft-decline.php', array( 'order_id' => $order_id ), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					wc_apa()->log( 'EMAIL ' . $message );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'AmazonRejected' === $state_reason_code || 'ProcessingFailure' === $state_reason_code ) {
					// Hard decline
					$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
					// Hard Decline client's email.
					$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
					$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
					self::send_email_notification( $subject, $message, $order->get_billing_email() );
				} elseif ( 'TransactionTimedOut' === $state_reason_code ) {
					if ( ! $order->meta_exists( 'amazon_timed_out_times' ) ) {
						$order->update_meta_data( 'amazon_timed_out_times', 1 );
						// Hard Decline
						$order->update_status( 'cancelled', sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), $state_reason_code ) );
						// Hard Decline client's email.
						$subject = __( 'Please contact us about your order', 'woocommerce-gateway-amazon-payments-advanced' );
						$message = wc_get_template_html( 'emails/legacy/hard-decline.php', array(), '', plugin_dir_path( __DIR__ ) . '/templates/' );
						self::send_email_notification( $subject, $message, $order->get_billing_email() );
						// Delete amazon_timed_out_transaction meta
						$order->delete_meta_data( $order_id, 'amazon_timed_out_transaction' );
						// Cancel amazon order.
						self::cancel_order_reference( $order_id );
					}
					$order->save();
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
		return get_post_meta( $order_id, 'amazon_order_language', true );
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
				'amazon_reference_id' => get_post_meta( $order_id, 'amazon_reference_id', true ),
				'capture_now'         => false,
			)
		);

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
	public static function get_authorize_request_args( WC_Order $order, $args ) {
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

		$order_id = wc_apa_get_order_prop( $order, 'id' );
		$args     = wp_parse_args(
			$args,
			array(
				'amazon_reference_id' => get_post_meta( $order_id, 'amazon_billing_agreement_id', true ),
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
	public static function get_authorize_recurring_request_args( WC_Order $order, $args ) {
		$order_id = wc_apa_get_order_prop( $order, 'id' );

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

		if ( null !== $auth_method ) {
			_deprecated_function( 'WC_Amazon_Payments_Advanced_API_Legacy::handle_payment_authorization_response', '1.6.0', 'Parameter auth_method is not used anymore' );
		}

		if ( is_wp_error( $response ) ) {
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
	 * @param WC_Order $order       Order object.
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

		update_post_meta( $order_id, 'amazon_authorization_id', $auth_id );

		// This is true only on AuthorizeOnBillingAgreement, for the recurring payments.
		if ( $amazon_reference_id ) {
			update_post_meta( $order_id, 'amazon_reference_id', $amazon_reference_id );
		}
		self::update_order_billing_address( $order_id, self::get_billing_address_from_response( $response ) );

		$state = self::get_auth_state_from_reponse( $response );
		if ( 'declined' === $state ) {
			$order->add_order_note( sprintf( __( 'Order Declined with reason code: %s', 'woocommerce-gateway-amazon-payments-advanced' ), self::get_auth_state_reason_code_from_response( $response ) ) );
			// Payment was not authorized.
			return false;
		}

		if ( $capture_now ) {
			update_post_meta( $order_id, 'amazon_capture_id', str_replace( '-A', '-C', $auth_id ) );

			$order->add_order_note( sprintf( __( 'Captured (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), str_replace( '-A', '-C', $auth_id ) ) );
			$order->payment_complete();
		} else {
			$order->add_order_note( sprintf( __( 'Authorized (Auth ID: %s)', 'woocommerce-gateway-amazon-payments-advanced' ), $auth_id ) );
		}

		return true;
	}

	/**
	 * Cancels a previously confirmed order reference.
	 *
	 * @since 1.7.0
	 *
	 * @param WC_Order $order  WC Order object.
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

		$amazon_reference_id = get_post_meta( $order_id, 'amazon_reference_id', true );
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

}
