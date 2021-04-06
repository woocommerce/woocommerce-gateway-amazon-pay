<?php
/**
 * Amazon Pay Merchant Onboarding flow handler.
 * Will handle automatic key exchange from registration process.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Pay Merchant Onboarding flow handler.
 * https://github.com/woocommerce/woocommerce-gateway-amazon-payments-advanced/files/537729/PWA.Merchant.Registration.Integration.Guide_Version.1.0.pdf
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

	const KEYS_OPTION_PRIVATE_KEY       = 'woocommerce_amazon_payments_advanced_private_key';
	const KEYS_OPTION_TEMP_PRIVATE_KEYS = 'woocommerce_amazon_payments_advanced_temp_private_keys';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Handles Simple Path request from Amazon.
		add_action( 'woocommerce_api_' . self::ENDPOINT_URL, array( $this, 'check_onboarding_request' ) );
		add_action( 'wc_amazon_keys_setup_and_validated', array( __CLASS__, 'update_migration_status' ) );
	}

	/**
	 * Check Onboarding Request.
	 *
	 * @throws Exception On errors.
	 */
	public function check_onboarding_request() {
		wc_apa()->log( 'Received Onboarding Key Exchage request.' );

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
			header( 'Access-Control-Allow-Origin: ' . $this->get_origin_header( $headers ) );
			header( 'Access-Control-Allow-Methods: GET, POST' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
			wp_send_json( array( 'result' => 'success' ), 200 );
		} catch ( Exception $e ) {
			wc_apa()->log( 'Failed to handle automatic key exchange request: ' . $e->getMessage() );
			wp_send_json(
				array(
					'result'  => 'error',
					'message' => esc_html__( 'Bad request.', 'woocommerce-gateway-amazon-payments-advanced' ) . ' ' . $e->getMessage(),
				),
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
	 * @param string $public_key_id Public Key ID.
	 *
	 * @return string|bool
	 * @throws Exception On Errors.
	 */
	protected function decrypt_encrypted_public_key_id( $public_key_id ) {
		$decrypted_key = null;

		$private_keys = $this->get_temp_private_keys();
		$private_keys = array_reverse( $private_keys ); // it's more likely that the last one is the one that works.

		$found = false;

		foreach ( $private_keys as $private_key ) {
			$res = openssl_private_decrypt(
				base64_decode( $public_key_id ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$decrypted_key,
				$private_key
			);

			if ( $res ) {
				$found = $private_key;
				break;
			}
		}

		if ( ! $found ) {
			return false;
		}

		update_option( self::KEYS_OPTION_PRIVATE_KEY, $found );
		delete_option( self::KEYS_OPTION_TEMP_PRIVATE_KEYS );

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
		// phpcs:disable PHPCompatibility.Variables.RemovedPredefinedGlobalVariables.http_raw_post_dataDeprecatedRemoved
		global $HTTP_RAW_POST_DATA;

		if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
			$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return $HTTP_RAW_POST_DATA;
		// phpcs:enable PHPCompatibility.Variables.RemovedPredefinedGlobalVariables.http_raw_post_dataDeprecatedRemoved
	}

	/**
	 * Return temporary private keys
	 *
	 * @return array
	 */
	protected function get_temp_private_keys() {
		$temps = get_option( self::KEYS_OPTION_TEMP_PRIVATE_KEYS, array() );
		if ( ! is_array( $temps ) ) {
			$temps = array();
		}
		return $temps;
	}

	/**
	 * The public key generated by the Ecommerce provider or plugin, which is used to encrypt the contents of the key
	 * exchange package when supported by the registration workflow. This is an ephemeral 2048 bit RSA public key.
	 *
	 * @param bool $public Returns public or private key.
	 * @return mixed
	 * @throws Exception On Errors.
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
		openssl_pkey_export( $keys, $private_key );

		$temps = $this->get_temp_private_keys();

		$temps[] = $private_key;
		update_option( self::KEYS_OPTION_TEMP_PRIVATE_KEYS, $temps );

		return ( $public ) ? $public_key['key'] : $private_key;
	}

	/**
	 * Destroy public/private key generated for keys exchange.
	 */
	public static function destroy_keys() {
		delete_option( self::KEYS_OPTION_PRIVATE_KEY );
	}

	/**
	 * Gets amazon gateway settings and update them with the new credentials from exchange.
	 *
	 * @param object $payload Payload received from Amazon.
	 */
	protected function save_payload( $payload ) {
		$settings = WC_Amazon_Payments_Advanced_API::get_settings();

		$settings['merchant_id']                     = $payload->merchantId; // phpcs:ignore WordPress.NamingConventions
		$settings['store_id']                        = $payload->storeId; // phpcs:ignore WordPress.NamingConventions
		$settings['public_key_id']                   = $payload->publicKeyId; // phpcs:ignore WordPress.NamingConventions
		$settings['amazon_keys_setup_and_validated'] = 1;
		update_option( 'woocommerce_amazon_payments_advanced_settings', $settings );
		update_option( 'woocommerce_amazon_payments_advanced_saved_payload', true );
	}

	/**
	 * Convert key to PEM format for openssl functions
	 *
	 * @param string $key Key data.
	 *
	 * @return string
	 */
	public function key2pem( $key ) {
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( $key, 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	/**
	 * Return RSA public key.
	 *
	 * @param bool $pem_format Wether to return the Key in PEM format.
	 * @param bool $reset Wether to reset the private key.
	 *
	 * @return string
	 * @throws Exception On Errors.
	 */
	public function get_public_key( $pem_format = false, $reset = false ) {
		$priv_key = $this->get_private_key( $reset );

		$priv_key = openssl_pkey_get_private( $priv_key );

		$public_key = openssl_pkey_get_details( $priv_key );

		$public_key = $public_key['key'];

		if ( ! $pem_format ) {
			$public_key = str_replace( array( '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n" ), array( '', '', '' ), $public_key );
		}

		return $public_key;
	}

	/**
	 * Return RSA private key.
	 *
	 * @param bool $reset Wether to force reset the private key.
	 *
	 * @return string
	 * @throws Exception On Errors.
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
	 * @param array $headers Headers received.
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
	 * Check because getallheaders is only available for apache, we need a fallback in case of nginx or others,
	 * http://php.net/manual/es/function.getallheaders.php
	 *
	 * @return array
	 */
	private function get_all_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = array();
			foreach ( $_SERVER as $name => $value ) {
				if ( substr( $name, 0, 5 ) === 'HTTP_' ) {
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
	 *
	 * @param array $headers Headers received.
	 *
	 * @return string
	 */
	private function get_origin_header( $headers ) {
		return ( $headers['Origin'] ) ? $headers['Origin'] : $headers['origin'];
	}

	/**
	 * Get API Migration status.
	 */
	public static function get_migration_status() {
		$status      = get_option( 'amazon_api_version' );
		$old_install = version_compare( get_option( 'woocommerce_amazon_payments_new_install' ), '2.0.0', '>=' );
		return 'V2' === $status || $old_install ? true : false;
	}

	/**
	 * Update migration status update
	 */
	public static function update_migration_status() {
		update_option( 'amazon_api_version', 'V2' );
	}

	/**
	 * Downgrade migration status update
	 */
	public static function delete_migration_status() {
		delete_option( 'amazon_api_version' );
	}

}
