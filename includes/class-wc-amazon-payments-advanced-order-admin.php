<?php
/**
 * Amazon Pay Order Admin class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Handle admin orders interface
 */
class WC_Amazon_Payments_Advanced_Order_Admin {

	/**
	 * Add meta box related to Amazon Pay.
	 *
	 * This will add new meta box in edit order screen.
	 *
	 * @see WC_Amazon_Payments_Advanced::init_order_admin
	 */
	public function add_meta_box() {
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
	}

	/**
	 * Add ajax handler related to Amazon Pay.
	 *
	 * This will add ajax handler for Amazon Pay meta box.
	 *
	 * @see WC_Amazon_Payments_Advanced::init_order_admin
	 */
	public function add_ajax_handler() {
		add_action( 'wp_ajax_amazon_order_action', array( $this, 'order_actions' ) );
	}

	/**
	 * AJAX handler that performs order actions.
	 */
	public function order_actions() {
		check_ajax_referer( 'amazon_order_action', 'security' );

		$order_id = absint( $_POST['order_id'] );
		$id       = isset( $_POST['amazon_id'] ) ? wc_clean( $_POST['amazon_id'] ) : '';
		$action   = sanitize_title( $_POST['amazon_action'] );

		$this->do_order_action( $order_id, $id, $action );

		die();
	}

	/**
	 * Perform the action.
	 *
	 * @todo Either return a value or throw exception so that error message
	 * can be retrieved by the caller.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $order_id  Order ID.
	 * @param int    $id        Reference ID.
	 * @param string $action    Action to perform.
	 */
	public function do_order_action( $order_id, $id, $action ) {
		wc_apa()->log( __METHOD__, sprintf( 'Info: Trying to perform "%s" for order #%s', $action, $order_id ) );
		switch ( $action ) {
			case 'refresh' :
				$this->clear_stored_states( $order_id );
			break;
			case 'authorize' :
				delete_post_meta( $order_id, 'amazon_authorization_id' );
				delete_post_meta( $order_id, 'amazon_capture_id' );

				// $id is order reference.
				wc_apa()->log( __METHOD__, 'Info: Trying to authorize payment in order reference ' . $id );

				WC_Amazon_Payments_Advanced_API::authorize_payment( $order_id, $id, false );
				$this->clear_stored_states( $order_id );
			break;
			case 'authorize_capture' :
				delete_post_meta( $order_id, 'amazon_authorization_id' );
				delete_post_meta( $order_id, 'amazon_capture_id' );

				// $id is order reference.
				wc_apa()->log( __METHOD__, 'Info: Trying to authorize and capture payment in order reference ' . $id );

				WC_Amazon_Payments_Advanced_API::authorize_payment( $order_id, $id, true );
				WC_Amazon_Payments_Advanced_API::close_order_reference( $order_id );
				$this->clear_stored_states( $order_id );
			break;
			case 'close_authorization' :
				// $id is authorization reference.
				wc_apa()->log( __METHOD__, 'Info: Trying to close authorization ' . $id );

				WC_Amazon_Payments_Advanced_API::close_authorization( $order_id, $id );
				$this->clear_stored_states( $order_id );
			break;
			case 'capture' :
				// $id is authorization reference.
				wc_apa()->log( __METHOD__, 'Info: Trying to capture payment with authorization ' . $id );

				WC_Amazon_Payments_Advanced_API::capture_payment( $order_id, $id );
				WC_Amazon_Payments_Advanced_API::close_order_reference( $order_id );
				$this->clear_stored_states( $order_id );
			break;
			case 'refund' :
				// $id is capture reference.
				wc_apa()->log( __METHOD__, 'Info: Trying to refund payment with capture reference ' . $id );

				$amazon_refund_amount = floatval( wc_clean( $_POST['amazon_refund_amount'] ) );
				$amazon_refund_note   = wc_clean( $_POST['amazon_refund_note'] );

				WC_Amazon_Payments_Advanced_API::refund_payment( $order_id, $id, $amazon_refund_amount, $amazon_refund_note );
				$this->clear_stored_states( $order_id );
			break;
		}
	}

	/**
	 * Get the refresh link.
	 *
	 * Refresh link in Amazon Pay meta box is used to clear Amazon order state.
	 *
	 * @since 1.6.0
	 *
	 * @return string HTML of refresh link with its container
	 */
	private function _get_refresh_link() {
		return wpautop( sprintf(
			'<a href="#" data-action="refresh" class="refresh">%s</a>%s',
			esc_html__( 'Refresh', 'woocommerce-gateway-amazon-payments-advanced' ),
			wc_help_tip( __( 'Refresh Amazon transaction status.', 'woocommerce-gateway-amazon-payments-advanced' ) )
		) );
	}

	/**
	 * Wipe states so the value is refreshed.
	 *
	 * Invoked when refresh link is clicked.
	 *
	 * @param int $order_id Order ID.
	 */
	public function clear_stored_states( $order_id ) {
		delete_post_meta( $order_id, 'amazon_reference_state' );
		delete_post_meta( $order_id, 'amazon_capture_state' );
		delete_post_meta( $order_id, 'amazon_authorization_state' );
	}

