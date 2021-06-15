=== WooCommerce Amazon Pay ===
Contributors: woocommerce, automattic, woothemes, akeda, jeffstieler, mikejolley, bor0, claudiosanches, royho, jamesrrodger, laurendavissmith001, dwainm, danreylop
Tags: woocommerce, amazon, checkout, payments, e-commerce, ecommerce
Requires at least: 4.4
Tested up to: 5.7
Stable tag: 2.0.3
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Install the Amazon Pay plugin for your WooCommerce store and take advantage of a seamless checkout experience

== Description ==

**What is Amazon Pay?** An end-to-end payment solution that gives hundreds of millions of active Amazon customers[1] a familiar, fast, and secure way to complete their purchase through your online store. Shoppers can use the address and payment information already stored in their Amazon account to check out – avoiding account creation or the need to re-enter their billing and shipping information. The performance is continually optimized by technology, learnings, and best practices from Amazon.

As earth’s most customer-centric company, we are continuously innovating on behalf of our customers. With 91% of Amazon Pay customers saying they would use Amazon Pay again and hundreds of millions of active Amazon customers already enabled for Amazon Pay, it can make it easier for you to deliver an improved customer experience online[2].

= Key Features =

- **PSD2 compliant**: Built-in support for Strong Customer Authentication (SCA) as required under the Second Payment Services Directive (PSD2) in the European Economic Area (EEA).
- **Multi-currency**: Maintain the local currency experience across the shopping journey and help customers avoid currency conversion fees from their credit card issuer or bank.
- **Recurring payment support for [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/)** (separate purchase): available for USA, UK, Germany, France, Italy, Ireland, Spain, Luxembourg, Austria, Belgium, Cyprus, Netherlands, Sweden, Portugal, Hungary, Denmark and Japan.
- **Automatic Decline Handling**: Reduce lost sales with a consistent experience for customers to gracefully recover from a declined payment.
- **Payment Protection Policy**: Protection against fraud-related chargebacks[3].
- **Amazon Pay A-to-z Guarantee**: Increase customer confidence to complete purchase in your online store with extra assurance on the timeliness of delivery and order quality[4].

= Definitions =

