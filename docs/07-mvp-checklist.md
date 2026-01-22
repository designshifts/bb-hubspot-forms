# 07 MVP Checklist

## Security
- Signed form tokens (HMAC + expiry + schema version).
- Rate limiting with burst protection.
- Captcha verification enforced when enabled.
- SSL verification enabled for all HubSpot requests.
- No CRM read endpoints in MVP.
- PII redaction in logs by default.
- Reject token expiry or schema mismatch.

## Settings Page
- HubSpot portal ID + private token.
- Captcha provider + keys.
- Domain blocking toggles and list sources.
- Logging level and dataLayer toggle.
- UTM capture toggle and field list.

## Forms (CPT)
- `hubspot_form` CPT registered.
- Form library list view with shortcode column.
- Form editor for schema, hidden fields, consent, redirects.
- Free tier limited to 1 form.

## Rendering
- Shortcode `[hubspot_form id="123"]`.
- Optional block selector referencing CPT entries.
- Renderer injects token + schema version.

## Submission
- REST controller validates against CPT schema.
- Normalized error responses.
- HubSpot payload mapping with consent and context.
- UTM fields accepted and mapped when enabled.

## Spam Controls
- Business-email domain blocking with list sources.
- Optional honeypot + time-to-submit heuristics.

## UX
- Clean admin UI for settings and CPT editor.
- Clear error messaging in frontend.

## Release Gates
- Manual smoke test in WP 6.9+.
- Submission succeeds to HubSpot.
- Invalid fields rejected server-side.

