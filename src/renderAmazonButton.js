const getSiblings = ( elem, className ) => {
	// Setup siblings array and get the first sibling
	let siblings = [];
	let sibling = elem.parentNode.firstChild;

	// Loop through each sibling and push to the array
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

const getButtonSettings = ( buttonSettingsFlag, checkoutConfig ) => {
	const obj = {
		// set checkout environment
		merchantId: amazon_payments_advanced.merchant_id,
		ledgerCurrency: amazon_payments_advanced.ledger_currency,
		sandbox: amazon_payments_advanced.sandbox === '1' ? true : false,
		// customize the buyer experience
		placement: amazon_payments_advanced.placement,
		buttonColor: amazon_payments_advanced.button_color,
		checkoutLanguage:
			amazon_payments_advanced.button_language !== ''
				? amazon_payments_advanced.button_language.replace( '-', '_' )
				: undefined,
		productType:
			'undefined' !== typeof checkoutConfig.payloadJSON.addressDetails
				? 'PayAndShip'
				: 'PayOnly',
	};
	return obj;
};

const renderAmazonButton = ( btnId, buttonSettingsFlag, checkoutConfig ) => {
	let amazonPayBtn = null;
	const btns = document.querySelectorAll( btnId );
	for ( const btn of btns ) {
		const thisButton = btn;
		const thisId = '#' + thisButton.getAttribute( 'id' );
		const button_settings = getButtonSettings(
			buttonSettingsFlag,
			checkoutConfig
		);
		amazonPayBtn = amazon.Pay.renderButton( thisId, button_settings );
	}
	return amazonPayBtn;
};

export const renderAndInitAmazonCheckout = ( btnId, flag, checkoutConfig ) => {
	checkoutConfig = JSON.parse( checkoutConfig );
	var amazonClassicBtn = renderAmazonButton( btnId, flag, checkoutConfig );
	checkoutConfig.payloadJSON = JSON.stringify( checkoutConfig.payloadJSON );
	if ( null !== amazonClassicBtn ) {
		amazonClassicBtn.initCheckout( {
			createCheckoutSessionConfig: checkoutConfig,
		} );
	}
};
