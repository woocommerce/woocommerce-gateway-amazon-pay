/**
 * External dependencies
 */
import { StoreOwnerFlow as Base } from 'wc-e2e-page-objects';

/**
 * Internal dependencies
 */
import WPAdminWCSettingsCheckoutAmazonPay from './wp-admin-wc-settings-checkout-amazon-pay.js';

export default class StoreOwnerFlow extends Base {
	constructor( driver, args = {} ) {
		super( driver, args );
	}

	openAmazonPaySettings() {
		return this.open( {
			path: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=amazon_payments_advanced',
			object: WPAdminWCSettingsCheckoutAmazonPay
		} );
	}

	setAmazonPaySettings( args ) {
		args = Object.assign(
			{
				enable: true,
				useLoginWithAmazonApp: true,
				useSandbox: true
			},
			args
		);

		const settings = this.openAmazonPaySettings();
		if ( args.enable ) {
			settings.checkEnable();
		}

		if ( args.sellerId ) {
			settings.setSellerId( args.sellerId );
		}
		if ( args.mwsAccessKey ) {
			settings.setMwsAccessKey( args.mwsAccessKey );
		}
		if ( args.mwsSecretKey ) {
			settings.setMwsSecretKey( args.mwsSecretKey );
		}
		if ( args.paymentRegion ) {
			settings.selectPaymentRegion( args.paymentRegion );
		}
		if ( args.useLoginWithAmazonApp ) {
			settings.checkUseLoginWithAmazonApp();
		}
		if ( args.appClientId ) {
			settings.setAppClientId( args.appClientId );
		}
		if ( args.appClientSecret ) {
			settings.setAppClientSecret( args.appClientSecret );
		}
		if ( args.useSandbox ) {
			settings.checkUseSandbox();
		}
		if ( args.paymentCapture ) {
			settings.selectPaymentCapture( args.paymentCapture );
		}
		if ( args.cartLoginButtonDisplay ) {
			settings.selectCartLoginButtonDisplay( args.cartLoginButtonDisplay );
		}
		if ( args.buttonType ) {
			settings.selectButtonType( args.buttonType );
		}
		if ( args.buttonSize ) {
			settings.selectButtonSize( args.buttonSize );
		}
		if ( args.buttonColor ) {
			settings.selectButtonColor( args.buttonColor );
		}
		if ( args.buttonLanguage ) {
			settings.selectButtonLanguage( args.buttonLanguage );
		}

		return settings.saveChanges();
	}
}
