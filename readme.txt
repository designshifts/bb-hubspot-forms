=== BB HubSpot Forms ===
Contributors: betterbuilds
Tags: hubspot, forms, marketing, leads
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security-first HubSpot forms for WordPress.

== Description ==
BB HubSpot Forms provides a secure, CPT-backed form system that submits to HubSpot from your WordPress site.

Features:
* CPT-backed form library
* Shortcode rendering
* Server-side submission to HubSpot
* Private App token authentication (no OAuth required)

Settings overview:
* HubSpot Portal ID and Private App Access Token
* Test HubSpot Connection button
* CAPTCHA (reCAPTCHA v3, Turnstile, hCaptcha, or disabled)
* Form IDs list (used as dropdown per form)

Secure token storage:
Define an encryption key in `wp-config.php` to enable encrypted storage of the HubSpot Private App token:
`define( 'BB_HUBSPOT_ENCRYPTION_KEY', 'put-a-long-random-string-here' );`

== Installation ==
1. Upload the plugin files to `/wp-content/plugins/bb-hubspot-forms`, or install via WordPress.
2. Activate the plugin.
3. Configure via Settings -> BB HubSpot Forms.

== Frequently Asked Questions ==
= Does it support HubSpot private app tokens? =
Yes. Use your private app token in the settings page.

= Do I need MCP Auth Apps or OAuth? =
No. This plugin uses HubSpot Private App access tokens (pat-...). OAuth or MCP Auth Apps are not required.

= Does it encrypt the token in the database? =
Yes, when you define `BB_HUBSPOT_ENCRYPTION_KEY` in `wp-config.php`, the token is stored encrypted at rest.

== Screenshots ==
1. Settings page

== Changelog ==
= 0.1.0 =
* Initial release.
