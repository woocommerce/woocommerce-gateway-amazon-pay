/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { renderAmazonButton } from '../../_renderAmazonButton';

/**
 * Returns a react component and also sets an observer for the onCheckoutSuccess event.
 * @param {object} props
 * @returns React component
 */
const AmazonPayExpressBtn = ( props ) => {
	const estimatedOrderAmount = calculateEstimatedOrderAmount( props );

	useEffect( () => {
		renderAmazonButton( '#pay_with_amazon_express', 'express', null, estimatedOrderAmount );
	}, [] );

	return <div id="pay_with_amazon_express" />;
};

/**
 * Returns the estimated order amount button attribute.
 * @param {object} props
 * @returns {object}|null Estimated order amount button attribute.
 */
const calculateEstimatedOrderAmount = ( props ) => {
	const { billing } = props;
	const { currency } = billing;

	/**
	 * Get how many charactes are present in the cart's total value.
	 * So if the checkout value was 23.76,
	 * billing.cartTotal.value would be equal to 2376
	 * cartTotalLength would be equal to 4 and
	 * currency.minorUnit would be 2.
	 */
	const stringCartTotal = String( billing.cartTotal.value );
	const cartTotalLength = stringCartTotal.length;

	// Get how many decimals is the store configured to use.
	const decimals = currency.minorUnit;

	/**
	 * Since we know the total length of the checkout value and the length of the decimals,
	 * we can build the checkout value in the format expected by Amazon Pay.
	 */
	const checkOutValue = stringCartTotal.slice( 0, cartTotalLength - decimals ) + '.' + stringCartTotal.slice( cartTotalLength - decimals );

	// If the number of decimals are more than the total number of chars in the checkout value. Something has gone wrong, so we return null.
	return cartTotalLength < decimals ? null : { amount: checkOutValue, currencyCode: currency.code };
};

/**
 * Returns the Components that will be used by Amazon Pay "Express".
 *
 * @param {object} props
 * @returns React Component
 */
export const AmazonExpressContent = ( props ) => {
	const estimatedOrderAmount = calculateEstimatedOrderAmount( props );
	const key = estimatedOrderAmount ? `${ estimatedOrderAmount.amount }${ estimatedOrderAmount.currencyCode }` : '0';
	const [id, setId] = useState( key );

	useEffect( () => {
		setId( key );
	}, [
		key
	] );

	return <AmazonPayExpressBtn key={id} { ...props } />;
};
