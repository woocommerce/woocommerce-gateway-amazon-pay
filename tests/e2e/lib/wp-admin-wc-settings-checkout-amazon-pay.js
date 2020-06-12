/**
 * External dependencies
 */
import { By } from 'selenium-webdriver';
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { WPAdminWCSettings } from 'wc-e2e-page-objects';

const ENABLE_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_enabled' );
const SELLER_ID_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_seller_id' );
const MWS_ACCESS_KEY_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_mws_access_key' );
const MWS_SECRET_KEY_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_secret_key' );
const PAYMENT_REGION_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_payment_region' );
const USE_LOGIN_WITH_AMAZON_APP_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_enable_login_app' );
const APP_CLIENT_ID_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_app_client_id' );
const APP_CLIENT_SECRET_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_app_client_secret' );
const USE_SANDBOX_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_sandbox' );
const PAYMENT_CAPTURE_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_payment_capture' );
const CART_LOGIN_BUTTON_DISPLAY_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_cart_button_display_mode' );
const BUTTON_TYPE_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_button_type' );
const BUTTON_SIZE_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_button_size' );
const BUTTON_COLOR_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_button_color' );
const BUTTON_LANGUAGE_SELECTOR = By.css( '#woocommerce_amazon_payments_advanced_button_language' );
const HIDE_STANDARD_CHECKOUT_BUTTON = By.css( '#woocommerce_amazon_payments_advanced_hide_standard_checkout_button' );

export default class WPAdminWCSettingsCheckoutAmazonPay extends WPAdminWCSettings {
	constructor( driver, args = {} ) {
		super( driver, args );
	}

	checkEnable() {
		return helper.setCheckbox( this.driver, ENABLE_SELECTOR );
	}

	uncheckEnable() {
		return helper.unsetCheckbox( this.driver, ENABLE_SELECTOR );
	}

	setSellerId( sellerId ) {
		return helper.setWhenSettable( this.driver, SELLER_ID_SELECTOR, sellerId );
	}

	setMwsAccessKey( accessKey ) {
		return helper.setWhenSettable( this.driver, MWS_ACCESS_KEY_SELECTOR, accessKey );
	}

	setMwsSecretKey( secretKey ) {
		return helper.setWhenSettable( this.driver, MWS_SECRET_KEY_SELECTOR, secretKey );
	}

	selectPaymentRegion( region ) {
		return helper.selectOption( this.driver, PAYMENT_REGION_SELECTOR, region );
	}

	checkUseLoginWithAmazonApp() {
		helper.unsetCheckbox( this.driver, USE_LOGIN_WITH_AMAZON_APP_SELECTOR );
		return helper.setCheckbox( this.driver, USE_LOGIN_WITH_AMAZON_APP_SELECTOR );
	}

	uncheckUseLoginWithAmazonApp() {
		helper.setCheckbox( this.driver, USE_LOGIN_WITH_AMAZON_APP_SELECTOR );
		return helper.unsetCheckbox( this.driver, USE_LOGIN_WITH_AMAZON_APP_SELECTOR );
	}

	setAppClientId( clientId ) {
		return helper.setWhenSettable( this.driver, APP_CLIENT_ID_SELECTOR, clientId );
	}

	setAppClientSecret( clientSecret ) {
		return helper.setWhenSettable( this.driver, APP_CLIENT_SECRET_SELECTOR, clientSecret );
	}

	checkUseSandbox() {
		helper.unsetCheckbox( this.driver, USE_SANDBOX_SELECTOR );
		return helper.setCheckbox( this.driver, USE_SANDBOX_SELECTOR );
	}

	uncheckUseSandbox() {
		helper.setCheckbox( this.driver, USE_SANDBOX_SELECTOR );
		return helper.unsetCheckbox( this.driver, USE_SANDBOX_SELECTOR );
	}

	selectPaymentCapture( option ) {
		return helper.selectOption( this.driver, PAYMENT_CAPTURE_SELECTOR, option );
	}

	selectCartLoginButtonDisplay( option ) {
		return helper.selectOption( this.driver, CART_LOGIN_BUTTON_DISPLAY_SELECTOR, option );
	}

	selectButtonType( type ) {
		return helper.selectOption( this.driver, BUTTON_TYPE_SELECTOR, type );
	}

	selectButtonSize( size ) {
		return helper.selectOption( this.driver, BUTTON_SIZE_SELECTOR, size );
	}

	selectButtonColor( color ) {
		return helper.selectOption( this.driver, BUTTON_COLOR_SELECTOR, color );
	}

	selectButtonLanguage( language ) {
		return helper.selectOption( this.driver, BUTTON_LANGUAGE_SELECTOR, language );
	}

	setAppClientId( clientId ) {
		return helper.setWhenSettable( this.driver, APP_CLIENT_ID_SELECTOR, clientId );
	}

	setAppClientSecret( clientSecret ) {
		return helper.setWhenSettable( this.driver, APP_CLIENT_SECRET_SELECTOR, clientSecret );
	}

	checkUseSandbox() {
		helper.unsetCheckbox( this.driver, USE_SANDBOX_SELECTOR );
		return helper.setCheckbox( this.driver, USE_SANDBOX_SELECTOR );
	}

	uncheckUseSandbox() {
		helper.setCheckbox( this.driver, USE_SANDBOX_SELECTOR );
		return helper.unsetCheckbox( this.driver, USE_SANDBOX_SELECTOR );
	}

	selectPaymentCapture( option ) {
		return helper.selectOption( this.driver, PAYMENT_CAPTURE_SELECTOR, option );
	}

	selectCartLoginButtonDisplay( option ) {
		return helper.selectOption( this.driver, CART_LOGIN_BUTTON_DISPLAY_SELECTOR, option );
	}

	selectButtonType( type ) {
		return helper.selectOption( this.driver, BUTTON_TYPE_SELECTOR, type );
	}

	selectButtonSize( size ) {
		return helper.selectOption( this.driver, BUTTON_SIZE_SELECTOR, size );
	}

	selectButtonColor( color ) {
		return helper.selectOption( this.driver, BUTTON_COLOR_SELECTOR, color );
	}

	selectButtonLanguage( language ) {
		return helper.selectOption( this.driver, BUTTON_LANGUAGE_SELECTOR, language );
	}

	checkHideStandardCheckoutButton() {
		helper.unsetCheckbox( this.driver, HIDE_STANDARD_CHECKOUT_BUTTON );
		return helper.setCheckbox( this.driver, HIDE_STANDARD_CHECKOUT_BUTTON );
	}

	uncheckHideStandardCheckoutButton() {
		helper.setCheckbox( this.driver, HIDE_STANDARD_CHECKOUT_BUTTON );
		return helper.unsetCheckbox( this.driver, HIDE_STANDARD_CHECKOUT_BUTTON );
	}
}
