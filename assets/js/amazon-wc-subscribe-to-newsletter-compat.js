( function( $ ) {
	var subscribeToNewsletterCompat = {
		subscribeSelector: '#subscribe_to_newsletter_field',
		consentWidget: '#amazon_consent_widget',
		cloned: false,

		subscribeExists: function() {
			return $( subscribeToNewsletterCompat.subscribeSelector ).length !== 0;
		},

		init: function() {
			if ( subscribeToNewsletterCompat.subscribeExists() ) {
				$( document ).on( 'wc_amazon_pa_widget_ready', subscribeToNewsletterCompat.copySubscribeField );
			}
		},

		copySubscribeField: function() {
			if ( subscribeToNewsletterCompat.cloned ) {
				return;
			}

			$( subscribeToNewsletterCompat.subscribeSelector ).clone().insertAfter( subscribeToNewsletterCompat.consentWidget );
			subscribeToNewsletterCompat.cloned = true;
		}
	};

	$( subscribeToNewsletterCompat.init );
} )( jQuery );
