/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { getBlocksConfiguration } from '../../utils';

export const settings = getBlocksConfiguration( PAYMENT_METHOD_NAME + '_data' );
