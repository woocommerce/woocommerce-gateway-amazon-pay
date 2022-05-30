/**
 * External dependencies
 */
const { registerPaymentMethod } = wc.wcBlocksRegistry;

/**
 * Internal dependencies
 */
import { amazonPayPaymentMethod } from './payments-methods/classic/';

/**
 * Registers Amazon Pay "Classic" as a Payment Method in the Checkout Block of WooCommerce Blocks.
 */
registerPaymentMethod( amazonPayPaymentMethod );
