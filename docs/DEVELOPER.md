Amazon Pay Developer Documentation
==================================

## Resources

### Merchant Account Dashboard

[Amazon Seller Central](https://sellercentral.amazon.com/gp/homepage.html)

### API Documentation

[Amazon Pay API Reference Guide](https://payments.amazon.com/documentation/apireference/201751630#201751630)

[Login with Amazon and Amazon Pay Integration Guide](https://payments.amazon.com/documentation/lpwa/201749840#201749840)

## Gotchas

### Subscriptions Support

Currently, Amazon Pay supports all Subscriptions features **except** payment method changes.

This decision is one of time management, and not of gateway capability. The plugin currently has too much of the payment widget rendering code outside of the `WC_Payment_Gateway` subclasses to easily use it for the "change payment method" form.

While supporting customer-initiated payment method changes is just a matter of correctly rendering the widgets, admin-initiated payment changes aren't _really_ possible. There is no way to request a valid Billing Agreement from Amazon outside the widget flow, so it's unlikely that an admin could replace the Billing Agreement Id with anything valid.

### Recurring Payment Limits

The recurring payments API limits total charges to a single billing agreement to $500 per calendar month.

According to [this documentation page](https://payments.amazon.com/documentation/automatic/201752090#201757640):

> **Note**: Amazon imposes a $500 per calendar month limit on the amount of funds you can charge a buyer. If you expect to exceed this limit due to an upgrade or the buyer's usage, please contact Amazon.

It is unclear if/how the limit will be altered should you contact Amazon.

If an authorization/capture attempt is made that pushes a given billing agreement over the $500 monthly cap, the following error will be encountered:

> BillingAgreement C01-6601668-8704891 has already been authorized for amount 0.00 in time period i.e. Sun Nov 01 00:00:00 UTC 2015 – Tue Dec 01 00:00:00 UTC 2015. A new authorization with amount 503.00 cannot be accepted as the total authorization amount cannot exceed 500.00.

In order to curb the number of potential recurring payment failures due to the cap, the gateway will disable itself if a cart contains more than $500 of recurring monthly subscriptions.

### API Request Throttling

Both subscription cancellation and renewal can happen in bulk and rely on API endpoints that have request limits.

The `CloseBillingAgreement` and `AuthorizeOnBillingAgreement` endpoints have maximum request quotas of 10 and a restore rate of one request every second in the production environment. This decreases to a maximum request quota of two and a restore rate of one request every two seconds in the sandbox environment.

See the documentation for [CloseBillingAgreement](https://payments.amazon.com/documentation/apireference/201752660#201751950) and [AuthorizeOnBillingAgreement](https://payments.amazon.com/documentation/apireference/201752660#201751940).

Since the method calling `AuthorizeOnBillingAgreement ` is called through the [Action Scheduler](https://github.com/Prospress/action-scheduler) built into Subscriptions, simply `sleep()`ing for an interval equal to the restore rate seems to mitigate any throttling.

This same tactic is used before the call to `CloseBillingAgreement`, but is susceptible to hitting the PHP execution time limit during large bulk operations.

## Zero Total Checkout Logic

When checking out with Amazon Pay, the billing and shipping address information is only available through the Amazon Pay API.

Billing and shipping addresses are selected using Amazon-provided widgets that the gateway renders in place of the default checkout form fields.

In most cases, payment gateways don't need to do anything when an order total is zero (WooCommerce doesn't even call the chosen gateway's `process_payment()` method). Typically this is fine, but in the case of Amazon, we only have access to billing and shipping address information if the integration is in "Login App" mode.

Because of this, the gateway is not available for zero-total checkouts when not in "Login with Amazon App" mode.

## WooCommerce Gateway Amazon Pay REST API

Since 1.6.0, this extension exposes some functionalities through REST API.

The WooCommerce Gateway Amazon Pay REST API allows you to authorize, capture, and close authorization.
The endpoint is `/wp-json/wc/v1/orders/<order_id>/amazon-payments-advanced/`.

### List of orders paid via Amazon Pay

There's no custom endpoint to retrieve list of orders paid via Amazon Pay. The built-in orders point can be used with
`_payment_method=amazon_payments_advanced` filter.

```
GET /wp-json/wc/v1/orders?filter[_payment_method]=amazon_payments_advanced
```

```
curl -g -X GET 'https://example.com/wp-json/wc/v1/orders?filter[_payment_method]=amazon_payments_advanced' -u consumer_key:consumer_secret
```

For CURL request that involves filter query (`[]`), you need to specify `-g` (to turn off [URL globbing](http://ec.haxx.se/cmdline-globbing.html)).

JSON response example:

```
[
  {
    "id": 132,
    "status": "on-hold",
    "order_key": "wc_order_57bb41b6eeb32",
    "number": 4606,
    "currency": "GBP",
    ...
    "amazon_reference": {
      "amazon_reference_state": "Open",
      "amazon_reference_id": "S02-0312204-2022855",
      "amazon_authorization_state": "",
      "amazon_authorization_id": "",
      "amazon_capture_state": "",
      "amazon_capture_id": "",
      "amazon_refund_ids": []
    },
    ...
  },
  ...
]
```

Orders paid via Amazon Pay will have `amazon_reference` on order item.

The `filter` parameter can be used with `status` parameter to retrieve list of orders that have been authorized but not captured yet.

```
curl -g -X GET 'https://example.com/wp-json/wc/v1/orders?filter[_payment_method]=amazon_payments_advanced&filter[amazon_authorization_state]=Open&status=on-hold' -u consumer_key:consumer_secret
```

### Authorize the order

```
POST /wp-json/wc/v1/orders/<order_id>/amazon-payments-advanced/authorize 
```

```
curl -X GET 'https://example.com/wp-json/wc/v1/orders/123/amazon-payments-advanced/authorize ' -u consumer_key:consumer_secret
```

JSON response example:

```
{
  "authorized": true,
  "amazon_authorization_id": "S02-6972444-9928455-A066187"
}
```

Possible JSON response with error:

```
{
  "code": "TransactionAmountExceeded",
  "message": "OrderReference S02-6972444-9928455 has already been authorized for amount 21.85 GBP. A new Authorization with amount 21.85 GBP cannot be accepted as the total Authorization amount cannot exceed 25.13 GBP.",
  "data": null
}
```

```
{
  "code": "woocommerce_rest_order_invalid_id",
  "message": "Invalid order ID.",
  "data": {
    "status": 404
  }
}
```

### Close authorization

```
POST /wp-json/wc/v1/orders/<order_id>/amazon-payments-advanced/close-authorization 
```

```
curl -X GET 'https://example.com/wp-json/wc/v1/orders/123/amazon-payments-advanced/close-authorization ' -u consumer_key:consumer_secret
```

JSON response example:

```
{
  "authorization_closed": true
}
```

Possible JSON response with error:

```
{
  "code": "woocommerce_rest_order_missing_amazon_authorization_id",
  "message": "Specified resource does not have Amazon authorization ID",
  "data": {
    "status": 400
  }
}
```

### Capture the order

```
POST /wp-json/wc/v1/orders/<order_id>/amazon-payments-advanced/capture 
```

```
curl -X GET 'https://example.com/wp-json/wc/v1/orders/123/amazon-payments-advanced/capture ' -u consumer_key:consumer_secret
```

JSON response example:

```
{
  "captured": true,
  "amazon_capture_id": "S02-6972444-9928455-C066187"
}
```

Possible JSON response with error:

```
{
  "code": "InvalidAuthorizationStatus",
  "message": "Authorization S02-6972444-9928455-A066187 is currently in Closed state. Capture can only be requested in Open state.",
  "data": null
}
```

### Authorize and capture the order

```
POST /wp-json/wc/v1/orders/<order_id>/amazon-payments-advanced/authorize-and-capture 
```

```
curl -X GET 'https://example.com/wp-json/wc/v1/orders/123/amazon-payments-advanced/authorize-and-capture ' -u consumer_key:consumer_secret
```

JSON response example:

```
{
  "authorized": true,
  "amazon_authorization_id": "S02-4966596-9591203-A079366",
  "captured": true,
  "amazon_capture_id": "S02-4966596-9591203-C079366"
}
```

Possible JSON response with error:

```
{
  "code": "InvalidAuthorizationStatus",
  "message": "Authorization S02-6972444-9928455-A066187 is currently in Closed state. Capture can only be requested in Open state.",
  "data": null
}
```

### Refund the order

```
POST /wp-json/wc/v1/orders/<order_id>/amazon-payments-advanced/refund
```

```
curl -X GET 'https://example.com/wp-json/wc/v1/orders/123/amazon-payments-advanced/refund' \
  -u consumer_key:consumer_secret \
  -H 'Content-Type: application/json' \
  -d '{"amount": "20.00", "reason": "reason for refund"}'
```

JSON response example:

```
{
  "refunded": true,
  "amazon_refund_id": "S02-1228806-5112466-R043423"
}
```
