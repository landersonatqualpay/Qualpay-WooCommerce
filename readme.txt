
=== Qualpay Payment Plugin for WooCommerce ===
Contributors: developerqualpay
Tags: Payment Gateway, Recurring Billing, Payment, Tokenization, Credit Cards
Requires at least: 4.4.0
Tested up to: 5.4.4
Stable tag: 3.1.4
Requires PHP: 7.0
Version : 3.1.4

Qualpay Payment Plugin for WooCommerce is a complete payment solution for your WooCommerce Store.

== Description ==
Qualpay provides a merchant account and payment gateway with Customer Vault and Recurring Billing.  Get started with Qualpay today; <a href="https://qualpay.com/get-started/woocommerce" target="_BLANK">https://qualpay.com/get-started/woocommerce</a>.

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

3.0.4 Fixed a bug for merchant where when you create a manual order you can now see an order total and displays the embedded fields, they can now enter the card id and send through the credit card details in the Transaction log.

3.0.5 Fixed a bug for log error.

3.0.6 bugfix: Manual orders can be processed while subscription is in cart.

3.0.7 Fixed a bug for counting a total including discounts in invoice payment and fixed a php error log.

3.0.8 You can now add a Qualpay security logo next to the title on the Qualpay payment frame. For logged in customers (not checking out as a guest) , records will be written to the Qualpay customer vault even when recurring billing is not enabled.

3.0.9 Qualpay Customer Vault entries from WooCommerce will include a customer ID formatted as firstname+lastname+3 digit random number and the phone number and company name as input by your customer during checkout.
ACH Payments are now supported.  Contact Qualpay at support@qualpay.com to have ACH enabled on your account.  You must use “capture charge immediately” to process ACH payments through WooCommerce.
Customers can now pay with a saved card-on-file.  If you enable cards-on-file your customer will be presented with a “Save Card” checkbox.  Upon the next checkout, the saved card will be presented as a payment option.  Not available for guest checkout, and we do recommend that you enforce strong passwords if you decide to opt-in to this feature.

3.1 bugFix for count total on variable product add in manual order create.

3.1.1 Refund button only displays when applicable, added support for daily recurring plans and subscriptions, optional requirement to collect CVV for customers paying with a saved card-on-file, added support for manual orders that include a fee but not a product line item, and partial captures from Qualpay Manager are now accurately reflected in the WooCommerce order history.

3.1.2 Updated subscriptions  to comply with Visa and MasterCard regulations for Negative Option Billing. Resolved any Qualpay Plugin conflicts with updates.

3.1.3 Bug fix for shipping charge.

3.1.4 Support google Advanced noCaptcha & invisible Captcha version 6.1.5 plugin
