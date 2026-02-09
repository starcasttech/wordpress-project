=== WC Capitec Pay via Ozow Gateway ===
Contributors: Ozow
Tags: woocommerce, payment request, online refund
Requires at least: 6.2
Tested up to: 6.5.4
Requires PHP: 7.2 or letter
Stable tag: 1.2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the WooCommerce extension to receive instant EFT payments from customers using the South African Ozow payments provider using Capitec bank.

== Description ==

Ozow is one of South Africa’s fastest growing fintechs changing the finance landscape by creating cashless and contactless payment solutions that speak to the heart of your customer’s needs.
The Ozow extension for WooCommerce enables you to accept payments via one of South Africa’s most popular payment gateways.

== Ozow Service Usage ==

= This plugin relies on the following Ozow service(s): = 

* Description: Ozow is a payment processing service used for handling payment status, refunds and tokenization.
* Service URL: https://api.ozow.com/
* Terms of Use: https://ozow.com/terms-and-conditions
* Privacy Policy: https://ozow.com/privacy-policy

Please be aware that by using this plugin, certain data may be transmitted to Ozow as outlined in their terms of use and privacy policy.

== Frequently Asked Questions ==

= Does this require a Ozow merchant account? =

Yes! A Ozow merchant account, merchant key and merchant ID are required for this gateway to function.

== Installation ==

= Installation Steps =

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress
3. Look for the \"WC Capitec Pay via Ozow Gateway\" plugin and click on \"Activate\".
4. Configure Ozow in your WooCommerce settings:
	4.1 Hover over \"WooCommerce\" link in the left menu and click on the \"Settings\" link.
	4.2 Click on the \"Checkout\" tab on the top of the screen.
	4.3 Scroll down to the \"Payment Gateways\" section and click on the \"Settings\" button for Capitec Pay via Ozow Gateway.
	4.4 Configure the title and description that will be displayed to users on checkout and fill in your merchant settings (these can be retrieved from the Ozow Merchant Admin site).

= PLEASE NOTE =	

Ozow currently only accepts payments in ZAR (South African Rand). Please ensure that your WooCommerce currency is set to South African Rand (R). The Ozow for WooCommerce
plugin will automatically disable itself if it detects a currency that is not supported.

* Any edits made to the plugin to bypass certain features/restraints are done so at your own risk, Ozow will not be held responsible or accountable for any payments made
or transactions failing as a result after a plugin provided by us has been edited. If you encounter any bugs or errors or would like to request a feature in the plugin
please let us know at info@ozow.com *

For any information or support please contact us at support@ozow.com

== Screenshots ==

== Changelog ==

= 1.0.0 - 2023-09-01 =
* Initial release

= 1.1.0 - 2024-01-19 = 
* Added: Compatibility with WooCommerce block-based checkout.
* Added: High-performance Order Storage (“HPOS”) Support.

= 1.2.0 - 2024-06-08
* Added: Implemented functionality to resend payment links within the administrative backend.