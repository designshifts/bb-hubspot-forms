# STATUS

## Done
- Plugin bootstrap and class loader (`bb-hubspot-forms/bb-hubspot-forms.php`, `src/Plugin.php`).
- Settings system with admin UI (`src/Settings.php`, `src/Admin/SettingsPage.php`).
- `hubspot_form` CPT and meta schema registration (`src/Forms/CPT.php`).
- Shortcode renderer with signed token (`src/Forms/Renderer.php`).
- REST submit endpoint with token verification, rate limiting, and captcha check (`src/REST/SubmitController.php`).
- HubSpot API client (`src/HubSpot/Client.php`).
- Spam utilities: domain blocker + captcha verifier (`src/Spam/*`).
- Frontend submit script (`assets/js/frontend.js`).
- Legacy plugin archived at `bb-hubspot-forms/legacy/thinkific-hubspot-forms`.

## Needs to Be Done (Basic)
- CPT form editor UI for schema/hidden fields/consent.
- Block selector (Gutenberg) for picking a CPT form.
- Frontend reCAPTCHA v3 token retrieval.
- Packaging/versioning, activation hooks, and uninstall cleanup.
- Admin list columns + shortcode column for CPT list view.

## Needs to Be Done (Pro Add-on)
- dataLayer event push (configurable fields).
- UTM capture rules (respect settings; map into submission).
- Business email domain blocking and list updates.
- Pro settings UI and gating hooks.

## Domain Lists (Source of Truth)
### Free email domains
- Package: `free-email-domains` (npm)
- Usage example:
```
const freeEmailDomains = require('free-email-domains')
freeEmailDomains.includes('gmail.com') // true
```

### Disposable domains
- Source: `disposable-email-domains` (blocklist file)
- PHP example:
```
function isDisposableEmail($email, $blocklist_path = null) {
    if (!$blocklist_path) $blocklist_path = __DIR__ . '/disposable_email_blocklist.conf';
    $disposable_domains = file($blocklist_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $domain = mb_strtolower(explode('@', trim($email))[1]);
    return in_array($domain, $disposable_domains);
}
```
