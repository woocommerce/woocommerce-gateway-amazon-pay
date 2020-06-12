<?php
/**
 * Amazon Pay Simple path registration flow handler.
 * Will handle automatic key exchange from registration process.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay Simple path registration flow handler.
 * https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/files/537729/PWA.Merchant.Registration.Integration.Guide_Version.1.0.pdf
 *
 * Class WC_Amazon_Payments_Advanced_Simple_Path_Handler
 */
class WC_Amazon_Payments_Advanced_Simple_Path_Handler {

	/**
	 * Our enpoint to receive encrypted keys
	 */
	const ENDPOINT_URL = 'wc_gateway_amazon_payments_advanced_simple_path';

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
		add_action( 'woocommerce_api_' . self::ENDPOINT_URL, array( $this, 'check_simple_path_request' ) );
		// Handles manual encrypted key exchange.
		add_action( 'wp_ajax_amazon_manual_exchange', array( $this, 'check_simple_path_ajax_request' ) );
	}


	public function check_simple_path_request() {
		wc_apa()->log( __METHOD__, 'Received Simple Path Registration Key Exchage request.' );

		$headers              = $this->get_all_headers();
		$registration_country = $this->get_country_origin_from_header( $headers );
		$raw_post_data        = $this->get_raw_post_data();
		parse_str( $raw_post_data, $body );

		try {
			$payload = array();
			if ( isset( $body['payload'] ) ) {
				$payload = json_decode( $body['payload'], true );
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
			$payload_verify = ( $payload ) ? clone $payload : false;

			// Validate JSON payload
			if ( ! isset( $payload->encryptedKey, $payload->encryptedPayload, $payload->iv, $payload->sigKeyID, $payload->signature ) ) {
				throw new Exception( esc_html__( 'Unable to import Amazon keys. Please verify your JSON format and values.', 'woocommerce-gateway-amazon-payments-advanced' ) );
			}

			// URL decode values
			foreach ( $payload as $key => $value ) {
				$payload->$key = rawurldecode( $value );
			}

			if ( $this->validate_public_key_from_payload( $payload, $payload_verify, $registration_country ) ) {
				$decrypted_key = $this->decrypt_encrypted_key_from_payload( $payload );
				$final_payload = $this->mcrypt_decrypt_alternative( $payload, $decrypted_key );
				$this->save_payload( $final_payload );
				$this->destroy_keys();

				header( 'Access-Control-Allow-Origin: ' . $this->get_origin_header( $headers ) );
				header( 'Access-Control-Allow-Methods: GET, POST' );
				header( 'Access-Control-Allow-Headers: Content-Type' );
				wp_send_json( array( 'result' => 'success' ), 200 );
			}
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
	public function check_simple_path_ajax_request() {
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
	 * 1. First validate that the request came from Amazon Pay.
	 *   a. Retrieve sigKeyId from the encrypted credential payload.
	 *   b. Make a HEAD or GET request to getpublickey
	 *   c. You will receive a public key in the response, which is used to validate the signature.
	 *   d. Base64decode the signature from the encrypted credential payload.
	 *   e. Use the verify function of the openSSL package (specifying the SHA256 algorithm), and pass
	 *      in the result of 1d (base 64 decoded signature) and 1c (public key).
	 *   f. Confirm that the verify command is successful.
	 *
	 * @param object $payload Original exchange payload.
	 * @param object $payload_verify Clone of original payload (withouth urldecode)
	 * @param string $registration_country Registration country.
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function validate_public_key_from_payload( $payload, $payload_verify, $registration_country ) {
		$amazon_public_key = $this->get_amazon_public_key( $registration_country, $payload->sigKeyID );

		// Use raw JSON (without signature or URL decode) as the data to verify signature
		unset( $payload_verify->signature );
		$payload_verify_json = json_encode( $payload_verify );

		if ( $amazon_public_key &&
			openssl_verify(
				$payload_verify_json,
				base64_decode( $payload->signature ),
				$this->key2pem( $amazon_public_key ),
				'SHA256'
			)
		) {
			return true;
		}
		throw new Exception( esc_html__( 'Request not coming from Amazon', 'woocommerce-gateway-amazon-payments-advanced' ) );
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
	protected function decrypt_encrypted_key_from_payload( $payload ) {
		$decrypted_key = null;

		openssl_private_decrypt(
			base64_decode( $payload->encryptedKey ),
			$decrypted_key,
			$this->get_private_key(),
			OPENSSL_PKCS1_OAEP_PADDING
		);

		return $decrypted_key;
	}

	/**
	 * @param $payload
	 * @param $decrypted_key
	 *
	 * @return string
	 */
	protected function mcrypt_decrypt_alternative( $payload, $decrypted_key ) {
		if ( function_exists( 'mcrypt_decrypt' ) ) {
			$final_payload = @mcrypt_decrypt(
				MCRYPT_RIJNDAEL_128,
				$decrypted_key,
				base64_decode( $payload->encryptedPayload ),
				MCRYPT_MODE_CBC,
				base64_decode( $payload->iv )
			);
		} else {
			$final_payload = openssl_decrypt( base64_decode( $payload->encryptedPayload ), 'AES-256-CBC', $decrypted_key, true, base64_decode( $payload->iv ) );
		}

		// Remove binary characters
		$final_payload = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $final_payload );
		return $final_payload;
	}

	/**
	 * API call to validate that the request ($sigkey_id) came from Amazon Pay.
	 * @param $region
	 * @param $sigkey_id
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_amazon_public_key( $region, $sigkey_id ) {
		if ( ! isset( WC_Amazon_Payments_Advanced_API::$get_public_keys_urls[ $region ] ) ) {
			throw new Exception( esc_html__( 'Invalid region.', 'woocommerce-gateway-amazon-payments-advanced' ) );
		}

		$url = add_query_arg(
			array(
				'sigkey_id' => $sigkey_id,
			),
			WC_Amazon_Payments_Advanced_API::$get_public_keys_urls[ $region ]
		);

		$response = wp_remote_get(
			$url,
			array(
				'maxredirects' => 2,
				'timeout'      => 30,
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$body = urldecode( $response['body'] );
			wc_apa()->log( __METHOD__, sprintf( 'Response: %s', $body ) );
			return $body;
		} else {
			wc_apa()->log( __METHOD__, sprintf( 'Error: %s', $response->get_error_message() ) );
			throw new Exception( $response->get_error_message() );
		}
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
		$settings['seller_id']                       = $values['merchant_id'];
		$settings['mws_access_key']                  = $values['access_key'];
		$settings['secret_key']                      = $values['secret_key'];
		$settings['app_client_id']                   = $values['client_id'];
		$settings['app_client_secret']               = $values['client_secret'];
		$settings['amazon_keys_setup_and_validated'] = 1;

		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
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
