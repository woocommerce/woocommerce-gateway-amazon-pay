import { useEffect, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Returns an array of the sibling of the element that have the class className.
 * 
 * @param {node} element The element's whose siblings we are searching for.
 * @param {string} className Filter the siblings by this className.
 * @returns {array}
 */
export const getSiblings = ( element, className ) => {
	let siblings = [];
	let sibling = element.parentNode.firstChild;

	while ( sibling ) {
		if (
			sibling.nodeType === 1 &&
			sibling !== element &&
			sibling.classList.contains( className )
		) {
			siblings.push( sibling );
		}
		sibling = sibling.nextSibling;
	}

	return siblings;
};

/**
 * Returns the backend provided settings based on the name param.
 *
 * @param {string} name The settings to access.
 * @returns {object}|{null}
 */
export const getBlocksConfiguration = ( name ) => {
	const amazonPayServerData = wc.wcSettings.getSetting( name, null );

	if ( ! amazonPayServerData ) {
		throw new Error( 'Amazon Pay initialization data is not available' );
	}

	return amazonPayServerData;
};

/**
 * Label component
 *
 * @param {object} props Props from payment API.
 */
export const Label = ( { label, ...props } ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

/**
 * Returns a React Component.
 *
 * @param {object} param0  RenderedComponent and props
 * @returns {RenderedComponent}
 */
export const AmazonComponent = ( { RenderedComponent, ...props } ) => {
	const [ errorMessage, setErrorMessage ] = useState( '' );

	useEffect( () => {
		if ( errorMessage ) {
			throw new Error( errorMessage );
		}
	}, [ errorMessage ] );

	return <RenderedComponent { ...props } />;
};

/**
 * Returns the payment method's description.
 *
 * @returns {string}
 */
export const Content = ( { description, ...props } ) => {
	return decodeEntities( description );
};
