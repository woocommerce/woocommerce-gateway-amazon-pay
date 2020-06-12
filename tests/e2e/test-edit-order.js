/**
 * External dependencies
 */
import config from 'config';
import test from 'selenium-webdriver/testing';

/**
 * Internal dependencies
 */
import * as t from './lib/test-helper';

test.describe( 'Edit order', function() {
	this.timeout( config.get( 'mochaTimeoutMs' ) );

	test.before( function() {
		this.timeout( config.get( 'startBrowserTimeoutMs' ) );
	} );

	test.before( t.startBrowser );

	test.it( '(TODO) Make a refund', () => {
	} );

	test.it( '(TODO) Refresh state', () => {
	} );

	test.it( '(TODO) Capture', () => {
	} );

	test.it( '(TODO) Cancel order', () => {
	} );

	test.after( t.quitBrowser );
} );
