=== Malipo Gateway for WooCommerce ===
Contributors: mobipay
Tags: woocommerce, payment gateway, malipo, mobile money, mpamba
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later

[logo]: assets/images/malipo-logo.png

![Malipo Logo](assets/images/malipo-logo.png)

Accept payments through Malipo's hosted checkout supporting TNM Mpamba, Airtel Money, and card payments.

== Description ==

Malipo Gateway for WooCommerce is a simple and secure payment gateway for WooCommerce that integrates with Malipo's hosted checkout service. It supports TNM Mpamba, Airtel Money, and card payments, and works seamlessly with both classic and block-based WooCommerce checkouts.

**Features:**
* Accepts TNM Mpamba, Airtel Money, and card payments
* Modern hosted checkout popup
* Works with WooCommerce Blocks and classic checkout
* Instant Payment Notification (IPN) support for real-time order status updates
* Customizable settings and reporting for admins
* Malipo-branded receipt/thank you page

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/malipo-woocommerce/`
2. Activate the plugin through WordPress admin
3. Go to WooCommerce > Settings > Payments > Malipo
4. Enter your Merchant Account ID from your Malipo dashboard
5. Enable the payment method

== Configuration ==

1. Get your Merchant Account ID from your Malipo dashboard
2. Go to WooCommerce Settings > Payments > Malipo
3. Enable the gateway and enter your Merchant Account ID
4. Copy the IPN/Callback URL from the settings and add it to your Malipo/MobiPay dashboard for real-time payment notifications
5. Save changes

== How It Works ==

* When a customer selects Malipo at checkout and clicks "Place Order", a secure hosted checkout popup is shown for payment.
* After successful payment, the customer is redirected to a receipt page.
* The plugin supports IPN (Instant Payment Notification) from Malipo/MobiPay. When a payment is completed or failed, Malipo will POST to your site's IPN endpoint, and the order status will be updated automatically.
* Admins can view all Malipo transactions and their statuses under WooCommerce > Malipo Transactions.

== IPN/Callback URL ==

* Your IPN endpoint is displayed in the Malipo settings. Copy this URL and add it to your Malipo/MobiPay dashboard to receive payment status updates.
* Example: `https://yourdomain.com/wp-json/malipo/v1/ipn`

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce Blocks? =
Yes, it supports both classic and block-based checkout flows.

= How do I get my Merchant Account ID? =
Log in to your Malipo dashboard and copy your Merchant Account ID from your account settings.

= How do I receive payment notifications? =
Copy the IPN/Callback URL from the Malipo settings page and add it to your Malipo/MobiPay dashboard.

== Screenshots ==
1. Malipo payment option at checkout
2. Hosted checkout popup
3. Malipo-branded receipt page
4. Malipo Transactions admin report

== Changelog ==
= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.0.0 =
First public release.

== License ==
GPL v2 or later