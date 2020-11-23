<?php
abstract class WC_Gateway_Amazon_Payments_Advanced_Abstract extends WC_Payment_Gateway {

	/**
	 * Get Amazon logout URL.
	 *
	 * @since 1.6.0
	 *
	 * @return string Amazon logout URL
	 */
	public function get_amazon_logout_url() {
		return add_query_arg(
			array(
				'amazon_payments_advanced' => 'true',
				'amazon_logout'            => 'true',
			),
			get_permalink( wc_get_page_id( 'checkout' ) )
		);
	}

}
