/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { renderAmazonButton } from '../../renderAmazonButton';

/**
 * Returns a react component and also sets an observer for the onCheckoutAfterProcessingWithSuccess event.
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

const calculateEstimatedOrderAmount = ( props ) => {
	const { billing } = props;
	const { currency } = billing;

	const stringCartTotal     = String( billing.cartTotal.value );
	const cartTotalLength     = stringCartTotal.length;
	const decimals            = currency.minorUnit;
	const checkOutValue       = stringCartTotal.slice( 0, cartTotalLength - decimals ) + '.' + stringCartTotal.slice( cartTotalLength - decimals );

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

	const [id, setId] = useState( estimatedOrderAmount ? estimatedOrderAmount.amount + estimatedOrderAmount.currencyCode : '0' );

	useEffect( () => {
		setId( estimatedOrderAmount ? estimatedOrderAmount.amount + estimatedOrderAmount.currencyCode : '0' );
	}, [
		estimatedOrderAmount
	] );

	return <AmazonPayExpressBtn key={id} { ...props } />;
};
