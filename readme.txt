=== WC Crypto Payments (no fee) ===
Contributors: algolplus
Tags: wooocommerce, bitcoin, cryptocurrency, bitcoin payment, crypto, btc, payments, ethereum
Requires PHP: 5.6
Requires at least: 5.0
Tested up to: 5.8
Stable Tag: 1.0.1
License: GPL v3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

No fees. No registration. No API keys. No middleman. Accept bitcoin, ethereum, litecoin, and more.

== Description ==
Only cryptocurrency gateway that truly takes out the middleman. 
Accept all major cryptocurrencies directly to your own wallets for free.
No middleman fees, everything(required for automatic mode) is hosted on your website.
Accept customer payments in bitcoin, ethereum, litecoin and some other cryptocurrencies.

== Supported Cryptocurrencies ==

* Bitcoin - BTC
* Ethereum - ETH
* Litecoin - LTC
* Binance Coin - BNB
* Tether - USDT
* USD Coin - USDC

== Installation ==

* Install and activate
* Navigate to WooCommerce » Settings » Payments
* Click Manage for "Crypto Payments", Select "Enable crypto payments", and press "Save changes"
* Select your cryptocurrency (shown as new subsecton), enter in valid wallet addresses, and save

== Features ==

* Automatic order processing/confirmation (allow to adjust # of confirmations)\*
* Real-time crypto valuation
* MPK Support - Unique address for every order\*
* QR code, wallet addess and amount displayed in "thank you" page and emails
* Supports all WooCommerce fiat currencies
\* varies by cryptocurrency

== Security ==

Disable сurrencies tabs: after configuring currencies - you can disable editing them in the WordPress admin area. You must add the following code to your wp-config file:

<code>define( 'CRYPTOPAYMENTS_DISABLE_CURRENCY_EDITOR', true );</code>

== Screenshots ==

1. Selecting your cryptocurrency
2. Adding new addresses
3. Checkout page with cryptocurrency
4. Customer thank-you page

== Changelog ==

= 1.0.1 =
* Fixed critical bug - "HD Wallet" mode didn't work

= 1.0.0 =
* Initial version