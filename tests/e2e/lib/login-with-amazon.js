/**
 * External dependencies
 */
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { By } from 'selenium-webdriver';

const AMAZON_USERNAME_FIELD_SELECTOR = By.css( '#ap_email' );
const AMAZON_PASSWORD_FIELD_SELECTOR = By.css( '#ap_password' );
const AMAZON_SUBMIT_SELECTOR = By.css( '.button-text.signin-button-text' );

export default function loginWithAmazon( driver, popupTriggerSelector, username, password ) {
	const originalWindow = driver.getWindowHandle().then( win => win );
	helper.clickWhenClickable( driver, popupTriggerSelector );

	const amazonWindow = driver.getAllWindowHandles().then( windows => {
		return windows.pop();
	} );

	driver.switchTo().window( amazonWindow ).then( () => {
		helper.setWhenSettable( driver, AMAZON_USERNAME_FIELD_SELECTOR, username );
		helper.setWhenSettable( driver, AMAZON_PASSWORD_FIELD_SELECTOR, password );
		helper.clickWhenClickable( driver, AMAZON_SUBMIT_SELECTOR );
	} );

	driver.switchTo().window( originalWindow );
}
