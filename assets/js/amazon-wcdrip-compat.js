( function( $ ) {
	var wcDdripCompat = {
		subscribeSelector: '#wcdrip_subscribe_field',
		consentWidget: '#amazon_consent_widget',
		cloned: false,

		subscribeExists: function() {
			return $( wcDdripCompat.subscribeSelector ).length !== 0;
		},

		init: function() {
			if ( wcDdripCompat.subscribeExists() ) {
				$( document ).on( 'wc_amazon_pa_widget_ready', wcDdripCompat.copySubscribeField );
			}
		},

		copySubscribeField: function() {
			if ( wcDdripCompat.cloned ) {
				return;
			}

			$( wcDdripCompat.subscribeSelector ).clone().insertAfter( wcDdripCompat.consentWidget );
			wcDdripCompat.cloned = true;
		}
	};

	$( wcDdripCompat.init );
} )( jQuery );
