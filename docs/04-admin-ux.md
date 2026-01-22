# 04 Admin UX

## Settings Page

### HubSpot Connection
- HubSpot Portal ID
- Private App Access Token (`pat-...`)
- Test HubSpot Connection button
- Note: OAuth/MCP auth apps are not used
- Encryption key required for token storage (`BB_HUBSPOT_ENCRYPTION_KEY`)

### Captcha Provider
- Provider type (None, reCAPTCHA v3, Turnstile, hCaptcha)
- Site key
- Secret key
- Minimum score (reCAPTCHA v3)
- Expected action (reCAPTCHA v3)
- Per-form override allowed in CPT

### HubSpot Form IDs
- Global list of Form IDs (label | GUID per line)
- Used as a dropdown selector per form

### Spam and Rate Limits (Pro Add-on)
- Enable business-email blocking
- Source lists and cache refresh interval
- Rate limit policy (requests/min, burst)
- Optional honeypot toggle

### Logging (Pro Add-on)
- Enable request logging
- Redact PII in logs (default on)
- Log level (error, warning, info, debug)

### dataLayer / Analytics (Pro Add-on)
- Enable dataLayer events
- Default event name
- Fields to include (safe list)

### UTM Capture (Pro Add-on)
- Enable UTM capture
- Fields to capture (utm_source, utm_medium, utm_campaign, utm_term, utm_content)

## Form Library (CPT List)

### List View
- Columns: Title, HubSpot Form ID, Status, Updated, Shortcode, Block Support
- Filters: Status, Captcha Required, Block Email Domains
- Bulk actions: Enable/disable captcha, export schema JSON
- Free tier allows only 1 form (creation disabled after limit)

### Form Editor (CPT)

#### Form Identity
- HubSpot Form ID (selected from global list)
- Form name/label
- Status (Draft/Published)

#### Field Schema Editor
- Field list with name, label, type, required, validation rules
- Select field options with ordering
- Field ordering via drag and drop
- Hidden fields editor (key/value)

#### Progressive Profiling
- Hide known fields (renderer only)
- Mapping rules: `{ from, to }`
- Allowed fields list is controlled server-side

#### Consent and Compliance
- Enable marketing consent checkbox
- GDPR region behavior (consent + marketing rules)
- Custom consent copy and subscription type mapping
- Consent text, marketing text, and subscription type ID fields

#### Redirect and Thank You
- Redirect URL
- Append email to redirect
- Thank you message for inline success state

#### Spam Controls
- Block business email domains
- Captcha required
- Allow list/deny list overrides (optional)

## Shortcode Behavior

### Shortcode
`[hubspot_form id="123"]`

### Behavior
- Renders the form for the CPT ID or HubSpot GUID mapping.
- Injects a signed token and schema version.
- If missing or invalid ID, renders a placeholder with an admin-only warning.

## Block Selector Behavior

### Block Selector
- Presents a dropdown of `hubspot_form` CPT entries.
- Optionally displays form preview (read-only).
- Uses the same renderer as the shortcode.
- Optional UI; shortcode remains canonical embedding method.

### Block Attributes (MVP)
- `form_id` (CPT ID reference)
- `block_email_domains` (boolean)
- `redirect_url` and `append_email_to_redirect`
- `thank_you_message`
- `enable_social_selector` and `enabled_social_platforms`
- `hidden_fields`
- `progressive_mappings`
- `hide_known_fields`

## Validation and Submission Rules

- Client-side validation improves UX but is not authoritative.
- REST controller validates against stored schema regardless of visibility.
- `visible_fields` or similar client hints are ignored server-side.
- Missing required fields or invalid formats return field-specific errors.
- Captcha is required when enabled in settings or per-form.
