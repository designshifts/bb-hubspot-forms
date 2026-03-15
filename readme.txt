=== BB HubSpot Forms ===
Contributors: coffeemugger
Tags: hubspot, forms, marketing, leads
Requires at least: 6.0
Tested up to: 6.9.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security-first HubSpot forms for WordPress, with native markup and server-side submission (no iframes).

== Description ==
BB HubSpot Forms helps you build fast, secure HubSpot form experiences directly inside WordPress.

Instead of embedding HubSpot iframes, the plugin uses native WordPress markup and submits server-side. That gives you cleaner front-end control, safer credential handling, and easier form management for editors.

Why teams use this plugin:
* Create and manage forms as a dedicated WordPress content type
* Pull form definitions from HubSpot and sync fields into the editor
* Render forms with a shortcode (`[bb_hubspot_form id="123"]`)
* Submit securely to HubSpot from your server (not from public browser credentials)
* Enable spam protection with reCAPTCHA v3
* Configure GDPR consent and optional marketing opt-in text
* Optionally use default styling, or fully style with your own CSS

Connection and security highlights:
* Uses HubSpot Private App token auth (no OAuth flow required)
* Includes a "Test Connection" tool in settings
* Supports encrypted token storage at rest when an encryption key is configured
* Keeps sensitive credentials out of browser-exposed code

Recommended one-time setup for encrypted token storage in `wp-config.php`:
`define( 'BB_HUBSPOT_ENCRYPTION_KEY', 'put-a-long-random-string-here' );`

== Installation ==
1. Upload the plugin files to `/wp-content/plugins/bb-hubspot-forms`, or install via WordPress.
2. Activate the plugin.
3. Configure via Settings -> BB HubSpot Forms.

== Frequently Asked Questions ==
= How is this different from HubSpot embed forms? =
This plugin renders native WordPress form markup instead of embedding an iframe. You get better styling control, editor-friendly management, and server-side submission handling.

= Do I need OAuth or MCP Auth Apps? =
No. BB HubSpot Forms uses HubSpot Private App access tokens (`pat-...`) and does not require OAuth.

= Does it support HubSpot Private App tokens? =
Yes. Add your Portal ID and Private App token in Settings -> BB HubSpot Forms, then run the built-in connection test.

= Is the HubSpot token encrypted in the database? =
Yes, if you define `BB_HUBSPOT_ENCRYPTION_KEY` in `wp-config.php`. Without that key, secure token storage is limited, so configuring the key is strongly recommended.

= Can I sync fields from an existing HubSpot form? =
Yes. In the HubSpot Form editor, choose a HubSpot form and click "Sync fields from HubSpot." You can then reorder, relabel, or hide fields in WordPress.

= How do I place a form on a page? =
Use the generated shortcode shown in the form editor, for example: `[bb_hubspot_form id="123"]`.

= Does it include spam and consent controls? =
Yes. You can enable reCAPTCHA v3, configure score/action checks, and set GDPR consent text plus optional marketing opt-in.

= Can I use my theme styles instead of plugin styles? =
Yes. Disable "Enable Default Form Styles" in settings and style the form classes in your theme or custom CSS.

== Privacy ==
This plugin sends form submissions to HubSpot.
If CAPTCHA is enabled, verification may send data to the CAPTCHA provider.
The site owner controls all configuration and can disable CAPTCHA.

== Screenshots ==
1. Main settings page with HubSpot connection, security status, and section navigation.
2. CAPTCHA settings for provider selection, score threshold, action name, and keys.
3. GDPR consent settings with required processing consent and optional marketing opt-in text.
4. Required, recommended, and optional HubSpot scopes reference panel.
5. Appearance settings with default style toggle and available CSS class names.
6. HubSpot Forms admin list table in WordPress.
7. HubSpot Form editor view with form sync, shortcode, and field controls.

== Changelog ==
= 1.1.0 =
* Security: Fix CAPTCHA bypass — token is now required when a provider is configured.
* Security: Validate form post type before processing submission.
* Security: Encrypt CAPTCHA secret key at rest using the same mechanism as the HubSpot token.
* Security: Replace raw HubSpot API error messages with a generic user-facing message.
* Fix: Uninstall now removes all hubspot_form CPT posts and their post meta.
* Fix: Add `bbhubspot_forms_submitted` action hook after successful submission (replaces private attribution integration).

= 1.0.0 =
* Initial release.
