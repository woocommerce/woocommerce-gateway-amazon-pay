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

/**
 * Renders an Amazon Pay Button on elements identified by btnId.
 *
 * @param {string} btnId Selector on where the Amazon Pay button will be rendered on.
 * @param {string} buttonSettingsFlag Specifies the context of the rendering.
 * @param {string} checkoutConfig The checkoutConfig with which we will provide Amazon Pay.
 * @returns {object} The Amazon Pay rendered button.
 */
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

/**
 * Renders and inits the Amazon checkout Process on elements identified by btnId.
 *
 * @param {string} btnId Selector on where the Amazon Pay button will be rendered on.
 * @param {string} flag Specifies the context of the rendering.
 * @param {string} checkoutConfig The checkoutConfig with which we will provide Amazon Pay on init.
 */
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
