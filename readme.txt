=== WP SecureSubmit ===
Contributors: markhagan
Tags: donation, payment, buy now, securesubmit, heartland
Requires at least: 3.0.1
Tested up to: 6.7.1
Stable tag: trunk
License: GPLv2
License URI: https://github.com/hps/heartland-wordpress-plugin/blob/master/LICENSE.md

SecureSubmit allows merchants using Heartland Payment Systems to take PCI-Friendly donations on their WordPress site.

== Description ==

SecureSubmit makes it simple to start taking tokenized, PCI-Friendly payments (no card data ever touches your web server
in any way) without making your visitors leave your site and without using iFrames.

Features of SecureSubmit:

*   Only two configuration fields: public and secret API key
*   Simple to isntall and configure
*   "Buy Now" options supported
*   "Donate Now" is default, tagless option
*   Fully supported by Heartland Payment Systems
*   Available as a form or as a modal window


== Installation ==

1. Upload `SecureSubmit` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `[securesubmit]` in your templates

== Frequently Asked Questions ==

= How do I get started? =

Get your Certification (Dev) API keys by creating an account by [Clicking Here](here: https://developer.heartlandpaymentsystems.com/SecureSubmit/ "Heartland SecureSubmit")

= Does this support sites using the Gutenberg editor?

This plugin's features should work without issue using the Gutenberg editor except for
the button builder. To use the button builder, the classic editor is currently required.

== Screenshots ==

1. This is an example of a "Buy Now" button configuration.
2. This shows what a "Buy Now" modal looks like.
3. This screenshot shows a "Donate Now" (the default [securesubmit] tag) view.
4. This screenshot shows a non-modal view.

== Changelog ==
= 1.5.18 =
* Vulnerability fixes

= 1.5.17 =
* Bugfix: Corrected gateway endpoints

= 1.5.16 =
* Bugfix: Fix tokenization when modal is used

= 1.5.15 =
* Update of securesubmit.js to globalpayments.js
* Bugfix: Fixed an issue where transaction ID isn't displayed correctly

= 1.5.14 =
* Replaced deprecated method

= 1.5.13 =
* Add amount cap feature/filter

= 1.5.12 =
* Added Transaction ID to thank you email

= 1.5.11 =
* Correct checkbox display on non-modal form

= 1.5.10 =
* Correct automatic amount change feature in modal and non-modal forms

= 1.5.9 =
* Fix shipping name parsing in modal form

= 1.5.8 =
* Fix settings page issue with tinymce on save which occurred on some systems

= 1.5.7 =
* Fix amount parsing when `,` separator is used
* Fix issue on error where `grecaptcha` is not on page when ReCaptcha is disabled
* Update non-modal form to not have past expiration years in dropdown

= 1.5.6 =
* Fix modal issue when amount is specified
* Improve admin styling/messaging

= 1.5.5 =
* Fix issue with shipping fields when requirebilling="true"

= 1.5.4 =
* Fix issue with additional information in modal form

= 1.5.3 =
* Updated shipping and billing information (Card Holder Name, Address, City and Zip Code) as required fields.
* Added support for PHP 7
* Test against PHP 7.0.10

= 1.5.2 =
* Updated Heartland PHP SDK
* Test against Wordpress 4.7

= 1.5.1 =
* Corrected minor issue that only appeared with debug mode enabled. Warning on button builder
* Fix compatibility regression with older versions of PHP

= 1.5.0 =
* Fixed button builder tinyMCE conflict when downloaded from github issue: 37

= 1.4.9 =
* Enabled Velocity check by default

= 1.4.8 =
* Added Google reCaptcha (non-modal only)

= 1.4.7 =
* Add velocity checking for anti-fraud
* Add velocity fraud check settings

= 1.4.6 =
* Clear payment fields in modal on tokenize error and after postback to WP Ajax handler

= 1.4.5 =
* Fix "Same as Shipping" for billing address not operating correctly
* Remove unncessary session_start calls.

= 1.4.4 =
* Ensure DC is present in all state select fields

= 1.4.3 =
* Fix issue with null product id on db insert

= 1.4.2 =
* Fix long form to allow public_key attribute

= 1.4.1 =
* Update certification url to support PCI DSS 3.1

= 1.4.0 =
* Update Heartland PHP SDK
* Test against Wordpress 4.3

= 1.3.10 =
* Add check for `$.browser` which has been removed in recent version of jQuery

= 1.3.9 =
* Removed more debug text :/

= 1.3.8 =
* Removed debug text :/

= 1.3.7 =
* Fixed bug with button field types in non-modal forms
* Removed more index warnings

= 1.3.6 =
* Fixed spacing bug with button builder

= 1.3.5 =
* Fixed PHP warnings for shortcode attribute edge cases

= 1.3.4 =
* Matching Wordpress.org and GitHub version numbers

= 1.3.3 =
* Fixed Subject Line variable swapping
* Fixed render long form in button builder

= 1.3.2 =
* Ensure SDK isn't already loaded

= 1.3.1 =
* Made State an option for multi-national
* Added e-mail reciept for long-form
* Allowing non-numeric characters in card number
* Amount field is automatically populated based on what value is selected from radio buttons

= 1.3.0 =
* Added Button Builder to WordPress WYSIWYG Editor
* Cleaned up long-form
::q
= 1.2.2 =
* Updated payment email to use configurable settings.

= 1.2.1 =
* Updated version of DB

= 1.2.0 =
* Added Admin view of transactions.
* Default amount can now be modified.
* FAQ section added to admin.
* Plugin now has a side menu option in the admin.
* Hiding amount fields after submission of payment.
* Upgraded version of jQuery tokenization library.
* From email address is now configurable from settings.

= 1.1.6 =
* Allowing non-modal users to configure the header text.
* amountdefault attribute added for default donation amounts.
* productimage can now be set to "none".

= 1.1.5 =
* Internet Explorer 6-9 placeholder fix.
* CSS Fix for additional style clearing.

= 1.1.4 =
* Updated styles to clear textbox attributes.

= 1.1.3 =
* Added work around for sites running line break conversion plugins.

= 1.1.2 =
* Added RAW tags to ignore line breaks.

= 1.1.1 =
* Fixed multiple buttons on the same page.
* Added dropdown as an additional option type.

= 1.1.0 =
* Added some screenshots of the plugin in action.
* Added ability to change button text.
* Added additional option types.

= 1.0.0 =
* Initial Release

== Tag Options ==

1. modal="true/false"
2. productid="value" (this is required for non-donation)
3. productname="value"
4. buttontext="value"
5. requirebilling="value"
6. requireshipping="value"
7. productimage="value" (default is a picture of a gift box)
