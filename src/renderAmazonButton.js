/* global amazon_payments_advanced, amazon */

/**
 * Returns the settings needed to be provided to the amazon.Pay.renderButton().
 *
 * @param {string} buttonSettingsFlag Specifies the context of the rendering.
 * @param {string} checkoutConfig The checkoutConfig with which we will provide Amazon Pay.
 * @returns {object} The settings to provide the Amazon Pay Button with.
 */
const getButtonSettings = ( buttonSettingsFlag, checkoutConfig ) => {
	let obj = {
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
				: undefined
	};

	if ( 'express' === buttonSettingsFlag ) {
		obj.productType = amazon_payments_advanced.action;
		obj.createCheckoutSessionConfig = amazon_payments_advanced.create_checkout_session_config;
	} else {
		obj.productType = 'undefined' !== typeof checkoutConfig.payloadJSON.addressDetails ? 'PayAndShip' : 'PayOnly';
	}
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
export const renderAmazonButton = ( buttonId, buttonSettingsFlag, checkoutConfig ) => {
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

/**
 * Bounds a change Action to button identified by button_id.
 *
 * @param {string} button_id ID of button to bound Amazon Change event on.
 * @param {string} action Type of action to bound the button with.
 */
export const activateChange = ( button_id, action ) => {
	var button = document.getElementById( button_id );
	if ( 0 === button.length || button.getAttribute( 'data-wc_apa_chage_bind' ) === action ) {
		return;
	}

	button.setAttribute( 'data-wc_apa_chage_bind', action );
	button.addEventListener( 'click', function( e ) {
		e.preventDefault();
	} );


	amazon.Pay.bindChangeAction( '#' + button.getAttribute( 'id' ), {
		amazonCheckoutSessionId: amazon_payments_advanced.checkout_session_id,
		changeAction: action
	} );
};
