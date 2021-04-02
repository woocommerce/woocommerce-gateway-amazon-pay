<?php
/**
 * Common handling for IPNs.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * WC_Amazon_Payments_Advanced_IPN_Handler_Abstract
 */
abstract class WC_Amazon_Payments_Advanced_IPN_Handler_Abstract {
	/**
	 * Validate required keys that need to be present in message.
	 *
	 * @since 1.8.0
	 * @version 1.8.0
	 *
	 * @throws Exception Missing required key in the message.
	 *
	 * @param array $message Message to check.
	 * @param array $keys    Required keys to be present.
	 */
	protected function validate_required_keys( $message, $keys ) {
		foreach ( $keys as $key ) {
			$key_has_options = is_array( $key );
			if ( ! $key_has_options ) {
				$found = isset( $message[ $key ] );
			} else {
				$found = false;
				foreach ( $key as $option ) {
					if ( isset( $message[ $option ] ) ) {
						$found = true;
						break;
					}
				}
			}

			if ( ! $found ) {
				if ( $key_has_options ) {
					$key = $key[0];
				}

				throw new Exception( $key . ' is required to verify the SNS message.' );
			}
		}
	}
}
