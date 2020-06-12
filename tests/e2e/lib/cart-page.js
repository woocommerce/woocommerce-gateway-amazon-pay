/**
 * External dependencies
 */
import { By } from 'selenium-webdriver';
import { CartPage as Base } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import lwa from './login-with-amazon';

const LOGIN_WITH_AMAZON_BUTTON_SELECTOR = By.css( '.wc-proceed-to-checkout #pay_with_amazon img' );
const LOGIN_WITH_AMAZON_BANNER_SELECTOR = By.css( '.woocommerce-info #pay_with_amazon img' );

export default class CartPage extends Base {
	loginWithAmazonButton( username, password ) {
		return lwa( this.driver, LOGIN_WITH_AMAZON_BUTTON_SELECTOR, username, password );
	}

	loginWithAmazonBanner( username, password ) {
		return lwa( this.driver, LOGIN_WITH_AMAZON_BANNER_SELECTOR, username, password );
	}

	missingAmazonButton() {
		return this.driver.findElement( LOGIN_WITH_AMAZON_BUTTON_SELECTOR ).then( null, err => {
			return 'NoSuchElementError' === err.name;
		} );
	}

	missingAmazonBanner() {
		return this.driver.findElement( LOGIN_WITH_AMAZON_BANNER_SELECTOR ).then( null, err => {
			return 'NoSuchElementError' === err.name;
		} );
	}
}
