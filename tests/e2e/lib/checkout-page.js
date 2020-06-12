/**
 * External dependencies
 */
import { By } from 'selenium-webdriver';
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { CheckoutPage as Base } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import lwa from './login-with-amazon';

const LOGIN_WITH_AMAZON_SELECTOR = By.css( '#pay_with_amazon img' );

const AMAZON_PAYMENT_WIDGET_IFRAME_NAME = 'OffAmazonPaymentsWidgets1IFrame';
const AMAZON_PAYMENT_WIDGET_IFRAME_SELECTOR = By.css( '#OffAmazonPaymentsWidgets1IFrame' );
const AMAZON_PAYMENT_WIDGET_NEXT_PAGE_SELECTOR = By.css( '.pagination .next a' );

const AMAZON_BILLING_AGREEMENT_WIDGET_IFRAME_NAME = 'OffAmazonPaymentsWidgets2IFrame';
const AMAZON_BILLING_AGREEMENT_WIDGET_IFRAME_SELECTOR = By.css( '#OffAmazonPaymentsWidgets2IFrame' );
const AMAZON_BILLING_AGREEMENT_WIDGET_CONSENT_SELECTOR = By.css( '.consent-container .consent-link' );

export default class CheckoutPage extends Base {
	loginWithAmazon( username, password ) {
		lwa( this.driver, LOGIN_WITH_AMAZON_SELECTOR, username, password );
	}

	selectAmazonPaymentMethodNo( no ) {
		helper.waitTillPresentAndDisplayed( this.driver, AMAZON_PAYMENT_WIDGET_IFRAME_SELECTOR );
		this.driver.switchTo().frame( AMAZON_PAYMENT_WIDGET_IFRAME_NAME );
		const ret = helper.clickWhenClickable( this.driver, By.css( '.payment-list li:nth-child( ' + no + ' ) a' ) );
		this.driver.switchTo().defaultContent();
		return ret;
	}

	nextPageAmazonPaymentMethod() {
		helper.waitTillPresentAndDisplayed( this.driver, AMAZON_PAYMENT_WIDGET_IFRAME_SELECTOR );
		this.driver.switchTo().frame( AMAZON_PAYMENT_WIDGET_IFRAME_NAME );
		const ret = helper.clickWhenClickable( this.driver, AMAZON_PAYMENT_WIDGET_NEXT_PAGE_SELECTOR );
		this.driver.switchTo().defaultContent();
		return ret;
	}

	walletWidgetHasText( text ) {
		helper.waitTillPresentAndDisplayed( this.driver, AMAZON_PAYMENT_WIDGET_IFRAME_SELECTOR );
		this.driver.switchTo().frame( AMAZON_PAYMENT_WIDGET_IFRAME_NAME );
		const ret = this.hasText( text );
		this.driver.switchTo().defaultContent();
		return ret;
	}

	setAuthorizeBillingAgreement() {
		helper.waitTillPresentAndDisplayed( this.driver, AMAZON_BILLING_AGREEMENT_WIDGET_IFRAME_SELECTOR );
		this.driver.switchTo().frame( AMAZON_BILLING_AGREEMENT_WIDGET_IFRAME_NAME );
		const ret = helper.clickWhenClickable( this.driver, AMAZON_BILLING_AGREEMENT_WIDGET_CONSENT_SELECTOR );
		this.driver.switchTo().defaultContent();
		return ret;
	}
}
