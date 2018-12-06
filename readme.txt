=== Qualpay Payment Plugin for WooCommerce ===
Contributors: developerqualpay
Tags: Payment Gateway, Recurring Billing, Payment, Tokenization, Credit Cards
Requires at least: 4.4.0
Tested up to: 5.0
Requires PHP: 7.0

Qualpay Payment Plugin for WooCommerce is a complete payment solution for your WooCommerce Store.

== Description ==
Qualpay provides a merchant account and payment gateway with Customer Vault and Recurring Billing.  Get started with Qualpay today; https://www.qualpay.com/get-started.

== Installation ==
Installing the Qualpay Plugin from the WordPress Dashboard
*WooCommerce installation is required in addition to the Qualpay Payment Plugin for WooCommerce.
1. Login to your WordPress dashboard.
2. Click on the Plugins menu.
3. Click on Add New.
4. Press Choose File and select qualpay.zip.
5. Press Open, then press Install Now.
6. Press Activate Now.

== Screenshots ==
1. Add Subscription (recurring) product
2. Configure Plugin
3. Qualpay Payment Fields

== Changelog ==
2.0.6 Added support for recurring billing

3.0.1 Added support for Hybrid Cart (subscription item and single item purchase in the same cart), allow two subscriptions in the same cart,  manage production and sandbox security keys in the configuration panel.

3.0.2  Added Qualpay webhooks for syncing Qualpay transactions with WooCommerce order status.  Any follow-on transaction actions are taken from Qualpay Manager as updates to WooCommerce orders.
        Added void, refund and capture transaction actions for WooCommerce orders.

3.0.3  Added detailed customer messaging in the case credit card data does not pass front end validation.
Bugfix: When Terms and Conditions acceptance is required ensure that the acceptance box is checked.