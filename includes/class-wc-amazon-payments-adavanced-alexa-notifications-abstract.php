<?php
/**
 * Amazon Alexa Notifications abstract class.
 *
 * @package WC_Gateway_Amazon_Pay
 */

/**
 * Amazon Alexa Notifications abstract class
 */
abstract class WC_Amazon_Payments_Advanced_Alexa_Notifications_Abstract {

    protected $action;

    protected $carrier;

    public function __construct( string $action, string $carrier ) {
        $this->action  = $action;
        $this->carrier = $carrier;

        if ( is_callable( $this->action ) ) {
            add_action(
                $this->action,
                array( $this, 'enable_alexa_notifications_for_carrier' ),
                apply_filters( 'apa_enable_alexa_notifications_for_carrier_priority_' . str_replace( ' ', '_', strtolower( $this->carrier ) ), 10 ),
                apply_filters( 'apa_enable_alexa_notifications_for_carrier_accepted_args_' . str_replace( ' ', '_', strtolower( $this->carrier ) ), 2 )
            );
        }
    }
}
