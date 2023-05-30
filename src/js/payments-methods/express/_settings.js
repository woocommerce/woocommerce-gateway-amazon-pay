/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './_constants';
import { getBlocksConfiguration } from '../../_utils';

export const settings = getBlocksConfiguration( PAYMENT_METHOD_NAME + '_data' );
