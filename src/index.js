/**
 * Internal dependencies
 */
import {amazonPayPaymentMethod} from './payments-methods/classic/';

/**
 * External dependencies
 */
const { registerPaymentMethod } = wc.wcBlocksRegistry;

registerPaymentMethod( amazonPayPaymentMethod );