	/**
	 * Amazon Pay authorization metabox.
	 */
	public function meta_box() {
		global $post, $wpdb;

		$order_id = absint( $post->ID );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( 'amazon_payments_advanced' === wc_apa_get_order_prop( $order, 'payment_method' ) ) {
			add_meta_box( 'woocommerce-amazon-payments-advanced', __( 'Amazon Pay', 'woocommerce-gateway-amazon-payments-advanced' ), array( $this, 'authorization_box' ), 'shop_order', 'side' );
		}
	}

	/**
	 * Authorization metabox content.
	 */
	public function authorization_box() {
		global $post, $wpdb, $theorder;

		$actions  = array();
		$order_id = absint( $post->ID );

		if ( ! is_object( $theorder ) ) {
			$theorder = new WC_Order( $order_id );
		}

		// Get ids.
		$amazon_authorization_id = get_post_meta( $order_id, 'amazon_authorization_id', true );
		$amazon_reference_id     = get_post_meta( $order_id, 'amazon_reference_id', true );
		$amazon_capture_id       = get_post_meta( $order_id, 'amazon_capture_id', true );
		$amazon_refund_ids       = get_post_meta( $order_id, 'amazon_refund_id', false );

		if ( $amazon_capture_id ) {

			$amazon_capture_state = WC_Amazon_Payments_Advanced_API::get_capture_state( $order_id, $amazon_capture_id );

			switch ( $amazon_capture_state ) {
				case 'Pending' :

					echo wpautop( sprintf( __( 'Capture Reference %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_capture_id ), esc_html( $amazon_capture_state ) ) );
					echo $this->_get_refresh_link();

					// Admin will need to re-check this, so clear the stored value.
					$this->clear_stored_states( $order_id );
				break;
				case 'Declined' :

					echo wpautop( __( 'The capture was declined.', 'woocommerce-gateway-amazon-payments-advanced' ) );
					echo $this->_get_refresh_link();

					$actions['authorize'] = array(
						'id'     => $amazon_reference_id,
						'button' => __( 'Re-authorize?', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

				break;
				case 'Completed' :

					echo wpautop( sprintf( __( 'Capture Reference %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_capture_id ), esc_html( $amazon_capture_state ) ) . ' <a href="#" class="toggle_refund">' . __( 'Make a refund?', 'woocommerce-gateway-amazon-payments-advanced' ) . '</a>' );

					// Refund form.
					?>
					<p class="refund_form" style="display:none">
						<input type="number" step="any" style="width:100%" class="amazon_refund_amount" value="<?php echo esc_attr( $theorder->get_total() ); ?>" />
						<input type="text" style="width:100%" class="amazon_refund_note" placeholder="<?php _e( 'Add a note about this refund', 'woocommerce-gateway-amazon-payments-advanced' ); ?>" /><br/>
						<a href="#" class="button" data-action="refund" data-id="<?php echo esc_attr( $amazon_capture_id ); ?>"><?php _e( 'Refund', 'woocommerce-gateway-amazon-payments-advanced' ); ?></a>
					</form>
					<?php

					echo $this->_get_refresh_link();

				break;
				case 'Closed' :

					/* translators: 1) is Amazon Pay capture reference ID, and 2) Amazon Pay capture state */
					echo wpautop( sprintf( __( 'Capture Reference %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_capture_id ), esc_html( $amazon_capture_state ) ) );
					echo $this->_get_refresh_link();

				break;
			}

			// Display refunds.
			if ( $amazon_refund_ids ) {
				$refunds = (array) get_post_meta( $order_id, 'amazon_refunds', true );

				foreach ( $amazon_refund_ids as $amazon_refund_id ) {

					if ( isset( $refunds[ $amazon_refund_id ] ) ) {
						echo wpautop( sprintf( __( 'Refund %1$s of %2$s is <strong>%s</strong> (%3$s).', 'woocommerce-gateway-amazon-payments-advanced' ), $amazon_refund_id, wc_price( $refunds[ $amazon_refund_id ]['amount'] ), $refunds[ $amazon_refund_id ]['state'], $refunds[ $amazon_refund_id ]['note'] ) );
					} else {

						$response = WC_Amazon_Payments_Advanced_API::request( array(
							'Action'         => 'GetRefundDetails',
							'AmazonRefundId' => $amazon_refund_id,
						) );

						if ( ! is_wp_error( $response ) && ! isset( $response['Error']['Message'] ) ) {

							// @codingStandardsIgnoreStart
							$note   = (string) $response->GetRefundDetailsResult->RefundDetails->SellerRefundNote;
							$state  = (string) $response->GetRefundDetailsResult->RefundDetails->RefundStatus->State;
							$amount = (string) $response->GetRefundDetailsResult->RefundDetails->RefundAmount->Amount;
							// @codingStandardsIgnoreEnd

							echo wpautop( sprintf( __( 'Refund %1$s of %2$s is <strong>%s</strong> (%3$s).', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_refund_id ), wc_price( $amount ), esc_html( $state ), esc_html( $note ) ) );

							if ( 'Completed' === $state ) {
								$refunds[ $amazon_refund_id ] = array(
									'state'  => $state,
									'amount' => $amount,
									'note'   => $note,
								);
							}
						}
					}
				}

				update_post_meta( $order_id, 'amazon_refunds', $refunds );
			}
		} elseif ( $amazon_authorization_id ) {

			$amazon_authorization_state = WC_Amazon_Payments_Advanced_API::get_authorization_state( $order_id, $amazon_authorization_id );

			/* translators: 1) is Amazon Pay authorization reference ID, and 2) Amazon Pay authorization state */
			echo wpautop( sprintf( __( 'Auth Reference %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_reference_id ), esc_html( $amazon_authorization_state ) ) );
			echo $this->_get_refresh_link();

			switch ( $amazon_authorization_state ) {
				case 'Open' :

					$actions['capture'] = array(
						'id'     => $amazon_authorization_id,
						'button' => __( 'Capture funds', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

					$actions['close_authorization'] = array(
						'id'     => $amazon_authorization_id,
						'button' => __( 'Close Authorization', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

				break;
				case 'Pending' :

					echo wpautop( __( 'You cannot capture funds whilst the authorization is pending. Try again later.', 'woocommerce-gateway-amazon-payments-advanced' ) );

					// Admin will need to re-check this, so clear the stored value.
					$this->clear_stored_states( $order_id );

				break;
				case 'Closed' :
				case 'Declined' :
					$actions['authorize'] = array(
						'id'     => $amazon_reference_id,
						'button' => __( 'Authorize again', 'woocommerce-gateway-amazon-payments-advanced' ),
					);
				break;
			}
		} elseif ( $amazon_reference_id ) {

			$amazon_reference_state = WC_Amazon_Payments_Advanced_API::get_reference_state( $order_id, $amazon_reference_id );

			/* translators: 1) is Amazon Pay order reference ID, and 2) Amazon Pay order state */
			echo wpautop( sprintf( __( 'Order Reference %1$s is <strong>%2$s</strong>.', 'woocommerce-gateway-amazon-payments-advanced' ), esc_html( $amazon_reference_id ), esc_html( $amazon_reference_state ) ) );
			echo $this->_get_refresh_link();

			switch ( $amazon_reference_state ) {
				case 'Open' :

					$actions['authorize'] = array(
						'id'     => $amazon_reference_id,
						'button' => __( 'Authorize', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

					$actions['authorize_capture'] = array(
						'id'     => $amazon_reference_id,
						'button' => __( 'Authorize &amp; Capture', 'woocommerce-gateway-amazon-payments-advanced' ),
					);

				break;
				case 'Suspended' :

					echo wpautop( __( 'The reference has been suspended. Another form of payment is required.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				break;
				case 'Canceled' :
				case 'Suspended' :

					echo wpautop( __( 'The reference has been cancelled/closed. No authorizations can be made.', 'woocommerce-gateway-amazon-payments-advanced' ) );

				break;
			}
		}

		if ( ! empty( $actions ) ) {

			echo '<p class="buttons">';

			foreach ( $actions as $action_name => $action ) {
				echo '<a href="#" class="button" data-action="' . esc_attr( $action_name ) . '" data-id="' . esc_attr( $action['id'] ) . '">' . esc_html( $action['button'] ) . '</a> ';
			}

			echo '</p>';

		}

		$js = "
			jQuery( '#woocommerce-amazon-payments-advanced' ).on( 'click', 'a.button, a.refresh', function() {

				jQuery( '#woocommerce-amazon-payments-advanced' ).block({
					message:    null,
					overlayCSS: {
						background: '#fff url(" . WC()->plugin_url() . "/assets/images/ajax-loader.gif) no-repeat center',
						opacity:    0.6
					}
				});

				var data = {
					action:               'amazon_order_action',
					security:             '" . wp_create_nonce( 'amazon_order_action' ) . "',
					order_id:             '$order_id',
					amazon_action:        jQuery( this ).data( 'action' ),
					amazon_id:            jQuery( this ).data( 'id' ),
					amazon_refund_amount: jQuery( '.amazon_refund_amount' ).val(),
					amazon_refund_note:   jQuery( '.amazon_refund_note' ).val(),
				};

				// Ajax action
				jQuery.ajax({
					url:     '" . admin_url( 'admin-ajax.php' ) . "',
					data:    data,
					type:    'POST',
					success: function( result ) {
						location.reload();
					}
				});

				return false;
			});

			jQuery( '#woocommerce-amazon-payments-advanced' ).on( 'click', 'a.toggle_refund', function() {
				jQuery( '.refund_form' ).slideToggle();
				return false;
			});
		";

		wc_enqueue_js( $js );
	}

}

new WC_Amazon_Payments_Advanced_Order_Admin();
