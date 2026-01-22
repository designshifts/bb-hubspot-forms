# BB HubSpot Forms

Security-first HubSpot forms for WordPress.

## What it does
- Provides a CPT-backed HubSpot form library.
- Renders forms via shortcode.
- Submits securely to HubSpot via server-side REST.
 - Uses HubSpot Private App access tokens (no OAuth).

## Who it’s for
- Site builders
- Marketers
- WordPress developers

## Status
- Stability: Alpha
- WordPress: tested up to 6.9
- PHP: 8.0+

## Install
1. Download ZIP or clone into `/wp-content/plugins/bb-hubspot-forms`
2. Activate in WordPress
3. Configure in: Settings -> BB HubSpot Forms

## Settings Overview
- HubSpot Portal ID and Private App Access Token
- Test HubSpot Connection button
- CAPTCHA (reCAPTCHA v3, Turnstile, hCaptcha, or disabled)
- Form IDs list (used as dropdown per form)

## Notes
- The Test button uses current field values; click Save Changes to persist.
- Tokens are stored server-side and never exposed to the browser.

## Secure token storage
Define an encryption key in `wp-config.php` to enable encrypted storage of the HubSpot Private App token:

```
define( 'BB_HUBSPOT_ENCRYPTION_KEY', 'put-a-long-random-string-here' );
```

## Docs
- `/docs`

## Roadmap (light)
- [ ] Basic form editor UI
- [ ] Block selector

## Contributing
Issues + PRs welcome. Please include:
- WP version
- plugin version
- steps to reproduce

## License
GPL-2.0-or-later