- [1] Represents active Amazon customer accounts, 2020.
- [2] Consumer Net Promoter Score (NPS) Surveys: Conducted by Amazon Pay in 2019 among US, UK, DE, FR, IT, and ES consumers who had used Amazon Pay in the 12 months preceding to the survey launch dates.
- [3] Available for qualified physical goods purchases only.
- [4] For eligible transactions detailed on the [Amazon Pay Customer Agreement](https://pay.amazon.com/help/201212430).

== Installation ==

= Minimum Requirements =

* WordPress 4.4 or greater

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "WooCommerce Amazon Pay" and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favorite FTP application. The
WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Changelog ==

= 2.0.3 - 2021-06-15 =
* Fix - Issues with state level handling of shipping zones.
* Fix - Issue that attempted to initialize the plugin in the REST API, throwing a fatal error.
* Fix - Issue with subscriptions and checkout session validation, which forced customers to login again.
* Add - Logging when users are asked to log in again, to debug other potential issues with this validation.

= 2.0.2 - 2021-05-26 =
* Fix - Issue that caused secret key from pre v2 to be lost after migrating to v2.
* Add - Allow recovery of v1 secret key if lost during migration to v2.

= 2.0.1 - 2021-05-14 =
* Update - WP tested up to 5.7.
* Update - WC tested up to 5.3.
* Fix - Properly compose url for order action buttons.

= 2.0.0 - 2021-05-11 =
* Upgrade to use the latest Amazon Pay frontend technology and backend API. Functionalities in parity with the previous version.

= 1.13.1 - 2021-02-25 =
* Fix - Avoid hiding default shipping fields at checkout.

= 1.13.0 - 2021-02-18 =
* Update - WP tested up to 5.6.
* Update - WC tested up to 5.0.
* Fix - Fatal checkout error when changing subscription's payment method if user is logged out of Amazon account.
* Fix - Checkout error when address book state does not match WooCommerce state data.
* Fix - Multi-currency compatibility is not detected when Price Based on Country and WMPL is active.
* Fix - PHP error when the currencies_supported option is not set.
* Fix - Add InheritShippingAddress to AuthorizeOnBillingAgreement. InheritShippingAddress = True when orders are shipping physical products.
* Fix - Missing order ID in session.
* Fix - Normalize and refactor URL handling when checkout page url is not set.

= 1.12.2 - 2020-05-05 =
* Fix - Fatal checkout error when submitting orders that do not need shipping.

= 1.12.1 - 2020-05-04 =
* Update - WC tested up to 4.1.

= 1.12.0 - 2020-04-20 =
* Add - Automatic key exchange on setup for GB and EU regions.
* Add - Handling for manual encrypted key exchange.
* Add - Pending transactions processed automatically even if the IPN isn't received.
* Add - Additional server-side logging for SCA transactions.
* Update - WC tested up to 4.0.
* Update - WP tested up to 5.4.
* Fix - Transaction timeout handling.
* Fix - Orders are created without billing information.
* Fix - Xero invoice exporting on order creation.
* Fix - Users are created with empty address fields.

= 1.11.1 - 2020-02-13 =
* Fix - Properly encode URL string

= 1.11.0 - 2020-01-21 =
* Add - Strong Customer Authentication (SCA) support for subscriptions (billing agreements).
* Add - Support for custom checkout fields.
* Add - Optimal login option to "Login with Amazon" feature.
* Update - Attach WooCommerce and Amazon Pay plugin version as transaction meta data.
* Update - Enable gateway by default and show a warning that it's live.

= 1.10.3 - 2019-11-18 =
* Update - WC tested up to 3.8.
* Update - WP tested up to 5.3.

= 1.10.2 - 2019-08-08 =
* Update - WC tested up to 3.7.

= 1.10.1 - 2019-06-11 =
* Fix - Payment options not working when Amazon Pay v1.10.0 is active
* Fix - Checkout broken when Login with Amazon app is disabled

= 1.10.0 - 2019-06-03 =
* Add - Strong Customer Authentication (SCA) support for United Kingdom, Euro Region merchants

= 1.9.1 - 2019-04-17 =
* Tweak - WC tested up to 3.6.

= 1.9.0 - 2019-02-11 =
* Update - Allow transactions of more than $1000 via async/IPN.
* Update - Upgrade merchant onboarding and registration experience.
* Update - Allow to capture payments in multiple currencies.
* Fix - Avoid using float css property so the cart button is always wrapped by the parent div.

= 1.8.5 - 2018-10-17 =
* Update - WC tested up to 3.5.

= 1.8.4 - 2018-05-17 =
* Update - WC tested up to version.
* Update - Privacy policy notification.
* Update - Export/erasure hooks added.
* Fix    - Missing most of the address information.

= 1.8.3 - 2018-05-09 =
* Add   - Hook to show/hide amazon address widget "woocommerce_amazon_show_address_widget" (bool), hidden by default.
* Add   - New setting field to Enable/Disable Subscriptions support.
* Fix   - Compatibility fixes with Advanced Ordernumbers plugin.
* Tweak - Allow Subscription details to be changed for Subscriptions paid through Amazon.

= 1.8.2 - 2017-03-12 =
* Tweak - Change refund_type string for IPNs when a payment refund is received for subscriptions.

= 1.8.1 - 2017-12-15 =
* Update - WC tested up to version.

= 1.8.0 - 2017-11-29 =
* Tweak - Added IPN handlers to handle notifications from Amazon. Currently only add the notification as order notes.
* Tweak - Handle order refund when IPN for payment refund is received.
* Tweak - Added admin notices for conditions that may cause an issue: 1) WooCommerce Germanized is active with disallow cancellation option enabled 2) Shop currency doesn't match with payment region.
* Fix - Remove restriction of subscriptions on EU region. Amazon has reached general availability for the recurring payments product. No white listing needed anymore in any region.
* Fix - Hide customizable button settings if login with Amazon app is disabled.
* Fix - Check city if state is missing from address widget. Please note that StateOrRegion, known as state in WooCommerce, is not mandatory in Amazon address. If the fallback is failed, the workaround would be from shipping zone to target the country.
* Fix - Handles buyer canceled scenario via IPN.

= 1.7.3 - 2017-07-06 =
* Tweak - Change Payment mark after Amazon re-brand.
* Tweak - Add setting link in plugin action links.
* Fix - Issue in PHP 7.1 might throw an error when trying to checkout.
* Fix - Added proper handler for `AmazonRejected`. It won't render the widgets and redirect to cart immediately.
* Fix - Removed explicit limit check when authorizing billing agreement. Order will be failed when attempting to authorize such payment and subscription still inactive until the order is paid.
* Fix - Suppress coupon notice/form when the transaction is declined with reason code `InvalidPaymentMethod`.
* Fix - PHP Notice: id was called incorrectly when attempting to pay Subscription product.

= 1.7.2 - 2017-06-27 =
* Add - New Woo plugin header, for WooCommerce 3.1 compatibility.

= 1.7.1 - 2017-05-01 =
* Fix - Issue where address is not being passed in new order email.
* Fix - Issue where billing and shipping information from Amazon were not saved when login app is not enabled.
* Fix - Make address widget read-only when authorization is declined with reason code `InvalidPaymentMethod`.

= 1.7.0 - 2017-04-04 =
* Fix - Update for WooCommerce 3.0 compatibility.
* Fix - Issue where subscription renewal order could not find billing agreement ID.
* Tweak - Compability with WPML.
* Fix - issue where disabled guest checkout with generated username and password blocked checkout.
* Tweak - Updated strings "Amazon Pay" as the brand name.
* Fix - Improper handling of declined authorization.
