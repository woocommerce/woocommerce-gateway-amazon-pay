<?php

class WC_Amazon_Payments_Advanced_Admin {

	/**
	 * Order admin handler instance.
	 *
	 * @since 1.6.0
	 * @var WC_Amazon_Payments_Advanced_Order_Admin
	 */
	private $order_admin;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->version       = WC_AMAZON_PAY_VERSION;
		$this->path          = untrailingslashit( plugin_dir_path( __FILE__ ) );
		$this->plugin_url    = untrailingslashit( plugins_url( '/', __FILE__ ) );

		$this->init_order_admin();
	}

	/**
	 * Init admin handler.
	 *
	 * @since 1.6.0
	 */
	public function init_order_admin() {
		include_once( $this->path . '/class-wc-amazon-payments-advanced-order-admin.php' );

		$this->order_admin = new WC_Amazon_Payments_Advanced_Order_Admin();
		$this->order_admin->add_meta_box();
		$this->order_admin->add_ajax_handler();
	}

}
