<?php
/**
 * Amazon Pay Merchant Onboarding flow handler.
 * Will handle automatic key exchange from registration process.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay Merchant Onboarding flow handler.
 * https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/files/537729/PWA.Merchant.Registration.Integration.Guide_Version.1.0.pdf TODO: Edit this file.
 *
 * Class WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler
 */
class WC_Amazon_Payments_Advanced_Merchant_Onboarding_Handler {

	/**
	 * Our enpoint to receive encrypted keys
	 */
	const ENDPOINT_URL = 'wc_gateway_amazon_payments_advanced_merchant_onboarding';

	const DIGEST_ALG       = 'sha1';
	const PRIVATE_KEY_BITS = 2048;
	const PRIVATE_KEY_TYPE = 'OPENSSL_KEYTYPE_RSA';

	const KEYS_OPTION_PUBLIC_KEY  = 'woocommerce_amazon_payments_advanced_public_key';
	const KEYS_OPTION_PRIVATE_KEY = 'woocommerce_amazon_payments_advanced_private_key';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Handles Simple Path request from Amazon.
		add_action( 'woocommerce_api_' . self::ENDPOINT_URL, array( $this, 'check_onboarding_request' ) );
		// Handles manual encrypted key exchange.
		add_action( 'wp_ajax_amazon_manual_exchange', array( $this, 'check_onboarding_ajax_request' ) );
	}

	/**
	 * Check Onboarding Request.
	 */
	public function check_onboarding_request() {
		wc_apa()->log( __METHOD__, 'Received Onboarding Key Exchage request.' );

		$headers              = $this->get_all_headers();
		$registration_country = $this->get_country_origin_from_header( $headers );
		$raw_post_data        = $this->get_raw_post_data();
		parse_str( $raw_post_data, $body );

		try {
			$payload = array();
			if ( isset( $body['payload'] ) ) {
				$payload = json_decode( $body['payload'], true );
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$payload = (object) filter_var_array(
				$payload,
				array(
					'merchantId'  => FILTER_SANITIZE_STRING,
					'storeId'     => FILTER_SANITIZE_STRING,
					'publicKeyId' => FILTER_SANITIZE_STRING,
				),
				true
			);
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( ! isset( $payload->merchantId, $payload->storeId, $payload->publicKeyId ) ) {
				throw new Exception( esc_html__( 'Unable to import Amazon keys. Please verify your JSON format and values.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$public_key_id = rawurldecode( $payload->publicKeyId );
			$decrypted_key = $this->decrypt_encrypted_public_key_id( $public_key_id );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$payload->publicKeyId = $decrypted_key;

			$this->save_payload( $payload );
			wc_apa()->update_migration_status();
			header( 'Access-Control-Allow-Origin: ' . $this->get_origin_header( $headers ) );
			header( 'Access-Control-Allow-Methods: GET, POST' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
			wp_send_json( array( 'result' => 'success' ), 200 );
		} catch ( Exception $e ) {
			wc_apa()->log( __METHOD__, 'Failed to handle automatic key exchange request: ' . $e->getMessage() );
			wp_send_json(
				array(
					'result' => 'error',
					'message' => esc_html__( 'Bad request.', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(),
				),
				400
			);
		}
	}

	/**
	 * Get encrypted payload from manual copy, decrypt it and return/save it.
	 */
	public function check_onboarding_ajax_request() {
		check_ajax_referer( 'amazon_pay_manual_exchange', 'nonce' );

		try {
			$payload = array();
			if ( isset( $_POST['data'] ) ) {
				$payload = $_POST['data'];
			}
			$payload = (object) filter_var_array(
				$payload,
				array(
					'encryptedKey' => FILTER_SANITIZE_STRING,
					'encryptedPayload' => FILTER_SANITIZE_STRING,
					'iv' => FILTER_SANITIZE_STRING,
					'sigKeyID' => FILTER_SANITIZE_STRING,
					'signature' => FILTER_SANITIZE_STRING,
				),
				true
			);
			$payment_region = isset( $_POST['region'] ) ? filter_input( INPUT_POST, 'region', FILTER_SANITIZE_STRING ) : 'us';

			// Validate payload
			if ( ! isset( $payload->encryptedKey, $payload->encryptedPayload, $payload->iv, $payload->sigKeyID, $payload->signature ) ) {
				throw new Exception( esc_html__( 'Incomplete payload.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			$payload_verify = ( $payload ) ? clone $payload : false;
			// URL decode values
			foreach ( $payload as $key => $value ) {
				$payload->$key = rawurldecode( $value );
			}

			if ( $this->validate_public_key_from_payload( $payload, $payload_verify, $payment_region ) ) {
				$decrypted_key = $this->decrypt_encrypted_key_from_payload( $payload );
				$final_payload = $this->mcrypt_decrypt_alternative( $payload, $decrypted_key );

				if ( ! empty( $final_payload ) ) {
					$this->save_payload( $final_payload );
					$this->destroy_keys();
					wp_send_json_success( $final_payload, 200 );
				} else {
					wc_apa()->log( __METHOD__, 'Failed to handle manual key exchange request: Empty message after decrypt' );
					wp_send_json_error(
						new WP_Error( 'invalid_payload', esc_html__( 'Bad request. Invalid Payload.', 'woocommerce-gateway-amazon-payments-advanced' ) ),
						400
					);
				}
			}
		} catch ( Exception $e ) {
			wc_apa()->log( __METHOD__, 'Failed to handle manual key exchange request: ' . $e->getMessage() );
			wp_send_json_error(
				new WP_Error( 'invalid_payload', esc_html__( 'Bad request.', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage() ),
				400
			);
		}

	}

	/**
	 * Decrypt the encryptedKey value from the encrypted credential payload.
	 * This gives you the key that was used to encrypt the encryptedPayload value.
	 *   a. Base64decode the encryptedKey value from the encrypted credential payload.
	 *   b. Use the private decrypt function of the openSSL package (specifying the OPENSSL_PKCS1_OAEP_PADDING algorithm),
	 *      to decrypt the result of 2a, passing in the private key that was generated on opening the workflow.
	 *
	 * @param $payload
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function decrypt_encrypted_public_key_id( $public_key_id ) {
		$decrypted_key = null;

		$res = openssl_private_decrypt(
			base64_decode( $public_key_id ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$decrypted_key,
			$this->get_private_key()
		);

		return $decrypted_key;
	}

	/**
	 * Get Simple path registration URL.
	 *
	 * @return string Notify URL.
	 */
	public function get_simple_path_registration_url() {
		return WC()->api_request_url( self::ENDPOINT_URL );
	}

	/**
	 * Retrieves the raw request data (body).
	 *
	 * `$HTTP_RAW_POST_DATA` is deprecated in PHP 5.6 and removed in PHP 5.7,
	 * it's used here for server that has issue with reading `php://input`
	 * stream.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @return string Raw request data.
	 */
	protected function get_raw_post_data() {
		global $HTTP_RAW_POST_DATA;

		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
		}

		return $HTTP_RAW_POST_DATA;
	}

	/**
	 * The public key generated by the Ecommerce provider or plugin, which is used to encrypt the contents of the key
	 * exchange package when supported by the registration workflow. This is an ephemeral 2048 bit RSA public key.
	 *
	 * @param bool $public Returns public or private key.
	 * @return mixed
	 * @throws Exception
	 */
	protected function generate_keys( $public = false ) {

		if ( ! function_exists( 'openssl_pkey_new' ) || ! function_exists( 'openssl_verify' ) ) {
			throw new Exception( esc_html__( 'OpenSSL extension is not available in your server.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}
		$keys = openssl_pkey_new(
			array(
				'digest_alg'       => self::DIGEST_ALG,
				'private_key_bits' => self::PRIVATE_KEY_BITS,
				'private_key_type' => self::PRIVATE_KEY_TYPE,
			)
		);

		$public_key = openssl_pkey_get_details( $keys );
		update_option( self::KEYS_OPTION_PUBLIC_KEY, $public_key['key'] );
		openssl_pkey_export( $keys, $private_key );
		update_option( self::KEYS_OPTION_PRIVATE_KEY, $private_key );

		return ( $public ) ? $public_key['key'] : $private_key;
	}

	/**
	 * Destroy public/private key generated for keys exchange.
	 */
	protected function destroy_keys() {
		delete_option( self::KEYS_OPTION_PUBLIC_KEY );
		delete_option( self::KEYS_OPTION_PRIVATE_KEY );
	}

	/**
	 * Gets amazon gateway settings and update them with the new credentials from exchange.
	 *
	 * @param array $payload
	 */
	protected function save_payload( $payload ) {
		$values = json_decode( $payload, true );

		$settings                                    = WC_Amazon_Payments_Advanced_API::get_settings();
		$settings['merchant_id']                     = $values['merchantId'];
		$settings['store_id']                        = $values['storeId'];
		$settings['public_key_id']                   = $values['publicKeyId'];
		$settings['amazon_keys_setup_and_validated'] = 1;

		update_option( 'woocommerce_amazon_payments_advanced_settings_v2', $settings );
	}

	/**
	 * Convert key to PEM format for openssl functions
	 *
	 * @param $key
	 *
	 * @return string
	 */
	public function key2pem( $key ) {
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( $key, 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	/**
	 * Return RSA public key.
	 *
	 * @param bool $pem_format
	 * @param bool $reset
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_public_key( $pem_format = false, $reset = false ) {
		$public_key = get_option( self::KEYS_OPTION_PUBLIC_KEY, false );

		if ( ( ! $public_key ) || $reset ) {
			$public_key = $this->generate_keys( true );
		}

		if ( ! $pem_format ) {
			$public_key = str_replace( array( '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n" ), array( '', '', '' ), $public_key );
		}

		return $public_key;
	}

	/**
	 * Return RSA private key.
	 *
	 * @param bool $reset
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_private_key( $reset = false ) {
		$private_key = get_option( self::KEYS_OPTION_PRIVATE_KEY, false );

		if ( ( ! $private_key ) || $reset ) {
			$private_key = $this->generate_keys( false );
		}

		return $private_key;
	}

	/**
	 * From Incoming Exchange message we need to know which country belong the registration.
	 *
	 * @param $headers
	 *
	 * @return string
	 */
	protected function get_country_origin_from_header( $headers ) {
		switch ( $this->get_origin_header( $headers ) ) {
			case 'https://payments.amazon.com':
				return 'us';
			case 'https://payments-eu.amazon.com':
				return 'eu';
			default:
				return 'us';
		}
	}

	/**
	 * getallheaders is only available for apache, we need a fallback in case of nginx or others,
	 * http://php.net/manual/es/function.getallheaders.php
	 * @return array|false
	 */
	private function get_all_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = [];
			foreach ( $_SERVER as $name => $value ) {
				if ( substr( $name, 0, 5 ) == 'HTTP_' ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
				}
			}
			return $headers;

		} else {
			return getallheaders();
		}
	}

	/**
	 * Apache uses capital, nginx uses not capitalised.
	 * @param $headers
	 *
	 * @return string
	 */
	private function get_origin_header( $headers ) {
		return ( $headers['Origin'] ) ? $headers['Origin'] : $headers['origin'];
	}

}
