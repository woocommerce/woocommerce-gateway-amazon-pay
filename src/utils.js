/**
 * Returns an array of the sibling of the elem that have the class className.
 * 
 * @param {node} elem The element's whose siblings we are searching for.
 * @param {string} className Filter the siblings by this className.
 * @returns {array}
 */
export const getSiblings = ( elem, className ) => {
	let siblings = [];
	let sibling = elem.parentNode.firstChild;

	while ( sibling ) {
		if (
			sibling.nodeType === 1 &&
			sibling !== elem &&
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
	const amzPayServerData = wc.wcSettings.getSetting( name, null );

	if ( ! amzPayServerData ) {
		throw new Error( 'Amazon Pay initialization data is not available' );
	}

	return amzPayServerData;
};