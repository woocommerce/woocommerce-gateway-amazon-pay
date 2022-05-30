/* global amazon_payments_advanced, amazon */

/**
 * Returns the settings needed to be provided to the amazon.Pay.renderButton().
 *
 * @param {string} buttonSettingsFlag Specifies the context of the rendering.
 * @param {string} checkoutConfig The checkoutConfig with which we will provide Amazon Pay.
 * @returns {object} The settings to provide the Amazon Pay Button with.
 */
const getButtonSettings = ( buttonSettingsFlag, checkoutConfig ) => {
	const obj = {
		// set checkout environment
		merchantId: amazon_payments_advanced.merchant_id,
		ledgerCurrency: amazon_payments_advanced.ledger_currency,
		sandbox: amazon_payments_advanced.sandbox === '1',
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

/**
 * Renders an Amazon Pay Button on elements identified by buttonId.
 *
 * @param {string} buttonId Selector on where the Amazon Pay button will be rendered on.
 * @param {string} buttonSettingsFlag Specifies the context of the rendering.
 * @param {string} checkoutConfig The checkoutConfig with which we will provide Amazon Pay.
 * @returns {object} The Amazon Pay rendered button.
 */
const renderAmazonButton = ( buttonId, buttonSettingsFlag, checkoutConfig ) => {
	let amazonPayButton = null;
	const buttons = document.querySelectorAll( buttonId );
	for ( const button of buttons ) {
		const thisId = '#' + button.getAttribute( 'id' );
		const buttonSettings = getButtonSettings(
			buttonSettingsFlag,
			checkoutConfig
		);
		amazonPayButton = amazon.Pay.renderButton( thisId, buttonSettings );
	}
	return amazonPayButton;
};

/**
 * Renders and inits the Amazon checkout Process on elements identified by buttonId.
 *
 * @param {string} buttonId Selector on where the Amazon Pay button will be rendered on.
 * @param {string} flag Specifies the context of the rendering.
 * @param {string} checkoutConfig The checkoutConfig with which we will provide Amazon Pay on init.
 */
export const renderAndInitAmazonCheckout = ( buttonId, flag, checkoutConfig ) => {
	checkoutConfig = JSON.parse( checkoutConfig );
	const amazonClassicButton = renderAmazonButton( buttonId, flag, checkoutConfig );
	if ( null !== amazonClassicButton ) {
		checkoutConfig.payloadJSON = JSON.stringify( checkoutConfig.payloadJSON );
		amazonClassicButton.initCheckout( {
			createCheckoutSessionConfig: checkoutConfig,
		} );
	}
};
